<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'invoices',
            function (Blueprint $table): void {
                $table->decimal(
                    'item_discount_total',
                    18,
                    2
                )
                    ->nullable()
                    ->after(
                        'discount_total'
                    );

                $table->string(
                    'global_discount_type',
                    16
                )
                    ->nullable()
                    ->after(
                        'item_discount_total'
                    );

                $table->decimal(
                    'global_discount_value',
                    18,
                    4
                )
                    ->nullable()
                    ->after(
                        'global_discount_type'
                    );

                $table->decimal(
                    'global_discount_amount',
                    18,
                    2
                )
                    ->nullable()
                    ->after(
                        'global_discount_value'
                    );

                $table->boolean(
                    'tax_enabled'
                )
                    ->nullable()
                    ->after(
                        'global_discount_amount'
                    );

                $table->decimal(
                    'tax_rate',
                    7,
                    4
                )
                    ->nullable()
                    ->after(
                        'tax_enabled'
                    );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'invoices',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'item_discount_total',
                    'global_discount_type',
                    'global_discount_value',
                    'global_discount_amount',
                    'tax_enabled',
                    'tax_rate',
                ]);
            }
        );
    }
};
