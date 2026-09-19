<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class ListSuppliersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->exists('search')) {
            $data['search'] = is_string($this->search)
                ? trim($this->search)
                : $this->search;
        }

        if ($this->exists('status')) {
            $data['status'] = is_string($this->status)
                ? trim($this->status)
                : $this->status;
        }

        $this->merge($data);
    }

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
                'in:ACTIVE,INACTIVE',
            ],
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
        ];
    }
}
