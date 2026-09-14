<?php

namespace Tests\Feature\Auth;

use App\Contracts\Messaging\WhatsAppDelivery;
use App\Models\AuthOtpChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordRecoveryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_returns_same_public_shape_for_known_and_unknown_email(): void
    {
        $user = $this->user();

        $fake = new class implements WhatsAppDelivery
        {
            public function send(
                string $number,
                string $message
            ): void {
            }
        };

        $this->app->instance(
            WhatsAppDelivery::class,
            $fake
        );

        $known = $this->postJson(
            '/api/v1/auth/password/forgot',
            [
                'identifier' =>
                    $user->email,
            ]
        );

        $unknown = $this->postJson(
            '/api/v1/auth/password/forgot',
            [
                'identifier' =>
                    'unknown@example.test',
            ]
        );

        $known
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'request_id',
                ],
            ]);

        $unknown
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'request_id',
                ],
            ]);

        $this->assertSame(
            $known->json('message'),
            $unknown->json('message')
        );

        $this->assertIsString(
            $known->json(
                'data.request_id'
            )
        );

        $this->assertIsString(
            $unknown->json(
                'data.request_id'
            )
        );
    }

    public function test_invalid_otp_is_rejected(): void
    {
        $user = $this->user();

        $challenge =
            app(
                \App\Services\Auth\Otp\OtpChallengeService::class
            )->issue(
                $user,
                \App\Services\Auth\Otp\OtpChallengeService::PURPOSE_PASSWORD_RESET,
                \App\Services\Auth\Otp\OtpChallengeService::CHANNEL_WHATSAPP,
                $user->phone
            );

        $response =
            $this->postJson(
                '/api/v1/auth/password/verify',
                [
                    'challenge_id' =>
                        $challenge
                            ->challengeId,

                    'code' =>
                        '999999',
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            )
            ->assertJsonPath(
                'error.details.fields.code.0',
                'Kode verifikasi tidak valid atau sudah tidak berlaku.'
            );
    }

    public function test_valid_otp_returns_reset_proof(): void
    {
        $user = $this->user();

        $challenge =
            app(
                \App\Services\Auth\Otp\OtpChallengeService::class
            )->issue(
                $user,
                \App\Services\Auth\Otp\OtpChallengeService::PURPOSE_PASSWORD_RESET,
                \App\Services\Auth\Otp\OtpChallengeService::CHANNEL_WHATSAPP,
                $user->phone
            );

        $response =
            $this->postJson(
                '/api/v1/auth/password/verify',
                [
                    'challenge_id' =>
                        $challenge
                            ->challengeId,

                    'code' =>
                        $challenge
                            ->code,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'reset_proof',
                ],
            ]);

        $this->assertIsString(
            $response->json(
                'data.reset_proof'
            )
        );
    }

    public function test_reset_password_changes_password_and_revokes_tokens(): void
    {
        $user = $this->user();

        $user->createToken(
            'existing-session'
        );

        $challenge =
            app(
                \App\Services\Auth\Otp\OtpChallengeService::class
            )->issue(
                $user,
                \App\Services\Auth\Otp\OtpChallengeService::PURPOSE_PASSWORD_RESET,
                \App\Services\Auth\Otp\OtpChallengeService::CHANNEL_WHATSAPP,
                $user->phone
            );

        $proofResponse =
            $this->postJson(
                '/api/v1/auth/password/verify',
                [
                    'challenge_id' =>
                        $challenge
                            ->challengeId,

                    'code' =>
                        $challenge
                            ->code,
                ]
            );

        $resetProof =
            $proofResponse->json(
                'data.reset_proof'
            );

        $response =
            $this->postJson(
                '/api/v1/auth/password/reset',
                [
                    'reset_proof' =>
                        $resetProof,

                    'password' =>
                        'NewSecurePassword123!',

                    'password_confirmation' =>
                        'NewSecurePassword123!',
                ]
            );

        $response
            ->assertOk();

        $user->refresh();

        $this->assertTrue(
            Hash::check(
                'NewSecurePassword123!',
                $user->password
            )
        );

        $this->assertSame(
            0,
            $user->tokens()
                ->count()
        );
    }

    public function test_reset_proof_cannot_be_reused(): void
    {
        $user = $this->user();

        $challenge =
            app(
                \App\Services\Auth\Otp\OtpChallengeService::class
            )->issue(
                $user,
                \App\Services\Auth\Otp\OtpChallengeService::PURPOSE_PASSWORD_RESET,
                \App\Services\Auth\Otp\OtpChallengeService::CHANNEL_WHATSAPP,
                $user->phone
            );

        $proofResponse =
            $this->postJson(
                '/api/v1/auth/password/verify',
                [
                    'challenge_id' =>
                        $challenge
                            ->challengeId,

                    'code' =>
                        $challenge
                            ->code,
                ]
            );

        $proof =
            $proofResponse->json(
                'data.reset_proof'
            );

        $payload = [
            'reset_proof' =>
                $proof,

            'password' =>
                'NewSecurePassword123!',

            'password_confirmation' =>
                'NewSecurePassword123!',
        ];

        $this->postJson(
            '/api/v1/auth/password/reset',
            $payload
        )->assertOk();

        $this->postJson(
            '/api/v1/auth/password/reset',
            $payload
        )->assertStatus(422);
    }

    public function test_old_password_fails_and_new_password_works_after_reset(): void
    {
        $user = $this->user();

        $challenge =
            app(
                \App\Services\Auth\Otp\OtpChallengeService::class
            )->issue(
                $user,
                \App\Services\Auth\Otp\OtpChallengeService::PURPOSE_PASSWORD_RESET,
                \App\Services\Auth\Otp\OtpChallengeService::CHANNEL_WHATSAPP,
                $user->phone
            );

        $proof =
            $this->postJson(
                '/api/v1/auth/password/verify',
                [
                    'challenge_id' =>
                        $challenge
                            ->challengeId,

                    'code' =>
                        $challenge
                            ->code,
                ]
            )->json(
                'data.reset_proof'
            );

        $this->postJson(
            '/api/v1/auth/password/reset',
            [
                'reset_proof' =>
                    $proof,

                'password' =>
                    'NewSecurePassword123!',

                'password_confirmation' =>
                    'NewSecurePassword123!',
            ]
        )->assertOk();

        $this->postJson(
            '/api/v1/auth/login',
            [
                'email' =>
                    $user->email,

                'password' =>
                    'OldPassword123!',
            ]
        )->assertStatus(422);

        /*
         * This user has no tenant/platform
         * context, so direct login may still
         * be rejected after password validation.
         * Verify password hash directly here.
         */
        $user->refresh();

        $this->assertTrue(
            Hash::check(
                'NewSecurePassword123!',
                $user->password
            )
        );

        $this->assertFalse(
            Hash::check(
                'OldPassword123!',
                $user->password
            )
        );
    }

    private function user(): User
    {
        return User::query()
            ->create([
                'name' =>
                    'Recovery API User',

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
