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
                $table->jsonb(
                    'branding_snapshot'
                )
                    ->nullable()
                    ->after(
                        'invoice_template_version'
                    );

                $table->ulid(
                    'branding_logo_file_id'
                )
                    ->nullable()
                    ->after(
                        'branding_snapshot'
                    );

                $table->ulid(
                    'branding_signature_file_id'
                )
                    ->nullable()
                    ->after(
                        'branding_logo_file_id'
                    );

                $table->foreign(
                    [
                        'branding_logo_file_id',
                        'tenant_id',
                    ],
                    'invoices_branding_logo_file_tenant_fk'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('files');

                $table->foreign(
                    [
                        'branding_signature_file_id',
                        'tenant_id',
                    ],
                    'invoices_branding_signature_file_tenant_fk'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('files');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'invoices',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'invoices_branding_logo_file_tenant_fk'
                );

                $table->dropForeign(
                    'invoices_branding_signature_file_tenant_fk'
                );

                $table->dropColumn([
                    'branding_snapshot',
                    'branding_logo_file_id',
                    'branding_signature_file_id',
                ]);
            }
        );
    }
};
