<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_invoice_list_is_tenant_scoped_and_searchable_by_number(): void
    {
        $first = $this->workspace(
            'invoice-list-first@example.test',
            'Invoice List First'
        );

        $second = $this->workspace(
            'invoice-list-second@example.test',
            'Invoice List Second'
        );

        $firstCustomer = $this->insertCustomer(
            $first['tenant_id'],
            'CUST-INV-LIST-FIRST',
            'Pelanggan First'
        );

        $secondCustomer = $this->insertCustomer(
            $second['tenant_id'],
            'CUST-INV-LIST-SECOND',
            'Pelanggan Second'
        );

        $firstInvoice = $this->insertInvoice(
            $first,
            $firstCustomer,
            'INV-SEARCH-001',
            'DRAFT'
        );

        $this->insertInvoice(
            $first,
            $firstCustomer,
            'INV-OTHER-001',
            'ISSUED'
        );

        $this->insertInvoice(
            $second,
            $secondCustomer,
            'INV-SEARCH-FOREIGN',
            'DRAFT'
        );

        $this->actingAsWorkspace(
            $first
        );

        $response = $this->getJson(
            '/api/v1/invoices?search=INV-SEARCH-001'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $firstInvoice
            )
            ->assertJsonPath(
                'data.0.invoice_number',
                'INV-SEARCH-001'
            )
            ->assertJsonPath(
                'data.0.status_label',
                'Draf'
            );
    }

    public function test_invoice_list_is_searchable_by_customer_name(): void
    {
        $workspace = $this->workspace(
            'invoice-customer-search@example.test',
            'Invoice Customer Search'
        );

        $alphaCustomer = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-ALPHA',
            'PT Alpha Reklame'
        );

        $betaCustomer = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-BETA',
            'PT Beta Media'
        );

        $alphaInvoice = $this->insertInvoice(
            $workspace,
            $alphaCustomer,
            'INV-ALPHA-001',
            'DRAFT'
        );

        $this->insertInvoice(
            $workspace,
            $betaCustomer,
            'INV-BETA-001',
            'DRAFT'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/invoices?search=Alpha'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $alphaInvoice
            )
            ->assertJsonPath(
                'data.0.customer.name',
                'PT Alpha Reklame'
            );
    }

    public function test_invoice_list_can_filter_status_and_customer(): void
    {
        $workspace = $this->workspace(
            'invoice-filter@example.test',
            'Invoice Filter'
        );

        $firstCustomer = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-FILTER-FIRST',
            'Customer Filter First'
        );

        $secondCustomer = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-FILTER-SECOND',
            'Customer Filter Second'
        );

        $targetInvoice = $this->insertInvoice(
            $workspace,
            $firstCustomer,
            'INV-FILTER-TARGET',
            'ISSUED'
        );

        $this->insertInvoice(
            $workspace,
            $firstCustomer,
            'INV-FILTER-DRAFT',
            'DRAFT'
        );

        $this->insertInvoice(
            $workspace,
            $secondCustomer,
            'INV-FILTER-OTHER',
            'ISSUED'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $url =
            '/api/v1/invoices'
            . '?status=issued'
            . '&customer_id='
            . $firstCustomer;

        $this->getJson($url)
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $targetInvoice
            )
            ->assertJsonPath(
                'data.0.status',
                'ISSUED'
            );
    }

    public function test_invoice_list_returns_pagination_meta(): void
    {
        $workspace = $this->workspace(
            'invoice-pagination@example.test',
            'Invoice Pagination'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-PAGINATION',
            'Customer Pagination'
        );

        foreach (
            [
                'INV-PAGE-001',
                'INV-PAGE-002',
                'INV-PAGE-003',
            ] as $number
        ) {
            $this->insertInvoice(
                $workspace,
                $customerId,
                $number,
                'DRAFT'
            );
        }

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/invoices?per_page=2'
        )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data'
            )
            ->assertJsonPath(
                'meta.current_page',
                1
            )
            ->assertJsonPath(
                'meta.per_page',
                2
            )
            ->assertJsonPath(
                'meta.total',
                3
            )
            ->assertJsonPath(
                'meta.last_page',
                2
            );
    }

    public function test_invoice_detail_contains_customer_items_and_history(): void
    {
        $workspace = $this->workspace(
            'invoice-detail@example.test',
            'Invoice Detail'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-INV-DETAIL',
            'Pelanggan Invoice Detail'
        );

        $invoiceId = $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-DETAIL-001',
            'DRAFT'
        );

        $this->insertInvoiceItem(
            $workspace['tenant_id'],
            $invoiceId,
            'Jasa Pembuatan Neon Box',
            '250000.00'
        );

        $this->insertHistory(
            $workspace,
            $invoiceId,
            null,
            'DRAFT'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            "/api/v1/invoices/{$invoiceId}"
        )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.id',
                $invoiceId
            )
            ->assertJsonPath(
                'data.customer.name',
                'Pelanggan Invoice Detail'
            )
            ->assertJsonPath(
                'data.items.0.name',
                'Jasa Pembuatan Neon Box'
            )
            ->assertJsonPath(
                'data.status_history.0.to_state',
                'DRAFT'
            );
    }

    public function test_foreign_tenant_invoice_detail_is_not_found(): void
    {
        $first = $this->workspace(
            'invoice-detail-local@example.test',
            'Invoice Detail Local'
        );

        $second = $this->workspace(
            'invoice-detail-foreign@example.test',
            'Invoice Detail Foreign'
        );

        $foreignCustomerId =
            $this->insertCustomer(
                $second['tenant_id'],
                'CUST-INV-FOREIGN',
                'Foreign Customer'
            );

        $foreignInvoiceId =
            $this->insertInvoice(
                $second,
                $foreignCustomerId,
                'INV-FOREIGN-DETAIL',
                'DRAFT'
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->getJson(
            "/api/v1/invoices/{$foreignInvoiceId}"
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );
    }

    public function test_missing_invoice_view_capability_blocks_list_and_detail(): void
    {
        $workspace = $this->workspace(
            'invoice-view-denied@example.test',
            'Invoice View Denied'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-INV-DENIED',
            'Denied Customer'
        );

        $invoiceId = $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-DENIED-001',
            'DRAFT'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'invoice.view'
        );

        $this->getJson(
            '/api/v1/invoices'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->getJson(
            "/api/v1/invoices/{$invoiceId}"
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_invoice_list_rejects_invalid_filters(): void
    {
        $first = $this->workspace(
            'invoice-filter-validation@example.test',
            'Invoice Filter Validation'
        );

        $second = $this->workspace(
            'invoice-filter-foreign@example.test',
            'Invoice Filter Foreign'
        );

        $foreignCustomerId =
            $this->insertCustomer(
                $second['tenant_id'],
                'CUST-FILTER-FOREIGN',
                'Foreign Filter Customer'
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->getJson(
            '/api/v1/invoices'
            . '?status=UNKNOWN'
            . '&customer_id='
            . $foreignCustomerId
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
                            'status',
                            'customer_id',
                        ],
                    ],
                ],
            ]);
    }


    public function test_owner_can_issue_draft_invoice(): void
    {
        $workspace = $this->workspace(
            'invoice-issue@example.test',
            'Invoice Issue'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-ISSUE',
            'Pelanggan Issue'
        );

        $invoiceId = $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-ISSUE-001',
            'DRAFT'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $response = $this->postJson(
            "/api/v1/invoices/{$invoiceId}/actions/issue"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.status',
                'ISSUED'
            )
            ->assertJsonPath(
                'data.status_label',
                'Diterbitkan'
            );

        $this->assertDatabaseHas(
            'invoices',
            [
                'id' =>
                    $invoiceId,

                'tenant_id' =>
                    $workspace['tenant_id'],

                'status' =>
                    'ISSUED',
            ]
        );

        $issuedAt = DB::table('invoices')
            ->where(
                'id',
                $invoiceId
            )
            ->value('issued_at');

        $this->assertNotNull(
            $issuedAt
        );

        $this->assertDatabaseHas(
            'invoice_status_history',
            [
                'invoice_id' =>
                    $invoiceId,

                'from_state' =>
                    'DRAFT',

                'to_state' =>
                    'ISSUED',

                'actor_user_id' =>
                    $workspace['user_id'],

                'source' =>
                    'USER',
            ]
        );
    }

    public function test_issuing_invoice_snapshots_default_template_without_creating_setting_row(): void
    {
        $workspace =
            $this->workspace(
                'invoice-template-default@example.test',
                'Invoice Template Default'
            );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-TEMPLATE-DEFAULT',
                'Pelanggan Template Default'
            );

        $invoiceId =
            $this->insertInvoice(
                $workspace,
                $customerId,
                'INV-TEMPLATE-DEFAULT',
                'DRAFT'
            );

        $this->assertDatabaseHas(
            'invoices',
            [
                'id' =>
                    $invoiceId,

                'invoice_template_key' =>
                    null,

                'invoice_palette_key' =>
                    null,

                'invoice_template_version' =>
                    null,
            ]
        );

        $this->assertDatabaseMissing(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
            ]
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            "/api/v1/invoices/{$invoiceId}/actions/issue"
        )->assertOk();

        $this->assertDatabaseHas(
            'invoices',
            [
                'id' =>
                    $invoiceId,

                'tenant_id' =>
                    $workspace['tenant_id'],

                'status' =>
                    'ISSUED',

                'invoice_template_key' =>
                    'classic_blue',

                'invoice_palette_key' =>
                    'blue',

                'invoice_template_version' =>
                    1,
            ]
        );

        $this->assertDatabaseMissing(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
            ]
        );
    }

    public function test_issuing_invoice_snapshots_selected_tenant_template(): void
    {
        $workspace =
            $this->workspace(
                'invoice-template-modern@example.test',
                'Invoice Template Modern'
            );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-TEMPLATE-MODERN',
                'Pelanggan Template Modern'
            );

        $invoiceId =
            $this->insertInvoice(
                $workspace,
                $customerId,
                'INV-TEMPLATE-MODERN',
                'DRAFT'
            );

        DB::table(
            'tenant_document_settings'
        )->insert([
            'id' =>
                (string) Str::ulid(),

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'invoice_template_key' =>
                'modern_emerald',

            'invoice_palette_key' =>
                'emerald',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            "/api/v1/invoices/{$invoiceId}/actions/issue"
        )->assertOk();

        $this->assertDatabaseHas(
            'invoices',
            [
                'id' =>
                    $invoiceId,

                'invoice_template_key' =>
                    'modern_emerald',

                'invoice_palette_key' =>
                    'emerald',

                'invoice_template_version' =>
                    1,
            ]
        );
    }

    public function test_changing_tenant_template_after_issue_does_not_change_invoice_snapshot(): void
    {
        $workspace =
            $this->workspace(
                'invoice-template-history@example.test',
                'Invoice Template History'
            );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-TEMPLATE-HISTORY',
                'Pelanggan Template History'
            );

        $invoiceId =
            $this->insertInvoice(
                $workspace,
                $customerId,
                'INV-TEMPLATE-HISTORY',
                'DRAFT'
            );

        DB::table(
            'tenant_document_settings'
        )->insert([
            'id' =>
                (string) Str::ulid(),

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'invoice_template_key' =>
                'modern_emerald',

            'invoice_palette_key' =>
                'emerald',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            "/api/v1/invoices/{$invoiceId}/actions/issue"
        )->assertOk();

        DB::table(
            'tenant_document_settings'
        )
            ->where(
                'tenant_id',
                $workspace['tenant_id']
            )
            ->where(
                'business_id',
                $workspace['business_id']
            )
            ->update([
                'invoice_template_key' =>
                    'minimal_slate',

                'invoice_palette_key' =>
                    'slate',

                'updated_at' =>
                    now(),
            ]);

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'invoice_template_key' =>
                    'minimal_slate',

                'invoice_palette_key' =>
                    'slate',
            ]
        );

        $this->assertDatabaseHas(
            'invoices',
            [
                'id' =>
                    $invoiceId,

                'invoice_template_key' =>
                    'modern_emerald',

                'invoice_palette_key' =>
                    'emerald',

                'invoice_template_version' =>
                    1,
            ]
        );
    }


    public function test_non_draft_invoice_cannot_be_issued(): void
    {
        $workspace = $this->workspace(
            'invoice-issue-invalid@example.test',
            'Invoice Issue Invalid'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-ISSUE-INVALID',
            'Pelanggan Issue Invalid'
        );

        $invoiceId = $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-ISSUE-INVALID',
            'ISSUED'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            "/api/v1/invoices/{$invoiceId}/actions/issue"
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'error.code',
                'INVALID_TRANSITION'
            );
    }

    public function test_foreign_tenant_invoice_cannot_be_issued(): void
    {
        $first = $this->workspace(
            'invoice-issue-local@example.test',
            'Invoice Issue Local'
        );

        $second = $this->workspace(
            'invoice-issue-foreign@example.test',
            'Invoice Issue Foreign'
        );

        $foreignCustomerId =
            $this->insertCustomer(
                $second['tenant_id'],
                'CUST-ISSUE-FOREIGN',
                'Foreign Issue Customer'
            );

        $foreignInvoiceId =
            $this->insertInvoice(
                $second,
                $foreignCustomerId,
                'INV-ISSUE-FOREIGN',
                'DRAFT'
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->postJson(
            "/api/v1/invoices/{$foreignInvoiceId}/actions/issue"
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );
    }

    public function test_missing_invoice_issue_capability_blocks_issue(): void
    {
        $workspace = $this->workspace(
            'invoice-issue-denied@example.test',
            'Invoice Issue Denied'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-ISSUE-DENIED',
            'Pelanggan Issue Denied'
        );

        $invoiceId = $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-ISSUE-DENIED',
            'DRAFT'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'invoice.issue'
        );

        $this->postJson(
            "/api/v1/invoices/{$invoiceId}/actions/issue"
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseHas(
            'invoices',
            [
                'id' =>
                    $invoiceId,

                'status' =>
                    'DRAFT',
            ]
        );
    }


    public function test_owner_can_void_draft_invoice_with_reason(): void
    {
        $workspace = $this->workspace(
            'invoice-void-draft@example.test',
            'Invoice Void Draft'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-VOID-DRAFT',
            'Pelanggan Void Draft'
        );

        $invoiceId = $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-VOID-DRAFT',
            'DRAFT'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            "/api/v1/invoices/{$invoiceId}/actions/void",
            [
                'reason' =>
                    'Tagihan dibuat keliru.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'VOID'
            )
            ->assertJsonPath(
                'data.status_label',
                'Dibatalkan'
            );

        $this->assertDatabaseHas(
            'invoice_status_history',
            [
                'invoice_id' =>
                    $invoiceId,
                'from_state' =>
                    'DRAFT',
                'to_state' =>
                    'VOID',
                'reason' =>
                    'Tagihan dibuat keliru.',
            ]
        );
    }

    public function test_owner_can_void_issued_invoice(): void
    {
        $workspace = $this->workspace(
            'invoice-void-issued@example.test',
            'Invoice Void Issued'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-VOID-ISSUED',
            'Pelanggan Void Issued'
        );

        $invoiceId = $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-VOID-ISSUED',
            'ISSUED'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            "/api/v1/invoices/{$invoiceId}/actions/void",
            [
                'reason' =>
                    'Transaksi dibatalkan pelanggan.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'VOID'
            );
    }

    public function test_paid_invoice_cannot_be_voided(): void
    {
        $workspace = $this->workspace(
            'invoice-void-paid@example.test',
            'Invoice Void Paid'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-VOID-PAID',
            'Pelanggan Void Paid'
        );

        $invoiceId = $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-VOID-PAID',
            'PAID'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            "/api/v1/invoices/{$invoiceId}/actions/void",
            [
                'reason' =>
                    'Tidak boleh langsung void paid.',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'INVALID_TRANSITION'
            );
    }

    public function test_void_requires_reason(): void
    {
        $workspace = $this->workspace(
            'invoice-void-reason@example.test',
            'Invoice Void Reason'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-VOID-REASON',
            'Pelanggan Void Reason'
        );

        $invoiceId = $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-VOID-REASON',
            'DRAFT'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            "/api/v1/invoices/{$invoiceId}/actions/void",
            [
                'reason' => '   ',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->assertDatabaseHas(
            'invoices',
            [
                'id' => $invoiceId,
                'status' => 'DRAFT',
            ]
        );
    }

    public function test_foreign_tenant_invoice_cannot_be_voided(): void
    {
        $first = $this->workspace(
            'invoice-void-local@example.test',
            'Invoice Void Local'
        );

        $second = $this->workspace(
            'invoice-void-foreign@example.test',
            'Invoice Void Foreign'
        );

        $customerId = $this->insertCustomer(
            $second['tenant_id'],
            'CUST-VOID-FOREIGN',
            'Pelanggan Void Foreign'
        );

        $invoiceId = $this->insertInvoice(
            $second,
            $customerId,
            'INV-VOID-FOREIGN',
            'DRAFT'
        );

        $this->actingAsWorkspace(
            $first
        );

        $this->postJson(
            "/api/v1/invoices/{$invoiceId}/actions/void",
            [
                'reason' =>
                    'Percobaan tenant lain.',
            ]
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );
    }

    public function test_missing_invoice_void_capability_blocks_void(): void
    {
        $workspace = $this->workspace(
            'invoice-void-denied@example.test',
            'Invoice Void Denied'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-VOID-DENIED',
            'Pelanggan Void Denied'
        );

        $invoiceId = $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-VOID-DENIED',
            'DRAFT'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'invoice.void'
        );

        $this->postJson(
            "/api/v1/invoices/{$invoiceId}/actions/void",
            [
                'reason' =>
                    'Harus ditolak capability.',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseHas(
            'invoices',
            [
                'id' =>
                    $invoiceId,
                'status' =>
                    'DRAFT',
            ]
        );
    }


    public function test_owner_can_download_branded_invoice_pdf(): void
    {
        $workspace = $this->workspace(
            'invoice-pdf@example.test',
            'Invoice PDF'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-INV-PDF',
            'Pelanggan Invoice PDF'
        );

        $invoiceId = $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-PDF-001',
            'ISSUED'
        );

        $this->insertInvoiceItem(
            $workspace['tenant_id'],
            $invoiceId,
            'Jasa Pembuatan Signage',
            '350000.00'
        );

        DB::table(
            'tenant_document_settings'
        )->insert([
            'id' =>
                (string) Str::ulid(),

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'business_name' =>
                'Signova Test Business',

            'address' =>
                'Jl. Pengujian No. 1',

            'phone' =>
                '081234567890',

            'email' =>
                'billing@example.test',

            'tax_id' =>
                'TEST-TAX-ID',

            'quotation_footer' =>
                null,

            'invoice_footnote' =>
                'Pembayaran dianggap sah setelah dana diterima.',

            'signature_name' =>
                'Budi Santoso',

            'signature_title' =>
                'Finance Manager',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this->actingAsWorkspace(
            $workspace
        );

        $response = $this->get(
            "/api/v1/invoices/{$invoiceId}/pdf"
        );

        $response
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'application/pdf'
            )
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename="Tagihan-INV-PDF-001.pdf"'
            )
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            );

        $this->assertStringStartsWith(
            '%PDF',
            $response->getContent()
        );
    }

    public function test_foreign_tenant_invoice_pdf_returns_not_found(): void
    {
        $first = $this->workspace(
            'invoice-pdf-local@example.test',
            'Invoice PDF Local'
        );

        $second = $this->workspace(
            'invoice-pdf-foreign@example.test',
            'Invoice PDF Foreign'
        );

        $customerId = $this->insertCustomer(
            $second['tenant_id'],
            'CUST-PDF-FOREIGN',
            'Pelanggan PDF Foreign'
        );

        $invoiceId = $this->insertInvoice(
            $second,
            $customerId,
            'INV-PDF-FOREIGN',
            'ISSUED'
        );

        $this->actingAsWorkspace(
            $first
        );

        $this->getJson(
            "/api/v1/invoices/{$invoiceId}/pdf"
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );
    }

    public function test_missing_invoice_view_capability_blocks_pdf(): void
    {
        $workspace = $this->workspace(
            'invoice-pdf-denied@example.test',
            'Invoice PDF Denied'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-PDF-DENIED',
            'Pelanggan PDF Denied'
        );

        $invoiceId = $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-PDF-DENIED',
            'ISSUED'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'invoice.view'
        );

        $this->getJson(
            "/api/v1/invoices/{$invoiceId}/pdf"
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }


    public function test_invoice_pdf_renders_modern_template_from_snapshot(): void
    {
        $workspace =
            $this->workspace(
                'invoice-pdf-modern@example.test',
                'Invoice PDF Modern'
            );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-PDF-MODERN',
                'Pelanggan PDF Modern'
            );

        $invoiceId =
            $this->insertInvoice(
                $workspace,
                $customerId,
                'INV-PDF-MODERN',
                'ISSUED'
            );

        DB::table('invoices')
            ->where(
                'id',
                $invoiceId
            )
            ->update([
                'invoice_template_key' =>
                    'modern_emerald',

                'invoice_palette_key' =>
                    'emerald',

                'invoice_template_version' =>
                    1,
            ]);

        $this->insertInvoiceItem(
            $workspace['tenant_id'],
            $invoiceId,
            'Jasa Modern',
            '350000.00'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->get(
                "/api/v1/invoices/{$invoiceId}/pdf"
            )
                ->assertOk()
                ->assertHeader(
                    'Content-Type',
                    'application/pdf'
                );

        $this->assertStringStartsWith(
            '%PDF',
            $response->getContent()
        );
    }

    public function test_invoice_pdf_renders_minimal_template_from_snapshot(): void
    {
        $workspace =
            $this->workspace(
                'invoice-pdf-minimal@example.test',
                'Invoice PDF Minimal'
            );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-PDF-MINIMAL',
                'Pelanggan PDF Minimal'
            );

        $invoiceId =
            $this->insertInvoice(
                $workspace,
                $customerId,
                'INV-PDF-MINIMAL',
                'ISSUED'
            );

        DB::table('invoices')
            ->where(
                'id',
                $invoiceId
            )
            ->update([
                'invoice_template_key' =>
                    'minimal_slate',

                'invoice_palette_key' =>
                    'slate',

                'invoice_template_version' =>
                    1,
            ]);

        $this->insertInvoiceItem(
            $workspace['tenant_id'],
            $invoiceId,
            'Jasa Minimal',
            '275000.00'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->get(
                "/api/v1/invoices/{$invoiceId}/pdf"
            )
                ->assertOk()
                ->assertHeader(
                    'Content-Type',
                    'application/pdf'
                );

        $this->assertStringStartsWith(
            '%PDF',
            $response->getContent()
        );
    }


    public function test_invoice_pdf_supports_uploaded_signature_image(): void
    {
        Storage::fake('local');

        config()->set(
            'filesystems.private_disk',
            'local'
        );

        $workspace = $this->workspace(
            'invoice-signature@example.test',
            'Invoice Signature'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-INV-SIGN',
            'Pelanggan Signature'
        );

        $invoiceId = $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-SIGN-001',
            'ISSUED'
        );

        $this->insertInvoiceItem(
            $workspace['tenant_id'],
            $invoiceId,
            'Jasa Signage',
            '250000.00'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->post(
            '/api/v1/settings/document/signature',
            [
                'signature' =>
                    UploadedFile::fake()->image(
                        'signature.png',
                        600,
                        200
                    ),
            ]
        )->assertOk();

        $file = DB::table('files')
            ->where(
                'tenant_id',
                $workspace['tenant_id']
            )
            ->where(
                'purpose',
                'DOCUMENT_SIGNATURE'
            )
            ->first();

        $this->assertNotNull(
            $file
        );

        Storage::disk('local')
            ->assertExists(
                $file->object_key
            );

        $response = $this->get(
            "/api/v1/invoices/{$invoiceId}/pdf"
        );

        $response
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'application/pdf'
            );

        $this->assertStringStartsWith(
            '%PDF',
            $response->getContent()
        );
    }

    public function test_owner_can_create_manual_draft_invoice(): void
    {
        $workspace =
            $this->workspace(
                'invoice-create@example.test',
                'Invoice Create'
            );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-CREATE',
                'Pelanggan Create'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->postJson(
                '/api/v1/invoices',
                [
                    'customer_id' =>
                        $customerId,

                    'due_at' =>
                        '2026-10-15T12:00:00+07:00',

                    'notes' =>
                        '  Invoice manual  ',

                    'items' => [
                        [
                            'item_type' =>
                                'SERVICE',

                            'code' =>
                                'SRV-MANUAL',

                            'name' =>
                                'Pembuatan Neon Box',

                            'quantity' =>
                                2,

                            'pricing_method' =>
                                'MANUAL',

                            'unit_price' =>
                                150000,

                            'discount_amount' =>
                                10000,

                            'tax_rate' =>
                                11,
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
                'data.status',
                'DRAFT'
            )
            ->assertJsonPath(
                'data.status_label',
                'Draf'
            )
            ->assertJsonPath(
                'data.customer_id',
                $customerId
            )
            ->assertJsonPath(
                'data.currency',
                'IDR'
            )
            ->assertJsonPath(
                'data.subtotal',
                '300000.00'
            )
            ->assertJsonPath(
                'data.discount_total',
                '10000.00'
            )
            ->assertJsonPath(
                'data.tax_total',
                '31900.00'
            )
            ->assertJsonPath(
                'data.total',
                '321900.00'
            )
            ->assertJsonPath(
                'data.paid_amount',
                '0.00'
            )
            ->assertJsonPath(
                'data.outstanding_amount',
                '0.00'
            )
            ->assertJsonPath(
                'data.notes',
                'Invoice manual'
            );

        $invoiceId =
            $response->json(
                'data.id'
            );

        $invoiceNumber =
            $response->json(
                'data.invoice_number'
            );

        $this->assertMatchesRegularExpression(
            '/^INV-\d{6}-0001$/',
            $invoiceNumber
        );

        $this->assertDatabaseHas(
            'invoices',
            [
                'id' =>
                    $invoiceId,

                'tenant_id' =>
                    $workspace[
                        'tenant_id'
                    ],

                'customer_id' =>
                    $customerId,

                'invoice_number' =>
                    $invoiceNumber,

                'status' =>
                    'DRAFT',

                'subtotal' =>
                    '300000.00',

                'discount_total' =>
                    '10000.00',

                'tax_total' =>
                    '31900.00',

                'total' =>
                    '321900.00',

                'paid_amount' =>
                    '0.00',

                'outstanding_amount' =>
                    '0.00',
            ]
        );

        $this->assertDatabaseHas(
            'invoice_items',
            [
                'invoice_id' =>
                    $invoiceId,

                'tenant_id' =>
                    $workspace[
                        'tenant_id'
                    ],

                'catalog_item_id' =>
                    null,

                'name' =>
                    'Pembuatan Neon Box',

                'quantity' =>
                    '2.0000',

                'unit_price' =>
                    '150000.00',

                'discount_amount' =>
                    '10000.00',

                'tax_amount' =>
                    '31900.00',

                'amount' =>
                    '321900.00',
            ]
        );

        $history =
            DB::table(
                'invoice_status_history'
            )
                ->where(
                    'invoice_id',
                    $invoiceId
                )
                ->first();

        $this->assertNotNull(
            $history
        );

        $this->assertNull(
            $history->from_state
        );

        $this->assertSame(
            'DRAFT',
            $history->to_state
        );

        $this->assertSame(
            'USER',
            $history->source
        );

        $context =
            json_decode(
                $history->context,
                true
            );

        $this->assertSame(
            'MANUAL',
            $context[
                'creation_type'
            ]
        );
    }

    public function test_manual_invoice_number_is_generated_sequentially(): void
    {
        $workspace =
            $this->workspace(
                'invoice-sequence@example.test',
                'Invoice Sequence'
            );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-SEQUENCE',
                'Pelanggan Sequence'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $payload = [
            'customer_id' =>
                $customerId,

            'items' => [
                [
                    'name' =>
                        'Jasa',

                    'quantity' =>
                        1,

                    'pricing_method' =>
                        'MANUAL',

                    'unit_price' =>
                        100000,
                ],
            ],
        ];

        $first =
            $this->postJson(
                '/api/v1/invoices',
                $payload
            );

        $second =
            $this->postJson(
                '/api/v1/invoices',
                $payload
            );

        $first->assertCreated();
        $second->assertCreated();

        $this->assertMatchesRegularExpression(
            '/^INV-\d{6}-0001$/',
            $first->json(
                'data.invoice_number'
            )
        );

        $this->assertMatchesRegularExpression(
            '/^INV-\d{6}-0002$/',
            $second->json(
                'data.invoice_number'
            )
        );
    }

    public function test_manual_invoice_rejects_foreign_tenant_customer(): void
    {
        $local =
            $this->workspace(
                'invoice-local@example.test',
                'Invoice Local'
            );

        $foreign =
            $this->workspace(
                'invoice-foreign@example.test',
                'Invoice Foreign'
            );

        $foreignCustomerId =
            $this->insertCustomer(
                $foreign['tenant_id'],
                'CUST-FOREIGN-CREATE',
                'Foreign Customer'
            );

        $this->actingAsWorkspace(
            $local
        );

        $this->postJson(
            '/api/v1/invoices',
            [
                'customer_id' =>
                    $foreignCustomerId,

                'items' => [
                    [
                        'name' => 'Jasa',
                        'quantity' => 1,
                        'pricing_method' =>
                            'MANUAL',
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
            );

        $this->assertDatabaseCount(
            'invoices',
            0
        );

        $this->assertDatabaseCount(
            'tenant_sequences',
            0
        );
    }

    public function test_manual_invoice_rejects_backend_owned_fields(): void
    {
        $workspace =
            $this->workspace(
                'invoice-owned@example.test',
                'Invoice Backend Owned'
            );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-OWNED',
                'Pelanggan Owned'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->postJson(
                '/api/v1/invoices',
                [
                    'customer_id' =>
                        $customerId,

                    'invoice_number' =>
                        'HACK-001',

                    'status' =>
                        'PAID',

                    'total' =>
                        1,

                    'paid_amount' =>
                        1,

                    'outstanding_amount' =>
                        0,

                    'items' => [
                        [
                            'name' => 'Jasa',
                            'quantity' => 1,
                            'pricing_method' =>
                                'MANUAL',
                            'unit_price' =>
                                100000,

                            'amount' =>
                                1,

                            'tax_amount' =>
                                1,
                        ],
                    ],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $fields =
            $response->json(
                'error.details.fields'
            );

        foreach (
            [
                'invoice_number',
                'status',
                'total',
                'paid_amount',
                'outstanding_amount',
                'items.0.amount',
                'items.0.tax_amount',
            ] as $field
        ) {
            $this->assertArrayHasKey(
                $field,
                $fields
            );

            $this->assertNotEmpty(
                $fields[$field]
            );
        }

        $this->assertDatabaseCount(
            'invoices',
            0
        );

        $this->assertDatabaseCount(
            'tenant_sequences',
            0
        );
    }

    public function test_missing_invoice_create_capability_blocks_manual_invoice(): void
    {
        $workspace =
            $this->workspace(
                'invoice-create-denied@example.test',
                'Invoice Create Denied'
            );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-CREATE-DENIED',
                'Denied Customer'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'invoice.create'
        );

        $this->postJson(
            '/api/v1/invoices',
            [
                'customer_id' =>
                    $customerId,

                'items' => [
                    [
                        'name' => 'Jasa',
                        'quantity' => 1,
                        'pricing_method' =>
                            'MANUAL',
                        'unit_price' =>
                            100000,
                    ],
                ],
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseCount(
            'invoices',
            0
        );

        $this->assertDatabaseCount(
            'tenant_sequences',
            0
        );
    }


    public function test_manual_invoice_snapshots_catalog_item_and_unit(): void
    {
        $workspace =
            $this->workspace(
                'invoice-catalog@example.test',
                'Invoice Catalog Snapshot'
            );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-CATALOG-SNAPSHOT',
                'Pelanggan Catalog Snapshot'
            );

        $unit =
            DB::table('units')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'status',
                    'ACTIVE'
                )
                ->orderBy('name')
                ->first();

        $this->assertNotNull(
            $unit
        );

        $catalogId =
            (string) Str::ulid();

        DB::table(
            'catalog_items'
        )->insert([
            'id' =>
                $catalogId,

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'category_id' =>
                null,

            'unit_id' =>
                $unit->id,

            'type' =>
                'SERVICE',

            'code' =>
                'SRV-CATALOG-SNAPSHOT',

            'name' =>
                'Jasa Neon Box Catalog',

            'description' =>
                'Deskripsi snapshot awal',

            'pricing_method' =>
                'STANDARD',

            'base_price' =>
                '250000.00',

            'currency' =>
                'IDR',

            'pricing_config' =>
                null,

            'status' =>
                'ACTIVE',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->postJson(
                '/api/v1/invoices',
                [
                    'customer_id' =>
                        $customerId,

                    'items' => [
                        [
                            'catalog_item_id' =>
                                $catalogId,

                            'quantity' =>
                                2,
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
                'data.subtotal',
                '500000.00'
            )
            ->assertJsonPath(
                'data.total',
                '500000.00'
            );

        $invoiceId =
            $response->json(
                'data.id'
            );

        $this->assertDatabaseHas(
            'invoice_items',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'invoice_id' =>
                    $invoiceId,

                'catalog_item_id' =>
                    $catalogId,

                'item_type' =>
                    'SERVICE',

                'code' =>
                    'SRV-CATALOG-SNAPSHOT',

                'name' =>
                    'Jasa Neon Box Catalog',

                'description' =>
                    'Deskripsi snapshot awal',

                'quantity' =>
                    '2.0000',

                'unit_code' =>
                    $unit->code,

                'unit_name' =>
                    $unit->name,

                'unit_symbol' =>
                    $unit->symbol,

                'unit_price' =>
                    '250000.00',

                'amount' =>
                    '500000.00',
            ]
        );

        /*
         * Mutasi catalog setelah invoice dibuat
         * tidak boleh mengubah snapshot invoice.
         */
        DB::table(
            'catalog_items'
        )
            ->where(
                'id',
                $catalogId
            )
            ->update([
                'name' =>
                    'Nama Catalog Berubah',

                'description' =>
                    'Deskripsi berubah',

                'base_price' =>
                    '999999.00',

                'updated_at' =>
                    now(),
            ]);

        $this->assertDatabaseHas(
            'invoice_items',
            [
                'invoice_id' =>
                    $invoiceId,

                'catalog_item_id' =>
                    $catalogId,

                'name' =>
                    'Jasa Neon Box Catalog',

                'description' =>
                    'Deskripsi snapshot awal',

                'unit_price' =>
                    '250000.00',

                'amount' =>
                    '500000.00',
            ]
        );
    }

    public function test_manual_invoice_failure_rolls_back_sequence_and_invoice(): void
    {
        $workspace =
            $this->workspace(
                'invoice-rollback@example.test',
                'Invoice Rollback'
            );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-ROLLBACK',
                'Pelanggan Rollback'
            );

        $invalidUserId =
            (string) Str::ulid();

        app(
            TenantContext::class
        )->set(
            $workspace['tenant_id'],
            $invalidUserId
        );

        app(
            BusinessContext::class
        )->set(
            $workspace['tenant_id'],
            $workspace['business_id'],
            $invalidUserId
        );

        $payloadItems = [
            [
                'name' =>
                    'Jasa Rollback',

                'quantity' =>
                    1,

                'pricing_method' =>
                    'MANUAL',

                'unit_price' =>
                    175000,
            ],
        ];

        try {
            app(
                \App\Services\Invoice\InvoiceService::class
            )->createDraft(
                $customerId,
                $payloadItems
            );

            $this->fail(
                'Invoice creation should have failed.'
            );
        } catch (
            \Illuminate\Database\QueryException $exception
        ) {
            $this->assertNotSame(
                '',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount(
            'invoices',
            0
        );

        $this->assertDatabaseCount(
            'invoice_items',
            0
        );

        $this->assertDatabaseCount(
            'invoice_status_history',
            0
        );

        $this->assertDatabaseCount(
            'tenant_sequences',
            0
        );

        /*
         * Setelah actor diperbaiki, nomor pertama
         * harus tetap 0001.
         */
        app(
            TenantContext::class
        )->set(
            $workspace['tenant_id'],
            $workspace['user_id']
        );

        app(
            BusinessContext::class
        )->set(
            $workspace['tenant_id'],
            $workspace['business_id'],
            $workspace['user_id']
        );

        $invoice =
            app(
                \App\Services\Invoice\InvoiceService::class
            )->createDraft(
                $customerId,
                $payloadItems
            );

        $this->assertMatchesRegularExpression(
            '/^INV-\d{6}-0001$/',
            $invoice->invoice_number
        );

        $this->assertSame(
            'DRAFT',
            $invoice->status
        );

        $this->assertDatabaseHas(
            'tenant_sequences',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'document_type' =>
                    'INVOICE',

                'next_number' =>
                    2,
            ]
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

            'business_id' =>
                $workspace['business_id'],

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

    private function businessIdForTenant(
        string $tenantId
    ): string {
        $businessId = DB::table(
            'business_profiles'
        )
            ->where(
                'tenant_id',
                $tenantId
            )
            ->where(
                'is_default',
                true
            )
            ->where(
                'status',
                'ACTIVE'
            )
            ->value('id');

        if (!$businessId) {
            throw new \RuntimeException(
                'Active default business not found '
                . 'for tenant ' . $tenantId
            );
        }

        return (string) $businessId;
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
            'business_id' =>
                $this->businessIdForTenant(
                    $tenantId
                ),
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

    private function insertInvoice(
        array $workspace,
        string $customerId,
        string $number,
        string $status
    ): string {
        $id = (string) Str::ulid();

        DB::table('invoices')->insert([
            'id' => $id,
            'tenant_id' =>
                $workspace['tenant_id'],
            'invoice_number' => $number,
            'customer_id' => $customerId,
            'project_id' => null,
            'source_quotation_id' => null,
            'source_quotation_version_id' => null,
            'status' => $status,
            'issued_at' =>
                $status === 'DRAFT'
                    ? null
                    : now(),
            'due_at' => null,
            'currency' => 'IDR',
            'subtotal' => '250000.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'total' => '250000.00',
            'notes' => null,
            'created_by_user_id' =>
                $workspace['user_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function insertInvoiceItem(
        string $tenantId,
        string $invoiceId,
        string $name,
        string $amount
    ): string {
        $id = (string) Str::ulid();

        DB::table('invoice_items')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'invoice_id' => $invoiceId,
            'source_quotation_item_id' => null,
            'catalog_item_id' => null,
            'item_type' => 'SERVICE',
            'code' => 'SRV-DETAIL',
            'name' => $name,
            'description' => null,
            'quantity' => '1.0000',
            'unit_code' => 'PCS',
            'unit_name' => 'Pcs',
            'unit_symbol' => 'pcs',
            'unit_price' => $amount,
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'amount' => $amount,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function insertHistory(
        array $workspace,
        string $invoiceId,
        ?string $fromState,
        string $toState
    ): string {
        $id = (string) Str::ulid();

        DB::table(
            'invoice_status_history'
        )->insert([
            'id' => $id,
            'tenant_id' =>
                $workspace['tenant_id'],
            'invoice_id' => $invoiceId,
            'from_state' => $fromState,
            'to_state' => $toState,
            'actor_user_id' =>
                $workspace['user_id'],
            'reason' => null,
            'source' => 'USER',
            'context' => json_encode([]),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
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

        $this->assertNotNull(
            $roleId
        );

        $this->assertNotNull(
            $capabilityId
        );

        DB::table('role_capabilities')
            ->updateOrInsert(
                [
                    'role_id' =>
                        $roleId,

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
