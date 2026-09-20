<?php

namespace App\Http\Resources\Inventory;

use App\Support\Localization\CanonicalLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'name' =>
                $this->name,

            'location' =>
                $this->location,

            'status' =>
                $this->status,

            'status_label' =>
                CanonicalLabel::status(
                    $this->status
                ),
        ];
    }
}
