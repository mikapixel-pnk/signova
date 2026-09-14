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
                $table->string(
                    'invoice_template_key',
                    100
                )
                    ->nullable()
                    ->after(
                        'invoice_footnote'
                    );

                $table->string(
                    'invoice_palette_key',
                    100
                )
                    ->nullable()
                    ->after(
                        'invoice_template_key'
                    );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'tenant_document_settings',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'invoice_template_key',
                    'invoice_palette_key',
                ]);
            }
        );
    }
};
