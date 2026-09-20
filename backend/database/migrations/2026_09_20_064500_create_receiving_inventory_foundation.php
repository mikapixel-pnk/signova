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
         * Materials
         * =========================================================
         */

        Schema::create(
            'materials',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('business_id');

                $table->string(
                    'code',
                    100
                );

                $table->string(
                    'name',
                    190
                );

                $table->ulid(
                    'unit_id'
                )->nullable();

                $table->string(
                    'category',
                    120
                )->nullable();

                $table->string(
                    'status',
                    32
                )->default('ACTIVE');

                $table->timestampsTz();

                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'materials_id_business_tenant_unique'
                );

                $table->unique(
                    [
                        'tenant_id',
                        'business_id',
                        'code',
                    ],
                    'materials_business_code_unique'
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
                    'materials_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('business_profiles')
                    ->cascadeOnDelete();

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'status',
                        'name',
                    ],
                    'materials_context_status_name_idx'
                );
            }
        );

        DB::statement(
            "ALTER TABLE materials
             ADD CONSTRAINT materials_status_check
             CHECK (
                status IN (
                    'ACTIVE',
                    'INACTIVE'
                )
             )"
        );


        /*
         * =========================================================
         * Warehouses
         * =========================================================
         */

        Schema::create(
            'warehouses',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('business_id');

                $table->string(
                    'name',
                    190
                );

                $table->text(
                    'location'
                )->nullable();

                $table->string(
                    'status',
                    32
                )->default('ACTIVE');

                $table->timestampsTz();

                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'warehouses_id_business_tenant_unique'
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
                    'warehouses_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('business_profiles')
                    ->cascadeOnDelete();

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'status',
                        'name',
                    ],
                    'warehouses_context_status_name_idx'
                );
            }
        );

        DB::statement(
            "ALTER TABLE warehouses
             ADD CONSTRAINT warehouses_status_check
             CHECK (
                status IN (
                    'ACTIVE',
                    'INACTIVE'
                )
             )"
        );


        /*
         * =========================================================
         * Goods Receipts
         * =========================================================
         */

        Schema::create(
            'goods_receipts',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('business_id');

                $table->ulid(
                    'purchase_order_id'
                );

                $table->string(
                    'receipt_number',
                    80
                );

                $table->string(
                    'status',
                    32
                )->default('DRAFT');

                $table->ulid(
                    'warehouse_id'
                );

                $table->timestampTz(
                    'received_at'
                )->nullable();

                $table->text(
                    'notes'
                )->nullable();

                $table->ulid(
                    'created_by_user_id'
                );

                $table->ulid(
                    'posted_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'posted_at'
                )->nullable();

                $table->ulid(
                    'reversed_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'reversed_at'
                )->nullable();

                $table->text(
                    'reversal_reason'
                )->nullable();

                $table->timestampsTz();

                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'goods_receipts_id_business_tenant_unique'
                );

                $table->unique(
                    [
                        'tenant_id',
                        'business_id',
                        'receipt_number',
                    ],
                    'goods_receipts_business_number_unique'
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
                    'goods_receipts_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('business_profiles')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'purchase_order_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'goods_receipts_order_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('purchase_orders')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'warehouse_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'goods_receipts_warehouse_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('warehouses')
                    ->restrictOnDelete();

                foreach (
                    [
                        'created_by_user_id',
                        'posted_by_user_id',
                        'reversed_by_user_id',
                    ] as $column
                ) {
                    $table->foreign(
                        [
                            'tenant_id',
                            $column,
                        ],
                        'goods_receipts_'
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
                        'purchase_order_id',
                        'status',
                    ],
                    'goods_receipts_order_status_idx'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'warehouse_id',
                        'received_at',
                    ],
                    'goods_receipts_warehouse_date_idx'
                );
            }
        );

        DB::statement(
            "ALTER TABLE goods_receipts
             ADD CONSTRAINT goods_receipts_status_check
             CHECK (
                status IN (
                    'DRAFT',
                    'POSTED',
                    'REVERSED'
                )
             )"
        );


        /*
         * =========================================================
         * Goods Receipt Items
         * =========================================================
         */

        Schema::create(
            'goods_receipt_items',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('business_id');

                $table->ulid(
                    'goods_receipt_id'
                );

                $table->ulid(
                    'purchase_order_item_id'
                );

                /*
                 * Nullable agar service/non-stock item dapat
                 * diterima tanpa membuat stock movement.
                 * Product stock-managed wajib material saat POST.
                 */
                $table->ulid(
                    'material_id'
                )->nullable();

                $table->decimal(
                    'quantity_received',
                    18,
                    4
                );

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
                    'goods_receipt_items_id_business_tenant_unique'
                );

                $table->unique(
                    [
                        'goods_receipt_id',
                        'purchase_order_item_id',
                    ],
                    'goods_receipt_items_receipt_order_item_unique'
                );

                $table->foreign(
                    [
                        'goods_receipt_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'goods_receipt_items_receipt_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('goods_receipts')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'purchase_order_item_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'goods_receipt_items_order_item_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('purchase_order_items')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'material_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'goods_receipt_items_material_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('materials')
                    ->restrictOnDelete();

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'goods_receipt_id',
                        'sort_order',
                    ],
                    'goods_receipt_items_context_sort_idx'
                );
            }
        );

        DB::statement(
            "ALTER TABLE goods_receipt_items
             ADD CONSTRAINT goods_receipt_items_quantity_check
             CHECK (quantity_received > 0)"
        );


        /*
         * =========================================================
         * Goods Receipt Status History
         * =========================================================
         */

        Schema::create(
            'goods_receipt_status_history',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('business_id');
                $table->ulid('goods_receipt_id');

                $table->string(
                    'from_status',
                    32
                )->nullable();

                $table->string(
                    'to_status',
                    32
                );

                /*
                 * Tidak diberi CHECK action agar workflow
                 * dapat berkembang tanpa mengubah migration.
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
                        'goods_receipt_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'goods_receipt_history_receipt_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('goods_receipts')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'tenant_id',
                        'actor_user_id',
                    ],
                    'goods_receipt_history_actor_foreign'
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
                        'goods_receipt_id',
                        'created_at',
                    ],
                    'goods_receipt_history_context_date_idx'
                );
            }
        );


        /*
         * =========================================================
         * Stock Movement Ledger
         * =========================================================
         */

        Schema::create(
            'stock_movements',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('business_id');

                $table->ulid(
                    'material_id'
                );

                $table->ulid(
                    'warehouse_id'
                );

                /*
                 * Contoh awal:
                 * RECEIPT
                 * RECEIPT_REVERSAL
                 *
                 * Future:
                 * ISSUE / TRANSFER / ADJUSTMENT.
                 *
                 * Sengaja tidak CHECK type agar extension tidak
                 * membutuhkan perubahan migration lama.
                 */
                $table->string(
                    'type',
                    64
                );

                $table->decimal(
                    'quantity_signed',
                    18,
                    4
                );

                /*
                 * Untuk receipt, source_id menunjuk
                 * goods_receipt_item.id.
                 */
                $table->string(
                    'source_type',
                    64
                );

                $table->ulid(
                    'source_id'
                );

                $table->timestampTz(
                    'occurred_at'
                );

                $table->ulid(
                    'actor_user_id'
                );

                $table->text(
                    'reason'
                )->nullable();

                $table->ulid(
                    'reversal_of_movement_id'
                )->nullable();

                $table->timestampTz(
                    'created_at'
                )->useCurrent();

                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'stock_movements_id_business_tenant_unique'
                );

                /*
                 * Idempotency per domain source/action.
                 */
                $table->unique(
                    [
                        'tenant_id',
                        'business_id',
                        'source_type',
                        'source_id',
                        'type',
                    ],
                    'stock_movements_source_type_unique'
                );

                $table->foreign(
                    [
                        'material_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'stock_movements_material_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('materials')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'warehouse_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'stock_movements_warehouse_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('warehouses')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'tenant_id',
                        'actor_user_id',
                    ],
                    'stock_movements_actor_foreign'
                )
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'reversal_of_movement_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'stock_movements_reversal_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('stock_movements')
                    ->restrictOnDelete();

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'material_id',
                        'warehouse_id',
                        'occurred_at',
                    ],
                    'stock_movements_balance_ledger_idx'
                );
            }
        );

        DB::statement(
            "ALTER TABLE stock_movements
             ADD CONSTRAINT stock_movements_quantity_check
             CHECK (quantity_signed <> 0)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'stock_movements'
        );

        Schema::dropIfExists(
            'goods_receipt_status_history'
        );

        Schema::dropIfExists(
            'goods_receipt_items'
        );

        Schema::dropIfExists(
            'goods_receipts'
        );

        Schema::dropIfExists(
            'warehouses'
        );

        Schema::dropIfExists(
            'materials'
        );
    }
};
