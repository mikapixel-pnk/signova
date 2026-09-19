<?php

namespace App\Http\Resources\Supplier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'tax_id' => $this->tax_id,
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,
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
