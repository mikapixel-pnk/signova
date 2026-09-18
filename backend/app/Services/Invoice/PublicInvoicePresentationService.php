<?php

namespace App\Services\Invoice;

use App\Models\BusinessProfile;
use App\Models\CashAccount;
use App\Models\FileAsset;
use App\Models\Invoice;
use App\Models\InvoicePublicLink;
use App\Models\TenantPaymentSetting;
use Illuminate\Support\Facades\Storage;

class PublicInvoicePresentationService
{
    private const IMAGE_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    public function build(
        InvoicePublicLink $link
    ): array {
        $invoice =
            $link->invoice;

        $invoice->loadMissing([
            'customer',
            'items',
        ]);

        $paymentAllowed =
            in_array(
                $invoice->status,
                [
                    'ISSUED',
                    'PARTIALLY_PAID',
                    'OVERDUE',
                ],
                true
            )
            && (float) (
                $invoice->outstanding_amount
                ?? 0
            ) > 0;

        $settings =
            TenantPaymentSetting::query()
                ->where(
                    'tenant_id',
                    $invoice->tenant_id
                )
                ->where(
                    'business_id',
                    $invoice->business_id
                )
                ->first();

        $bankTransferEnabled =
            $paymentAllowed
            && (bool) (
                $settings
                    ?->bank_transfer_enabled
                ?? false
            );

        $bankAccounts =
            $bankTransferEnabled
                ? CashAccount::query()
                    ->where(
                        'tenant_id',
                        $invoice->tenant_id
                    )
                    ->where(
                        'business_id',
                        $invoice->business_id
                    )
                    ->where(
                        'type',
                        'BANK'
                    )
                    ->where(
                        'status',
                        'ACTIVE'
                    )
                    ->where(
                        'accepts_payments',
                        true
                    )
                    ->orderByDesc(
                        'is_default'
                    )
                    ->orderBy(
                        'name'
                    )
                    ->get()
                    ->map(
                        fn (
                            CashAccount $account
                        ): array => [
                            /*
                             * Sengaja tidak expose:
                             * id, tenant_id, business_id,
                             * balance, created_by, timestamps.
                             */
                            'name' =>
                                $account->name,

                            'bank_name' =>
                                $account->bank_name,

                            'account_number' =>
                                $account->account_number,

                            'account_name' =>
                                $account->account_name,

                            'currency' =>
                                $account->currency,

                            'is_default' =>
                                (bool)
                                $account
                                    ->is_default,
                        ]
                    )
                    ->values()
                    ->all()
                : [];

        $staticQrEnabled =
            $paymentAllowed
            && (bool) (
                $settings
                    ?->static_qr_enabled
                ?? false
            );

        $staticQrDataUri =
            $staticQrEnabled
                ? $this->imageDataUri(
                    $invoice->tenant_id,
                    $settings
                        ?->static_qr_file_id,
                    'PAYMENT_QR'
                )
                : null;

        /*
         * Jika metadata QR menyatakan aktif tetapi file
         * tidak tersedia, fail-safe: jangan expose QR
         * sebagai metode pembayaran.
         */
        $staticQrEnabled =
            $staticQrEnabled
            && $staticQrDataUri !== null;

        return [
            'invoice_number' =>
                $invoice->invoice_number,

            'status' =>
                $invoice->status,

            'issued_at' =>
                $invoice->issued_at
                    ?->format(
                        'Y-m-d'
                    ),

            'due_at' =>
                $invoice->due_at
                    ?->format(
                        'Y-m-d'
                    ),

            'currency' =>
                $invoice->currency,

            'subtotal' =>
                $invoice->subtotal,

            'discount_total' =>
                $invoice->discount_total,

            'tax_total' =>
                $invoice->tax_total,

            'total' =>
                $invoice->total,

            'paid_amount' =>
                $invoice->paid_amount,

            'outstanding_amount' =>
                $invoice
                    ->outstanding_amount,

            'notes' =>
                $invoice->notes,

            'payment_allowed' =>
                $paymentAllowed,

            'branding' =>
                $this->branding(
                    $invoice
                ),

            'customer' => [
                'name' =>
                    $invoice->customer
                        ->name,
            ],

            'items' =>
                $invoice->items
                    ->map(
                        fn ($item): array => [
                            'name' =>
                                $item->name,

                            'description' =>
                                $item->description,

                            'quantity' =>
                                $item->quantity,

                            'unit_price' =>
                                $item->unit_price,

                            'discount_amount' =>
                                $item
                                    ->discount_amount,

                            'tax_amount' =>
                                $item->tax_amount,

                            'amount' =>
                                $item->amount,
                        ]
                    )
                    ->values()
                    ->all(),

            'payment_options' => [
                'bank_transfer_enabled' =>
                    $bankTransferEnabled
                    && count(
                        $bankAccounts
                    ) > 0,

                'bank_accounts' =>
                    $bankAccounts,

                'static_qr_enabled' =>
                    $staticQrEnabled,

                'static_qr_data_uri' =>
                    $staticQrDataUri,

                'partial_payment_enabled' =>
                    $paymentAllowed
                    && (bool) (
                        $settings
                            ?->partial_payment_enabled
                        ?? true
                    ),
            ],
        ];
    }

    private function branding(
        Invoice $invoice
    ): array {
        $snapshot =
            $invoice
                ->branding_snapshot;

        /*
         * Invoice issued setelah snapshot foundation
         * harus memakai branding historis.
         */
        if (is_array($snapshot)) {
            return [
                'business_name' =>
                    $snapshot[
                        'business_name'
                    ] ?? null,

                'address' =>
                    $snapshot[
                        'address'
                    ] ?? null,

                'phone' =>
                    $snapshot[
                        'phone'
                    ] ?? null,

                'email' =>
                    $snapshot[
                        'email'
                    ] ?? null,

                'tax_id' =>
                    $snapshot[
                        'tax_id'
                    ] ?? null,

                'invoice_footnote' =>
                    $snapshot[
                        'invoice_footnote'
                    ] ?? null,

                'logo_data_uri' =>
                    $this->imageDataUri(
                        $invoice->tenant_id,
                        $invoice
                            ->branding_logo_file_id,
                        'INVOICE_BRANDING_LOGO'
                    ),
            ];
        }

        /*
         * Compatibility untuk invoice historis lama
         * sebelum branding snapshot tersedia.
         */
        $business =
            BusinessProfile::query()
                ->where(
                    'tenant_id',
                    $invoice->tenant_id
                )
                ->where(
                    'id',
                    $invoice->business_id
                )
                ->first();

        return [
            'business_name' =>
                $business?->name,

            'address' =>
                $business?->address,

            'phone' =>
                $business?->phone,

            'email' =>
                $business?->email,

            'tax_id' =>
                $business?->tax_id,

            'invoice_footnote' =>
                null,

            'logo_data_uri' =>
                $this->imageDataUri(
                    $invoice->tenant_id,
                    $business
                        ?->logo_file_id,
                    'BUSINESS_LOGO'
                ),
        ];
    }

    private function imageDataUri(
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
                    self::IMAGE_MIME_TYPES
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
