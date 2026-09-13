<?php

namespace App\Services\Invoice;

use App\Models\TenantDocumentSetting;
use App\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class InvoicePdfService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InvoiceService $invoiceService
    ) {
    }

    public function render(
        string $invoiceId
    ): string {
        return $this->document(
            $invoiceId
        )['content'];
    }

    public function document(
        string $invoiceId
    ): array {
        $invoice =
            $this->invoiceService->findOrFail(
                $invoiceId
            );

        $tenantId =
            $this->tenantContext->tenantId();

        $tenant = DB::table('tenants')
            ->where(
                'id',
                $tenantId
            )
            ->firstOrFail();

        $settings =
            TenantDocumentSetting::query()
                ->where(
                    'tenant_id',
                    $tenantId
                )
                ->first();

        $branding = [
            'business_name' =>
                $settings?->business_name
                ?: $tenant->name,

            'address' =>
                $settings?->address,

            'phone' =>
                $settings?->phone,

            'email' =>
                $settings?->email,

            'tax_id' =>
                $settings?->tax_id,

            'invoice_footnote' =>
                $settings?->invoice_footnote,

            'signature_name' =>
                $settings?->signature_name,

            'signature_title' =>
                $settings?->signature_title,
        ];

        $content = Pdf::loadView(
            'pdf.invoice',
            [
                'invoice' =>
                    $invoice,

                'branding' =>
                    $branding,
            ]
        )
            ->setPaper(
                'a4',
                'portrait'
            )
            ->output();

        return [
            'content' =>
                $content,

            'filename' =>
                $this->filename(
                    $invoice->invoice_number
                ),
        ];
    }

    public function filename(
        string $invoiceNumber
    ): string {
        $safe = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '-',
            $invoiceNumber
        );

        $safe = trim(
            (string) $safe,
            '-'
        );

        if ($safe === '') {
            $safe = 'tagihan';
        }

        return 'Tagihan-'
            . $safe
            . '.pdf';
    }
}
