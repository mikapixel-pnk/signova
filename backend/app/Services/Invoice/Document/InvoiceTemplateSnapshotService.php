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
        return $this->currentFor(
            $this->tenantContext->tenantId(),
            $this->businessContext->businessId()
        );
    }

    public function currentFor(
        string $tenantId,
        string $businessId
    ): array {
        $settings =
            TenantDocumentSetting::query()
                ->where(
                    'tenant_id',
                    $tenantId
                )
                ->where(
                    'business_id',
                    $businessId
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
