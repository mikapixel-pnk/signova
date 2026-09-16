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
            $first['business_id'],
            'FIRST-001',
            'Alpha Reklame'
        );

        $this->insertCustomer(
            $first['tenant_id'],
            $first['business_id'],
            'FIRST-002',
            'Beta Advertising'
        );

        $this->insertCustomer(
            $second['tenant_id'],
            $second['business_id'],
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
            $workspace['business_id'],
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
            $second['business_id'],
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
            $workspace['business_id'],
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
            $second['business_id'],
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
            $workspace['business_id'],
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
            $second['business_id'],
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

    public function test_owner_can_create_customer_with_primary_address(): void
    {
        $workspace = $this->workspace(
            'customer-address-create@example.test',
            'Customer Address Create'
        );

        $this->actingAsWorkspace($workspace);

        $response = $this->postJson(
            '/api/v1/customers',
            [
                'code' => 'ADDR-001',
                'name' => 'PT Alamat Test',
                'address' => 'Jl. Ahmad Yani No. 10',
                'city' => 'Pontianak',
                'province' => 'Kalimantan Barat',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.address',
                'Jl. Ahmad Yani No. 10'
            )
            ->assertJsonPath(
                'data.city',
                'Pontianak'
            )
            ->assertJsonPath(
                'data.province',
                'Kalimantan Barat'
            );

        $customerId =
            $response->json('data.id');

        $this->assertDatabaseHas(
            'customer_addresses',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'business_id' =>
                    $workspace['business_id'],
                'customer_id' =>
                    $customerId,
                'type' => 'BILLING',
                'address_line_1' =>
                    'Jl. Ahmad Yani No. 10',
                'city' => 'Pontianak',
                'province' =>
                    'Kalimantan Barat',
                'is_primary' => true,
                'status' => 'ACTIVE',
            ]
        );
    }

    public function test_owner_can_update_customer_primary_address(): void
    {
        $workspace = $this->workspace(
            'customer-address-update@example.test',
            'Customer Address Update'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            $workspace['business_id'],
            'ADDR-UPD',
            'Pelanggan Alamat'
        );

        $this->actingAsWorkspace($workspace);

        $this->patchJson(
            "/api/v1/customers/{$customerId}",
            [
                'address' => 'Jl. Gajah Mada No. 20',
                'city' => 'Pontianak',
                'province' => 'Kalimantan Barat',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.address',
                'Jl. Gajah Mada No. 20'
            )
            ->assertJsonPath(
                'data.city',
                'Pontianak'
            )
            ->assertJsonPath(
                'data.province',
                'Kalimantan Barat'
            );

        $this->assertDatabaseHas(
            'customer_addresses',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'business_id' =>
                    $workspace['business_id'],
                'customer_id' => $customerId,
                'address_line_1' =>
                    'Jl. Gajah Mada No. 20',
                'city' => 'Pontianak',
                'province' =>
                    'Kalimantan Barat',
                'is_primary' => true,
            ]
        );
    }

    public function test_owner_can_remove_customer_primary_address(): void
    {
        $workspace = $this->workspace(
            'customer-address-remove@example.test',
            'Customer Address Remove'
        );

        $this->actingAsWorkspace($workspace);

        $createResponse = $this->postJson(
            '/api/v1/customers',
            [
                'code' => 'ADDR-DEL',
                'name' => 'Pelanggan Hapus Alamat',
                'address' => 'Jl. Lama No. 1',
                'city' => 'Pontianak',
                'province' => 'Kalimantan Barat',
            ]
        )->assertCreated();

        $customerId =
            $createResponse->json('data.id');

        $this->patchJson(
            "/api/v1/customers/{$customerId}",
            [
                'address' => null,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.address',
                null
            )
            ->assertJsonPath(
                'data.city',
                null
            )
            ->assertJsonPath(
                'data.province',
                null
            );

        $this->assertDatabaseMissing(
            'customer_addresses',
            [
                'customer_id' => $customerId,
                'tenant_id' =>
                    $workspace['tenant_id'],
                'business_id' =>
                    $workspace['business_id'],
                'type' => 'BILLING',
                'is_primary' => true,
            ]
        );
    }

    public function test_create_customer_rejects_city_without_address(): void
    {
        $workspace = $this->workspace(
            'customer-city-without-address@example.test',
            'Customer City Without Address'
        );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/customers',
            [
                'name' => 'Pelanggan Kota Tanpa Alamat',
                'city' => 'Pontianak',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            )
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'fields' => [
                            'address',
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseMissing(
            'customers',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'business_id' =>
                    $workspace['business_id'],
                'name' =>
                    'Pelanggan Kota Tanpa Alamat',
            ]
        );
    }

    public function test_update_customer_without_address_rejects_city_only(): void
    {
        $workspace = $this->workspace(
            'customer-update-city-without-address@example.test',
            'Customer Update City Without Address'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            $workspace['business_id'],
            'ADDR-NONE',
            'Pelanggan Tanpa Alamat'
        );

        $this->actingAsWorkspace($workspace);

        $this->patchJson(
            "/api/v1/customers/{$customerId}",
            [
                'city' => 'Pontianak',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            )
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'fields' => [
                            'address',
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseMissing(
            'customer_addresses',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'business_id' =>
                    $workspace['business_id'],
                'customer_id' =>
                    $customerId,
            ]
        );
    }

    public function test_update_existing_address_allows_city_only(): void
    {
        $workspace = $this->workspace(
            'customer-update-existing-address-city@example.test',
            'Customer Update Existing Address City'
        );

        $this->actingAsWorkspace($workspace);

        $createResponse = $this->postJson(
            '/api/v1/customers',
            [
                'code' => 'ADDR-CITY-ONLY',
                'name' => 'Pelanggan Alamat Existing',
                'address' => 'Jl. Lama No. 10',
                'city' => 'Pontianak',
                'province' => 'Kalimantan Barat',
            ]
        )->assertCreated();

        $customerId =
            $createResponse->json('data.id');

        $this->patchJson(
            "/api/v1/customers/{$customerId}",
            [
                'city' => 'Singkawang',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.address',
                'Jl. Lama No. 10'
            )
            ->assertJsonPath(
                'data.city',
                'Singkawang'
            )
            ->assertJsonPath(
                'data.province',
                'Kalimantan Barat'
            );

        $this->assertDatabaseHas(
            'customer_addresses',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'business_id' =>
                    $workspace['business_id'],
                'customer_id' =>
                    $customerId,
                'address_line_1' =>
                    'Jl. Lama No. 10',
                'city' =>
                    'Singkawang',
                'province' =>
                    'Kalimantan Barat',
                'is_primary' =>
                    true,
            ]
        );
    }

    public function test_same_customer_code_is_allowed_between_businesses_in_same_tenant(): void
    {
        $workspace = $this->workspace(
            'customer-code-business@example.test',
            'Customer Code Business Scope'
        );

        $this->actingAsWorkspace($workspace);

        $firstResponse = $this->postJson(
            '/api/v1/customers',
            [
                'code' => 'SAME-BUSINESS-CODE',
                'name' => 'Pelanggan Usaha Pertama',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.code',
                'SAME-BUSINESS-CODE'
            );

        $firstCustomerId =
            $firstResponse->json('data.id');

        $secondBusinessId =
            (string) \Illuminate\Support\Str::ulid();

        DB::table('business_profiles')->insert([
            'id' => $secondBusinessId,
            'tenant_id' => $workspace['tenant_id'],
            'name' => 'Usaha Kedua Kode Sama',
            'is_default' => false,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondWorkspace = $workspace;
        $secondWorkspace['business_id'] =
            $secondBusinessId;

        $this->actingAsWorkspace(
            $secondWorkspace
        );

        $secondResponse = $this->postJson(
            '/api/v1/customers',
            [
                'code' => 'SAME-BUSINESS-CODE',
                'name' => 'Pelanggan Usaha Kedua',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.code',
                'SAME-BUSINESS-CODE'
            );

        $secondCustomerId =
            $secondResponse->json('data.id');

        $this->assertNotSame(
            $firstCustomerId,
            $secondCustomerId
        );

        $this->assertDatabaseHas(
            'customers',
            [
                'id' => $firstCustomerId,
                'tenant_id' =>
                    $workspace['tenant_id'],
                'business_id' =>
                    $workspace['business_id'],
                'code' =>
                    'SAME-BUSINESS-CODE',
            ]
        );

        $this->assertDatabaseHas(
            'customers',
            [
                'id' => $secondCustomerId,
                'tenant_id' =>
                    $workspace['tenant_id'],
                'business_id' =>
                    $secondBusinessId,
                'code' =>
                    'SAME-BUSINESS-CODE',
            ]
        );

        $this->assertSame(
            2,
            DB::table('customers')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'code',
                    'SAME-BUSINESS-CODE'
                )
                ->count()
        );
    }

    public function test_customers_are_isolated_between_businesses_in_same_tenant(): void
    {
        $workspace = $this->workspace(
            'customer-business-isolation@example.test',
            'Customer Business Isolation'
        );

        $secondBusinessId =
            (string) \Illuminate\Support\Str::ulid();

        DB::table('business_profiles')->insert([
            'id' => $secondBusinessId,
            'tenant_id' => $workspace['tenant_id'],
            'name' => 'Usaha Kedua',
            'is_default' => false,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $foreignCustomerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                $secondBusinessId,
                'SECOND-BUSINESS-CUSTOMER',
                'Pelanggan Usaha Kedua'
            );

        $this->actingAsWorkspace($workspace);

        $listResponse = $this->getJson(
            '/api/v1/customers'
        );

        $listResponse->assertOk();

        $this->assertFalse(
            collect($listResponse->json('data'))
                ->contains(
                    fn (array $customer) =>
                        $customer['id']
                        === $foreignCustomerId
                )
        );

        $this->getJson(
            "/api/v1/customers/{$foreignCustomerId}"
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );

        $this->patchJson(
            "/api/v1/customers/{$foreignCustomerId}",
            [
                'name' =>
                    'Perubahan Tidak Sah',
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
                    $workspace['tenant_id'],
                'business_id' =>
                    $secondBusinessId,
                'name' =>
                    'Pelanggan Usaha Kedua',
            ]
        );

        $this->assertDatabaseMissing(
            'customers',
            [
                'id' => $foreignCustomerId,
                'name' =>
                    'Perubahan Tidak Sah',
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

        $this->withHeader(
            'X-Signova-Business',
            $workspace['business_id']
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
        string $businessId,
        string $code,
        string $name
    ): string {
        $id = (string) \Illuminate\Support\Str::ulid();

        DB::table('customers')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'business_id' => $businessId,
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
