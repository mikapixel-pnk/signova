<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unique(
                ['id', 'tenant_id'],
                'subscriptions_id_tenant_unique'
            );
        });

        Schema::table('subscription_periods', function (Blueprint $table) {
            $table->foreign(
                ['subscription_id', 'tenant_id'],
                'subscription_periods_subscription_tenant_fk'
            )
                ->references(['id', 'tenant_id'])
                ->on('subscriptions')
                ->restrictOnDelete();

            $table->unique(
                ['id', 'subscription_id', 'tenant_id'],
                'subscription_periods_scope_unique'
            );
        });

        Schema::create('entitlement_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('tenant_id');
            $table->ulid('subscription_id');
            $table->ulid('subscription_period_id');
            $table->ulid('feature_id');

            $table->boolean('enabled');

            $table->unsignedBigInteger('limit_value')
                ->nullable();

            $table->string('value', 190)
                ->nullable();

            $table->jsonb('config')
                ->nullable();

            $table->string('source', 64);

            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();

            $table->timestampsTz();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->foreign(
                [
                    'subscription_period_id',
                    'subscription_id',
                    'tenant_id',
                ],
                'entitlement_snapshots_period_scope_fk'
            )
                ->references([
                    'id',
                    'subscription_id',
                    'tenant_id',
                ])
                ->on('subscription_periods')
                ->restrictOnDelete();

            $table->foreign('feature_id')
                ->references('id')
                ->on('features')
                ->restrictOnDelete();

            $table->unique([
                'subscription_period_id',
                'feature_id',
            ]);

            $table->index([
                'tenant_id',
                'feature_id',
                'effective_from',
            ]);

            $table->index([
                'subscription_id',
                'effective_from',
                'effective_to',
            ]);
        });

        DB::statement("
            ALTER TABLE entitlement_snapshots
            ADD CONSTRAINT entitlement_snapshots_effective_range_check
            CHECK (
                effective_to IS NULL
                OR effective_to > effective_from
            )
        ");

        Schema::create('entitlement_overrides', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('tenant_id');
            $table->ulid('subscription_id')->nullable();
            $table->ulid('subscription_period_id')->nullable();
            $table->ulid('feature_id');

            $table->boolean('enabled')->nullable();

            $table->unsignedBigInteger('limit_value')
                ->nullable();

            $table->string('value', 190)
                ->nullable();

            $table->jsonb('config')
                ->nullable();

            $table->string('source', 64);
            $table->string('reason', 500);

            $table->ulid('actor_user_id')->nullable();

            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();

            $table->timestampsTz();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->foreign(
                ['subscription_id', 'tenant_id'],
                'entitlement_overrides_subscription_tenant_fk'
            )
                ->references(['id', 'tenant_id'])
                ->on('subscriptions')
                ->restrictOnDelete();

            $table->foreign(
                [
                    'subscription_period_id',
                    'subscription_id',
                    'tenant_id',
                ],
                'entitlement_overrides_period_scope_fk'
            )
                ->references([
                    'id',
                    'subscription_id',
                    'tenant_id',
                ])
                ->on('subscription_periods')
                ->restrictOnDelete();

            $table->foreign('feature_id')
                ->references('id')
                ->on('features')
                ->restrictOnDelete();

            $table->foreign('actor_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index([
                'tenant_id',
                'feature_id',
                'effective_from',
                'effective_to',
            ]);

            $table->index([
                'subscription_id',
                'feature_id',
            ]);
        });

        DB::statement("
            ALTER TABLE entitlement_overrides
            ADD CONSTRAINT entitlement_overrides_effective_range_check
            CHECK (
                effective_to IS NULL
                OR effective_to > effective_from
            )
        ");

        DB::statement("
            ALTER TABLE entitlement_overrides
            ADD CONSTRAINT entitlement_overrides_scope_check
            CHECK (
                subscription_period_id IS NULL
                OR subscription_id IS NOT NULL
            )
        ");

        DB::statement("
            ALTER TABLE entitlement_overrides
            ADD CONSTRAINT entitlement_overrides_value_check
            CHECK (
                enabled IS NOT NULL
                OR limit_value IS NOT NULL
                OR value IS NOT NULL
                OR config IS NOT NULL
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlement_overrides');
        Schema::dropIfExists('entitlement_snapshots');

        Schema::table('subscription_periods', function (Blueprint $table) {
            $table->dropForeign(
                'subscription_periods_subscription_tenant_fk'
            );

            $table->dropUnique(
                'subscription_periods_scope_unique'
            );
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(
                'subscriptions_id_tenant_unique'
            );
        });
    }
};
