<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'invoices',
            function (Blueprint $table): void {
                $table->decimal(
                    'paid_amount',
                    18,
                    2
                )
                    ->default(0);

                $table->decimal(
                    'outstanding_amount',
                    18,
                    2
                )
                    ->default(0);

                $table->index(
                    [
                        'tenant_id',
                        'status',
                        'outstanding_amount',
                    ],
                    'invoices_receivable_lookup_idx'
                );
            }
        );

        /*
         * Existing invoice belum memiliki payment engine aktif.
         *
         * VOID tidak mempunyai outstanding receivable.
         * Invoice lainnya dimulai dari total penuh.
         */
        DB::statement(
            <<<'SQL'
            UPDATE invoices
            SET
                paid_amount =
                    CASE
                        WHEN status = 'PAID' THEN total
                        ELSE 0
                    END,
                outstanding_amount =
                    CASE
                        WHEN status IN (
                            'DRAFT',
                            'VOID',
                            'PAID'
                        ) THEN 0
                        ELSE total
                    END
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            ADD CONSTRAINT invoices_paid_amount_nonnegative_check
            CHECK (paid_amount >= 0)
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            ADD CONSTRAINT invoices_outstanding_amount_nonnegative_check
            CHECK (outstanding_amount >= 0)
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            ADD CONSTRAINT invoices_paid_amount_not_above_total_check
            CHECK (paid_amount <= total)
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            ADD CONSTRAINT invoices_outstanding_not_above_total_check
            CHECK (outstanding_amount <= total)
            SQL
        );
    }

    public function down(): void
    {
        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            DROP CONSTRAINT IF EXISTS
                invoices_outstanding_not_above_total_check
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            DROP CONSTRAINT IF EXISTS
                invoices_paid_amount_not_above_total_check
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            DROP CONSTRAINT IF EXISTS
                invoices_outstanding_amount_nonnegative_check
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            DROP CONSTRAINT IF EXISTS
                invoices_paid_amount_nonnegative_check
            SQL
        );

        Schema::table(
            'invoices',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'invoices_receivable_lookup_idx'
                );

                $table->dropColumn([
                    'paid_amount',
                    'outstanding_amount',
                ]);
            }
        );
    }
};
