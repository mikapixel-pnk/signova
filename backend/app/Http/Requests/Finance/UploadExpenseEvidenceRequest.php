<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UploadExpenseEvidenceRequest extends FormRequest
{
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
