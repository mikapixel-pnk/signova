<?php

namespace App\Services\Settings;

use App\Services\Invoice\Document\InvoicePdfRenderer;
use App\Services\Invoice\Document\InvoicePreviewViewModelFactory;
use App\Services\Invoice\Document\InvoiceTemplateViewResolver;

class InvoiceTemplatePreviewService
{
    public function __construct(
        private readonly InvoiceTemplateViewResolver $resolver,
        private readonly InvoicePreviewViewModelFactory $viewModelFactory,
        private readonly InvoicePdfRenderer $renderer
    ) {
    }

    public function render(
        string $templateKey,
        ?string $paletteKey = null
    ): string {
        $resolved =
            $this->resolver->resolve(
                $templateKey,
                $paletteKey
            );

        $viewModel =
            $this->viewModelFactory->make(
                $resolved
            );

        return $this->renderer->renderHtml(
            $resolved['view'],
            $viewModel,
            $resolved['view_path']
                ?? null
        );
    }
}
