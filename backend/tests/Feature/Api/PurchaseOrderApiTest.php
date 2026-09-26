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

class PurchaseOrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_owner_can_create_manual_draft_with_server_totals_and_no_cash_side_effect(): void
    {
        $workspace =
            $this->workspace(
                'po-manual@example.test',
                'PO Manual'
            );

        $supplierId =
            $this->insertSupplier(
                $workspace,
                'Pemasok Manual'
            );

        $cashBefore =
            DB::table(
                'cash_transactions'
            )->count();

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->postJson(
                '/api/v1/purchasing/orders',
                [
                    'supplier_id' =>
                        $supplierId,

                    'currency' =>
                        'IDR',

                    'items' => [
                        [
                            'name' =>
                                'Akrilik 5mm',

                            'item_type' =>
                                'PRODUCT',

                            'quantity' =>
                                2,

                            'unit_price' =>
                                10000,

                            'discount_amount' =>
                                1000,

                            'tax_amount' =>
                                500,
                        ],
                    ],
                ]
            )
                ->assertCreated()
                ->assertJsonPath(
                    'data.status',
                    'DRAFT'
                );

        $purchaseOrderId =
            (string) $response->json(
                'data.id'
            );

        $this->assertMatchesRegularExpression(
            '/^PO-\d{6}-\d{4}$/',
            (string) $response->json(
                'data.order_number'
            )
        );

        $this->assertDatabaseHas(
            'purchase_orders',
            [
                'id' =>
                    $purchaseOrderId,

                'tenant_id' =>
                    $workspace['tenant_id'],

                'business_id' =>
                    $workspace['business_id'],

                'supplier_id' =>
                    $supplierId,

                'status' =>
                    'DRAFT',

                'subtotal' =>
                    '20000.00',

                'discount_total' =>
                    '1000.00',

                'tax_total' =>
                    '500.00',

                'total' =>
                    '19500.00',
            ]
        );

        $this->assertDatabaseHas(
            'purchase_order_status_history',
            [
                'purchase_order_id' =>
                    $purchaseOrderId,

                'from_status' =>
                    null,

                'to_status' =>
                    'DRAFT',

                'action' =>
                    'CREATED',
            ]
        );

        $this->assertSame(
            $cashBefore,
            DB::table(
                'cash_transactions'
            )->count()
        );
    }

    public function test_draft_can_be_updated_and_totals_are_recalculated(): void
    {
        $workspace =
            $this->workspace(
                'po-update@example.test',
                'PO Update'
            );

        $supplierId =
            $this->insertSupplier(
                $workspace,
                'Pemasok Update'
            );

        $purchaseOrderId =
            $this->createManualPo(
                $workspace,
                $supplierId
            );

        $this->patchJson(
            "/api/v1/purchasing/orders/{$purchaseOrderId}",
            [
                'notes' =>
                    'Harga hasil negosiasi.',

                'items' => [
                    [
                        'name' =>
                            'Bahan Update',

                        'item_type' =>
                            'PRODUCT',

                        'quantity' =>
                            3,

                        'unit_price' =>
                            5000,

                        'discount_amount' =>
                            500,

                        'tax_amount' =>
                            250,
                    ],
                ],
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'DRAFT'
            );

        $this->assertDatabaseHas(
            'purchase_orders',
            [
                'id' =>
                    $purchaseOrderId,

                'subtotal' =>
                    '15000.00',

                'discount_total' =>
                    '500.00',

                'tax_total' =>
                    '250.00',

                'total' =>
                    '14750.00',
            ]
        );
    }

    public function test_approved_request_can_be_split_into_multiple_purchase_orders(): void
    {
        $workspace =
            $this->workspace(
                'po-split@example.test',
                'PO Split'
            );

        $supplierId =
            $this->insertSupplier(
                $workspace,
                'Pemasok Split'
            );

        $purchaseRequestId =
            $this->createApprovedPurchaseRequest(
                $workspace,
                3,
                12000
            );

        $sourceItemId =
            (string) DB::table(
                'purchase_request_items'
            )
                ->where(
                    'purchase_request_id',
                    $purchaseRequestId
                )
                ->value('id');

        $this->actingAsWorkspace(
            $workspace
        );

        foreach ([1, 2] as $quantity) {
            $this->postJson(
                '/api/v1/purchasing/orders',
                [
                    'supplier_id' =>
                        $supplierId,

                    'source_purchase_request_id' =>
                        $purchaseRequestId,

                    'items' => [
                        [
                            'source_purchase_request_item_id' =>
                                $sourceItemId,

                            'quantity' =>
                                $quantity,

                            'unit_price' =>
                                12000,
                        ],
                    ],
                ]
            )->assertCreated();
        }

        $this->assertSame(
            2,
            DB::table(
                'purchase_orders'
            )
                ->where(
                    'source_purchase_request_id',
                    $purchaseRequestId
                )
                ->count()
        );

        $this->assertSame(
            3.0,
            (float) DB::table(
                'purchase_order_items'
            )
                ->where(
                    'source_purchase_request_item_id',
                    $sourceItemId
                )
                ->sum('quantity')
        );
    }

    public function test_po_cannot_exceed_remaining_purchase_request_quantity(): void
    {
        $workspace =
            $this->workspace(
                'po-over@example.test',
                'PO Over'
            );

        $supplierId =
            $this->insertSupplier(
                $workspace,
                'Pemasok Over'
            );

        $purchaseRequestId =
            $this->createApprovedPurchaseRequest(
                $workspace,
                2,
                10000
            );

        $sourceItemId =
            (string) DB::table(
                'purchase_request_items'
            )
                ->where(
                    'purchase_request_id',
                    $purchaseRequestId
                )
                ->value('id');

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            '/api/v1/purchasing/orders',
            [
                'supplier_id' =>
                    $supplierId,

                'source_purchase_request_id' =>
                    $purchaseRequestId,

                'items' => [
                    [
                        'source_purchase_request_item_id' =>
                            $sourceItemId,

                        'quantity' =>
                            1,
                    ],
                ],
            ]
        )->assertCreated();

        $this->postJson(
            '/api/v1/purchasing/orders',
            [
                'supplier_id' =>
                    $supplierId,

                'source_purchase_request_id' =>
                    $purchaseRequestId,

                'items' => [
                    [
                        'source_purchase_request_item_id' =>
                            $sourceItemId,

                        'quantity' =>
                            2,
                    ],
                ],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_non_approved_purchase_request_cannot_create_po(): void
    {
        $workspace =
            $this->workspace(
                'po-pr-state@example.test',
                'PO PR State'
            );

        $supplierId =
            $this->insertSupplier(
                $workspace,
                'Pemasok PR State'
            );

        $purchaseRequestId =
            $this->createPurchaseRequestDraft(
                $workspace,
                1,
                10000
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            '/api/v1/purchasing/orders',
            [
                'supplier_id' =>
                    $supplierId,

                'source_purchase_request_id' =>
                    $purchaseRequestId,
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );
    }

    public function test_issue_and_cancel_record_history_without_moving_cash(): void
    {
        $workspace =
            $this->workspace(
                'po-flow@example.test',
                'PO Flow'
            );

        $supplierId =
            $this->insertSupplier(
                $workspace,
                'Pemasok Flow'
            );

        $purchaseOrderId =
            $this->createManualPo(
                $workspace,
                $supplierId
            );

        $cashBefore =
            DB::table(
                'cash_transactions'
            )->count();

        $this->postJson(
            "/api/v1/purchasing/orders/{$purchaseOrderId}/actions/issue"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'ISSUED'
            );

        $this->postJson(
            "/api/v1/purchasing/orders/{$purchaseOrderId}/actions/cancel",
            [
                'reason' =>
                    'Pesanan tidak dilanjutkan.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'CANCELLED'
            );

        $history =
            DB::table(
                'purchase_order_status_history'
            )
                ->where(
                    'purchase_order_id',
                    $purchaseOrderId
                )
                ->orderBy(
                    'created_at'
                )
                ->pluck(
                    'action'
                )
                ->all();

        $this->assertSame(
            [
                'CREATED',
                'ISSUED',
                'CANCELLED',
            ],
            $history
        );

        $this->assertSame(
            $cashBefore,
            DB::table(
                'cash_transactions'
            )->count()
        );

        $this->postJson(
            "/api/v1/purchasing/orders/{$purchaseOrderId}/actions/issue"
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );
    }

    public function test_issue_and_cancel_use_separate_capabilities(): void
    {
        $workspace =
            $this->workspace(
                'po-capability@example.test',
                'PO Capability'
            );

        $supplierId =
            $this->insertSupplier(
                $workspace,
                'Pemasok Capability'
            );

        $purchaseOrderId =
            $this->createManualPo(
                $workspace,
                $supplierId
            );

        $this->revokeOwnerCapability(
            $workspace,
            'purchasing.approve_po'
        );

        $this->postJson(
            "/api/v1/purchasing/orders/{$purchaseOrderId}/actions/issue"
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->revokeOwnerCapability(
            $workspace,
            'purchasing.cancel_po'
        );

        $this->postJson(
            "/api/v1/purchasing/orders/{$purchaseOrderId}/actions/cancel",
            [
                'reason' =>
                    'Tidak dilanjutkan.',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_supplier_scope_and_server_owned_fields_are_enforced(): void
    {
        $workspace =
            $this->workspace(
                'po-scope@example.test',
                'PO Scope'
            );

        $otherBusiness =
            $this->secondBusinessWorkspace(
                $workspace,
                'Cabang Dua'
            );

        $foreignSupplier =
            $this->insertSupplier(
                $otherBusiness,
                'Pemasok Cabang Dua'
            );

        $localSupplier =
            $this->insertSupplier(
                $workspace,
                'Pemasok Lokal'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            '/api/v1/purchasing/orders',
            [
                'supplier_id' =>
                    $foreignSupplier,

                'items' => [
                    [
                        'name' =>
                            'Bahan',

                        'quantity' =>
                            1,
                    ],
                ],
            ]
        )->assertUnprocessable();

        $this->postJson(
            '/api/v1/purchasing/orders',
            [
                'supplier_id' =>
                    $localSupplier,

                'order_number' =>
                    'PO-HACK',

                'status' =>
                    'ISSUED',

                'total' =>
                    1,

                'items' => [
                    [
                        'name' =>
                            'Bahan',

                        'quantity' =>
                            1,
                    ],
                ],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_create_po_capability_is_enforced(): void
    {
        $workspace =
            $this->workspace(
                'po-create-capability@example.test',
                'PO Create Capability'
            );

        $supplierId =
            $this->insertSupplier(
                $workspace,
                'Pemasok Create Capability'
            );

        $this->revokeOwnerCapability(
            $workspace,
            'purchasing.create_po'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            '/api/v1/purchasing/orders',
            [
                'supplier_id' =>
                    $supplierId,

                'items' => [
                    [
                        'name' =>
                            'Bahan',

                        'quantity' =>
                            1,

                        'unit_price' =>
                            10000,
                    ],
                ],
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_purchase_orders_are_isolated_between_businesses_for_list_and_detail(): void
    {
        $first =
            $this->workspace(
                'po-business@example.test',
                'PO Business Scope'
            );

        $second =
            $this->secondBusinessWorkspace(
                $first,
                'Cabang Kedua'
            );

        $firstSupplier =
            $this->insertSupplier(
                $first,
                'Pemasok Cabang Utama'
            );

        $secondSupplier =
            $this->insertSupplier(
                $second,
                'Pemasok Cabang Kedua'
            );

        $firstPo =
            $this->createManualPo(
                $first,
                $firstSupplier
            );

        $secondPo =
            $this->createManualPo(
                $second,
                $secondSupplier
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->getJson(
            '/api/v1/purchasing/orders'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $firstPo
            );

        $this->getJson(
            "/api/v1/purchasing/orders/{$secondPo}"
        )->assertNotFound();

        $this->actingAsWorkspace(
            $second
        );

        $this->getJson(
            '/api/v1/purchasing/orders'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $secondPo
            );

        $this->getJson(
            "/api/v1/purchasing/orders/{$firstPo}"
        )->assertNotFound();
    }

    private function createManualPo(
        array $workspace,
        string $supplierId
    ): string {
        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->postJson(
                '/api/v1/purchasing/orders',
                [
                    'supplier_id' =>
                        $supplierId,

                    'items' => [
                        [
                            'name' =>
                                'Bahan Test',

                            'item_type' =>
                                'PRODUCT',

                            'quantity' =>
                                1,

                            'unit_price' =>
                                10000,
                        ],
                    ],
                ]
            )
                ->assertCreated();

        return (string) $response->json(
            'data.id'
        );
    }

    public function test_direct_inventory_po_uses_material_master(): void
    {
        $workspace =
            $this->workspace(
                'po-inventory-item@example.test',
                'PO Inventory Item'
            );

        $supplierId =
            $this->insertSupplier(
                $workspace,
                'Pemasok Inventory'
            );

        $materialId =
            (string) \Illuminate\Support\Str::ulid();

        DB::table(
            'materials'
        )->insert([
            'id' =>
                $materialId,

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'code' =>
                'MAT-PO-001',

            'name' =>
                'Akrilik PO',

            'unit_id' =>
                null,

            'category' =>
                'Akrilik',

            'inventory_type' =>
                'RAW_MATERIAL',

            'status' =>
                'ACTIVE',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            '/api/v1/purchasing/orders',
            [
                'supplier_id' =>
                    $supplierId,

                'currency' =>
                    'IDR',

                'items' => [
                    [
                        'material_id' =>
                            $materialId,

                        'procurement_type' =>
                            'INVENTORY_ITEM',

                        'quantity' =>
                            '2',

                        'unit_price' =>
                            '12500',
                    ],
                ],
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.items.0.material_id',
                $materialId
            )
            ->assertJsonPath(
                'data.items.0.procurement_type',
                'INVENTORY_ITEM'
            )
            ->assertJsonPath(
                'data.items.0.item_type',
                'PRODUCT'
            )
            ->assertJsonPath(
                'data.items.0.code',
                'MAT-PO-001'
            )
            ->assertJsonPath(
                'data.items.0.name',
                'Akrilik PO'
            );
    }


    private function createApprovedPurchaseRequest(
        array $workspace,
        int $quantity,
        int $unitPrice
    ): string {
        $id =
            $this->createPurchaseRequestDraft(
                $workspace,
                $quantity,
                $unitPrice
            );

        $this->postJson(
            "/api/v1/purchasing/requests/{$id}/actions/submit"
        )->assertOk();

        $this->postJson(
            "/api/v1/purchasing/requests/{$id}/actions/approve"
        )->assertOk();

        return $id;
    }

    private function createPurchaseRequestDraft(
        array $workspace,
        int $quantity,
        int $unitPrice
    ): string {
        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->postJson(
                '/api/v1/purchasing/requests',
                [
                    'currency' =>
                        'IDR',

                    'items' => [
                        [
                            'name' =>
                                'Bahan PR',

                            'item_type' =>
                                'PRODUCT',

                            'quantity' =>
                                $quantity,

                            'estimated_unit_price' =>
                                $unitPrice,
                        ],
                    ],
                ]
            )
                ->assertCreated();

        return (string) $response->json(
            'data.id'
        );
    }

    private function insertSupplier(
        array $workspace,
        string $name
    ): string {
        $id =
            (string) Str::ulid();

        DB::table(
            'suppliers'
        )->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'code' =>
                'SUP-' . substr(
                    $id,
                    -8
                ),

            'name' =>
                $name,

            'payment_terms_days' =>
                0,

            'status' =>
                'ACTIVE',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return $id;
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

    private function workspace(
        string $email,
        string $tenantName
    ): array {
        return app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' =>
                'Purchase Order API Owner',

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
