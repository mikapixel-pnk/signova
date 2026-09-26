<?php

namespace App\Http\Requests\Inventory;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialRequest extends FormRequest
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
                'stock_tracking',
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

        if (isset($data['inventory_type'])) {
            $data['inventory_type'] =
                strtoupper(
                    $data['inventory_type']
                );
        }

        if (isset($data['stock_tracking'])) {
            $data['stock_tracking'] =
                strtoupper(
                    $data['stock_tracking']
                );
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

        $stockThresholdRules = [
            'sometimes',
            'nullable',
            'numeric',
            'decimal:0,4',
            'min:0',
            'max:99999999999999.9999',
        ];

        return [
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique(
                    'materials',
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

            /*
             * category adalah compatibility alias API lama.
             * Source of truth baru adalah category_id.
             */
            'category' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
            ],

            'category_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists(
                    'inventory_categories',
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

            'inventory_type' => [
                'sometimes',
                'required',
                'in:RAW_MATERIAL,COMPONENT,CONSUMABLE,RESALE,FINISHED_GOOD',
            ],

            'stock_tracking' => [
                'sometimes',
                'required',
                'in:TRACKED,NOT_TRACKED',
            ],

            'minimum_stock' =>
                $stockThresholdRules,

            'reorder_point' =>
                $stockThresholdRules,

            'maximum_stock' =>
                $stockThresholdRules,

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
