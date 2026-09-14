<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Database\Seeders\PlatformAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformAccessSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_platform_capabilities_and_super_admin_role(): void
    {
        $this->seed(
            PlatformAccessControlSeeder::class
        );

        $this->assertDatabaseHas(
            'platform_roles',
            [
                'code' => 'SUPER_ADMIN',
                'status' => 'ACTIVE',
                'is_system' => true,
            ]
        );

        $this->assertSame(
            15,
            DB::table(
                'platform_capabilities'
            )
                ->where(
                    'is_active',
                    true
                )
                ->count()
        );

        $roleId = DB::table(
            'platform_roles'
        )
            ->where(
                'code',
                'SUPER_ADMIN'
            )
            ->value('id');

        $this->assertSame(
            15,
            DB::table(
                'platform_role_capabilities'
            )
                ->where(
                    'platform_role_id',
                    $roleId
                )
                ->count()
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(
            PlatformAccessControlSeeder::class
        );

        $this->seed(
            PlatformAccessControlSeeder::class
        );

        $this->assertSame(
            1,
            DB::table(
                'platform_roles'
            )
                ->where(
                    'code',
                    'SUPER_ADMIN'
                )
                ->count()
        );

        $this->assertSame(
            15,
            DB::table(
                'platform_capabilities'
            )
                ->count()
        );

        $roleId = DB::table(
            'platform_roles'
        )
            ->where(
                'code',
                'SUPER_ADMIN'
            )
            ->value('id');

        $this->assertSame(
            15,
            DB::table(
                'platform_role_capabilities'
            )
                ->where(
                    'platform_role_id',
                    $roleId
                )
                ->count()
        );
    }

    public function test_command_grants_super_admin_to_existing_user(): void
    {
        $this->seed(
            PlatformAccessControlSeeder::class
        );

        $user = User::query()->create([
            'name' =>
                'Platform Bootstrap',
            'email' =>
                'platform-bootstrap@example.test',
            'password' =>
                'SecurePassword123!',
            'auth_status' =>
                'ACTIVE',
        ]);

        $this->artisan(
            'platform:grant-super-admin',
            [
                'email' =>
                    'platform-bootstrap@example.test',
            ]
        )
            ->expectsOutput(
                'SUPER_ADMIN berhasil diberikan.'
            )
            ->assertSuccessful();

        $roleId = DB::table(
            'platform_roles'
        )
            ->where(
                'code',
                'SUPER_ADMIN'
            )
            ->value('id');

        $this->assertDatabaseHas(
            'platform_user_roles',
            [
                'user_id' =>
                    $user->id,
                'platform_role_id' =>
                    $roleId,
            ]
        );
    }

    public function test_command_is_idempotent(): void
    {
        $this->seed(
            PlatformAccessControlSeeder::class
        );

        User::query()->create([
            'name' =>
                'Platform Existing',
            'email' =>
                'platform-existing@example.test',
            'password' =>
                'SecurePassword123!',
            'auth_status' =>
                'ACTIVE',
        ]);

        $this->artisan(
            'platform:grant-super-admin',
            [
                'email' =>
                    'platform-existing@example.test',
            ]
        )->assertSuccessful();

        $this->artisan(
            'platform:grant-super-admin',
            [
                'email' =>
                    'platform-existing@example.test',
            ]
        )
            ->expectsOutput(
                'User sudah memiliki role SUPER_ADMIN.'
            )
            ->assertSuccessful();

        $this->assertSame(
            1,
            DB::table(
                'platform_user_roles'
            )->count()
        );
    }
}
