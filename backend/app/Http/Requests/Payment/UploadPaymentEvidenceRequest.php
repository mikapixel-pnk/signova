<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class UploadPaymentEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'evidence' => [
                'required',
                'file',
                'mimetypes:image/png,image/jpeg,image/webp,application/pdf',
                'max:5120',
            ],
        ];
    }
}
