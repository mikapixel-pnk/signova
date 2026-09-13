<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'payments',
            function (Blueprint $table): void {
                $table->ulid(
                    'cash_account_id'
                )
                    ->nullable()
                    ->after('customer_id');

                $table->foreign([
                    'cash_account_id',
                    'tenant_id',
                ])
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('cash_accounts');

                $table->index([
                    'tenant_id',
                    'cash_account_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'payments',
            function (Blueprint $table): void {
                $table->dropForeign([
                    'cash_account_id',
                    'tenant_id',
                ]);

                $table->dropIndex([
                    'tenant_id',
                    'cash_account_id',
                ]);

                $table->dropColumn(
                    'cash_account_id'
                );
            }
        );
    }
};
