<?php

namespace Tests\Feature\Tenancy;

use App\Actions\MasterData\SeedTenantMasterDataAction;
use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantMasterDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SignovaAccessControlSeeder::class);
    }

    public function test_new_tenant_receives_default_units(): void
    {
        $workspace = app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Default Unit Owner',
            'email' => 'default-unit@example.test',
            'password' => 'SecurePassword123!',
            'tenant_name' => 'Default Unit Workspace',
        ]);

        $units = DB::table('units')
            ->where(
                'tenant_id',
                $workspace['tenant_id']
            )
            ->orderBy('code')
            ->pluck('symbol', 'code');

        $this->assertCount(9, $units);

        $this->assertSame('pcs', $units['PCS']);
        $this->assertSame('cm', $units['CM']);
        $this->assertSame('m²', $units['M2']);
        $this->assertSame('paket', $units['PACKAGE']);
    }

    public function test_default_unit_seeding_is_idempotent(): void
    {
        $workspace = app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Idempotent Owner',
            'email' => 'idempotent-unit@example.test',
            'password' => 'SecurePassword123!',
            'tenant_name' => 'Idempotent Unit Workspace',
        ]);

        app(
            SeedTenantMasterDataAction::class
        )->execute(
            $workspace['tenant_id']
        );

        $this->assertSame(
            9,
            DB::table('units')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->count()
        );
    }
}
