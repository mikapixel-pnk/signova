<?php

namespace App\Authorization;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class EffectiveEntitlementResolver
{
    public const RESOLVED = 'RESOLVED';

    public const TENANT_INACTIVE = 'TENANT_INACTIVE';

    public const FEATURE_UNAVAILABLE = 'FEATURE_UNAVAILABLE';

    public const SUBSCRIPTION_INACTIVE = 'SUBSCRIPTION_INACTIVE';

    public const AMBIGUOUS_SUBSCRIPTION =
        'AMBIGUOUS_SUBSCRIPTION';

    public const AMBIGUOUS_SNAPSHOT =
        'AMBIGUOUS_SNAPSHOT';

    public const AMBIGUOUS_OVERRIDE =
        'AMBIGUOUS_OVERRIDE';

    public function resolve(
        string $tenantId,
        string $featureCode,
        ?CarbonInterface $at = null
    ): array {
        $at ??= CarbonImmutable::now();

        $tenant = DB::table('tenants')
            ->where('id', $tenantId)
            ->first([
                'id',
                'lifecycle_status',
            ]);

        if (
            ! $tenant
            || $tenant->lifecycle_status !== 'ACTIVE'
        ) {
            return $this->denied(
                self::TENANT_INACTIVE,
                $tenantId,
                $featureCode
            );
        }

        $feature = DB::table('features')
            ->where('code', $featureCode)
            ->where('is_active', true)
            ->first([
                'id',
                'code',
            ]);

        if (! $feature) {
            return $this->denied(
                self::FEATURE_UNAVAILABLE,
                $tenantId,
                $featureCode
            );
        }

        $subscriptions = DB::table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', $at)
            ->where(
                function ($query) use ($at): void {
                    $query
                        ->where(
                            function ($scope) use ($at): void {
                                $scope
                                    ->where('status', 'TRIAL')
                                    ->whereNotNull(
                                        'trial_ends_at'
                                    )
                                    ->where(
                                        'trial_ends_at',
                                        '>',
                                        $at
                                    );
                            }
                        )
                        ->orWhere(
                            function ($scope) use ($at): void {
                                $scope
                                    ->where('status', 'ACTIVE')
                                    ->whereNotNull('ends_at')
                                    ->where(
                                        'ends_at',
                                        '>',
                                        $at
                                    );
                            }
                        )
                        ->orWhere(
                            function ($scope) use ($at): void {
                                $scope
                                    ->where('status', 'GRACE')
                                    ->whereNotNull(
                                        'grace_ends_at'
                                    )
                                    ->where(
                                        'grace_ends_at',
                                        '>',
                                        $at
                                    );
                            }
                        );
                }
            )
            ->limit(2)
            ->get([
                'id',
                'status',
                'starts_at',
                'ends_at',
                'trial_ends_at',
                'grace_ends_at',
            ]);

        if ($subscriptions->isEmpty()) {
            return $this->denied(
                self::SUBSCRIPTION_INACTIVE,
                $tenantId,
                $featureCode,
                $feature->id
            );
        }

        if ($subscriptions->count() > 1) {
            return $this->denied(
                self::AMBIGUOUS_SUBSCRIPTION,
                $tenantId,
                $featureCode,
                $feature->id
            );
        }

        $subscription = $subscriptions->first();

        /*
         * Snapshot is selected by its own effective window.
         * This intentionally provides a request-time date guard.
         */
        $snapshots = DB::table('entitlement_snapshots')
            ->where('tenant_id', $tenantId)
            ->where(
                'subscription_id',
                $subscription->id
            )
            ->where('feature_id', $feature->id)
            ->where('effective_from', '<=', $at)
            ->where(function ($query) use ($at): void {
                $query
                    ->whereNull('effective_to')
                    ->orWhere(
                        'effective_to',
                        '>',
                        $at
                    );
            })
            ->limit(2)
            ->get([
                'id',
                'subscription_period_id',
                'enabled',
                'limit_value',
                'value',
                'config',
                'source',
            ]);

        if ($snapshots->count() > 1) {
            return $this->denied(
                self::AMBIGUOUS_SNAPSHOT,
                $tenantId,
                $featureCode,
                $feature->id,
                $subscription->id
            );
        }

        $snapshot = $snapshots->first();

        /*
         * No snapshot means default deny.
         *
         * A tenant/subscription override may still explicitly grant
         * an add-on feature that is not present in the base snapshot.
         *
         * Period-scoped overrides are only considered when an active
         * snapshot supplies the exact period. We do not guess a period.
         */
        $enabled = $snapshot
            ? (bool) $snapshot->enabled
            : false;

        $limitValue = $snapshot?->limit_value !== null
            ? (int) $snapshot->limit_value
            : null;

        $value = $snapshot?->value;

        $config = $this->decodeJson(
            $snapshot?->config
        );

        $periodId =
            $snapshot?->subscription_period_id;

        $overrides = DB::table('entitlement_overrides')
            ->where('tenant_id', $tenantId)
            ->where('feature_id', $feature->id)
            ->where('effective_from', '<=', $at)
            ->where(function ($query) use ($at): void {
                $query
                    ->whereNull('effective_to')
                    ->orWhere(
                        'effective_to',
                        '>',
                        $at
                    );
            })
            ->where(
                function ($query) use (
                    $subscription,
                    $periodId
                ): void {
                    /*
                     * Tenant-wide override.
                     */
                    $query->where(
                        function ($scope): void {
                            $scope
                                ->whereNull(
                                    'subscription_id'
                                )
                                ->whereNull(
                                    'subscription_period_id'
                                );
                        }
                    );

                    /*
                     * Subscription-scoped override.
                     */
                    $query->orWhere(
                        function ($scope) use (
                            $subscription
                        ): void {
                            $scope
                                ->where(
                                    'subscription_id',
                                    $subscription->id
                                )
                                ->whereNull(
                                    'subscription_period_id'
                                );
                        }
                    );

                    /*
                     * Exact period-scoped override.
                     */
                    if ($periodId !== null) {
                        $query->orWhere(
                            function ($scope) use (
                                $subscription,
                                $periodId
                            ): void {
                                $scope
                                    ->where(
                                        'subscription_id',
                                        $subscription->id
                                    )
                                    ->where(
                                        'subscription_period_id',
                                        $periodId
                                    );
                            }
                        );
                    }
                }
            )
            ->limit(2)
            ->get([
                'id',
                'enabled',
                'limit_value',
                'value',
                'config',
                'source',
            ]);

        /*
         * Override precedence is intentionally NOT guessed.
         * Until an ADR defines stacking/precedence, multiple active
         * overrides for the same effective feature fail closed.
         */
        if ($overrides->count() > 1) {
            return $this->denied(
                self::AMBIGUOUS_OVERRIDE,
                $tenantId,
                $featureCode,
                $feature->id,
                $subscription->id,
                $periodId
            );
        }

        $override = $overrides->first();

        if ($override) {
            if ($override->enabled !== null) {
                $enabled =
                    (bool) $override->enabled;
            }

            if ($override->limit_value !== null) {
                $limitValue =
                    (int) $override->limit_value;
            }

            if ($override->value !== null) {
                $value = $override->value;
            }

            if ($override->config !== null) {
                $config = $this->decodeJson(
                    $override->config
                );
            }
        }

        return [
            'status' => self::RESOLVED,
            'tenant_id' => $tenantId,

            'subscription_id' =>
                $subscription->id,

            'subscription_period_id' =>
                $periodId,

            'feature_id' => $feature->id,
            'feature_code' => $feature->code,

            'enabled' => $enabled,
            'limit_value' => $limitValue,
            'value' => $value,
            'config' => $config,

            'snapshot_id' =>
                $snapshot?->id,

            'override_id' =>
                $override?->id,

            'source' => $override
                ? 'OVERRIDE'
                : ($snapshot
                    ? 'SNAPSHOT'
                    : 'DEFAULT_DENY'),
        ];
    }

    public function isEnabled(
        string $tenantId,
        string $featureCode,
        ?CarbonInterface $at = null
    ): bool {
        $result = $this->resolve(
            $tenantId,
            $featureCode,
            $at
        );

        return
            $result['status'] === self::RESOLVED
            && $result['enabled'] === true;
    }

    private function decodeJson(
        mixed $value
    ): mixed {
        if (
            $value === null
            || is_array($value)
        ) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode(
            $value,
            true
        );

        return json_last_error()
            === JSON_ERROR_NONE
                ? $decoded
                : null;
    }

    private function denied(
        string $status,
        string $tenantId,
        string $featureCode,
        ?string $featureId = null,
        ?string $subscriptionId = null,
        ?string $periodId = null
    ): array {
        return [
            'status' => $status,
            'tenant_id' => $tenantId,

            'subscription_id' =>
                $subscriptionId,

            'subscription_period_id' =>
                $periodId,

            'feature_id' => $featureId,
            'feature_code' => $featureCode,

            'enabled' => false,
            'limit_value' => null,
            'value' => null,
            'config' => null,

            'snapshot_id' => null,
            'override_id' => null,

            'source' => 'FAIL_CLOSED',
        ];
    }
}
