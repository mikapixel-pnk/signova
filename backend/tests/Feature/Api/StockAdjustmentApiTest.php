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

class StockAdjustmentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_adjustment_lifecycle_is_exact_ledger_based_and_has_no_cash_side_effect(): void
    {
        $workspace =
            $this->workspace(
                'adjustment-lifecycle@example.test',
                'Adjustment Lifecycle'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        /*
         * Capability baru harus benar-benar
         * default deny.
         */
        $this->postJson(
            '/api/v1/inventory/adjustments',
            []
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->grantOwnerCapability(
            $workspace,
            'inventory.adjust'
        );

        $warehouseId =
            $this->createWarehouse(
                'Gudang Adjustment'
            );

        $materialId =
            $this->createMaterial(
                'ADJ-MAT-001',
                'Akrilik Adjustment',
                'TRACKED'
            );

        $draft =
            $this->postJson(
                '/api/v1/inventory/adjustments',
                [
                    'warehouse_id' =>
                        $warehouseId,

                    'reason' =>
                        'Selisih hasil hitung fisik.',

                    'items' => [
                        [
                            'material_id' =>
                                $materialId,

                            'quantity_delta' =>
                                '2.5000',
                        ],
                    ],
                ]
            )
                ->assertCreated()
                ->assertJsonPath(
                    'data.status',
                    'DRAFT'
                )
                ->assertJsonPath(
                    'data.status_label',
                    'Draf'
                )
                ->assertJsonPath(
                    'data.items.0.quantity_delta',
                    '2.5000'
                )
                ->assertJsonPath(
                    'data.workflow.next_action',
                    'POST'
                );

        $adjustmentId =
            (string) $draft->json(
                'data.id'
            );

        $number =
            (string) $draft->json(
                'data.adjustment_number'
            );

        $this->assertStringStartsWith(
            'ADJ-',
            $number
        );

        $this->assertSame(
            0,
            DB::table(
                'stock_movements'
            )
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'business_id',
                    $workspace['business_id']
                )
                ->count()
        );

        $this->postJson(
            "/api/v1/inventory/adjustments/{$adjustmentId}/actions/post"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'POSTED'
            )
            ->assertJsonPath(
                'data.status_label',
                'Dicatat'
            )
            ->assertJsonPath(
                'data.workflow.next_action',
                'REVERSE'
            );

        /*
         * POST kedua harus ditolak dan
         * tidak membuat movement duplikat.
         */
        $this->postJson(
            "/api/v1/inventory/adjustments/{$adjustmentId}/actions/post"
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->assertSame(
            1,
            DB::table(
                'stock_movements'
            )
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'business_id',
                    $workspace['business_id']
                )
                ->where(
                    'type',
                    'ADJUSTMENT'
                )
                ->count()
        );

        $movement =
            DB::table(
                'stock_movements'
            )
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'business_id',
                    $workspace['business_id']
                )
                ->where(
                    'material_id',
                    $materialId
                )
                ->where(
                    'warehouse_id',
                    $warehouseId
                )
                ->where(
                    'type',
                    'ADJUSTMENT'
                )
                ->where(
                    'source_type',
                    'STOCK_ADJUSTMENT_ITEM'
                )
                ->first();

        $this->assertNotNull(
            $movement
        );

        $this->assertSame(
            '2.5000',
            (string)
                $movement->quantity_signed
        );

        $this->assertSame(
            'Selisih hasil hitung fisik.',
            $movement->reason
        );

        $this->postJson(
            "/api/v1/inventory/adjustments/{$adjustmentId}/actions/reverse",
            [
                'reason' =>
                    'Koreksi penyesuaian sebelumnya.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'REVERSED'
            )
            ->assertJsonPath(
                'data.status_label',
                'Dikoreksi'
            )
            ->assertJsonPath(
                'data.workflow.next_action',
                null
            );

        /*
         * REVERSE kedua juga state conflict.
         */
        $this->postJson(
            "/api/v1/inventory/adjustments/{$adjustmentId}/actions/reverse",
            [
                'reason' =>
                    'Percobaan koreksi kedua.',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->assertSame(
            1,
            DB::table(
                'stock_movements'
            )
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'business_id',
                    $workspace['business_id']
                )
                ->where(
                    'type',
                    'ADJUSTMENT_REVERSAL'
                )
                ->count()
        );

        $reversal =
            DB::table(
                'stock_movements'
            )
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'business_id',
                    $workspace['business_id']
                )
                ->where(
                    'type',
                    'ADJUSTMENT_REVERSAL'
                )
                ->where(
                    'reversal_of_movement_id',
                    $movement->id
                )
                ->first();

        $this->assertNotNull(
            $reversal
        );

        $this->assertSame(
            '-2.5000',
            (string)
                $reversal->quantity_signed
        );

        $this->assertSame(
            $movement->source_id,
            $reversal->source_id
        );

        $this->assertSame(
            'STOCK_ADJUSTMENT_ITEM',
            $reversal->source_type
        );

        $this->assertDatabaseHas(
            'stock_adjustment_status_history',
            [
                'stock_adjustment_id' =>
                    $adjustmentId,

                'action' =>
                    'CREATED',

                'to_status' =>
                    'DRAFT',
            ]
        );

        $this->assertDatabaseHas(
            'stock_adjustment_status_history',
            [
                'stock_adjustment_id' =>
                    $adjustmentId,

                'action' =>
                    'POSTED',

                'to_status' =>
                    'POSTED',
            ]
        );

        $this->assertDatabaseHas(
            'stock_adjustment_status_history',
            [
                'stock_adjustment_id' =>
                    $adjustmentId,

                'action' =>
                    'REVERSED',

                'to_status' =>
                    'REVERSED',
            ]
        );

        $this->assertSame(
            0,
            DB::table(
                'cash_transactions'
            )
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'business_id',
                    $workspace['business_id']
                )
                ->count()
        );
    }

    public function test_non_tracked_zero_and_over_precision_adjustments_are_rejected_without_movements(): void
    {
        $workspace =
            $this->workspace(
                'adjustment-validation@example.test',
                'Adjustment Validation'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->grantOwnerCapability(
            $workspace,
            'inventory.adjust'
        );

        $warehouseId =
            $this->createWarehouse(
                'Gudang Validation'
            );

        $trackedId =
            $this->createMaterial(
                'ADJ-TRACKED',
                'Barang Tracked',
                'TRACKED'
            );

        $notTrackedId =
            $this->createMaterial(
                'ADJ-NOT-TRACKED',
                'Barang Non Tracked',
                'NOT_TRACKED'
            );

        $this->postJson(
            '/api/v1/inventory/adjustments',
            [
                'warehouse_id' =>
                    $warehouseId,

                'reason' =>
                    'Validasi non tracked.',

                'items' => [
                    [
                        'material_id' =>
                            $notTrackedId,

                        'quantity_delta' =>
                            '1.0000',
                    ],
                ],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            )
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'fields' => [
                            'items.0.material_id',
                        ],
                    ],
                ],
            ]);

        $this->postJson(
            '/api/v1/inventory/adjustments',
            [
                'warehouse_id' =>
                    $warehouseId,

                'reason' =>
                    'Validasi nilai nol.',

                'items' => [
                    [
                        'material_id' =>
                            $trackedId,

                        'quantity_delta' =>
                            '0.0000',
                    ],
                ],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            )
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'fields' => [
                            'items.0.quantity_delta',
                        ],
                    ],
                ],
            ]);

        $this->postJson(
            '/api/v1/inventory/adjustments',
            [
                'warehouse_id' =>
                    $warehouseId,

                'reason' =>
                    'Validasi presisi berlebih.',

                'items' => [
                    [
                        'material_id' =>
                            $trackedId,

                        'quantity_delta' =>
                            '1.23456',
                    ],
                ],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            )
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'fields' => [
                            'items.0.quantity_delta',
                        ],
                    ],
                ],
            ]);

        $this->assertSame(
            0,
            DB::table(
                'stock_adjustments'
            )->count()
        );

        $this->assertSame(
            0,
            DB::table(
                'stock_movements'
            )->count()
        );
    }

    public function test_adjustments_are_tenant_and_business_scoped_and_adjust_capability_is_enforced(): void
    {
        $first =
            $this->workspace(
                'adjustment-scope@example.test',
                'Adjustment Scope'
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->grantOwnerCapability(
            $first,
            'inventory.adjust'
        );

        $warehouseId =
            $this->createWarehouse(
                'Gudang Scope'
            );

        $materialId =
            $this->createMaterial(
                'ADJ-SCOPE',
                'Material Scope',
                'TRACKED'
            );

        $draft =
            $this->postJson(
                '/api/v1/inventory/adjustments',
                [
                    'warehouse_id' =>
                        $warehouseId,

                    'reason' =>
                        'Uji pembatasan konteks.',

                    'items' => [
                        [
                            'material_id' =>
                                $materialId,

                            'quantity_delta' =>
                                '1.2500',
                        ],
                    ],
                ]
            )
                ->assertCreated();

        $adjustmentId =
            (string) $draft->json(
                'data.id'
            );

        /*
         * Business lain di tenant yang sama
         * tidak boleh membaca dokumen.
         */
        $secondBusiness =
            $this->secondBusinessWorkspace(
                $first,
                'Cabang Adjustment Dua'
            );

        $this->actingAsWorkspace(
            $secondBusiness
        );

        $this->getJson(
            "/api/v1/inventory/adjustments/{$adjustmentId}"
        )->assertNotFound();

        /*
         * Tenant lain juga tidak boleh
         * membaca dokumen tenant pertama.
         */
        $otherTenant =
            $this->workspace(
                'adjustment-other-tenant@example.test',
                'Adjustment Other Tenant'
            );

        $this->actingAsWorkspace(
            $otherTenant
        );

        $this->getJson(
            "/api/v1/inventory/adjustments/{$adjustmentId}"
        )->assertNotFound();

        /*
         * Kembali ke workspace pertama,
         * cabut capability adjustment.
         */
        $this->actingAsWorkspace(
            $first
        );

        $this->revokeOwnerCapability(
            $first,
            'inventory.adjust'
        );

        $this->postJson(
            "/api/v1/inventory/adjustments/{$adjustmentId}/actions/post"
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseHas(
            'stock_adjustments',
            [
                'id' =>
                    $adjustmentId,

                'tenant_id' =>
                    $first['tenant_id'],

                'business_id' =>
                    $first['business_id'],

                'status' =>
                    'DRAFT',
            ]
        );

        $this->assertSame(
            0,
            DB::table(
                'stock_movements'
            )
                ->where(
                    'tenant_id',
                    $first['tenant_id']
                )
                ->where(
                    'business_id',
                    $first['business_id']
                )
                ->count()
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
                'Adjustment Owner',

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

    private function createWarehouse(
        string $name
    ): string {
        $response =
            $this->postJson(
                '/api/v1/inventory/warehouses',
                [
                    'name' =>
                        $name,
                ]
            )->assertCreated();

        return (string)
            $response->json(
                'data.id'
            );
    }

    private function createMaterial(
        string $code,
        string $name,
        string $stockTracking
    ): string {
        $response =
            $this->postJson(
                '/api/v1/inventory/materials',
                [
                    'code' =>
                        $code,

                    'name' =>
                        $name,

                    'stock_tracking' =>
                        $stockTracking,
                ]
            )->assertCreated();

        return (string)
            $response->json(
                'data.id'
            );
    }

    private function grantOwnerCapability(
        array $workspace,
        string $capabilityCode
    ): void {
        $capabilityId =
            DB::table(
                'capabilities'
            )
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
        )->updateOrInsert(
            [
                'role_id' =>
                    $workspace[
                        'owner_role_id'
                    ],

                'capability_id' =>
                    $capabilityId,
            ],
            [
                'effect' =>
                    'ALLOW',

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]
        );
    }

    private function revokeOwnerCapability(
        array $workspace,
        string $capabilityCode
    ): void {
        $capabilityId =
            DB::table(
                'capabilities'
            )
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
