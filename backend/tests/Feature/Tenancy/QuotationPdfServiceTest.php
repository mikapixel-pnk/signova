<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\TenantDocumentSetting;
use App\Services\Quotation\QuotationPdfService;
use App\Services\Quotation\QuotationService;
use App\Tenancy\TenantContext;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_pdf_renders_with_tenant_name_fallback(): void
    {
        $fixture = $this->quotationFixture(
            'pdf-fallback@example.test',
            'Reklame Fallback',
            'Q-PDF-FALLBACK'
        );

        $this->setTenantContext(
            $fixture
        );

        $pdf = app(
            QuotationPdfService::class
        )->render(
            $fixture['quotation_id']
        );

        $this->assertStringStartsWith(
            '%PDF-',
            $pdf
        );

        $this->assertGreaterThan(
            1000,
            strlen($pdf)
        );
    }

    public function test_pdf_renders_with_custom_document_branding(): void
    {
        $fixture = $this->quotationFixture(
            'pdf-branding@example.test',
            'Workspace Branding',
            'Q-PDF-BRANDING'
        );

        TenantDocumentSetting::query()->create([
            'tenant_id' =>
                $fixture['tenant_id'],
            'business_name' =>
                'PT Signage Hebat',
            'address' =>
                'Jl. Reklame No. 88',
            'phone' =>
                '08123456789',
            'email' =>
                'halo@signage.test',
            'quotation_footer' =>
                'Terima kasih atas kepercayaan Anda.',
        ]);

        $this->setTenantContext(
            $fixture
        );

        $pdf = app(
            QuotationPdfService::class
        )->render(
            $fixture['quotation_id']
        );

        $this->assertStringStartsWith(
            '%PDF-',
            $pdf
        );
    }

    public function test_pdf_filename_is_safe(): void
    {
        $service = app(
            QuotationPdfService::class
        );

        $this->assertSame(
            'Penawaran-QTN-2026-001.pdf',
            $service->filename(
                'QTN/2026/001'
            )
        );
    }

    public function test_pdf_cannot_render_foreign_tenant_quotation(): void
    {
        $tenantA = $this->quotationFixture(
            'pdf-tenant-a@example.test',
            'Tenant A',
            'Q-PDF-A'
        );

        $tenantB = $this->quotationFixture(
            'pdf-tenant-b@example.test',
            'Tenant B',
            'Q-PDF-B'
        );

        $this->setTenantContext(
            $tenantA
        );

        $this->expectException(
            ModelNotFoundException::class
        );

        app(
            QuotationPdfService::class
        )->render(
            $tenantB['quotation_id']
        );
    }

    private function setTenantContext(
        array $fixture
    ): void {
        app(
            TenantContext::class
        )->set(
            $fixture['tenant_id'],
            $fixture['user_id']
        );
    }

    private function quotationFixture(
        string $email,
        string $tenantName,
        string $quotationNumber
    ): array {
        $workspace = app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' =>
                'Quotation PDF Test',
            'email' =>
                $email,
            'password' =>
                'SecurePassword123!',
            'tenant_name' =>
                $tenantName,
        ]);

        app(
            TenantContext::class
        )->set(
            $workspace['tenant_id'],
            $workspace['user_id']
        );

        $customerId =
            (string) Str::ulid();

        DB::table('customers')->insert([
            'id' =>
                $customerId,
            'tenant_id' =>
                $workspace['tenant_id'],
            'type' =>
                'COMPANY',
            'code' =>
                'C-' . $quotationNumber,
            'name' =>
                'Customer ' . $quotationNumber,
            'payment_terms_days' =>
                0,
            'status' =>
                'ACTIVE',
            'created_at' =>
                now(),
            'updated_at' =>
                now(),
        ]);

        $quotation = app(
            QuotationService::class
        )->createDraft(
            [
                'quotation_number' =>
                    $quotationNumber,
                'customer_id' =>
                    $customerId,
                'valid_until' =>
                    now()
                        ->addDays(14)
                        ->format('Y-m-d'),
            ],
            [
                'currency' =>
                    'IDR',
                'terms' =>
                    'Pembayaran sesuai kesepakatan.',
                'notes' =>
                    'Harga belum termasuk pekerjaan tambahan.',
            ],
            [
                [
                    'item_type' =>
                        'SERVICE',
                    'code' =>
                        'SIGN-001',
                    'name' =>
                        'Pembuatan Signage',
                    'description' =>
                        'Signage custom sesuai ukuran.',
                    'quantity' =>
                        '2',
                    'unit_code' =>
                        'PCS',
                    'unit_name' =>
                        'Pieces',
                    'unit_symbol' =>
                        'pcs',
                    'pricing_method' =>
                        'STANDARD',
                    'unit_price' =>
                        '150000',
                    'discount_amount' =>
                        '0',
                    'tax_rate' =>
                        '0',
                    'sort_order' =>
                        1,
                ],
            ]
        );

        return [
            'tenant_id' =>
                $workspace['tenant_id'],
            'user_id' =>
                $workspace['user_id'],
            'quotation_id' =>
                $quotation->id,
        ];
    }
}
