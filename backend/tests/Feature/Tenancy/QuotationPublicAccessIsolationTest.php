<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationPublicAccessIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_public_link_cannot_mix_quotation_and_version_across_tenants(): void
    {
        $first = $this->workspace(
            'public-link-first@example.test',
            'Public Link First'
        );

        $second = $this->workspace(
            'public-link-second@example.test',
            'Public Link Second'
        );

        $quotationId = $this->insertQuotation(
            $first['tenant_id'],
            'Q-PUBLIC-FIRST'
        );

        $foreignQuotationId = $this->insertQuotation(
            $second['tenant_id'],
            'Q-PUBLIC-SECOND'
        );

        $foreignVersionId = $this->insertVersion(
            $second['tenant_id'],
            $foreignQuotationId
        );

        $this->expectException(
            QueryException::class
        );

        DB::table('quotation_public_links')
            ->insert([
                'id' => (string) Str::ulid(),
                'tenant_id' =>
                    $first['tenant_id'],
                'business_id' =>
                    $this->businessIdForTenant(
                        $first['tenant_id']
                    ),
                'quotation_id' =>
                    $quotationId,
                'quotation_version_id' =>
                    $foreignVersionId,
                'token_hash' =>
                    hash(
                        'sha256',
                        'cross-tenant-public-link'
                    ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function test_public_link_token_hash_must_be_unique(): void
    {
        $workspace = $this->workspace(
            'public-link-token@example.test',
            'Public Link Token'
        );

        $quotationId = $this->insertQuotation(
            $workspace['tenant_id'],
            'Q-PUBLIC-TOKEN'
        );

        $versionId = $this->insertVersion(
            $workspace['tenant_id'],
            $quotationId
        );

        $hash = hash(
            'sha256',
            'same-public-token'
        );

        $this->insertPublicLink(
            $workspace['tenant_id'],
            $quotationId,
            $versionId,
            $hash
        );

        $this->expectException(
            QueryException::class
        );

        $this->insertPublicLink(
            $workspace['tenant_id'],
            $quotationId,
            $versionId,
            $hash
        );
    }

    public function test_action_cannot_reference_public_link_from_other_tenant(): void
    {
        $first = $this->workspace(
            'public-action-first@example.test',
            'Public Action First'
        );

        $second = $this->workspace(
            'public-action-second@example.test',
            'Public Action Second'
        );

        $quotationId = $this->insertQuotation(
            $first['tenant_id'],
            'Q-ACTION-FIRST'
        );

        $versionId = $this->insertVersion(
            $first['tenant_id'],
            $quotationId
        );

        $foreignQuotationId = $this->insertQuotation(
            $second['tenant_id'],
            'Q-ACTION-SECOND'
        );

        $foreignVersionId = $this->insertVersion(
            $second['tenant_id'],
            $foreignQuotationId
        );

        $foreignLinkId = $this->insertPublicLink(
            $second['tenant_id'],
            $foreignQuotationId,
            $foreignVersionId,
            hash(
                'sha256',
                'foreign-public-link'
            )
        );

        $this->expectException(
            QueryException::class
        );

        DB::table('quotation_actions')
            ->insert([
                'id' => (string) Str::ulid(),
                'tenant_id' =>
                    $first['tenant_id'],
                'business_id' =>
                    $this->businessIdForTenant(
                        $first['tenant_id']
                    ),
                'quotation_id' =>
                    $quotationId,
                'quotation_version_id' =>
                    $versionId,
                'public_link_id' =>
                    $foreignLinkId,
                'action' => 'VIEW',
                'actor_type' => 'PUBLIC',
                'context' => json_encode([]),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function test_duplicate_public_action_for_same_link_version_is_rejected(): void
    {
        $workspace = $this->workspace(
            'public-action-duplicate@example.test',
            'Public Action Duplicate'
        );

        $quotationId = $this->insertQuotation(
            $workspace['tenant_id'],
            'Q-ACTION-DUP'
        );

        $versionId = $this->insertVersion(
            $workspace['tenant_id'],
            $quotationId
        );

        $linkId = $this->insertPublicLink(
            $workspace['tenant_id'],
            $quotationId,
            $versionId,
            hash(
                'sha256',
                'duplicate-action-link'
            )
        );

        $this->insertAction(
            $workspace['tenant_id'],
            $quotationId,
            $versionId,
            $linkId,
            'VIEW'
        );

        $this->expectException(
            QueryException::class
        );

        $this->insertAction(
            $workspace['tenant_id'],
            $quotationId,
            $versionId,
            $linkId,
            'VIEW'
        );
    }

    private function workspace(
        string $email,
        string $tenantName
    ): array {
        return app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' =>
                'Quotation Public Access Test',
            'email' => $email,
            'password' =>
                'SecurePassword123!',
            'tenant_name' =>
                $tenantName,
        ]);
    }

    private function businessIdForTenant(
        string $tenantId
    ): string {
        $businessId = DB::table(
            'business_profiles'
        )
            ->where(
                'tenant_id',
                $tenantId
            )
            ->where(
                'is_default',
                true
            )
            ->where(
                'status',
                'ACTIVE'
            )
            ->value('id');

        if (!$businessId) {
            throw new \RuntimeException(
                'Active default business not found '
                . 'for tenant ' . $tenantId
            );
        }

        return (string) $businessId;
    }

    private function insertCustomer(
        string $tenantId,
        string $code
    ): string {
        $id = (string) Str::ulid();

        DB::table('customers')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'business_id' =>
                $this->businessIdForTenant(
                    $tenantId
                ),
            'type' => 'COMPANY',
            'code' => $code,
            'name' => $code,
            'payment_terms_days' => 0,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function insertQuotation(
        string $tenantId,
        string $number
    ): string {
        $customerId = $this->insertCustomer(
            $tenantId,
            'C-' . $number
        );

        $id = (string) Str::ulid();

        DB::table('quotations')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'business_id' =>
                $this->businessIdForTenant(
                    $tenantId
                ),
            'quotation_number' => $number,
            'customer_id' => $customerId,
            'status' => 'DRAFT',
            'source' => 'MANUAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function insertVersion(
        string $tenantId,
        string $quotationId
    ): string {
        $id = (string) Str::ulid();

        DB::table('quotation_versions')
            ->insert([
                'id' => $id,
                'tenant_id' => $tenantId,
                'business_id' =>
                    $this->businessIdForTenant(
                        $tenantId
                    ),
                'quotation_id' => $quotationId,
                'revision_no' => 1,
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => 0,
                'currency' => 'IDR',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('quotations')
            ->where('id', $quotationId)
            ->update([
                'current_version_id' => $id,
                'updated_at' => now(),
            ]);

        return $id;
    }

    private function insertPublicLink(
        string $tenantId,
        string $quotationId,
        string $versionId,
        string $tokenHash
    ): string {
        $id = (string) Str::ulid();

        DB::table('quotation_public_links')
            ->insert([
                'id' => $id,
                'tenant_id' => $tenantId,
                'business_id' =>
                    $this->businessIdForTenant(
                        $tenantId
                    ),
                'quotation_id' => $quotationId,
                'quotation_version_id' => $versionId,
                'token_hash' => $tokenHash,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return $id;
    }

    private function insertAction(
        string $tenantId,
        string $quotationId,
        string $versionId,
        string $linkId,
        string $action
    ): void {
        DB::table('quotation_actions')
            ->insert([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'business_id' =>
                    $this->businessIdForTenant(
                        $tenantId
                    ),
                'quotation_id' => $quotationId,
                'quotation_version_id' =>
                    $versionId,
                'public_link_id' => $linkId,
                'action' => $action,
                'actor_type' => 'PUBLIC',
                'context' => json_encode([]),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
