<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class VoidInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (
            $this->exists('reason')
            && is_string($this->reason)
        ) {
            $this->merge([
                'reason' =>
                    trim($this->reason),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:1',
                'max:1000',
            ],
        ];
    }
}
