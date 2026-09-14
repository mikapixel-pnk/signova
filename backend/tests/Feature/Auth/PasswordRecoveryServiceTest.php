<?php

namespace Tests\Feature\Auth;

use App\Contracts\Messaging\WhatsAppDelivery;
use App\Exceptions\Auth\PasswordResetProofException;
use App\Models\AuthOtpChallenge;
use App\Models\AuthPasswordResetProof;
use App\Models\User;
use App\Services\Auth\Otp\OtpChallengeService;
use App\Services\Auth\PasswordRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordRecoveryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_for_unknown_account_remains_silent(): void
    {
        $fake = new class implements WhatsAppDelivery
        {
            public int $sent = 0;

            public function send(
                string $number,
                string $message
            ): void {
                $this->sent++;
            }
        };

        $this->app->instance(
            WhatsAppDelivery::class,
            $fake
        );

        app(
            PasswordRecoveryService::class
        )->request(
            'unknown@example.test',
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertSame(
            0,
            $fake->sent
        );

        $this->assertSame(
            0,
            AuthOtpChallenge::query()->count()
        );
    }

    public function test_request_sends_password_reset_otp_to_active_user(): void
    {
        $user = $this->user();

        $fake = new class implements WhatsAppDelivery
        {
            public int $sent = 0;

            public ?string $number = null;

            public ?string $message = null;

            public function send(
                string $number,
                string $message
            ): void {
                $this->sent++;
                $this->number = $number;
                $this->message = $message;
            }
        };

        $this->app->instance(
            WhatsAppDelivery::class,
            $fake
        );

        app(
            PasswordRecoveryService::class
        )->request(
            $user->email,
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertSame(
            1,
            $fake->sent
        );

        $this->assertSame(
            '6281234567890',
            $fake->number
        );

        $this->assertNotNull(
            $fake->message
        );

        $this->assertSame(
            1,
            AuthOtpChallenge::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'purpose',
                    OtpChallengeService::PURPOSE_PASSWORD_RESET
                )
                ->count()
        );
    }

    public function test_verified_otp_issues_single_use_reset_proof(): void
    {
        $user = $this->user();

        $otp =
            app(
                OtpChallengeService::class
            )->issue(
                $user,
                OtpChallengeService::PURPOSE_PASSWORD_RESET,
                OtpChallengeService::CHANNEL_WHATSAPP,
                $user->phone
            );

        $proofToken =
            app(
                PasswordRecoveryService::class
            )->verify(
                $otp->challengeId,
                $otp->code
            );

        $this->assertNotSame(
            '',
            $proofToken
        );

        $this->assertSame(
            1,
            AuthPasswordResetProof::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->count()
        );
    }

    public function test_reset_changes_password_and_revokes_existing_tokens(): void
    {
        $user = $this->user();

        $user->createToken(
            'existing-session'
        );

        $otp =
            app(
                OtpChallengeService::class
            )->issue(
                $user,
                OtpChallengeService::PURPOSE_PASSWORD_RESET,
                OtpChallengeService::CHANNEL_WHATSAPP,
                $user->phone
            );

        $proofToken =
            app(
                PasswordRecoveryService::class
            )->verify(
                $otp->challengeId,
                $otp->code
            );

        app(
            PasswordRecoveryService::class
        )->reset(
            $proofToken,
            'NewSecurePassword123!'
        );

        $user->refresh();

        $this->assertTrue(
            Hash::check(
                'NewSecurePassword123!',
                $user->password
            )
        );

        $this->assertSame(
            0,
            $user->tokens()->count()
        );
    }

    public function test_reset_proof_cannot_be_reused(): void
    {
        $user = $this->user();

        $otp =
            app(
                OtpChallengeService::class
            )->issue(
                $user,
                OtpChallengeService::PURPOSE_PASSWORD_RESET,
                OtpChallengeService::CHANNEL_WHATSAPP,
                $user->phone
            );

        $proofToken =
            app(
                PasswordRecoveryService::class
            )->verify(
                $otp->challengeId,
                $otp->code
            );

        app(
            PasswordRecoveryService::class
        )->reset(
            $proofToken,
            'NewSecurePassword123!'
        );

        $this->expectException(
            PasswordResetProofException::class
        );

        app(
            PasswordRecoveryService::class
        )->reset(
            $proofToken,
            'AnotherPassword123!'
        );
    }

    private function user(): User
    {
        return User::query()
            ->create([
                'name' =>
                    'Recovery Test User',

                'email' =>
                    fake()
                        ->unique()
                        ->safeEmail(),

                'phone' =>
                    '081234567890',

                'password' =>
                    'OldPassword123!',

                'auth_status' =>
                    'ACTIVE',
            ]);
    }
}
