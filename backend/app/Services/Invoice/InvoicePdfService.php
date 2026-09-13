<?php

namespace App\Services\Invoice;

use App\Services\Invoice\Document\InvoiceDocumentViewModelBuilder;
use App\Services\Invoice\Document\InvoicePdfRenderer;

class InvoicePdfService
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceDocumentViewModelBuilder $viewModelBuilder,
        private readonly InvoicePdfRenderer $renderer
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

        $viewModel =
            $this->viewModelBuilder
                ->build(
                    $invoice
                );

        return [
            'content' =>
                $this->renderer->render(
                    'pdf.invoices.classic',
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
