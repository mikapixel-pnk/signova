<?php

namespace App\Http\Requests\Purchasing;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (
            $this->exists('currency')
            && is_string($this->currency)
        ) {
            $this->merge([
                'currency' =>
                    strtoupper(
                        trim($this->currency)
                    ),
            ]);
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
            'needed_at' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'currency' => [
                'sometimes',
                'string',
                'size:3',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
                'max:100',
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

            'items.*.material_id' => [
                'sometimes',
                'nullable',
                'string',
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

            'items.*.procurement_type' => [
                'sometimes',
                'nullable',
                'in:INVENTORY_ITEM,NON_STOCK_GOOD,SERVICE',
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
                'required_without_all:items.*.catalog_item_id,items.*.material_id',
                'nullable',
                'string',
                'max:190',
            ],

            'items.*.description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'items.*.estimated_unit_price' => [
                'sometimes',
                'numeric',
                'min:0',
            ],

            'items.*.sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'request_number' => [
                'prohibited',
            ],

            'status' => [
                'prohibited',
            ],

            'estimated_total' => [
                'prohibited',
            ],

            'requested_by_user_id' => [
                'prohibited',
            ],

            'submitted_by_user_id' => [
                'prohibited',
            ],

            'approved_by_user_id' => [
                'prohibited',
            ],

            'rejected_by_user_id' => [
                'prohibited',
            ],

            'cancelled_by_user_id' => [
                'prohibited',
            ],

            'items.*.amount' => [
                'prohibited',
            ],

            'items.*.unit_code' => [
                'prohibited',
            ],

            'items.*.unit_name' => [
                'prohibited',
            ],

            'items.*.unit_symbol' => [
                'prohibited',
            ],
        ];
    }
}
