<?php

namespace App\Tenancy;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenantContextResolver
{
    public function resolve(
        User $user,
        ?string $requestedTenantId = null
    ): ?string {
        $memberships = DB::table('tenant_users')
            ->where('user_id', $user->id)
            ->where('status', 'ACTIVE');

        if ($requestedTenantId !== null) {
            return (clone $memberships)
                ->where('tenant_id', $requestedTenantId)
                ->value('tenant_id');
        }

        return (clone $memberships)
            ->orderBy('joined_at')
            ->value('tenant_id');
    }
}
