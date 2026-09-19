<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'expenses',
            function (Blueprint $table): void {
                $table->ulid(
                    'evidence_file_id'
                )->nullable();

                $table->foreign(
                    [
                        'evidence_file_id',
                        'tenant_id',
                    ],
                    'expenses_evidence_file_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('files');

                $table->index(
                    [
                        'tenant_id',
                        'evidence_file_id',
                    ],
                    'expenses_tenant_evidence_file_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'expenses',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'expenses_evidence_file_tenant_foreign'
                );

                $table->dropIndex(
                    'expenses_tenant_evidence_file_index'
                );

                $table->dropColumn(
                    'evidence_file_id'
                );
            }
        );
    }
};
