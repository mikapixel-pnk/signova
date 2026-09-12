<?php

namespace App\Http\Requests\Catalog;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCatalogCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (
            ['code', 'name', 'description']
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
        $tenantId = app(
            TenantContext::class
        )->tenantId();

        return [
            'code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique(
                    'catalog_categories',
                    'code'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'tenant_id',
                            $tenantId
                        )
                ),
            ],
            'name' => [
                'required',
                'string',
                'max:160',
            ],
            'description' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}
