<?php

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class ListPurchaseOrdersRequest extends FormRequest
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
                'in:DRAFT,ISSUED,PARTIALLY_RECEIVED,RECEIVED,CANCELLED',
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
