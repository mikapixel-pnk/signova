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
            'payments',
            function (Blueprint $table): void {
                $table
                    ->ulid('intended_invoice_id')
                    ->nullable()
                    ->after('customer_id');

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'intended_invoice_id',
                        'status',
                    ],
                    'payments_intended_invoice_status_idx'
                );
            }
        );

        /*
         * Membuktikan bahwa invoice tujuan berada pada
         * Tenant + Business yang sama dengan payment.
         *
         * Nullable dibutuhkan untuk mempertahankan payment
         * lama dan payment manual yang belum terkait invoice.
         */
        DB::statement(
            <<<'SQL'
ALTER TABLE payments
ADD CONSTRAINT payments_intended_invoice_scope_fk
FOREIGN KEY (
    intended_invoice_id,
    business_id,
    tenant_id
)
REFERENCES invoices (
    id,
    business_id,
    tenant_id
)
ON DELETE RESTRICT
SQL
        );
    }

    public function down(): void
    {
        DB::statement(
            <<<'SQL'
ALTER TABLE payments
DROP CONSTRAINT IF EXISTS payments_intended_invoice_scope_fk
SQL
        );

        Schema::table(
            'payments',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'payments_intended_invoice_status_idx'
                );

                $table->dropColumn(
                    'intended_invoice_id'
                );
            }
        );
    }
};
