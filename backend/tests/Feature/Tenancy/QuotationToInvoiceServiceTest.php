<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\Invoice;
use App\Services\Invoice\QuotationToInvoiceService;
use App\Services\Quotation\QuotationService;
use App\Tenancy\TenantContext;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
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
            'INV-CONVERT-001',
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
                $quotation->id,
                'INV-NOT-APPROVED'
            );

            $this->fail(
                'Expected quotation approval guard.'
            );
        } catch (RuntimeException $e) {
            $this->assertSame(
                'QUOTATION_NOT_APPROVED',
                $e->getMessage()
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
            $quotation->id,
            'INV-RETRY-001'
        );

        $second = $service->convert(
            $quotation->id,
            'INV-RETRY-OTHER'
        );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            'INV-RETRY-001',
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
            $quotation->id,
            'INV-FOREIGN'
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
        ];
    }

    private function setTenantContext(
        array $workspace
    ): void {
        app(TenantContext::class)->set(
            $workspace['tenant_id'],
            $workspace['user_id']
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
