<?php

namespace App\Services\Invoice;

use App\Services\Invoice\Document\InvoiceDocumentViewModelBuilder;
use App\Services\Invoice\Document\InvoicePdfRenderer;
use App\Services\Invoice\Document\InvoiceTemplateViewResolver;

class InvoicePdfService
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceDocumentViewModelBuilder $viewModelBuilder,
        private readonly InvoicePdfRenderer $renderer,
        private readonly InvoiceTemplateViewResolver $templateResolver
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
            $this->invoiceService
                ->findOrFail(
                    $invoiceId
                );

        $resolvedTemplate =
            $this->templateResolver
                ->resolveSnapshot(
                    $invoice->invoice_template_key,
                    $invoice->invoice_palette_key,
                    $invoice->invoice_template_version
                );

        $viewModel =
            array_merge(
                $this->viewModelBuilder
                    ->build(
                        $invoice
                    ),
                [
                    'template' =>
                        $resolvedTemplate[
                            'template'
                        ],

                    'theme' =>
                        $resolvedTemplate[
                            'palette'
                        ]['tokens'],
                ]
            );

        return [
            'content' =>
                $this->renderer->render(
                    $resolvedTemplate['view'],
                    $viewModel
                ),

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
