<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_single_active_membership_is_resolved_automatically(): void
    {
        $workspace = $this->workspace(
            'single@example.test',
            'Single Workspace'
        );

        Sanctum::actingAs(
            User::findOrFail(
                $workspace['user_id']
            )
        );

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath(
                'data.tenant.id',
                $workspace['tenant_id']
            );
    }

    public function test_explicit_active_tenant_is_resolved(): void
    {
        $workspace = $this->workspace(
            'explicit@example.test',
            'Explicit Workspace'
        );

        Sanctum::actingAs(
            User::findOrFail(
                $workspace['user_id']
            )
        );

        $this
            ->withHeader(
                'X-Signova-Tenant',
                $workspace['tenant_id']
            )
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath(
                'data.tenant.id',
                $workspace['tenant_id']
            );
    }

    public function test_foreign_tenant_is_denied(): void
    {
        $first = $this->workspace(
            'first@example.test',
            'First Workspace'
        );

        $second = $this->workspace(
            'second@example.test',
            'Second Workspace'
        );

        Sanctum::actingAs(
            User::findOrFail(
                $first['user_id']
            )
        );

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

    public function test_inactive_membership_is_denied(): void
    {
        $workspace = $this->workspace(
            'inactive-member@example.test',
            'Inactive Membership Workspace'
        );

        DB::table('tenant_users')
            ->where(
                'tenant_id',
                $workspace['tenant_id']
            )
            ->where(
                'user_id',
                $workspace['user_id']
            )
            ->update([
                'status' => 'INACTIVE',
                'updated_at' => now(),
            ]);

        Sanctum::actingAs(
            User::findOrFail(
                $workspace['user_id']
            )
        );

        $this
            ->withHeader(
                'X-Signova-Tenant',
                $workspace['tenant_id']
            )
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'TENANT_ACCESS_DENIED'
            );
    }

    public function test_inactive_tenant_is_denied(): void
    {
        $workspace = $this->workspace(
            'inactive-tenant@example.test',
            'Inactive Tenant Workspace'
        );

        DB::table('tenants')
            ->where(
                'id',
                $workspace['tenant_id']
            )
            ->update([
                'lifecycle_status' => 'SUSPENDED',
                'updated_at' => now(),
            ]);

        Sanctum::actingAs(
            User::findOrFail(
                $workspace['user_id']
            )
        );

        $this
            ->withHeader(
                'X-Signova-Tenant',
                $workspace['tenant_id']
            )
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'TENANT_ACCESS_DENIED'
            );
    }

    public function test_multiple_active_memberships_require_explicit_selection(): void
    {
        $first = $this->workspace(
            'multi-first@example.test',
            'Multi First'
        );

        $second = $this->workspace(
            'multi-second@example.test',
            'Multi Second'
        );

        DB::table('tenant_users')->insert([
            'tenant_id' =>
                $second['tenant_id'],
            'user_id' =>
                $first['user_id'],
            'status' => 'ACTIVE',
            'joined_at' => now(),
            'context' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs(
            User::findOrFail(
                $first['user_id']
            )
        );

        $this->getJson('/api/v1/auth/me')
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'TENANT_SELECTION_REQUIRED'
            );
    }

    public function test_explicit_selection_works_with_multiple_memberships(): void
    {
        $first = $this->workspace(
            'select-first@example.test',
            'Select First'
        );

        $second = $this->workspace(
            'select-second@example.test',
            'Select Second'
        );

        DB::table('tenant_users')->insert([
            'tenant_id' =>
                $second['tenant_id'],
            'user_id' =>
                $first['user_id'],
            'status' => 'ACTIVE',
            'joined_at' => now(),
            'context' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs(
            User::findOrFail(
                $first['user_id']
            )
        );

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

    private function workspace(
        string $email,
        string $tenantName
    ): array {
        return app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Tenant Context Test',
            'email' => $email,
            'password' =>
                'SecurePassword123!',
            'tenant_name' =>
                $tenantName,
        ]);
    }
}
