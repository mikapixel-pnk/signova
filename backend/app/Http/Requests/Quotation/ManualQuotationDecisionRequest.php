<?php

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;

class ManualQuotationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (
            [
                'decision',
                'method',
                'reason',
                'note',
            ] as $field
        ) {
            if (! $this->exists($field)) {
                continue;
            }

            $value =
                $this->input(
                    $field
                );

            $data[$field] =
                is_string($value)
                    ? trim($value)
                    : $value;
        }

        if (isset($data['decision'])) {
            $data['decision'] =
                strtoupper(
                    $data['decision']
                );
        }

        if (isset($data['method'])) {
            $data['method'] =
                strtoupper(
                    $data['method']
                );
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'decision' => [
                'required',
                'in:APPROVE,REJECT',
            ],

            'method' => [
                'required',
                'in:SIGNATURE,WHATSAPP,EMAIL,PHONE,MEETING,OTHER',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:decision,REJECT',
            ],

            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'decided_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}
