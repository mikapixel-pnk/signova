<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_owner_can_create_draft_with_backend_calculated_area_pricing(): void
    {
        $workspace = $this->workspace(
            'quotation-api-create@example.test',
            'Quotation API Create'
        );

        $this->actingAsWorkspace($workspace);

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-Q-001',
            'Pelanggan Penawaran'
        );

        $catalogId = $this->insertCatalogItem(
            $workspace['tenant_id'],
            'SPANDUK-001',
            'Spanduk Flexi',
            'SERVICE',
            'AREA',
            '25000'
        );

        $response = $this->postJson(
            '/api/v1/quotations',
            [
                'quotation_number' =>
                    'Q-API-001',

                'customer_id' =>
                    $customerId,

                'valid_until' =>
                    now()
                        ->addDays(14)
                        ->toDateString(),

                'notes' =>
                    'Penawaran awal',

                'items' => [
                    [
                        'catalog_item_id' =>
                            $catalogId,

                        'quantity' => '2',

                        'pricing_config' => [
                            'width' => '3.5',
                            'height' => '1.2',
                        ],
                    ],
                ],
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.quotation_number',
                'Q-API-001'
            )
            ->assertJsonPath(
                'data.status',
                'DRAFT'
            )
            ->assertJsonPath(
                'data.status_label',
                'Draf'
            )
            ->assertJsonPath(
                'data.current_version.revision_no',
                1
            )
            ->assertJsonPath(
                'data.current_version.subtotal',
                '210000.00'
            )
            ->assertJsonPath(
                'data.current_version.total',
                '210000.00'
            )
            ->assertJsonPath(
                'data.current_version.items.0.pricing_method',
                'AREA'
            )
            ->assertJsonPath(
                'data.current_version.items.0.amount',
                '210000.00'
            )
            ->assertJsonMissingPath(
                'data.tenant_id'
            );

        $this->assertDatabaseHas(
            'quotations',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'quotation_number' =>
                    'Q-API-001',

                'status' =>
                    'DRAFT',
            ]
        );

        $this->assertDatabaseHas(
            'quotation_versions',
            [
                'subtotal' =>
                    210000,

                'total' =>
                    210000,
            ]
        );
    }

    public function test_area_pricing_without_required_dimensions_is_validation_error(): void
    {
        $workspace = $this->workspace(
            'quotation-api-area-invalid@example.test',
            'Quotation API Area Invalid'
        );

        $this->actingAsWorkspace($workspace);

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-AREA-INVALID',
            'Pelanggan Area Invalid'
        );

        $catalogId = $this->insertCatalogItem(
            $workspace['tenant_id'],
            'AREA-INVALID',
            'Item Luas Invalid',
            'SERVICE',
            'AREA',
            '25000'
        );

        $response = $this->postJson(
            '/api/v1/quotations',
            [
                'quotation_number' =>
                    'Q-AREA-INVALID',

                'customer_id' =>
                    $customerId,

                'items' => [
                    [
                        'catalog_item_id' =>
                            $catalogId,

                        'quantity' => 1,

                        'pricing_config' => [],
                    ],
                ],
            ]
        );

        $response
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
                        'fields' => [
                            'items.0.pricing_config.width',
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseMissing(
            'quotations',
            [
                'quotation_number' =>
                    'Q-AREA-INVALID',
            ]
        );
    }


    public function test_owner_can_update_draft_header(): void
    {
        $workspace = $this->workspace(
            'quotation-api-update@example.test',
            'Quotation API Update'
        );

        $this->actingAsWorkspace($workspace);

        $firstCustomerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-UPDATE-OLD',
            'Pelanggan Lama'
        );

        $secondCustomerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-UPDATE-NEW',
            'Pelanggan Baru'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $firstCustomerId,
            'Q-UPDATE-DRAFT',
            'DRAFT'
        );

        $response = $this->patchJson(
            "/api/v1/quotations/{$quotationId}",
            [
                'customer_id' => $secondCustomerId,
                'valid_until' => now()
                    ->addDays(30)
                    ->toDateString(),
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.customer.id',
                $secondCustomerId
            )
            ->assertJsonPath(
                'data.status',
                'DRAFT'
            );

        $this->assertDatabaseHas(
            'quotations',
            [
                'id' => $quotationId,
                'tenant_id' => $workspace['tenant_id'],
                'customer_id' => $secondCustomerId,
                'status' => 'DRAFT',
            ]
        );

        $this->assertDatabaseCount(
            'quotation_versions',
            1
        );
    }

    public function test_non_draft_quotation_header_cannot_be_updated(): void
    {
        $workspace = $this->workspace(
            'quotation-api-update-state@example.test',
            'Quotation API Update State'
        );

        $this->actingAsWorkspace($workspace);

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-UPDATE-STATE',
            'Pelanggan Update State'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-UPDATE-SENT',
            'SENT'
        );

        $this->patchJson(
            "/api/v1/quotations/{$quotationId}",
            [
                'valid_until' => now()
                    ->addDays(30)
                    ->toDateString(),
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'error.code',
                'QUOTATION_NOT_EDITABLE'
            );
    }

    public function test_update_draft_rejects_cross_tenant_customer(): void
    {
        $first = $this->workspace(
            'quotation-api-update-first@example.test',
            'Quotation API Update First'
        );

        $second = $this->workspace(
            'quotation-api-update-second@example.test',
            'Quotation API Update Second'
        );

        $localCustomerId = $this->insertCustomer(
            $first['tenant_id'],
            'CUST-UPDATE-LOCAL',
            'Pelanggan Lokal'
        );

        $foreignCustomerId = $this->insertCustomer(
            $second['tenant_id'],
            'CUST-UPDATE-FOREIGN',
            'Pelanggan Asing'
        );

        $quotationId = $this->insertQuotation(
            $first,
            $localCustomerId,
            'Q-UPDATE-CROSS',
            'DRAFT'
        );

        $this->actingAsWorkspace($first);

        $this->patchJson(
            "/api/v1/quotations/{$quotationId}",
            [
                'customer_id' => $foreignCustomerId,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->assertDatabaseHas(
            'quotations',
            [
                'id' => $quotationId,
                'customer_id' => $localCustomerId,
            ]
        );
    }


    public function test_update_draft_requires_at_least_one_editable_field(): void
    {
        $workspace = $this->workspace(
            'quotation-api-update-empty@example.test',
            'Quotation API Update Empty'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-UPDATE-EMPTY',
            'Pelanggan Update Empty'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-UPDATE-EMPTY',
            'DRAFT'
        );

        $this->actingAsWorkspace($workspace);

        $this->patchJson(
            "/api/v1/quotations/{$quotationId}",
            []
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_update_draft_from_other_tenant_is_not_found(): void
    {
        $first = $this->workspace(
            'quotation-api-update-owner@example.test',
            'Quotation API Update Owner'
        );

        $second = $this->workspace(
            'quotation-api-update-foreign@example.test',
            'Quotation API Update Foreign'
        );

        $foreignCustomerId = $this->insertCustomer(
            $second['tenant_id'],
            'CUST-UPDATE-FOREIGN-Q',
            'Pelanggan Foreign Quotation'
        );

        $quotationId = $this->insertQuotation(
            $second,
            $foreignCustomerId,
            'Q-UPDATE-FOREIGN',
            'DRAFT'
        );

        $this->actingAsWorkspace($first);

        $this->patchJson(
            "/api/v1/quotations/{$quotationId}",
            [
                'valid_until' => now()
                    ->addDays(20)
                    ->toDateString(),
            ]
        )->assertNotFound();
    }

    public function test_missing_quotation_update_capability_is_denied(): void
    {
        $workspace = $this->workspace(
            'quotation-api-update-denied@example.test',
            'Quotation API Update Denied'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-UPDATE-DENIED',
            'Pelanggan Update Denied'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-UPDATE-DENIED',
            'DRAFT'
        );

        $this->actingAsWorkspace($workspace);

        $this->denyCapability(
            $workspace,
            'quotation.update'
        );

        $this->patchJson(
            "/api/v1/quotations/{$quotationId}",
            [
                'valid_until' => now()
                    ->addDays(15)
                    ->toDateString(),
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_backend_owned_financial_fields_are_rejected(): void
    {
        $workspace = $this->workspace(
            'quotation-api-forged@example.test',
            'Quotation API Forged'
        );

        $this->actingAsWorkspace($workspace);

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-FORGED',
            'Pelanggan Forged'
        );

        $response = $this->postJson(
            '/api/v1/quotations',
            [
                'quotation_number' =>
                    'Q-FORGED',

                'customer_id' =>
                    $customerId,

                'subtotal' => 1,
                'total' => 1,

                'items' => [
                    [
                        'name' =>
                            'Item Manual',

                        'item_type' =>
                            'SERVICE',

                        'pricing_method' =>
                            'MANUAL',

                        'quantity' => 1,
                        'unit_price' => 100000,

                        'tax_amount' => 1,
                        'amount' => 1,
                    ],
                ],
            ]
        );

        $response
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
                        'fields' => [
                            'subtotal',
                            'total',
                            'items.0.tax_amount',
                            'items.0.amount',
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseMissing(
            'quotations',
            [
                'quotation_number' =>
                    'Q-FORGED',
            ]
        );
    }

    public function test_list_is_tenant_scoped_searchable_and_filterable(): void
    {
        $first = $this->workspace(
            'quotation-api-list-first@example.test',
            'Quotation API First'
        );

        $second = $this->workspace(
            'quotation-api-list-second@example.test',
            'Quotation API Second'
        );

        $firstCustomer = $this->insertCustomer(
            $first['tenant_id'],
            'CUST-FIRST',
            'Alpha Reklame'
        );

        $secondCustomer = $this->insertCustomer(
            $second['tenant_id'],
            'CUST-SECOND',
            'Beta Reklame'
        );

        $this->insertQuotation(
            $first,
            $firstCustomer,
            'Q-ALPHA',
            'DRAFT'
        );

        $otherCustomer = $this->insertCustomer(
            $first['tenant_id'],
            'CUST-OTHER',
            'Gamma Reklame'
        );

        $this->insertQuotation(
            $first,
            $otherCustomer,
            'Q-OTHER',
            'DRAFT'
        );

        $this->insertQuotation(
            $second,
            $secondCustomer,
            'Q-FOREIGN',
            'DRAFT'
        );

        $this->actingAsWorkspace($first);

        $response = $this->getJson(
            '/api/v1/quotations'
            . '?search=ALPHA'
            . '&status=DRAFT'
        );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.quotation_number',
                'Q-ALPHA'
            )
            ->assertJsonPath(
                'meta.total',
                1
            );

        $this->assertStringNotContainsString(
            'Q-FOREIGN',
            $response->getContent()
        );
    }

    public function test_detail_is_tenant_scoped(): void
    {
        $first = $this->workspace(
            'quotation-api-detail-first@example.test',
            'Quotation Detail First'
        );

        $second = $this->workspace(
            'quotation-api-detail-second@example.test',
            'Quotation Detail Second'
        );

        $foreignCustomer = $this->insertCustomer(
            $second['tenant_id'],
            'CUST-FOREIGN-DETAIL',
            'Foreign Customer'
        );

        $quotationId = $this->insertQuotation(
            $second,
            $foreignCustomer,
            'Q-FOREIGN-DETAIL',
            'DRAFT'
        );

        $this->actingAsWorkspace($first);

        $this->getJson(
            "/api/v1/quotations/{$quotationId}"
        )->assertNotFound();
    }

    public function test_cross_tenant_customer_is_rejected(): void
    {
        $first = $this->workspace(
            'quotation-api-customer-first@example.test',
            'Quotation Customer First'
        );

        $second = $this->workspace(
            'quotation-api-customer-second@example.test',
            'Quotation Customer Second'
        );

        $foreignCustomerId =
            $this->insertCustomer(
                $second['tenant_id'],
                'CUST-FOREIGN',
                'Foreign Customer'
            );

        $this->actingAsWorkspace($first);

        $this->postJson(
            '/api/v1/quotations',
            [
                'quotation_number' =>
                    'Q-CROSS-CUSTOMER',

                'customer_id' =>
                    $foreignCustomerId,

                'items' => [
                    [
                        'name' =>
                            'Manual Item',

                        'pricing_method' =>
                            'MANUAL',

                        'unit_price' =>
                            10000,
                    ],
                ],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->assertDatabaseMissing(
            'quotations',
            [
                'quotation_number' =>
                    'Q-CROSS-CUSTOMER',
            ]
        );
    }

    public function test_cross_tenant_catalog_item_is_rejected(): void
    {
        $first = $this->workspace(
            'quotation-api-catalog-first@example.test',
            'Quotation Catalog First'
        );

        $second = $this->workspace(
            'quotation-api-catalog-second@example.test',
            'Quotation Catalog Second'
        );

        $customerId = $this->insertCustomer(
            $first['tenant_id'],
            'CUST-LOCAL',
            'Local Customer'
        );

        $foreignCatalogId =
            $this->insertCatalogItem(
                $second['tenant_id'],
                'FOREIGN-CATALOG',
                'Foreign Catalog',
                'PRODUCT',
                'STANDARD',
                '10000'
            );

        $this->actingAsWorkspace($first);

        $this->postJson(
            '/api/v1/quotations',
            [
                'quotation_number' =>
                    'Q-CROSS-CATALOG',

                'customer_id' =>
                    $customerId,

                'items' => [
                    [
                        'catalog_item_id' =>
                            $foreignCatalogId,

                        'quantity' => 1,
                    ],
                ],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->assertDatabaseMissing(
            'quotations',
            [
                'quotation_number' =>
                    'Q-CROSS-CATALOG',
            ]
        );
    }

    public function test_missing_quotation_view_capability_is_denied(): void
    {
        $workspace = $this->workspace(
            'quotation-api-view-denied@example.test',
            'Quotation View Denied'
        );

        $this->actingAsWorkspace($workspace);

        $this->denyCapability(
            $workspace,
            'quotation.view'
        );

        $this->getJson(
            '/api/v1/quotations'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_missing_quotation_create_capability_is_denied(): void
    {
        $workspace = $this->workspace(
            'quotation-api-create-denied@example.test',
            'Quotation Create Denied'
        );

        $this->actingAsWorkspace($workspace);

        $this->denyCapability(
            $workspace,
            'quotation.create'
        );

        $this->postJson(
            '/api/v1/quotations',
            []
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    private function workspace(
        string $email,
        string $businessName
    ): array {
        $workspace = app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Owner',
            'email' => $email,
            'password' => 'password',
            'tenant_name' => $businessName,
            'timezone' => 'Asia/Jakarta',
        ]);

        return [
            'user_id' =>
                $workspace['user_id'],

            'tenant_id' =>
                $workspace['tenant_id'],

            'user' =>
                User::query()->findOrFail(
                    $workspace['user_id']
                ),
        ];
    }

    private function actingAsWorkspace(
        array $workspace
    ): void {
        $this->actingAs(
            $workspace['user'],
            'sanctum'
        );

        $this->withHeader(
            'X-Tenant-ID',
            $workspace['tenant_id']
        );
    }

    private function insertCustomer(
        string $tenantId,
        string $code,
        string $name
    ): string {
        $id = (string) Str::ulid();

        DB::table('customers')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'type' => 'COMPANY',
            'code' => $code,
            'name' => $name,
            'status' => 'ACTIVE',
            'payment_terms_days' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function insertCatalogItem(
        string $tenantId,
        string $code,
        string $name,
        string $type,
        string $pricingMethod,
        string $basePrice
    ): string {
        $id = (string) Str::ulid();

        DB::table('catalog_items')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'category_id' => null,
            'unit_id' => null,
            'type' => $type,
            'code' => $code,
            'name' => $name,
            'description' => null,
            'pricing_method' =>
                $pricingMethod,
            'base_price' => $basePrice,
            'currency' => 'IDR',
            'pricing_config' => null,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function insertQuotation(
        array $workspace,
        string $customerId,
        string $number,
        string $status
    ): string {
        $this->setTenantContext(
            $workspace
        );

        $quotation =
            app(
                \App\Services\Quotation\QuotationService::class
            )->createDraft(
                [
                    'quotation_number' =>
                        $number,

                    'customer_id' =>
                        $customerId,
                ],
                [],
                [
                    [
                        'name' =>
                            'Manual Item',

                        'pricing_method' =>
                            'MANUAL',

                        'quantity' => 1,

                        'unit_price' =>
                            10000,
                    ],
                ]
            );

        if ($status !== 'DRAFT') {
            DB::table('quotations')
                ->where(
                    'id',
                    $quotation->id
                )
                ->update([
                    'status' => $status,
                ]);
        }

        return $quotation->id;
    }

    private function setTenantContext(
        array $workspace
    ): void {
        app(
            TenantContext::class
        )->set(
            $workspace['tenant_id'],
            $workspace['user_id']
        );
    }

    private function denyCapability(
        array $workspace,
        string $capability
    ): void {
        $roleId = DB::table('roles')
            ->where(
                'tenant_id',
                $workspace['tenant_id']
            )
            ->where('code', 'OWNER')
            ->value('id');

        $capabilityId =
            DB::table('capabilities')
                ->where(
                    'code',
                    $capability
                )
                ->value('id');

        $this->assertNotNull($roleId);
        $this->assertNotNull($capabilityId);

        DB::table('role_capabilities')
            ->updateOrInsert(
                [
                    'role_id' => $roleId,
                    'capability_id' =>
                        $capabilityId,
                ],
                [
                    'effect' => 'DENY',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
    }
}
