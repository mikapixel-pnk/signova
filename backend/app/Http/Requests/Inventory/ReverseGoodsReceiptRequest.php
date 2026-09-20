<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ReverseGoodsReceiptRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (
            $this->exists('reason')
            && is_string($this->reason)
        ) {
            $this->merge([
                'reason' =>
                    trim($this->reason),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:3',
                'max:5000',
            ],
        ];
    }
}
