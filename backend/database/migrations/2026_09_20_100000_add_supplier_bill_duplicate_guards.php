<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE UNIQUE INDEX
                supplier_bills_active_receipt_unique
            ON supplier_bills (
                tenant_id,
                business_id,
                goods_receipt_id
            )
            WHERE goods_receipt_id IS NOT NULL
              AND status <> 'CANCELLED'
        ");

        DB::statement("
            CREATE UNIQUE INDEX
                supplier_bills_active_supplier_invoice_unique
            ON supplier_bills (
                tenant_id,
                business_id,
                supplier_id,
                supplier_invoice_number
            )
            WHERE supplier_invoice_number IS NOT NULL
              AND status <> 'CANCELLED'
        ");
    }

    public function down(): void
    {
        DB::statement("
            DROP INDEX IF EXISTS
                supplier_bills_active_supplier_invoice_unique
        ");

        DB::statement("
            DROP INDEX IF EXISTS
                supplier_bills_active_receipt_unique
        ");
    }
};
