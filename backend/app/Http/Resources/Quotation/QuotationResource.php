<?php

namespace App\Http\Resources\Quotation;

use App\Support\Localization\CanonicalLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'quotation_number' =>
                $this->quotation_number,

            'customer_id' =>
                $this->customer_id,

            'customer' =>
                $this->whenLoaded(
                    'customer',
                    fn () => [
                        'id' =>
                            $this->customer->id,

                        'code' =>
                            $this->customer->code,

                        'name' =>
                            $this->customer->name,
                    ]
                ),

            'status' =>
                $this->status,

            'status_label' =>
                CanonicalLabel::quotationStatus(
                    $this->status
                ),

            'valid_until' =>
                $this->valid_until?->format(
                    'Y-m-d'
                ),

            'owner_user_id' =>
                $this->owner_user_id,

            'source' =>
                $this->source,

            'current_version' =>
                $this->whenLoaded(
                    'currentVersion',
                    fn () => (
                        new QuotationVersionResource(
                            $this->currentVersion
                        )
                    )->resolve($request)
                ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
