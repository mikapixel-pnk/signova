<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SignovaAccessControlSeeder::class);
    }

    public function test_first_active_membership_is_used_when_no_tenant_header_is_sent(): void
    {
        $workspace = app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Owner A',
            'email' => 'owner-a@example.test',
            'password' => 'SecurePassword123!',
            'tenant_name' => 'Workspace A',
        ]);

        $user = User::findOrFail($workspace['user_id']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath(
                'data.tenant.id',
                $workspace['tenant_id']
            );
    }

    public function test_user_can_select_an_active_tenant_membership(): void
    {
        $first = app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Multi Tenant Owner',
            'email' => 'multi@example.test',
            'password' => 'SecurePassword123!',
            'tenant_name' => 'Workspace Pertama',
        ]);

        $second = app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Second Owner',
            'email' => 'second@example.test',
            'password' => 'SecurePassword123!',
            'tenant_name' => 'Workspace Kedua',
        ]);

        \DB::table('tenant_users')->insert([
            'tenant_id' => $second['tenant_id'],
            'user_id' => $first['user_id'],
            'status' => 'ACTIVE',
            'joined_at' => now(),
            'context' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::findOrFail($first['user_id']);

        Sanctum::actingAs($user);

        $this
            ->withHeader(
                'X-Signova-Tenant',
                $second['tenant_id']
            )
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath(
                'data.tenant.id',
                $second['tenant_id']
            );
    }

    public function test_user_cannot_access_tenant_without_membership(): void
    {
        $first = app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Owner A',
            'email' => 'tenant-a@example.test',
            'password' => 'SecurePassword123!',
            'tenant_name' => 'Tenant A',
        ]);

        $second = app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Owner B',
            'email' => 'tenant-b@example.test',
            'password' => 'SecurePassword123!',
            'tenant_name' => 'Tenant B',
        ]);

        $user = User::findOrFail($first['user_id']);

        Sanctum::actingAs($user);

        $this
            ->withHeader(
                'X-Signova-Tenant',
                $second['tenant_id']
            )
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'TENANT_ACCESS_DENIED'
            );
    }
}
