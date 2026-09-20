<?php

namespace App\Http\Requests\Finance;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierBillRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (
            [
                'supplier_invoice_number',
                'notes',
            ] as $field
        ) {
            if (
                $this->exists($field)
                && is_string($this->{$field})
            ) {
                $value =
                    trim($this->{$field});

                $this->merge([
                    $field =>
                        $value === ''
                            ? null
                            : $value,
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
            'goods_receipt_id' => [
                'required',
                'string',
                Rule::exists(
                    'goods_receipts',
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

            'supplier_invoice_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:190',
            ],

            'bill_date' => [
                'sometimes',
                'date',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'supplier_id' => [
                'prohibited',
            ],

            'purchase_order_id' => [
                'prohibited',
            ],

            'bill_number' => [
                'prohibited',
            ],

            'due_date' => [
                'prohibited',
            ],

            'currency' => [
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

            'status' => [
                'prohibited',
            ],
        ];
    }
}
