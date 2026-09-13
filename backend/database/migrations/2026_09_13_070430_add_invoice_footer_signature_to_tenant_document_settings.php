<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'tenant_document_settings',
            function (Blueprint $table): void {
                $table->text(
                    'invoice_footnote'
                )->nullable();

                $table->string(
                    'signature_name',
                    190
                )->nullable();

                $table->string(
                    'signature_title',
                    190
                )->nullable();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'tenant_document_settings',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'invoice_footnote',
                    'signature_name',
                    'signature_title',
                ]);
            }
        );
    }
};
