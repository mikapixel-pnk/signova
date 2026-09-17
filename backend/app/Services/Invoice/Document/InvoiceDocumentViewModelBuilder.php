<?php

namespace App\Services\Invoice\Document;

use App\Models\Invoice;
use App\Support\Localization\CanonicalLabel;

class InvoiceDocumentViewModelBuilder
{
    public function __construct(
        private readonly InvoiceBrandingSnapshotService $brandingSnapshotService
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
