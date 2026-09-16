<?php

namespace App\Services\Quotation;

use App\Models\BusinessProfile;
use App\Models\TenantDocumentSetting;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;

class QuotationPdfService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
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

        $businessId =
            $this->businessContext->businessId();

        $business =
            BusinessProfile::query()
                ->where(
                    'tenant_id',
                    $tenantId
                )
                ->where(
                    'id',
                    $businessId
                )
                ->firstOrFail();

        $settings =
            TenantDocumentSetting::query()
                ->where(
                    'tenant_id',
                    $tenantId
                )
                ->where(
                    'business_id',
                    $businessId
                )
                ->first();

        $branding = [
            'business_name' =>
                $business->name,

            'legal_name' =>
                $business->legal_name,

            'address' =>
                $business->address,

            'city' =>
                $business->city,

            'province' =>
                $business->province,

            'postal_code' =>
                $business->postal_code,

            'phone' =>
                $business->phone,

            'whatsapp' =>
                $business->whatsapp,

            'email' =>
                $business->email,

            'website' =>
                $business->website,

            'tax_id' =>
                $business->tax_id,

            'quotation_opening_text' =>
                $settings
                    ?->quotation_opening_text,

            'quotation_closing_text' =>
                $settings
                    ?->quotation_closing_text,
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
