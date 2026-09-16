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
            Schema::table(
                'catalog_categories',
                function (Blueprint $table): void {
                    $table->ulid('business_id')
                        ->nullable();
                }
            );

            /*
             * Existing categories are assigned to the deterministic
             * default Business belonging to the same Tenant.
             *
             * Never assume business_id === tenant_id.
             */
            DB::statement(
                <<<'SQL'
                UPDATE catalog_categories AS cc
                SET business_id = bp.id
                FROM business_profiles AS bp
                WHERE bp.tenant_id = cc.tenant_id
                  AND bp.is_default = TRUE
                  AND cc.business_id IS NULL
                SQL
            );

            $missingBusinessCount =
                DB::table('catalog_categories')
                    ->whereNull('business_id')
                    ->count();

            if ($missingBusinessCount > 0) {
                throw new \RuntimeException(
                    'Unable to backfill catalog_categories.business_id: '
                    . $missingBusinessCount
                    . ' category rows have no default Business.'
                );
            }

            Schema::table(
                'catalog_categories',
                function (Blueprint $table): void {
                    $table->foreign(
                        ['business_id', 'tenant_id'],
                        'catalog_categories_business_tenant_foreign'
                    )
                        ->references(['id', 'tenant_id'])
                        ->on('business_profiles');

                    /*
                     * Keep tenant-only uniqueness temporarily during
                     * EXPAND so the old application remains compatible.
                     */
                    $table->unique(
                        [
                            'tenant_id',
                            'business_id',
                            'code',
                        ],
                        'catalog_categories_tenant_business_code_unique'
                    );

                    $table->index(
                        [
                            'tenant_id',
                            'business_id',
                            'status',
                            'name',
                        ],
                        'catalog_categories_tenant_business_status_name_index'
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
                    $table->dropForeign(
                        'catalog_categories_business_tenant_foreign'
                    );

                    $table->dropUnique(
                        'catalog_categories_tenant_business_code_unique'
                    );

                    $table->dropIndex(
                        'catalog_categories_tenant_business_status_name_index'
                    );

                    $table->dropColumn('business_id');
                }
            );
        });
    }
};
