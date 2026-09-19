<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_owner_can_create_supplier(): void
    {
        $workspace = $this->workspace(
            'supplier-create@example.test',
            'Supplier Create'
        );

        $this->actingAsWorkspace($workspace);

        $response = $this->postJson(
            '/api/v1/suppliers',
            [
                'code' => 'SUP-001',
                'name' => 'PT Bahan Reklame',
                'contact_name' => 'Andi',
                'phone' => '081234567890',
                'email' => 'ANDI@EXAMPLE.TEST',
                'address' => 'Jl. Industri No. 10',
                'city' => 'Pontianak',
                'province' => 'Kalimantan Barat',
                'payment_terms_days' => 30,
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.name',
                'PT Bahan Reklame'
            )
            ->assertJsonPath(
                'data.code',
                'SUP-001'
            )
            ->assertJsonPath(
                'data.email',
                'andi@example.test'
            )
            ->assertJsonMissingPath(
                'data.tenant_id'
            )
            ->assertJsonMissingPath(
                'data.business_id'
            );

        $this->assertDatabaseHas(
            'suppliers',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'business_id' =>
                    $workspace['business_id'],
                'code' => 'SUP-001',
                'name' => 'PT Bahan Reklame',
                'status' => 'ACTIVE',
            ]
        );
    }

    public function test_supplier_list_is_scoped_and_searchable(): void
    {
        $first = $this->workspace(
            'supplier-list-first@example.test',
            'Supplier List First'
        );

        $second = $this->workspace(
            'supplier-list-second@example.test',
            'Supplier List Second'
        );

        $this->insertSupplier(
            $first['tenant_id'],
            $first['business_id'],
            'FIRST-001',
            'Alpha Material'
        );

        $this->insertSupplier(
            $first['tenant_id'],
            $first['business_id'],
            'FIRST-002',
            'Beta Acrylic'
        );

        $this->insertSupplier(
            $second['tenant_id'],
            $second['business_id'],
            'SECOND-001',
            'Alpha Tenant Lain'
        );

        $this->actingAsWorkspace($first);

        $this->getJson(
            '/api/v1/suppliers?search=Alpha'
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.name',
                'Alpha Material'
            )
            ->assertJsonPath(
                'meta.total',
                1
            );
    }

    public function test_owner_can_update_supplier(): void
    {
        $workspace = $this->workspace(
            'supplier-update@example.test',
            'Supplier Update'
        );

        $supplierId = $this->insertSupplier(
            $workspace['tenant_id'],
            $workspace['business_id'],
            'UPDATE-001',
            'Nama Lama'
        );

        $this->actingAsWorkspace($workspace);

        $this->patchJson(
            "/api/v1/suppliers/{$supplierId}",
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
            'suppliers',
            [
                'id' => $supplierId,
                'tenant_id' =>
                    $workspace['tenant_id'],
                'business_id' =>
                    $workspace['business_id'],
                'name' => 'Nama Baru',
                'status' => 'INACTIVE',
            ]
        );
    }

    public function test_supplier_validation_uses_standard_contract(): void
    {
        $workspace = $this->workspace(
            'supplier-validation@example.test',
            'Supplier Validation'
        );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/suppliers',
            [
                'name' => '',
                'email' => 'bukan-email',
            ]
        )
            ->assertUnprocessable()
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

    public function test_duplicate_supplier_code_is_rejected_in_same_business(): void
    {
        $workspace = $this->workspace(
            'supplier-duplicate@example.test',
            'Supplier Duplicate'
        );

        $this->insertSupplier(
            $workspace['tenant_id'],
            $workspace['business_id'],
            'DUP-001',
            'Existing Supplier'
        );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/suppliers',
            [
                'code' => 'DUP-001',
                'name' => 'Duplicate Supplier',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_same_supplier_code_is_allowed_between_businesses(): void
    {
        $workspace = $this->workspace(
            'supplier-code-business@example.test',
            'Supplier Business Code'
        );

        $this->actingAsWorkspace($workspace);

        $firstResponse = $this->postJson(
            '/api/v1/suppliers',
            [
                'code' => 'SAME-CODE',
                'name' => 'Pemasok Usaha Pertama',
            ]
        )->assertCreated();

        $secondBusinessId =
            (string) \Illuminate\Support\Str::ulid();

        DB::table('business_profiles')->insert([
            'id' => $secondBusinessId,
            'tenant_id' => $workspace['tenant_id'],
            'name' => 'Usaha Kedua Supplier',
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
            '/api/v1/suppliers',
            [
                'code' => 'SAME-CODE',
                'name' => 'Pemasok Usaha Kedua',
            ]
        )->assertCreated();

        $this->assertNotSame(
            $firstResponse->json('data.id'),
            $secondResponse->json('data.id')
        );

        $this->assertSame(
            2,
            DB::table('suppliers')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'code',
                    'SAME-CODE'
                )
                ->count()
        );
    }

    public function test_cross_tenant_supplier_returns_not_found(): void
    {
        $first = $this->workspace(
            'supplier-cross-first@example.test',
            'Supplier Cross First'
        );

        $second = $this->workspace(
            'supplier-cross-second@example.test',
            'Supplier Cross Second'
        );

        $foreignSupplierId =
            $this->insertSupplier(
                $second['tenant_id'],
                $second['business_id'],
                'FOREIGN-001',
                'Foreign Supplier'
            );

        $this->actingAsWorkspace($first);

        $this->getJson(
            "/api/v1/suppliers/{$foreignSupplierId}"
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );
    }

    public function test_suppliers_are_isolated_between_businesses(): void
    {
        $workspace = $this->workspace(
            'supplier-business-isolation@example.test',
            'Supplier Business Isolation'
        );

        $secondBusinessId =
            (string) \Illuminate\Support\Str::ulid();

        DB::table('business_profiles')->insert([
            'id' => $secondBusinessId,
            'tenant_id' => $workspace['tenant_id'],
            'name' => 'Supplier Usaha Kedua',
            'is_default' => false,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $foreignSupplierId =
            $this->insertSupplier(
                $workspace['tenant_id'],
                $secondBusinessId,
                'SECOND-BUSINESS',
                'Pemasok Usaha Kedua'
            );

        $this->actingAsWorkspace($workspace);

        $listResponse = $this->getJson(
            '/api/v1/suppliers'
        )->assertOk();

        $this->assertFalse(
            collect(
                $listResponse->json('data')
            )->contains(
                fn (array $supplier) =>
                    $supplier['id']
                    === $foreignSupplierId
            )
        );

        $this->getJson(
            "/api/v1/suppliers/{$foreignSupplierId}"
        )->assertNotFound();

        $this->patchJson(
            "/api/v1/suppliers/{$foreignSupplierId}",
            [
                'name' =>
                    'Perubahan Tidak Sah',
            ]
        )->assertNotFound();

        $this->assertDatabaseHas(
            'suppliers',
            [
                'id' => $foreignSupplierId,
                'business_id' =>
                    $secondBusinessId,
                'name' =>
                    'Pemasok Usaha Kedua',
            ]
        );

        $this->assertDatabaseMissing(
            'suppliers',
            [
                'id' => $foreignSupplierId,
                'name' =>
                    'Perubahan Tidak Sah',
            ]
        );
    }

    public function test_missing_supplier_view_capability_is_denied(): void
    {
        $workspace = $this->workspace(
            'supplier-view-denied@example.test',
            'Supplier View Denied'
        );

        $this->revokeOwnerCapability(
            $workspace,
            'supplier.view'
        );

        $this->actingAsWorkspace($workspace);

        $this->getJson('/api/v1/suppliers')
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_missing_supplier_create_capability_is_denied(): void
    {
        $workspace = $this->workspace(
            'supplier-create-denied@example.test',
            'Supplier Create Denied'
        );

        $this->revokeOwnerCapability(
            $workspace,
            'supplier.create'
        );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/suppliers',
            [
                'name' => 'Tidak Boleh',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseMissing(
            'suppliers',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'name' => 'Tidak Boleh',
            ]
        );
    }

    public function test_missing_supplier_update_capability_is_denied(): void
    {
        $workspace = $this->workspace(
            'supplier-update-denied@example.test',
            'Supplier Update Denied'
        );

        $supplierId = $this->insertSupplier(
            $workspace['tenant_id'],
            $workspace['business_id'],
            'DENIED-001',
            'Nama Asli'
        );

        $this->revokeOwnerCapability(
            $workspace,
            'supplier.update'
        );

        $this->actingAsWorkspace($workspace);

        $this->patchJson(
            "/api/v1/suppliers/{$supplierId}",
            [
                'name' => 'Tidak Sah',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseHas(
            'suppliers',
            [
                'id' => $supplierId,
                'name' => 'Nama Asli',
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
            'name' => 'Supplier API Test',
            'email' => $email,
            'password' =>
                'SecurePassword123!',
            'tenant_name' => $tenantName,
        ]);
    }

    private function insertSupplier(
        string $tenantId,
        string $businessId,
        string $code,
        string $name
    ): string {
        $id =
            (string) \Illuminate\Support\Str::ulid();

        DB::table('suppliers')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'business_id' => $businessId,
            'code' => $code,
            'name' => $name,
            'contact_name' => null,
            'phone' => null,
            'email' => null,
            'tax_id' => null,
            'address' => null,
            'city' => null,
            'province' => null,
            'payment_terms_days' => 0,
            'notes' => null,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
