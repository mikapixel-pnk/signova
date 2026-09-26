<?php

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequestResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'request_number' =>
                $this->request_number,

            'status' =>
                $this->status,

            'needed_at' =>
                $this->needed_at
                    ?->toDateString(),

            'currency' =>
                $this->currency,

            'estimated_total' =>
                $this->estimated_total,

            'notes' =>
                $this->notes,

            'submitted_at' =>
                $this->submitted_at
                    ?->toIso8601String(),

            'approved_at' =>
                $this->approved_at
                    ?->toIso8601String(),

            'rejected_at' =>
                $this->rejected_at
                    ?->toIso8601String(),

            'rejection_reason' =>
                $this->rejection_reason,

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

                                    'catalog_item_id' =>
                                        $item
                                            ->catalog_item_id,

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

                                    'estimated_unit_price' =>
                                        $item
                                            ->estimated_unit_price,

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
