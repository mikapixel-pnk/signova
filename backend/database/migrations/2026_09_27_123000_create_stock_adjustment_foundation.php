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
            'stock_adjustments',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->ulid('tenant_id');
                $table->ulid('business_id');

                $table->string(
                    'adjustment_number',
                    64
                );

                $table->ulid(
                    'warehouse_id'
                );

                $table->string(
                    'status',
                    32
                )->default('DRAFT');

                $table->text(
                    'reason'
                );

                $table->text(
                    'notes'
                )->nullable();

                $table->timestampTz(
                    'adjusted_at'
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
                        'tenant_id',
                        'business_id',
                        'adjustment_number',
                    ],
                    'stock_adjustments_number_unique'
                );

                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'stock_adjustments_id_business_tenant_unique'
                );

                $table->foreign(
                    [
                        'warehouse_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'stock_adjustments_warehouse_foreign'
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
                        'created_by_user_id'
                            => 'stock_adjustments_creator_foreign',
                        'posted_by_user_id'
                            => 'stock_adjustments_poster_foreign',
                        'reversed_by_user_id'
                            => 'stock_adjustments_reverser_foreign',
                    ]
                    as $column => $constraint
                ) {
                    $table->foreign(
                        [
                            'tenant_id',
                            $column,
                        ],
                        $constraint
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
                    'stock_adjustments_context_status_idx'
                );
            }
        );

        DB::statement(
            "ALTER TABLE stock_adjustments
             ADD CONSTRAINT stock_adjustments_status_check
             CHECK (
                status IN (
                    'DRAFT',
                    'POSTED',
                    'REVERSED'
                )
             )"
        );

        Schema::create(
            'stock_adjustment_items',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->ulid('tenant_id');
                $table->ulid('business_id');

                $table->ulid(
                    'stock_adjustment_id'
                );

                $table->ulid(
                    'material_id'
                );

                /*
                 * Signed delta:
                 * positive = stock increase
                 * negative = stock decrease.
                 *
                 * Authoritative balance tetap berasal dari
                 * stock_movements setelah document POSTED.
                 */
                $table->decimal(
                    'quantity_delta',
                    18,
                    4
                );

                $table->text(
                    'notes'
                )->nullable();

                $table->timestampsTz();

                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'stock_adjustment_items_id_business_tenant_unique'
                );

                $table->unique(
                    [
                        'tenant_id',
                        'business_id',
                        'stock_adjustment_id',
                        'material_id',
                    ],
                    'stock_adjustment_items_material_unique'
                );

                $table->foreign(
                    [
                        'stock_adjustment_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'stock_adjustment_items_document_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('stock_adjustments')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'material_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'stock_adjustment_items_material_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('materials')
                    ->restrictOnDelete();
            }
        );

        DB::statement(
            "ALTER TABLE stock_adjustment_items
             ADD CONSTRAINT stock_adjustment_items_quantity_check
             CHECK (quantity_delta <> 0)"
        );

        Schema::create(
            'stock_adjustment_status_history',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->ulid('tenant_id');
                $table->ulid('business_id');

                $table->ulid(
                    'stock_adjustment_id'
                );

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
                    32
                );

                $table->ulid(
                    'actor_user_id'
                );

                $table->text(
                    'reason'
                )->nullable();

                $table->timestampTz(
                    'created_at'
                )->useCurrent();

                $table->foreign(
                    [
                        'stock_adjustment_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'stock_adjustment_history_document_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('stock_adjustments')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'tenant_id',
                        'actor_user_id',
                    ],
                    'stock_adjustment_history_actor_foreign'
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
                        'stock_adjustment_id',
                        'created_at',
                    ],
                    'stock_adjustment_history_document_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'stock_adjustment_status_history'
        );

        Schema::dropIfExists(
            'stock_adjustment_items'
        );

        Schema::dropIfExists(
            'stock_adjustments'
        );
    }
};
