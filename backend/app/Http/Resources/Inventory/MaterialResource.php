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
        return [
            'id' =>
                $this->id,

            'code' =>
                $this->code,

            'name' =>
                $this->name,

            'unit_id' =>
                $this->unit_id,

            'category' =>
                $this->category,

            'inventory_type' =>
                $this->inventory_type,

            'status' =>
                $this->status,

            'status_label' =>
                CanonicalLabel::status(
                    $this->status
                ),
        ];
    }
}
