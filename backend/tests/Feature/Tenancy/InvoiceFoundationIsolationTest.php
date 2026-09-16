<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use Database\Seeders\SignovaAccessControlSeeder;
use App\Services\Quotation\QuotationService;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceFoundationIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_invoice_cannot_reference_customer_from_other_tenant(): void
    {
        $first = $this->workspace(
            'invoice-first@example.test',
            'Invoice First'
        );

        $second = $this->workspace(
            'invoice-second@example.test',
            'Invoice Second'
        );

        $foreignCustomerId = $this->insertCustomer(
            $second['tenant_id'],
            'CUST-INV-FOREIGN',
            'Pelanggan Asing'
        );

        $this->expectException(
            QueryException::class
        );

        $this->insertInvoice(
            $first,
            $foreignCustomerId,
            'INV-CROSS-CUSTOMER'
        );
    }

    public function test_invoice_item_cannot_reference_catalog_item_from_other_tenant(): void
    {
        $first = $this->workspace(
            'invoice-item-first@example.test',
            'Invoice Item First'
        );

        $second = $this->workspace(
            'invoice-item-second@example.test',
            'Invoice Item Second'
        );

        $customerId = $this->insertCustomer(
            $first['tenant_id'],
            'CUST-INV-ITEM',
            'Pelanggan Invoice Item'
        );

        $invoiceId = $this->insertInvoice(
            $first,
            $customerId,
            'INV-ITEM-CROSS'
        );

        $foreignCatalogId =
            $this->insertCatalogItem(
                $second['tenant_id'],
                'CAT-INV-FOREIGN',
                'Item Asing'
            );

        $this->expectException(
            QueryException::class
        );

        DB::table('invoice_items')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' =>
                $first['tenant_id'],
            'invoice_id' =>
                $invoiceId,
            'catalog_item_id' =>
                $foreignCatalogId,
            'item_type' => 'SERVICE',
            'code' => 'CAT-INV-FOREIGN',
            'name' => 'Item Asing',
            'description' => null,
            'quantity' => '1.0000',
            'unit_code' => 'PCS',
            'unit_name' => 'Pcs',
            'unit_symbol' => 'pcs',
            'unit_price' => '100000.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'amount' => '100000.00',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_same_quotation_version_cannot_create_two_invoices(): void
    {
        $workspace = $this->workspace(
            'invoice-idempotency@example.test',
            'Invoice Idempotency'
        );

        $this->setTenantContext(
            $workspace
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-INV-IDEM',
            'Pelanggan Idempotency'
        );

        $quotation = app(
            QuotationService::class
        )->createDraft(
            [
                'quotation_number' =>
                    'Q-INV-IDEM',
                'customer_id' =>
                    $customerId,
                'valid_until' =>
                    now()
                        ->addDays(14)
                        ->toDateString(),
            ],
            [
                'currency' => 'IDR',
                'terms' => null,
                'notes' => null,
            ],
            [
                [
                    'item_type' => 'SERVICE',
                    'code' => 'SRV-IDEM',
                    'name' => 'Jasa Idempotency',
                    'description' => null,
                    'quantity' => 1,
                    'unit_code' => 'PCS',
                    'unit_name' => 'Pcs',
                    'unit_symbol' => 'pcs',
                    'pricing_method' => 'STANDARD',
                    'unit_price' => 100000,
                    'discount_amount' => 0,
                    'tax_rate' => 0,
                    'sort_order' => 0,
                ],
            ]
        );

        $versionId =
            $quotation->current_version_id;

        $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-IDEM-001',
            $quotation->id,
            $versionId
        );

        $this->expectException(
            QueryException::class
        );

        $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-IDEM-002',
            $quotation->id,
            $versionId
        );
    }

    public function test_invoice_history_actor_must_belong_to_same_tenant(): void
    {
        $first = $this->workspace(
            'invoice-history-first@example.test',
            'Invoice History First'
        );

        $second = $this->workspace(
            'invoice-history-second@example.test',
            'Invoice History Second'
        );

        $customerId = $this->insertCustomer(
            $first['tenant_id'],
            'CUST-INV-HISTORY',
            'Pelanggan History'
        );

        $invoiceId = $this->insertInvoice(
            $first,
            $customerId,
            'INV-HISTORY'
        );

        $this->expectException(
            QueryException::class
        );

        DB::table(
            'invoice_status_history'
        )->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' =>
                $first['tenant_id'],
            'invoice_id' =>
                $invoiceId,
            'from_state' => null,
            'to_state' => 'DRAFT',
            'actor_user_id' =>
                $second['user_id'],
            'reason' => null,
            'source' => 'USER',
            'context' => null,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
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

    private function insertCatalogItem(
        string $tenantId,
        string $code,
        string $name
    ): string {
        $id = (string) Str::ulid();

        DB::table('catalog_items')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'business_id' =>
                $this->businessIdForTenant(
                    $tenantId
                ),
            'category_id' => null,
            'unit_id' => null,
            'type' => 'SERVICE',
            'code' => $code,
            'name' => $name,
            'description' => null,
            'pricing_method' => 'STANDARD',
            'base_price' => '100000.00',
            'currency' => 'IDR',
            'pricing_config' => null,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function insertInvoice(
        array $workspace,
        string $customerId,
        string $invoiceNumber,
        ?string $quotationId = null,
        ?string $quotationVersionId = null
    ): string {
        $id = (string) Str::ulid();

        DB::table('invoices')->insert([
            'id' => $id,
            'tenant_id' =>
                $workspace['tenant_id'],
            'invoice_number' =>
                $invoiceNumber,
            'customer_id' =>
                $customerId,
            'project_id' => null,
            'source_quotation_id' =>
                $quotationId,
            'source_quotation_version_id' =>
                $quotationVersionId,
            'status' => 'DRAFT',
            'issued_at' => null,
            'due_at' => null,
            'currency' => 'IDR',
            'subtotal' => '100000.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'total' => '100000.00',
            'notes' => null,
            'created_by_user_id' =>
                $workspace['user_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
