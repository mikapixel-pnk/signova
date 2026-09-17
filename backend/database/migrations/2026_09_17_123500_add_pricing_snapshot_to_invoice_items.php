<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'invoice_items',
            function (Blueprint $table): void {
                $table->string(
                    'pricing_method',
                    32
                )
                    ->nullable()
                    ->after('unit_symbol');

                $table->jsonb(
                    'pricing_config'
                )
                    ->nullable()
                    ->after('pricing_method');

                $table->decimal(
                    'pricing_quantity',
                    24,
                    4
                )
                    ->nullable()
                    ->after('pricing_config');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'invoice_items',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'pricing_method',
                    'pricing_config',
                    'pricing_quantity',
                ]);
            }
        );
    }
};
