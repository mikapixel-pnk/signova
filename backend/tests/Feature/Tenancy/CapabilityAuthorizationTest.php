<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CapabilityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SignovaAccessControlSeeder::class);

        Route::middleware([
            'auth:sanctum',
            'tenant.context',
            'capability:customer.view',
        ])->get(
            '/api/v1/test/customer-view',
            fn () => response()->json([
                'ok' => true,
            ])
        );
    }

    public function test_owner_is_allowed_for_mapped_capability(): void
    {
        $workspace = $this->createWorkspace(
            'owner@example.test',
            'Owner Workspace'
        );

        Sanctum::actingAs(
            User::findOrFail($workspace['user_id'])
        );

        $this->getJson(
            '/api/v1/test/customer-view'
        )
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_missing_capability_is_denied(): void
    {
        $workspace = $this->createWorkspace(
            'denied@example.test',
            'Denied Workspace'
        );

        $capabilityId = DB::table('capabilities')
            ->where('code', 'customer.view')
            ->value('id');

        DB::table('role_capabilities')
            ->where(
                'role_id',
                $workspace['owner_role_id']
            )
            ->where(
                'capability_id',
                $capabilityId
            )
            ->delete();

        Sanctum::actingAs(
            User::findOrFail($workspace['user_id'])
        );

        $this->getJson(
            '/api/v1/test/customer-view'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'CAPABILITY_DENIED'
            );
    }

    public function test_deny_overrides_allow_across_multiple_roles(): void
    {
        $workspace = $this->createWorkspace(
            'deny@example.test',
            'Deny Workspace'
        );

        $capabilityId = DB::table('capabilities')
            ->where('code', 'customer.view')
            ->value('id');

        $denyRoleId = (string) Str::ulid();

        DB::table('roles')->insert([
            'id' => $denyRoleId,
            'tenant_id' => $workspace['tenant_id'],
            'master_role_id' => null,
            'name' => 'Explicit Deny Test',
            'code' => 'DENY_TEST',
            'status' => 'ACTIVE',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('role_capabilities')->insert([
            'role_id' => $denyRoleId,
            'capability_id' => $capabilityId,
            'effect' => 'DENY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tenant_user_roles')->insert([
            'tenant_id' => $workspace['tenant_id'],
            'user_id' => $workspace['user_id'],
            'role_id' => $denyRoleId,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs(
            User::findOrFail($workspace['user_id'])
        );

        $this->getJson(
            '/api/v1/test/customer-view'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'CAPABILITY_DENIED'
            );
    }

    public function test_inactive_role_does_not_grant_capability(): void
    {
        $workspace = $this->createWorkspace(
            'inactive-role@example.test',
            'Inactive Role Workspace'
        );

        DB::table('roles')
            ->where(
                'id',
                $workspace['owner_role_id']
            )
            ->update([
                'status' => 'INACTIVE',
                'updated_at' => now(),
            ]);

        Sanctum::actingAs(
            User::findOrFail($workspace['user_id'])
        );

        $this->getJson(
            '/api/v1/test/customer-view'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'CAPABILITY_DENIED'
            );
    }

    public function test_inactive_user_is_denied(): void
    {
        $workspace = $this->createWorkspace(
            'inactive-user@example.test',
            'Inactive User Workspace'
        );

        DB::table('users')
            ->where('id', $workspace['user_id'])
            ->update([
                'auth_status' => 'INACTIVE',
                'updated_at' => now(),
            ]);

        $user = User::findOrFail(
            $workspace['user_id']
        );

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/v1/test/customer-view'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'CAPABILITY_DENIED'
            );
    }

    private function createWorkspace(
        string $email,
        string $tenantName
    ): array {
        return app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Capability Test',
            'email' => $email,
            'password' => 'SecurePassword123!',
            'tenant_name' => $tenantName,
        ]);
    }
}
