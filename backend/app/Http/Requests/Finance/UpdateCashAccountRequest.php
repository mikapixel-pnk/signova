<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCashAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach ([
            'name',
            'type',
            'bank_name',
            'account_number',
            'account_name',
        ] as $field) {
            if ($this->exists($field)) {
                $data[$field] = is_string($this->$field)
                    ? trim($this->$field)
                    : $this->$field;
            }
        }

        if (isset($data['type'])) {
            $data['type'] =
                strtoupper($data['type']);
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
            'type' => [
                'sometimes',
                'required',
                'in:CASH,BANK',
            ],
            'bank_name' => [
                'sometimes',
                'nullable',
                'required_if:type,BANK',
                'string',
                'max:100',
            ],
            'account_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'account_name' => [
                'sometimes',
                'nullable',
                'required_if:type,BANK',
                'string',
                'max:190',
            ],
        ];
    }
}
