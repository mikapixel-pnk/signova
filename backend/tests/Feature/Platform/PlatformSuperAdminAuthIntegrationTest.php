<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Database\Seeders\PlatformAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSuperAdminAuthIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_super_admin_can_login_as_platform_context(): void
    {
        $this->seed(
            PlatformAccessControlSeeder::class
        );

        User::query()->create([
            'name' =>
                'SIGNOVA Super Admin Test',
            'email' =>
                'super-admin-auth@example.test',
            'password' =>
                'SecurePassword123!',
            'auth_status' =>
                'ACTIVE',
        ]);

        $this->artisan(
            'platform:grant-super-admin',
            [
                'email' =>
                    'super-admin-auth@example.test',
            ]
        )->assertSuccessful();

        $response = $this->postJson(
            '/api/v1/auth/login',
            [
                'email' =>
                    'super-admin-auth@example.test',
                'password' =>
                    'SecurePassword123!',
                'device_name' =>
                    'platform-auth-test',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.access.platform.available',
                true
            )
            ->assertJsonPath(
                'data.default_context.type',
                'PLATFORM'
            )
            ->assertJsonPath(
                'data.default_context.tenant_id',
                null
            )
            ->assertJsonPath(
                'data.requires_context_selection',
                false
            )
            ->assertJsonPath(
                'data.tenant',
                null
            );

        $capabilities = $response->json(
            'data.access.platform.capabilities'
        );

        $this->assertContains(
            'platform.dashboard.view',
            $capabilities
        );

        $this->assertContains(
            'platform.tenant.manage',
            $capabilities
        );

        $this->assertContains(
            'platform.settings.manage',
            $capabilities
        );

        $token = $response->json(
            'data.token'
        );

        $this->withToken($token)
            ->getJson(
                '/api/v1/auth/context'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.access.platform.available',
                true
            )
            ->assertJsonPath(
                'data.default_context.type',
                'PLATFORM'
            );
    }
}
