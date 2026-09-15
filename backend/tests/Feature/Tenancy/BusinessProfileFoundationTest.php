<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessProfileFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SignovaAccessControlSeeder::class);
    }

    public function test_tenant_can_have_multiple_business_profiles(): void
    {
        $workspace = $this->workspace();

        DB::table('business_profiles')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $workspace['tenant_id'],
            'name' => 'Usaha Kedua',
            'is_default' => false,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            2,
            DB::table('business_profiles')
                ->where('tenant_id', $workspace['tenant_id'])
                ->count()
        );

        $this->assertSame(
            1,
            DB::table('business_profiles')
                ->where('tenant_id', $workspace['tenant_id'])
                ->where('is_default', true)
                ->count()
        );
    }

    public function test_database_rejects_second_default_business(): void
    {
        $workspace = $this->workspace();

        $this->expectException(QueryException::class);

        DB::table('business_profiles')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $workspace['tenant_id'],
            'name' => 'Default Kedua',
            'is_default' => true,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_unknown_tenant(): void
    {
        $this->expectException(QueryException::class);

        DB::table('business_profiles')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => (string) Str::ulid(),
            'name' => 'Usaha Tanpa Tenant',
            'is_default' => false,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_non_canonical_status(): void
    {
        $workspace = $this->workspace();

        $this->expectException(QueryException::class);

        DB::table('business_profiles')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $workspace['tenant_id'],
            'name' => 'Status Salah',
            'is_default' => false,
            'status' => 'UNKNOWN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function workspace(): array
    {
        return app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Business Foundation Owner',
            'email' => 'business-foundation@example.test',
            'password' => 'SecurePassword123!',
            'tenant_name' => 'Business Foundation Tenant',
        ]);
    }
}
