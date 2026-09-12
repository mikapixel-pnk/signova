<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');

            $table->string('quotation_number', 80);
            $table->ulid('customer_id');

            // Added as FK after quotation_versions exists.
            $table->ulid('current_version_id')->nullable();

            $table->string('status', 32)
                ->default('DRAFT');

            $table->date('valid_until')->nullable();

            $table->ulid('owner_user_id')->nullable();

            $table->string('source', 32)
                ->default('MANUAL');

            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('viewed_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();

            $table->timestampsTz();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign(
                ['customer_id', 'tenant_id'],
                'quotations_customer_tenant_foreign'
            )
                ->references(['id', 'tenant_id'])
                ->on('customers')
                ->restrictOnDelete();

            $table->foreign(
                ['tenant_id', 'owner_user_id'],
                'quotations_owner_tenant_user_foreign'
            )
                ->references(['tenant_id', 'user_id'])
                ->on('tenant_users')
                ->restrictOnDelete();

            $table->unique(
                ['id', 'tenant_id'],
                'quotations_id_tenant_unique'
            );

            $table->unique(
                ['tenant_id', 'quotation_number'],
                'quotations_tenant_number_unique'
            );

            $table->index(
                ['tenant_id', 'status', 'created_at'],
                'quotations_tenant_status_created_index'
            );

            $table->index(
                ['tenant_id', 'customer_id', 'created_at'],
                'quotations_tenant_customer_created_index'
            );
        });

        Schema::create(
            'quotation_versions',
            function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('quotation_id');

                $table->unsignedInteger('revision_no');

                $table->decimal('subtotal', 18, 2)
                    ->default(0);

                $table->decimal('discount_total', 18, 2)
                    ->default(0);

                $table->decimal('tax_total', 18, 2)
                    ->default(0);

                $table->decimal('total', 18, 2)
                    ->default(0);

                $table->char('currency', 3)
                    ->default('IDR');

                $table->text('terms')->nullable();
                $table->text('notes')->nullable();

                $table->ulid('created_by_user_id')
                    ->nullable();

                $table->timestampsTz();

                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign(
                    ['quotation_id', 'tenant_id'],
                    'quotation_versions_quotation_tenant_foreign'
                )
                    ->references(['id', 'tenant_id'])
                    ->on('quotations')
                    ->cascadeOnDelete();

                $table->foreign(
                    ['tenant_id', 'created_by_user_id'],
                    'quotation_versions_creator_tenant_user_foreign'
                )
                    ->references(['tenant_id', 'user_id'])
                    ->on('tenant_users')
                    ->restrictOnDelete();

                $table->unique(
                    ['id', 'tenant_id'],
                    'quotation_versions_id_tenant_unique'
                );

                $table->unique(
                    ['quotation_id', 'revision_no'],
                    'quotation_versions_revision_unique'
                );

                $table->index(
                    ['tenant_id', 'quotation_id', 'revision_no'],
                    'quotation_versions_tenant_quotation_revision_index'
                );
            }
        );

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign(
                ['current_version_id', 'tenant_id'],
                'quotations_current_version_tenant_foreign'
            )
                ->references(['id', 'tenant_id'])
                ->on('quotation_versions')
                ->restrictOnDelete();
        });

        Schema::create(
            'quotation_items',
            function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('quotation_version_id');

                $table->ulid('catalog_item_id')->nullable();
                $table->ulid('unit_id')->nullable();

                $table->string('item_type', 32)
                    ->nullable();

                $table->string('code', 100)
                    ->nullable();

                $table->string('name', 190);

                $table->text('description')
                    ->nullable();

                $table->decimal('quantity', 18, 4)
                    ->default(1);

                // Snapshot satuan agar histori tidak berubah.
                $table->string('unit_code', 80)
                    ->nullable();

                $table->string('unit_name', 120)
                    ->nullable();

                $table->string('unit_symbol', 40)
                    ->nullable();

                // Snapshot metode perhitungan katalog.
                $table->string('pricing_method', 32)
                    ->default('STANDARD');

                $table->jsonb('pricing_config')
                    ->nullable();

                $table->decimal('unit_price', 18, 2)
                    ->default(0);

                $table->decimal('discount_amount', 18, 2)
                    ->default(0);

                $table->decimal('tax_amount', 18, 2)
                    ->default(0);

                $table->decimal('amount', 18, 2)
                    ->default(0);

                $table->unsignedInteger('sort_order')
                    ->default(0);

                $table->timestampsTz();

                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign(
                    ['quotation_version_id', 'tenant_id'],
                    'quotation_items_version_tenant_foreign'
                )
                    ->references(['id', 'tenant_id'])
                    ->on('quotation_versions')
                    ->cascadeOnDelete();

                $table->foreign(
                    ['catalog_item_id', 'tenant_id'],
                    'quotation_items_catalog_tenant_foreign'
                )
                    ->references(['id', 'tenant_id'])
                    ->on('catalog_items')
                    ->restrictOnDelete();

                $table->foreign(
                    ['unit_id', 'tenant_id'],
                    'quotation_items_unit_tenant_foreign'
                )
                    ->references(['id', 'tenant_id'])
                    ->on('units')
                    ->restrictOnDelete();

                $table->unique(
                    ['id', 'tenant_id'],
                    'quotation_items_id_tenant_unique'
                );

                $table->index(
                    [
                        'tenant_id',
                        'quotation_version_id',
                        'sort_order',
                    ],
                    'quotation_items_tenant_version_sort_index'
                );
            }
        );

        Schema::create(
            'quotation_status_history',
            function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('quotation_id');

                $table->string('from_state', 32)
                    ->nullable();

                $table->string('to_state', 32);

                $table->ulid('actor_user_id')
                    ->nullable();

                $table->text('reason')->nullable();

                $table->string('source', 32)
                    ->default('SYSTEM');

                $table->jsonb('context')->nullable();

                $table->timestampTz('occurred_at')
                    ->useCurrent();

                $table->timestampsTz();

                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign(
                    ['quotation_id', 'tenant_id'],
                    'quotation_history_quotation_tenant_foreign'
                )
                    ->references(['id', 'tenant_id'])
                    ->on('quotations')
                    ->cascadeOnDelete();

                $table->foreign(
                    ['tenant_id', 'actor_user_id'],
                    'quotation_history_actor_tenant_user_foreign'
                )
                    ->references(['tenant_id', 'user_id'])
                    ->on('tenant_users')
                    ->restrictOnDelete();

                $table->index(
                    [
                        'tenant_id',
                        'quotation_id',
                        'occurred_at',
                    ],
                    'quotation_history_tenant_quotation_time_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'quotation_status_history'
        );

        Schema::dropIfExists('quotation_items');

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(
                'quotations_current_version_tenant_foreign'
            );
        });

        Schema::dropIfExists('quotation_versions');
        Schema::dropIfExists('quotations');
    }
};
