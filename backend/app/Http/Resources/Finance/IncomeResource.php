<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncomeResource extends JsonResource
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

            'occurred_at' =>
                $this->occurred_at?->toISOString(),

            'category' =>
                $this->category,

            'description' =>
                $this->description,

            'reference' =>
                $this->reference,

            'status' =>
                $this->status,

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
