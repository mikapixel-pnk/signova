<?php

namespace App\Services\Invoice\Document;

use App\Models\Invoice;
use App\Services\Invoice\InvoiceItemPricingPresenter;
use App\Support\Localization\CanonicalLabel;

class InvoiceDocumentViewModelBuilder
{
    public function __construct(
        private readonly InvoiceBrandingSnapshotService $brandingSnapshotService,
        private readonly InvoiceItemPricingPresenter $pricingPresenter
    ) {
    }

    public function build(
        Invoice $invoice
    ): array {
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

                            'input_quantity' =>
                                $this->quantity(
                                    $item->quantity
                                ),

                            'pricing_method' =>
                                $item->pricing_method,

                            'pricing_config' =>
                                $item->pricing_config,

                            'pricing_quantity' =>
                                $item->pricing_quantity !== null
                                    ? $this->quantity(
                                        $item->pricing_quantity
                                    )
                                    : null,

                            'quantity' =>
                                $this->quantity(
                                    $item->pricing_quantity
                                    ?? $item->quantity
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
                /*
                 * Customer-facing subtotal.
                 *
                 * Item discount sudah tercermin
                 * pada subtotal ini sehingga tidak
                 * perlu diekspos sebagai baris
                 * diskon terpisah pada dokumen.
                 *
                 * Invoice legacy belum memiliki
                 * item_discount_total sehingga
                 * menggunakan discount_total lama.
                 */
                'subtotal' =>
                    $this->money(
                        \Brick\Math\BigDecimal::of(
                            (string) $invoice->subtotal
                        )
                            ->minus(
                                (string) (
                                    $invoice->item_discount_total
                                    ?? $invoice->discount_total
                                )
                            )
                            ->__toString()
                    ),

                /*
                 * Tetap tersedia untuk kompatibilitas
                 * internal/template lama.
                 */
                'discount_total' =>
                    $this->money(
                        $invoice->discount_total
                    ),

                'show_global_discount' =>
                    $invoice->global_discount_type !== null
                    && (float) (
                        $invoice->global_discount_amount
                        ?? 0
                    ) > 0,

                'global_discount_label' =>
                    $invoice->global_discount_type
                        === 'PERCENT'
                        && $invoice->global_discount_value
                            !== null
                            ? 'Diskon '
                                . $this->quantity(
                                    $invoice->global_discount_value
                                )
                                . '%'
                            : 'Diskon Global',

                'global_discount_amount' =>
                    $this->money(
                        $invoice->global_discount_amount
                        ?? 0
                    ),

                'show_tax' =>
                    (float) $invoice->tax_total
                        > 0,

                'tax_label' =>
                    $invoice->tax_enabled === true
                    && $invoice->tax_rate !== null
                        ? 'Pajak '
                            . $this->quantity(
                                $invoice->tax_rate
                            )
                            . '%'
                        : 'Pajak',

                'tax_total' =>
                    $this->money(
                        $invoice->tax_total
                    ),

                'total' =>
                    $this->money(
                        $invoice->total
                    ),
            ],

            'branding' =>
                $this->brandingSnapshotService
                    ->resolve(
                        $invoice
                    ),
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


}
