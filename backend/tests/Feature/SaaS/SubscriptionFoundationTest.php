<?php

namespace Tests\Feature\SaaS;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_foundation_schema_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('subscriptions')
        );

        $this->assertTrue(
            Schema::hasTable('subscription_periods')
        );

        $this->assertTrue(
            Schema::hasTable('subscription_history')
        );

        $this->assertTrue(
            Schema::hasTable('subscription_changes')
        );

        $this->assertTrue(
            Schema::hasColumns('subscriptions', [
                'id',
                'tenant_id',
                'plan_version_id',
                'offering_id',
                'source_order_id',
                'previous_subscription_id',
                'status',
                'starts_at',
                'ends_at',
                'trial_starts_at',
                'trial_ends_at',
                'grace_ends_at',
                'cancelled_at',
                'suspended_at',
                'suspension_reason',
                'auto_renew',
                'renewal_anchor',
                'created_at',
                'updated_at',
            ])
        );
    }

    public function test_only_one_current_subscription_is_allowed_per_tenant(): void
    {
        [$tenantId, $planVersionId] =
            $this->createTenantAndPlanVersion();

        DB::table('subscriptions')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'plan_version_id' => $planVersionId,
            'offering_id' => null,
            'previous_subscription_id' => null,
            'status' => 'ACTIVE',
            'auto_renew' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(
            QueryException::class
        );

        DB::table('subscriptions')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'plan_version_id' => $planVersionId,
            'offering_id' => null,
            'previous_subscription_id' => null,
            'status' => 'GRACE',
            'auto_renew' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_historical_subscriptions_can_coexist(): void
    {
        [$tenantId, $planVersionId] =
            $this->createTenantAndPlanVersion();

        foreach (['EXPIRED', 'CANCELLED'] as $status) {
            DB::table('subscriptions')->insert([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'plan_version_id' => $planVersionId,
                'offering_id' => null,
                'previous_subscription_id' => null,
                'status' => $status,
                'auto_renew' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertSame(
            2,
            DB::table('subscriptions')
                ->where('tenant_id', $tenantId)
                ->count()
        );
    }

    public function test_subscription_history_rejects_unknown_status(): void
    {
        [$tenantId, $planVersionId] =
            $this->createTenantAndPlanVersion();

        $subscriptionId = (string) Str::ulid();
        $now = now();

        DB::table('subscriptions')->insert([
            'id' => $subscriptionId,
            'tenant_id' => $tenantId,
            'plan_version_id' => $planVersionId,
            'offering_id' => null,
            'source_order_id' => null,
            'previous_subscription_id' => null,
            'status' => 'PENDING',
            'auto_renew' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->expectException(
            QueryException::class
        );

        DB::table('subscription_history')->insert([
            'id' => (string) Str::ulid(),
            'subscription_id' => $subscriptionId,
            'tenant_id' => $tenantId,
            'from_status' => null,
            'to_status' => 'UNKNOWN',
            'source' => 'TEST',
            'reason' => null,
            'actor_user_id' => null,
            'metadata' => null,
            'occurred_at' => $now,
        ]);
    }

    public function test_subscription_history_blocks_parent_hard_delete(): void
    {
        [$tenantId, $planVersionId] =
            $this->createTenantAndPlanVersion();

        $subscriptionId = (string) Str::ulid();
        $now = now();

        DB::table('subscriptions')->insert([
            'id' => $subscriptionId,
            'tenant_id' => $tenantId,
            'plan_version_id' => $planVersionId,
            'offering_id' => null,
            'source_order_id' => null,
            'previous_subscription_id' => null,
            'status' => 'ACTIVE',
            'auto_renew' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('subscription_history')->insert([
            'id' => (string) Str::ulid(),
            'subscription_id' => $subscriptionId,
            'tenant_id' => $tenantId,
            'from_status' => 'PENDING',
            'to_status' => 'ACTIVE',
            'source' => 'TEST',
            'reason' => 'Activation test',
            'actor_user_id' => null,
            'metadata' => null,
            'occurred_at' => $now,
        ]);

        $this->expectException(
            QueryException::class
        );

        DB::table('subscriptions')
            ->where('id', $subscriptionId)
            ->delete();
    }

    private function createTenantAndPlanVersion(): array
    {
        $tenantId = (string) Str::ulid();
        $planId = (string) Str::ulid();
        $planVersionId = (string) Str::ulid();

        $now = now();

        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Tenant Subscription Test',
            'code' => null,
            'slug' => 'tenant-subscription-' . strtolower(
                Str::random(8)
            ),
            'lifecycle_status' => 'ACTIVE',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'primary_owner_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('plans')->insert([
            'id' => $planId,
            'code' => 'TEST-' . strtoupper(
                Str::random(8)
            ),
            'name' => 'Test Plan',
            'description' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('plan_versions')->insert([
            'id' => $planVersionId,
            'plan_id' => $planId,
            'version_no' => 1,
            'status' => 'PUBLISHED',
            'effective_from' => $now,
            'effective_to' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            $tenantId,
            $planVersionId,
        ];
    }
}
