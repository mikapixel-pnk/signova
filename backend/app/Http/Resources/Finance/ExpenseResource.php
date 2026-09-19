<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' =>
                $this->id,

            'cash_account' =>
                $this->cashAccount
                    ? [
                        'id' =>
                            $this->cashAccount->id,
                        'name' =>
                            $this->cashAccount->name,
                        'type' =>
                            $this->cashAccount->type,
                        'status' =>
                            $this->cashAccount->status,
                    ]
                    : null,

            'amount' =>
                number_format(
                    (float) $this->amount,
                    2,
                    '.',
                    ''
                ),

            'currency' =>
                $this->currency,

            'incurred_at' =>
                $this->incurred_at?->toISOString(),

            'category' =>
                $this->category,

            'description' =>
                $this->description,

            'status' =>
                $this->status,

            'submitted_at' =>
                $this->submitted_at?->toISOString(),

            'approved_at' =>
                $this->approved_at?->toISOString(),

            'rejected_at' =>
                $this->rejected_at?->toISOString(),

            'rejection_reason' =>
                $this->rejection_reason,

            'posted_at' =>
                $this->posted_at?->toISOString(),

            'voided_at' =>
                $this->voided_at?->toISOString(),

            'void_reason' =>
                $this->void_reason,

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
