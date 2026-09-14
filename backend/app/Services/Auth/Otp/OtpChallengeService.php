<?php

namespace App\Services\Auth\Otp;

use App\Exceptions\Auth\OtpResendCooldownException;
use App\Exceptions\Auth\OtpVerificationException;
use App\Models\AuthOtpChallenge;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OtpChallengeService
{
    public const PURPOSE_LOGIN =
        'LOGIN';

    public const PURPOSE_PASSWORD_RESET =
        'PASSWORD_RESET';

    public const CHANNEL_WHATSAPP =
        'WHATSAPP';

    public const CHANNEL_EMAIL =
        'EMAIL';

    private const OTP_LENGTH = 6;

    private const EXPIRY_MINUTES = 5;

    private const RESEND_SECONDS = 60;

    private const MAX_ATTEMPTS = 5;

    public function issue(
        User $user,
        string $purpose,
        string $channel,
        string $deliveryTarget,
        ?string $requestIp = null,
        ?string $userAgent = null,
    ): IssuedOtpChallenge {
        $this->assertPurpose(
            $purpose
        );

        $this->assertChannel(
            $channel
        );

        return DB::transaction(
            function () use (
                $user,
                $purpose,
                $channel,
                $deliveryTarget,
                $requestIp,
                $userAgent,
            ): IssuedOtpChallenge {
                $now =
                    CarbonImmutable::now();

                $latest =
                    AuthOtpChallenge::query()
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->where(
                            'purpose',
                            $purpose
                        )
                        ->where(
                            'channel',
                            $channel
                        )
                        ->whereNull(
                            'consumed_at'
                        )
                        ->whereNull(
                            'invalidated_at'
                        )
                        ->orderByDesc(
                            'created_at'
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $latest
                    && $latest
                        ->resend_available_at
                        ->isFuture()
                ) {
                    $seconds =
                        max(
                            1,
                            $now->diffInSeconds(
                                $latest
                                    ->resend_available_at,
                                false
                            )
                        );

                    throw new OtpResendCooldownException(
                        $seconds
                    );
                }

                /*
                 * Any older still-active challenge
                 * becomes invalid before a new one
                 * is issued.
                 */
                AuthOtpChallenge::query()
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->where(
                        'purpose',
                        $purpose
                    )
                    ->where(
                        'channel',
                        $channel
                    )
                    ->whereNull(
                        'consumed_at'
                    )
                    ->whereNull(
                        'invalidated_at'
                    )
                    ->update([
                        'invalidated_at' =>
                            $now,
                        'updated_at' =>
                            $now,
                    ]);

                $code =
                    $this->generateCode();

                $expiresAt =
                    $now->addMinutes(
                        self::EXPIRY_MINUTES
                    );

                $resendAvailableAt =
                    $now->addSeconds(
                        self::RESEND_SECONDS
                    );

                $challenge =
                    AuthOtpChallenge::query()
                        ->create([
                            'user_id' =>
                                $user->id,

                            'purpose' =>
                                $purpose,

                            'channel' =>
                                $channel,

                            'code_hash' =>
                                Hash::make(
                                    $code
                                ),

                            'attempt_count' =>
                                0,

                            'max_attempts' =>
                                self::MAX_ATTEMPTS,

                            'expires_at' =>
                                $expiresAt,

                            'resend_available_at' =>
                                $resendAvailableAt,

                            'delivery_target_hash' =>
                                $this->hashMetadata(
                                    $deliveryTarget
                                ),

                            'requested_ip_hash' =>
                                $this->hashMetadata(
                                    $requestIp
                                ),

                            'user_agent_hash' =>
                                $this->hashMetadata(
                                    $userAgent
                                ),
                        ]);

                return new IssuedOtpChallenge(
                    challengeId:
                        (string) $challenge->id,

                    code:
                        $code,

                    expiresAt:
                        $expiresAt,

                    resendAvailableAt:
                        $resendAvailableAt,
                );
            }
        );
    }

    public function verify(
        string $challengeId,
        string $purpose,
        string $code,
    ): AuthOtpChallenge {
        $this->assertPurpose(
            $purpose
        );

        $result = DB::transaction(
            function () use (
                $challengeId,
                $purpose,
                $code,
            ): array {
                $challenge =
                    AuthOtpChallenge::query()
                        ->where(
                            'id',
                            $challengeId
                        )
                        ->where(
                            'purpose',
                            $purpose
                        )
                        ->lockForUpdate()
                        ->first();

                if (! $challenge) {
                    return [
                        'verified' => false,
                        'challenge' => null,
                    ];
                }

                if (
                    $challenge
                        ->consumed_at
                    || $challenge
                        ->invalidated_at
                    || $challenge
                        ->expires_at
                        ->isPast()
                    || $challenge
                        ->attempt_count
                        >= $challenge
                            ->max_attempts
                ) {
                    return [
                        'verified' => false,
                        'challenge' => null,
                    ];
                }

                if (
                    ! Hash::check(
                        $code,
                        $challenge
                            ->code_hash
                    )
                ) {
                    $challenge
                        ->attempt_count++;

                    if (
                        $challenge
                            ->attempt_count
                        >= $challenge
                            ->max_attempts
                    ) {
                        $challenge
                            ->invalidated_at =
                                now();
                    }

                    $challenge->save();

                    /*
                     * Do not throw inside the
                     * transaction. The failed
                     * attempt must be committed.
                     */
                    return [
                        'verified' => false,
                        'challenge' => null,
                    ];
                }

                $challenge->consumed_at =
                    now();

                $challenge->save();

                return [
                    'verified' => true,
                    'challenge' =>
                        $challenge->fresh(),
                ];
            }
        );

        if (
            ! $result['verified']
            || ! $result['challenge']
        ) {
            throw $this->invalidOtp();
        }

        return $result['challenge'];
    }

    private function generateCode(): string
    {
        return str_pad(
            (string) random_int(
                0,
                (10 ** self::OTP_LENGTH) - 1
            ),
            self::OTP_LENGTH,
            '0',
            STR_PAD_LEFT
        );
    }

    private function hashMetadata(
        ?string $value
    ): ?string {
        if (
            $value === null
            || trim($value) === ''
        ) {
            return null;
        }

        return hash_hmac(
            'sha256',
            strtolower(
                trim($value)
            ),
            (string) config(
                'app.key'
            )
        );
    }

    private function assertPurpose(
        string $purpose
    ): void {
        if (
            ! in_array(
                $purpose,
                [
                    self::PURPOSE_LOGIN,
                    self::PURPOSE_PASSWORD_RESET,
                ],
                true
            )
        ) {
            throw new \InvalidArgumentException(
                'Purpose OTP tidak valid.'
            );
        }
    }

    private function assertChannel(
        string $channel
    ): void {
        if (
            ! in_array(
                $channel,
                [
                    self::CHANNEL_WHATSAPP,
                    self::CHANNEL_EMAIL,
                ],
                true
            )
        ) {
            throw new \InvalidArgumentException(
                'Channel OTP tidak valid.'
            );
        }
    }

    private function invalidOtp():
        OtpVerificationException
    {
        return new OtpVerificationException(
            'Kode verifikasi tidak valid atau sudah tidak berlaku.'
        );
    }
}
