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
            'purchase_request_items',
            function (Blueprint $table): void {
                $table->ulid(
                    'material_id'
                )->nullable();

                $table->string(
                    'procurement_type',
                    32
                )->default(
                    'NON_STOCK_GOOD'
                );

                $table->foreign(
                    [
                        'material_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'purchase_request_items_material_foreign'
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
                        'material_id',
                    ],
                    'purchase_request_items_context_material_idx'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'procurement_type',
                    ],
                    'purchase_request_items_context_procurement_idx'
                );
            }
        );

        Schema::table(
            'purchase_order_items',
            function (Blueprint $table): void {
                $table->ulid(
                    'material_id'
                )->nullable();

                $table->string(
                    'procurement_type',
                    32
                )->default(
                    'NON_STOCK_GOOD'
                );

                $table->foreign(
                    [
                        'material_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'purchase_order_items_material_foreign'
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
                        'material_id',
                    ],
                    'purchase_order_items_context_material_idx'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'procurement_type',
                    ],
                    'purchase_order_items_context_procurement_idx'
                );
            }
        );

        DB::statement(
            "UPDATE purchase_request_items
             SET procurement_type =
                CASE
                    WHEN UPPER(COALESCE(item_type, '')) = 'SERVICE'
                        THEN 'SERVICE'
                    ELSE 'NON_STOCK_GOOD'
                END"
        );

        DB::statement(
            "UPDATE purchase_order_items
             SET procurement_type =
                CASE
                    WHEN UPPER(COALESCE(item_type, '')) = 'SERVICE'
                        THEN 'SERVICE'
                    ELSE 'NON_STOCK_GOOD'
                END"
        );

        DB::statement(
            "ALTER TABLE purchase_request_items
             ADD CONSTRAINT purchase_request_items_procurement_type_check
             CHECK (
                procurement_type IN (
                    'INVENTORY_ITEM',
                    'NON_STOCK_GOOD',
                    'SERVICE'
                )
             )"
        );

        DB::statement(
            "ALTER TABLE purchase_order_items
             ADD CONSTRAINT purchase_order_items_procurement_type_check
             CHECK (
                procurement_type IN (
                    'INVENTORY_ITEM',
                    'NON_STOCK_GOOD',
                    'SERVICE'
                )
             )"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE purchase_order_items
             DROP CONSTRAINT IF EXISTS
             purchase_order_items_procurement_type_check"
        );

        DB::statement(
            "ALTER TABLE purchase_request_items
             DROP CONSTRAINT IF EXISTS
             purchase_request_items_procurement_type_check"
        );

        Schema::table(
            'purchase_order_items',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'purchase_order_items_material_foreign'
                );

                $table->dropIndex(
                    'purchase_order_items_context_material_idx'
                );

                $table->dropIndex(
                    'purchase_order_items_context_procurement_idx'
                );

                $table->dropColumn([
                    'material_id',
                    'procurement_type',
                ]);
            }
        );

        Schema::table(
            'purchase_request_items',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'purchase_request_items_material_foreign'
                );

                $table->dropIndex(
                    'purchase_request_items_context_material_idx'
                );

                $table->dropIndex(
                    'purchase_request_items_context_procurement_idx'
                );

                $table->dropColumn([
                    'material_id',
                    'procurement_type',
                ]);
            }
        );
    }
};
