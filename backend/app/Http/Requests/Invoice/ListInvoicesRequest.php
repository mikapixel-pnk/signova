<?php

namespace App\Http\Requests\Invoice;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListInvoicesRequest extends FormRequest
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
                'search',
                'status',
                'customer_id',
            ] as $field
        ) {
            if ($this->exists($field)) {
                $data[$field] =
                    is_string($this->$field)
                        ? trim($this->$field)
                        : $this->$field;
            }
        }

        if (isset($data['status'])) {
            $data['status'] = strtoupper(
                $data['status']
            );
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $tenantId = app(
            TenantContext::class
        )->tenantId();

        return [
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'max:190',
            ],

            'status' => [
                'sometimes',
                'nullable',
                'in:DRAFT,ISSUED,PARTIALLY_PAID,PAID,OVERDUE,VOID',
            ],

            'customer_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists(
                    'customers',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'tenant_id',
                            $tenantId
                        )
                ),
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
