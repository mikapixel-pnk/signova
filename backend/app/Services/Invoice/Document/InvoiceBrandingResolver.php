<?php

namespace App\Services\Invoice\Document;

use App\Models\BusinessProfile;
use App\Models\FileAsset;
use App\Models\TenantDocumentSetting;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;

class InvoiceBrandingResolver
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext
    ) {
    }

    public function resolve(): array
    {
        return $this->resolveFor(
            $this->tenantContext->tenantId(),
            $this->businessContext->businessId()
        );
    }

    public function resolveFor(
        string $tenantId,
        string $businessId
    ): array {
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

        return [
            'business_name' =>
                $business->name,

            'address' =>
                $business->address,

            'phone' =>
                $business->phone,

            'email' =>
                $business->email,

            'tax_id' =>
                $business->tax_id,

            'logo_data_uri' =>
                $this->privateImageDataUriForTenant(
                    $tenantId,
                    $business->logo_file_id,
                    'BUSINESS_LOGO'
                ),

            'invoice_footnote' =>
                $settings?->invoice_footnote,

            'signature_name' =>
                $settings?->signature_name,

            'signature_title' =>
                $settings?->signature_title,

            'signature_image_data_uri' =>
                $this->privateImageDataUriForTenant(
                    $tenantId,
                    $settings
                        ?->signature_image_file_id,
                    'DOCUMENT_SIGNATURE'
                ),
        ];
    }

    public function privateImageDataUri(
        ?string $fileId,
        string $purpose
    ): ?string {
        return $this->privateImageDataUriForTenant(
            $this->tenantContext->tenantId(),
            $fileId,
            $purpose
        );
    }

    public function privateImageDataUriForTenant(
        string $tenantId,
        ?string $fileId,
        string $purpose
    ): ?string {
        if ($fileId === null) {
            return null;
        }

        $file =
            FileAsset::query()
                ->where(
                    'tenant_id',
                    $tenantId
                )
                ->where(
                    'id',
                    $fileId
                )
                ->where(
                    'purpose',
                    $purpose
                )
                ->whereIn(
                    'mime_type',
                    [
                        'image/png',
                        'image/jpeg',
                        'image/webp',
                    ]
                )
                ->first();

        if ($file === null) {
            return null;
        }

        $disk =
            Storage::disk(
                $file->storage_disk
            );

        if (! $disk->exists(
            $file->object_key
        )) {
            return null;
        }

        $contents =
            $disk->get(
                $file->object_key
            );

        if ($contents === '') {
            return null;
        }

        return 'data:'
            . $file->mime_type
            . ';base64,'
            . base64_encode(
                $contents
            );
    }
}
