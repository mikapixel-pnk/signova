<?php

namespace App\Http\Resources\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicInvoiceResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        $invoice =
            $this->invoice;

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
                $invoice->outstanding_amount,

            'notes' =>
                $invoice->notes,

            'payment_allowed' =>
                $paymentAllowed,

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
                                $item->discount_amount,

                            'tax_amount' =>
                                $item->tax_amount,

                            'amount' =>
                                $item->amount,
                        ]
                    )
                    ->values()
                    ->all(),
        ];
    }
}
