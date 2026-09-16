<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $missingBusinessCount =
                DB::table('catalog_items')
                    ->whereNull('business_id')
                    ->count();

            if ($missingBusinessCount > 0) {
                throw new \RuntimeException(
                    'Cannot contract catalog_items.business_id: '
                    . $missingBusinessCount
                    . ' rows still have NULL business_id.'
                );
            }

            Schema::table(
                'catalog_items',
                function (Blueprint $table): void {
                    $table->ulid('business_id')
                        ->nullable(false)
                        ->change();

                    $table->dropUnique(
                        'catalog_items_tenant_code_unique'
                    );

                    $table->dropIndex(
                        'catalog_items_tenant_status_type_index'
                    );

                    $table->dropIndex(
                        'catalog_items_tenant_name_index'
                    );
                }
            );
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            Schema::table(
                'catalog_items',
                function (Blueprint $table): void {
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

                    $table->ulid('business_id')
                        ->nullable()
                        ->change();
                }
            );
        });
    }
};
