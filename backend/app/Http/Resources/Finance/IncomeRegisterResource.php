<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncomeRegisterResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return $this->resource;
    }
}
