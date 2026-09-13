<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class RejectPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('reason')) {
            return;
        }

        $reason = $this->input('reason');

        if (is_string($reason)) {
            $reason = trim($reason);
        }

        $this->merge([
            'reason' => $reason,
        ]);
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }
}
