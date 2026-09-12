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
