<?php

namespace App\Services\Invoice\Document;

use App\Models\BusinessProfile;
use App\Models\FileAsset;
use App\Models\Invoice;
use App\Models\TenantDocumentSetting;
use App\Support\Localization\CanonicalLabel;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;

class InvoiceDocumentViewModelBuilder
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext
    ) {
    }

    public function build(
        Invoice $invoice
    ): array {
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

        return [
            'document' => [
                'title' =>
                    'TAGIHAN',

                'number' =>
                    $invoice->invoice_number,

                'status' =>
                    $invoice->status,

                'status_label' =>
                    CanonicalLabel::status(
                        $invoice->status
                    ),

                'issued_at' =>
                    $invoice->issued_at
                        ?->format('d-m-Y')
                    ?? '-',

                'due_at' =>
                    $invoice->due_at
                        ?->format('d-m-Y')
                    ?? '-',

                'currency' =>
                    $invoice->currency,

                'notes' =>
                    $invoice->notes,
            ],

            'customer' => [
                'name' =>
                    $invoice->customer->name,
            ],

            'items' =>
                $invoice->items
                    ->values()
                    ->map(
                        fn ($item, $index) => [
                            'number' =>
                                $index + 1,

                            'name' =>
                                $item->name,

                            'description' =>
                                $item->description,

                            'quantity' =>
                                $this->quantity(
                                    $item->quantity
                                ),

                            'unit' =>
                                $item->unit_symbol
                                ?: $item->unit_name
                                ?: $item->unit_code
                                ?: '-',

                            'unit_price' =>
                                $this->money(
                                    $item->unit_price
                                ),

                            'amount' =>
                                $this->money(
                                    $item->amount
                                ),
                        ]
                    )
                    ->all(),

            'summary' => [
                'subtotal' =>
                    $this->money(
                        $invoice->subtotal
                    ),

                'discount_total' =>
                    $this->money(
                        $invoice->discount_total
                    ),

                'tax_total' =>
                    $this->money(
                        $invoice->tax_total
                    ),

                'total' =>
                    $this->money(
                        $invoice->total
                    ),
            ],

            'branding' => [
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
                    $this->privateImageDataUri(
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
                    $this->privateImageDataUri(
                        $tenantId,
                        $settings
                            ?->signature_image_file_id,
                        'DOCUMENT_SIGNATURE'
                    ),
            ],
        ];
    }

    private function quantity(
        mixed $value
    ): string {
        return rtrim(
            rtrim(
                number_format(
                    (float) $value,
                    4,
                    ',',
                    '.'
                ),
                '0'
            ),
            ','
        );
    }

    private function money(
        mixed $value
    ): string {
        return number_format(
            (float) $value,
            2,
            ',',
            '.'
        );
    }

    private function privateImageDataUri(
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
