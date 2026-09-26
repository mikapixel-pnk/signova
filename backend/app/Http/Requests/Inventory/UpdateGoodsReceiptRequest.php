<?php

namespace App\Http\Requests\Inventory;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGoodsReceiptRequest extends FormRequest
{
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

            'received_at' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'items' => [
                'sometimes',
                'array',
                'min:1',
                'max:100',
            ],

            'items.*.purchase_order_item_id' => [
                'required_with:items',
                'string',
                'distinct',
            ],

            'items.*.material_id' => [
                'prohibited',
            ],

            'items.*.quantity_received' => [
                'required_with:items',
                'numeric',
                'gt:0',
            ],

            'purchase_order_id' => [
                'prohibited',
            ],

            'status' => [
                'prohibited',
            ],

            'receipt_number' => [
                'prohibited',
            ],
        ];
    }
}
