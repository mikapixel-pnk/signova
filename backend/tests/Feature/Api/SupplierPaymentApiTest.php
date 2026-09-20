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

class SupplierPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_partial_then_full_payment_updates_payable_and_cash_ledger(): void
    {
        $workspace =
            $this->workspace(
                'supplier-payment-partial@example.test',
                'Supplier Payment Partial'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $supplierId =
            $this->createSupplier(
                'Pemasok Pembayaran Partial'
            );

        $warehouseId =
            $this->createWarehouse(
                'Gudang Pembayaran Partial'
            );

        $billId =
            $this->createPostedBill(
                $supplierId,
                $warehouseId,
                100000,
                'PARTIAL-001'
            );

        $cashAccountId =
            $this->createCashAccount(
                'Kas Utama'
            );

        $first =
            $this->postJson(
                '/api/v1/finance/payables/payments',
                [
                    'cash_account_id' =>
                        $cashAccountId,

                    'paid_at' =>
                        '2026-09-20 10:00:00',

                    'reference' =>
                        'TRX-PARTIAL-001',

                    'allocations' => [
                        [
                            'supplier_bill_id' =>
                                $billId,

                            'amount' =>
                                40000,
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
                    'data.amount',
                    '40000.00'
                )
                ->assertJsonPath(
                    'data.allocations.0.supplier_bill_id',
                    $billId
                );

        $firstPaymentId =
            (string) $first->json(
                'data.id'
            );

        $this->assertMatchesRegularExpression(
            '/^SP-\d{6}-\d{4}$/',
            (string) $first->json(
                'data.payment_number'
            )
        );

        $this->assertSame(
            0,
            DB::table(
                'cash_transactions'
            )->count()
        );

        $this->postJson(
            "/api/v1/finance/payables/payments/{$firstPaymentId}/actions/post"
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
            'supplier_bills',
            [
                'id' =>
                    $billId,

                'status' =>
                    'PARTIALLY_PAID',
            ]
        );

        $this->assertDatabaseHas(
            'cash_transactions',
            [
                'cash_account_id' =>
                    $cashAccountId,

                'direction' =>
                    'OUT',

                'source_type' =>
                    'SUPPLIER_PAYMENT',

                'source_id' =>
                    $firstPaymentId,
            ]
        );

        $this->assertSame(
            40000.0,
            (float) DB::table(
                'cash_transactions'
            )
                ->where(
                    'source_type',
                    'SUPPLIER_PAYMENT'
                )
                ->where(
                    'source_id',
                    $firstPaymentId
                )
                ->value('amount')
        );

        /*
         * Retry POST tidak boleh membuat cash-out kedua.
         */
        $this->postJson(
            "/api/v1/finance/payables/payments/{$firstPaymentId}/actions/post"
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->assertSame(
            1,
            DB::table(
                'cash_transactions'
            )
                ->where(
                    'source_type',
                    'SUPPLIER_PAYMENT'
                )
                ->where(
                    'source_id',
                    $firstPaymentId
                )
                ->count()
        );

        $second =
            $this->postJson(
                '/api/v1/finance/payables/payments',
                [
                    'cash_account_id' =>
                        $cashAccountId,

                    'reference' =>
                        'TRX-PARTIAL-002',

                    'allocations' => [
                        [
                            'supplier_bill_id' =>
                                $billId,

                            'amount' =>
                                60000,
                        ],
                    ],
                ]
            )->assertCreated();

        $secondPaymentId =
            (string) $second->json(
                'data.id'
            );

        $this->postJson(
            "/api/v1/finance/payables/payments/{$secondPaymentId}/actions/post"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'POSTED'
            );

        $this->assertDatabaseHas(
            'supplier_bills',
            [
                'id' =>
                    $billId,

                'status' =>
                    'PAID',
            ]
        );

        $this->assertSame(
            100000.0,
            (float) DB::table(
                'cash_transactions'
            )
                ->where(
                    'source_type',
                    'SUPPLIER_PAYMENT'
                )
                ->where(
                    'direction',
                    'OUT'
                )
                ->sum('amount')
        );
    }

    public function test_reversal_creates_cash_in_and_recalculates_supplier_bill(): void
    {
        $workspace =
            $this->workspace(
                'supplier-payment-reverse@example.test',
                'Supplier Payment Reverse'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $supplierId =
            $this->createSupplier(
                'Pemasok Reverse'
            );

        $warehouseId =
            $this->createWarehouse(
                'Gudang Reverse'
            );

        $billId =
            $this->createPostedBill(
                $supplierId,
                $warehouseId,
                125000,
                'REV-001'
            );

        $cashAccountId =
            $this->createCashAccount(
                'Bank Operasional'
            );

        $payment =
            $this->postJson(
                '/api/v1/finance/payables/payments',
                [
                    'cash_account_id' =>
                        $cashAccountId,

                    'reference' =>
                        'PAY-REV-001',

                    'allocations' => [
                        [
                            'supplier_bill_id' =>
                                $billId,

                            'amount' =>
                                125000,
                        ],
                    ],
                ]
            )->assertCreated();

        $paymentId =
            (string) $payment->json(
                'data.id'
            );

        $posted =
            $this->postJson(
                "/api/v1/finance/payables/payments/{$paymentId}/actions/post"
            )
                ->assertOk()
                ->assertJsonPath(
                    'data.status',
                    'POSTED'
                );

        $originalId =
            (string) $posted->json(
                'data.cash_transaction_id'
            );

        $this->assertDatabaseHas(
            'supplier_bills',
            [
                'id' =>
                    $billId,

                'status' =>
                    'PAID',
            ]
        );

        $reversed =
            $this->postJson(
                "/api/v1/finance/payables/payments/{$paymentId}/actions/reverse",
                [
                    'reason' =>
                        'Transfer bank dikoreksi.',
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
                );

        $reversalId =
            (string) $reversed->json(
                'data.reversal_cash_transaction_id'
            );

        $this->assertDatabaseHas(
            'cash_transactions',
            [
                'id' =>
                    $originalId,

                'direction' =>
                    'OUT',

                'source_type' =>
                    'SUPPLIER_PAYMENT',

                'source_id' =>
                    $paymentId,
            ]
        );

        $this->assertDatabaseHas(
            'cash_transactions',
            [
                'id' =>
                    $reversalId,

                'direction' =>
                    'IN',

                'source_type' =>
                    'SUPPLIER_PAYMENT_REVERSAL',

                'source_id' =>
                    $paymentId,

                'reversal_of_transaction_id' =>
                    $originalId,
            ]
        );

        $this->assertDatabaseHas(
            'supplier_bills',
            [
                'id' =>
                    $billId,

                'status' =>
                    'POSTED',
            ]
        );

        /*
         * Retry reversal juga harus idempotent.
         */
        $this->postJson(
            "/api/v1/finance/payables/payments/{$paymentId}/actions/reverse",
            [
                'reason' =>
                    'Percobaan ulang.',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->assertSame(
            2,
            DB::table(
                'cash_transactions'
            )
                ->where(
                    'source_id',
                    $paymentId
                )
                ->count()
        );

        $this->assertSame(
            0.0,
            (float) DB::table(
                'cash_transactions'
            )
                ->where(
                    'source_id',
                    $paymentId
                )
                ->selectRaw("
                    COALESCE(
                        SUM(
                            CASE
                                WHEN direction = 'IN'
                                    THEN amount
                                ELSE -amount
                            END
                        ),
                        0
                    ) AS net
                ")
                ->value('net')
        );
    }

    public function test_post_rechecks_outstanding_and_blocks_overpayment(): void
    {
        $workspace =
            $this->workspace(
                'supplier-payment-overpay@example.test',
                'Supplier Payment Overpay'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $supplierId =
            $this->createSupplier(
                'Pemasok Overpay'
            );

        $warehouseId =
            $this->createWarehouse(
                'Gudang Overpay'
            );

        $billId =
            $this->createPostedBill(
                $supplierId,
                $warehouseId,
                100000,
                'OVERPAY-001'
            );

        $cashAccountId =
            $this->createCashAccount(
                'Kas Overpay'
            );

        /*
         * Dua draft dibuat saat outstanding masih Rp100.000.
         */
        $first =
            $this->postJson(
                '/api/v1/finance/payables/payments',
                [
                    'cash_account_id' =>
                        $cashAccountId,

                    'allocations' => [
                        [
                            'supplier_bill_id' =>
                                $billId,

                            'amount' =>
                                70000,
                        ],
                    ],
                ]
            )->assertCreated();

        $second =
            $this->postJson(
                '/api/v1/finance/payables/payments',
                [
                    'cash_account_id' =>
                        $cashAccountId,

                    'allocations' => [
                        [
                            'supplier_bill_id' =>
                                $billId,

                            'amount' =>
                                40000,
                        ],
                    ],
                ]
            )->assertCreated();

        $firstId =
            (string) $first->json(
                'data.id'
            );

        $secondId =
            (string) $second->json(
                'data.id'
            );

        $this->postJson(
            "/api/v1/finance/payables/payments/{$firstId}/actions/post"
        )->assertOk();

        /*
         * Saat payment kedua diposting, outstanding tinggal Rp30.000.
         */
        $this->postJson(
            "/api/v1/finance/payables/payments/{$secondId}/actions/post"
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
                            'allocations.0.amount',
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseHas(
            'supplier_payments',
            [
                'id' =>
                    $secondId,

                'status' =>
                    'DRAFT',

                'cash_transaction_id' =>
                    null,
            ]
        );

        $this->assertSame(
            1,
            DB::table(
                'cash_transactions'
            )
                ->where(
                    'source_type',
                    'SUPPLIER_PAYMENT'
                )
                ->count()
        );

        $this->assertDatabaseHas(
            'supplier_bills',
            [
                'id' =>
                    $billId,

                'status' =>
                    'PARTIALLY_PAID',
            ]
        );
    }

    public function test_inactive_cash_account_blocks_post_without_cash_movement(): void
    {
        $workspace =
            $this->workspace(
                'supplier-payment-inactive@example.test',
                'Supplier Payment Inactive'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $supplierId =
            $this->createSupplier(
                'Pemasok Inactive'
            );

        $warehouseId =
            $this->createWarehouse(
                'Gudang Inactive'
            );

        $billId =
            $this->createPostedBill(
                $supplierId,
                $warehouseId,
                80000,
                'INACTIVE-001'
            );

        $cashAccountId =
            $this->createCashAccount(
                'Kas Akan Nonaktif'
            );

        $payment =
            $this->postJson(
                '/api/v1/finance/payables/payments',
                [
                    'cash_account_id' =>
                        $cashAccountId,

                    'allocations' => [
                        [
                            'supplier_bill_id' =>
                                $billId,

                            'amount' =>
                                80000,
                        ],
                    ],
                ]
            )->assertCreated();

        $paymentId =
            (string) $payment->json(
                'data.id'
            );

        DB::table(
            'cash_accounts'
        )
            ->where(
                'id',
                $cashAccountId
            )
            ->update([
                'status' =>
                    'INACTIVE',

                'updated_at' =>
                    now(),
            ]);

        $this->postJson(
            "/api/v1/finance/payables/payments/{$paymentId}/actions/post"
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->assertDatabaseHas(
            'supplier_payments',
            [
                'id' =>
                    $paymentId,

                'status' =>
                    'DRAFT',

                'cash_transaction_id' =>
                    null,
            ]
        );

        $this->assertDatabaseHas(
            'supplier_bills',
            [
                'id' =>
                    $billId,

                'status' =>
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

    public function test_one_payment_can_allocate_multiple_bills_of_same_supplier(): void
    {
        $workspace =
            $this->workspace(
                'supplier-payment-multi@example.test',
                'Supplier Payment Multi'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $supplierId =
            $this->createSupplier(
                'Pemasok Multi'
            );

        $warehouseId =
            $this->createWarehouse(
                'Gudang Multi'
            );

        $firstBillId =
            $this->createPostedBill(
                $supplierId,
                $warehouseId,
                60000,
                'MULTI-001'
            );

        $secondBillId =
            $this->createPostedBill(
                $supplierId,
                $warehouseId,
                40000,
                'MULTI-002'
            );

        $cashAccountId =
            $this->createCashAccount(
                'Kas Multi'
            );

        $payment =
            $this->postJson(
                '/api/v1/finance/payables/payments',
                [
                    'cash_account_id' =>
                        $cashAccountId,

                    'allocations' => [
                        [
                            'supplier_bill_id' =>
                                $firstBillId,

                            'amount' =>
                                30000,
                        ],
                        [
                            'supplier_bill_id' =>
                                $secondBillId,

                            'amount' =>
                                20000,
                        ],
                    ],
                ]
            )
                ->assertCreated()
                ->assertJsonPath(
                    'data.amount',
                    '50000.00'
                );

        $paymentId =
            (string) $payment->json(
                'data.id'
            );

        $this->postJson(
            "/api/v1/finance/payables/payments/{$paymentId}/actions/post"
        )->assertOk();

        $this->assertDatabaseHas(
            'supplier_bills',
            [
                'id' =>
                    $firstBillId,

                'status' =>
                    'PARTIALLY_PAID',
            ]
        );

        $this->assertDatabaseHas(
            'supplier_bills',
            [
                'id' =>
                    $secondBillId,

                'status' =>
                    'PARTIALLY_PAID',
            ]
        );

        $this->assertSame(
            2,
            DB::table(
                'supplier_payment_allocations'
            )
                ->where(
                    'supplier_payment_id',
                    $paymentId
                )
                ->count()
        );

        $this->assertSame(
            50000.0,
            (float) DB::table(
                'cash_transactions'
            )
                ->where(
                    'source_type',
                    'SUPPLIER_PAYMENT'
                )
                ->where(
                    'source_id',
                    $paymentId
                )
                ->value('amount')
        );
    }

    public function test_supplier_payments_are_business_scoped_and_manage_capability_is_enforced(): void
    {
        $workspace =
            $this->workspace(
                'supplier-payment-scope@example.test',
                'Supplier Payment Scope'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $supplierId =
            $this->createSupplier(
                'Pemasok Scope Payment'
            );

        $warehouseId =
            $this->createWarehouse(
                'Gudang Scope Payment'
            );

        $billId =
            $this->createPostedBill(
                $supplierId,
                $warehouseId,
                90000,
                'SCOPE-001'
            );

        $cashAccountId =
            $this->createCashAccount(
                'Kas Scope'
            );

        $payment =
            $this->postJson(
                '/api/v1/finance/payables/payments',
                [
                    'cash_account_id' =>
                        $cashAccountId,

                    'allocations' => [
                        [
                            'supplier_bill_id' =>
                                $billId,

                            'amount' =>
                                90000,
                        ],
                    ],
                ]
            )->assertCreated();

        $paymentId =
            (string) $payment->json(
                'data.id'
            );

        $secondBusiness =
            $this->secondBusinessWorkspace(
                $workspace,
                'Cabang Pembayaran'
            );

        $this->actingAsWorkspace(
            $secondBusiness
        );

        $this->getJson(
            "/api/v1/finance/payables/payments/{$paymentId}"
        )->assertNotFound();

        $this->actingAsWorkspace(
            $workspace
        );

        $this->revokeOwnerCapability(
            $workspace,
            'finance.payable.manage'
        );

        $this->postJson(
            "/api/v1/finance/payables/payments/{$paymentId}/actions/post"
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseHas(
            'supplier_payments',
            [
                'id' =>
                    $paymentId,

                'status' =>
                    'DRAFT',
            ]
        );

        $this->assertSame(
            0,
            DB::table(
                'cash_transactions'
            )->count()
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
                'Payable Payment Owner',

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

    private function createCashAccount(
        string $name
    ): string {
        $response =
            $this->postJson(
                '/api/v1/finance/cash-accounts',
                [
                    'name' =>
                        $name,

                    'type' =>
                        'CASH',
                ]
            )->assertCreated();

        return (string) $response->json(
            'data.id'
        );
    }

    private function createPostedBill(
        string $supplierId,
        string $warehouseId,
        float|int $amount,
        string $suffix
    ): string {
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
                                'Jasa ' . $suffix,

                            'quantity' =>
                                1,

                            'unit_price' =>
                                $amount,
                        ],
                    ],
                ]
            )->assertCreated();

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
        )->assertOk();

        $receipt =
            $this->postJson(
                '/api/v1/inventory/receipts',
                [
                    'purchase_order_id' =>
                        $orderId,

                    'warehouse_id' =>
                        $warehouseId,

                    'items' => [
                        [
                            'purchase_order_item_id' =>
                                $itemId,

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
        )->assertOk();

        $bill =
            $this->postJson(
                '/api/v1/finance/payables/bills',
                [
                    'goods_receipt_id' =>
                        $receiptId,

                    'supplier_invoice_number' =>
                        'INV-' . $suffix,

                    'bill_date' =>
                        '2026-09-20',
                ]
            )->assertCreated();

        $billId =
            (string) $bill->json(
                'data.id'
            );

        $this->postJson(
            "/api/v1/finance/payables/bills/{$billId}/actions/post"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'POSTED'
            );

        return $billId;
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
