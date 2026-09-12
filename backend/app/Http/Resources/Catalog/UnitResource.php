<?php

namespace App\Http\Resources\Catalog;

use App\Support\Localization\CanonicalLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'symbol' => $this->symbol,

            'unit_type' => $this->unit_type,
            'unit_type_label' =>
                CanonicalLabel::unitType(
                    $this->unit_type
                ),

            'decimal_precision' =>
                $this->decimal_precision,

            'status' => $this->status,
            'status_label' =>
                CanonicalLabel::status(
                    $this->status
                ),
        ];
    }
}
