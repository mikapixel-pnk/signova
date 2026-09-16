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
                'catalog_items',
                function (Blueprint $table): void {
                    $table->ulid('business_id')
                        ->nullable();
                }
            );

            /*
             * Existing items follow the deterministic default Business
             * of their Tenant.
             *
             * Never assume business_id === tenant_id.
             */
            DB::statement(
                <<<'SQL'
                UPDATE catalog_items AS ci
                SET business_id = bp.id
                FROM business_profiles AS bp
                WHERE bp.tenant_id = ci.tenant_id
                  AND bp.is_default = TRUE
                  AND ci.business_id IS NULL
                SQL
            );

            $missingBusinessCount =
                DB::table('catalog_items')
                    ->whereNull('business_id')
                    ->count();

            if ($missingBusinessCount > 0) {
                throw new \RuntimeException(
                    'Unable to backfill catalog_items.business_id: '
                    . $missingBusinessCount
                    . ' rows have no default Business.'
                );
            }

            /*
             * Existing Category references must belong to the same
             * Business assigned to the Catalog Item.
             */
            $categoryMismatchCount = DB::table(
                'catalog_items as ci'
            )
                ->join(
                    'catalog_categories as cc',
                    function ($join): void {
                        $join
                            ->on(
                                'cc.id',
                                '=',
                                'ci.category_id'
                            )
                            ->on(
                                'cc.tenant_id',
                                '=',
                                'ci.tenant_id'
                            );
                    }
                )
                ->whereNotNull('ci.category_id')
                ->whereColumn(
                    'cc.business_id',
                    '!=',
                    'ci.business_id'
                )
                ->count();

            if ($categoryMismatchCount > 0) {
                throw new \RuntimeException(
                    'Cannot expand catalog_items: '
                    . $categoryMismatchCount
                    . ' Category references cross Business boundaries.'
                );
            }

            /*
             * Existing Unit references must also belong to the same
             * Business assigned to the Catalog Item.
             */
            $unitMismatchCount = DB::table(
                'catalog_items as ci'
            )
                ->join(
                    'units as u',
                    function ($join): void {
                        $join
                            ->on(
                                'u.id',
                                '=',
                                'ci.unit_id'
                            )
                            ->on(
                                'u.tenant_id',
                                '=',
                                'ci.tenant_id'
                            );
                    }
                )
                ->whereNotNull('ci.unit_id')
                ->whereColumn(
                    'u.business_id',
                    '!=',
                    'ci.business_id'
                )
                ->count();

            if ($unitMismatchCount > 0) {
                throw new \RuntimeException(
                    'Cannot expand catalog_items: '
                    . $unitMismatchCount
                    . ' Unit references cross Business boundaries.'
                );
            }

            Schema::table(
                'catalog_items',
                function (Blueprint $table): void {
                    $table->foreign(
                        ['business_id', 'tenant_id'],
                        'catalog_items_business_tenant_foreign'
                    )
                        ->references(['id', 'tenant_id'])
                        ->on('business_profiles');

                    /*
                     * Business-aware uniqueness/indexes are added during
                     * EXPAND while tenant-only compatibility remains.
                     */
                    $table->unique(
                        [
                            'tenant_id',
                            'business_id',
                            'code',
                        ],
                        'catalog_items_tenant_business_code_unique'
                    );

                    $table->index(
                        [
                            'tenant_id',
                            'business_id',
                            'status',
                            'type',
                        ],
                        'catalog_items_tenant_business_status_type_index'
                    );

                    $table->index(
                        [
                            'tenant_id',
                            'business_id',
                            'name',
                        ],
                        'catalog_items_tenant_business_name_index'
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
                    $table->dropForeign(
                        'catalog_items_business_tenant_foreign'
                    );

                    $table->dropUnique(
                        'catalog_items_tenant_business_code_unique'
                    );

                    $table->dropIndex(
                        'catalog_items_tenant_business_status_type_index'
                    );

                    $table->dropIndex(
                        'catalog_items_tenant_business_name_index'
                    );

                    $table->dropColumn('business_id');
                }
            );
        });
    }
};
