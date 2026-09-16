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
            $missingBusinessCount = DB::table('units')
                ->whereNull('business_id')
                ->count();

            if ($missingBusinessCount > 0) {
                throw new \RuntimeException(
                    'Cannot contract units.business_id: '
                    . $missingBusinessCount
                    . ' rows still have NULL business_id.'
                );
            }

            Schema::table('units', function (Blueprint $table): void {
                $table->ulid('business_id')
                    ->nullable(false)
                    ->change();

                /*
                 * Application now scopes Unit by Tenant + Business.
                 * The old tenant-only code uniqueness would prevent
                 * different Businesses inside one Tenant from using
                 * the same canonical unit code.
                 */
                $table->dropUnique(
                    'units_tenant_code_unique'
                );

                $table->dropIndex(
                    'units_tenant_status_name_index'
                );
            });
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            Schema::table('units', function (Blueprint $table): void {
                /*
                 * Rollback can only restore tenant-level uniqueness
                 * if no duplicate code already exists across
                 * Businesses of the same Tenant.
                 */
                $table->unique(
                    ['tenant_id', 'code'],
                    'units_tenant_code_unique'
                );

                $table->index(
                    ['tenant_id', 'status', 'name'],
                    'units_tenant_status_name_index'
                );

                $table->ulid('business_id')
                    ->nullable()
                    ->change();
            });
        });
    }
};
