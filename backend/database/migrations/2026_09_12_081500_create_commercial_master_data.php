<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');

            $table->string('type', 32)->default('COMPANY');
            $table->string('code', 80)->nullable();
            $table->string('name', 190);
            $table->string('phone', 64)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('tax_id', 100)->nullable();

            $table->unsignedSmallInteger('payment_terms_days')
                ->default(0);

            $table->text('notes')->nullable();

            $table->string('status', 32)
                ->default('ACTIVE');

            $table->timestampsTz();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unique(
                ['id', 'tenant_id'],
                'customers_id_tenant_unique'
            );

            $table->unique(
                ['tenant_id', 'code'],
                'customers_tenant_code_unique'
            );

            $table->index(
                ['tenant_id', 'status', 'name'],
                'customers_tenant_status_name_index'
            );
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('customer_id');

            $table->string('name', 190);
            $table->string('position', 120)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('email', 190)->nullable();

            $table->boolean('is_primary')
                ->default(false);

            $table->string('status', 32)
                ->default('ACTIVE');

            $table->timestampsTz();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign(
                ['customer_id', 'tenant_id'],
                'customer_contacts_customer_tenant_foreign'
            )
                ->references(['id', 'tenant_id'])
                ->on('customers')
                ->cascadeOnDelete();

            $table->index(
                ['tenant_id', 'customer_id', 'status'],
                'customer_contacts_tenant_customer_status_index'
            );
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('customer_id');

            $table->string('type', 32)
                ->default('BILLING');

            $table->string('label', 120)->nullable();

            $table->text('address_line_1');
            $table->text('address_line_2')->nullable();

            $table->string('city', 120)->nullable();
            $table->string('province', 120)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('country_code', 2)
                ->default('ID');

            $table->boolean('is_primary')
                ->default(false);

            $table->text('notes')->nullable();

            $table->string('status', 32)
                ->default('ACTIVE');

            $table->timestampsTz();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign(
                ['customer_id', 'tenant_id'],
                'customer_addresses_customer_tenant_foreign'
            )
                ->references(['id', 'tenant_id'])
                ->on('customers')
                ->cascadeOnDelete();

            $table->index(
                ['tenant_id', 'customer_id', 'status'],
                'customer_addresses_tenant_customer_status_index'
            );
        });

        Schema::create('catalog_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');

            $table->string('code', 80)->nullable();
            $table->string('name', 160);
            $table->string('description', 500)->nullable();

            $table->string('status', 32)
                ->default('ACTIVE');

            $table->timestampsTz();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unique(
                ['id', 'tenant_id'],
                'catalog_categories_id_tenant_unique'
            );

            $table->unique(
                ['tenant_id', 'code'],
                'catalog_categories_tenant_code_unique'
            );

            $table->index(
                ['tenant_id', 'status', 'name'],
                'catalog_categories_tenant_status_name_index'
            );
        });

        Schema::create('units', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');

            $table->string('code', 80);
            $table->string('name', 120);
            $table->string('symbol', 40)->nullable();

            $table->string('unit_type', 32)
                ->default('OTHER');

            $table->unsignedSmallInteger('decimal_precision')
                ->default(2);

            $table->string('status', 32)
                ->default('ACTIVE');

            $table->timestampsTz();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unique(
                ['id', 'tenant_id'],
                'units_id_tenant_unique'
            );

            $table->unique(
                ['tenant_id', 'code'],
                'units_tenant_code_unique'
            );

            $table->index(
                ['tenant_id', 'status', 'name'],
                'units_tenant_status_name_index'
            );
        });

        Schema::create('catalog_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');

            $table->ulid('category_id')->nullable();
            $table->ulid('unit_id')->nullable();

            $table->string('type', 32);
            $table->string('code', 100)->nullable();
            $table->string('name', 190);
            $table->text('description')->nullable();

            $table->string('pricing_method', 32)
                ->default('STANDARD');

            $table->decimal('base_price', 18, 2)
                ->default(0);

            $table->char('currency', 3)
                ->default('IDR');

            $table->jsonb('pricing_config')
                ->nullable();

            $table->string('status', 32)
                ->default('ACTIVE');

            $table->timestampsTz();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign(
                ['category_id', 'tenant_id'],
                'catalog_items_category_tenant_foreign'
            )
                ->references(['id', 'tenant_id'])
                ->on('catalog_categories')
                ->nullOnDelete();

            $table->foreign(
                ['unit_id', 'tenant_id'],
                'catalog_items_unit_tenant_foreign'
            )
                ->references(['id', 'tenant_id'])
                ->on('units')
                ->nullOnDelete();

            $table->unique(
                ['id', 'tenant_id'],
                'catalog_items_id_tenant_unique'
            );

            $table->unique(
                ['tenant_id', 'code'],
                'catalog_items_tenant_code_unique'
            );

            $table->index(
                ['tenant_id', 'status', 'type'],
                'catalog_items_tenant_status_type_index'
            );

            $table->index(
                ['tenant_id', 'name'],
                'catalog_items_tenant_name_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
        Schema::dropIfExists('units');
        Schema::dropIfExists('catalog_categories');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customers');
    }
};
