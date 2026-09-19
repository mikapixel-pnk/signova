<?php

namespace App\Http\Requests\Finance;

class RecordExpenseRequest extends StoreExpenseRequest
{
    public function rules(): array
    {
        $rules =
            parent::rules();

        $cashAccountRules =
            array_values(
                array_filter(
                    $rules['cash_account_id'],
                    fn ($rule) =>
                        $rule !== 'nullable'
                )
            );

        array_unshift(
            $cashAccountRules,
            'required'
        );

        $rules['cash_account_id'] =
            $cashAccountRules;

        return $rules;
    }
}
