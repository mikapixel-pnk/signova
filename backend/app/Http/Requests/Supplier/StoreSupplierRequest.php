<?php

namespace App\Http\Requests\Supplier;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
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
                'contact_name',
                'phone',
                'tax_id',
                'address',
                'city',
                'province',
                'notes',
            ] as $field
        ) {
            if ($this->exists($field)) {
                $data[$field] = is_string($this->$field)
                    ? trim($this->$field)
                    : $this->$field;
            }
        }

        if ($this->exists('email')) {
            $data['email'] = is_string($this->email)
                ? strtolower(trim($this->email))
                : $this->email;
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
                'nullable',
                'string',
                'max:80',
                Rule::unique(
                    'suppliers',
                    'code'
                )->where(
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
                ),
            ],
            'name' => [
                'required',
                'string',
                'max:190',
            ],
            'contact_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:190',
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:190',
            ],
            'tax_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'address' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'city' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
            ],
            'province' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
            ],
            'payment_terms_days' => [
                'sometimes',
                'integer',
                'min:0',
                'max:3650',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ];
    }
}
