<?php

namespace App\Http\Requests\Purchasing;

class UpdatePurchaseOrderRequest extends StorePurchaseOrderRequest
{
    public function rules(): array
    {
        $rules =
            parent::rules();

        $rules['supplier_id'] = [
            'sometimes',
            ...array_slice(
                $rules['supplier_id'],
                1
            ),
        ];

        $rules['source_purchase_request_id'] = [
            'prohibited',
        ];

        $rules['items'] = [
            'sometimes',
            'array',
            'min:1',
            'max:100',
        ];

        return $rules;
    }
}
