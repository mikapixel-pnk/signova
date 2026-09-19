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
            'suppliers',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('business_id');

                $table->string('code', 80)
                    ->nullable();

                $table->string('name', 190);

                $table->string(
                    'contact_name',
                    190
                )->nullable();

                $table->string('phone', 64)
                    ->nullable();

                $table->string('email', 190)
                    ->nullable();

                $table->string('tax_id', 100)
                    ->nullable();

                $table->text('address')
                    ->nullable();

                $table->string('city', 120)
                    ->nullable();

                $table->string('province', 120)
                    ->nullable();

                $table
                    ->unsignedSmallInteger(
                        'payment_terms_days'
                    )
                    ->default(0);

                $table->text('notes')
                    ->nullable();

                $table->string('status', 32)
                    ->default('ACTIVE');

                $table->timestampsTz();

                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'business_id',
                        'tenant_id',
                    ],
                    'suppliers_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('business_profiles');

                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'suppliers_id_business_tenant_unique'
                );

                $table->unique(
                    [
                        'tenant_id',
                        'business_id',
                        'code',
                    ],
                    'suppliers_tenant_business_code_unique'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'status',
                        'name',
                    ],
                    'suppliers_tenant_business_status_name_index'
                );
            }
        );

        DB::statement(
            "ALTER TABLE suppliers
             ADD CONSTRAINT suppliers_status_check
             CHECK (status IN ('ACTIVE', 'INACTIVE'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
