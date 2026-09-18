<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthCapabilitiesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_active_context_returns_effective_capability_codes(): void
    {
        $workspace =
            $this->workspace(
                'capabilities@example.test',
                'Capability Workspace'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->getJson(
                '/api/v1/auth/capabilities'
            )
                ->assertOk()
                ->assertJsonPath(
                    'data.tenant_id',
                    $workspace['tenant_id']
                )
                ->assertJsonPath(
                    'data.business_id',
                    $workspace['business_id']
                );

        $codes =
            $response->json(
                'data.capability_codes'
            );

        $this->assertIsArray(
            $codes
        );

        $this->assertContains(
            'finance.income.manage',
            $codes
        );

        $this->assertContains(
            'finance.income.view',
            $codes
        );
    }

    public function test_denied_capability_is_not_returned(): void
    {
        $workspace =
            $this->workspace(
                'capabilities-deny@example.test',
                'Capability Deny'
            );

        $this->denyCapability(
            $workspace,
            'finance.income.manage'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->getJson(
                '/api/v1/auth/capabilities'
            )
                ->assertOk();

        $codes =
            $response->json(
                'data.capability_codes'
            );

        $this->assertIsArray(
            $codes
        );

        $this->assertNotContains(
            'finance.income.manage',
            $codes
        );

        $this->assertContains(
            'finance.income.view',
            $codes
        );
    }

    public function test_foreign_business_context_is_rejected(): void
    {
        $workspace =
            $this->workspace(
                'capabilities-owner@example.test',
                'Capability Owner'
            );

        $foreign =
            $this->workspace(
                'capabilities-foreign@example.test',
                'Capability Foreign'
            );

        $user =
            User::query()
                ->findOrFail(
                    $workspace['user_id']
                );

        $this->actingAs(
            $user,
            'sanctum'
        );

        $this->withHeader(
            'X-Signova-Tenant',
            $workspace['tenant_id']
        );

        $this->withHeader(
            'X-Signova-Business',
            $foreign['business_id']
        );

        $this->getJson(
            '/api/v1/auth/capabilities'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'BUSINESS_ACCESS_DENIED'
            );
    }

    private function workspace(
        string $email,
        string $tenantName
    ): array {
        return app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' =>
                'Capability Owner',

            'email' =>
                $email,

            'password' =>
                'password',

            'tenant_name' =>
                $tenantName,

            'timezone' =>
                'Asia/Jakarta',
        ]);
    }

    private function actingAsWorkspace(
        array $workspace
    ): void {
        $user =
            User::query()
                ->findOrFail(
                    $workspace['user_id']
                );

        $this->actingAs(
            $user,
            'sanctum'
        );

        $this->withHeader(
            'X-Signova-Tenant',
            $workspace['tenant_id']
        );

        $this->withHeader(
            'X-Signova-Business',
            $workspace['business_id']
        );
    }

    private function denyCapability(
        array $workspace,
        string $capabilityCode
    ): void {
        $capabilityId =
            DB::table(
                'capabilities'
            )
                ->where(
                    'code',
                    $capabilityCode
                )
                ->value('id');

        $this->assertNotNull(
            $capabilityId
        );

        DB::table(
            'role_capabilities'
        )->updateOrInsert(
            [
                'role_id' =>
                    $workspace[
                        'owner_role_id'
                    ],

                'capability_id' =>
                    $capabilityId,
            ],
            [
                'effect' =>
                    'DENY',

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]
        );
    }
}
