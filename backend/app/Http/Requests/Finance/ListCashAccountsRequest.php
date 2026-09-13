<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ListCashAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach ([
            'search',
            'status',
            'type',
        ] as $field) {
            if ($this->exists($field)) {
                $data[$field] = is_string($this->$field)
                    ? trim($this->$field)
                    : $this->$field;
            }
        }

        if (isset($data['status'])) {
            $data['status'] =
                strtoupper($data['status']);
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
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'max:190',
            ],
            'status' => [
                'sometimes',
                'nullable',
                'in:ACTIVE,INACTIVE',
            ],
            'type' => [
                'sometimes',
                'nullable',
                'in:CASH,BANK',
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
