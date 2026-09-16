<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessProfileRequest extends FormRequest
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
                'name',
                'legal_name',
                'address',
                'city',
                'province',
                'postal_code',
                'phone',
                'whatsapp',
                'email',
                'website',
                'tax_id',
            ] as $field
        ) {
            if (! $this->exists($field)) {
                continue;
            }

            if (is_string($this->$field)) {
                $value = trim($this->$field);

                $data[$field] =
                    $value === ''
                        ? null
                        : $value;
            } else {
                $data[$field] =
                    $this->$field;
            }
        }

        if (
            array_key_exists('email', $data)
            && is_string($data['email'])
        ) {
            $data['email'] =
                strtolower($data['email']);
        }

        if (
            array_key_exists('name', $data)
            && $data['name'] === null
        ) {
            $data['name'] = '';
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:190',
            ],

            'legal_name' => [
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

            'city' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
            ],

            'province' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
            ],

            'postal_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:32',
            ],

            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
            ],

            'whatsapp' => [
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

            'website' => [
                'sometimes',
                'nullable',
                'url:http,https',
                'max:255',
            ],

            'tax_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }
}
