<?php

namespace App\Http\Resources\Inventory;

use App\Support\Localization\CanonicalLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        $inventoryCategory =
            $this->inventoryCategory;

        return [
            'id' =>
                $this->id,

            'code' =>
                $this->code,

            'name' =>
                $this->name,

            'unit_id' =>
                $this->unit_id,

            'category_id' =>
                $this->category_id,

            /*
             * Compatibility field.
             * Nilai diprioritaskan dari relational master.
             */
            'category' =>
                $inventoryCategory?->name
                ?? $this->category,

            'inventory_category' =>
                $inventoryCategory
                    ? [
                        'id' =>
                            $inventoryCategory->id,

                        'code' =>
                            $inventoryCategory->code,

                        'name' =>
                            $inventoryCategory->name,

                        'status' =>
                            $inventoryCategory->status,
                    ]
                    : null,

            'inventory_type' =>
                $this->inventory_type,

            'stock_tracking' =>
                $this->stock_tracking,

            'minimum_stock' =>
                $this->minimum_stock,

            'reorder_point' =>
                $this->reorder_point,

            'maximum_stock' =>
                $this->maximum_stock,

            'description' =>
                $this->description,

            'status' =>
                $this->status,

            'status_label' =>
                CanonicalLabel::status(
                    $this->status
                ),
        ];
    }
}
