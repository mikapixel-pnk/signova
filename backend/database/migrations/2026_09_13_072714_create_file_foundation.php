<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'files',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->ulid(
                    'tenant_id'
                );

                $table->string(
                    'purpose',
                    64
                );

                $table->string(
                    'storage_disk',
                    64
                );

                $table->string(
                    'object_key',
                    500
                );

                $table->string(
                    'original_name',
                    255
                );

                $table->string(
                    'mime_type',
                    190
                );

                $table->bigInteger(
                    'size_bytes'
                );

                $table->char(
                    'checksum_sha256',
                    64
                )->nullable();

                $table->string(
                    'visibility',
                    16
                )->default('PRIVATE');

                $table->ulid(
                    'uploaded_by_user_id'
                )->nullable();

                $table->timestampsTz();

                $table->foreign(
                    'tenant_id'
                )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign([
                    'tenant_id',
                    'uploaded_by_user_id',
                ])
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users');

                $table->unique([
                    'id',
                    'tenant_id',
                ]);

                $table->unique(
                    [
                        'tenant_id',
                        'object_key',
                    ],
                    'files_tenant_object_key_unique'
                );

                $table->index(
                    [
                        'tenant_id',
                        'purpose',
                    ],
                    'files_tenant_purpose_index'
                );

                $table->index(
                    [
                        'tenant_id',
                        'created_at',
                    ],
                    'files_tenant_created_at_index'
                );
            }
        );

        Schema::table(
            'tenant_document_settings',
            function (Blueprint $table): void {
                $table->ulid(
                    'signature_image_file_id'
                )->nullable();

                $table->foreign([
                    'signature_image_file_id',
                    'tenant_id',
                ])
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
            'tenant_document_settings',
            function (Blueprint $table): void {
                $table->dropForeign([
                    'signature_image_file_id',
                    'tenant_id',
                ]);

                $table->dropColumn(
                    'signature_image_file_id'
                );
            }
        );

        Schema::dropIfExists('files');
    }
};
