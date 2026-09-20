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

class GoodsReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_product_receipt_updates_stock_and_purchase_order_status(): void
    {
        $workspace =
            $this->workspace(
                'receipt-product@example.test',
                'Receipt Product'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $warehouseId =
            $this->createWarehouse(
                'Gudang Utama'
            );

        $materialId =
            $this->createMaterial(
                'MAT-001',
                'Akrilik 5mm'
            );

        $po =
            $this->createIssuedPurchaseOrder(
                'PRODUCT',
                'Akrilik 5mm',
                10
            );

        $firstReceipt =
            $this->postJson(
                '/api/v1/inventory/receipts',
                [
                    'purchase_order_id' =>
                        $po['id'],

                    'warehouse_id' =>
                        $warehouseId,

                    'items' => [
                        [
                            'purchase_order_item_id' =>
                                $po['item_id'],

                            'material_id' =>
                                $materialId,

                            'quantity_received' =>
                                4,
                        ],
                    ],
                ]
            )
                ->assertCreated()
                ->assertJsonPath(
                    'data.status',
                    'DRAFT'
                );

        $firstReceiptId =
            (string) $firstReceipt->json(
                'data.id'
            );

        $this->postJson(
            "/api/v1/inventory/receipts/{$firstReceiptId}/actions/post"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'POSTED'
            )
            ->assertJsonPath(
                'data.workflow.next_action',
                'REVERSE'
            );

        $this->getJson(
            "/api/v1/purchasing/orders/{$po['id']}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'PARTIALLY_RECEIVED'
            );

        $this->assertEquals(
            4.0,
            (float) DB::table(
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
                ->sum(
                    'quantity_signed'
                )
        );

        $secondReceipt =
            $this->postJson(
                '/api/v1/inventory/receipts',
                [
                    'purchase_order_id' =>
                        $po['id'],

                    'warehouse_id' =>
                        $warehouseId,

                    'items' => [
                        [
                            'purchase_order_item_id' =>
                                $po['item_id'],

                            'material_id' =>
                                $materialId,

                            'quantity_received' =>
                                6,
                        ],
                    ],
                ]
            )->assertCreated();

        $secondReceiptId =
            (string) $secondReceipt->json(
                'data.id'
            );

        $this->postJson(
            "/api/v1/inventory/receipts/{$secondReceiptId}/actions/post"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'POSTED'
            );

        $this->getJson(
            "/api/v1/purchasing/orders/{$po['id']}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'RECEIVED'
            );

        $this->assertEquals(
            10.0,
            (float) DB::table(
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
                ->sum(
                    'quantity_signed'
                )
        );

        $this->postJson(
            "/api/v1/inventory/receipts/{$secondReceiptId}/actions/reverse",
            [
                'reason' =>
                    'Jumlah penerimaan perlu dikoreksi.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'REVERSED'
            )
            ->assertJsonPath(
                'data.workflow.next_action',
                null
            );

        $this->getJson(
            "/api/v1/purchasing/orders/{$po['id']}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'PARTIALLY_RECEIVED'
            );

        $this->assertEquals(
            4.0,
            (float) DB::table(
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
                ->sum(
                    'quantity_signed'
                )
        );

        $this->assertDatabaseHas(
            'stock_movements',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'business_id' =>
                    $workspace['business_id'],

                'type' =>
                    'RECEIPT_REVERSAL',
            ]
        );

        $this->assertDatabaseHas(
            'purchase_order_status_history',
            [
                'purchase_order_id' =>
                    $po['id'],

                'action' =>
                    'RECEIPT_REVERSED',

                'to_status' =>
                    'PARTIALLY_RECEIVED',
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

    public function test_service_receipt_does_not_create_stock_movement_or_cash_transaction(): void
    {
        $workspace =
            $this->workspace(
                'receipt-service@example.test',
                'Receipt Service'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $warehouseId =
            $this->createWarehouse(
                'Gudang Jasa'
            );

        $po =
            $this->createIssuedPurchaseOrder(
                'SERVICE',
                'Jasa Cutting',
                1
            );

        $receipt =
            $this->postJson(
                '/api/v1/inventory/receipts',
                [
                    'purchase_order_id' =>
                        $po['id'],

                    'warehouse_id' =>
                        $warehouseId,

                    'items' => [
                        [
                            'purchase_order_item_id' =>
                                $po['item_id'],

                            'quantity_received' =>
                                1,
                        ],
                    ],
                ]
            )->assertCreated();

        $receiptId =
            (string) $receipt->json(
                'data.id'
            );

        $this->postJson(
            "/api/v1/inventory/receipts/{$receiptId}/actions/post"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'POSTED'
            );

        $this->getJson(
            "/api/v1/purchasing/orders/{$po['id']}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'RECEIVED'
            );

        $this->assertSame(
            0,
            DB::table(
                'stock_movements'
            )->count()
        );

        $this->assertSame(
            0,
            DB::table(
                'cash_transactions'
            )->count()
        );
    }

    public function test_over_receipt_is_rejected(): void
    {
        $workspace =
            $this->workspace(
                'receipt-over@example.test',
                'Receipt Over'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $warehouseId =
            $this->createWarehouse(
                'Gudang Over'
            );

        $materialId =
            $this->createMaterial(
                'MAT-OVER',
                'Material Over'
            );

        $po =
            $this->createIssuedPurchaseOrder(
                'PRODUCT',
                'Material Over',
                5
            );

        $first =
            $this->postJson(
                '/api/v1/inventory/receipts',
                [
                    'purchase_order_id' =>
                        $po['id'],

                    'warehouse_id' =>
                        $warehouseId,

                    'items' => [
                        [
                            'purchase_order_item_id' =>
                                $po['item_id'],

                            'material_id' =>
                                $materialId,

                            'quantity_received' =>
                                4,
                        ],
                    ],
                ]
            )->assertCreated();

        $firstId =
            (string) $first->json(
                'data.id'
            );

        $this->postJson(
            "/api/v1/inventory/receipts/{$firstId}/actions/post"
        )->assertOk();

        $this->postJson(
            '/api/v1/inventory/receipts',
            [
                'purchase_order_id' =>
                    $po['id'],

                'warehouse_id' =>
                    $warehouseId,

                'items' => [
                    [
                        'purchase_order_item_id' =>
                            $po['item_id'],

                        'material_id' =>
                            $materialId,

                        'quantity_received' =>
                            2,
                    ],
                ],
            ]
        )->assertUnprocessable();

        $this->assertEquals(
            4.0,
            (float) DB::table(
                'stock_movements'
            )->sum(
                'quantity_signed'
            )
        );
    }

    public function test_receipts_are_business_scoped_and_receive_capability_is_enforced(): void
    {
        $workspace =
            $this->workspace(
                'receipt-scope@example.test',
                'Receipt Scope'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $warehouseId =
            $this->createWarehouse(
                'Gudang Scope'
            );

        $po =
            $this->createIssuedPurchaseOrder(
                'SERVICE',
                'Jasa Scope',
                1
            );

        $receipt =
            $this->postJson(
                '/api/v1/inventory/receipts',
                [
                    'purchase_order_id' =>
                        $po['id'],

                    'warehouse_id' =>
                        $warehouseId,

                    'items' => [
                        [
                            'purchase_order_item_id' =>
                                $po['item_id'],

                            'quantity_received' =>
                                1,
                        ],
                    ],
                ]
            )->assertCreated();

        $receiptId =
            (string) $receipt->json(
                'data.id'
            );

        $secondBusiness =
            $this->secondBusinessWorkspace(
                $workspace,
                'Cabang Dua'
            );

        $this->actingAsWorkspace(
            $secondBusiness
        );

        $this->getJson(
            "/api/v1/inventory/receipts/{$receiptId}"
        )->assertNotFound();

        $this->actingAsWorkspace(
            $workspace
        );

        $this->revokeOwnerCapability(
            $workspace,
            'inventory.receive'
        );

        $this->postJson(
            "/api/v1/inventory/receipts/{$receiptId}/actions/post"
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseHas(
            'goods_receipts',
            [
                'id' =>
                    $receiptId,

                'status' =>
                    'DRAFT',
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
                'Receipt Owner',

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

        return (string) $response->json(
            'data.id'
        );
    }

    private function createMaterial(
        string $code,
        string $name
    ): string {
        $response =
            $this->postJson(
                '/api/v1/inventory/materials',
                [
                    'code' =>
                        $code,

                    'name' =>
                        $name,
                ]
            )->assertCreated();

        return (string) $response->json(
            'data.id'
        );
    }

    private function createIssuedPurchaseOrder(
        string $itemType,
        string $name,
        float|int $quantity
    ): array {
        $supplier =
            $this->postJson(
                '/api/v1/suppliers',
                [
                    'name' =>
                        'Pemasok ' . $name,
                ]
            )->assertCreated();

        $supplierId =
            (string) $supplier->json(
                'data.id'
            );

        $order =
            $this->postJson(
                '/api/v1/purchasing/orders',
                [
                    'supplier_id' =>
                        $supplierId,

                    'items' => [
                        [
                            'item_type' =>
                                $itemType,

                            'name' =>
                                $name,

                            'quantity' =>
                                $quantity,

                            'unit_price' =>
                                1000,
                        ],
                    ],
                ]
            )
                ->assertCreated()
                ->assertJsonPath(
                    'data.status',
                    'DRAFT'
                );

        $orderId =
            (string) $order->json(
                'data.id'
            );

        $itemId =
            (string) $order->json(
                'data.items.0.id'
            );

        $this->postJson(
            "/api/v1/purchasing/orders/{$orderId}/actions/issue"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'ISSUED'
            );

        return [
            'id' =>
                $orderId,

            'item_id' =>
                $itemId,
        ];
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
