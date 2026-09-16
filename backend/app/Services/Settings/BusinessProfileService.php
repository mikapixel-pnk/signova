<?php

namespace App\Services\Settings;

use App\Models\BusinessProfile;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;

class BusinessProfileService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext
    ) {
    }

    public function get(): BusinessProfile
    {
        return BusinessProfile::query()
            ->where(
                'tenant_id',
                $this->tenantContext->tenantId()
            )
            ->where(
                'id',
                $this->businessContext->businessId()
            )
            ->firstOrFail();
    }

    public function update(
        array $data
    ): BusinessProfile {
        $business = $this->get();

        $business->fill($data);
        $business->save();

        return $business->fresh();
    }
}
