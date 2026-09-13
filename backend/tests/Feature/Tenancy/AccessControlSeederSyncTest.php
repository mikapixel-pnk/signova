<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccessControlSeederSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_master_capability_is_added_to_existing_system_role(): void
    {
        $this->seed(
            SignovaAccessControlSeeder::class
        );

        $workspace = app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' =>
                'Owner',
            'email' =>
                'acl-sync@example.test',
            'password' =>
                'password',
            'tenant_name' =>
                'ACL Sync',
        ]);

        $capabilityId =
            DB::table('capabilities')
                ->where(
                    'code',
                    'finance.cash_bank.manage'
                )
                ->value('id');

        $this->assertNotNull(
            $capabilityId
        );

        /*
         * Simulasikan tenant lama:
         * capability baseline baru belum ada
         * di role_capabilities tenant.
         */
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

        $this->assertDatabaseMissing(
            'role_capabilities',
            [
                'role_id' =>
                    $workspace['owner_role_id'],

                'capability_id' =>
                    $capabilityId,
            ]
        );

        $this->seed(
            SignovaAccessControlSeeder::class
        );

        $this->assertDatabaseHas(
            'role_capabilities',
            [
                'role_id' =>
                    $workspace['owner_role_id'],

                'capability_id' =>
                    $capabilityId,

                'effect' =>
                    'ALLOW',
            ]
        );
    }

    public function test_existing_explicit_deny_is_not_overwritten(): void
    {
        $this->seed(
            SignovaAccessControlSeeder::class
        );

        $workspace = app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' =>
                'Owner',
            'email' =>
                'acl-deny@example.test',
            'password' =>
                'password',
            'tenant_name' =>
                'ACL Deny',
        ]);

        $capabilityId =
            DB::table('capabilities')
                ->where(
                    'code',
                    'finance.cash_bank.manage'
                )
                ->value('id');

        $this->assertNotNull(
            $capabilityId
        );

        DB::table('role_capabilities')
            ->where(
                'role_id',
                $workspace['owner_role_id']
            )
            ->where(
                'capability_id',
                $capabilityId
            )
            ->update([
                'effect' =>
                    'DENY',

                'updated_at' =>
                    now(),
            ]);

        $this->seed(
            SignovaAccessControlSeeder::class
        );

        $this->assertDatabaseHas(
            'role_capabilities',
            [
                'role_id' =>
                    $workspace['owner_role_id'],

                'capability_id' =>
                    $capabilityId,

                'effect' =>
                    'DENY',
            ]
        );
    }
}
