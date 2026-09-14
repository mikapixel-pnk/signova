<?php

namespace Tests\Feature\Auth;

use App\Exceptions\Auth\OtpResendCooldownException;
use App\Exceptions\Auth\OtpVerificationException;
use App\Models\AuthOtpChallenge;
use App\Models\User;
use App\Services\Auth\Otp\OtpChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OtpChallengeServiceTest extends TestCase
{
    use RefreshDatabase;

    private OtpChallengeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service =
            app(
                OtpChallengeService::class
            );
    }

    public function test_otp_is_stored_as_hash_and_not_plaintext(): void
    {
        $user =
            $this->user();

        $issued =
            $this->service->issue(
                $user,
                OtpChallengeService::PURPOSE_PASSWORD_RESET,
                OtpChallengeService::CHANNEL_WHATSAPP,
                '628123456789'
            );

        $challenge =
            AuthOtpChallenge::query()
                ->findOrFail(
                    $issued->challengeId
                );

        $this->assertNotSame(
            $issued->code,
            $challenge->code_hash
        );

        $this->assertTrue(
            Hash::check(
                $issued->code,
                $challenge->code_hash
            )
        );

        $this->assertSame(
            6,
            strlen(
                $issued->code
            )
        );
    }

    public function test_valid_otp_is_consumed_once(): void
    {
        $issued =
            $this->service->issue(
                $this->user(),
                OtpChallengeService::PURPOSE_LOGIN,
                OtpChallengeService::CHANNEL_WHATSAPP,
                '628123456789'
            );

        $verified =
            $this->service->verify(
                $issued->challengeId,
                OtpChallengeService::PURPOSE_LOGIN,
                $issued->code
            );

        $this->assertNotNull(
            $verified->consumed_at
        );

        $this->expectException(
            OtpVerificationException::class
        );

        $this->service->verify(
            $issued->challengeId,
            OtpChallengeService::PURPOSE_LOGIN,
            $issued->code
        );
    }

    public function test_wrong_otp_increments_attempts_and_eventually_invalidates(): void
    {
        $issued =
            $this->service->issue(
                $this->user(),
                OtpChallengeService::PURPOSE_LOGIN,
                OtpChallengeService::CHANNEL_WHATSAPP,
                '628123456789'
            );

        for ($i = 1; $i <= 5; $i++) {
            try {
                $this->service->verify(
                    $issued->challengeId,
                    OtpChallengeService::PURPOSE_LOGIN,
                    '999999'
                );
            } catch (
                OtpVerificationException
            ) {
                // Expected.
            }
        }

        $challenge =
            AuthOtpChallenge::query()
                ->findOrFail(
                    $issued->challengeId
                );

        $this->assertSame(
            5,
            $challenge->attempt_count
        );

        $this->assertNotNull(
            $challenge->invalidated_at
        );
    }

    public function test_resend_cooldown_is_enforced(): void
    {
        $user =
            $this->user();

        $this->service->issue(
            $user,
            OtpChallengeService::PURPOSE_PASSWORD_RESET,
            OtpChallengeService::CHANNEL_WHATSAPP,
            '628123456789'
        );

        $this->expectException(
            OtpResendCooldownException::class
        );

        $this->service->issue(
            $user,
            OtpChallengeService::PURPOSE_PASSWORD_RESET,
            OtpChallengeService::CHANNEL_WHATSAPP,
            '628123456789'
        );
    }

    public function test_new_challenge_invalidates_previous_after_cooldown(): void
    {
        $user =
            $this->user();

        $first =
            $this->service->issue(
                $user,
                OtpChallengeService::PURPOSE_PASSWORD_RESET,
                OtpChallengeService::CHANNEL_WHATSAPP,
                '628123456789'
            );

        AuthOtpChallenge::query()
            ->where(
                'id',
                $first->challengeId
            )
            ->update([
                'resend_available_at' =>
                    now()->subSecond(),
            ]);

        $second =
            $this->service->issue(
                $user,
                OtpChallengeService::PURPOSE_PASSWORD_RESET,
                OtpChallengeService::CHANNEL_WHATSAPP,
                '628123456789'
            );

        $this->assertNotSame(
            $first->challengeId,
            $second->challengeId
        );

        $this->assertNotNull(
            AuthOtpChallenge::query()
                ->findOrFail(
                    $first->challengeId
                )
                ->invalidated_at
        );
    }

    public function test_expired_otp_is_rejected(): void
    {
        $issued =
            $this->service->issue(
                $this->user(),
                OtpChallengeService::PURPOSE_LOGIN,
                OtpChallengeService::CHANNEL_WHATSAPP,
                '628123456789'
            );

        AuthOtpChallenge::query()
            ->where(
                'id',
                $issued->challengeId
            )
            ->update([
                'expires_at' =>
                    now()->subSecond(),
            ]);

        $this->expectException(
            OtpVerificationException::class
        );

        $this->service->verify(
            $issued->challengeId,
            OtpChallengeService::PURPOSE_LOGIN,
            $issued->code
        );
    }

    private function user(): User
    {
        return User::query()
            ->create([
                'name' =>
                    'OTP Test User',

                'email' =>
                    fake()->unique()
                        ->safeEmail(),

                'phone' =>
                    '628123456789',

                'password' =>
                    'SecurePassword123!',

                'auth_status' =>
                    'ACTIVE',
            ]);
    }
}
