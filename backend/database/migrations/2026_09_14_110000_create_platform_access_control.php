<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'platform_capabilities',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->string('code', 160)->unique();
                $table->string('name', 160);
                $table->string('description', 500)->nullable();

                $table->boolean('is_sensitive')
                    ->default(false);

                $table->boolean('is_active')
                    ->default(true);

                $table->timestampsTz();

                $table->index('is_sensitive');
                $table->index('is_active');
            }
        );

        Schema::create(
            'platform_roles',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->string('code', 120)->unique();
                $table->string('name', 160);
                $table->string('description', 500)->nullable();

                $table->string('status', 32)
                    ->default('ACTIVE');

                $table->boolean('is_system')
                    ->default(true);

                $table->timestampsTz();

                $table->index('status');
            }
        );

        Schema::create(
            'platform_role_capabilities',
            function (Blueprint $table): void {
                $table->ulid('platform_role_id');
                $table->ulid('platform_capability_id');

                $table->string('effect', 16)
                    ->default('ALLOW');

                $table->timestampsTz();

                $table->primary([
                    'platform_role_id',
                    'platform_capability_id',
                ]);

                $table->foreign('platform_role_id')
                    ->references('id')
                    ->on('platform_roles')
                    ->cascadeOnDelete();

                $table->foreign(
                    'platform_capability_id'
                )
                    ->references('id')
                    ->on('platform_capabilities')
                    ->cascadeOnDelete();

                $table->index([
                    'platform_capability_id',
                    'effect',
                ]);
            }
        );

        Schema::create(
            'platform_user_roles',
            function (Blueprint $table): void {
                $table->ulid('user_id');
                $table->ulid('platform_role_id');

                $table->timestampTz('assigned_at')
                    ->nullable();

                $table->ulid('assigned_by_user_id')
                    ->nullable();

                $table->timestampsTz();

                $table->primary([
                    'user_id',
                    'platform_role_id',
                ]);

                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                $table->foreign('platform_role_id')
                    ->references('id')
                    ->on('platform_roles')
                    ->cascadeOnDelete();

                $table->foreign('assigned_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index('platform_role_id');
                $table->index('assigned_by_user_id');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'platform_user_roles'
        );

        Schema::dropIfExists(
            'platform_role_capabilities'
        );

        Schema::dropIfExists(
            'platform_roles'
        );

        Schema::dropIfExists(
            'platform_capabilities'
        );
    }
};
