<?php

namespace App\Http\Requests\Payment;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
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
                'customer_id',
                'method',
                'currency',
                'reference',
            ] as $field
        ) {
            if ($this->exists($field)) {
                $value = $this->input($field);

                if (is_string($value)) {
                    $value = trim($value);

                    if ($value === '') {
                        $value = null;
                    }
                }

                $data[$field] = $value;
            }
        }

        foreach (['method', 'currency'] as $field) {
            if (
                isset($data[$field])
                && is_string($data[$field])
            ) {
                $data[$field] =
                    strtoupper($data[$field]);
            }
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $tenantId = app(
            TenantContext::class
        )->tenantId();

        return [
            'customer_id' => [
                'required',
                'string',
                Rule::exists(
                    'customers',
                    'id'
                )->where(
                    fn ($query) =>
                        $query
                            ->where(
                                'tenant_id',
                                $tenantId
                            )
                            ->where(
                                'status',
                                'ACTIVE'
                            )
                ),
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'currency' => [
                'sometimes',
                'nullable',
                'string',
                'size:3',
                'in:IDR',
            ],

            'paid_at' => [
                'required',
                'date',
            ],

            'method' => [
                'required',
                'in:BANK_TRANSFER,STATIC_QR',
            ],

            'reference' => [
                'sometimes',
                'nullable',
                'string',
                'max:190',
            ],
        ];
    }
}
