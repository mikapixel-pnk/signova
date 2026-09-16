<?php

namespace App\Tenancy;

use Illuminate\Support\Facades\DB;

class BusinessContextResolver
{
    public const RESOLVED = 'RESOLVED';

    public const ACTIVE_BUSINESS_REQUIRED =
        'ACTIVE_BUSINESS_REQUIRED';

    public const BUSINESS_SELECTION_REQUIRED =
        'BUSINESS_SELECTION_REQUIRED';

    public const BUSINESS_ACCESS_DENIED =
        'BUSINESS_ACCESS_DENIED';

    public function resolve(
        string $tenantId,
        ?string $requestedBusinessId = null
    ): array {
        $businesses = DB::table('business_profiles')
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE');

        if ($requestedBusinessId !== null) {
            $businessId = (clone $businesses)
                ->where('id', $requestedBusinessId)
                ->value('id');

            if (! $businessId) {
                return [
                    'status' =>
                        self::BUSINESS_ACCESS_DENIED,
                    'business_id' => null,
                ];
            }

            return [
                'status' => self::RESOLVED,
                'business_id' => $businessId,
            ];
        }

        $businessIds = (clone $businesses)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(2)
            ->pluck('id');

        if ($businessIds->isEmpty()) {
            return [
                'status' =>
                    self::ACTIVE_BUSINESS_REQUIRED,
                'business_id' => null,
            ];
        }

        if ($businessIds->count() > 1) {
            return [
                'status' =>
                    self::BUSINESS_SELECTION_REQUIRED,
                'business_id' => null,
            ];
        }

        return [
            'status' => self::RESOLVED,
            'business_id' => $businessIds->first(),
        ];
    }
}
