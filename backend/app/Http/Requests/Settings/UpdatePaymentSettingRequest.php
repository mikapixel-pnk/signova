<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $fields = [
            'bank_name',
            'bank_account_number',
            'bank_account_name',
        ];

        $data = [];

        foreach ($fields as $field) {
            if ($this->exists($field)) {
                $value = $this->input($field);

                if (is_string($value)) {
                    $value = trim($value);
                    $value =
                        $value === ''
                            ? null
                            : $value;
                }

                $data[$field] = $value;
            }
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'bank_transfer_enabled' => [
                'sometimes',
                'boolean',
            ],

            'bank_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'bank_account_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'bank_account_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:190',
            ],

            'static_qr_enabled' => [
                'sometimes',
                'boolean',
            ],

            'partial_payment_enabled' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
