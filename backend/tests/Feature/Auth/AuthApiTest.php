<?php

namespace Tests\Feature\Auth;

use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SignovaAccessControlSeeder::class);
    }

    public function test_user_can_register_and_receive_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Owner API',
            'email' => 'owner-api@example.test',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'tenant_name' => 'API Workspace',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.user.email',
                'owner-api@example.test'
            )
            ->assertJsonPath(
                'data.tenant.name',
                'API Workspace'
            )
            ->assertJsonPath(
                'data.token_type',
                'Bearer'
            );

        $this->assertNotEmpty(
            $response->json('data.token')
        );

        $this->assertDatabaseHas('users', [
            'email' => 'owner-api@example.test',
        ]);

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1
        );
    }

    public function test_duplicate_registration_is_rejected(): void
    {
        $payload = [
            'name' => 'Owner API',
            'email' => 'duplicate-api@example.test',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'tenant_name' => 'Duplicate Workspace',
        ];

        $this->postJson(
            '/api/v1/auth/register',
            $payload
        )->assertCreated();

        $this->postJson(
            '/api/v1/auth/register',
            $payload
        )->assertUnprocessable();

        $this->assertSame(
            1,
            DB::table('users')
                ->where(
                    'email',
                    'duplicate-api@example.test'
                )
                ->count()
        );
    }

    public function test_user_can_login_me_and_logout(): void
    {
        $register = $this->postJson(
            '/api/v1/auth/register',
            [
                'name' => 'Login Test',
                'email' => 'login@example.test',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
                'tenant_name' => 'Login Workspace',
            ]
        );

        $register->assertCreated();

        $login = $this->postJson(
            '/api/v1/auth/login',
            [
                'email' => 'login@example.test',
                'password' => 'SecurePassword123!',
                'device_name' => 'phpunit',
            ]
        );

        $login
            ->assertOk()
            ->assertJsonPath(
                'data.user.email',
                'login@example.test'
            )
            ->assertJsonPath(
                'data.tenant.name',
                'Login Workspace'
            );

        $token = $login->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath(
                'data.user.email',
                'login@example.test'
            );

        $tokenId = (int) explode('|', $token, 2)[0];

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);

        // Laravel feature tests reuse the application instance.
        // Reset the resolved auth guard before retrying the revoked token.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_invalid_password_is_rejected(): void
    {
        $this->postJson(
            '/api/v1/auth/register',
            [
                'name' => 'Password Test',
                'email' => 'password@example.test',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
                'tenant_name' => 'Password Workspace',
            ]
        )->assertCreated();

        $this->postJson(
            '/api/v1/auth/login',
            [
                'email' => 'password@example.test',
                'password' => 'wrong-password',
            ]
        )->assertUnprocessable();
    }

    public function test_unauthenticated_user_cannot_access_me(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
