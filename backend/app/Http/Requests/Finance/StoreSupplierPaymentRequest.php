<?php

namespace App\Http\Requests\Finance;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (
            [
                'reference',
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
            'cash_account_id' => [
                'required',
                'string',
                Rule::exists(
                    'cash_accounts',
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

            'paid_at' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'reference' => [
                'sometimes',
                'nullable',
                'string',
                'max:190',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'allocations' => [
                'required',
                'array',
                'min:1',
            ],

            'allocations.*.supplier_bill_id' => [
                'required',
                'string',
                'distinct',
                Rule::exists(
                    'supplier_bills',
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

            'allocations.*.amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'supplier_id' => [
                'prohibited',
            ],

            'amount' => [
                'prohibited',
            ],

            'currency' => [
                'prohibited',
            ],

            'status' => [
                'prohibited',
            ],

            'payment_number' => [
                'prohibited',
            ],
        ];
    }
}
