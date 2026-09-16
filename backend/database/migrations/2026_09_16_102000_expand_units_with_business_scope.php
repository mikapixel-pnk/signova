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
            Schema::table('units', function (Blueprint $table): void {
                $table->ulid('business_id')
                    ->nullable();
            });

            /*
             * Existing units belong to the deterministic default
             * Business of their Tenant.
             *
             * Do not assume:
             *     business_id === tenant_id
             *
             * New tenants already have an independent Business ULID.
             */
            DB::statement(
                <<<'SQL'
                UPDATE units AS u
                SET business_id = bp.id
                FROM business_profiles AS bp
                WHERE bp.tenant_id = u.tenant_id
                  AND bp.is_default = TRUE
                  AND u.business_id IS NULL
                SQL
            );

            $missingBusinessCount =
                DB::table('units')
                    ->whereNull('business_id')
                    ->count();

            if ($missingBusinessCount > 0) {
                throw new \RuntimeException(
                    'Unable to backfill units.business_id: '
                    . $missingBusinessCount
                    . ' unit rows have no default Business.'
                );
            }

            Schema::table('units', function (Blueprint $table): void {
                /*
                 * Composite FK proves that the selected Business
                 * belongs to the same Tenant as the Unit.
                 *
                 * No cascade on Business deletion:
                 * operational data must not disappear merely because
                 * a Business profile is removed.
                 */
                $table->foreign(
                    ['business_id', 'tenant_id'],
                    'units_business_tenant_foreign'
                )
                    ->references(['id', 'tenant_id'])
                    ->on('business_profiles');

                /*
                 * Temporary business-aware constraint.
                 *
                 * units_tenant_code_unique remains during EXPAND so
                 * old application code cannot create duplicate codes
                 * while it still writes without business_id.
                 */
                $table->unique(
                    ['tenant_id', 'business_id', 'code'],
                    'units_tenant_business_code_unique'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'status',
                        'name',
                    ],
                    'units_tenant_business_status_name_index'
                );
            });
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            Schema::table('units', function (Blueprint $table): void {
                $table->dropForeign(
                    'units_business_tenant_foreign'
                );

                $table->dropUnique(
                    'units_tenant_business_code_unique'
                );

                $table->dropIndex(
                    'units_tenant_business_status_name_index'
                );

                $table->dropColumn('business_id');
            });
        });
    }
};
