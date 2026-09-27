<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'adjustment_number' =>
                $this->adjustment_number,

            'warehouse_id' =>
                $this->warehouse_id,

            'warehouse_name' =>
                $this->warehouse?->name,

            'status' =>
                $this->status,

            'status_label' =>
                match ($this->status) {
                    'DRAFT' =>
                        'Draf',

                    'POSTED' =>
                        'Dicatat',

                    'REVERSED' =>
                        'Dikoreksi',

                    default =>
                        $this->status,
                },

            'reason' =>
                $this->reason,

            'notes' =>
                $this->notes,

            'adjusted_at' =>
                $this->adjusted_at
                    ?->toISOString(),

            'posted_at' =>
                $this->posted_at
                    ?->toISOString(),

            'reversed_at' =>
                $this->reversed_at
                    ?->toISOString(),

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

                                    'material_id' =>
                                        $item->material_id,

                                    'material_code' =>
                                        $item
                                            ->material
                                            ?->code,

                                    'material_name' =>
                                        $item
                                            ->material
                                            ?->name,

                                    'quantity_delta' =>
                                        $item
                                            ->quantity_delta,

                                    'notes' =>
                                        $item->notes,
                                ]
                            )
                            ->values()
                            ->all()
                ),

            'workflow' => [
                'next_action' =>
                    match ($this->status) {
                        'DRAFT' =>
                            'POST',

                        'POSTED' =>
                            'REVERSE',

                        default =>
                            null,
                    },
            ],
        ];
    }
}
