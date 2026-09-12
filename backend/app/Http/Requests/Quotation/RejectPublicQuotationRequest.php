<?php

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;

class RejectPublicQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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

    public function messages(): array
    {
        return [
            'reason.required' =>
                'Alasan penolakan wajib diisi.',
            'reason.string' =>
                'Alasan penolakan harus berupa teks.',
            'reason.min' =>
                'Alasan penolakan wajib diisi.',
            'reason.max' =>
                'Alasan penolakan maksimal 1000 karakter.',
        ];
    }
}
