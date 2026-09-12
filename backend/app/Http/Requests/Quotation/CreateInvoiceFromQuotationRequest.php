<?php

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;

class CreateInvoiceFromQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (
            $this->exists('invoice_number')
            && is_string($this->invoice_number)
        ) {
            $this->merge([
                'invoice_number' =>
                    trim($this->invoice_number),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'invoice_number' => [
                'required',
                'string',
                'min:1',
                'max:100',
            ],

            'due_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}
