<?php

namespace App\Http\Requests\Payment;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('invoice_id')) {
            $invoiceId = $this->input('invoice_id');

            if (is_string($invoiceId)) {
                $invoiceId = trim($invoiceId);
            }

            $this->merge([
                'invoice_id' => $invoiceId,
            ]);
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
            'invoice_id' => [
                'required',
                'string',
                'max:26',

                Rule::exists(
                    'invoices',
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

            'amount' => [
                'required',
                'numeric',
                'decimal:0,2',
                'gt:0',
                'lte:9999999999999999.99',
            ],
        ];
    }
}
