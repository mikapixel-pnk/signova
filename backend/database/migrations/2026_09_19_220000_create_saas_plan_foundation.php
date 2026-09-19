<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('code', 64)->unique();
            $table->string('name', 160);
            $table->string('description', 500)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestampsTz();

            $table->index('is_active');
        });

        Schema::create('plan_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('plan_id');

            $table->unsignedInteger('version_no');
            $table->string('status', 32);

            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('effective_to')->nullable();

            $table->timestampsTz();

            $table->foreign('plan_id')
                ->references('id')
                ->on('plans')
                ->restrictOnDelete();

            $table->unique([
                'plan_id',
                'version_no',
            ]);

            $table->index([
                'status',
                'effective_from',
            ]);
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->ulid('plan_version_id');
            $table->ulid('feature_id');

            $table->boolean('enabled');

            $table->unsignedBigInteger('default_limit')
                ->nullable();

            $table->string('default_value', 190)
                ->nullable();

            $table->jsonb('config')
                ->nullable();

            $table->unsignedInteger('config_schema_version')
                ->nullable();

            $table->timestampsTz();

            $table->primary([
                'plan_version_id',
                'feature_id',
            ]);

            $table->foreign('plan_version_id')
                ->references('id')
                ->on('plan_versions')
                ->restrictOnDelete();

            $table->foreign('feature_id')
                ->references('id')
                ->on('features')
                ->restrictOnDelete();

            $table->index([
                'feature_id',
                'enabled',
            ]);
        });

        Schema::create('plan_offerings', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('plan_version_id');

            $table->string('code', 120)->unique();
            $table->string('name', 160);
            $table->string('description', 500)->nullable();

            $table->string('billing_type', 32);

            $table->unsignedSmallInteger('duration_months')
                ->nullable();

            $table->unsignedInteger('duration_days')
                ->nullable();

            $table->decimal(
                'price_amount',
                18,
                2
            );

            $table->char('currency', 3);

            $table->boolean('is_active')->default(true);

            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('effective_to')->nullable();

            $table->timestampsTz();

            $table->foreign('plan_version_id')
                ->references('id')
                ->on('plan_versions')
                ->restrictOnDelete();

            $table->index([
                'plan_version_id',
                'is_active',
            ]);

            $table->index([
                'effective_from',
                'effective_to',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_offerings');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plan_versions');
        Schema::dropIfExists('plans');
    }
};
