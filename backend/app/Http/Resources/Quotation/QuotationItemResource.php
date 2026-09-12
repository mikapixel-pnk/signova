<?php

namespace App\Http\Resources\Quotation;

use App\Support\Localization\CanonicalLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationItemResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'catalog_item_id' =>
                $this->catalog_item_id,

            'unit_id' =>
                $this->unit_id,

            'item_type' =>
                $this->item_type,

            'item_type_label' =>
                CanonicalLabel::catalogType(
                    $this->item_type
                ),

            'code' => $this->code,
            'name' => $this->name,

            'description' =>
                $this->description,

            'quantity' =>
                $this->quantity,

            'unit_code' =>
                $this->unit_code,

            'unit_name' =>
                $this->unit_name,

            'unit_symbol' =>
                $this->unit_symbol,

            'pricing_method' =>
                $this->pricing_method,

            'pricing_method_label' =>
                CanonicalLabel::pricingMethod(
                    $this->pricing_method
                ),

            'pricing_config' =>
                $this->pricing_config,

            'unit_price' =>
                $this->unit_price,

            'discount_amount' =>
                $this->discount_amount,

            'tax_amount' =>
                $this->tax_amount,

            'amount' =>
                $this->amount,

            'sort_order' =>
                $this->sort_order,
        ];
    }
}
