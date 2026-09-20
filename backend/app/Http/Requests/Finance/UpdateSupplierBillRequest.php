<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierBillRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (
            [
                'supplier_invoice_number',
                'notes',
            ] as $field
        ) {
            if (
                $this->exists($field)
                && is_string($this->{$field})
            ) {
                $value =
                    trim($this->{$field});

                $this->merge([
                    $field =>
                        $value === ''
                            ? null
                            : $value,
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'supplier_invoice_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:190',
            ],

            'bill_date' => [
                'sometimes',
                'date',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'goods_receipt_id' => [
                'prohibited',
            ],

            'supplier_id' => [
                'prohibited',
            ],

            'purchase_order_id' => [
                'prohibited',
            ],

            'bill_number' => [
                'prohibited',
            ],

            'due_date' => [
                'prohibited',
            ],

            'currency' => [
                'prohibited',
            ],

            'subtotal' => [
                'prohibited',
            ],

            'discount_total' => [
                'prohibited',
            ],

            'tax_total' => [
                'prohibited',
            ],

            'total' => [
                'prohibited',
            ],

            'status' => [
                'prohibited',
            ],
        ];
    }
}
