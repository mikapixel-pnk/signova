<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'receipt_number' =>
                $this->receipt_number,

            'purchase_order_id' =>
                $this->purchase_order_id,

            'warehouse_id' =>
                $this->warehouse_id,

            'warehouse_name' =>
                $this->warehouse?->name,

            'status' =>
                $this->status,

            'status_label' =>
                match ($this->status) {
                    'DRAFT' => 'Draf',
                    'POSTED' => 'Dicatat',
                    'REVERSED' => 'Dikoreksi',
                    default => $this->status,
                },

            'received_at' =>
                $this->received_at
                    ?->toISOString(),

            'notes' =>
                $this->notes,

            'reversal_reason' =>
                $this->reversal_reason,

            'items' =>
                $this->whenLoaded(
                    'items',
                    fn () =>
                        $this->items
                            ->map(
                                fn ($item) => [
                                    'id' =>
                                        $item->id,

                                    'purchase_order_item_id' =>
                                        $item
                                            ->purchase_order_item_id,

                                    'material_id' =>
                                        $item->material_id,

                                    'material_name' =>
                                        $item->material?->name,

                                    'item_type' =>
                                        $item
                                            ->purchaseOrderItem
                                            ?->item_type,

                                    'code' =>
                                        $item
                                            ->purchaseOrderItem
                                            ?->code,

                                    'name' =>
                                        $item
                                            ->purchaseOrderItem
                                            ?->name,

                                    'ordered_quantity' =>
                                        $item
                                            ->purchaseOrderItem
                                            ?->quantity,

                                    'quantity_received' =>
                                        $item
                                            ->quantity_received,

                                    'unit_symbol' =>
                                        $item
                                            ->purchaseOrderItem
                                            ?->unit_symbol,
                                ]
                            )
                            ->values()
                            ->all()
                ),

            'workflow' => [
                'next_action' =>
                    match ($this->status) {
                        'DRAFT' => 'POST',
                        'POSTED' => 'REVERSE',
                        default => null,
                    },
            ],
        ];
    }
}
