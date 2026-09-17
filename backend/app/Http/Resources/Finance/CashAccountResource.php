<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashAccountResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,

            'bank_name' =>
                $this->bank_name,

            'account_number' =>
                $this->account_number,

            'account_name' =>
                $this->account_name,

            'currency' =>
                $this->currency,

            'status' =>
                $this->status,

            'is_default' =>
                (bool) $this->is_default,

            'accepts_payments' =>
                (bool) $this->accepts_payments,

            'balance' =>
                number_format(
                    (float) ($this->balance ?? 0),
                    2,
                    '.',
                    ''
                ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
