<?php

namespace App\Http\Requests\Purchasing;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
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
            'supplier_id' => [
                'required',
                'string',
                Rule::exists(
                    'suppliers',
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

            'source_purchase_request_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists(
                    'purchase_requests',
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

            'expected_at' => [
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
                'required_without:source_purchase_request_id',
                'sometimes',
                'array',
                'min:1',
                'max:100',
            ],

            'items.*.source_purchase_request_item_id' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'items.*.catalog_item_id' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'items.*.unit_id' => [
                'sometimes',
                'nullable',
                'string',
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
                'required_without_all:items.*.catalog_item_id,items.*.source_purchase_request_item_id',
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

            'items.*.unit_price' => [
                'sometimes',
                'numeric',
                'min:0',
            ],

            'items.*.discount_amount' => [
                'sometimes',
                'numeric',
                'min:0',
            ],

            'items.*.tax_amount' => [
                'sometimes',
                'numeric',
                'min:0',
            ],

            'items.*.sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'order_number' => [
                'prohibited',
            ],

            'status' => [
                'prohibited',
            ],

            'subtotal' => [
                'prohibited',
            ],

            'discount_total' => [
                'prohibited',
            ],

            'tax_total' => [
                'prohibited',
            ],

            'total' => [
                'prohibited',
            ],

            'created_by_user_id' => [
                'prohibited',
            ],

            'issued_by_user_id' => [
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
