<?php

namespace App\Authorization;

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EffectiveCapabilityResolver
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function allows(User $user, string $capabilityCode): bool
    {
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
                'c.code',
                'rc.effect',
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

    private function effectsFor(
        string $userId,
        string $capabilityCode
    ): Collection {
        return $this->baseQuery($userId)
            ->where('c.code', $capabilityCode)
            ->pluck('rc.effect')
            ->map(
                fn ($effect) =>
                    strtoupper((string) $effect)
            );
    }

    private function baseQuery(string $userId)
    {
        $tenantId = $this->tenantContext->tenantId();

        return DB::table('tenant_user_roles as tur')
            ->join('tenant_users as tu', function ($join) {
                $join
                    ->on(
                        'tu.tenant_id',
                        '=',
                        'tur.tenant_id'
                    )
                    ->on(
                        'tu.user_id',
                        '=',
                        'tur.user_id'
                    );
            })
            ->join('roles as r', function ($join) {
                $join
                    ->on(
                        'r.id',
                        '=',
                        'tur.role_id'
                    )
                    ->on(
                        'r.tenant_id',
                        '=',
                        'tur.tenant_id'
                    );
            })
            ->join(
                'role_capabilities as rc',
                'rc.role_id',
                '=',
                'r.id'
            )
            ->join(
                'capabilities as c',
                'c.id',
                '=',
                'rc.capability_id'
            )
            ->where('tur.tenant_id', $tenantId)
            ->where('tur.user_id', $userId)
            ->where('tu.status', 'ACTIVE')
            ->where('r.status', 'ACTIVE')
            ->where('c.is_active', true);
    }
}
