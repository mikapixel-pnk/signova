<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class ListCatalogItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (
            ['search', 'status', 'type', 'pricing_method']
            as $field
        ) {
            if ($this->exists($field)) {
                $data[$field] = is_string($this->$field)
                    ? trim($this->$field)
                    : $this->$field;
            }
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
            'type' => [
                'sometimes',
                'in:PRODUCT,SERVICE',
            ],
            'pricing_method' => [
                'sometimes',
                'in:STANDARD,AREA,LENGTH,VOLUME,TIME,PACKAGE,MANUAL',
            ],
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
        ];
    }
}
