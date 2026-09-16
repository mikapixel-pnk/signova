<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (
            [
                'quotation_opening_text',
                'quotation_closing_text',
                'quotation_default_terms',
                'quotation_number_prefix',
                'invoice_number_prefix',
                'invoice_footnote',
                'signature_name',
                'signature_title',
            ] as $field
        ) {
            if (! $this->exists($field)) {
                continue;
            }

            if (is_string($this->$field)) {
                $value = trim(
                    $this->$field
                );

                $data[$field] =
                    $value === ''
                        ? null
                        : $value;

                continue;
            }

            $data[$field] =
                $this->$field;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'quotation_opening_text' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'quotation_closing_text' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'quotation_default_terms' => [
                'sometimes',
                'nullable',
                'string',
                'max:10000',
            ],

            'quotation_default_validity_days' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:365',
            ],

            'quotation_number_prefix' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9]+$/',
            ],

            'invoice_number_prefix' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9]+$/',
            ],

            'invoice_footnote' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'signature_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:190',
            ],

            'signature_title' => [
                'sometimes',
                'nullable',
                'string',
                'max:190',
            ],
        ];
    }
}
