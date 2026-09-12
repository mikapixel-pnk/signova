<?php

namespace App\Http\Resources\Quotation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationVersionResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'revision_no' =>
                $this->revision_no,

            'subtotal' =>
                $this->subtotal,

            'discount_total' =>
                $this->discount_total,

            'tax_total' =>
                $this->tax_total,

            'total' =>
                $this->total,

            'currency' =>
                $this->currency,

            'terms' =>
                $this->terms,

            'notes' =>
                $this->notes,

            'items' =>
                QuotationItemResource::collection(
                    $this->whenLoaded('items')
                )->resolve($request),

            'created_at' =>
                $this->created_at?->toISOString(),
        ];
    }
}
