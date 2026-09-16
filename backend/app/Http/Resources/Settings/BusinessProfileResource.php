<?php

namespace App\Http\Resources\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessProfileResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,
            'name' => $this->name,

            'legal_name' =>
                $this->legal_name,

            'address' =>
                $this->address,

            'city' =>
                $this->city,

            'province' =>
                $this->province,

            'postal_code' =>
                $this->postal_code,

            'phone' =>
                $this->phone,

            'whatsapp' =>
                $this->whatsapp,

            'email' =>
                $this->email,

            'website' =>
                $this->website,

            'tax_id' =>
                $this->tax_id,

            'is_default' =>
                (bool) $this->is_default,

            'status' =>
                $this->status,

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),
        ];
    }
}
