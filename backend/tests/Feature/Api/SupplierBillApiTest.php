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

class SupplierBillApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_partial_receipt_creates_proportional_supplier_bill_without_cash_movement(): void
    {
        $workspace =
            $this->workspace(
                'payable-partial@example.test',
                'Payable Partial'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $supplierId =
            $this->createSupplier(
                'Pemasok Partial'
            );

        DB::table('suppliers')
            ->where(
                'id',
                $supplierId
            )
            ->update([
                'payment_terms_days' =>
                    14,
            ]);

        $warehouseId =
            $this->createWarehouse(
                'Gudang Partial'
            );

        $po =
            $this->createIssuedPurchaseOrder(
                $supplierId,
                'Jasa Produksi',
                10,
                25000
            );

        $receiptId =
            $this->createPostedReceipt(
                $po['id'],
                $po['item_id'],
                $warehouseId,
                4
            );

        $response =
            $this->postJson(
                '/api/v1/finance/payables/bills',
                [
                    'goods_receipt_id' =>
                        $receiptId,

                    'supplier_invoice_number' =>
                        'SUP-INV-001',

                    'bill_date' =>
                        '2026-09-20',

                    'notes' =>
                        'Tagihan penerimaan sebagian.',
                ]
            )
                ->assertCreated()
                ->assertJsonPath(
                    'data.status',
                    'DRAFT'
                )
                ->assertJsonPath(
                    'data.supplier.id',
                    $supplierId
                )
                ->assertJsonPath(
                    'data.purchase_order.id',
                    $po['id']
                )
                ->assertJsonPath(
                    'data.goods_receipt.id',
                    $receiptId
                )
                ->assertJsonPath(
                    'data.due_date',
                    '2026-10-04'
                );

        $billId =
            (string) $response->json(
                'data.id'
            );

        $this->assertSame(
            100000.0,
            (float) $response->json(
                'data.subtotal'
            )
        );

        $this->assertSame(
            100000.0,
            (float) $response->json(
                'data.total'
            )
        );

        $this->assertSame(
            0,
            DB::table(
                'cash_transactions'
            )->count()
        );

        $this->assertDatabaseHas(
            'supplier_bill_status_history',
            [
                'supplier_bill_id' =>
                    $billId,

                'from_status' =>
                    null,

                'to_status' =>
                    'DRAFT',

                'action' =>
                    'CREATED',
            ]
        );

        $this->postJson(
            "/api/v1/finance/payables/bills/{$billId}/actions/post"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'POSTED'
            )
            ->assertJsonPath(
                'data.status_label',
                'Tercatat'
            );

        $this->assertDatabaseHas(
            'supplier_bill_status_history',
            [
                'supplier_bill_id' =>
                    $billId,

                'from_status' =>
                    'DRAFT',

                'to_status' =>
                    'POSTED',

                'action' =>
                    'POSTED',
            ]
        );

        $this->assertSame(
            0,
            DB::table(
                'cash_transactions'
            )->count()
        );
    }

    public function test_duplicate_receipt_bill_is_rejected_and_active_bill_blocks_receipt_reversal(): void
    {
        $workspace =
            $this->workspace(
                'payable-guard@example.test',
                'Payable Guard'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $supplierId =
            $this->createSupplier(
                'Pemasok Guard'
            );

        $warehouseId =
            $this->createWarehouse(
                'Gudang Guard'
            );

        $po =
            $this->createIssuedPurchaseOrder(
                $supplierId,
                'Jasa Guard',
                1,
                75000
            );

        $receiptId =
            $this->createPostedReceipt(
                $po['id'],
                $po['item_id'],
                $warehouseId,
                1
            );

        $bill =
            $this->postJson(
                '/api/v1/finance/payables/bills',
                [
                    'goods_receipt_id' =>
                        $receiptId,

                    'supplier_invoice_number' =>
                        'SUP-GUARD-001',

                    'bill_date' =>
                        '2026-09-20',
                ]
            )->assertCreated();

        $billId =
            (string) $bill->json(
                'data.id'
            );

        $this->postJson(
            '/api/v1/finance/payables/bills',
            [
                'goods_receipt_id' =>
                    $receiptId,

                'supplier_invoice_number' =>
                    'SUP-GUARD-002',

                'bill_date' =>
                    '2026-09-20',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->postJson(
            "/api/v1/inventory/receipts/{$receiptId}/actions/reverse",
            [
                'reason' =>
                    'Koreksi penerimaan.',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->assertDatabaseHas(
            'goods_receipts',
            [
                'id' =>
                    $receiptId,

                'status' =>
                    'POSTED',
            ]
        );

        $this->postJson(
            "/api/v1/finance/payables/bills/{$billId}/actions/cancel",
            [
                'reason' =>
                    'Invoice pemasok perlu diperbaiki.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'CANCELLED'
            );

        $this->postJson(
            "/api/v1/inventory/receipts/{$receiptId}/actions/reverse",
            [
                'reason' =>
                    'Koreksi setelah tagihan dibatalkan.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'REVERSED'
            );

        $this->assertSame(
            0,
            DB::table(
                'cash_transactions'
            )->count()
        );
    }

    public function test_same_supplier_invoice_number_cannot_be_used_twice_while_active(): void
    {
        $workspace =
            $this->workspace(
                'payable-invoice@example.test',
                'Payable Invoice'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $supplierId =
            $this->createSupplier(
                'Pemasok Invoice'
            );

        $warehouseId =
            $this->createWarehouse(
                'Gudang Invoice'
            );

        $po =
            $this->createIssuedPurchaseOrder(
                $supplierId,
                'Jasa Invoice',
                2,
                50000
            );

        $firstReceipt =
            $this->createPostedReceipt(
                $po['id'],
                $po['item_id'],
                $warehouseId,
                1
            );

        $secondReceipt =
            $this->createPostedReceipt(
                $po['id'],
                $po['item_id'],
                $warehouseId,
                1
            );

        $this->postJson(
            '/api/v1/finance/payables/bills',
            [
                'goods_receipt_id' =>
                    $firstReceipt,

                'supplier_invoice_number' =>
                    'SUP-DUP-001',

                'bill_date' =>
                    '2026-09-20',
            ]
        )->assertCreated();

        $this->postJson(
            '/api/v1/finance/payables/bills',
            [
                'goods_receipt_id' =>
                    $secondReceipt,

                'supplier_invoice_number' =>
                    'SUP-DUP-001',

                'bill_date' =>
                    '2026-09-20',
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
                            'supplier_invoice_number',
                        ],
                    ],
                ],
            ]);

        $this->assertSame(
            1,
            DB::table(
                'supplier_bills'
            )
                ->where(
                    'supplier_id',
                    $supplierId
                )
                ->where(
                    'supplier_invoice_number',
                    'SUP-DUP-001'
                )
                ->where(
                    'status',
                    '<>',
                    'CANCELLED'
                )
                ->count()
        );
    }

    public function test_supplier_bills_are_business_scoped_and_manage_capability_is_enforced(): void
    {
        $workspace =
            $this->workspace(
                'payable-scope@example.test',
                'Payable Scope'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $supplierId =
            $this->createSupplier(
                'Pemasok Scope'
            );

        $warehouseId =
            $this->createWarehouse(
                'Gudang Scope'
            );

        $po =
            $this->createIssuedPurchaseOrder(
                $supplierId,
                'Jasa Scope',
                1,
                120000
            );

        $receiptId =
            $this->createPostedReceipt(
                $po['id'],
                $po['item_id'],
                $warehouseId,
                1
            );

        $bill =
            $this->postJson(
                '/api/v1/finance/payables/bills',
                [
                    'goods_receipt_id' =>
                        $receiptId,

                    'supplier_invoice_number' =>
                        'SUP-SCOPE-001',

                    'bill_date' =>
                        '2026-09-20',
                ]
            )->assertCreated();

        $billId =
            (string) $bill->json(
                'data.id'
            );

        $secondBusiness =
            $this->secondBusinessWorkspace(
                $workspace,
                'Cabang Payable'
            );

        $this->actingAsWorkspace(
            $secondBusiness
        );

        $this->getJson(
            "/api/v1/finance/payables/bills/{$billId}"
        )->assertNotFound();

        $this->actingAsWorkspace(
            $workspace
        );

        $this->revokeOwnerCapability(
            $workspace,
            'finance.payable.manage'
        );

        $this->postJson(
            "/api/v1/finance/payables/bills/{$billId}/actions/post"
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseHas(
            'supplier_bills',
            [
                'id' =>
                    $billId,

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
                'Payable Owner',

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

    private function createSupplier(
        string $name
    ): string {
        $response =
            $this->postJson(
                '/api/v1/suppliers',
                [
                    'name' =>
                        $name,
                ]
            )->assertCreated();

        return (string) $response->json(
            'data.id'
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

    private function createIssuedPurchaseOrder(
        string $supplierId,
        string $name,
        float|int $quantity,
        float|int $unitPrice
    ): array {
        $order =
            $this->postJson(
                '/api/v1/purchasing/orders',
                [
                    'supplier_id' =>
                        $supplierId,

                    'items' => [
                        [
                            'item_type' =>
                                'SERVICE',

                            'name' =>
                                $name,

                            'quantity' =>
                                $quantity,

                            'unit_price' =>
                                $unitPrice,
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

    private function createPostedReceipt(
        string $purchaseOrderId,
        string $purchaseOrderItemId,
        string $warehouseId,
        float|int $quantity
    ): string {
        $receipt =
            $this->postJson(
                '/api/v1/inventory/receipts',
                [
                    'purchase_order_id' =>
                        $purchaseOrderId,

                    'warehouse_id' =>
                        $warehouseId,

                    'items' => [
                        [
                            'purchase_order_item_id' =>
                                $purchaseOrderItemId,

                            'quantity_received' =>
                                $quantity,
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

        return $receiptId;
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
