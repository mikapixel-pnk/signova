<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_owner_can_create_customer(): void
    {
        $workspace = $this->workspace(
            'customer-create@example.test',
            'Customer Create'
        );

        $this->actingAsWorkspace($workspace);

        $response = $this->postJson(
            '/api/v1/customers',
            [
                'type' => 'COMPANY',
                'code' => 'CUST-001',
                'name' => 'PT Signova Test',
                'phone' => '08123456789',
                'email' => 'hello@example.test',
                'payment_terms_days' => 14,
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.name',
                'PT Signova Test'
            )
            ->assertJsonPath(
                'data.code',
                'CUST-001'
            )
            ->assertJsonMissingPath(
                'data.tenant_id'
            );

        $this->assertDatabaseHas(
            'customers',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'code' => 'CUST-001',
                'name' => 'PT Signova Test',
            ]
        );
    }

    public function test_customer_list_is_tenant_scoped_and_searchable(): void
    {
        $first = $this->workspace(
            'list-first@example.test',
            'Customer List First'
        );

        $second = $this->workspace(
            'list-second@example.test',
            'Customer List Second'
        );

        $this->insertCustomer(
            $first['tenant_id'],
            'FIRST-001',
            'Alpha Reklame'
        );

        $this->insertCustomer(
            $first['tenant_id'],
            'FIRST-002',
            'Beta Advertising'
        );

        $this->insertCustomer(
            $second['tenant_id'],
            'SECOND-001',
            'Alpha Tenant Lain'
        );

        $this->actingAsWorkspace($first);

        $response = $this->getJson(
            '/api/v1/customers?search=Alpha'
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.name',
                'Alpha Reklame'
            )
            ->assertJsonPath(
                'meta.total',
                1
            );
    }

    public function test_owner_can_update_customer(): void
    {
        $workspace = $this->workspace(
            'customer-update@example.test',
            'Customer Update'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'UPDATE-001',
            'Nama Lama'
        );

        $this->actingAsWorkspace($workspace);

        $this->patchJson(
            "/api/v1/customers/{$customerId}",
            [
                'name' => 'Nama Baru',
                'status' => 'INACTIVE',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Nama Baru'
            )
            ->assertJsonPath(
                'data.status',
                'INACTIVE'
            );

        $this->assertDatabaseHas(
            'customers',
            [
                'id' => $customerId,
                'tenant_id' =>
                    $workspace['tenant_id'],
                'name' => 'Nama Baru',
                'status' => 'INACTIVE',
            ]
        );
    }

    public function test_cross_tenant_customer_id_returns_not_found(): void
    {
        $first = $this->workspace(
            'cross-first@example.test',
            'Cross First'
        );

        $second = $this->workspace(
            'cross-second@example.test',
            'Cross Second'
        );

        $foreignCustomerId = $this->insertCustomer(
            $second['tenant_id'],
            'FOREIGN-001',
            'Foreign Customer'
        );

        $this->actingAsWorkspace($first);

        $this->getJson(
            "/api/v1/customers/{$foreignCustomerId}"
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );
    }

    public function test_customer_validation_uses_standard_error_contract(): void
    {
        $workspace = $this->workspace(
            'customer-validation@example.test',
            'Customer Validation'
        );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/customers',
            [
                'name' => '',
                'type' => 'INVALID',
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            )
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'fields',
                    ],
                ],
                'meta' => [
                    'request_id',
                ],
            ]);
    }

    public function test_duplicate_customer_code_is_rejected_within_same_tenant(): void
    {
        $workspace = $this->workspace(
            'duplicate-code@example.test',
            'Duplicate Customer Code'
        );

        $this->insertCustomer(
            $workspace['tenant_id'],
            'DUP-001',
            'Existing Customer'
        );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/customers',
            [
                'code' => 'DUP-001',
                'name' => 'Duplicate Customer',
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_same_customer_code_is_allowed_in_different_tenants(): void
    {
        $first = $this->workspace(
            'code-first@example.test',
            'Code First'
        );

        $second = $this->workspace(
            'code-second@example.test',
            'Code Second'
        );

        $this->insertCustomer(
            $second['tenant_id'],
            'SHARED-001',
            'Other Tenant Customer'
        );

        $this->actingAsWorkspace($first);

        $this->postJson(
            '/api/v1/customers',
            [
                'code' => 'SHARED-001',
                'name' => 'Own Customer',
            ]
        )->assertCreated();
    }

    public function test_invalid_list_filter_is_rejected(): void
    {
        $workspace = $this->workspace(
            'filter@example.test',
            'Filter Validation'
        );

        $this->actingAsWorkspace($workspace);

        $this->getJson(
            '/api/v1/customers?status=UNKNOWN'
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_missing_customer_view_capability_is_denied(): void
    {
        $workspace = $this->workspace(
            'customer-view-denied@example.test',
            'Customer View Denied'
        );

        $this->revokeOwnerCapability(
            $workspace,
            'customer.view'
        );

        $this->actingAsWorkspace($workspace);

        $this->getJson('/api/v1/customers')
            ->assertForbidden()
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_missing_customer_create_capability_is_denied(): void
    {
        $workspace = $this->workspace(
            'customer-create-denied@example.test',
            'Customer Create Denied'
        );

        $this->revokeOwnerCapability(
            $workspace,
            'customer.create'
        );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/customers',
            [
                'name' => 'Denied Customer',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseMissing(
            'customers',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'name' => 'Denied Customer',
            ]
        );
    }

    public function test_missing_customer_update_capability_is_denied(): void
    {
        $workspace = $this->workspace(
            'customer-update-denied@example.test',
            'Customer Update Denied'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'DENIED-UPDATE',
            'Original Customer'
        );

        $this->revokeOwnerCapability(
            $workspace,
            'customer.update'
        );

        $this->actingAsWorkspace($workspace);

        $this->patchJson(
            "/api/v1/customers/{$customerId}",
            [
                'name' => 'Unauthorized Change',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseHas(
            'customers',
            [
                'id' => $customerId,
                'name' => 'Original Customer',
            ]
        );
    }

    public function test_cross_tenant_customer_update_returns_not_found_and_does_not_mutate_data(): void
    {
        $first = $this->workspace(
            'patch-cross-first@example.test',
            'Patch Cross First'
        );

        $second = $this->workspace(
            'patch-cross-second@example.test',
            'Patch Cross Second'
        );

        $foreignCustomerId = $this->insertCustomer(
            $second['tenant_id'],
            'FOREIGN-PATCH',
            'Foreign Original'
        );

        $this->actingAsWorkspace($first);

        $this->patchJson(
            "/api/v1/customers/{$foreignCustomerId}",
            [
                'name' => 'Illegal Mutation',
            ]
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );

        $this->assertDatabaseHas(
            'customers',
            [
                'id' => $foreignCustomerId,
                'tenant_id' =>
                    $second['tenant_id'],
                'name' => 'Foreign Original',
            ]
        );

        $this->assertDatabaseMissing(
            'customers',
            [
                'id' => $foreignCustomerId,
                'name' => 'Illegal Mutation',
            ]
        );
    }

    private function actingAsWorkspace(
        array $workspace
    ): void {
        Sanctum::actingAs(
            User::findOrFail(
                $workspace['user_id']
            )
        );

        $this->withHeader(
            'X-Signova-Tenant',
            $workspace['tenant_id']
        );
    }

    private function revokeOwnerCapability(
        array $workspace,
        string $capabilityCode
    ): void {
        $capabilityId = DB::table(
            'capabilities'
        )
            ->where(
                'code',
                $capabilityCode
            )
            ->value('id');

        $this->assertNotNull(
            $capabilityId,
            "Capability {$capabilityCode} harus tersedia."
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
            ->delete();
    }

    private function workspace(
        string $email,
        string $tenantName
    ): array {
        return app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Customer API Test',
            'email' => $email,
            'password' =>
                'SecurePassword123!',
            'tenant_name' => $tenantName,
        ]);
    }

    private function insertCustomer(
        string $tenantId,
        string $code,
        string $name
    ): string {
        $id = (string) \Illuminate\Support\Str::ulid();

        DB::table('customers')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'type' => 'COMPANY',
            'code' => $code,
            'name' => $name,
            'phone' => null,
            'email' => null,
            'tax_id' => null,
            'payment_terms_days' => 0,
            'notes' => null,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
