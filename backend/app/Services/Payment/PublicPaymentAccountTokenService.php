<?php

namespace App\Services\Payment;

use App\Models\CashAccount;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublicPaymentAccountTokenService
{
    public function issue(
        CashAccount $account
    ): string {
        return Crypt::encryptString(
            json_encode(
                [
                    'v' => 1,

                    'tenant_id' =>
                        $account->tenant_id,

                    'business_id' =>
                        $account->business_id,

                    'cash_account_id' =>
                        $account->id,
                ],
                JSON_THROW_ON_ERROR
            )
        );
    }

    public function decode(
        string $presentedToken
    ): array {
        try {
            $payload =
                json_decode(
                    Crypt::decryptString(
                        $presentedToken
                    ),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'payment_account_token' => [
                    'Rekening tujuan pembayaran tidak valid.',
                ],
            ]);
        }

        if (
            ! is_array($payload)
            || ($payload['v'] ?? null) !== 1
            || ! is_string(
                $payload['tenant_id']
                ?? null
            )
            || ! is_string(
                $payload['business_id']
                ?? null
            )
            || ! is_string(
                $payload['cash_account_id']
                ?? null
            )
        ) {
            throw ValidationException::withMessages([
                'payment_account_token' => [
                    'Rekening tujuan pembayaran tidak valid.',
                ],
            ]);
        }

        return [
            'tenant_id' =>
                $payload['tenant_id'],

            'business_id' =>
                $payload['business_id'],

            'cash_account_id' =>
                $payload['cash_account_id'],
        ];
    }
}
