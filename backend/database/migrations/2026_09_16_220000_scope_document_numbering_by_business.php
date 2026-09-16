<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Prefix adalah konfigurasi Business.
         * tenant_sequences hanya menyimpan counter aktif per
         * Business + document type + period.
         */
        Schema::table(
            'tenant_document_settings',
            function (Blueprint $table): void {
                $table->string(
                    'quotation_number_prefix',
                    20
                )->nullable();

                $table->string(
                    'invoice_number_prefix',
                    20
                )->nullable();
            }
        );

        DB::table('tenant_document_settings')
            ->whereNull('quotation_number_prefix')
            ->update([
                'quotation_number_prefix' => 'PNW',
            ]);

        DB::table('tenant_document_settings')
            ->whereNull('invoice_number_prefix')
            ->update([
                'invoice_number_prefix' => 'INV',
            ]);

        Schema::table(
            'tenant_sequences',
            function (Blueprint $table): void {
                $table->ulid(
                    'business_id'
                )->nullable();
            }
        );

        /*
         * Legacy sequence dimiliki default Business tenant.
         * Aman juga untuk database yang tenant_sequences-nya
         * masih kosong.
         */
        DB::statement(
            <<<'SQL'
            UPDATE tenant_sequences AS sequence
            SET business_id = business.id
            FROM business_profiles AS business
            WHERE business.tenant_id = sequence.tenant_id
              AND business.is_default = TRUE
              AND sequence.business_id IS NULL
            SQL
        );

        $missingBusinessCount =
            DB::table('tenant_sequences')
                ->whereNull('business_id')
                ->count();

        if ($missingBusinessCount > 0) {
            throw new RuntimeException(
                'Tidak semua tenant_sequences dapat dipetakan '
                . 'ke default Business.'
            );
        }

        Schema::table(
            'tenant_sequences',
            function (Blueprint $table): void {
                $table->ulid(
                    'business_id'
                )->nullable(false)
                    ->change();
            }
        );

        Schema::table(
            'tenant_sequences',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'tenant_sequences_tenant_id_document_type_period_unique'
                );

                $table->unique(
                    [
                        'tenant_id',
                        'business_id',
                        'document_type',
                        'period',
                    ],
                    'tenant_sequences_business_document_period_unique'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'document_type',
                    ],
                    'tenant_sequences_business_document_index'
                );
            }
        );

        DB::statement(
            'ALTER TABLE tenant_sequences '
            . 'ADD CONSTRAINT '
            . 'tenant_sequences_business_tenant_foreign '
            . 'FOREIGN KEY (business_id, tenant_id) '
            . 'REFERENCES business_profiles (id, tenant_id) '
            . 'ON DELETE CASCADE'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE tenant_sequences '
            . 'DROP CONSTRAINT IF EXISTS '
            . 'tenant_sequences_business_tenant_foreign'
        );

        Schema::table(
            'tenant_sequences',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'tenant_sequences_business_document_period_unique'
                );

                $table->dropIndex(
                    'tenant_sequences_business_document_index'
                );

                $table->unique(
                    [
                        'tenant_id',
                        'document_type',
                        'period',
                    ],
                    'tenant_sequences_tenant_id_document_type_period_unique'
                );

                $table->dropColumn(
                    'business_id'
                );
            }
        );

        Schema::table(
            'tenant_document_settings',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'quotation_number_prefix',
                    'invoice_number_prefix',
                ]);
            }
        );
    }
};
