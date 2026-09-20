<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE cash_transactions
            DROP CONSTRAINT cash_transactions_source_type_check
        ");

        DB::statement("
            ALTER TABLE cash_transactions
            ADD CONSTRAINT cash_transactions_source_type_check
            CHECK (
                source_type IN (
                    'PAYMENT',
                    'PAYMENT_REVERSAL',
                    'MANUAL_INCOME',
                    'MANUAL_INCOME_VOID',
                    'EXPENSE',
                    'EXPENSE_VOID',
                    'SUPPLIER_PAYMENT',
                    'SUPPLIER_PAYMENT_REVERSAL'
                )
            )
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE cash_transactions
            DROP CONSTRAINT cash_transactions_source_type_check
        ");

        DB::statement("
            ALTER TABLE cash_transactions
            ADD CONSTRAINT cash_transactions_source_type_check
            CHECK (
                source_type IN (
                    'PAYMENT',
                    'PAYMENT_REVERSAL',
                    'MANUAL_INCOME',
                    'MANUAL_INCOME_VOID',
                    'EXPENSE',
                    'EXPENSE_VOID'
                )
            )
        ");
    }
};
