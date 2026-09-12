<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_owner_can_create_service_with_area_pricing_and_indonesian_labels(): void
    {
        $workspace = $this->workspace(
            'catalog-service@example.test',
            'Catalog Service'
        );

        $this->actingAsWorkspace($workspace);

        $unitId = DB::table('units')
            ->where(
                'tenant_id',
                $workspace['tenant_id']
            )
            ->where('code', 'M2')
            ->value('id');

        $this->assertNotNull($unitId);

        $response = $this->postJson(
            '/api/v1/catalog/items',
            [
                'type' => 'SERVICE',
                'code' => 'SPANDUK-001',
                'name' => 'Spanduk Flexi',
                'unit_id' => $unitId,
                'pricing_method' => 'AREA',
                'base_price' => 25000,
                'currency' => 'IDR',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.type',
                'SERVICE'
            )
            ->assertJsonPath(
                'data.type_label',
                'Jasa'
            )
            ->assertJsonPath(
                'data.pricing_method',
                'AREA'
            )
            ->assertJsonPath(
                'data.pricing_method_label',
                'Berdasarkan Luas'
            )
            ->assertJsonPath(
                'data.status',
                'ACTIVE'
            )
            ->assertJsonPath(
                'data.status_label',
                'Aktif'
            )
            ->assertJsonMissingPath(
                'data.tenant_id'
            );

        $this->assertDatabaseHas(
            'catalog_items',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'code' => 'SPANDUK-001',
                'type' => 'SERVICE',
                'pricing_method' => 'AREA',
            ]
        );
    }

    public function test_owner_can_create_product_with_standard_pricing(): void
    {
        $workspace = $this->workspace(
            'catalog-product@example.test',
            'Catalog Product'
        );

        $this->actingAsWorkspace($workspace);

        $response = $this->postJson(
            '/api/v1/catalog/items',
            [
                'type' => 'PRODUCT',
                'code' => 'LED-001',
                'name' => 'Modul LED',
                'pricing_method' => 'STANDARD',
                'base_price' => 15000,
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.type',
                'PRODUCT'
            )
            ->assertJsonPath(
                'data.type_label',
                'Barang'
            )
            ->assertJsonPath(
                'data.pricing_method_label',
                'Harga Standar'
            )
            ->assertJsonPath(
                'data.currency',
                'IDR'
            );
    }

    public function test_catalog_list_is_tenant_scoped_and_filterable(): void
    {
        $first = $this->workspace(
            'catalog-first@example.test',
            'Catalog First'
        );

        $second = $this->workspace(
            'catalog-second@example.test',
            'Catalog Second'
        );

        $this->insertItem(
            $first['tenant_id'],
            'FIRST-SERVICE',
            'Jasa Pasang Neon',
            'SERVICE',
            'TIME'
        );

        $this->insertItem(
            $first['tenant_id'],
            'FIRST-PRODUCT',
            'Acrylic Sheet',
            'PRODUCT',
            'STANDARD'
        );

        $this->insertItem(
            $second['tenant_id'],
            'SECOND-SERVICE',
            'Jasa Tenant Lain',
            'SERVICE',
            'TIME'
        );

        $this->actingAsWorkspace($first);

        $response = $this->getJson(
            '/api/v1/catalog/items'
            . '?type=SERVICE'
            . '&pricing_method=TIME'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.name',
                'Jasa Pasang Neon'
            )
            ->assertJsonPath(
                'data.0.type_label',
                'Jasa'
            )
            ->assertJsonPath(
                'data.0.pricing_method_label',
                'Berdasarkan Waktu'
            )
            ->assertJsonPath(
                'meta.total',
                1
            );
    }

    public function test_owner_can_update_catalog_item_and_deactivate_it(): void
    {
        $workspace = $this->workspace(
            'catalog-update@example.test',
            'Catalog Update'
        );

        $itemId = $this->insertItem(
            $workspace['tenant_id'],
            'UPDATE-001',
            'Nama Lama',
            'PRODUCT',
            'STANDARD'
        );

        $this->actingAsWorkspace($workspace);

        $this->patchJson(
            "/api/v1/catalog/items/{$itemId}",
            [
                'name' => 'Nama Baru',
                'pricing_method' => 'PACKAGE',
                'base_price' => 100000,
                'status' => 'INACTIVE',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Nama Baru'
            )
            ->assertJsonPath(
                'data.pricing_method',
                'PACKAGE'
            )
            ->assertJsonPath(
                'data.pricing_method_label',
                'Harga Paket'
            )
            ->assertJsonPath(
                'data.status_label',
                'Nonaktif'
            );

        $this->assertDatabaseHas(
            'catalog_items',
            [
                'id' => $itemId,
                'tenant_id' =>
                    $workspace['tenant_id'],
                'name' => 'Nama Baru',
                'status' => 'INACTIVE',
            ]
        );
    }

    public function test_cross_tenant_catalog_item_returns_not_found(): void
    {
        $first = $this->workspace(
            'catalog-cross-first@example.test',
            'Catalog Cross First'
        );

        $second = $this->workspace(
            'catalog-cross-second@example.test',
            'Catalog Cross Second'
        );

        $foreignItemId = $this->insertItem(
            $second['tenant_id'],
            'FOREIGN-001',
            'Barang Tenant Lain',
            'PRODUCT',
            'STANDARD'
        );

        $this->actingAsWorkspace($first);

        $this->getJson(
            "/api/v1/catalog/items/{$foreignItemId}"
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );
    }

    public function test_cross_tenant_category_and_unit_are_rejected(): void
    {
        $first = $this->workspace(
            'reference-first@example.test',
            'Reference First'
        );

        $second = $this->workspace(
            'reference-second@example.test',
            'Reference Second'
        );

        $foreignCategoryId =
            $this->insertCategory(
                $second['tenant_id'],
                'FOREIGN-CAT',
                'Kategori Asing'
            );

        $foreignUnitId = DB::table('units')
            ->where(
                'tenant_id',
                $second['tenant_id']
            )
            ->value('id');

        $this->assertNotNull($foreignUnitId);

        $this->actingAsWorkspace($first);

        $this->postJson(
            '/api/v1/catalog/items',
            [
                'type' => 'PRODUCT',
                'name' => 'Percobaan Lintas Tenant',
                'category_id' =>
                    $foreignCategoryId,
                'unit_id' =>
                    $foreignUnitId,
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_owner_can_create_list_and_update_category(): void
    {
        $workspace = $this->workspace(
            'category@example.test',
            'Category Workspace'
        );

        $this->actingAsWorkspace($workspace);

        $create = $this->postJson(
            '/api/v1/catalog/categories',
            [
                'code' => 'SIGNAGE',
                'name' => 'Signage',
                'description' =>
                    'Produk dan jasa signage',
            ]
        );

        $create
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Signage'
            )
            ->assertJsonPath(
                'data.status_label',
                'Aktif'
            );

        $categoryId =
            $create->json('data.id');

        $this->getJson(
            '/api/v1/catalog/categories'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.code',
                'SIGNAGE'
            );

        $this->patchJson(
            "/api/v1/catalog/categories/{$categoryId}",
            [
                'name' => 'Signage & Reklame',
                'status' => 'INACTIVE',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Signage & Reklame'
            )
            ->assertJsonPath(
                'data.status_label',
                'Nonaktif'
            );
    }

    public function test_units_endpoint_returns_tenant_defaults_with_indonesian_labels(): void
    {
        $workspace = $this->workspace(
            'units@example.test',
            'Units Workspace'
        );

        $this->actingAsWorkspace($workspace);

        $response = $this->getJson(
            '/api/v1/units'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            );

        $units = $response->json('data');

        $this->assertCount(9, $units);

        $m2 = collect($units)
            ->firstWhere('code', 'M2');

        $this->assertNotNull($m2);
        $this->assertSame(
            'Luas',
            $m2['unit_type_label']
        );
        $this->assertSame(
            'Aktif',
            $m2['status_label']
        );

        foreach ($units as $unit) {
            $this->assertArrayNotHasKey(
                'tenant_id',
                $unit
            );
        }
    }

    public function test_missing_catalog_view_capability_is_denied(): void
    {
        $workspace = $this->workspace(
            'catalog-view-denied@example.test',
            'Catalog View Denied'
        );

        $this->revokeOwnerCapability(
            $workspace,
            'catalog.view'
        );

        $this->actingAsWorkspace($workspace);

        $this->getJson('/api/v1/catalog/items')
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_missing_catalog_manage_capability_is_denied(): void
    {
        $workspace = $this->workspace(
            'catalog-manage-denied@example.test',
            'Catalog Manage Denied'
        );

        $this->revokeOwnerCapability(
            $workspace,
            'catalog.manage'
        );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/catalog/items',
            [
                'type' => 'PRODUCT',
                'name' => 'Tidak Boleh Dibuat',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseMissing(
            'catalog_items',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'name' => 'Tidak Boleh Dibuat',
            ]
        );
    }

    public function test_cross_tenant_catalog_item_update_returns_not_found_and_does_not_mutate_data(): void
    {
        $first = $this->workspace(
            'catalog-patch-first@example.test',
            'Catalog Patch First'
        );

        $second = $this->workspace(
            'catalog-patch-second@example.test',
            'Catalog Patch Second'
        );

        $foreignItemId = $this->insertItem(
            $second['tenant_id'],
            'FOREIGN-PATCH',
            'Barang Asli',
            'PRODUCT',
            'STANDARD'
        );

        $this->actingAsWorkspace($first);

        $this->patchJson(
            "/api/v1/catalog/items/{$foreignItemId}",
            [
                'name' => 'Perubahan Ilegal',
            ]
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );

        $this->assertDatabaseHas(
            'catalog_items',
            [
                'id' => $foreignItemId,
                'tenant_id' =>
                    $second['tenant_id'],
                'name' => 'Barang Asli',
            ]
        );

        $this->assertDatabaseMissing(
            'catalog_items',
            [
                'id' => $foreignItemId,
                'name' => 'Perubahan Ilegal',
            ]
        );
    }

    public function test_duplicate_catalog_item_code_is_rejected_within_same_tenant(): void
    {
        $workspace = $this->workspace(
            'catalog-duplicate@example.test',
            'Catalog Duplicate'
        );

        $this->insertItem(
            $workspace['tenant_id'],
            'DUP-ITEM',
            'Item Pertama',
            'PRODUCT',
            'STANDARD'
        );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/catalog/items',
            [
                'type' => 'PRODUCT',
                'code' => 'DUP-ITEM',
                'name' => 'Item Kedua',
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_duplicate_category_code_is_rejected_within_same_tenant(): void
    {
        $workspace = $this->workspace(
            'category-duplicate@example.test',
            'Category Duplicate'
        );

        $this->insertCategory(
            $workspace['tenant_id'],
            'DUP-CAT',
            'Kategori Pertama'
        );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/catalog/categories',
            [
                'code' => 'DUP-CAT',
                'name' => 'Kategori Kedua',
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_invalid_catalog_type_is_rejected(): void
    {
        $workspace = $this->workspace(
            'catalog-type-invalid@example.test',
            'Catalog Type Invalid'
        );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/catalog/items',
            [
                'type' => 'UNKNOWN',
                'name' => 'Tipe Salah',
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_invalid_pricing_method_is_rejected(): void
    {
        $workspace = $this->workspace(
            'catalog-pricing-invalid@example.test',
            'Catalog Pricing Invalid'
        );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/catalog/items',
            [
                'type' => 'SERVICE',
                'name' => 'Metode Harga Salah',
                'pricing_method' => 'RANDOM',
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    private function actingAsWorkspace(
        array $workspace
    ): void {
        Sanctum::actingAs(
            User::findOrFail(
                $workspace['user_id']
            )
        );

        $this->withHeader(
            'X-Signova-Tenant',
            $workspace['tenant_id']
        );
    }

    private function revokeOwnerCapability(
        array $workspace,
        string $capabilityCode
    ): void {
        $capabilityId = DB::table(
            'capabilities'
        )
            ->where(
                'code',
                $capabilityCode
            )
            ->value('id');

        $this->assertNotNull(
            $capabilityId,
            "Capability {$capabilityCode} harus tersedia."
        );

        DB::table('role_capabilities')
            ->where(
                'role_id',
                $workspace['owner_role_id']
            )
            ->where(
                'capability_id',
                $capabilityId
            )
            ->delete();
    }

    private function workspace(
        string $email,
        string $tenantName
    ): array {
        return app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Catalog API Test',
            'email' => $email,
            'password' =>
                'SecurePassword123!',
            'tenant_name' => $tenantName,
        ]);
    }

    private function insertItem(
        string $tenantId,
        string $code,
        string $name,
        string $type,
        string $pricingMethod
    ): string {
        $id = (string) Str::ulid();

        DB::table('catalog_items')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'category_id' => null,
            'unit_id' => null,
            'type' => $type,
            'code' => $code,
            'name' => $name,
            'description' => null,
            'pricing_method' =>
                $pricingMethod,
            'base_price' => 0,
            'currency' => 'IDR',
            'pricing_config' => null,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function insertCategory(
        string $tenantId,
        string $code,
        string $name
    ): string {
        $id = (string) Str::ulid();

        DB::table(
            'catalog_categories'
        )->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $name,
            'description' => null,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
