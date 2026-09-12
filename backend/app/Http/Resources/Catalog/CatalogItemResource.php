<?php

namespace App\Http\Resources\Catalog;

use App\Support\Localization\CanonicalLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogItemResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'unit_id' => $this->unit_id,

            'type' => $this->type,
            'type_label' =>
                CanonicalLabel::catalogType(
                    $this->type
                ),

            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,

            'pricing_method' =>
                $this->pricing_method,
            'pricing_method_label' =>
                CanonicalLabel::pricingMethod(
                    $this->pricing_method
                ),

            'base_price' => $this->base_price,
            'currency' => $this->currency,
            'pricing_config' =>
                $this->pricing_config,

            'status' => $this->status,
            'status_label' =>
                CanonicalLabel::status(
                    $this->status
                ),

            'created_at' =>
                $this->created_at?->toISOString(),
            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
