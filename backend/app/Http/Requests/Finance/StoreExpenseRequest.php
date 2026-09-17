<?php

namespace App\Http\Requests\Finance;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
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
            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'incurred_at' => [
                'required',
                'date',
            ],
            'category' => [
                'nullable',
                'string',
                'max:100',
            ],
            'description' => [
                'required',
                'string',
                'max:5000',
            ],
        ];
    }
}
