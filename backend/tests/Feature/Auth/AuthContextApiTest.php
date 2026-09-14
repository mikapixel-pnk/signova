<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthContextApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_platform_only_user_can_login_without_tenant(): void
    {
        $user = $this->createUser(
            'platform-only@example.test'
        );

        $this->grantPlatformCapability(
            $user,
            'platform.dashboard.view'
        );

        $response = $this->postJson(
            '/api/v1/auth/login',
            [
                'email' =>
                    'platform-only@example.test',
                'password' =>
                    'SecurePassword123!',
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
                'data.requires_context_selection',
                false
            )
            ->assertJsonPath(
                'data.tenant',
                null
            );

        $this->assertSame(
            [],
            $response->json(
                'data.access.tenants'
            )
        );
    }

    public function test_single_tenant_user_keeps_tenant_default_context(): void
    {
        $register = $this->postJson(
            '/api/v1/auth/register',
            [
                'name' =>
                    'Tenant Context User',
                'email' =>
                    'tenant-context@example.test',
                'password' =>
                    'SecurePassword123!',
                'password_confirmation' =>
                    'SecurePassword123!',
                'tenant_name' =>
                    'Tenant Context Workspace',
            ]
        );

        $register->assertCreated();

        $response = $this->postJson(
            '/api/v1/auth/login',
            [
                'email' =>
                    'tenant-context@example.test',
                'password' =>
                    'SecurePassword123!',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.access.platform.available',
                false
            )
            ->assertJsonPath(
                'data.default_context.type',
                'TENANT'
            )
            ->assertJsonPath(
                'data.requires_context_selection',
                false
            )
            ->assertJsonPath(
                'data.tenant.name',
                'Tenant Context Workspace'
            );
    }

    public function test_platform_and_tenant_require_context_selection(): void
    {
        $register = $this->postJson(
            '/api/v1/auth/register',
            [
                'name' =>
                    'Dual Context User',
                'email' =>
                    'dual-context@example.test',
                'password' =>
                    'SecurePassword123!',
                'password_confirmation' =>
                    'SecurePassword123!',
                'tenant_name' =>
                    'Dual Context Workspace',
            ]
        );

        $register->assertCreated();

        $user = User::query()
            ->where(
                'email',
                'dual-context@example.test'
            )
            ->firstOrFail();

        $this->grantPlatformCapability(
            $user,
            'platform.dashboard.view'
        );

        $response = $this->postJson(
            '/api/v1/auth/login',
            [
                'email' =>
                    'dual-context@example.test',
                'password' =>
                    'SecurePassword123!',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.access.platform.available',
                true
            )
            ->assertJsonPath(
                'data.default_context',
                null
            )
            ->assertJsonPath(
                'data.requires_context_selection',
                true
            );
    }

    public function test_user_without_any_active_context_cannot_login(): void
    {
        $this->createUser(
            'no-context@example.test'
        );

        $this->postJson(
            '/api/v1/auth/login',
            [
                'email' =>
                    'no-context@example.test',
                'password' =>
                    'SecurePassword123!',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_authenticated_user_can_fetch_auth_context_without_tenant(): void
    {
        $user = $this->createUser(
            'platform-context@example.test'
        );

        $this->grantPlatformCapability(
            $user,
            'platform.dashboard.view'
        );

        $login = $this->postJson(
            '/api/v1/auth/login',
            [
                'email' =>
                    'platform-context@example.test',
                'password' =>
                    'SecurePassword123!',
            ]
        )->assertOk();

        $token = $login->json(
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

    private function createUser(
        string $email
    ): User {
        return User::query()->create([
            'name' => 'Auth Context Test',
            'email' => $email,
            'password' => Hash::make(
                'SecurePassword123!'
            ),
            'auth_status' => 'ACTIVE',
        ]);
    }

    private function grantPlatformCapability(
        User $user,
        string $code
    ): void {
        $capabilityId =
            (string) Str::ulid();

        $roleId =
            (string) Str::ulid();

        DB::table(
            'platform_capabilities'
        )->insert([
            'id' => $capabilityId,
            'code' => $code,
            'name' => 'Platform Test Capability',
            'is_sensitive' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(
            'platform_roles'
        )->insert([
            'id' => $roleId,
            'code' =>
                'TEST_PLATFORM_'
                . strtoupper(
                    substr(
                        (string) Str::ulid(),
                        -8
                    )
                ),
            'name' =>
                'Test Platform Role',
            'status' => 'ACTIVE',
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(
            'platform_role_capabilities'
        )->insert([
            'platform_role_id' =>
                $roleId,
            'platform_capability_id' =>
                $capabilityId,
            'effect' => 'ALLOW',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(
            'platform_user_roles'
        )->insert([
            'user_id' => $user->id,
            'platform_role_id' =>
                $roleId,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
