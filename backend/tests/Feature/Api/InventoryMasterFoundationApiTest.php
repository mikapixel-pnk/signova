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

class InventoryMasterFoundationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_owner_can_manage_inventory_category_and_material_stock_policy(): void
    {
        $workspace =
            $this->workspace(
                'inventory-foundation@example.test',
                'Inventory Foundation'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $category =
            $this->postJson(
                '/api/v1/inventory/categories',
                [
                    'code' =>
                        'akr',

                    'name' =>
                        'Akrilik',

                    'description' =>
                        'Material akrilik',
                ]
            )
                ->assertCreated()
                ->assertJsonPath(
                    'data.code',
                    'AKR'
                )
                ->assertJsonPath(
                    'data.status',
                    'ACTIVE'
                );

        $categoryId =
            (string) $category->json(
                'data.id'
            );

        $material =
            $this->postJson(
                '/api/v1/inventory/materials',
                [
                    'code' =>
                        'mat-100',

                    'name' =>
                        'Akrilik 5mm',

                    'category_id' =>
                        $categoryId,

                    'inventory_type' =>
                        'raw_material',

                    'stock_tracking' =>
                        'tracked',

                    'minimum_stock' =>
                        '10.0000',

                    'reorder_point' =>
                        '20.0000',

                    'maximum_stock' =>
                        '100.0000',

                    'description' =>
                        'Lembaran akrilik 5 mm',
                ]
            )
                ->assertCreated()
                ->assertJsonPath(
                    'data.code',
                    'MAT-100'
                )
                ->assertJsonPath(
                    'data.category_id',
                    $categoryId
                )
                ->assertJsonPath(
                    'data.category',
                    'Akrilik'
                )
                ->assertJsonPath(
                    'data.inventory_category.name',
                    'Akrilik'
                )
                ->assertJsonPath(
                    'data.inventory_type',
                    'RAW_MATERIAL'
                )
                ->assertJsonPath(
                    'data.stock_tracking',
                    'TRACKED'
                )
                ->assertJsonPath(
                    'data.minimum_stock',
                    '10.0000'
                )
                ->assertJsonPath(
                    'data.reorder_point',
                    '20.0000'
                )
                ->assertJsonPath(
                    'data.maximum_stock',
                    '100.0000'
                );

        $materialId =
            (string) $material->json(
                'data.id'
            );

        /*
         * Duplicate strict identity dijaga oleh code
         * dalam tenant + business yang sama.
         */
        $this->postJson(
            '/api/v1/inventory/materials',
            [
                'code' =>
                    'mat-100',

                'name' =>
                    'Duplikat',
            ]
        )->assertUnprocessable();

        /*
         * TRACKED → NOT_TRACKED membersihkan stock thresholds.
         */
        $this->patchJson(
            "/api/v1/inventory/materials/{$materialId}",
            [
                'stock_tracking' =>
                    'not_tracked',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.stock_tracking',
                'NOT_TRACKED'
            )
            ->assertJsonPath(
                'data.minimum_stock',
                null
            )
            ->assertJsonPath(
                'data.reorder_point',
                null
            )
            ->assertJsonPath(
                'data.maximum_stock',
                null
            );

        /*
         * Rename category tetap tercermin pada compatibility field.
         */
        $this->patchJson(
            "/api/v1/inventory/categories/{$categoryId}",
            [
                'name' =>
                    'Akrilik Lembaran',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Akrilik Lembaran'
            );

        $this->getJson(
            '/api/v1/inventory/materials'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.0.category',
                'Akrilik Lembaran'
            );
    }

    public function test_inventory_category_and_stock_policy_duplicate_guards_are_enforced(): void
    {
        $workspace =
            $this->workspace(
                'inventory-guard@example.test',
                'Inventory Guard'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            '/api/v1/inventory/categories',
            [
                'code' =>
                    'CAT-01',

                'name' =>
                    'Bahan Utama',
            ]
        )->assertCreated();

        /*
         * Name duplicate case/whitespace-insensitive.
         */
        $this->postJson(
            '/api/v1/inventory/categories',
            [
                'code' =>
                    'CAT-02',

                'name' =>
                    '  bahan utama  ',
            ]
        )->assertUnprocessable();

        $this->postJson(
            '/api/v1/inventory/materials',
            [
                'code' =>
                    'POLICY-01',

                'name' =>
                    'Policy Invalid',

                'stock_tracking' =>
                    'TRACKED',

                'minimum_stock' =>
                    '30.0000',

                'reorder_point' =>
                    '20.0000',

                'maximum_stock' =>
                    '100.0000',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'fields' => [
                            'reorder_point',
                        ],
                    ],
                ],
            ]);

        $this->postJson(
            '/api/v1/inventory/materials',
            [
                'code' =>
                    'POLICY-02',

                'name' =>
                    'Non Tracked Invalid',

                'stock_tracking' =>
                    'NOT_TRACKED',

                'minimum_stock' =>
                    '1.0000',
            ]
        )
            ->assertUnprocessable();
    }

    public function test_legacy_material_category_is_mapped_to_relational_master(): void
    {
        $workspace =
            $this->workspace(
                'inventory-legacy@example.test',
                'Inventory Legacy'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $material =
            $this->postJson(
                '/api/v1/inventory/materials',
                [
                    'code' =>
                        'LEGACY-01',

                    'name' =>
                        'Vinyl Putih',

                    'category' =>
                        'Vinyl',
                ]
            )
                ->assertCreated()
                ->assertJsonPath(
                    'data.category',
                    'Vinyl'
                );

        $categoryId =
            (string) $material->json(
                'data.category_id'
            );

        $this->assertNotSame(
            '',
            $categoryId
        );

        $this->assertDatabaseHas(
            'inventory_categories',
            [
                'id' =>
                    $categoryId,

                'tenant_id' =>
                    $workspace['tenant_id'],

                'business_id' =>
                    $workspace['business_id'],

                'name' =>
                    'Vinyl',
            ]
        );

        $this->assertDatabaseHas(
            'materials',
            [
                'code' =>
                    'LEGACY-01',

                'category_id' =>
                    $categoryId,
            ]
        );
    }

    public function test_inventory_categories_are_business_scoped(): void
    {
        $first =
            $this->workspace(
                'inventory-category-scope@example.test',
                'Inventory Category Scope'
            );

        $this->actingAsWorkspace(
            $first
        );

        $category =
            $this->postJson(
                '/api/v1/inventory/categories',
                [
                    'code' =>
                        'SCOPE',

                    'name' =>
                        'Scoped Category',
                ]
            )->assertCreated();

        $categoryId =
            (string) $category->json(
                'data.id'
            );

        $second =
            $this->secondBusinessWorkspace(
                $first,
                'Cabang Dua'
            );

        $this->actingAsWorkspace(
            $second
        );

        $this->getJson(
            '/api/v1/inventory/categories'
        )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data'
            );

        /*
         * Category business pertama tidak boleh digunakan
         * oleh business kedua.
         */
        $this->postJson(
            '/api/v1/inventory/materials',
            [
                'code' =>
                    'SCOPE-02',

                'name' =>
                    'Material Cabang Dua',

                'category_id' =>
                    $categoryId,
            ]
        )->assertUnprocessable();

        /*
         * Code/name yang sama sah di business berbeda.
         */
        $this->postJson(
            '/api/v1/inventory/categories',
            [
                'code' =>
                    'SCOPE',

                'name' =>
                    'Scoped Category',
            ]
        )->assertCreated();
    }

    public function test_inventory_master_manage_is_separate_from_receiving(): void
    {
        $masterWorkspace =
            $this->workspace(
                'inventory-master-permission@example.test',
                'Inventory Master Permission'
            );

        $this->actingAsWorkspace(
            $masterWorkspace
        );

        /*
         * Master manager tidak membutuhkan inventory.receive.
         */
        $this->revokeOwnerCapability(
            $masterWorkspace,
            'inventory.receive'
        );

        $this->postJson(
            '/api/v1/inventory/categories',
            [
                'code' =>
                    'MASTER',

                'name' =>
                    'Master Category',
            ]
        )->assertCreated();

        $this->postJson(
            '/api/v1/inventory/warehouses',
            [
                'name' =>
                    'Gudang Master',
            ]
        )->assertCreated();

        $restricted =
            $this->workspace(
                'inventory-master-denied@example.test',
                'Inventory Master Denied'
            );

        $this->actingAsWorkspace(
            $restricted
        );

        $this->revokeOwnerCapability(
            $restricted,
            'inventory.master.manage'
        );

        $this->postJson(
            '/api/v1/inventory/categories',
            [
                'code' =>
                    'DENIED',

                'name' =>
                    'Tidak Boleh',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->postJson(
            '/api/v1/inventory/warehouses',
            [
                'name' =>
                    'Tidak Boleh',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_stock_balances_are_ledger_derived_scoped_and_capability_guarded(): void
    {
        $first =
            $this->workspace(
                'stock-balance@example.test',
                'Stock Balance'
            );

        $this->actingAsWorkspace(
            $first
        );

        $tracked =
            $this->postJson(
                '/api/v1/inventory/materials',
                [
                    'code' =>
                        'STOCK-TRACKED',

                    'name' =>
                        'Akrilik Stock',

                    'stock_tracking' =>
                        'TRACKED',

                    'minimum_stock' =>
                        '2.0000',

                    'reorder_point' =>
                        '3.0000',

                    'maximum_stock' =>
                        '10.0000',
                ]
            )
                ->assertCreated();

        $trackedId =
            (string) $tracked->json(
                'data.id'
            );

        $this->postJson(
            '/api/v1/inventory/materials',
            [
                'code' =>
                    'STOCK-NONTRACKED',

                'name' =>
                    'Barang Non Stock',

                'stock_tracking' =>
                    'NOT_TRACKED',
            ]
        )->assertCreated();

        $warehouse =
            $this->postJson(
                '/api/v1/inventory/warehouses',
                [
                    'name' =>
                        'Gudang Stock Test',
                ]
            )
                ->assertCreated();

        $warehouseId =
            (string) $warehouse->json(
                'data.id'
            );

        DB::table(
            'stock_movements'
        )->insert([
            [
                'id' =>
                    (string) Str::ulid(),

                'tenant_id' =>
                    $first['tenant_id'],

                'business_id' =>
                    $first['business_id'],

                'material_id' =>
                    $trackedId,

                'warehouse_id' =>
                    $warehouseId,

                'type' =>
                    'RECEIPT',

                'quantity_signed' =>
                    '4.2500',

                'source_type' =>
                    'TEST_STOCK_BALANCE',

                'source_id' =>
                    (string) Str::ulid(),

                'occurred_at' =>
                    now(),

                'actor_user_id' =>
                    $first['user_id'],

                'reason' =>
                    'Test saldo masuk.',

                'created_at' =>
                    now(),
            ],
            [
                'id' =>
                    (string) Str::ulid(),

                'tenant_id' =>
                    $first['tenant_id'],

                'business_id' =>
                    $first['business_id'],

                'material_id' =>
                    $trackedId,

                'warehouse_id' =>
                    $warehouseId,

                'type' =>
                    'ISSUE',

                'quantity_signed' =>
                    '-1.0000',

                'source_type' =>
                    'TEST_STOCK_BALANCE',

                'source_id' =>
                    (string) Str::ulid(),

                'occurred_at' =>
                    now(),

                'actor_user_id' =>
                    $first['user_id'],

                'reason' =>
                    'Test saldo keluar.',

                'created_at' =>
                    now(),
            ],
        ]);

        $this->getJson(
            '/api/v1/inventory/stock-balances'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.material_id',
                $trackedId
            )
            ->assertJsonPath(
                'data.0.code',
                'STOCK-TRACKED'
            )
            ->assertJsonPath(
                'data.0.on_hand',
                '3.2500'
            )
            ->assertJsonPath(
                'data.0.minimum_stock',
                '2.0000'
            )
            ->assertJsonPath(
                'data.0.reorder_point',
                '3.0000'
            )
            ->assertJsonPath(
                'data.0.maximum_stock',
                '10.0000'
            );

        /*
         * Business lain tidak boleh melihat saldo business pertama.
         */
        $second =
            $this->secondBusinessWorkspace(
                $first,
                'Cabang Stock Dua'
            );

        $this->actingAsWorkspace(
            $second
        );

        $secondMaterial =
            $this->postJson(
                '/api/v1/inventory/materials',
                [
                    'code' =>
                        'STOCK-B2',

                    'name' =>
                        'Material Cabang Dua',

                    'stock_tracking' =>
                        'TRACKED',
                ]
            )
                ->assertCreated();

        $secondMaterialId =
            (string) $secondMaterial->json(
                'data.id'
            );

        $this->getJson(
            '/api/v1/inventory/stock-balances'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.material_id',
                $secondMaterialId
            )
            ->assertJsonPath(
                'data.0.on_hand',
                '0.0000'
            );

        /*
         * Read model tetap mengikuti capability inventory.view.
         */
        $this->revokeOwnerCapability(
            $second,
            'inventory.view'
        );

        $this->getJson(
            '/api/v1/inventory/stock-balances'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
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
                'Inventory Owner',

            'email' =>
                $email,

            'password' =>
                'SecurePassword123!',

            'tenant_name' =>
                $tenantName,

            'timezone' =>
                'Asia/Jakarta',
        ]);
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

        $this->withHeader(
            'X-Signova-Business',
            $workspace['business_id']
        );
    }

    private function revokeOwnerCapability(
        array $workspace,
        string $capabilityCode
    ): void {
        $capabilityId =
            DB::table('capabilities')
                ->where(
                    'code',
                    $capabilityCode
                )
                ->value('id');

        $this->assertNotNull(
            $capabilityId
        );

        DB::table(
            'role_capabilities'
        )
            ->where(
                'role_id',
                $workspace[
                    'owner_role_id'
                ]
            )
            ->where(
                'capability_id',
                $capabilityId
            )
            ->delete();
    }

    private function secondBusinessWorkspace(
        array $workspace,
        string $name
    ): array {
        $businessId =
            (string) Str::ulid();

        DB::table(
            'business_profiles'
        )->insert([
            'id' =>
                $businessId,

            'tenant_id' =>
                $workspace['tenant_id'],

            'name' =>
                $name,

            'is_default' =>
                false,

            'status' =>
                'ACTIVE',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return [
            ...$workspace,

            'business_id' =>
                $businessId,
        ];
    }
}
