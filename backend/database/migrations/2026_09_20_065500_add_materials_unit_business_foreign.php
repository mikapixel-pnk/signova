<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'materials',
            function (Blueprint $table): void {
                $table->foreign(
                    [
                        'unit_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'materials_unit_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('units')
                    ->restrictOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'materials',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'materials_unit_business_tenant_foreign'
                );
            }
        );
    }
};
