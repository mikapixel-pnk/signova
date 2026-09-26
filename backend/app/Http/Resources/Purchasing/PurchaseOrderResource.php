<?php

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'order_number' =>
                $this->order_number,

            'supplier_id' =>
                $this->supplier_id,

            'supplier' =>
                $this->whenLoaded(
                    'supplier',
                    fn () => [
                        'id' =>
                            $this->supplier->id,

                        'code' =>
                            $this->supplier->code,

                        'name' =>
                            $this->supplier->name,
                    ]
                ),

            'source_purchase_request_id' =>
                $this->source_purchase_request_id,

            'source_purchase_request' =>
                $this->whenLoaded(
                    'sourcePurchaseRequest',
                    fn () =>
                        $this->sourcePurchaseRequest
                            ? [
                                'id' =>
                                    $this
                                        ->sourcePurchaseRequest
                                        ->id,

                                'request_number' =>
                                    $this
                                        ->sourcePurchaseRequest
                                        ->request_number,

                                'status' =>
                                    $this
                                        ->sourcePurchaseRequest
                                        ->status,
                            ]
                            : null
                ),

            'status' =>
                $this->status,

            'currency' =>
                $this->currency,

            'expected_at' =>
                $this->expected_at
                    ?->toDateString(),

            'subtotal' =>
                $this->subtotal,

            'discount_total' =>
                $this->discount_total,

            'tax_total' =>
                $this->tax_total,

            'total' =>
                $this->total,

            'notes' =>
                $this->notes,

            'issued_at' =>
                $this->issued_at
                    ?->toIso8601String(),

            'cancelled_at' =>
                $this->cancelled_at
                    ?->toIso8601String(),

            'cancellation_reason' =>
                $this->cancellation_reason,

            'items' =>
                $this->whenLoaded(
                    'items',
                    fn () =>
                        $this->items
                            ->sortBy(
                                'sort_order'
                            )
                            ->values()
                            ->map(
                                fn ($item) => [
                                    'id' =>
                                        $item->id,

                                    'source_purchase_request_item_id' =>
                                        $item
                                            ->source_purchase_request_item_id,

                                    'catalog_item_id' =>
                                        $item->catalog_item_id,

                                    'material_id' =>
                                        $item->material_id,

                                    'unit_id' =>
                                        $item->unit_id,

                                    'procurement_type' =>
                                        $item
                                            ->procurement_type,

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

                                    'unit_price' =>
                                        $item->unit_price,

                                    'discount_amount' =>
                                        $item->discount_amount,

                                    'tax_amount' =>
                                        $item->tax_amount,

                                    'amount' =>
                                        $item->amount,

                                    'sort_order' =>
                                        $item->sort_order,
                                ]
                            )
                            ->all()
                ),
        ];
    }
}
