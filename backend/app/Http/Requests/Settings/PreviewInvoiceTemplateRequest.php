<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class PreviewInvoiceTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (
            $this->exists('palette')
            && is_string(
                $this->palette
            )
        ) {
            $this->merge([
                'palette' =>
                    trim(
                        $this->palette
                    ),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'palette' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }
}
