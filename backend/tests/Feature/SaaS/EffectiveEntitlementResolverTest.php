<?php

namespace Tests\Feature\SaaS;

use App\Authorization\EffectiveEntitlementResolver;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EffectiveEntitlementResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_resolves_effective_entitlement(): void
    {
        $context = $this->createContext();

        $result = $this->resolver()->resolve(
            $context['tenant_id'],
            $context['feature_code'],
            $context['at']
        );

        $this->assertSame(
            EffectiveEntitlementResolver::RESOLVED,
            $result['status']
        );

        $this->assertTrue($result['enabled']);
        $this->assertSame(
            10,
            $result['limit_value']
        );
        $this->assertSame(
            'BASE',
            $result['value']
        );
        $this->assertSame(
            ['mode' => 'BASE'],
            $result['config']
        );
        $this->assertSame(
            'SNAPSHOT',
            $result['source']
        );
    }

    public function test_subscription_override_can_grant_addon_without_snapshot(): void
    {
        $context = $this->createContext([
            'with_snapshot' => false,
        ]);

        $this->insertOverride(
            $context,
            [
                'enabled' => true,
                'value' => 'ADD_ON',
            ],
            'SUBSCRIPTION'
        );

        $result = $this->resolver()->resolve(
            $context['tenant_id'],
            $context['feature_code'],
            $context['at']
        );

        $this->assertSame(
            EffectiveEntitlementResolver::RESOLVED,
            $result['status']
        );

        $this->assertTrue($result['enabled']);
        $this->assertSame(
            'ADD_ON',
            $result['value']
        );
        $this->assertNull(
            $result['snapshot_id']
        );
        $this->assertSame(
            'OVERRIDE',
            $result['source']
        );
    }

    public function test_partial_period_override_preserves_snapshot_values(): void
    {
        $context = $this->createContext();

        $this->insertOverride(
            $context,
            [
                'limit_value' => 25,
            ],
            'PERIOD'
        );

        $result = $this->resolver()->resolve(
            $context['tenant_id'],
            $context['feature_code'],
            $context['at']
        );

        $this->assertTrue($result['enabled']);

        $this->assertSame(
            25,
            $result['limit_value']
        );

        $this->assertSame(
            'BASE',
            $result['value']
        );

        $this->assertSame(
            ['mode' => 'BASE'],
            $result['config']
        );

        $this->assertSame(
            'OVERRIDE',
            $result['source']
        );
    }

    public function test_multiple_active_overrides_fail_closed(): void
    {
        $context = $this->createContext();

        $this->insertOverride(
            $context,
            ['enabled' => true],
            'TENANT'
        );

        $this->insertOverride(
            $context,
            ['limit_value' => 99],
            'SUBSCRIPTION'
        );

        $result = $this->resolver()->resolve(
            $context['tenant_id'],
            $context['feature_code'],
            $context['at']
        );

        $this->assertSame(
            EffectiveEntitlementResolver::AMBIGUOUS_OVERRIDE,
            $result['status']
        );

        $this->assertFalse(
            $result['enabled']
        );

        $this->assertFalse(
            $this->resolver()->isEnabled(
                $context['tenant_id'],
                $context['feature_code'],
                $context['at']
            )
        );
    }

    public function test_future_override_is_ignored(): void
    {
        $context = $this->createContext();

        $this->insertOverride(
            $context,
            [
                'enabled' => false,
                'limit_value' => 1,
            ],
            'SUBSCRIPTION',
            $context['at']->addDay()
        );

        $result = $this->resolver()->resolve(
            $context['tenant_id'],
            $context['feature_code'],
            $context['at']
        );

        $this->assertSame(
            EffectiveEntitlementResolver::RESOLVED,
            $result['status']
        );

        $this->assertTrue($result['enabled']);
        $this->assertSame(
            10,
            $result['limit_value']
        );
        $this->assertNull(
            $result['override_id']
        );
    }

    public function test_inactive_tenant_fails_closed(): void
    {
        $context = $this->createContext([
            'tenant_lifecycle' => 'SUSPENDED',
        ]);

        $result = $this->resolver()->resolve(
            $context['tenant_id'],
            $context['feature_code'],
            $context['at']
        );

        $this->assertSame(
            EffectiveEntitlementResolver::TENANT_INACTIVE,
            $result['status']
        );

        $this->assertFalse(
            $result['enabled']
        );
    }

    public function test_active_subscription_is_end_exclusive(): void
    {
        $at = CarbonImmutable::parse(
            '2026-09-20T00:00:00+00:00'
        );

        $context = $this->createContext([
            'at' => $at,
            'ends_at' => $at,
        ]);

        $result = $this->resolver()->resolve(
            $context['tenant_id'],
            $context['feature_code'],
            $at
        );

        $this->assertSame(
            EffectiveEntitlementResolver::SUBSCRIPTION_INACTIVE,
            $result['status']
        );

        $this->assertFalse(
            $result['enabled']
        );
    }

    public function test_trial_uses_authoritative_trial_end(): void
    {
        $at = CarbonImmutable::parse(
            '2026-09-20T00:00:00+00:00'
        );

        $trialEnd = $at->addDays(7);

        $context = $this->createContext([
            'at' => $at,
            'subscription_status' => 'TRIAL',
            'trial_ends_at' => $trialEnd,
            'ends_at' => $at->addDays(30),
        ]);

        $duringTrial = $this->resolver()->resolve(
            $context['tenant_id'],
            $context['feature_code'],
            $trialEnd->subSecond()
        );

        $this->assertSame(
            EffectiveEntitlementResolver::RESOLVED,
            $duringTrial['status']
        );

        $this->assertTrue(
            $duringTrial['enabled']
        );

        $atTrialEnd = $this->resolver()->resolve(
            $context['tenant_id'],
            $context['feature_code'],
            $trialEnd
        );

        $this->assertSame(
            EffectiveEntitlementResolver::SUBSCRIPTION_INACTIVE,
            $atTrialEnd['status']
        );

        $this->assertFalse(
            $atTrialEnd['enabled']
        );
    }

    public function test_inactive_feature_fails_closed(): void
    {
        $context = $this->createContext();

        DB::table('features')
            ->where(
                'id',
                $context['feature_id']
            )
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        $result = $this->resolver()->resolve(
            $context['tenant_id'],
            $context['feature_code'],
            $context['at']
        );

        $this->assertSame(
            EffectiveEntitlementResolver::FEATURE_UNAVAILABLE,
            $result['status']
        );

        $this->assertFalse(
            $result['enabled']
        );
    }

    private function resolver(): EffectiveEntitlementResolver
    {
        return app(
            EffectiveEntitlementResolver::class
        );
    }

    private function createContext(
        array $options = []
    ): array {
        $at = $options['at']
            ?? CarbonImmutable::parse(
                '2026-09-20T00:00:00+00:00'
            );

        $startsAt = $options['starts_at']
            ?? $at->subDay();

        $endsAt = array_key_exists(
            'ends_at',
            $options
        )
            ? $options['ends_at']
            : $at->addDays(30);

        $subscriptionStatus =
            $options['subscription_status']
            ?? 'ACTIVE';

        $trialEndsAt =
            $options['trial_ends_at']
            ?? (
                $subscriptionStatus === 'TRIAL'
                    ? $at->addDays(7)
                    : null
            );

        $graceEndsAt =
            $options['grace_ends_at']
            ?? (
                $subscriptionStatus === 'GRACE'
                    ? $at->addDays(7)
                    : null
            );

        $tenantId = (string) Str::ulid();
        $planId = (string) Str::ulid();
        $planVersionId = (string) Str::ulid();
        $featureId = (string) Str::ulid();
        $subscriptionId = (string) Str::ulid();
        $periodId = (string) Str::ulid();

        $featureCode =
            'test.feature.'
            . strtolower(Str::random(10));

        $now = now();

        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Resolver Test Tenant',
            'code' => null,
            'slug' =>
                'resolver-test-'
                . strtolower(Str::random(12)),
            'lifecycle_status' =>
                $options['tenant_lifecycle']
                ?? 'ACTIVE',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'primary_owner_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('plans')->insert([
            'id' => $planId,
            'code' =>
                'TEST_'
                . strtoupper(Str::random(10)),
            'name' => 'Resolver Test Plan',
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
            'effective_from' => $startsAt,
            'effective_to' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('features')->insert([
            'id' => $featureId,
            'code' => $featureCode,
            'name' => 'Resolver Test Feature',
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
            'status' => $subscriptionStatus,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'trial_ends_at' => $trialEndsAt,
            'grace_ends_at' => $graceEndsAt,
            'auto_renew' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        /*
         * Period exists even when the feature has no snapshot,
         * allowing future activation/rebuild to remain explicit.
         */
        $periodEnd = $endsAt
            ?? $trialEndsAt
            ?? $graceEndsAt
            ?? $at->addDays(30);

        DB::table('subscription_periods')->insert([
            'id' => $periodId,
            'subscription_id' => $subscriptionId,
            'tenant_id' => $tenantId,
            'plan_version_id' => $planVersionId,
            'offering_id' => null,
            'status' => 'ACTIVE',
            'period_start' => $startsAt,
            'period_end' => $periodEnd,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (
            $options['with_snapshot']
            ?? true
        ) {
            DB::table(
                'entitlement_snapshots'
            )->insert([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'subscription_id' =>
                    $subscriptionId,
                'subscription_period_id' =>
                    $periodId,
                'feature_id' => $featureId,
                'enabled' => true,
                'limit_value' => 10,
                'value' => 'BASE',
                'config' => json_encode([
                    'mode' => 'BASE',
                ]),
                'source' => 'PLAN_VERSION',
                'effective_from' => $startsAt,
                'effective_to' =>
                    $subscriptionStatus === 'TRIAL'
                        ? $trialEndsAt
                        : $endsAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return [
            'at' => $at,
            'tenant_id' => $tenantId,
            'plan_id' => $planId,
            'plan_version_id' => $planVersionId,
            'feature_id' => $featureId,
            'feature_code' => $featureCode,
            'subscription_id' => $subscriptionId,
            'period_id' => $periodId,
        ];
    }

    private function insertOverride(
        array $context,
        array $values,
        string $scope,
        ?CarbonInterface $effectiveFrom = null,
        ?CarbonInterface $effectiveTo = null
    ): string {
        $overrideId = (string) Str::ulid();

        $subscriptionId = match ($scope) {
            'TENANT' => null,
            'SUBSCRIPTION',
            'PERIOD' =>
                $context['subscription_id'],
            default =>
                throw new \InvalidArgumentException(
                    'Unknown override scope.'
                ),
        };

        $periodId = $scope === 'PERIOD'
            ? $context['period_id']
            : null;

        $config = $values['config'] ?? null;

        if (is_array($config)) {
            $config = json_encode($config);
        }

        DB::table('entitlement_overrides')->insert([
            'id' => $overrideId,
            'tenant_id' =>
                $context['tenant_id'],
            'subscription_id' =>
                $subscriptionId,
            'subscription_period_id' =>
                $periodId,
            'feature_id' =>
                $context['feature_id'],
            'enabled' =>
                $values['enabled'] ?? null,
            'limit_value' =>
                $values['limit_value'] ?? null,
            'value' =>
                $values['value'] ?? null,
            'config' => $config,
            'source' => 'MANUAL',
            'reason' =>
                'Effective entitlement resolver test',
            'actor_user_id' => null,
            'effective_from' =>
                $effectiveFrom
                ?? $context['at']->subHour(),
            'effective_to' => $effectiveTo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $overrideId;
    }
}
