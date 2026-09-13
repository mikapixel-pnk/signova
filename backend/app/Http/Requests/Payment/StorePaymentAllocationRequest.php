<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'invoice_id' => [
                'required',
                'string',
                'max:26',
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
