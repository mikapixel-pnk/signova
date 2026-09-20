<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ListSupplierPaymentsRequest extends FormRequest
{
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
                'in:DRAFT,POSTED,REVERSED',
            ],

            'supplier_id' => [
                'sometimes',
                'nullable',
                'string',
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
