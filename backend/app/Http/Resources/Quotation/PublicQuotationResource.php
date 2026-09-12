<?php

namespace App\Http\Resources\Quotation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicQuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $quotation = $this->quotation;
        $version = $this->quotationVersion;

        return [
            'quotation_number' =>
                $quotation->quotation_number,

            'status' =>
                $quotation->status,

            'valid_until' =>
                $quotation->valid_until?->format('Y-m-d'),

            'customer' => [
                'name' =>
                    $quotation->customer->name,
            ],

            'version' => [
                'revision_no' =>
                    $version->revision_no,

                'currency' =>
                    $version->currency,

                'subtotal' =>
                    $version->subtotal,

                'discount_total' =>
                    $version->discount_total,

                'tax_total' =>
                    $version->tax_total,

                'total' =>
                    $version->total,

                'terms' =>
                    $version->terms,

                'notes' =>
                    $version->notes,

                'items' =>
                    $version->items
                        ->map(
                            fn ($item): array => [
                                'item_type' =>
                                    $item->item_type,

                                'code' =>
                                    $item->code,

                                'name' =>
                                    $item->name,

                                'description' =>
                                    $item->description,

                                'quantity' =>
                                    $item->quantity,

                                'unit_code' =>
                                    $item->unit_code,

                                'unit_name' =>
                                    $item->unit_name,

                                'unit_symbol' =>
                                    $item->unit_symbol,

                                'pricing_method' =>
                                    $item->pricing_method,

                                'pricing_config' =>
                                    $item->pricing_config,

                                'unit_price' =>
                                    $item->unit_price,

                                'discount_amount' =>
                                    $item->discount_amount,

                                'tax_amount' =>
                                    $item->tax_amount,

                                'amount' =>
                                    $item->amount,
                            ]
                        )
                        ->values()
                        ->all(),
            ],
        ];
    }
}
