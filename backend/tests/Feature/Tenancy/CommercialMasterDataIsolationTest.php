<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommercialMasterDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SignovaAccessControlSeeder::class);
    }

    public function test_customer_contact_cannot_reference_customer_from_other_tenant(): void
    {
        $tenantA = $this->workspace(
            'master-a@example.test',
            'Master Tenant A'
        );

        $tenantB = $this->workspace(
            'master-b@example.test',
            'Master Tenant B'
        );

        $customerId = (string) Str::ulid();

        DB::table('customers')->insert([
            'id' => $customerId,
            'tenant_id' => $tenantA['tenant_id'],
            'type' => 'COMPANY',
            'name' => 'Customer Tenant A',
            'payment_terms_days' => 0,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('customer_contacts')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantB['tenant_id'],
            'customer_id' => $customerId,
            'name' => 'Cross Tenant Contact',
            'is_primary' => true,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_catalog_item_cannot_use_unit_from_other_tenant(): void
    {
        $tenantA = $this->workspace(
            'catalog-a@example.test',
            'Catalog Tenant A'
        );

        $tenantB = $this->workspace(
            'catalog-b@example.test',
            'Catalog Tenant B'
        );

        $unitId = (string) Str::ulid();

        DB::table('units')->insert([
            'id' => $unitId,
            'tenant_id' => $tenantA['tenant_id'],
            'code' => 'PCS',
            'name' => 'Pieces',
            'symbol' => 'pcs',
            'unit_type' => 'COUNT',
            'decimal_precision' => 0,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('catalog_items')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantB['tenant_id'],
            'unit_id' => $unitId,
            'type' => 'GOODS',
            'name' => 'Cross Tenant Item',
            'pricing_method' => 'STANDARD',
            'base_price' => 10000,
            'currency' => 'IDR',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function workspace(
        string $email,
        string $tenantName
    ): array {
        return app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Master Data Owner',
            'email' => $email,
            'password' => 'SecurePassword123!',
            'tenant_name' => $tenantName,
        ]);
    }
}
