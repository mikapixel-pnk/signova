<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncomeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'cash_account_id' => [
                'nullable',
                'string',
                'max:26',
            ],
            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'occurred_at' => [
                'required',
                'date',
            ],
            'category' => [
                'nullable',
                'string',
                'max:100',
            ],
            'description' => [
                'required',
                'string',
                'max:5000',
            ],
            'reference' => [
                'nullable',
                'string',
                'max:190',
            ],
        ];
    }
}
