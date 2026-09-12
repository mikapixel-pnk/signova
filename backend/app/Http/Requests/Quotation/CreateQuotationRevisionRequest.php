<?php

namespace App\Http\Requests\Quotation;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateQuotationRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (
            [
                'currency',
                'terms',
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

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper(
                $data['currency']
            );
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
            'currency' => [
                'sometimes',
                'string',
                'size:3',
            ],

            'terms' => [
                'sometimes',
                'nullable',
                'string',
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
             * Quotation header tidak boleh dimutasi
             * melalui endpoint revision.
             */
            'quotation_number' => ['prohibited'],
            'customer_id' => ['prohibited'],
            'valid_until' => ['prohibited'],

            /*
             * Backend-owned fields.
             */
            'tenant_id' => ['prohibited'],
            'status' => ['prohibited'],
            'owner_user_id' => ['prohibited'],
            'source' => ['prohibited'],

            'subtotal' => ['prohibited'],
            'discount_total' => ['prohibited'],
            'tax_total' => ['prohibited'],
            'total' => ['prohibited'],

            'items.*.tax_amount' => [
                'prohibited',
            ],

            'items.*.amount' => [
                'prohibited',
            ],
        ];
    }
}
