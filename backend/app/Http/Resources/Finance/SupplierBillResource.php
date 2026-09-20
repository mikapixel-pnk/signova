<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierBillResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'bill_number' =>
                $this->bill_number,

            'supplier_invoice_number' =>
                $this->supplier_invoice_number,

            'supplier' => [
                'id' =>
                    $this->supplier_id,

                'name' =>
                    $this->supplier?->name,
            ],

            'purchase_order' => [
                'id' =>
                    $this->purchase_order_id,

                'order_number' =>
                    $this->purchaseOrder
                        ?->order_number,
            ],

            'goods_receipt' => [
                'id' =>
                    $this->goods_receipt_id,

                'receipt_number' =>
                    $this->goodsReceipt
                        ?->receipt_number,
            ],

            'status' =>
                $this->status,

            'status_label' =>
                match ($this->status) {
                    'DRAFT' =>
                        'Draf',

                    'POSTED' =>
                        'Tercatat',

                    'PARTIALLY_PAID' =>
                        'Dibayar Sebagian',

                    'PAID' =>
                        'Lunas',

                    'CANCELLED' =>
                        'Dibatalkan',

                    default =>
                        $this->status,
                },

            'currency' =>
                $this->currency,

            'bill_date' =>
                $this->bill_date
                    ?->format('Y-m-d'),

            'due_date' =>
                $this->due_date
                    ?->format('Y-m-d'),

            'subtotal' =>
                $this->subtotal,

            'discount_total' =>
                $this->discount_total,

            'tax_total' =>
                $this->tax_total,

            'total' =>
                $this->total,

            'paid_amount' =>
                $this->paidAmount(),

            'outstanding_amount' =>
                $this->outstandingAmount(),

            'notes' =>
                $this->notes,

            'cancellation_reason' =>
                $this->cancellation_reason,

            'workflow' => [
                'next_action' =>
                    match ($this->status) {
                        'DRAFT' =>
                            'POST',

                        'POSTED',
                        'PARTIALLY_PAID' =>
                            'PAY',

                        default =>
                            null,
                    },
            ],
        ];
    }

    private function paidAmount(): string
    {
        return bcadd(
            (string) (
                $this->paid_amount
                ?? '0.00'
            ),
            '0.00',
            2
        );
    }

    private function outstandingAmount(): string
    {
        $outstanding =
            bcsub(
                (string) $this->total,
                (string) (
                    $this->paid_amount
                    ?? '0.00'
                ),
                2
            );

        if (
            bccomp(
                $outstanding,
                '0.00',
                2
            ) < 0
        ) {
            return '0.00';
        }

        return $outstanding;
    }
}
