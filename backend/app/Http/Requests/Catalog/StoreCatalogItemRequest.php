<?php

namespace App\Http\Requests\Catalog;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCatalogItemRequest extends FormRequest
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
                'type',
                'code',
                'name',
                'description',
                'pricing_method',
                'currency',
            ] as $field
        ) {
            if ($this->exists($field)) {
                $data[$field] = is_string($this->$field)
                    ? trim($this->$field)
                    : $this->$field;
            }
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper(
                $data['currency']
            );
        }

        if (isset($data['pricing_method'])) {
            $data['pricing_method'] = strtoupper(
                $data['pricing_method']
            );
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $tenantId = app(
            TenantContext::class
        )->tenantId();

        return [
            'category_id' => [
                'nullable',
                'string',
                Rule::exists(
                    'catalog_categories',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'tenant_id',
                            $tenantId
                        )
                ),
            ],
            'unit_id' => [
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
            'type' => [
                'required',
                'in:PRODUCT,SERVICE',
            ],
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique(
                    'catalog_items',
                    'code'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'tenant_id',
                            $tenantId
                        )
                ),
            ],
            'name' => [
                'required',
                'string',
                'max:190',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'pricing_method' => [
                'sometimes',
                'in:STANDARD,AREA,LENGTH,VOLUME,TIME,PACKAGE,MANUAL',
            ],
            'base_price' => [
                'sometimes',
                'numeric',
                'min:0',
            ],
            'currency' => [
                'sometimes',
                'string',
                'size:3',
            ],
            'pricing_config' => [
                'nullable',
                'array',
            ],
        ];
    }
}
