<?php

namespace App\Http\Resources\Invoice;

use App\Support\Localization\CanonicalLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' =>
                $this->id,

            'invoice_number' =>
                $this->invoice_number,

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

            'project_id' =>
                $this->project_id,

            'source_quotation_id' =>
                $this->source_quotation_id,

            'source_quotation_version_id' =>
                $this->source_quotation_version_id,

            'status' =>
                $this->status,

            'status_label' =>
                CanonicalLabel::status(
                    $this->status
                ),

            'issued_at' =>
                $this->issued_at?->toISOString(),

            'due_at' =>
                $this->due_at?->toISOString(),

            'currency' =>
                $this->currency,

            'subtotal' =>
                $this->subtotal,

            'discount_total' =>
                $this->discount_total,

            'tax_total' =>
                $this->tax_total,

            'total' =>
                $this->total,

            'notes' =>
                $this->notes,

            'items' =>
                $this->whenLoaded(
                    'items',
                    fn () =>
                        $this->items
                            ->map(
                                fn ($item) => [
                                    'id' =>
                                        $item->id,

                                    'catalog_item_id' =>
                                        $item->catalog_item_id,

                                    'item_type' =>
                                        $item->item_type,

                                    'code' =>
                                        $item->code,

                                    'name' =>
                                        $item->name,

                                    'description' =>
                                        $item->description,

                                    'quantity' =>
                                        $item->quantity,

                                    'unit_code' =>
                                        $item->unit_code,

                                    'unit_name' =>
                                        $item->unit_name,

                                    'unit_symbol' =>
                                        $item->unit_symbol,

                                    'unit_price' =>
                                        $item->unit_price,

                                    'discount_amount' =>
                                        $item->discount_amount,

                                    'tax_amount' =>
                                        $item->tax_amount,

                                    'amount' =>
                                        $item->amount,

                                    'sort_order' =>
                                        $item->sort_order,
                                ]
                            )
                            ->values()
                            ->all()
                ),

            'status_history' =>
                $this->whenLoaded(
                    'statusHistory',
                    fn () =>
                        $this->statusHistory
                            ->map(
                                fn ($history) => [
                                    'id' =>
                                        $history->id,

                                    'from_state' =>
                                        $history->from_state,

                                    'to_state' =>
                                        $history->to_state,

                                    'reason' =>
                                        $history->reason,

                                    'source' =>
                                        $history->source,

                                    'occurred_at' =>
                                        $history
                                            ->occurred_at
                                            ?->toISOString(),
                                ]
                            )
                            ->values()
                            ->all()
                ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
