<?php

namespace App\Http\Resources\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicInvoiceResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return is_array(
            $this->resource
        )
            ? $this->resource
            : [];
    }
}
