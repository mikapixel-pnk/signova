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
            'invoices',
            function (Blueprint $table): void {
                $table->string(
                    'invoice_template_key',
                    100
                )
                    ->nullable()
                    ->after(
                        'outstanding_amount'
                    );

                $table->string(
                    'invoice_palette_key',
                    100
                )
                    ->nullable()
                    ->after(
                        'invoice_template_key'
                    );

                $table->unsignedSmallInteger(
                    'invoice_template_version'
                )
                    ->nullable()
                    ->after(
                        'invoice_palette_key'
                    );
            }
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            ADD CONSTRAINT invoices_template_version_positive_check
            CHECK (
                invoice_template_version IS NULL
                OR invoice_template_version >= 1
            )
            SQL
        );
    }

    public function down(): void
    {
        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            DROP CONSTRAINT IF EXISTS
            invoices_template_version_positive_check
            SQL
        );

        Schema::table(
            'invoices',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'invoice_template_key',
                    'invoice_palette_key',
                    'invoice_template_version',
                ]);
            }
        );
    }
};
