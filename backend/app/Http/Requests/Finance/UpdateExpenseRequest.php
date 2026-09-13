<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'cash_account_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:26',
            ],
            'amount' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],
            'incurred_at' => [
                'sometimes',
                'date',
            ],
            'category' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'description' => [
                'sometimes',
                'string',
                'max:5000',
            ],
        ];
    }
}
