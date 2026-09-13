<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListExpensesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:190',
            ],
            'status' => [
                'nullable',
                Rule::in([
                    'DRAFT',
                    'POSTED',
                    'VOID',
                ]),
            ],
            'cash_account_id' => [
                'nullable',
                'string',
                'max:26',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('status')) {
            $this->merge([
                'status' => strtoupper(
                    (string) $this->input('status')
                ),
            ]);
        }
    }
}
