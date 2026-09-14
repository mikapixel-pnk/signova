<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceTemplateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (
            [
                'invoice_template_key',
                'invoice_palette_key',
            ] as $field
        ) {
            if (
                $this->exists(
                    $field
                )
                && is_string(
                    $this->$field
                )
            ) {
                $this->merge([
                    $field =>
                        trim(
                            $this->$field
                        ),
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'invoice_template_key' => [
                'required',
                'string',
                'max:100',
            ],

            'invoice_palette_key' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }
}
