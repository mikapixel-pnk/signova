<?php

namespace App\Http\Resources\Invoice;

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

            'project_id' =>
                $this->project_id,

            'source_quotation_id' =>
                $this->source_quotation_id,

            'source_quotation_version_id' =>
                $this->source_quotation_version_id,

            'status' =>
                $this->status,

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
        ];
    }
}
