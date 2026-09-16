<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE customers '
            . 'ALTER COLUMN business_id SET NOT NULL'
        );

        DB::statement(
            'ALTER TABLE customer_contacts '
            . 'ALTER COLUMN business_id SET NOT NULL'
        );

        DB::statement(
            'ALTER TABLE customer_addresses '
            . 'ALTER COLUMN business_id SET NOT NULL'
        );

        DB::statement(
            'ALTER TABLE customers '
            . 'DROP CONSTRAINT customers_tenant_code_unique'
        );

        DB::statement(
            'DROP INDEX '
            . 'customers_tenant_status_name_index'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE customers '
            . 'ADD CONSTRAINT customers_tenant_code_unique '
            . 'UNIQUE (tenant_id, code)'
        );

        DB::statement(
            'CREATE INDEX '
            . 'customers_tenant_status_name_index '
            . 'ON customers (tenant_id, status, name)'
        );

        DB::statement(
            'ALTER TABLE customer_addresses '
            . 'ALTER COLUMN business_id DROP NOT NULL'
        );

        DB::statement(
            'ALTER TABLE customer_contacts '
            . 'ALTER COLUMN business_id DROP NOT NULL'
        );

        DB::statement(
            'ALTER TABLE customers '
            . 'ALTER COLUMN business_id DROP NOT NULL'
        );
    }
};
