<?php

namespace App\Http\Requests\Quotation;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if (
                    ! $this->exists('customer_id')
                    && ! $this->exists('valid_until')
                ) {
                    $validator->errors()->add(
                        'quotation',
                        'Minimal satu data penawaran harus diubah.'
                    );
                }
            },
        ];
    }

    public function rules(): array
    {
        $tenantId = app(
            TenantContext::class
        )->tenantId();

        return [
            'customer_id' => [
                'sometimes',
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

            'valid_until' => [
                'sometimes',
                'nullable',
                'date',
            ],

            /*
             * Tidak boleh diubah melalui header PATCH.
             */
            'quotation_number' => ['prohibited'],
            'status' => ['prohibited'],
            'owner_user_id' => ['prohibited'],
            'source' => ['prohibited'],
            'items' => ['prohibited'],
            'currency' => ['prohibited'],
            'terms' => ['prohibited'],
            'notes' => ['prohibited'],
            'subtotal' => ['prohibited'],
            'discount_total' => ['prohibited'],
            'tax_total' => ['prohibited'],
            'total' => ['prohibited'],
        ];
    }
}
