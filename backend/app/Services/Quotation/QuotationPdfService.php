<?php

namespace App\Services\Quotation;

use App\Models\TenantDocumentSetting;
use App\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class QuotationPdfService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly QuotationService $quotationService
    ) {
    }

    public function render(
        string $quotationId
    ): string {
        return $this->document(
            $quotationId
        )['content'];
    }

    public function document(
        string $quotationId
    ): array {
        $quotation =
            $this->quotationService->findOrFail(
                $quotationId
            );

        $tenantId =
            $this->tenantContext->tenantId();

        $tenant = DB::table('tenants')
            ->where('id', $tenantId)
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

            'quotation_footer' =>
                $settings?->quotation_footer,
        ];

        $content = Pdf::loadView(
            'pdf.quotation',
            [
                'quotation' =>
                    $quotation,

                'version' =>
                    $quotation->currentVersion,

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
                    $quotation->quotation_number
                ),
        ];
    }

    public function filename(
        string $quotationNumber
    ): string {
        $safe = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '-',
            $quotationNumber
        );

        $safe = trim(
            (string) $safe,
            '-'
        );

        if ($safe === '') {
            $safe = 'penawaran';
        }

        return 'Penawaran-'
            . $safe
            . '.pdf';
    }
}
