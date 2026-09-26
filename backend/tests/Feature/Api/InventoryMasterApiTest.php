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

class InventoryMasterApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_owner_can_manage_material_and_warehouse(): void
    {
        $workspace =
            $this->workspace(
                'inventory-master@example.test',
                'Inventory Master'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $material =
            $this->postJson(
                '/api/v1/inventory/materials',
                [
                    'code' =>
                        'mat-001',

                    'name' =>
                        'Akrilik 5mm',

                    'category' =>
                        'Akrilik',

                    'inventory_type' =>
                        'raw_material',
                ]
            )
                ->assertCreated()
                ->assertJsonPath(
                    'data.code',
                    'MAT-001'
                )
                ->assertJsonPath(
                    'data.inventory_type',
                    'RAW_MATERIAL'
                )
                ->assertJsonPath(
                    'data.status',
                    'ACTIVE'
                );

        $materialId =
            (string) $material->json(
                'data.id'
            );

        $warehouse =
            $this->postJson(
                '/api/v1/inventory/warehouses',
                [
                    'name' =>
                        'Gudang Utama',

                    'location' =>
                        'Area produksi',
                ]
            )
                ->assertCreated()
                ->assertJsonPath(
                    'data.status',
                    'ACTIVE'
                );

        $warehouseId =
            (string) $warehouse->json(
                'data.id'
            );

        $this->patchJson(
            "/api/v1/inventory/materials/{$materialId}",
            [
                'inventory_type' =>
                    'finished_good',

                'status' =>
                    'INACTIVE',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.inventory_type',
                'FINISHED_GOOD'
            )
            ->assertJsonPath(
                'data.status',
                'INACTIVE'
            );

        $this->patchJson(
            "/api/v1/inventory/materials/{$materialId}",
            [
                'inventory_type' =>
                    'INVALID_TYPE',
            ]
        )->assertUnprocessable();

        $this->patchJson(
            "/api/v1/inventory/warehouses/{$warehouseId}",
            [
                'location' =>
                    'Area produksi belakang',
            ]
        )
            ->assertOk();

        $this->getJson(
            '/api/v1/inventory/materials'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            );

        $this->getJson(
            '/api/v1/inventory/warehouses'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            );
    }

    public function test_inventory_master_is_business_scoped(): void
    {
        $first =
            $this->workspace(
                'inventory-scope@example.test',
                'Inventory Scope'
            );

        $second =
            $this->secondBusinessWorkspace(
                $first,
                'Cabang Dua'
            );

        $this->actingAsWorkspace(
            $second
        );

        $material =
            $this->postJson(
                '/api/v1/inventory/materials',
                [
                    'code' =>
                        'CABANG-2',

                    'name' =>
                        'Material Cabang Dua',
                ]
            )->assertCreated();

        $materialId =
            (string) $material->json(
                'data.id'
            );

        $this->postJson(
            '/api/v1/inventory/warehouses',
            [
                'name' =>
                    'Gudang Cabang Dua',
            ]
        )->assertCreated();

        $this->actingAsWorkspace(
            $first
        );

        $this->getJson(
            '/api/v1/inventory/materials'
        )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data'
            );

        $this->getJson(
            '/api/v1/inventory/warehouses'
        )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data'
            );

        $this->patchJson(
            "/api/v1/inventory/materials/{$materialId}",
            [
                'name' =>
                    'Tidak Boleh',
            ]
        )->assertNotFound();
    }

    public function test_inventory_capabilities_are_enforced(): void
    {
        $viewWorkspace =
            $this->workspace(
                'inventory-view@example.test',
                'Inventory View'
            );

        $this->actingAsWorkspace(
            $viewWorkspace
        );

        $this->revokeOwnerCapability(
            $viewWorkspace,
            'inventory.view'
        );

        $this->getJson(
            '/api/v1/inventory/materials'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $receiveWorkspace =
            $this->workspace(
                'inventory-receive@example.test',
                'Inventory Receive'
            );

        $this->actingAsWorkspace(
            $receiveWorkspace
        );

        $this->revokeOwnerCapability(
            $receiveWorkspace,
            'inventory.master.manage'
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
