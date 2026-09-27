<?php

namespace App\Http\Requests\Inventory;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockAdjustmentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (
            [
                'reason',
                'notes',
            ] as $field
        ) {
            if (
                $this->exists($field)
                && is_string(
                    $this->input($field)
                )
            ) {
                $this->merge([
                    $field =>
                        trim(
                            $this->input($field)
                        ),
                ]);
            }
        }
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
            'warehouse_id' => [
                'sometimes',
                'required',
                'string',
                Rule::exists(
                    'warehouses',
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

            'reason' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:5000',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'adjusted_at' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'items' => [
                'sometimes',
                'array',
                'min:1',
                'max:100',
            ],

            'items.*.material_id' => [
                'required_with:items',
                'string',
                'distinct',
                Rule::exists(
                    'materials',
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
                            ->where(
                                'stock_tracking',
                                'TRACKED'
                            )
                ),
            ],

            'items.*.quantity_delta' => [
                'required_with:items',
                'numeric',
            ],

            'items.*.notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'status' => [
                'prohibited',
            ],

            'adjustment_number' => [
                'prohibited',
            ],

            'created_by_user_id' => [
                'prohibited',
            ],

            'posted_by_user_id' => [
                'prohibited',
            ],

            'posted_at' => [
                'prohibited',
            ],

            'reversed_by_user_id' => [
                'prohibited',
            ],

            'reversed_at' => [
                'prohibited',
            ],

            'reversal_reason' => [
                'prohibited',
            ],
        ];
    }
}
