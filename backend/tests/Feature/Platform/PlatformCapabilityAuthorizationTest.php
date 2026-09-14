<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformCapabilityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([
            'auth:sanctum',
            'platform.capability:platform.dashboard.view',
        ])->get(
            '/api/v1/test/platform-dashboard',
            fn () => response()->json([
                'ok' => true,
            ])
        );
    }

    public function test_platform_capability_allows_access(): void
    {
        $user = $this->createPlatformUser(
            'allow-platform@example.test',
            'ALLOW'
        );

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/v1/test/platform-dashboard'
        )
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_missing_platform_capability_is_denied(): void
    {
        $user = $this->createUser(
            'missing-platform@example.test'
        );

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/v1/test/platform-dashboard'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_PLATFORM_CAPABILITY'
            );
    }

    public function test_platform_deny_overrides_allow(): void
    {
        $user = $this->createPlatformUser(
            'deny-platform@example.test',
            'ALLOW'
        );

        $capabilityId = DB::table(
            'platform_capabilities'
        )
            ->where(
                'code',
                'platform.dashboard.view'
            )
            ->value('id');

        $denyRoleId = (string) Str::ulid();

        DB::table(
            'platform_roles'
        )->insert([
            'id' => $denyRoleId,
            'code' => 'DENY_ROLE',
            'name' => 'Deny Role',
            'status' => 'ACTIVE',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(
            'platform_role_capabilities'
        )->insert([
            'platform_role_id' =>
                $denyRoleId,
            'platform_capability_id' =>
                $capabilityId,
            'effect' => 'DENY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(
            'platform_user_roles'
        )->insert([
            'user_id' => $user->id,
            'platform_role_id' =>
                $denyRoleId,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/v1/test/platform-dashboard'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_PLATFORM_CAPABILITY'
            );
    }

    public function test_inactive_platform_role_does_not_grant_access(): void
    {
        $user = $this->createPlatformUser(
            'inactive-role-platform@example.test',
            'ALLOW'
        );

        DB::table('platform_roles')
            ->update([
                'status' => 'INACTIVE',
                'updated_at' => now(),
            ]);

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/v1/test/platform-dashboard'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_PLATFORM_CAPABILITY'
            );
    }

    public function test_inactive_user_is_denied(): void
    {
        $user = $this->createPlatformUser(
            'inactive-platform-user@example.test',
            'ALLOW'
        );

        $user->forceFill([
            'auth_status' => 'INACTIVE',
        ])->save();

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/v1/test/platform-dashboard'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_PLATFORM_CAPABILITY'
            );
    }

    private function createPlatformUser(
        string $email,
        string $effect
    ): User {
        $user = $this->createUser($email);

        $capabilityId = (string) Str::ulid();
        $roleId = (string) Str::ulid();

        DB::table(
            'platform_capabilities'
        )->insert([
            'id' => $capabilityId,
            'code' =>
                'platform.dashboard.view',
            'name' =>
                'Melihat Beranda Platform',
            'is_sensitive' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(
            'platform_roles'
        )->insert([
            'id' => $roleId,
            'code' => 'TEST_PLATFORM_ROLE',
            'name' => 'Test Platform Role',
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
            'effect' => $effect,
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

        return $user;
    }

    private function createUser(string $email): User
    {
        return User::query()->create([
            'name' => 'Platform Test',
            'email' => $email,
            'password' =>
                'SecurePassword123!',
            'auth_status' => 'ACTIVE',
        ]);
    }
}
