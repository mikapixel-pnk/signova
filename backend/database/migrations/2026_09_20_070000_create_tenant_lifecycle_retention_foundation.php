<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_status_history', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('tenant_id');

            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);

            $table->string('source', 64);
            $table->string('reason', 500)->nullable();

            $table->ulid('actor_user_id')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->foreign('actor_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index([
                'tenant_id',
                'occurred_at',
            ]);

            $table->index([
                'tenant_id',
                'to_status',
            ]);
        });

        DB::statement("
            ALTER TABLE tenant_status_history
            ADD CONSTRAINT tenant_status_history_transition_check
            CHECK (
                from_status IS NULL
                OR from_status <> to_status
            )
        ");

        Schema::create('tenant_retention_controls', function (Blueprint $table) {
            $table->ulid('tenant_id')->primary();

            $table->timestampTz('delete_requested_at')->nullable();
            $table->ulid('delete_requested_by_user_id')->nullable();

            $table->string(
                'delete_request_source',
                64
            )->nullable();

            $table->string(
                'delete_request_reason',
                500
            )->nullable();

            $table->timestampTz('retention_until')->nullable();

            $table->timestampTz('purge_approved_at')->nullable();
            $table->ulid('purge_approved_by_user_id')->nullable();

            $table->string(
                'purge_approval_source',
                64
            )->nullable();

            $table->timestampsTz();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->foreign('delete_requested_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('purge_approved_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index([
                'retention_until',
                'purge_approved_at',
            ]);
        });

        DB::statement("
            ALTER TABLE tenant_retention_controls
            ADD CONSTRAINT tenant_retention_request_check
            CHECK (
                delete_requested_at IS NULL
                OR (
                    delete_request_source IS NOT NULL
                    AND delete_request_reason IS NOT NULL
                )
            )
        ");

        DB::statement("
            ALTER TABLE tenant_retention_controls
            ADD CONSTRAINT tenant_retention_window_check
            CHECK (
                retention_until IS NULL
                OR (
                    delete_requested_at IS NOT NULL
                    AND retention_until >= delete_requested_at
                )
            )
        ");

        DB::statement("
            ALTER TABLE tenant_retention_controls
            ADD CONSTRAINT tenant_purge_approval_check
            CHECK (
                purge_approved_at IS NULL
                OR (
                    delete_requested_at IS NOT NULL
                    AND retention_until IS NOT NULL
                    AND purge_approval_source IS NOT NULL
                )
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_retention_controls');
        Schema::dropIfExists('tenant_status_history');
    }
};
