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
                'business_name',
                'address',
                'phone',
                'email',
                'tax_id',
                'quotation_footer',
                'invoice_footnote',
                'signature_name',
                'signature_title',
            ] as $field
        ) {
            if ($this->exists($field)) {
                if (is_string($this->$field)) {
                    $value = trim($this->$field);

                    $data[$field] =
                        $value === ''
                            ? null
                            : $value;
                } else {
                    $data[$field] = $this->$field;
                }
            }
        }

        if (
            array_key_exists('email', $data)
            && is_string($data['email'])
        ) {
            $data['email'] = strtolower(
                $data['email']
            );
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'business_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:190',
            ],
            'address' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:190',
            ],
            'tax_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'quotation_footer' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
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
