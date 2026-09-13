<?php

namespace App\Http\Requests\Invoice;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (
            [
                'notes',
            ] as $field
        ) {
            if ($this->exists($field)) {
                $data[$field] =
                    is_string($this->$field)
                        ? trim($this->$field)
                        : $this->$field;
            }
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $tenantId = app(
            TenantContext::class
        )->tenantId();

        $pricingMethods =
            'STANDARD,AREA,LENGTH,VOLUME,TIME,PACKAGE,MANUAL';

        return [
            'customer_id' => [
                'required',
                'string',
                Rule::exists(
                    'customers',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'tenant_id',
                            $tenantId
                        )
                ),
            ],

            'due_at' => [
                'nullable',
                'date',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.catalog_item_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists(
                    'catalog_items',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'tenant_id',
                            $tenantId
                        )
                ),
            ],

            'items.*.unit_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists(
                    'units',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'tenant_id',
                            $tenantId
                        )
                ),
            ],

            'items.*.item_type' => [
                'sometimes',
                'nullable',
                'in:PRODUCT,SERVICE',
            ],

            'items.*.code' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'items.*.name' => [
                'required_without:items.*.catalog_item_id',
                'nullable',
                'string',
                'max:190',
            ],

            'items.*.description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'items.*.quantity' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'items.*.pricing_method' => [
                'required_without:items.*.catalog_item_id',
                'nullable',
                'in:' . $pricingMethods,
            ],

            'items.*.pricing_config' => [
                'sometimes',
                'nullable',
                'array',
            ],

            'items.*.pricing_config.width' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'items.*.pricing_config.height' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'items.*.pricing_config.depth' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'items.*.pricing_config.length' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'items.*.pricing_config.duration' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'items.*.unit_price' => [
                'required_without:items.*.catalog_item_id',
                'nullable',
                'numeric',
                'min:0',
            ],

            'items.*.discount_amount' => [
                'sometimes',
                'numeric',
                'min:0',
            ],

            'items.*.tax_rate' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:100',
            ],

            'items.*.sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            /*
             * Backend-owned fields.
             */
            'tenant_id' => ['prohibited'],
            'invoice_number' => ['prohibited'],
            'project_id' => ['prohibited'],
            'source_quotation_id' => ['prohibited'],
            'source_quotation_version_id' => ['prohibited'],
            'status' => ['prohibited'],
            'issued_at' => ['prohibited'],
            'currency' => ['prohibited'],
            'subtotal' => ['prohibited'],
            'discount_total' => ['prohibited'],
            'tax_total' => ['prohibited'],
            'total' => ['prohibited'],
            'paid_amount' => ['prohibited'],
            'outstanding_amount' => ['prohibited'],

            'items.*.tax_amount' => [
                'prohibited',
            ],

            'items.*.amount' => [
                'prohibited',
            ],
        ];
    }
}
