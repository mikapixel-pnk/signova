<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Exceptions\Invoice\QuotationToInvoiceConflictException;
use App\Models\Invoice;
use App\Services\Invoice\QuotationToInvoiceService;
use App\Services\Quotation\QuotationService;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationToInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_approved_quotation_converts_to_draft_invoice_with_snapshot(): void
    {
        $workspace = $this->workspace(
            'invoice-convert@example.test',
            'Invoice Convert'
        );

        $this->setTenantContext(
            $workspace
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-CONVERT',
            'Pelanggan Convert'
        );

        $quotation = $this->createQuotation(
            $customerId,
            'Q-CONVERT-001'
        );

        DB::table('quotations')
            ->where('id', $quotation->id)
            ->update([
                'status' => 'APPROVED',
                'updated_at' => now(),
            ]);

        $invoice = app(
            QuotationToInvoiceService::class
        )->convert(
            $quotation->id,
            now()->addDays(14)->toISOString()
        );

        $version = DB::table(
            'quotation_versions'
        )
            ->where(
                'id',
                $quotation->current_version_id
            )
            ->first();

        $this->assertMatchesRegularExpression(
            '/^INV-\d{6}-0001$/',
            $invoice->invoice_number
        );

        $this->assertSame(
            'DRAFT',
            $invoice->status
        );

        $this->assertSame(
            $quotation->id,
            $invoice->source_quotation_id
        );

        $this->assertSame(
            $quotation->current_version_id,
            $invoice->source_quotation_version_id
        );

        $this->assertSame(
            $customerId,
            $invoice->customer_id
        );

        $this->assertSame(
            (string) $version->subtotal,
            (string) $invoice->subtotal
        );

        $this->assertSame(
            (string) $version->discount_total,
            (string) $invoice->discount_total
        );

        $this->assertSame(
            (string) $version->tax_total,
            (string) $invoice->tax_total
        );

        $this->assertSame(
            (string) $version->total,
            (string) $invoice->total
        );

        $this->assertCount(
            1,
            $invoice->items
        );

        $item = $invoice->items->first();

        $this->assertSame(
            'Jasa Konversi',
            $item->name
        );

        $this->assertSame(
            '150000.00',
            (string) $item->unit_price
        );

        $this->assertSame(
            '150000.00',
            (string) $item->amount
        );

        $this->assertCount(
            1,
            $invoice->statusHistory
        );

        $history =
            $invoice->statusHistory->first();

        $this->assertNull(
            $history->from_state
        );

        $this->assertSame(
            'DRAFT',
            $history->to_state
        );

        $this->assertSame(
            'QUOTATION',
            $history->source
        );
    }

    public function test_non_approved_quotation_cannot_convert(): void
    {
        $workspace = $this->workspace(
            'invoice-not-approved@example.test',
            'Invoice Not Approved'
        );

        $this->setTenantContext(
            $workspace
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-NOT-APPROVED',
            'Pelanggan Belum Approved'
        );

        $quotation = $this->createQuotation(
            $customerId,
            'Q-NOT-APPROVED'
        );

        try {
            app(
                QuotationToInvoiceService::class
            )->convert(
                $quotation->id
            );

            $this->fail(
                'Expected quotation approval guard.'
            );
        } catch (
            QuotationToInvoiceConflictException $e
        ) {
            $this->assertSame(
                'QUOTATION_NOT_APPROVED',
                $e->errorCode()
            );
        }

        $this->assertDatabaseMissing(
            'invoices',
            [
                'source_quotation_id' =>
                    $quotation->id,
            ]
        );
    }

    public function test_conversion_retry_returns_existing_invoice(): void
    {
        $workspace = $this->workspace(
            'invoice-retry@example.test',
            'Invoice Retry'
        );

        $this->setTenantContext(
            $workspace
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-RETRY',
            'Pelanggan Retry'
        );

        $quotation = $this->createQuotation(
            $customerId,
            'Q-RETRY'
        );

        DB::table('quotations')
            ->where('id', $quotation->id)
            ->update([
                'status' => 'APPROVED',
                'updated_at' => now(),
            ]);

        $service = app(
            QuotationToInvoiceService::class
        );

        $first = $service->convert(
            $quotation->id
        );

        $second = $service->convert(
            $quotation->id
        );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            $first->invoice_number,
            $second->invoice_number
        );

        $this->assertMatchesRegularExpression(
            '/^INV-\d{6}-0001$/',
            $second->invoice_number
        );

        $this->assertDatabaseCount(
            'invoices',
            1
        );

        $this->assertDatabaseCount(
            'invoice_items',
            1
        );

        $this->assertDatabaseCount(
            'invoice_status_history',
            1
        );
    }

    public function test_foreign_tenant_cannot_convert_quotation(): void
    {
        $first = $this->workspace(
            'invoice-local@example.test',
            'Invoice Local'
        );

        $second = $this->workspace(
            'invoice-foreign@example.test',
            'Invoice Foreign'
        );

        $this->setTenantContext(
            $second
        );

        $customerId = $this->insertCustomer(
            $second['tenant_id'],
            'CUST-FOREIGN-CONVERT',
            'Pelanggan Foreign'
        );

        $quotation = $this->createQuotation(
            $customerId,
            'Q-FOREIGN-CONVERT'
        );

        DB::table('quotations')
            ->where('id', $quotation->id)
            ->update([
                'status' => 'APPROVED',
                'updated_at' => now(),
            ]);

        $this->setTenantContext(
            $first
        );

        $this->expectException(
            ModelNotFoundException::class
        );

        app(
            QuotationToInvoiceService::class
        )->convert(
            $quotation->id
        );
    }

    public function test_manual_and_quotation_invoice_share_same_sequence(): void
    {
        $workspace = $this->workspace(
            'invoice-shared-sequence@example.test',
            'Invoice Shared Sequence'
        );

        $this->setTenantContext(
            $workspace
        );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-SHARED-SEQUENCE',
                'Pelanggan Shared Sequence'
            );

        $manual =
            app(
                \App\Services\Invoice\InvoiceService::class
            )->createDraft(
                $customerId,
                [
                    [
                        'name' =>
                            'Jasa Manual',

                        'quantity' =>
                            1,

                        'pricing_method' =>
                            'MANUAL',

                        'unit_price' =>
                            100000,
                    ],
                ]
            );

        $quotation =
            $this->createQuotation(
                $customerId,
                'Q-SHARED-SEQUENCE'
            );

        DB::table('quotations')
            ->where(
                'id',
                $quotation->id
            )
            ->update([
                'status' =>
                    'APPROVED',

                'updated_at' =>
                    now(),
            ]);

        $converted =
            app(
                QuotationToInvoiceService::class
            )->convert(
                $quotation->id
            );

        $this->assertMatchesRegularExpression(
            '/^INV-\d{6}-0001$/',
            $manual->invoice_number
        );

        $this->assertMatchesRegularExpression(
            '/^INV-\d{6}-0002$/',
            $converted->invoice_number
        );

        $this->assertDatabaseHas(
            'tenant_sequences',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'document_type' =>
                    'INVOICE',

                'next_number' =>
                    3,
            ]
        );
    }

    public function test_conversion_retry_does_not_consume_another_invoice_number(): void
    {
        $workspace = $this->workspace(
            'invoice-retry-sequence@example.test',
            'Invoice Retry Sequence'
        );

        $this->setTenantContext(
            $workspace
        );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-RETRY-SEQUENCE',
                'Pelanggan Retry Sequence'
            );

        $quotation =
            $this->createQuotation(
                $customerId,
                'Q-RETRY-SEQUENCE'
            );

        DB::table('quotations')
            ->where(
                'id',
                $quotation->id
            )
            ->update([
                'status' =>
                    'APPROVED',

                'updated_at' =>
                    now(),
            ]);

        $service =
            app(
                QuotationToInvoiceService::class
            );

        $first =
            $service->convert(
                $quotation->id
            );

        $second =
            $service->convert(
                $quotation->id
            );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            $first->invoice_number,
            $second->invoice_number
        );

        $this->assertMatchesRegularExpression(
            '/^INV-\d{6}-0001$/',
            $first->invoice_number
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

        $this->assertDatabaseCount(
            'invoices',
            1
        );
    }

    public function test_conversion_failure_after_number_reservation_rolls_back_sequence(): void
    {
        $workspace = $this->workspace(
            'invoice-convert-rollback@example.test',
            'Invoice Convert Rollback'
        );

        $this->setTenantContext(
            $workspace
        );

        $customerId =
            $this->insertCustomer(
                $workspace['tenant_id'],
                'CUST-CONVERT-ROLLBACK',
                'Pelanggan Convert Rollback'
            );

        $quotation =
            $this->createQuotation(
                $customerId,
                'Q-CONVERT-ROLLBACK'
            );

        DB::table('quotations')
            ->where(
                'id',
                $quotation->id
            )
            ->update([
                'status' =>
                    'APPROVED',

                'updated_at' =>
                    now(),
            ]);

        /*
         * Tenant valid, actor invalid.
         *
         * Conversion dapat mencapai reservasi nomor,
         * lalu INSERT invoice gagal pada FK actor.
         */
        app(
            TenantContext::class
        )->set(
            $workspace['tenant_id'],
            (string) Str::ulid()
        );

        try {
            app(
                QuotationToInvoiceService::class
            )->convert(
                $quotation->id
            );

            $this->fail(
                'Quotation conversion should have failed.'
            );
        } catch (
            \Illuminate\Database\QueryException $exception
        ) {
            $this->assertNotSame(
                '',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseMissing(
            'invoices',
            [
                'source_quotation_id' =>
                    $quotation->id,
            ]
        );

        $this->assertDatabaseMissing(
            'tenant_sequences',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'business_id' =>
                    $workspace['business_id'],

                'document_type' =>
                    'INVOICE',
            ]
        );

        /*
         * Restore actor valid.
         * Nomor pertama harus tetap 0001.
         */
        $this->setTenantContext(
            $workspace
        );

        $invoice =
            app(
                QuotationToInvoiceService::class
            )->convert(
                $quotation->id
            );

        $this->assertMatchesRegularExpression(
            '/^INV-\d{6}-0001$/',
            $invoice->invoice_number
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
        ];
    }

    private function setTenantContext(
        array $workspace
    ): void {
        app(TenantContext::class)->set(
            $workspace['tenant_id'],
            $workspace['user_id']
        );

        app(BusinessContext::class)->set(
            $workspace['tenant_id'],
            $workspace['business_id'],
            $workspace['user_id']
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

    private function createQuotation(
        string $customerId,
        string $number
    ) {
        return app(
            QuotationService::class
        )->createDraft(
            [
                'quotation_number' =>
                    $number,
                'customer_id' =>
                    $customerId,
                'valid_until' =>
                    now()
                        ->addDays(14)
                        ->toDateString(),
            ],
            [
                'currency' => 'IDR',
                'terms' => 'Pembayaran sesuai tagihan.',
                'notes' => 'Snapshot invoice test.',
            ],
            [
                [
                    'item_type' => 'SERVICE',
                    'code' => 'SRV-CONVERT',
                    'name' => 'Jasa Konversi',
                    'description' =>
                        'Snapshot jasa',
                    'quantity' => 1,
                    'unit_code' => 'PCS',
                    'unit_name' => 'Pcs',
                    'unit_symbol' => 'pcs',
                    'pricing_method' =>
                        'STANDARD',
                    'unit_price' => 150000,
                    'discount_amount' => 0,
                    'tax_rate' => 0,
                    'sort_order' => 0,
                ],
            ]
        );
    }
}
