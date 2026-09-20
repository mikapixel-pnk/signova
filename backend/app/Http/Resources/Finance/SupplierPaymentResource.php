<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPaymentResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'payment_number' =>
                $this->payment_number,

            'supplier' => [
                'id' =>
                    $this->supplier_id,

                'name' =>
                    $this->supplier?->name,
            ],

            'cash_account' => [
                'id' =>
                    $this->cash_account_id,

                'name' =>
                    $this->cashAccount?->name,

                'type' =>
                    $this->cashAccount?->type,
            ],

            'status' =>
                $this->status,

            'status_label' =>
                match ($this->status) {
                    'DRAFT' =>
                        'Draf',

                    'POSTED' =>
                        'Tercatat',

                    'REVERSED' =>
                        'Dikoreksi',

                    default =>
                        $this->status,
                },

            'currency' =>
                $this->currency,

            'amount' =>
                $this->amount,

            'paid_at' =>
                $this->paid_at?->toIso8601String(),

            'reference' =>
                $this->reference,

            'notes' =>
                $this->notes,

            'cash_transaction_id' =>
                $this->cash_transaction_id,

            'reversal_cash_transaction_id' =>
                $this->reversal_cash_transaction_id,

            'reversal_reason' =>
                $this->reversal_reason,

            'allocations' =>
                $this->allocations
                    ->map(
                        fn ($allocation) => [
                            'id' =>
                                $allocation->id,

                            'supplier_bill_id' =>
                                $allocation
                                    ->supplier_bill_id,

                            'bill_number' =>
                                $allocation
                                    ->bill
                                    ?->bill_number,

                            'bill_status' =>
                                $allocation
                                    ->bill
                                    ?->status,

                            'amount' =>
                                $allocation->amount,
                        ]
                    )
                    ->values()
                    ->all(),

            'workflow' => [
                'next_action' =>
                    match ($this->status) {
                        'DRAFT' =>
                            'POST',

                        'POSTED' =>
                            'REVERSE',

                        default =>
                            null,
                    },
            ],
        ];
    }
}
