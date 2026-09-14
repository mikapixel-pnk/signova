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

        $platformAvailable =
            $this->platformCapabilities
                ->hasPlatformAccess($user);

        $platformCapabilityCodes =
            $platformAvailable
                ? $this->platformCapabilities->codes($user)
                : [];

        $contextCount =
            ($platformAvailable ? 1 : 0)
            + $tenants->count();

        $defaultContext = null;

        if ($contextCount === 1) {
            if ($platformAvailable) {
                $defaultContext = [
                    'type' => 'PLATFORM',
                    'tenant_id' => null,
                ];
            } else {
                $defaultContext = [
                    'type' => 'TENANT',
                    'tenant_id' =>
                        $tenants->first()->id,
                ];
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
                    $tenants
                        ->map(
                            fn ($tenant) => [
                                'id' => $tenant->id,
                                'name' => $tenant->name,
                                'slug' => $tenant->slug,
                                'lifecycle_status' =>
                                    $tenant->lifecycle_status,
                                'timezone' =>
                                    $tenant->timezone,
                                'locale' =>
                                    $tenant->locale,
                            ]
                        )
                        ->values()
                        ->all(),
            ],

            'default_context' =>
                $defaultContext,

            'requires_context_selection' =>
                $contextCount > 1,

            'has_access' =>
                $contextCount > 0,
        ];
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
