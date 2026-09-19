<?php

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class ListPurchaseRequestsRequest extends FormRequest
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
                'in:DRAFT,SUBMITTED,APPROVED,REJECTED,CANCELLED',
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
