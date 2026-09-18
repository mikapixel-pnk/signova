<?php

namespace App\Services\Invoice\Document;

use App\Models\BusinessProfile;
use App\Models\FileAsset;
use App\Models\Invoice;
use App\Models\TenantDocumentSetting;
use App\Services\File\FileService;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;

class InvoiceBrandingSnapshotService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
        private readonly InvoiceBrandingResolver $brandingResolver,
        private readonly FileService $fileService
    ) {
    }

    public function capture(): array
    {
        $tenantId =
            $this->tenantContext->tenantId();

        $businessId =
            $this->businessContext->businessId();

        $business =
            BusinessProfile::query()
                ->where(
                    'tenant_id',
                    $tenantId
                )
                ->where(
                    'id',
                    $businessId
                )
                ->firstOrFail();

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

        $branding =
            $this->brandingResolver
                ->resolve();

        $logo =
            $this->copyImage(
                $business->logo_file_id,
                'BUSINESS_LOGO',
                'INVOICE_BRANDING_LOGO',
                'invoice-branding/logos'
            );

        $signature =
            $this->copyImage(
                $settings
                    ?->signature_image_file_id,
                'DOCUMENT_SIGNATURE',
                'INVOICE_BRANDING_SIGNATURE',
                'invoice-branding/signatures'
            );

        return [
            'branding_snapshot' => [
                'version' =>
                    1,

                'business_name' =>
                    $branding['business_name'],

                'address' =>
                    $branding['address'],

                'phone' =>
                    $branding['phone'],

                'email' =>
                    $branding['email'],

                'tax_id' =>
                    $branding['tax_id'],

                'invoice_footnote' =>
                    $branding['invoice_footnote'],

                'signature_name' =>
                    $branding['signature_name'],

                'signature_title' =>
                    $branding['signature_title'],
            ],

            'branding_logo_file_id' =>
                $logo?->id,

            'branding_signature_file_id' =>
                $signature?->id,
        ];
    }

    public function resolve(
        Invoice $invoice
    ): array {
        $snapshot =
            $invoice->branding_snapshot;

        if (! is_array($snapshot)) {
            return $this->brandingResolver
                ->resolveFor(
                    $invoice->tenant_id,
                    $invoice->business_id
                );
        }

        return [
            'business_name' =>
                $snapshot['business_name']
                ?? null,

            'address' =>
                $snapshot['address']
                ?? null,

            'phone' =>
                $snapshot['phone']
                ?? null,

            'email' =>
                $snapshot['email']
                ?? null,

            'tax_id' =>
                $snapshot['tax_id']
                ?? null,

            'logo_data_uri' =>
                $this->brandingResolver
                    ->privateImageDataUriForTenant(
                        $invoice->tenant_id,
                        $invoice
                            ->branding_logo_file_id,
                        'INVOICE_BRANDING_LOGO'
                    ),

            'invoice_footnote' =>
                $snapshot['invoice_footnote']
                ?? null,

            'signature_name' =>
                $snapshot['signature_name']
                ?? null,

            'signature_title' =>
                $snapshot['signature_title']
                ?? null,

            'signature_image_data_uri' =>
                $this->brandingResolver
                    ->privateImageDataUriForTenant(
                        $invoice->tenant_id,
                        $invoice
                            ->branding_signature_file_id,
                        'INVOICE_BRANDING_SIGNATURE'
                    ),
        ];
    }

    private function copyImage(
        ?string $fileId,
        string $sourcePurpose,
        string $snapshotPurpose,
        string $folder
    ): ?FileAsset {
        if ($fileId === null) {
            return null;
        }

        $source =
            FileAsset::query()
                ->where(
                    'tenant_id',
                    $this->tenantContext
                        ->tenantId()
                )
                ->where(
                    'id',
                    $fileId
                )
                ->where(
                    'purpose',
                    $sourcePurpose
                )
                ->first();

        if ($source === null) {
            return null;
        }

        return $this->fileService
            ->copyPrivateImage(
                $source,
                $snapshotPurpose,
                $folder
            );
    }
}
