<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'tenant_document_settings',
            function (Blueprint $table) {
                $table->ulid('tenant_id')->primary();

                $table->string(
                    'business_name',
                    190
                )->nullable();

                $table->text(
                    'address'
                )->nullable();

                $table->string(
                    'phone',
                    64
                )->nullable();

                $table->string(
                    'email',
                    190
                )->nullable();

                $table->string(
                    'tax_id',
                    100
                )->nullable();

                $table->text(
                    'quotation_footer'
                )->nullable();

                $table->timestampsTz();

                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'tenant_document_settings'
        );
    }
};
