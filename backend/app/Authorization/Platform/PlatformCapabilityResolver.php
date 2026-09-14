<?php

namespace App\Authorization\Platform;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlatformCapabilityResolver
{
    public function allows(
        User $user,
        string $capabilityCode
    ): bool {
        if ($user->auth_status !== 'ACTIVE') {
            return false;
        }

        $effects = $this->effectsFor(
            $user->id,
            $capabilityCode
        );

        if ($effects->contains('DENY')) {
            return false;
        }

        return $effects->contains('ALLOW');
    }

    public function codes(User $user): array
    {
        if ($user->auth_status !== 'ACTIVE') {
            return [];
        }

        $rows = $this->baseQuery($user->id)
            ->select([
                'pc.code',
                'prc.effect',
            ])
            ->get();

        return $rows
            ->groupBy('code')
            ->filter(function (Collection $rows): bool {
                $effects = $rows
                    ->pluck('effect')
                    ->map(
                        fn ($effect) =>
                            strtoupper((string) $effect)
                    );

                if ($effects->contains('DENY')) {
                    return false;
                }

                return $effects->contains('ALLOW');
            })
            ->keys()
            ->sort()
            ->values()
            ->all();
    }

    public function hasPlatformAccess(
        User $user
    ): bool {
        if ($user->auth_status !== 'ACTIVE') {
            return false;
        }

        return $this->baseQuery($user->id)
            ->exists();
    }

    private function effectsFor(
        string $userId,
        string $capabilityCode
    ): Collection {
        return $this->baseQuery($userId)
            ->where(
                'pc.code',
                $capabilityCode
            )
            ->pluck('prc.effect')
            ->map(
                fn ($effect) =>
                    strtoupper((string) $effect)
            );
    }

    private function baseQuery(string $userId)
    {
        return DB::table(
            'platform_user_roles as pur'
        )
            ->join(
                'platform_roles as pr',
                'pr.id',
                '=',
                'pur.platform_role_id'
            )
            ->join(
                'platform_role_capabilities as prc',
                'prc.platform_role_id',
                '=',
                'pr.id'
            )
            ->join(
                'platform_capabilities as pc',
                'pc.id',
                '=',
                'prc.platform_capability_id'
            )
            ->where(
                'pur.user_id',
                $userId
            )
            ->where(
                'pr.status',
                'ACTIVE'
            )
            ->where(
                'pc.is_active',
                true
            );
    }
}
