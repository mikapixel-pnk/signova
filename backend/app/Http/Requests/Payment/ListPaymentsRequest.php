<?php

namespace App\Http\Requests\Payment;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPaymentsRequest extends FormRequest
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
                'search',
                'status',
                'method',
                'customer_id',
            ] as $field
        ) {
            if ($this->exists($field)) {
                $value = $this->input($field);

                $data[$field] =
                    is_string($value)
                        ? trim($value)
                        : $value;
            }
        }

        foreach (['status', 'method'] as $field) {
            if (
                isset($data[$field])
                && is_string($data[$field])
            ) {
                $data[$field] =
                    strtoupper($data[$field]);
            }
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $tenantId = app(
            TenantContext::class
        )->tenantId();

        return [
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'max:190',
            ],

            'status' => [
                'sometimes',
                'nullable',
                'in:PENDING,VERIFIED,REJECTED,REVERSED',
            ],

            'method' => [
                'sometimes',
                'nullable',
                'in:BANK_TRANSFER,STATIC_QR,MIDTRANS',
            ],

            'customer_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists(
                    'customers',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'tenant_id',
                            $tenantId
                        )
                ),
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
