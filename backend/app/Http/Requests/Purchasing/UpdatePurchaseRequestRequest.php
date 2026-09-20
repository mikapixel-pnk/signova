<?php

namespace App\Http\Requests\Purchasing;

class UpdatePurchaseRequestRequest extends StorePurchaseRequestRequest
{
    public function rules(): array
    {
        $rules =
            parent::rules();

        $rules['items'] = [
            'sometimes',
            'array',
            'min:1',
            'max:100',
        ];

        return $rules;
    }
}
