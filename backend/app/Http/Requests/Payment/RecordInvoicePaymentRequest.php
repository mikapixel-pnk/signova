<?php

namespace App\Http\Requests\Payment;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordInvoicePaymentRequest extends FormRequest
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
                'cash_account_id',
                'method',
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

        if (
            isset($data['method'])
            && is_string($data['method'])
        ) {
            $data['method'] =
                strtoupper(
                    $data['method']
                );
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

        $businessId = app(
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
                            ->where(
                                'status',
                                'ACTIVE'
                            )
                ),
            ],

            'amount' => [
                'required',
                'numeric',
                'decimal:0,2',
                'gt:0',
                'lte:9999999999999999.99',
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
