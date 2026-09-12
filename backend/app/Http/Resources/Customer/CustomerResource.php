<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'code' => $this->code,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'tax_id' => $this->tax_id,
            'payment_terms_days' =>
                $this->payment_terms_days,
            'notes' => $this->notes,
            'status' => $this->status,
            'created_at' =>
                $this->created_at?->toISOString(),
            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
