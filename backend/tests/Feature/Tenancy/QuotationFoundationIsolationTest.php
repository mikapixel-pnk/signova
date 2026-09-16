<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationFoundationIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_quotation_cannot_reference_customer_from_other_tenant(): void
    {
        $first = $this->workspace(
            'quotation-first@example.test',
            'Quotation First'
        );

        $second = $this->workspace(
            'quotation-second@example.test',
            'Quotation Second'
        );

        $customerId = $this->insertCustomer(
            $second['tenant_id'],
            'CUSTOMER-FOREIGN'
        );

        $this->expectException(
            QueryException::class
        );

        DB::table('quotations')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $first['tenant_id'],
            'quotation_number' => 'Q-001',
            'customer_id' => $customerId,
            'status' => 'DRAFT',
            'source' => 'MANUAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_quotation_version_cannot_reference_quotation_from_other_tenant(): void
    {
        $first = $this->workspace(
            'version-first@example.test',
            'Version First'
        );

        $second = $this->workspace(
            'version-second@example.test',
            'Version Second'
        );

        $quotationId = $this->insertQuotation(
            $second['tenant_id'],
            'Q-FOREIGN'
        );

        $this->expectException(
            QueryException::class
        );

        DB::table('quotation_versions')
            ->insert([
                'id' => (string) Str::ulid(),
                'tenant_id' =>
                    $first['tenant_id'],
                'quotation_id' =>
                    $quotationId,
                'revision_no' => 1,
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => 0,
                'currency' => 'IDR',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function test_quotation_item_cannot_reference_catalog_item_from_other_tenant(): void
    {
        $first = $this->workspace(
            'item-first@example.test',
            'Item First'
        );

        $second = $this->workspace(
            'item-second@example.test',
            'Item Second'
        );

        $quotationId = $this->insertQuotation(
            $first['tenant_id'],
            'Q-ITEM-001'
        );

        $versionId = $this->insertVersion(
            $first['tenant_id'],
            $quotationId
        );

        $catalogItemId =
            $this->insertCatalogItem(
                $second['tenant_id'],
                'FOREIGN-CATALOG'
            );

        $this->expectException(
            QueryException::class
        );

        DB::table('quotation_items')
            ->insert([
                'id' => (string) Str::ulid(),
                'tenant_id' =>
                    $first['tenant_id'],
                'quotation_version_id' =>
                    $versionId,
                'catalog_item_id' =>
                    $catalogItemId,
                'name' => 'Item Asing',
                'quantity' => 1,
                'pricing_method' =>
                    'STANDARD',
                'unit_price' => 10000,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'amount' => 10000,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function test_current_version_cannot_reference_version_from_other_tenant(): void
    {
        $first = $this->workspace(
            'current-first@example.test',
            'Current First'
        );

        $second = $this->workspace(
            'current-second@example.test',
            'Current Second'
        );

        $quotationId = $this->insertQuotation(
            $first['tenant_id'],
            'Q-CURRENT-001'
        );

        $foreignQuotationId =
            $this->insertQuotation(
                $second['tenant_id'],
                'Q-CURRENT-FOREIGN'
            );

        $foreignVersionId =
            $this->insertVersion(
                $second['tenant_id'],
                $foreignQuotationId
            );

        $this->expectException(
            QueryException::class
        );

        DB::table('quotations')
            ->where('id', $quotationId)
            ->update([
                'current_version_id' =>
                    $foreignVersionId,
                'updated_at' => now(),
            ]);
    }

    public function test_duplicate_revision_number_is_rejected_for_same_quotation(): void
    {
        $workspace = $this->workspace(
            'revision@example.test',
            'Revision Workspace'
        );

        $quotationId = $this->insertQuotation(
            $workspace['tenant_id'],
            'Q-REV-001'
        );

        $this->insertVersion(
            $workspace['tenant_id'],
            $quotationId,
            1
        );

        $this->expectException(
            QueryException::class
        );

        $this->insertVersion(
            $workspace['tenant_id'],
            $quotationId,
            1
        );
    }

    public function test_snapshot_item_survives_without_mutating_catalog_data(): void
    {
        $workspace = $this->workspace(
            'snapshot@example.test',
            'Snapshot Workspace'
        );

        $quotationId = $this->insertQuotation(
            $workspace['tenant_id'],
            'Q-SNAPSHOT-001'
        );

        $versionId = $this->insertVersion(
            $workspace['tenant_id'],
            $quotationId
        );

        DB::table('quotation_items')
            ->insert([
                'id' => (string) Str::ulid(),
                'tenant_id' =>
                    $workspace['tenant_id'],
                'quotation_version_id' =>
                    $versionId,
                'catalog_item_id' => null,
                'unit_id' => null,
                'item_type' => 'SERVICE',
                'code' => null,
                'name' => 'Jasa Custom',
                'description' =>
                    'Snapshot jasa manual',
                'quantity' => 2,
                'unit_code' => 'M2',
                'unit_name' =>
                    'Meter Persegi',
                'unit_symbol' => 'm²',
                'pricing_method' => 'AREA',
                'pricing_config' =>
                    json_encode([
                        'width' => 2,
                        'height' => 3,
                    ]),
                'unit_price' => 25000,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'amount' => 150000,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->assertDatabaseHas(
            'quotation_items',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'quotation_version_id' =>
                    $versionId,
                'name' => 'Jasa Custom',
                'pricing_method' => 'AREA',
                'amount' => 150000,
            ]
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
                'Quotation Foundation Test',
            'email' => $email,
            'password' =>
                'SecurePassword123!',
            'tenant_name' => $tenantName,
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
        string $quotationId,
        int $revisionNo = 1
    ): string {
        $id = (string) Str::ulid();

        DB::table('quotation_versions')
            ->insert([
                'id' => $id,
                'tenant_id' => $tenantId,
                'quotation_id' =>
                    $quotationId,
                'revision_no' =>
                    $revisionNo,
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => 0,
                'currency' => 'IDR',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return $id;
    }

    private function insertCatalogItem(
        string $tenantId,
        string $code
    ): string {
        $id = (string) Str::ulid();

        DB::table('catalog_items')
            ->insert([
                'id' => $id,
                'tenant_id' => $tenantId,
                'business_id' =>
                    $this->businessIdForTenant(
                        $tenantId
                    ),
                'category_id' => null,
                'unit_id' => null,
                'type' => 'PRODUCT',
                'code' => $code,
                'name' => $code,
                'description' => null,
                'pricing_method' =>
                    'STANDARD',
                'base_price' => 10000,
                'currency' => 'IDR',
                'pricing_config' => null,
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return $id;
    }
}
