<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('namespace', 80);
            $table->string('key', 120);

            $table->string('value_type', 24);
            $table->jsonb('value');

            $table->unsignedSmallInteger('schema_version')
                ->default(1);

            $table->string('description', 500)->nullable();

            $table->timestampsTz();

            $table->unique(
                ['namespace', 'key'],
                'platform_settings_namespace_key_unique'
            );

            $table->index('namespace');
        });

        DB::statement("
            ALTER TABLE platform_settings
            ADD CONSTRAINT platform_settings_value_type_check
            CHECK (
                value_type IN (
                    'BOOLEAN',
                    'INTEGER',
                    'DECIMAL',
                    'STRING',
                    'JSON'
                )
            )
        ");

        DB::statement("
            ALTER TABLE platform_settings
            ADD CONSTRAINT platform_settings_schema_version_check
            CHECK (schema_version >= 1)
        ");

        DB::statement("
            ALTER TABLE platform_settings
            ADD CONSTRAINT platform_settings_identity_check
            CHECK (
                LENGTH(BTRIM(namespace)) > 0
                AND LENGTH(BTRIM(key)) > 0
            )
        ");

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();

            /*
             * Intentionally no foreign keys on actor_user_id / tenant_id.
             * Audit retention may outlive the referenced source record.
             */
            $table->ulid('actor_user_id')->nullable();
            $table->ulid('tenant_id')->nullable();

            $table->string('action', 120);
            $table->string('entity_type', 120);
            $table->string('entity_id', 120)->nullable();

            $table->string('source', 64);
            $table->string('reason', 500)->nullable();

            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_id', 120)->nullable();

            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->index([
                'tenant_id',
                'occurred_at',
            ]);

            $table->index([
                'actor_user_id',
                'occurred_at',
            ]);

            $table->index([
                'entity_type',
                'entity_id',
                'occurred_at',
            ]);

            $table->index([
                'action',
                'occurred_at',
            ]);

            $table->index('request_id');
        });

        DB::statement("
            ALTER TABLE audit_logs
            ADD CONSTRAINT audit_logs_identity_check
            CHECK (
                LENGTH(BTRIM(action)) > 0
                AND LENGTH(BTRIM(entity_type)) > 0
                AND LENGTH(BTRIM(source)) > 0
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('platform_settings');
    }
};
