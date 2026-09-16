<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'tenant_document_settings',
            function (Blueprint $table): void {
                $table->text(
                    'quotation_opening_text'
                )->nullable();

                $table->text(
                    'quotation_closing_text'
                )->nullable();
            }
        );

        DB::table('tenant_document_settings')
            ->whereNull('quotation_closing_text')
            ->whereNotNull('quotation_footer')
            ->update([
                'quotation_closing_text' =>
                    DB::raw('quotation_footer'),
            ]);
    }

    public function down(): void
    {
        Schema::table(
            'tenant_document_settings',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'quotation_opening_text',
                    'quotation_closing_text',
                ]);
            }
        );
    }
};
