<?php

namespace App\Http\Resources\Catalog;

use App\Support\Localization\CanonicalLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogCategoryResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,

            'status' => $this->status,
            'status_label' =>
                CanonicalLabel::status(
                    $this->status
                ),
        ];
    }
}
