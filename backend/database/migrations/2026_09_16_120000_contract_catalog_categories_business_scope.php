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
                DB::table('catalog_categories')
                    ->whereNull('business_id')
                    ->count();

            if ($missingBusinessCount > 0) {
                throw new \RuntimeException(
                    'Cannot contract catalog_categories.business_id: '
                    . $missingBusinessCount
                    . ' rows still have NULL business_id.'
                );
            }

            Schema::table(
                'catalog_categories',
                function (Blueprint $table): void {
                    $table->ulid('business_id')
                        ->nullable(false)
                        ->change();

                    $table->dropUnique(
                        'catalog_categories_tenant_code_unique'
                    );

                    $table->dropIndex(
                        'catalog_categories_tenant_status_name_index'
                    );
                }
            );
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            Schema::table(
                'catalog_categories',
                function (Blueprint $table): void {
                    $table->unique(
                        ['tenant_id', 'code'],
                        'catalog_categories_tenant_code_unique'
                    );

                    $table->index(
                        ['tenant_id', 'status', 'name'],
                        'catalog_categories_tenant_status_name_index'
                    );

                    $table->ulid('business_id')
                        ->nullable()
                        ->change();
                }
            );
        });
    }
};
