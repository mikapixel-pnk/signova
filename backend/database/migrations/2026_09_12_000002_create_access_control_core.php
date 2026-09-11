<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('code', 120)->unique();
            $table->string('name', 160);
            $table->string('description', 500)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestampsTz();

            $table->index('is_active');
        });

        Schema::create('capabilities', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('feature_id')->nullable();

            $table->string('code', 160)->unique();
            $table->string('name', 160);
            $table->string('description', 500)->nullable();

            $table->string('data_scope_type', 32)->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestampsTz();

            $table->foreign('feature_id')
                ->references('id')
                ->on('features')
                ->nullOnDelete();

            $table->index(['feature_id', 'is_active']);
            $table->index('is_sensitive');
        });

        Schema::create('master_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('code', 120)->unique();
            $table->string('name', 160);
            $table->string('description', 500)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestampsTz();

            $table->index('is_active');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('tenant_id');
            $table->ulid('master_role_id')->nullable();

            $table->string('name', 160);
            $table->string('code', 120);

            $table->string('status', 32)->default('ACTIVE');
            $table->boolean('is_system')->default(false);

            $table->timestampsTz();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('master_role_id')
                ->references('id')
                ->on('master_roles')
                ->nullOnDelete();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('role_capabilities', function (Blueprint $table) {
            $table->ulid('role_id');
            $table->ulid('capability_id');

            $table->string('effect', 16)->default('ALLOW');

            $table->timestampsTz();

            $table->primary(['role_id', 'capability_id']);

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();

            $table->foreign('capability_id')
                ->references('id')
                ->on('capabilities')
                ->cascadeOnDelete();

            $table->index(['capability_id', 'effect']);
        });

        Schema::create('tenant_user_roles', function (Blueprint $table) {
            $table->ulid('tenant_id');
            $table->ulid('user_id');
            $table->ulid('role_id');

            $table->timestampTz('assigned_at')->nullable();

            $table->timestampsTz();

            $table->primary(['tenant_id', 'user_id', 'role_id']);

            $table->foreign(['tenant_id', 'user_id'])
                ->references(['tenant_id', 'user_id'])
                ->on('tenant_users')
                ->cascadeOnDelete();

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();

            $table->index(['role_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_roles');
        Schema::dropIfExists('role_capabilities');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('master_roles');
        Schema::dropIfExists('capabilities');
        Schema::dropIfExists('features');
    }
};
