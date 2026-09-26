<?php

namespace App\Http\Requests\Inventory;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (
            [
                'code',
                'name',
                'category',
                'inventory_type',
                'status',
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

        if (isset($data['inventory_type'])) {
            $data['inventory_type'] =
                strtoupper(
                    $data['inventory_type']
                );
        }

        if (isset($data['status'])) {
            $data['status'] =
                strtoupper($data['status']);
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
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique(
                    'materials',
                    'code'
                )
                    ->where(
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
                    )
                    ->ignore(
                        $this->route(
                            'materialId'
                        ),
                        'id'
                    ),
            ],

            'name' => [
                'sometimes',
                'required',
                'string',
                'max:190',
            ],

            'unit_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists(
                    'units',
                    'id'
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
                            ->where(
                                'status',
                                'ACTIVE'
                            )
                ),
            ],

            'category' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
            ],

            'inventory_type' => [
                'sometimes',
                'required',
                'in:RAW_MATERIAL,COMPONENT,CONSUMABLE,RESALE,FINISHED_GOOD',
            ],

            'status' => [
                'sometimes',
                'in:ACTIVE,INACTIVE',
            ],
        ];
    }
}
