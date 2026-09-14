<?php

namespace App\Services\Auth;

use App\Contracts\Messaging\WhatsAppDelivery;
use App\Exceptions\Auth\OtpResendCooldownException;
use App\Models\User;
use App\Services\Auth\Otp\OtpChallengeService;
use App\Support\Phone\IndonesianPhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class PasswordRecoveryService
{
    public function __construct(
        private readonly OtpChallengeService $otp,
        private readonly PasswordResetProofService $proofs,
        private readonly WhatsAppDelivery $whatsApp,
    ) {
    }

    public function request(
        string $identifier,
        ?string $requestIp = null,
        ?string $userAgent = null,
    ): string {
        /*
         * Always prepare an opaque reference
         * so public response shape does not
         * disclose account existence.
         */
        $opaqueRequestId =
            (string) Str::ulid();

        $identifier =
            strtolower(
                trim($identifier)
            );

        $user =
            User::query()
                ->whereRaw(
                    'LOWER(email) = ?',
                    [$identifier]
                )
                ->first();

        if (
            ! $user
            || $user->auth_status
                !== 'ACTIVE'
            || ! $user->phone
        ) {
            return $opaqueRequestId;
        }

        try {
            $phone =
                IndonesianPhoneNumber::normalize(
                    $user->phone
                );

            if (! $phone) {
                return $opaqueRequestId;
            }

            $issued =
                $this->otp->issue(
                    $user,
                    OtpChallengeService::PURPOSE_PASSWORD_RESET,
                    OtpChallengeService::CHANNEL_WHATSAPP,
                    $phone,
                    $requestIp,
                    $userAgent
                );

            $message =
                "Kode verifikasi SIGNOVA Anda: "
                . $issued->code
                . ". Berlaku 5 menit. "
                . "Jangan berikan kode ini kepada siapa pun.";

            $this->whatsApp->send(
                $phone,
                $message
            );

            return $issued
                ->challengeId;
        } catch (
            OtpResendCooldownException
        ) {
            return $opaqueRequestId;
        } catch (Throwable) {
            return $opaqueRequestId;
        }
    }

    public function verify(
        string $challengeId,
        string $code
    ): string {
        $challenge =
            $this->otp->verify(
                $challengeId,
                OtpChallengeService::PURPOSE_PASSWORD_RESET,
                $code
            );

        return $this->proofs->issue(
            $challenge
        );
    }

    public function reset(
        string $proofToken,
        string $password
    ): void {
        $proof =
            $this->proofs->consume(
                $proofToken
            );

        $user = User::query()
            ->findOrFail(
                $proof->user_id
            );

        $user->password =
            Hash::make(
                $password
            );

        $user->save();

        $user->tokens()->delete();
    }
}
