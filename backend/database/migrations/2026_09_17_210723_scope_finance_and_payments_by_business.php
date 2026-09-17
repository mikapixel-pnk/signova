<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(
            function (): void {
                /*
                 * ---------------------------------------------------------
                 * EXPAND
                 * ---------------------------------------------------------
                 */

                foreach (
                    [
                        'cash_accounts',
                        'cash_transactions',
                        'incomes',
                        'expenses',
                        'payments',
                        'payment_allocations',
                        'payment_reversals',
                    ] as $tableName
                ) {
                    Schema::table(
                        $tableName,
                        function (
                            Blueprint $table
                        ): void {
                            $table
                                ->ulid(
                                    'business_id'
                                )
                                ->nullable()
                                ->after(
                                    'tenant_id'
                                );
                        }
                    );
                }

                /*
                 * ---------------------------------------------------------
                 * BACKFILL
                 * ---------------------------------------------------------
                 *
                 * Cash account legacy:
                 * deterministic default Business milik Tenant.
                 */

                DB::statement(
                    <<<'SQL'
                    UPDATE cash_accounts AS ca
                    SET business_id = bp.id
                    FROM business_profiles AS bp
                    WHERE ca.business_id IS NULL
                      AND bp.tenant_id = ca.tenant_id
                      AND bp.is_default = TRUE
                    SQL
                );

                /*
                 * Cash transaction selalu mengikuti Cash Account.
                 */

                DB::statement(
                    <<<'SQL'
                    UPDATE cash_transactions AS ct
                    SET business_id = ca.business_id
                    FROM cash_accounts AS ca
                    WHERE ct.business_id IS NULL
                      AND ct.cash_account_id = ca.id
                      AND ct.tenant_id = ca.tenant_id
                    SQL
                );

                /*
                 * Income/Expense yang sudah memiliki Cash Account
                 * mengikuti Business Cash Account tersebut.
                 */

                DB::statement(
                    <<<'SQL'
                    UPDATE incomes AS income
                    SET business_id = ca.business_id
                    FROM cash_accounts AS ca
                    WHERE income.business_id IS NULL
                      AND income.cash_account_id IS NOT NULL
                      AND income.cash_account_id = ca.id
                      AND income.tenant_id = ca.tenant_id
                    SQL
                );

                DB::statement(
                    <<<'SQL'
                    UPDATE expenses AS expense
                    SET business_id = ca.business_id
                    FROM cash_accounts AS ca
                    WHERE expense.business_id IS NULL
                      AND expense.cash_account_id IS NOT NULL
                      AND expense.cash_account_id = ca.id
                      AND expense.tenant_id = ca.tenant_id
                    SQL
                );

                /*
                 * Draft Income/Expense tanpa Cash Account merupakan
                 * legacy tenant-level data. Pindahkan secara deterministik
                 * ke default Business Tenant.
                 */

                DB::statement(
                    <<<'SQL'
                    UPDATE incomes AS income
                    SET business_id = bp.id
                    FROM business_profiles AS bp
                    WHERE income.business_id IS NULL
                      AND bp.tenant_id = income.tenant_id
                      AND bp.is_default = TRUE
                    SQL
                );

                DB::statement(
                    <<<'SQL'
                    UPDATE expenses AS expense
                    SET business_id = bp.id
                    FROM business_profiles AS bp
                    WHERE expense.business_id IS NULL
                      AND bp.tenant_id = expense.tenant_id
                      AND bp.is_default = TRUE
                    SQL
                );

                /*
                 * Payment ownership mengikuti Customer.
                 * Customer sendiri sudah canonical tenant + business.
                 */

                DB::statement(
                    <<<'SQL'
                    UPDATE payments AS payment
                    SET business_id = customer.business_id
                    FROM customers AS customer
                    WHERE payment.business_id IS NULL
                      AND payment.customer_id = customer.id
                      AND payment.tenant_id = customer.tenant_id
                    SQL
                );

                /*
                 * Allocation dan reversal mengikuti Payment.
                 */

                DB::statement(
                    <<<'SQL'
                    UPDATE payment_allocations AS allocation
                    SET business_id = payment.business_id
                    FROM payments AS payment
                    WHERE allocation.business_id IS NULL
                      AND allocation.payment_id = payment.id
                      AND allocation.tenant_id = payment.tenant_id
                    SQL
                );

                DB::statement(
                    <<<'SQL'
                    UPDATE payment_reversals AS reversal
                    SET business_id = payment.business_id
                    FROM payments AS payment
                    WHERE reversal.business_id IS NULL
                      AND reversal.payment_id = payment.id
                      AND reversal.tenant_id = payment.tenant_id
                    SQL
                );

                /*
                 * ---------------------------------------------------------
                 * ASSERT BACKFILL COMPLETE
                 * ---------------------------------------------------------
                 */

                foreach (
                    [
                        'cash_accounts',
                        'cash_transactions',
                        'incomes',
                        'expenses',
                        'payments',
                        'payment_allocations',
                        'payment_reversals',
                    ] as $tableName
                ) {
                    $missing =
                        DB::table(
                            $tableName
                        )
                            ->whereNull(
                                'business_id'
                            )
                            ->count();

                    if ($missing > 0) {
                        throw new \RuntimeException(
                            "Finance business backfill failed: "
                            . "{$tableName} has "
                            . "{$missing} rows without business_id."
                        );
                    }
                }

                /*
                 * ---------------------------------------------------------
                 * ASSERT SAME-BUSINESS LEGACY DATA
                 * ---------------------------------------------------------
                 */

                $invalidPaymentCustomer =
                    DB::table(
                        'payments as payment'
                    )
                        ->join(
                            'customers as customer',
                            function ($join): void {
                                $join
                                    ->on(
                                        'customer.id',
                                        '=',
                                        'payment.customer_id'
                                    )
                                    ->on(
                                        'customer.tenant_id',
                                        '=',
                                        'payment.tenant_id'
                                    );
                            }
                        )
                        ->whereColumn(
                            'payment.business_id',
                            '<>',
                            'customer.business_id'
                        )
                        ->exists();

                if ($invalidPaymentCustomer) {
                    throw new \RuntimeException(
                        'Payment and Customer Business ownership mismatch.'
                    );
                }

                $invalidPaymentCashAccount =
                    DB::table(
                        'payments as payment'
                    )
                        ->join(
                            'cash_accounts as cash_account',
                            function ($join): void {
                                $join
                                    ->on(
                                        'cash_account.id',
                                        '=',
                                        'payment.cash_account_id'
                                    )
                                    ->on(
                                        'cash_account.tenant_id',
                                        '=',
                                        'payment.tenant_id'
                                    );
                            }
                        )
                        ->whereNotNull(
                            'payment.cash_account_id'
                        )
                        ->whereColumn(
                            'payment.business_id',
                            '<>',
                            'cash_account.business_id'
                        )
                        ->exists();

                if ($invalidPaymentCashAccount) {
                    throw new \RuntimeException(
                        'Payment and Cash Account Business ownership mismatch.'
                    );
                }

                $invalidAllocationInvoice =
                    DB::table(
                        'payment_allocations as allocation'
                    )
                        ->join(
                            'invoices as invoice',
                            function ($join): void {
                                $join
                                    ->on(
                                        'invoice.id',
                                        '=',
                                        'allocation.invoice_id'
                                    )
                                    ->on(
                                        'invoice.tenant_id',
                                        '=',
                                        'allocation.tenant_id'
                                    );
                            }
                        )
                        ->whereColumn(
                            'allocation.business_id',
                            '<>',
                            'invoice.business_id'
                        )
                        ->exists();

                if ($invalidAllocationInvoice) {
                    throw new \RuntimeException(
                        'Payment Allocation and Invoice Business ownership mismatch.'
                    );
                }

                /*
                 * ---------------------------------------------------------
                 * CONTRACT business_id
                 * ---------------------------------------------------------
                 */

                foreach (
                    [
                        'cash_accounts',
                        'cash_transactions',
                        'incomes',
                        'expenses',
                        'payments',
                        'payment_allocations',
                        'payment_reversals',
                    ] as $tableName
                ) {
                    DB::statement(
                        sprintf(
                            'ALTER TABLE %s '
                            . 'ALTER COLUMN business_id SET NOT NULL',
                            $tableName
                        )
                    );
                }

                /*
                 * Parent compatibility keys for composite FK.
                 */

                Schema::table(
                    'cash_accounts',
                    function (
                        Blueprint $table
                    ): void {
                        $table->unique(
                            [
                                'id',
                                'business_id',
                                'tenant_id',
                            ],
                            'cash_accounts_id_business_tenant_unique'
                        );

                        $table->index(
                            [
                                'tenant_id',
                                'business_id',
                                'status',
                                'type',
                            ],
                            'cash_accounts_tenant_business_status_type_idx'
                        );

                        $table->foreign(
                            [
                                'business_id',
                                'tenant_id',
                            ],
                            'cash_accounts_business_tenant_foreign'
                        )
                            ->references([
                                'id',
                                'tenant_id',
                            ])
                            ->on(
                                'business_profiles'
                            );
                    }
                );

                /*
                 * Default Cash Account sekarang per Business,
                 * bukan satu per Tenant.
                 */

                DB::statement(
                    'DROP INDEX IF EXISTS '
                    . 'cash_accounts_one_default_per_tenant_idx'
                );

                DB::statement(
                    <<<'SQL'
                    CREATE UNIQUE INDEX
                        cash_accounts_one_default_per_business_idx
                    ON cash_accounts (
                        tenant_id,
                        business_id
                    )
                    WHERE is_default = TRUE
                    SQL
                );

                Schema::table(
                    'cash_transactions',
                    function (
                        Blueprint $table
                    ): void {
                        $table->unique(
                            [
                                'id',
                                'business_id',
                                'tenant_id',
                            ],
                            'cash_transactions_id_business_tenant_unique'
                        );

                        $table->index(
                            [
                                'tenant_id',
                                'business_id',
                                'cash_account_id',
                                'occurred_at',
                            ],
                            'cash_transactions_business_account_date_idx'
                        );

                        $table->index(
                            [
                                'tenant_id',
                                'business_id',
                                'direction',
                                'occurred_at',
                            ],
                            'cash_transactions_business_direction_date_idx'
                        );

                        $table->foreign(
                            [
                                'business_id',
                                'tenant_id',
                            ],
                            'cash_transactions_business_tenant_foreign'
                        )
                            ->references([
                                'id',
                                'tenant_id',
                            ])
                            ->on(
                                'business_profiles'
                            );

                        $table->foreign(
                            [
                                'cash_account_id',
                                'business_id',
                                'tenant_id',
                            ],
                            'cash_transactions_account_business_tenant_fk'
                        )
                            ->references([
                                'id',
                                'business_id',
                                'tenant_id',
                            ])
                            ->on(
                                'cash_accounts'
                            );

                        $table->foreign(
                            [
                                'reversal_of_transaction_id',
                                'business_id',
                                'tenant_id',
                            ],
                            'cash_transactions_reversal_business_tenant_fk'
                        )
                            ->references([
                                'id',
                                'business_id',
                                'tenant_id',
                            ])
                            ->on(
                                'cash_transactions'
                            );
                    }
                );

                Schema::table(
                    'incomes',
                    function (
                        Blueprint $table
                    ): void {
                        $table->index(
                            [
                                'tenant_id',
                                'business_id',
                                'status',
                                'occurred_at',
                            ],
                            'incomes_tenant_business_status_date_idx'
                        );

                        $table->index(
                            [
                                'tenant_id',
                                'business_id',
                                'cash_account_id',
                                'occurred_at',
                            ],
                            'incomes_tenant_business_account_date_idx'
                        );

                        $table->foreign(
                            [
                                'business_id',
                                'tenant_id',
                            ],
                            'incomes_business_tenant_foreign'
                        )
                            ->references([
                                'id',
                                'tenant_id',
                            ])
                            ->on(
                                'business_profiles'
                            );

                        $table->foreign(
                            [
                                'cash_account_id',
                                'business_id',
                                'tenant_id',
                            ],
                            'incomes_account_business_tenant_foreign'
                        )
                            ->references([
                                'id',
                                'business_id',
                                'tenant_id',
                            ])
                            ->on(
                                'cash_accounts'
                            );
                    }
                );

                Schema::table(
                    'expenses',
                    function (
                        Blueprint $table
                    ): void {
                        $table->index(
                            [
                                'tenant_id',
                                'business_id',
                                'status',
                                'incurred_at',
                            ],
                            'expenses_tenant_business_status_date_idx'
                        );

                        $table->index(
                            [
                                'tenant_id',
                                'business_id',
                                'cash_account_id',
                                'incurred_at',
                            ],
                            'expenses_tenant_business_account_date_idx'
                        );

                        $table->foreign(
                            [
                                'business_id',
                                'tenant_id',
                            ],
                            'expenses_business_tenant_foreign'
                        )
                            ->references([
                                'id',
                                'tenant_id',
                            ])
                            ->on(
                                'business_profiles'
                            );

                        $table->foreign(
                            [
                                'cash_account_id',
                                'business_id',
                                'tenant_id',
                            ],
                            'expenses_account_business_tenant_foreign'
                        )
                            ->references([
                                'id',
                                'business_id',
                                'tenant_id',
                            ])
                            ->on(
                                'cash_accounts'
                            );
                    }
                );

                Schema::table(
                    'payments',
                    function (
                        Blueprint $table
                    ): void {
                        $table->unique(
                            [
                                'id',
                                'business_id',
                                'tenant_id',
                            ],
                            'payments_id_business_tenant_unique'
                        );

                        $table->index(
                            [
                                'tenant_id',
                                'business_id',
                                'status',
                                'paid_at',
                            ],
                            'payments_tenant_business_status_paid_idx'
                        );

                        $table->index(
                            [
                                'tenant_id',
                                'business_id',
                                'customer_id',
                                'paid_at',
                            ],
                            'payments_tenant_business_customer_paid_idx'
                        );

                        $table->index(
                            [
                                'tenant_id',
                                'business_id',
                                'cash_account_id',
                            ],
                            'payments_tenant_business_cash_account_idx'
                        );

                        $table->foreign(
                            [
                                'business_id',
                                'tenant_id',
                            ],
                            'payments_business_tenant_foreign'
                        )
                            ->references([
                                'id',
                                'tenant_id',
                            ])
                            ->on(
                                'business_profiles'
                            );

                        $table->foreign(
                            [
                                'customer_id',
                                'business_id',
                                'tenant_id',
                            ],
                            'payments_customer_business_tenant_foreign'
                        )
                            ->references([
                                'id',
                                'business_id',
                                'tenant_id',
                            ])
                            ->on(
                                'customers'
                            );

                        $table->foreign(
                            [
                                'cash_account_id',
                                'business_id',
                                'tenant_id',
                            ],
                            'payments_cash_account_business_tenant_foreign'
                        )
                            ->references([
                                'id',
                                'business_id',
                                'tenant_id',
                            ])
                            ->on(
                                'cash_accounts'
                            );
                    }
                );

                Schema::table(
                    'payment_allocations',
                    function (
                        Blueprint $table
                    ): void {
                        $table->unique(
                            [
                                'tenant_id',
                                'business_id',
                                'payment_id',
                                'invoice_id',
                            ],
                            'payment_allocations_business_payment_invoice_unique'
                        );

                        $table->index(
                            [
                                'tenant_id',
                                'business_id',
                                'invoice_id',
                            ],
                            'payment_allocations_business_invoice_idx'
                        );

                        $table->foreign(
                            [
                                'business_id',
                                'tenant_id',
                            ],
                            'payment_allocations_business_tenant_foreign'
                        )
                            ->references([
                                'id',
                                'tenant_id',
                            ])
                            ->on(
                                'business_profiles'
                            );

                        $table->foreign(
                            [
                                'payment_id',
                                'business_id',
                                'tenant_id',
                            ],
                            'payment_allocations_payment_business_tenant_fk'
                        )
                            ->references([
                                'id',
                                'business_id',
                                'tenant_id',
                            ])
                            ->on(
                                'payments'
                            );

                        $table->foreign(
                            [
                                'invoice_id',
                                'business_id',
                                'tenant_id',
                            ],
                            'payment_allocations_invoice_business_tenant_fk'
                        )
                            ->references([
                                'id',
                                'business_id',
                                'tenant_id',
                            ])
                            ->on(
                                'invoices'
                            );
                    }
                );

                Schema::table(
                    'payment_reversals',
                    function (
                        Blueprint $table
                    ): void {
                        $table->index(
                            [
                                'tenant_id',
                                'business_id',
                                'payment_id',
                                'reversed_at',
                            ],
                            'payment_reversals_business_payment_date_idx'
                        );

                        $table->foreign(
                            [
                                'business_id',
                                'tenant_id',
                            ],
                            'payment_reversals_business_tenant_foreign'
                        )
                            ->references([
                                'id',
                                'tenant_id',
                            ])
                            ->on(
                                'business_profiles'
                            );

                        $table->foreign(
                            [
                                'payment_id',
                                'business_id',
                                'tenant_id',
                            ],
                            'payment_reversals_payment_business_tenant_fk'
                        )
                            ->references([
                                'id',
                                'business_id',
                                'tenant_id',
                            ])
                            ->on(
                                'payments'
                            );
                    }
                );
            }
        );
    }

    public function down(): void
    {
        DB::transaction(
            function (): void {
                /*
                 * Tenant-only schema hanya mendukung satu default
                 * Cash Account. Tolak rollback jika keadaan baru
                 * sudah tidak dapat direpresentasikan.
                 */
                $multipleDefaults =
                    DB::table(
                        'cash_accounts'
                    )
                        ->select(
                            'tenant_id'
                        )
                        ->where(
                            'is_default',
                            true
                        )
                        ->groupBy(
                            'tenant_id'
                        )
                        ->havingRaw(
                            'COUNT(*) > 1'
                        )
                        ->exists();

                if ($multipleDefaults) {
                    throw new \RuntimeException(
                        'Cannot rollback business-scoped finance '
                        . 'while a Tenant has multiple default Cash Accounts.'
                    );
                }

                Schema::table(
                    'payment_reversals',
                    function (
                        Blueprint $table
                    ): void {
                        $table->dropForeign(
                            'payment_reversals_payment_business_tenant_fk'
                        );

                        $table->dropForeign(
                            'payment_reversals_business_tenant_foreign'
                        );

                        $table->dropIndex(
                            'payment_reversals_business_payment_date_idx'
                        );
                    }
                );

                Schema::table(
                    'payment_allocations',
                    function (
                        Blueprint $table
                    ): void {
                        $table->dropForeign(
                            'payment_allocations_invoice_business_tenant_fk'
                        );

                        $table->dropForeign(
                            'payment_allocations_payment_business_tenant_fk'
                        );

                        $table->dropForeign(
                            'payment_allocations_business_tenant_foreign'
                        );

                        $table->dropUnique(
                            'payment_allocations_business_payment_invoice_unique'
                        );

                        $table->dropIndex(
                            'payment_allocations_business_invoice_idx'
                        );
                    }
                );

                Schema::table(
                    'payments',
                    function (
                        Blueprint $table
                    ): void {
                        $table->dropForeign(
                            'payments_cash_account_business_tenant_foreign'
                        );

                        $table->dropForeign(
                            'payments_customer_business_tenant_foreign'
                        );

                        $table->dropForeign(
                            'payments_business_tenant_foreign'
                        );

                        $table->dropUnique(
                            'payments_id_business_tenant_unique'
                        );

                        $table->dropIndex(
                            'payments_tenant_business_status_paid_idx'
                        );

                        $table->dropIndex(
                            'payments_tenant_business_customer_paid_idx'
                        );

                        $table->dropIndex(
                            'payments_tenant_business_cash_account_idx'
                        );
                    }
                );

                Schema::table(
                    'expenses',
                    function (
                        Blueprint $table
                    ): void {
                        $table->dropForeign(
                            'expenses_account_business_tenant_foreign'
                        );

                        $table->dropForeign(
                            'expenses_business_tenant_foreign'
                        );

                        $table->dropIndex(
                            'expenses_tenant_business_status_date_idx'
                        );

                        $table->dropIndex(
                            'expenses_tenant_business_account_date_idx'
                        );
                    }
                );

                Schema::table(
                    'incomes',
                    function (
                        Blueprint $table
                    ): void {
                        $table->dropForeign(
                            'incomes_account_business_tenant_foreign'
                        );

                        $table->dropForeign(
                            'incomes_business_tenant_foreign'
                        );

                        $table->dropIndex(
                            'incomes_tenant_business_status_date_idx'
                        );

                        $table->dropIndex(
                            'incomes_tenant_business_account_date_idx'
                        );
                    }
                );

                Schema::table(
                    'cash_transactions',
                    function (
                        Blueprint $table
                    ): void {
                        $table->dropForeign(
                            'cash_transactions_reversal_business_tenant_fk'
                        );

                        $table->dropForeign(
                            'cash_transactions_account_business_tenant_fk'
                        );

                        $table->dropForeign(
                            'cash_transactions_business_tenant_foreign'
                        );

                        $table->dropUnique(
                            'cash_transactions_id_business_tenant_unique'
                        );

                        $table->dropIndex(
                            'cash_transactions_business_account_date_idx'
                        );

                        $table->dropIndex(
                            'cash_transactions_business_direction_date_idx'
                        );
                    }
                );

                Schema::table(
                    'cash_accounts',
                    function (
                        Blueprint $table
                    ): void {
                        $table->dropForeign(
                            'cash_accounts_business_tenant_foreign'
                        );

                        $table->dropUnique(
                            'cash_accounts_id_business_tenant_unique'
                        );

                        $table->dropIndex(
                            'cash_accounts_tenant_business_status_type_idx'
                        );
                    }
                );

                DB::statement(
                    'DROP INDEX IF EXISTS '
                    . 'cash_accounts_one_default_per_business_idx'
                );

                DB::statement(
                    <<<'SQL'
                    CREATE UNIQUE INDEX
                        cash_accounts_one_default_per_tenant_idx
                    ON cash_accounts (tenant_id)
                    WHERE is_default = TRUE
                    SQL
                );

                foreach (
                    [
                        'payment_reversals',
                        'payment_allocations',
                        'payments',
                        'expenses',
                        'incomes',
                        'cash_transactions',
                        'cash_accounts',
                    ] as $tableName
                ) {
                    Schema::table(
                        $tableName,
                        function (
                            Blueprint $table
                        ): void {
                            $table->dropColumn(
                                'business_id'
                            );
                        }
                    );
                }
            }
        );
    }
};
