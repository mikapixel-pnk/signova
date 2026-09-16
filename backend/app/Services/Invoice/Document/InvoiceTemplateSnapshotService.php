<?php

namespace App\Services\Invoice\Document;

use App\Models\TenantDocumentSetting;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;

class InvoiceTemplateSnapshotService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
        private readonly InvoiceTemplateRegistry $registry
    ) {
    }

    public function current(): array
    {
        $settings =
            TenantDocumentSetting::query()
                ->where(
                    'tenant_id',
                    $this->tenantContext
                        ->tenantId()
                )
                ->where(
                    'business_id',
                    $this->businessContext
                        ->businessId()
                )
                ->first();

        $resolved =
            $this->registry->resolve(
                $settings
                    ?->invoice_template_key,
                $settings
                    ?->invoice_palette_key
            );

        return [
            'invoice_template_key' =>
                $resolved[
                    'template'
                ]['key'],

            'invoice_palette_key' =>
                $resolved[
                    'palette'
                ]['key'],

            'invoice_template_version' =>
                $resolved[
                    'template'
                ]['version'],
        ];
    }
}
