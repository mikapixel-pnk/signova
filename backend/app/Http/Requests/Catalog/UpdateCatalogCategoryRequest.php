<?php

namespace App\Http\Requests\Catalog;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCatalogCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(
            TenantContext::class
        )->tenantId();

        return [
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:80',
                Rule::unique(
                    'catalog_categories',
                    'code'
                )
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'tenant_id',
                                $tenantId
                            )
                    )
                    ->ignore(
                        $this->route('categoryId'),
                        'id'
                    ),
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:160',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],
            'status' => [
                'sometimes',
                'in:ACTIVE,INACTIVE',
            ],
        ];
    }
}
