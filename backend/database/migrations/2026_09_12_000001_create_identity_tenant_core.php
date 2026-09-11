<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('name', 150);
            $table->string('email', 190)->unique();
            $table->string('phone', 32)->nullable();

            $table->string('auth_status', 32)->default('ACTIVE');

            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password');

            $table->rememberToken();
            $table->timestampsTz();

            $table->index('auth_status');
            $table->index('phone');
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('name', 190);
            $table->string('code', 64)->nullable()->unique();
            $table->string('slug', 120)->unique();

            $table->string('lifecycle_status', 32)->default('ACTIVE');

            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->string('locale', 16)->default('id');

            $table->ulid('primary_owner_user_id')->nullable();

            $table->timestampsTz();

            $table->foreign('primary_owner_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('lifecycle_status');
        });

        Schema::create('tenant_users', function (Blueprint $table) {
            $table->ulid('tenant_id');
            $table->ulid('user_id');

            $table->string('status', 32)->default('ACTIVE');

            $table->timestampTz('joined_at')->nullable();

            /*
             * Future context:
             * branch/team/project scope may be added as normalized
             * relational data when required by Pro/multi-context.
             */
            $table->jsonb('context')->nullable();

            $table->timestampsTz();

            $table->primary(['tenant_id', 'user_id']);

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index(['user_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('users');
    }
};
