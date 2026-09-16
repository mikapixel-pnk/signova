<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'business_profiles',
            function (Blueprint $table): void {
                $table->ulid('logo_file_id')
                    ->nullable()
                    ->after('tax_id');

                $table->foreign([
                    'logo_file_id',
                    'tenant_id',
                ])
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('files');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'business_profiles',
            function (Blueprint $table): void {
                $table->dropForeign([
                    'logo_file_id',
                    'tenant_id',
                ]);

                $table->dropColumn(
                    'logo_file_id'
                );
            }
        );
    }
};
