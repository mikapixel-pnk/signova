<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'supplier_bills',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->ulid('tenant_id');
                $table->ulid('business_id');

                $table->string(
                    'bill_number',
                    80
                );

                $table->ulid('supplier_id');

                $table->ulid(
                    'purchase_order_id'
                )->nullable();

                $table->ulid(
                    'goods_receipt_id'
                )->nullable();

                $table->string(
                    'supplier_invoice_number',
                    190
                )->nullable();

                $table->string(
                    'status',
                    32
                )->default('DRAFT');

                $table->char(
                    'currency',
                    3
                )->default('IDR');

                $table->date('bill_date');

                $table->date(
                    'due_date'
                )->nullable();

                $table->decimal(
                    'subtotal',
                    18,
                    2
                )->default(0);

                $table->decimal(
                    'discount_total',
                    18,
                    2
                )->default(0);

                $table->decimal(
                    'tax_total',
                    18,
                    2
                )->default(0);

                $table->decimal(
                    'total',
                    18,
                    2
                )->default(0);

                $table->text(
                    'notes'
                )->nullable();

                $table->ulid(
                    'created_by_user_id'
                );

                $table->ulid(
                    'posted_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'posted_at'
                )->nullable();

                $table->ulid(
                    'cancelled_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'cancelled_at'
                )->nullable();

                $table->text(
                    'cancellation_reason'
                )->nullable();

                $table->timestampsTz();

                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'supplier_bills_id_business_tenant_unique'
                );

                $table->unique(
                    [
                        'tenant_id',
                        'business_id',
                        'bill_number',
                    ],
                    'supplier_bills_business_number_unique'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'supplier_id',
                        'status',
                        'due_date',
                    ],
                    'supplier_bills_supplier_status_due_idx'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'purchase_order_id',
                    ],
                    'supplier_bills_po_idx'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'goods_receipt_id',
                    ],
                    'supplier_bills_receipt_idx'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'supplier_invoice_number',
                    ],
                    'supplier_bills_supplier_invoice_idx'
                );

                $table->foreign(
                    'tenant_id',
                    'supplier_bills_tenant_foreign'
                )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'business_id',
                        'tenant_id',
                    ],
                    'supplier_bills_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('business_profiles')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'supplier_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'supplier_bills_supplier_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('suppliers')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'purchase_order_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'supplier_bills_po_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('purchase_orders')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'goods_receipt_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'supplier_bills_receipt_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('goods_receipts')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'tenant_id',
                        'created_by_user_id',
                    ],
                    'supplier_bills_created_by_foreign'
                )
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'tenant_id',
                        'posted_by_user_id',
                    ],
                    'supplier_bills_posted_by_foreign'
                )
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'tenant_id',
                        'cancelled_by_user_id',
                    ],
                    'supplier_bills_cancelled_by_foreign'
                )
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users')
                    ->restrictOnDelete();
            }
        );

        DB::statement("
            ALTER TABLE supplier_bills
            ADD CONSTRAINT supplier_bills_status_check
            CHECK (
                status IN (
                    'DRAFT',
                    'POSTED',
                    'PARTIALLY_PAID',
                    'PAID',
                    'CANCELLED'
                )
            )
        ");

        DB::statement("
            ALTER TABLE supplier_bills
            ADD CONSTRAINT supplier_bills_totals_check
            CHECK (
                subtotal >= 0
                AND discount_total >= 0
                AND tax_total >= 0
                AND total >= 0
            )
        ");

        Schema::create(
            'supplier_bill_status_history',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->ulid('tenant_id');
                $table->ulid('business_id');
                $table->ulid('supplier_bill_id');

                $table->string(
                    'from_status',
                    32
                )->nullable();

                $table->string(
                    'to_status',
                    32
                );

                $table->string(
                    'action',
                    64
                );

                $table->ulid(
                    'actor_user_id'
                )->nullable();

                $table->text(
                    'reason'
                )->nullable();

                $table->timestampTz(
                    'created_at'
                )->useCurrent();

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'supplier_bill_id',
                        'created_at',
                    ],
                    'supplier_bill_history_bill_idx'
                );

                $table->foreign(
                    [
                        'supplier_bill_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'supplier_bill_history_bill_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('supplier_bills')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'tenant_id',
                        'actor_user_id',
                    ],
                    'supplier_bill_history_actor_foreign'
                )
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users')
                    ->restrictOnDelete();
            }
        );

        Schema::create(
            'supplier_payments',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->ulid('tenant_id');
                $table->ulid('business_id');

                $table->string(
                    'payment_number',
                    80
                );

                $table->ulid('supplier_id');
                $table->ulid('cash_account_id');

                $table->string(
                    'status',
                    32
                )->default('DRAFT');

                $table->char(
                    'currency',
                    3
                )->default('IDR');

                $table->decimal(
                    'amount',
                    18,
                    2
                );

                $table->timestampTz(
                    'paid_at'
                )->nullable();

                $table->string(
                    'reference',
                    190
                )->nullable();

                $table->text(
                    'notes'
                )->nullable();

                $table->ulid(
                    'cash_transaction_id'
                )->nullable();

                $table->ulid(
                    'reversal_cash_transaction_id'
                )->nullable();

                $table->ulid(
                    'created_by_user_id'
                );

                $table->ulid(
                    'posted_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'posted_at'
                )->nullable();

                $table->ulid(
                    'reversed_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'reversed_at'
                )->nullable();

                $table->text(
                    'reversal_reason'
                )->nullable();

                $table->timestampsTz();

                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'supplier_payments_id_business_tenant_unique'
                );

                $table->unique(
                    [
                        'tenant_id',
                        'business_id',
                        'payment_number',
                    ],
                    'supplier_payments_business_number_unique'
                );

                $table->unique(
                    [
                        'tenant_id',
                        'business_id',
                        'cash_transaction_id',
                    ],
                    'supplier_payments_cash_tx_unique'
                );

                $table->unique(
                    [
                        'tenant_id',
                        'business_id',
                        'reversal_cash_transaction_id',
                    ],
                    'supplier_payments_reversal_cash_tx_unique'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'supplier_id',
                        'status',
                        'paid_at',
                    ],
                    'supplier_payments_supplier_status_idx'
                );

                $table->foreign(
                    'tenant_id',
                    'supplier_payments_tenant_foreign'
                )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'business_id',
                        'tenant_id',
                    ],
                    'supplier_payments_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('business_profiles')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'supplier_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'supplier_payments_supplier_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('suppliers')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'cash_account_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'supplier_payments_cash_account_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('cash_accounts')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'cash_transaction_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'supplier_payments_cash_tx_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('cash_transactions')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'reversal_cash_transaction_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'supplier_payments_reversal_cash_tx_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('cash_transactions')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'tenant_id',
                        'created_by_user_id',
                    ],
                    'supplier_payments_created_by_foreign'
                )
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'tenant_id',
                        'posted_by_user_id',
                    ],
                    'supplier_payments_posted_by_foreign'
                )
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'tenant_id',
                        'reversed_by_user_id',
                    ],
                    'supplier_payments_reversed_by_foreign'
                )
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users')
                    ->restrictOnDelete();
            }
        );

        DB::statement("
            ALTER TABLE supplier_payments
            ADD CONSTRAINT supplier_payments_status_check
            CHECK (
                status IN (
                    'DRAFT',
                    'POSTED',
                    'REVERSED'
                )
            )
        ");

        DB::statement("
            ALTER TABLE supplier_payments
            ADD CONSTRAINT supplier_payments_amount_check
            CHECK (amount > 0)
        ");

        Schema::create(
            'supplier_payment_allocations',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->ulid('tenant_id');
                $table->ulid('business_id');

                $table->ulid(
                    'supplier_payment_id'
                );

                $table->ulid(
                    'supplier_bill_id'
                );

                $table->decimal(
                    'amount',
                    18,
                    2
                );

                $table->timestampsTz();

                $table->unique(
                    [
                        'supplier_payment_id',
                        'supplier_bill_id',
                    ],
                    'supplier_payment_allocations_payment_bill_unique'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'supplier_bill_id',
                    ],
                    'supplier_payment_allocations_bill_idx'
                );

                $table->foreign(
                    [
                        'supplier_payment_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'supplier_payment_allocations_payment_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('supplier_payments')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'supplier_bill_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'supplier_payment_allocations_bill_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('supplier_bills')
                    ->restrictOnDelete();
            }
        );

        DB::statement("
            ALTER TABLE supplier_payment_allocations
            ADD CONSTRAINT supplier_payment_allocations_amount_check
            CHECK (amount > 0)
        ");

        Schema::create(
            'supplier_payment_status_history',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->ulid('tenant_id');
                $table->ulid('business_id');

                $table->ulid(
                    'supplier_payment_id'
                );

                $table->string(
                    'from_status',
                    32
                )->nullable();

                $table->string(
                    'to_status',
                    32
                );

                $table->string(
                    'action',
                    64
                );

                $table->ulid(
                    'actor_user_id'
                )->nullable();

                $table->text(
                    'reason'
                )->nullable();

                $table->timestampTz(
                    'created_at'
                )->useCurrent();

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'supplier_payment_id',
                        'created_at',
                    ],
                    'supplier_payment_history_payment_idx'
                );

                $table->foreign(
                    [
                        'supplier_payment_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'supplier_payment_history_payment_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('supplier_payments')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'tenant_id',
                        'actor_user_id',
                    ],
                    'supplier_payment_history_actor_foreign'
                )
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users')
                    ->restrictOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'supplier_payment_status_history'
        );

        Schema::dropIfExists(
            'supplier_payment_allocations'
        );

        Schema::dropIfExists(
            'supplier_payments'
        );

        Schema::dropIfExists(
            'supplier_bill_status_history'
        );

        Schema::dropIfExists(
            'supplier_bills'
        );
    }
};
