<?php

namespace App\Http\Requests\Inventory;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryCategoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (
            [
                'code',
                'name',
                'description',
            ] as $field
        ) {
            if ($this->exists($field)) {
                $data[$field] =
                    is_string($this->$field)
                        ? trim($this->$field)
                        : $this->$field;
            }
        }

        if (isset($data['code'])) {
            $data['code'] =
                strtoupper($data['code']);
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $tenantId =
            app(
                TenantContext::class
            )->tenantId();

        $businessId =
            app(
                BusinessContext::class
            )->businessId();

        return [
            'code' => [
                'required',
                'string',
                'max:80',

                Rule::unique(
                    'inventory_categories',
                    'code'
                )->where(
                    fn ($query) =>
                        $query
                            ->where(
                                'tenant_id',
                                $tenantId
                            )
                            ->where(
                                'business_id',
                                $businessId
                            )
                ),
            ],

            'name' => [
                'required',
                'string',
                'max:120',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'status' => [
                'prohibited',
            ],
        ];
    }
}
