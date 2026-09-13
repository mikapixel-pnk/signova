<?php

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;

class CreateInvoiceFromQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_number' => [
                'prohibited',
            ],

            'due_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}
