<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Expand first.
         *
         * Existing migration history remains immutable.
         * business_id is resolved from each tenant's default business;
         * never assume business_id === tenant_id.
         */
        Schema::table(
            'tenant_document_settings',
            function (Blueprint $table): void {
                $table->ulid('id')->nullable();
                $table->ulid('business_id')->nullable();

                $table->index(
                    ['tenant_id', 'business_id'],
                    'tenant_document_settings_tenant_business_index'
                );
            }
        );

        DB::table('tenant_document_settings')
            ->select(['tenant_id'])
            ->orderBy('tenant_id')
            ->get()
            ->each(
                function ($settings): void {
                    $businessId =
                        DB::table('business_profiles')
                            ->where(
                                'tenant_id',
                                $settings->tenant_id
                            )
                            ->where(
                                'is_default',
                                true
                            )
                            ->value('id');

                    if (! is_string($businessId)) {
                        throw new \RuntimeException(
                            'Default business is required to backfill '
                            . 'tenant document settings.'
                        );
                    }

                    DB::table('tenant_document_settings')
                        ->where(
                            'tenant_id',
                            $settings->tenant_id
                        )
                        ->update([
                            'id' =>
                                (string) Str::ulid(),

                            'business_id' =>
                                $businessId,
                        ]);
                }
            );

        DB::statement(
            'ALTER TABLE tenant_document_settings '
            . 'ALTER COLUMN id SET NOT NULL'
        );

        DB::statement(
            'ALTER TABLE tenant_document_settings '
            . 'ALTER COLUMN business_id SET NOT NULL'
        );

        /*
         * Contract old one-row-per-tenant identity.
         */
        Schema::table(
            'tenant_document_settings',
            function (Blueprint $table): void {
                $table->dropPrimary(
                    'tenant_document_settings_pkey'
                );

                $table->primary(
                    'id',
                    'tenant_document_settings_pkey'
                );

                $table->unique(
                    ['tenant_id', 'business_id'],
                    'tenant_document_settings_tenant_business_unique'
                );

                $table->foreign(
                    ['business_id', 'tenant_id'],
                    'tenant_document_settings_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('business_profiles');
            }
        );
    }

    public function down(): void
    {
        /*
         * Safe rollback only when every tenant has at most one row.
         */
        $duplicates =
            DB::table('tenant_document_settings')
                ->select('tenant_id')
                ->groupBy('tenant_id')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

        if ($duplicates) {
            throw new \RuntimeException(
                'Cannot rollback business-scoped document settings '
                . 'while a tenant has multiple business rows.'
            );
        }

        Schema::table(
            'tenant_document_settings',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'tenant_document_settings_business_tenant_foreign'
                );

                $table->dropUnique(
                    'tenant_document_settings_tenant_business_unique'
                );

                $table->dropPrimary(
                    'tenant_document_settings_pkey'
                );

                $table->primary(
                    'tenant_id',
                    'tenant_document_settings_pkey'
                );

                $table->dropIndex(
                    'tenant_document_settings_tenant_business_index'
                );

                $table->dropColumn([
                    'id',
                    'business_id',
                ]);
            }
        );
    }
};
