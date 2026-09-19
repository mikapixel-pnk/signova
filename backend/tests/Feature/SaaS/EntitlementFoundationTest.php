<?php

namespace Tests\Feature\SaaS;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class EntitlementFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_entitlement_foundation_schema_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('entitlement_snapshots')
        );

        $this->assertTrue(
            Schema::hasTable('entitlement_overrides')
        );

        $this->assertTrue(
            Schema::hasColumns('entitlement_snapshots', [
                'id',
                'tenant_id',
                'subscription_id',
                'subscription_period_id',
                'feature_id',
                'enabled',
                'limit_value',
                'value',
                'config',
                'source',
                'effective_from',
                'effective_to',
                'created_at',
                'updated_at',
            ])
        );

        $this->assertTrue(
            Schema::hasColumns('entitlement_overrides', [
                'id',
                'tenant_id',
                'subscription_id',
                'subscription_period_id',
                'feature_id',
                'enabled',
                'limit_value',
                'value',
                'config',
                'source',
                'reason',
                'actor_user_id',
                'effective_from',
                'effective_to',
                'created_at',
                'updated_at',
            ])
        );
    }

    public function test_snapshot_is_unique_per_period_and_feature(): void
    {
        $context = $this->createEntitlementContext();

        $this->insertSnapshot($context);

        $this->expectException(
            QueryException::class
        );

        $this->insertSnapshot($context);
    }

    public function test_snapshot_rejects_invalid_effective_range(): void
    {
        $context = $this->createEntitlementContext();

        $this->expectException(
            QueryException::class
        );

        DB::table('entitlement_snapshots')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $context['tenant_id'],
            'subscription_id' => $context['subscription_id'],
            'subscription_period_id' => $context['period_id'],
            'feature_id' => $context['feature_id'],
            'enabled' => true,
            'limit_value' => null,
            'value' => null,
            'config' => null,
            'source' => 'PLAN_VERSION',
            'effective_from' => now(),
            'effective_to' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_override_requires_an_actual_override_value(): void
    {
        $context = $this->createEntitlementContext();

        $this->expectException(
            QueryException::class
        );

        DB::table('entitlement_overrides')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $context['tenant_id'],
            'subscription_id' => null,
            'subscription_period_id' => null,
            'feature_id' => $context['feature_id'],
            'enabled' => null,
            'limit_value' => null,
            'value' => null,
            'config' => null,
            'source' => 'MANUAL',
            'reason' => 'No-op override test',
            'actor_user_id' => null,
            'effective_from' => now(),
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_period_scoped_override_requires_subscription_scope(): void
    {
        $context = $this->createEntitlementContext();

        $this->expectException(
            QueryException::class
        );

        DB::table('entitlement_overrides')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $context['tenant_id'],
            'subscription_id' => null,
            'subscription_period_id' => $context['period_id'],
            'feature_id' => $context['feature_id'],
            'enabled' => true,
            'limit_value' => null,
            'value' => null,
            'config' => null,
            'source' => 'MANUAL',
            'reason' => 'Invalid scope test',
            'actor_user_id' => null,
            'effective_from' => now(),
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_snapshot_rejects_cross_tenant_period_scope(): void
    {
        $context = $this->createEntitlementContext();

        $otherTenantId = $this->createTenant(
            'Snapshot Scope'
        );

        $this->expectException(
            QueryException::class
        );

        DB::table('entitlement_snapshots')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $otherTenantId,
            'subscription_id' => $context['subscription_id'],
            'subscription_period_id' => $context['period_id'],
            'feature_id' => $context['feature_id'],
            'enabled' => true,
            'limit_value' => null,
            'value' => null,
            'config' => null,
            'source' => 'PLAN_VERSION',
            'effective_from' => $context['period_start'],
            'effective_to' => $context['period_end'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_override_rejects_cross_tenant_subscription_scope(): void
    {
        $context = $this->createEntitlementContext();

        $otherTenantId = $this->createTenant(
            'Override Scope'
        );

        $this->expectException(
            QueryException::class
        );

        DB::table('entitlement_overrides')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $otherTenantId,
            'subscription_id' => $context['subscription_id'],
            'subscription_period_id' => null,
            'feature_id' => $context['feature_id'],
            'enabled' => true,
            'limit_value' => null,
            'value' => null,
            'config' => null,
            'source' => 'MANUAL',
            'reason' => 'Cross tenant scope test',
            'actor_user_id' => null,
            'effective_from' => now(),
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_snapshot_blocks_parent_period_hard_delete(): void
    {
        $context = $this->createEntitlementContext();

        $this->insertSnapshot($context);

        $this->expectException(
            QueryException::class
        );

        DB::table('subscription_periods')
            ->where('id', $context['period_id'])
            ->delete();
    }

    private function insertSnapshot(array $context): void
    {
        DB::table('entitlement_snapshots')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $context['tenant_id'],
            'subscription_id' => $context['subscription_id'],
            'subscription_period_id' => $context['period_id'],
            'feature_id' => $context['feature_id'],
            'enabled' => true,
            'limit_value' => 10,
            'value' => null,
            'config' => null,
            'source' => 'PLAN_VERSION',
            'effective_from' => $context['period_start'],
            'effective_to' => $context['period_end'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTenant(string $label): string
    {
        $tenantId = (string) Str::ulid();
        $now = now();

        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Tenant ' . $label,
            'code' => null,
            'slug' => 'tenant-' . strtolower(
                Str::random(12)
            ),
            'lifecycle_status' => 'ACTIVE',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'primary_owner_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $tenantId;
    }

    private function createEntitlementContext(): array
    {
        $now = now();

        $tenantId = (string) Str::ulid();
        $planId = (string) Str::ulid();
        $planVersionId = (string) Str::ulid();
        $subscriptionId = (string) Str::ulid();
        $periodId = (string) Str::ulid();
        $featureId = (string) Str::ulid();

        $periodStart = $now->copy();
        $periodEnd = $now->copy()->addMonth();

        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Tenant Entitlement Test',
            'code' => null,
            'slug' => 'tenant-entitlement-' . strtolower(
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
            'code' => 'ENT-' . strtoupper(
                Str::random(8)
            ),
            'name' => 'Entitlement Test Plan',
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

        DB::table('features')->insert([
            'id' => $featureId,
            'code' => 'entitlement-test-' . strtolower(
                Str::random(8)
            ),
            'name' => 'Entitlement Test Feature',
            'description' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('subscriptions')->insert([
            'id' => $subscriptionId,
            'tenant_id' => $tenantId,
            'plan_version_id' => $planVersionId,
            'offering_id' => null,
            'source_order_id' => null,
            'previous_subscription_id' => null,
            'status' => 'ACTIVE',
            'starts_at' => $periodStart,
            'ends_at' => $periodEnd,
            'auto_renew' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('subscription_periods')->insert([
            'id' => $periodId,
            'subscription_id' => $subscriptionId,
            'tenant_id' => $tenantId,
            'plan_version_id' => $planVersionId,
            'offering_id' => null,
            'status' => 'ACTIVE',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'tenant_id' => $tenantId,
            'subscription_id' => $subscriptionId,
            'period_id' => $periodId,
            'feature_id' => $featureId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }
}
