<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use App\Tenancy\BusinessContext;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_single_active_business_is_resolved_automatically(): void
    {
        $workspace = $this->workspace(
            'single-business@example.test',
            'Single Business Workspace'
        );

        $businessId = DB::table('business_profiles')
            ->where(
                'tenant_id',
                $workspace['tenant_id']
            )
            ->value('id');

        Sanctum::actingAs(
            User::findOrFail(
                $workspace['user_id']
            )
        );

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath(
                'data.business.id',
                $businessId
            )
            ->assertJsonPath(
                'data.business.tenant_id',
                $workspace['tenant_id']
            );

        $this->assertFalse(
            app(BusinessContext::class)
                ->hasBusiness()
        );
    }

    public function test_multiple_active_businesses_require_explicit_selection(): void
    {
        $workspace = $this->workspace(
            'multi-business@example.test',
            'Multi Business Workspace'
        );

        $this->createBusiness(
            $workspace['tenant_id'],
            'Usaha Kedua'
        );

        Sanctum::actingAs(
            User::findOrFail(
                $workspace['user_id']
            )
        );

        $this->getJson('/api/v1/auth/me')
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'BUSINESS_SELECTION_REQUIRED'
            );
    }

    public function test_explicit_business_is_resolved(): void
    {
        $workspace = $this->workspace(
            'explicit-business@example.test',
            'Explicit Business Workspace'
        );

        $secondBusinessId =
            $this->createBusiness(
                $workspace['tenant_id'],
                'Usaha Kedua'
            );

        Sanctum::actingAs(
            User::findOrFail(
                $workspace['user_id']
            )
        );

        $this
            ->withHeaders([
                'X-Signova-Tenant' =>
                    $workspace['tenant_id'],
                'X-Signova-Business' =>
                    $secondBusinessId,
            ])
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath(
                'data.business.id',
                $secondBusinessId
            );
    }

    public function test_foreign_business_is_denied(): void
    {
        $first = $this->workspace(
            'business-first@example.test',
            'Business First'
        );

        $second = $this->workspace(
            'business-second@example.test',
            'Business Second'
        );

        $foreignBusinessId =
            DB::table('business_profiles')
                ->where(
                    'tenant_id',
                    $second['tenant_id']
                )
                ->value('id');

        Sanctum::actingAs(
            User::findOrFail(
                $first['user_id']
            )
        );

        $this
            ->withHeaders([
                'X-Signova-Tenant' =>
                    $first['tenant_id'],
                'X-Signova-Business' =>
                    $foreignBusinessId,
            ])
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'BUSINESS_ACCESS_DENIED'
            );
    }

    public function test_inactive_business_is_denied(): void
    {
        $workspace = $this->workspace(
            'inactive-business@example.test',
            'Inactive Business Workspace'
        );

        $businessId =
            DB::table('business_profiles')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->value('id');

        DB::table('business_profiles')
            ->where('id', $businessId)
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
            ->withHeaders([
                'X-Signova-Tenant' =>
                    $workspace['tenant_id'],
                'X-Signova-Business' =>
                    $businessId,
            ])
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'BUSINESS_ACCESS_DENIED'
            );
    }

    public function test_tenant_without_active_business_is_denied(): void
    {
        $workspace = $this->workspace(
            'no-business@example.test',
            'No Business Workspace'
        );

        DB::table('business_profiles')
            ->where(
                'tenant_id',
                $workspace['tenant_id']
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

        $this->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'ACTIVE_BUSINESS_REQUIRED'
            );
    }

    private function workspace(
        string $email,
        string $tenantName
    ): array {
        return app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Business Context Test',
            'email' => $email,
            'password' =>
                'SecurePassword123!',
            'tenant_name' =>
                $tenantName,
        ]);
    }

    private function createBusiness(
        string $tenantId,
        string $name
    ): string {
        $businessId =
            (string) Str::ulid();

        DB::table('business_profiles')
            ->insert([
                'id' => $businessId,
                'tenant_id' => $tenantId,
                'name' => $name,
                'is_default' => false,
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return $businessId;
    }
}
