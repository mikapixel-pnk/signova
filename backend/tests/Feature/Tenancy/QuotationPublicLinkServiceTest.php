<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Services\Quotation\QuotationPublicLinkService;
use App\Tenancy\TenantContext;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationPublicLinkServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_create_public_link_returns_raw_token_and_stores_only_hash(): void
    {
        $workspace = $this->workspace(
            'public-link-service@example.test',
            'Public Link Service'
        );

        $quotationId = $this->insertQuotationWithVersion(
            $workspace['tenant_id'],
            'Q-PUBLIC-SERVICE'
        );

        $this->setTenantContext(
            $workspace
        );

        $result = app(
            QuotationPublicLinkService::class
        )->createForQuotation(
            $quotationId
        );

        $this->assertNotSame(
            '',
            $result['token']
        );

        $this->assertStringStartsWith(
            rtrim(
                (string) config(
                    'signova.public_quotation.base_url'
                ),
                '/'
            ) . '/PENAWARAN-',
            $result['public_url']
        );

        $this->assertStringEndsWith(
            $result['token'],
            $result['public_url']
        );

        $row = DB::table(
            'quotation_public_links'
        )
            ->where(
                'quotation_id',
                $quotationId
            )
            ->first();

        $this->assertNotNull(
            $row
        );

        $this->assertSame(
            hash(
                'sha256',
                $result['token']
            ),
            $row->token_hash
        );

        $this->assertNotSame(
            $result['token'],
            $row->token_hash
        );
    }

    public function test_presented_prefixed_token_resolves_public_link(): void
    {
        $workspace = $this->workspace(
            'public-link-resolve@example.test',
            'Public Link Resolve'
        );

        $quotationId = $this->insertQuotationWithVersion(
            $workspace['tenant_id'],
            'Q-PUBLIC-RESOLVE'
        );

        $this->setTenantContext(
            $workspace
        );

        $service = app(
            QuotationPublicLinkService::class
        );

        $created = $service->createForQuotation(
            $quotationId
        );

        $presented = basename(
            $created['public_url']
        );

        $resolved =
            $service->resolveByPresentedToken(
                $presented
            );

        $this->assertSame(
            $created['link']->id,
            $resolved->id
        );

        $this->assertSame(
            $quotationId,
            $resolved->quotation_id
        );
    }

    public function test_expired_public_link_cannot_be_resolved(): void
    {
        $workspace = $this->workspace(
            'public-link-expired@example.test',
            'Public Link Expired'
        );

        $quotationId = $this->insertQuotationWithVersion(
            $workspace['tenant_id'],
            'Q-PUBLIC-EXPIRED'
        );

        $this->setTenantContext(
            $workspace
        );

        $service = app(
            QuotationPublicLinkService::class
        );

        $created = $service->createForQuotation(
            $quotationId,
            now()->subMinute()
        );

        $this->expectException(
            ModelNotFoundException::class
        );

        $service->resolveByPresentedToken(
            $created['token']
        );
    }

    public function test_revoked_public_link_cannot_be_resolved(): void
    {
        $workspace = $this->workspace(
            'public-link-revoked@example.test',
            'Public Link Revoked'
        );

        $quotationId = $this->insertQuotationWithVersion(
            $workspace['tenant_id'],
            'Q-PUBLIC-REVOKED'
        );

        $this->setTenantContext(
            $workspace
        );

        $service = app(
            QuotationPublicLinkService::class
        );

        $created = $service->createForQuotation(
            $quotationId
        );

        DB::table('quotation_public_links')
            ->where(
                'id',
                $created['link']->id
            )
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);

        $this->expectException(
            ModelNotFoundException::class
        );

        $service->resolveByPresentedToken(
            $created['token']
        );
    }


    public function test_tenant_cannot_create_public_link_for_foreign_quotation(): void
    {
        $first = $this->workspace(
            'public-link-local@example.test',
            'Public Link Local'
        );

        $second = $this->workspace(
            'public-link-foreign@example.test',
            'Public Link Foreign'
        );

        $foreignQuotationId =
            $this->insertQuotationWithVersion(
                $second['tenant_id'],
                'Q-PUBLIC-FOREIGN'
            );

        $this->setTenantContext(
            $first
        );

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        app(
            QuotationPublicLinkService::class
        )->createForQuotation(
            $foreignQuotationId
        );
    }

    public function test_draft_quotation_cannot_create_public_link(): void
    {
        $workspace = $this->workspace(
            'public-link-draft@example.test',
            'Public Link Draft'
        );

        $quotationId =
            $this->insertQuotationWithVersion(
                $workspace['tenant_id'],
                'Q-PUBLIC-DRAFT'
            );

        DB::table('quotations')
            ->where('id', $quotationId)
            ->update([
                'status' => 'DRAFT',
                'sent_at' => null,
                'updated_at' => now(),
            ]);

        $this->setTenantContext(
            $workspace
        );

        $this->expectException(
            \App\Exceptions\Quotation\InvalidQuotationTransitionException::class
        );

        app(
            QuotationPublicLinkService::class
        )->createForQuotation(
            $quotationId
        );
    }


    public function test_public_url_base_is_environment_configurable(): void
    {
        $workspace = $this->workspace(
            'public-link-config@example.test',
            'Public Link Config'
        );

        $quotationId =
            $this->insertQuotationWithVersion(
                $workspace['tenant_id'],
                'Q-PUBLIC-CONFIG'
            );

        $this->setTenantContext(
            $workspace
        );

        config()->set(
            'signova.public_quotation.base_url',
            'https://staging-public.example.test/q'
        );

        $created = app(
            QuotationPublicLinkService::class
        )->createForQuotation(
            $quotationId
        );

        $this->assertStringStartsWith(
            'https://staging-public.example.test/q/PENAWARAN-',
            $created['public_url']
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
                'Public Link Service Test',
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

    private function setTenantContext(
        array $workspace
    ): void {
        app(
            TenantContext::class
        )->set(
            $workspace['tenant_id'],
            $workspace['user_id']
        );

        app(
            \App\Tenancy\BusinessContext::class
        )->set(
            $workspace['tenant_id'],
            $workspace['business_id'],
            $workspace['user_id']
        );
    }

    private function insertQuotationWithVersion(
        string $tenantId,
        string $number
    ): string {
        $customerId =
            (string) Str::ulid();

        DB::table('customers')->insert([
            'id' => $customerId,
            'tenant_id' => $tenantId,
            'business_id' =>
                $this->businessIdForTenant(
                    $tenantId
                ),
            'type' => 'COMPANY',
            'code' => 'C-' . $number,
            'name' => 'Customer ' . $number,
            'payment_terms_days' => 0,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $quotationId =
            (string) Str::ulid();

        DB::table('quotations')->insert([
            'id' => $quotationId,
            'tenant_id' => $tenantId,
            'business_id' =>
                $this->businessIdForTenant(
                    $tenantId
                ),
            'quotation_number' => $number,
            'customer_id' => $customerId,
            'status' => 'SENT',
            'source' => 'MANUAL',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $versionId =
            (string) Str::ulid();

        DB::table('quotation_versions')
            ->insert([
                'id' => $versionId,
                'tenant_id' => $tenantId,
                'business_id' =>
                    $this->businessIdForTenant(
                        $tenantId
                    ),
                'quotation_id' =>
                    $quotationId,
                'revision_no' => 1,
                'subtotal' => 100000,
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => 100000,
                'currency' => 'IDR',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('quotations')
            ->where(
                'id',
                $quotationId
            )
            ->update([
                'current_version_id' =>
                    $versionId,
                'updated_at' => now(),
            ]);

        return $quotationId;
    }
}
