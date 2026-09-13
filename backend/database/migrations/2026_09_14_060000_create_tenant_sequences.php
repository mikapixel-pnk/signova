<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'tenant_sequences',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->ulid(
                    'tenant_id'
                );

                $table->string(
                    'document_type',
                    64
                );

                $table->string(
                    'period',
                    32
                );

                $table->string(
                    'prefix',
                    100
                );

                $table->unsignedBigInteger(
                    'next_number'
                )->default(1);

                $table->unsignedSmallInteger(
                    'padding'
                )->default(4);

                $table->timestampsTz();

                $table->unique([
                    'tenant_id',
                    'document_type',
                    'period',
                ]);

                $table->foreign(
                    'tenant_id'
                )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->index([
                    'tenant_id',
                    'document_type',
                ]);
            }
        );

        DB::statement(
            'ALTER TABLE tenant_sequences '
            . 'ADD CONSTRAINT tenant_sequences_next_number_check '
            . 'CHECK (next_number >= 1)'
        );

        DB::statement(
            'ALTER TABLE tenant_sequences '
            . 'ADD CONSTRAINT tenant_sequences_padding_check '
            . 'CHECK (padding >= 1 AND padding <= 12)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'tenant_sequences'
        );
    }
};
