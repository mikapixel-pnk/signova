<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordRecoveryRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear(
            'unused'
        );
    }

    public function test_forgot_password_is_rate_limited_by_identifier(): void
    {
        $payload = [
            'identifier' =>
                'unknown@example.test',
        ];

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(
                '/api/v1/auth/password/forgot',
                $payload
            )->assertOk();
        }

        $this->postJson(
            '/api/v1/auth/password/forgot',
            $payload
        )
            ->assertStatus(429)
            ->assertJsonPath(
                'error.code',
                'RATE_LIMITED'
            );
    }

    public function test_verify_endpoint_is_rate_limited_by_challenge(): void
    {
        $payload = [
            'challenge_id' =>
                '01TESTINVALIDCHALLENGE00000',

            'code' =>
                '123456',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(
                '/api/v1/auth/password/verify',
                $payload
            )->assertStatus(422);
        }

        $this->postJson(
            '/api/v1/auth/password/verify',
            $payload
        )
            ->assertStatus(429)
            ->assertJsonPath(
                'error.code',
                'RATE_LIMITED'
            );
    }

    public function test_reset_endpoint_is_rate_limited_by_proof(): void
    {
        $payload = [
            'reset_proof' =>
                'invalid-proof',

            'password' =>
                'NewSecurePassword123!',

            'password_confirmation' =>
                'NewSecurePassword123!',
        ];

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(
                '/api/v1/auth/password/reset',
                $payload
            )->assertStatus(422);
        }

        $this->postJson(
            '/api/v1/auth/password/reset',
            $payload
        )
            ->assertStatus(429)
            ->assertJsonPath(
                'error.code',
                'RATE_LIMITED'
            );
    }
}
