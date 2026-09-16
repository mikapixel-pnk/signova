<?php

namespace App\Services\Auth;

use App\Authorization\Platform\PlatformCapabilityResolver;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuthAccessContextService
{
    public function __construct(
        private readonly PlatformCapabilityResolver $platformCapabilities,
    ) {
    }

    public function build(User $user): array
    {
        $tenants = $this->activeTenants($user);

        $businessesByTenant =
            $this->activeBusinessesByTenant(
                $tenants
                    ->pluck('id')
                    ->all()
            );

        $tenantAccess =
            $tenants
                ->map(
                    function ($tenant) use (
                        $businessesByTenant
                    ): array {
                        $businesses =
                            collect(
                                $businessesByTenant[
                                    $tenant->id
                                ] ?? []
                            )
                                ->map(
                                    fn ($business) => [
                                        'id' =>
                                            $business->id,
                                        'name' =>
                                            $business->name,
                                        'is_default' =>
                                            (bool)
                                            $business->is_default,
                                        'status' =>
                                            $business->status,
                                    ]
                                )
                                ->values()
                                ->all();

                        return [
                            'id' => $tenant->id,
                            'name' => $tenant->name,
                            'slug' => $tenant->slug,
                            'lifecycle_status' =>
                                $tenant->lifecycle_status,
                            'timezone' =>
                                $tenant->timezone,
                            'locale' =>
                                $tenant->locale,
                            'businesses' =>
                                $businesses,
                        ];
                    }
                )
                ->values();

        $platformAvailable =
            $this->platformCapabilities
                ->hasPlatformAccess($user);

        $platformCapabilityCodes =
            $platformAvailable
                ? $this->platformCapabilities->codes($user)
                : [];

        $businessContextCount =
            $tenantAccess->sum(
                fn (array $tenant): int =>
                    count(
                        $tenant['businesses']
                    )
            );

        $contextCount =
            ($platformAvailable ? 1 : 0)
            + $businessContextCount;

        $defaultContext = null;

        if ($contextCount === 1) {
            if ($platformAvailable) {
                $defaultContext = [
                    'type' => 'PLATFORM',
                    'tenant_id' => null,
                ];
            } else {
                foreach ($tenantAccess as $tenant) {
                    if (
                        count(
                            $tenant['businesses']
                        ) !== 1
                    ) {
                        continue;
                    }

                    $defaultContext = [
                        'type' => 'TENANT',
                        'tenant_id' =>
                            $tenant['id'],
                        'business_id' =>
                            $tenant['businesses'][0][
                                'id'
                            ],
                    ];

                    break;
                }
            }
        }

        return [
            'access' => [
                'platform' => [
                    'available' =>
                        $platformAvailable,
                    'capabilities' =>
                        $platformCapabilityCodes,
                ],

                'tenants' =>
                    $tenantAccess->all(),
            ],

            'default_context' =>
                $defaultContext,

            'requires_context_selection' =>
                $contextCount > 1,

            'has_access' =>
                $contextCount > 0,
        ];
    }

    private function activeBusinessesByTenant(
        array $tenantIds
    ) {
        if ($tenantIds === []) {
            return collect();
        }

        return DB::table('business_profiles')
            ->whereIn(
                'tenant_id',
                $tenantIds
            )
            ->where(
                'status',
                'ACTIVE'
            )
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get([
                'id',
                'tenant_id',
                'name',
                'is_default',
                'status',
            ])
            ->groupBy('tenant_id');
    }

    private function activeTenants(User $user)
    {
        return DB::table('tenant_users as tu')
            ->join(
                'tenants as t',
                't.id',
                '=',
                'tu.tenant_id'
            )
            ->where(
                'tu.user_id',
                $user->id
            )
            ->where(
                'tu.status',
                'ACTIVE'
            )
            ->where(
                't.lifecycle_status',
                'ACTIVE'
            )
            ->orderBy('tu.joined_at')
            ->select([
                't.id',
                't.name',
                't.slug',
                't.lifecycle_status',
                't.timezone',
                't.locale',
            ])
            ->get();
    }
}
