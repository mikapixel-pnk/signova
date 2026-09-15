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

                        'tax_rate' =>
                            '11',
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
                fn ($value) =>
                    is_string($value)
                    && preg_match(
                        '/^PEN-\\d{4}-\\d{6}$/',
                        $value
                    ) === 1
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
                'data.current_version.tax_total',
                '23100.00'
            )
            ->assertJsonPath(
                'data.current_version.total',
                '233100.00'
            )
            ->assertJsonPath(
                'data.current_version.items.0.pricing_method',
                'AREA'
            )
            ->assertJsonPath(
                'data.current_version.items.0.pricing_config.tax_rate',
                '11.0000'
            )
            ->assertJsonPath(
                'data.current_version.items.0.tax_amount',
                '23100.00'
            )
            ->assertJsonPath(
                'data.current_version.items.0.amount',
                '233100.00'
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
                    $response->json(
                        'data.quotation_number'
                    ),

                'status' =>
                    'DRAFT',
            ]
        );

        $this->assertDatabaseHas(
            'quotation_versions',
            [
                'subtotal' =>
                    210000,

                'tax_total' =>
                    23100,

                'total' =>
                    233100,
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


    public function test_owner_can_create_draft_revision_with_backend_pricing(): void
    {
        $workspace = $this->workspace(
            'quotation-api-revision@example.test',
            'Quotation API Revision'
        );

        $this->actingAsWorkspace($workspace);

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-REV-API',
            'Pelanggan Revision API'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-REV-API',
            'DRAFT'
        );

        $oldVersionId = DB::table('quotations')
            ->where('id', $quotationId)
            ->value('current_version_id');

        $response = $this->postJson(
            "/api/v1/quotations/{$quotationId}/versions",
            [
                'currency' => 'idr',
                'notes' => 'Revisi kedua',
                'items' => [
                    [
                        'name' => 'Item Revisi',
                        'item_type' => 'SERVICE',
                        'pricing_method' => 'MANUAL',
                        'quantity' => 2,
                        'unit_price' => 75000,
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
                'data.current_version.revision_no',
                2
            )
            ->assertJsonPath(
                'data.current_version.currency',
                'IDR'
            )
            ->assertJsonPath(
                'data.current_version.notes',
                'Revisi kedua'
            )
            ->assertJsonPath(
                'data.current_version.total',
                '150000.00'
            );

        $newVersionId = DB::table('quotations')
            ->where('id', $quotationId)
            ->value('current_version_id');

        $this->assertNotSame(
            $oldVersionId,
            $newVersionId
        );

        $this->assertDatabaseHas(
            'quotation_versions',
            [
                'id' => $oldVersionId,
                'quotation_id' => $quotationId,
                'revision_no' => 1,
            ]
        );

        $this->assertDatabaseHas(
            'quotation_versions',
            [
                'id' => $newVersionId,
                'quotation_id' => $quotationId,
                'revision_no' => 2,
                'total' => 150000,
            ]
        );

        $this->assertDatabaseCount(
            'quotation_versions',
            2
        );
    }

    public function test_non_draft_quotation_revision_is_rejected(): void
    {
        $workspace = $this->workspace(
            'quotation-api-revision-state@example.test',
            'Quotation API Revision State'
        );

        $this->actingAsWorkspace($workspace);

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-REV-STATE',
            'Pelanggan Revision State'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-REV-SENT',
            'SENT'
        );

        $this->postJson(
            "/api/v1/quotations/{$quotationId}/versions",
            [
                'items' => [
                    [
                        'name' => 'Item Revisi',
                        'pricing_method' => 'MANUAL',
                        'quantity' => 1,
                        'unit_price' => 100000,
                    ],
                ],
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'QUOTATION_NOT_EDITABLE'
            );

        $this->assertDatabaseCount(
            'quotation_versions',
            1
        );
    }

    public function test_revision_of_foreign_tenant_quotation_is_not_found(): void
    {
        $first = $this->workspace(
            'quotation-api-revision-first@example.test',
            'Quotation API Revision First'
        );

        $second = $this->workspace(
            'quotation-api-revision-second@example.test',
            'Quotation API Revision Second'
        );

        $foreignCustomerId = $this->insertCustomer(
            $second['tenant_id'],
            'CUST-REV-FOREIGN',
            'Pelanggan Revision Foreign'
        );

        $quotationId = $this->insertQuotation(
            $second,
            $foreignCustomerId,
            'Q-REV-FOREIGN',
            'DRAFT'
        );

        $this->actingAsWorkspace($first);

        $this->postJson(
            "/api/v1/quotations/{$quotationId}/versions",
            [
                'items' => [
                    [
                        'name' => 'Item Revisi',
                        'pricing_method' => 'MANUAL',
                        'quantity' => 1,
                        'unit_price' => 100000,
                    ],
                ],
            ]
        )->assertNotFound();
    }

    public function test_revision_rejects_backend_owned_totals(): void
    {
        $workspace = $this->workspace(
            'quotation-api-revision-forged@example.test',
            'Quotation API Revision Forged'
        );

        $this->actingAsWorkspace($workspace);

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-REV-FORGED',
            'Pelanggan Revision Forged'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-REV-FORGED',
            'DRAFT'
        );

        $this->postJson(
            "/api/v1/quotations/{$quotationId}/versions",
            [
                'total' => 1,
                'items' => [
                    [
                        'name' => 'Item Revisi',
                        'pricing_method' => 'MANUAL',
                        'quantity' => 1,
                        'unit_price' => 100000,
                        'amount' => 1,
                    ],
                ],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->assertDatabaseCount(
            'quotation_versions',
            1
        );
    }


    public function test_missing_quotation_update_capability_blocks_revision(): void
    {
        $workspace = $this->workspace(
            'quotation-api-revision-denied@example.test',
            'Quotation API Revision Denied'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-REV-DENIED',
            'Pelanggan Revision Denied'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-REV-DENIED',
            'DRAFT'
        );

        $this->actingAsWorkspace($workspace);

        $this->denyCapability(
            $workspace,
            'quotation.update'
        );

        $this->postJson(
            "/api/v1/quotations/{$quotationId}/versions",
            [
                'items' => [
                    [
                        'name' => 'Item Revisi',
                        'pricing_method' => 'MANUAL',
                        'quantity' => 1,
                        'unit_price' => 100000,
                    ],
                ],
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_revision_rejects_cross_tenant_catalog_item(): void
    {
        $first = $this->workspace(
            'quotation-api-revision-local@example.test',
            'Quotation API Revision Local'
        );

        $second = $this->workspace(
            'quotation-api-revision-catalog-foreign@example.test',
            'Quotation API Revision Catalog Foreign'
        );

        $customerId = $this->insertCustomer(
            $first['tenant_id'],
            'CUST-REV-LOCAL',
            'Pelanggan Revision Local'
        );

        $foreignCatalogId = $this->insertCatalogItem(
            $second['tenant_id'],
            'REV-FOREIGN-CAT',
            'Catalog Asing',
            'SERVICE',
            'STANDARD',
            '100000'
        );

        $quotationId = $this->insertQuotation(
            $first,
            $customerId,
            'Q-REV-CROSS-CAT',
            'DRAFT'
        );

        $this->actingAsWorkspace($first);

        $this->postJson(
            "/api/v1/quotations/{$quotationId}/versions",
            [
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

        $this->assertDatabaseCount(
            'quotation_versions',
            1
        );
    }

    public function test_revision_area_pricing_missing_dimension_is_validation_error(): void
    {
        $workspace = $this->workspace(
            'quotation-api-revision-area@example.test',
            'Quotation API Revision Area'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-REV-AREA',
            'Pelanggan Revision Area'
        );

        $catalogId = $this->insertCatalogItem(
            $workspace['tenant_id'],
            'REV-AREA',
            'Item Area Revision',
            'SERVICE',
            'AREA',
            '25000'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-REV-AREA',
            'DRAFT'
        );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            "/api/v1/quotations/{$quotationId}/versions",
            [
                'items' => [
                    [
                        'catalog_item_id' => $catalogId,
                        'quantity' => 1,
                        'pricing_config' => [],
                    ],
                ],
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
                            'items.0.pricing_config.width',
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseCount(
            'quotation_versions',
            1
        );
    }


    public function test_owner_can_send_draft_quotation(): void
    {
        $workspace = $this->workspace(
            'quotation-api-send@example.test',
            'Quotation API Send'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-API-SEND',
            'Pelanggan API Send'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-API-SEND',
            'DRAFT'
        );

        $this->actingAsWorkspace($workspace);

        $response = $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/send"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.status',
                'SENT'
            );

        $this->assertDatabaseHas(
            'quotations',
            [
                'id' => $quotationId,
                'status' => 'SENT',
            ]
        );

        $this->assertDatabaseHas(
            'quotation_status_history',
            [
                'quotation_id' => $quotationId,
                'from_state' => 'DRAFT',
                'to_state' => 'SENT',
            ]
        );
    }

    public function test_send_invalid_transition_returns_409(): void
    {
        $workspace = $this->workspace(
            'quotation-api-send-invalid@example.test',
            'Quotation API Send Invalid'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-API-SEND-INVALID',
            'Pelanggan API Send Invalid'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-API-SEND-INVALID',
            'SENT'
        );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/send"
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'INVALID_TRANSITION'
            );
    }

    public function test_owner_can_cancel_quotation_with_reason(): void
    {
        $workspace = $this->workspace(
            'quotation-api-cancel@example.test',
            'Quotation API Cancel'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-API-CANCEL',
            'Pelanggan API Cancel'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-API-CANCEL',
            'DRAFT'
        );

        $this->actingAsWorkspace($workspace);

        $response = $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/cancel",
            [
                'reason' =>
                    'Pelanggan membatalkan permintaan.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.status',
                'CANCELLED'
            );

        $this->assertDatabaseHas(
            'quotation_status_history',
            [
                'quotation_id' => $quotationId,
                'to_state' => 'CANCELLED',
                'reason' =>
                    'Pelanggan membatalkan permintaan.',
            ]
        );
    }

    public function test_cancel_requires_reason(): void
    {
        $workspace = $this->workspace(
            'quotation-api-cancel-reason@example.test',
            'Quotation API Cancel Reason'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-API-CANCEL-REASON',
            'Pelanggan API Cancel Reason'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-API-CANCEL-REASON',
            'DRAFT'
        );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/cancel",
            []
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_missing_issue_capability_blocks_send_and_cancel(): void
    {
        $workspace = $this->workspace(
            'quotation-api-issue-denied@example.test',
            'Quotation API Issue Denied'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-API-ISSUE-DENIED',
            'Pelanggan API Issue Denied'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-API-ISSUE-DENIED',
            'DRAFT'
        );

        $this->actingAsWorkspace($workspace);

        $this->denyCapability(
            $workspace,
            'quotation.issue'
        );

        $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/send"
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/cancel",
            [
                'reason' => 'Tidak jadi.',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_foreign_tenant_send_is_not_found(): void
    {
        $first = $this->workspace(
            'quotation-api-send-first@example.test',
            'Quotation API Send First'
        );

        $second = $this->workspace(
            'quotation-api-send-second@example.test',
            'Quotation API Send Second'
        );

        $foreignCustomerId = $this->insertCustomer(
            $second['tenant_id'],
            'CUST-API-SEND-FOREIGN',
            'Pelanggan API Send Foreign'
        );

        $quotationId = $this->insertQuotation(
            $second,
            $foreignCustomerId,
            'Q-API-SEND-FOREIGN',
            'DRAFT'
        );

        $this->actingAsWorkspace($first);

        $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/send"
        )->assertNotFound();
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
                'data.0.customer.name',
                'Alpha Reklame'
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


    public function test_owner_can_download_quotation_pdf(): void
    {
        $workspace = $this->workspace(
            'quotation-pdf-download@example.test',
            'Quotation PDF Download'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-PDF-DOWNLOAD',
            'Pelanggan PDF'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-PDF-DOWNLOAD',
            'DRAFT'
        );

        $response = $this->get(
            "/api/v1/quotations/{$quotationId}/pdf"
        );

        $response
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'application/pdf'
            )
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            )
            ->assertHeader(
                'Cache-Control'
            );

        $this->assertStringStartsWith(
            '%PDF-',
            $response->getContent()
        );

        $contentDisposition =
            $response->headers->get(
                'Content-Disposition'
            );

        $this->assertNotNull(
            $contentDisposition
        );

        $this->assertStringContainsString(
            'attachment;',
            $contentDisposition
        );

        $this->assertMatchesRegularExpression(
            '/attachment; filename="Penawaran-PEN-\d{4}-\d{6}\.pdf"/',
            $contentDisposition
        );
    }

    public function test_foreign_tenant_quotation_pdf_returns_not_found(): void
    {
        $first = $this->workspace(
            'quotation-pdf-first@example.test',
            'Quotation PDF First'
        );

        $second = $this->workspace(
            'quotation-pdf-second@example.test',
            'Quotation PDF Second'
        );

        $foreignCustomerId =
            $this->insertCustomer(
                $second['tenant_id'],
                'CUST-PDF-FOREIGN',
                'Pelanggan PDF Asing'
            );

        $foreignQuotationId =
            $this->insertQuotation(
                $second,
                $foreignCustomerId,
                'Q-PDF-FOREIGN',
                'DRAFT'
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->getJson(
            "/api/v1/quotations/{$foreignQuotationId}/pdf"
        )
            ->assertNotFound()
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );
    }

    public function test_missing_quotation_view_capability_blocks_pdf(): void
    {
        $workspace = $this->workspace(
            'quotation-pdf-view-denied@example.test',
            'Quotation PDF View Denied'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-PDF-DENIED',
            'Pelanggan PDF Denied'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-PDF-DENIED',
            'DRAFT'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'quotation.view'
        );

        $this->getJson(
            "/api/v1/quotations/{$quotationId}/pdf"
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
    }


    public function test_owner_can_create_invoice_from_approved_quotation(): void
    {
        $workspace = $this->workspace(
            'quotation-create-invoice@example.test',
            'Quotation Create Invoice'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-CREATE-INVOICE',
            'Pelanggan Create Invoice'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-CREATE-INVOICE',
            'APPROVED'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $response = $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/create-invoice",
            [
                'due_at' =>
                    now()
                        ->addDays(14)
                        ->toISOString(),
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.source_quotation_id',
                $quotationId
            )
            ->assertJsonPath(
                'data.status',
                'DRAFT'
            );

        $this->assertMatchesRegularExpression(
            '/^INV-\d{6}-0001$/',
            $response->json(
                'data.invoice_number'
            )
        );

        $this->assertDatabaseHas(
            'invoices',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'source_quotation_id' =>
                    $quotationId,
                'invoice_number' =>
                    $response->json(
                        'data.invoice_number'
                    ),
                'status' =>
                    'DRAFT',
            ]
        );
    }

    public function test_create_invoice_retry_returns_existing_invoice(): void
    {
        $workspace = $this->workspace(
            'quotation-invoice-retry@example.test',
            'Quotation Invoice Retry'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-INVOICE-RETRY',
            'Pelanggan Invoice Retry'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-INVOICE-RETRY',
            'APPROVED'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $first = $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/create-invoice",
            []
        );

        $first->assertCreated();

        $invoiceId =
            $first->json('data.id');

        $second = $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/create-invoice",
            []
        );

        $second
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $invoiceId
            )
            ->assertJsonPath(
                'data.invoice_number',
                $first->json(
                    'data.invoice_number'
                )
            );

        $this->assertMatchesRegularExpression(
            '/^INV-\d{6}-0001$/',
            $first->json(
                'data.invoice_number'
            )
        );

        $this->assertDatabaseCount(
            'invoices',
            1
        );
    }

    public function test_non_approved_quotation_cannot_create_invoice(): void
    {
        $workspace = $this->workspace(
            'quotation-invoice-not-approved@example.test',
            'Quotation Invoice Not Approved'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-INVOICE-NOT-APPROVED',
            'Pelanggan Belum Approved'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-INVOICE-NOT-APPROVED',
            'DRAFT'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/create-invoice",
            []
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'error.code',
                'QUOTATION_NOT_APPROVED'
            );

        $this->assertDatabaseMissing(
            'invoices',
            [
                'source_quotation_id' =>
                    $quotationId,
            ]
        );
    }

    public function test_foreign_tenant_cannot_create_invoice_from_quotation(): void
    {
        $first = $this->workspace(
            'quotation-invoice-local@example.test',
            'Quotation Invoice Local'
        );

        $second = $this->workspace(
            'quotation-invoice-foreign@example.test',
            'Quotation Invoice Foreign'
        );

        $foreignCustomerId =
            $this->insertCustomer(
                $second['tenant_id'],
                'CUST-INVOICE-FOREIGN',
                'Pelanggan Asing'
            );

        $foreignQuotationId =
            $this->insertQuotation(
                $second,
                $foreignCustomerId,
                'Q-INVOICE-FOREIGN',
                'APPROVED'
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->postJson(
            "/api/v1/quotations/{$foreignQuotationId}/actions/create-invoice",
            []
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );
    }

    public function test_missing_invoice_create_capability_blocks_conversion(): void
    {
        $workspace = $this->workspace(
            'quotation-invoice-denied@example.test',
            'Quotation Invoice Denied'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-INVOICE-DENIED',
            'Pelanggan Invoice Denied'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-INVOICE-DENIED',
            'APPROVED'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'invoice.create'
        );

        $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/create-invoice",
            []
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_create_invoice_validates_payload(): void
    {
        $workspace = $this->workspace(
            'quotation-invoice-validation@example.test',
            'Quotation Invoice Validation'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-INVOICE-VALIDATION',
            'Pelanggan Invoice Validation'
        );

        $quotationId = $this->insertQuotation(
            $workspace,
            $customerId,
            'Q-INVOICE-VALIDATION',
            'APPROVED'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/create-invoice",
            [
                'invoice_number' =>
                    'CLIENT-MUST-NOT-SET',
                'due_at' =>
                    'not-a-date',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_quotation_number_is_generated_by_backend_and_increments_per_tenant_period(): void
    {
        $workspace = $this->workspace(
            'quotation-number@example.test',
            'Quotation Numbering'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-NUM',
                'Pelanggan Numbering'
            );

        $first = $this->postJson(
            '/api/v1/quotations',
            [
                'customer_id' =>
                    $customerId,

                'items' => [
                    [
                        'name' =>
                            'Item Pertama',

                        'quantity' =>
                            1,

                        'pricing_method' =>
                            'STANDARD',

                        'unit_price' =>
                            100000,
                    ],
                ],
            ]
        );

        $second = $this->postJson(
            '/api/v1/quotations',
            [
                'customer_id' =>
                    $customerId,

                'items' => [
                    [
                        'name' =>
                            'Item Kedua',

                        'quantity' =>
                            1,

                        'pricing_method' =>
                            'STANDARD',

                        'unit_price' =>
                            200000,
                    ],
                ],
            ]
        );

        $first->assertCreated();
        $second->assertCreated();

        $period =
            now()->format('ym');

        $first->assertJsonPath(
            'data.quotation_number',
            'PEN-' . $period . '-000001'
        );

        $second->assertJsonPath(
            'data.quotation_number',
            'PEN-' . $period . '-000002'
        );
    }

    public function test_client_cannot_supply_quotation_number(): void
    {
        $workspace = $this->workspace(
            'quotation-number-owned@example.test',
            'Quotation Number Backend Owned'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-NUM-OWNED',
                'Pelanggan Backend Owned'
            );

        $this->postJson(
            '/api/v1/quotations',
            [
                'quotation_number' =>
                    'CUSTOM-001',

                'customer_id' =>
                    $customerId,

                'items' => [
                    [
                        'name' =>
                            'Item',

                        'quantity' =>
                            1,

                        'pricing_method' =>
                            'STANDARD',

                        'unit_price' =>
                            100000,
                    ],
                ],
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
                            'quotation_number',
                        ],
                    ],
                ],
            ]);
    }

    public function test_owner_can_record_manual_approval(): void
    {
        $workspace = $this->workspace(
            'quotation-manual-approve@example.test',
            'Quotation Manual Approve'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-MAN-APP',
                'Pelanggan Manual Approve'
            );

        $quotationId =
            $this->insertQuotation(
                $workspace,
                $customerId,
                'Q-MAN-APPROVE',
                'SENT'
            );

        $response = $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/manual-decision",
            [
                'decision' =>
                    'APPROVE',

                'method' =>
                    'SIGNATURE',

                'note' =>
                    'Ditandatangani pelanggan.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'APPROVED'
            );

        $this->assertDatabaseHas(
            'quotation_actions',
            [
                'quotation_id' =>
                    $quotationId,

                'action' =>
                    'APPROVE',

                'actor_type' =>
                    'USER',

                'public_link_id' =>
                    null,
            ]
        );

        $this->assertDatabaseHas(
            'quotation_status_history',
            [
                'quotation_id' =>
                    $quotationId,

                'from_state' =>
                    'SENT',

                'to_state' =>
                    'APPROVED',

                'source' =>
                    'USER',
            ]
        );
    }

    public function test_manual_reject_requires_reason(): void
    {
        $workspace = $this->workspace(
            'quotation-manual-reject@example.test',
            'Quotation Manual Reject'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-MAN-REJ',
                'Pelanggan Manual Reject'
            );

        $quotationId =
            $this->insertQuotation(
                $workspace,
                $customerId,
                'Q-MAN-REJECT',
                'SENT'
            );

        $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/manual-decision",
            [
                'decision' =>
                    'REJECT',

                'method' =>
                    'WHATSAPP',
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
                            'reason',
                        ],
                    ],
                ],
            ]);
    }

    public function test_rejected_quotation_can_create_new_revision_and_returns_to_draft(): void
    {
        $workspace = $this->workspace(
            'quotation-rejected-revision@example.test',
            'Quotation Rejected Revision'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-REV-REJ',
                'Pelanggan Revisi'
            );

        $quotationId =
            $this->insertQuotation(
                $workspace,
                $customerId,
                'Q-REV-REJECTED',
                'REJECTED'
            );

        $response = $this->postJson(
            "/api/v1/quotations/{$quotationId}/versions",
            [
                'currency' =>
                    'IDR',

                'notes' =>
                    'Revisi setelah permintaan pelanggan.',

                'items' => [
                    [
                        'name' =>
                            'Item Revisi',

                        'quantity' =>
                            1,

                        'pricing_method' =>
                            'STANDARD',

                        'unit_price' =>
                            150000,
                    ],
                ],
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                'DRAFT'
            )
            ->assertJsonPath(
                'data.current_version.revision_no',
                2
            );

        $this->assertDatabaseHas(
            'quotation_status_history',
            [
                'quotation_id' =>
                    $quotationId,

                'from_state' =>
                    'REJECTED',

                'to_state' =>
                    'DRAFT',

                'source' =>
                    'USER',
            ]
        );
    }


    public function test_owner_can_issue_and_rotate_public_link(): void
    {
        $workspace = $this->workspace(
            'quotation-public-link@example.test',
            'Quotation Public Link'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-PUB-LINK',
                'Pelanggan Public Link'
            );

        $quotationId =
            $this->insertQuotation(
                $workspace,
                $customerId,
                'Q-PUBLIC-LINK',
                'SENT'
            );

        $first = $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/issue-public-link"
        );

        $first
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.quotation_id',
                $quotationId
            );

        $firstUrl =
            $first->json(
                'data.public_url'
            );

        $this->assertIsString(
            $firstUrl
        );

        $this->assertNotSame(
            '',
            $firstUrl
        );

        $second = $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/issue-public-link"
        );

        $second
            ->assertCreated();

        $secondUrl =
            $second->json(
                'data.public_url'
            );

        $this->assertNotSame(
            $firstUrl,
            $secondUrl
        );

        $this->assertDatabaseCount(
            'quotation_public_links',
            2
        );

        $this->assertSame(
            1,
            \App\Models\QuotationPublicLink::query()
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'quotation_id',
                    $quotationId
                )
                ->whereNull(
                    'revoked_at'
                )
                ->count()
        );

        $this->assertSame(
            1,
            \App\Models\QuotationPublicLink::query()
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'quotation_id',
                    $quotationId
                )
                ->whereNotNull(
                    'revoked_at'
                )
                ->count()
        );
    }

    public function test_draft_quotation_cannot_issue_public_link(): void
    {
        $workspace = $this->workspace(
            'quotation-public-link-draft@example.test',
            'Quotation Public Link Draft'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-PUB-DRAFT',
                'Pelanggan Public Draft'
            );

        $quotationId =
            $this->insertQuotation(
                $workspace,
                $customerId,
                'Q-PUBLIC-DRAFT',
                'DRAFT'
            );

        $this->postJson(
            "/api/v1/quotations/{$quotationId}/actions/issue-public-link"
        )
            ->assertConflict();
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
