<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UploadBusinessLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                'file',
                'mimetypes:image/png,image/jpeg,image/webp',
                'max:5120',
                'dimensions:max_width=4096,max_height=4096',
            ],
        ];
    }
}
