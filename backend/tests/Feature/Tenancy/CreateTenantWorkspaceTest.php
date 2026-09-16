<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class CreateTenantWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SignovaAccessControlSeeder::class);
    }

    public function test_it_creates_complete_owner_workspace_atomically(): void
    {
        $result = app(CreateTenantWorkspaceAction::class)->execute([
            'name' => 'Pemilik Test',
            'email' => 'owner@example.test',
            'password' => 'Secure-Test-Password!',
            'tenant_name' => 'Usaha Test',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $result['user_id'],
            'email' => 'owner@example.test',
            'auth_status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $result['tenant_id'],
            'name' => 'Usaha Test',
            'primary_owner_user_id' => $result['user_id'],
        ]);

        $this->assertDatabaseHas('business_profiles', [
            'id' => $result['business_id'],
            'tenant_id' => $result['tenant_id'],
            'name' => 'Usaha Test',
            'is_default' => true,
            'status' => 'ACTIVE',
        ]);

        $this->assertNotSame(
            $result['tenant_id'],
            $result['business_id']
        );

        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $result['tenant_id'],
            'user_id' => $result['user_id'],
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('roles', [
            'id' => $result['owner_role_id'],
            'tenant_id' => $result['tenant_id'],
            'code' => 'OWNER',
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('tenant_user_roles', [
            'tenant_id' => $result['tenant_id'],
            'user_id' => $result['user_id'],
            'role_id' => $result['owner_role_id'],
        ]);

        $ownerMasterRoleId =
            DB::table('master_roles')
                ->where('code', 'OWNER')
                ->value('id');

        $expectedCapabilityCount =
            DB::table('master_role_capabilities')
                ->where(
                    'master_role_id',
                    $ownerMasterRoleId
                )
                ->count();

        $this->assertSame(
            $expectedCapabilityCount,
            DB::table('role_capabilities')
                ->where(
                    'role_id',
                    $result['owner_role_id']
                )
                ->count()
        );
    }

    public function test_duplicate_email_rolls_back_entire_onboarding(): void
    {
        $action = app(CreateTenantWorkspaceAction::class);

        $action->execute([
            'name' => 'Owner Pertama',
            'email' => 'duplicate@example.test',
            'password' => 'Secure-Test-Password!',
            'tenant_name' => 'Tenant Pertama',
        ]);

        $before = [
            'users' => DB::table('users')->count(),
            'tenants' => DB::table('tenants')->count(),
            'business_profiles' => DB::table('business_profiles')->count(),
            'memberships' => DB::table('tenant_users')->count(),
            'roles' => DB::table('roles')->count(),
            'assignments' => DB::table('tenant_user_roles')->count(),
        ];

        try {
            $action->execute([
                'name' => 'Owner Kedua',
                'email' => 'duplicate@example.test',
                'password' => 'Secure-Test-Password!',
                'tenant_name' => 'Tenant Yang Harus Rollback',
            ]);

            $this->fail('Duplicate email should fail.');
        } catch (\Throwable) {
            // Expected.
        }

        $after = [
            'users' => DB::table('users')->count(),
            'tenants' => DB::table('tenants')->count(),
            'business_profiles' => DB::table('business_profiles')->count(),
            'memberships' => DB::table('tenant_users')->count(),
            'roles' => DB::table('roles')->count(),
            'assignments' => DB::table('tenant_user_roles')->count(),
        ];

        $this->assertSame($before, $after);

        $this->assertDatabaseMissing('tenants', [
            'name' => 'Tenant Yang Harus Rollback',
        ]);
    }

    public function test_missing_owner_master_role_rolls_back_entire_onboarding(): void
    {
        DB::table('master_roles')
            ->where('code', 'OWNER')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        try {
            app(CreateTenantWorkspaceAction::class)->execute([
                'name' => 'Owner Rollback',
                'email' => 'rollback@example.test',
                'password' => 'Secure-Test-Password!',
                'tenant_name' => 'Rollback Workspace',
            ]);

            $this->fail('Inactive OWNER master role should fail.');
        } catch (RuntimeException $e) {
            $this->assertSame(
                'Master role OWNER is not available.',
                $e->getMessage()
            );
        }

        $this->assertDatabaseMissing('users', [
            'email' => 'rollback@example.test',
        ]);

        $this->assertDatabaseMissing('tenants', [
            'name' => 'Rollback Workspace',
        ]);

        $this->assertDatabaseMissing('business_profiles', [
            'name' => 'Rollback Workspace',
        ]);
    }

    public function test_database_rejects_cross_tenant_role_assignment(): void
    {
        $action = app(CreateTenantWorkspaceAction::class);

        $tenantA = $action->execute([
            'name' => 'Owner A',
            'email' => 'owner-a@example.test',
            'password' => 'Secure-Test-Password!',
            'tenant_name' => 'Tenant A',
        ]);

        $tenantB = $action->execute([
            'name' => 'Owner B',
            'email' => 'owner-b@example.test',
            'password' => 'Secure-Test-Password!',
            'tenant_name' => 'Tenant B',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('tenant_user_roles')->insert([
            'tenant_id' => $tenantA['tenant_id'],
            'user_id' => $tenantA['user_id'],
            'role_id' => $tenantB['owner_role_id'],
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_duplicate_tenant_name_gets_unique_slug(): void
    {
        $action = app(CreateTenantWorkspaceAction::class);

        $first = $action->execute([
            'name' => 'Owner Satu',
            'email' => 'slug-1@example.test',
            'password' => 'Secure-Test-Password!',
            'tenant_name' => 'Reklame Jaya',
        ]);

        $second = $action->execute([
            'name' => 'Owner Dua',
            'email' => 'slug-2@example.test',
            'password' => 'Secure-Test-Password!',
            'tenant_name' => 'Reklame Jaya',
        ]);

        $this->assertSame('reklame-jaya', $first['tenant_slug']);
        $this->assertNotSame(
            $first['tenant_slug'],
            $second['tenant_slug']
        );

        $this->assertStringStartsWith(
            'reklame-jaya-',
            $second['tenant_slug']
        );
    }

    public function test_owner_role_capabilities_match_master_owner_template(): void
    {
        $result = app(CreateTenantWorkspaceAction::class)->execute([
            'name' => 'Owner Capability',
            'email' => 'capability@example.test',
            'password' => 'Secure-Test-Password!',
            'tenant_name' => 'Capability Workspace',
        ]);

        $masterOwner = DB::table('master_roles')
            ->where('code', 'OWNER')
            ->firstOrFail();

        $masterCapabilities = DB::table('master_role_capabilities')
            ->where('master_role_id', $masterOwner->id)
            ->orderBy('capability_id')
            ->pluck('capability_id')
            ->all();

        $tenantCapabilities = DB::table('role_capabilities')
            ->where('role_id', $result['owner_role_id'])
            ->orderBy('capability_id')
            ->pluck('capability_id')
            ->all();

        $this->assertSame(
            $masterCapabilities,
            $tenantCapabilities
        );
    }
}
