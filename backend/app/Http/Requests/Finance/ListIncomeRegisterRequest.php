<?php

namespace App\Http\Requests\Finance;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListIncomeRegisterRequest extends FormRequest
{
    public function rules(): array
    {
        $tenantId = app(
            TenantContext::class
        )->tenantId();

        $businessId = app(
            BusinessContext::class
        )->businessId();

        return [
            'search' => [
                'nullable',
                'string',
                'max:190',
            ],

            'group' => [
                'nullable',
                Rule::in([
                    'CUSTOMER_PAYMENT',
                    'MANUAL',
                    'POS',
                    'MARKETPLACE',
                    'OTHER',
                ]),
            ],

            'from' => [
                'nullable',
                'date',
            ],

            'to' => [
                'nullable',
                'date',
                'after_or_equal:from',
            ],

            'cash_account_id' => [
                'nullable',
                'string',
                'max:26',

                Rule::exists(
                    'cash_accounts',
                    'id'
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

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('group')) {
            $this->merge([
                'group' => strtoupper(
                    (string) $this->input(
                        'group'
                    )
                ),
            ]);
        }
    }
}
