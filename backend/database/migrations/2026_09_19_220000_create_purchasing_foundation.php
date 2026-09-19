<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * =========================================================
         * Purchase Requests
         * =========================================================
         */

        Schema::create(
            'purchase_requests',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('business_id');

                $table->string(
                    'request_number',
                    80
                );

                $table->string(
                    'status',
                    32
                )->default('DRAFT');

                $table->date(
                    'needed_at'
                )->nullable();

                $table->char(
                    'currency',
                    3
                )->default('IDR');

                $table->decimal(
                    'estimated_total',
                    18,
                    2
                )->default(0);

                $table->text(
                    'notes'
                )->nullable();

                $table->ulid(
                    'requested_by_user_id'
                );

                $table->ulid(
                    'submitted_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'submitted_at'
                )->nullable();

                $table->ulid(
                    'approved_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'approved_at'
                )->nullable();

                $table->ulid(
                    'rejected_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'rejected_at'
                )->nullable();

                $table->text(
                    'rejection_reason'
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
                    'purchase_requests_id_business_tenant_unique'
                );

                $table->unique(
                    [
                        'tenant_id',
                        'business_id',
                        'request_number',
                    ],
                    'purchase_requests_business_number_unique'
                );

                $table->foreign(
                    'tenant_id'
                )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'business_id',
                        'tenant_id',
                    ],
                    'purchase_requests_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('business_profiles')
                    ->cascadeOnDelete();

                foreach (
                    [
                        'requested_by_user_id',
                        'submitted_by_user_id',
                        'approved_by_user_id',
                        'rejected_by_user_id',
                        'cancelled_by_user_id',
                    ] as $column
                ) {
                    $table->foreign(
                        [
                            'tenant_id',
                            $column,
                        ],
                        'purchase_requests_'
                        . str_replace(
                            '_user_id',
                            '',
                            $column
                        )
                        . '_tenant_user_foreign'
                    )
                        ->references([
                            'tenant_id',
                            'user_id',
                        ])
                        ->on('tenant_users')
                        ->restrictOnDelete();
                }

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'status',
                        'created_at',
                    ],
                    'purchase_requests_context_status_date_idx'
                );
            }
        );

        DB::statement(
            "ALTER TABLE purchase_requests
             ADD CONSTRAINT purchase_requests_status_check
             CHECK (
                status IN (
                    'DRAFT',
                    'SUBMITTED',
                    'APPROVED',
                    'REJECTED',
                    'CANCELLED'
                )
             )"
        );

        DB::statement(
            "ALTER TABLE purchase_requests
             ADD CONSTRAINT purchase_requests_estimated_total_check
             CHECK (estimated_total >= 0)"
        );

        Schema::create(
            'purchase_request_items',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('business_id');
                $table->ulid('purchase_request_id');

                $table->ulid(
                    'catalog_item_id'
                )->nullable();

                $table->ulid(
                    'unit_id'
                )->nullable();

                $table->string(
                    'item_type',
                    32
                )->nullable();

                $table->string(
                    'code',
                    100
                )->nullable();

                $table->string(
                    'name',
                    190
                );

                $table->text(
                    'description'
                )->nullable();

                $table->decimal(
                    'quantity',
                    18,
                    4
                )->default(1);

                /*
                 * Snapshot satuan.
                 */
                $table->string(
                    'unit_code',
                    80
                )->nullable();

                $table->string(
                    'unit_name',
                    120
                )->nullable();

                $table->string(
                    'unit_symbol',
                    40
                )->nullable();

                /*
                 * Harga pada PR hanya estimasi pembelian.
                 * Bukan harga jual katalog.
                 */
                $table->decimal(
                    'estimated_unit_price',
                    18,
                    2
                )->default(0);

                $table->decimal(
                    'amount',
                    18,
                    2
                )->default(0);

                $table->unsignedInteger(
                    'sort_order'
                )->default(0);

                $table->timestampsTz();

                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'purchase_request_items_id_business_tenant_unique'
                );

                $table->foreign(
                    [
                        'purchase_request_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'purchase_request_items_request_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('purchase_requests')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'catalog_item_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'purchase_request_items_catalog_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('catalog_items')
                    ->restrictOnDelete();

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'purchase_request_id',
                        'sort_order',
                    ],
                    'purchase_request_items_context_sort_idx'
                );
            }
        );

        DB::statement(
            "ALTER TABLE purchase_request_items
             ADD CONSTRAINT purchase_request_items_quantity_check
             CHECK (quantity > 0)"
        );

        DB::statement(
            "ALTER TABLE purchase_request_items
             ADD CONSTRAINT purchase_request_items_amount_check
             CHECK (
                estimated_unit_price >= 0
                AND amount >= 0
             )"
        );

        Schema::create(
            'purchase_request_status_history',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('business_id');
                $table->ulid('purchase_request_id');

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

                $table->foreign(
                    [
                        'purchase_request_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'purchase_request_history_request_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('purchase_requests')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'tenant_id',
                        'actor_user_id',
                    ],
                    'purchase_request_history_actor_foreign'
                )
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users')
                    ->restrictOnDelete();

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'purchase_request_id',
                        'created_at',
                    ],
                    'purchase_request_history_context_date_idx'
                );
            }
        );

        /*
         * =========================================================
         * Purchase Orders
         * =========================================================
         */

        Schema::create(
            'purchase_orders',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('business_id');

                $table->string(
                    'order_number',
                    80
                );

                $table->ulid(
                    'supplier_id'
                );

                $table->ulid(
                    'source_purchase_request_id'
                )->nullable();

                $table->string(
                    'status',
                    32
                )->default('DRAFT');

                $table->char(
                    'currency',
                    3
                )->default('IDR');

                $table->date(
                    'expected_at'
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
                    'issued_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'issued_at'
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
                    'purchase_orders_id_business_tenant_unique'
                );

                $table->unique(
                    [
                        'tenant_id',
                        'business_id',
                        'order_number',
                    ],
                    'purchase_orders_business_number_unique'
                );

                $table->foreign(
                    'tenant_id'
                )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'business_id',
                        'tenant_id',
                    ],
                    'purchase_orders_business_tenant_foreign'
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
                    'purchase_orders_supplier_foreign'
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
                        'source_purchase_request_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'purchase_orders_source_request_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('purchase_requests')
                    ->restrictOnDelete();

                foreach (
                    [
                        'created_by_user_id',
                        'issued_by_user_id',
                        'cancelled_by_user_id',
                    ] as $column
                ) {
                    $table->foreign(
                        [
                            'tenant_id',
                            $column,
                        ],
                        'purchase_orders_'
                        . str_replace(
                            '_user_id',
                            '',
                            $column
                        )
                        . '_tenant_user_foreign'
                    )
                        ->references([
                            'tenant_id',
                            'user_id',
                        ])
                        ->on('tenant_users')
                        ->restrictOnDelete();
                }

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'supplier_id',
                        'status',
                    ],
                    'purchase_orders_supplier_status_idx'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'status',
                        'created_at',
                    ],
                    'purchase_orders_context_status_date_idx'
                );
            }
        );

        DB::statement(
            "ALTER TABLE purchase_orders
             ADD CONSTRAINT purchase_orders_status_check
             CHECK (
                status IN (
                    'DRAFT',
                    'ISSUED',
                    'PARTIALLY_RECEIVED',
                    'RECEIVED',
                    'CANCELLED'
                )
             )"
        );

        DB::statement(
            "ALTER TABLE purchase_orders
             ADD CONSTRAINT purchase_orders_totals_check
             CHECK (
                subtotal >= 0
                AND discount_total >= 0
                AND tax_total >= 0
                AND total >= 0
             )"
        );

        Schema::create(
            'purchase_order_items',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('business_id');
                $table->ulid('purchase_order_id');

                $table->ulid(
                    'source_purchase_request_item_id'
                )->nullable();

                $table->ulid(
                    'catalog_item_id'
                )->nullable();

                $table->ulid(
                    'unit_id'
                )->nullable();

                $table->string(
                    'item_type',
                    32
                )->nullable();

                $table->string(
                    'code',
                    100
                )->nullable();

                $table->string(
                    'name',
                    190
                );

                $table->text(
                    'description'
                )->nullable();

                $table->decimal(
                    'quantity',
                    18,
                    4
                )->default(1);

                $table->string(
                    'unit_code',
                    80
                )->nullable();

                $table->string(
                    'unit_name',
                    120
                )->nullable();

                $table->string(
                    'unit_symbol',
                    40
                )->nullable();

                $table->decimal(
                    'unit_price',
                    18,
                    2
                )->default(0);

                $table->decimal(
                    'discount_amount',
                    18,
                    2
                )->default(0);

                $table->decimal(
                    'tax_amount',
                    18,
                    2
                )->default(0);

                $table->decimal(
                    'amount',
                    18,
                    2
                )->default(0);

                $table->unsignedInteger(
                    'sort_order'
                )->default(0);

                $table->timestampsTz();

                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'purchase_order_items_id_business_tenant_unique'
                );

                $table->foreign(
                    [
                        'purchase_order_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'purchase_order_items_order_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('purchase_orders')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'source_purchase_request_item_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'purchase_order_items_source_request_item_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('purchase_request_items')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'catalog_item_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'purchase_order_items_catalog_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('catalog_items')
                    ->restrictOnDelete();

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'purchase_order_id',
                        'sort_order',
                    ],
                    'purchase_order_items_context_sort_idx'
                );
            }
        );

        DB::statement(
            "ALTER TABLE purchase_order_items
             ADD CONSTRAINT purchase_order_items_quantity_check
             CHECK (quantity > 0)"
        );

        DB::statement(
            "ALTER TABLE purchase_order_items
             ADD CONSTRAINT purchase_order_items_amounts_check
             CHECK (
                unit_price >= 0
                AND discount_amount >= 0
                AND tax_amount >= 0
                AND amount >= 0
             )"
        );

        Schema::create(
            'purchase_order_status_history',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('business_id');
                $table->ulid('purchase_order_id');

                $table->string(
                    'from_status',
                    32
                )->nullable();

                $table->string(
                    'to_status',
                    32
                );

                /*
                 * Sengaja tidak diberi CHECK action.
                 * F6-A2 receiving akan menambah action tanpa
                 * mengubah migration ini.
                 */
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

                $table->foreign(
                    [
                        'purchase_order_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'purchase_order_history_order_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('purchase_orders')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'tenant_id',
                        'actor_user_id',
                    ],
                    'purchase_order_history_actor_foreign'
                )
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users')
                    ->restrictOnDelete();

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'purchase_order_id',
                        'created_at',
                    ],
                    'purchase_order_history_context_date_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'purchase_order_status_history'
        );

        Schema::dropIfExists(
            'purchase_order_items'
        );

        Schema::dropIfExists(
            'purchase_orders'
        );

        Schema::dropIfExists(
            'purchase_request_status_history'
        );

        Schema::dropIfExists(
            'purchase_request_items'
        );

        Schema::dropIfExists(
            'purchase_requests'
        );
    }
};
