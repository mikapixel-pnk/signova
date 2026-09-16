<?php

namespace App\Http\Requests\Catalog;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
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
                'code',
                'name',
                'symbol',
                'unit_type',
                'status',
            ] as $field
        ) {
            if ($this->exists($field)) {
                $data[$field] = is_string($this->$field)
                    ? trim($this->$field)
                    : $this->$field;
            }
        }

        if (isset($data['code'])) {
            $data['code'] = strtoupper(
                $data['code']
            );
        }

        if (isset($data['unit_type'])) {
            $data['unit_type'] = strtoupper(
                $data['unit_type']
            );
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $tenantId = app(
            TenantContext::class
        )->tenantId();

        $businessId = app(
            BusinessContext::class
        )->businessId();

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:80',
                Rule::unique(
                    'units',
                    'code'
                )
                    ->where(
                        fn ($query) =>
                            $query
                                ->where(
                                    'tenant_id',
                                    $tenantId
                                )
                                ->where(
                                    'business_id',
                                    $businessId
                                )
                    )
                    ->ignore(
                        $this->route('unitId'),
                        'id'
                    ),
            ],

            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
            ],

            'symbol' => [
                'sometimes',
                'nullable',
                'string',
                'max:40',
            ],

            'unit_type' => [
                'sometimes',
                'in:COUNT,LENGTH,AREA,VOLUME,TIME,PACKAGE,OTHER',
            ],

            'decimal_precision' => [
                'sometimes',
                'integer',
                'min:0',
                'max:6',
            ],

            'status' => [
                'sometimes',
                'in:ACTIVE,INACTIVE',
            ],
        ];
    }
}
