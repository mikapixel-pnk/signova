<?php

namespace App\Tenancy;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenantContextResolver
{
    public const RESOLVED = 'RESOLVED';

    public const ACTIVE_TENANT_REQUIRED =
        'ACTIVE_TENANT_REQUIRED';

    public const TENANT_SELECTION_REQUIRED =
        'TENANT_SELECTION_REQUIRED';

    public const TENANT_ACCESS_DENIED =
        'TENANT_ACCESS_DENIED';

    public function resolve(
        User $user,
        ?string $requestedTenantId = null
    ): array {
        $memberships = DB::table('tenant_users as tu')
            ->join(
                'tenants as t',
                't.id',
                '=',
                'tu.tenant_id'
            )
            ->where('tu.user_id', $user->id)
            ->where('tu.status', 'ACTIVE')
            ->where('t.lifecycle_status', 'ACTIVE');

        if ($requestedTenantId !== null) {
            $tenantId = (clone $memberships)
                ->where(
                    'tu.tenant_id',
                    $requestedTenantId
                )
                ->value('tu.tenant_id');

            if (! $tenantId) {
                return [
                    'status' =>
                        self::TENANT_ACCESS_DENIED,
                    'tenant_id' => null,
                ];
            }

            return [
                'status' => self::RESOLVED,
                'tenant_id' => $tenantId,
            ];
        }

        $tenantIds = (clone $memberships)
            ->orderBy('tu.joined_at')
            ->limit(2)
            ->pluck('tu.tenant_id');

        if ($tenantIds->isEmpty()) {
            return [
                'status' =>
                    self::ACTIVE_TENANT_REQUIRED,
                'tenant_id' => null,
            ];
        }

        if ($tenantIds->count() > 1) {
            return [
                'status' =>
                    self::TENANT_SELECTION_REQUIRED,
                'tenant_id' => null,
            ];
        }

        return [
            'status' => self::RESOLVED,
            'tenant_id' => $tenantIds->first(),
        ];
    }
}
