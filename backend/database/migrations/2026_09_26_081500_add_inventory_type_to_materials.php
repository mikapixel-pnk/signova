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
            'materials',
            function (Blueprint $table): void {
                $table->string(
                    'inventory_type',
                    32
                )->default(
                    'RAW_MATERIAL'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'inventory_type',
                        'status',
                    ],
                    'materials_context_inventory_type_idx'
                );
            }
        );

        DB::statement(
            "ALTER TABLE materials
             ADD CONSTRAINT materials_inventory_type_check
             CHECK (
                inventory_type IN (
                    'RAW_MATERIAL',
                    'COMPONENT',
                    'CONSUMABLE',
                    'RESALE',
                    'FINISHED_GOOD'
                )
             )"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE materials
             DROP CONSTRAINT IF EXISTS
             materials_inventory_type_check"
        );

        Schema::table(
            'materials',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'materials_context_inventory_type_idx'
                );

                $table->dropColumn(
                    'inventory_type'
                );
            }
        );
    }
};
