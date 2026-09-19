<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('tenant_id');
            $table->ulid('plan_version_id');
            $table->ulid('offering_id')->nullable();
            $table->ulid('source_order_id')->nullable();
            $table->ulid('previous_subscription_id')->nullable();

            $table->string('status', 32)->default('PENDING');

            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();

            $table->timestampTz('trial_starts_at')->nullable();
            $table->timestampTz('trial_ends_at')->nullable();

            $table->timestampTz('grace_ends_at')->nullable();

            $table->timestampTz('cancelled_at')->nullable();

            $table->timestampTz('suspended_at')->nullable();
            $table->string('suspension_reason', 500)->nullable();

            $table->boolean('auto_renew')->default(false);
            $table->timestampTz('renewal_anchor')->nullable();

            $table->timestampsTz();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->foreign('plan_version_id')
                ->references('id')
                ->on('plan_versions')
                ->restrictOnDelete();

            $table->foreign('offering_id')
                ->references('id')
                ->on('plan_offerings')
                ->nullOnDelete();

            $table->unique('source_order_id');

            $table->index([
                'tenant_id',
                'status',
            ]);

            $table->index([
                'ends_at',
                'status',
            ]);

            $table->index('grace_ends_at');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('previous_subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->restrictOnDelete();
        });

        DB::statement("
            ALTER TABLE subscriptions
            ADD CONSTRAINT subscriptions_status_check
            CHECK (
                status IN (
                    'PENDING',
                    'TRIAL',
                    'ACTIVE',
                    'GRACE',
                    'SUSPENDED',
                    'EXPIRED',
                    'CANCELLED'
                )
            )
        ");

        DB::statement("
            ALTER TABLE subscriptions
            ADD CONSTRAINT subscriptions_active_period_check
            CHECK (
                starts_at IS NULL
                OR ends_at IS NULL
                OR ends_at > starts_at
            )
        ");

        DB::statement("
            ALTER TABLE subscriptions
            ADD CONSTRAINT subscriptions_trial_period_check
            CHECK (
                trial_starts_at IS NULL
                OR trial_ends_at IS NULL
                OR trial_ends_at > trial_starts_at
            )
        ");

        DB::statement("
            CREATE UNIQUE INDEX subscriptions_tenant_current_unique
            ON subscriptions (tenant_id)
            WHERE status IN (
                'TRIAL',
                'ACTIVE',
                'GRACE',
                'SUSPENDED'
            )
        ");

        Schema::create('subscription_periods', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('subscription_id');
            $table->ulid('tenant_id');

            $table->ulid('plan_version_id');
            $table->ulid('offering_id')->nullable();

            $table->string('status', 32);

            $table->timestampTz('period_start');
            $table->timestampTz('period_end');

            $table->timestampsTz();

            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->restrictOnDelete();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->foreign('plan_version_id')
                ->references('id')
                ->on('plan_versions')
                ->restrictOnDelete();

            $table->foreign('offering_id')
                ->references('id')
                ->on('plan_offerings')
                ->nullOnDelete();

            $table->index([
                'tenant_id',
                'period_start',
                'period_end',
            ]);

            $table->index([
                'subscription_id',
                'status',
            ]);
        });

        DB::statement("
            ALTER TABLE subscription_periods
            ADD CONSTRAINT subscription_periods_range_check
            CHECK (period_end > period_start)
        ");

        Schema::create('subscription_history', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('subscription_id');
            $table->ulid('tenant_id');

            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);

            $table->string('source', 64);
            $table->string('reason', 500)->nullable();

            $table->ulid('actor_user_id')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestampTz('occurred_at');

            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->restrictOnDelete();

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
                'subscription_id',
                'occurred_at',
            ]);
        });

        DB::statement("
            ALTER TABLE subscription_history
            ADD CONSTRAINT subscription_history_from_status_check
            CHECK (
                from_status IS NULL
                OR from_status IN (
                    'PENDING',
                    'TRIAL',
                    'ACTIVE',
                    'GRACE',
                    'SUSPENDED',
                    'EXPIRED',
                    'CANCELLED'
                )
            )
        ");

        DB::statement("
            ALTER TABLE subscription_history
            ADD CONSTRAINT subscription_history_to_status_check
            CHECK (
                to_status IN (
                    'PENDING',
                    'TRIAL',
                    'ACTIVE',
                    'GRACE',
                    'SUSPENDED',
                    'EXPIRED',
                    'CANCELLED'
                )
            )
        ");

        Schema::create('subscription_changes', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('subscription_id');
            $table->ulid('tenant_id');

            $table->string('change_type', 32);
            $table->string('status', 32);

            $table->timestampTz('requested_at');
            $table->timestampTz('effective_at')->nullable();

            $table->ulid('from_plan_version_id')->nullable();
            $table->ulid('to_plan_version_id')->nullable();

            $table->ulid('from_offering_id')->nullable();
            $table->ulid('to_offering_id')->nullable();

            $table->string('reason', 500)->nullable();

            $table->ulid('actor_user_id')->nullable();

            $table->timestampsTz();

            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->restrictOnDelete();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->foreign('from_plan_version_id')
                ->references('id')
                ->on('plan_versions')
                ->restrictOnDelete();

            $table->foreign('to_plan_version_id')
                ->references('id')
                ->on('plan_versions')
                ->restrictOnDelete();

            $table->foreign('from_offering_id')
                ->references('id')
                ->on('plan_offerings')
                ->nullOnDelete();

            $table->foreign('to_offering_id')
                ->references('id')
                ->on('plan_offerings')
                ->nullOnDelete();

            $table->foreign('actor_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index([
                'tenant_id',
                'change_type',
                'status',
            ]);

            $table->index([
                'subscription_id',
                'effective_at',
            ]);
        });

        DB::statement("
            ALTER TABLE subscription_changes
            ADD CONSTRAINT subscription_changes_type_check
            CHECK (
                change_type IN (
                    'RENEW',
                    'UPGRADE',
                    'DOWNGRADE',
                    'CANCEL',
                    'REACTIVATE'
                )
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_changes');
        Schema::dropIfExists('subscription_history');
        Schema::dropIfExists('subscription_periods');

        DB::statement(
            'DROP INDEX IF EXISTS subscriptions_tenant_current_unique'
        );

        Schema::dropIfExists('subscriptions');
    }
};
