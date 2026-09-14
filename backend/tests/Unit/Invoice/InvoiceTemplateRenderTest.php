<?php

namespace Tests\Unit\Invoice;

use App\Services\Invoice\Document\InvoicePdfRenderer;
use App\Services\Invoice\Document\InvoicePreviewViewModelFactory;
use App\Services\Invoice\Document\InvoiceTemplateViewResolver;
use Tests\TestCase;

class InvoiceTemplateRenderTest extends TestCase
{
    public function test_all_starter_templates_render_html_and_pdf(): void
    {
        $resolver =
            app(
                InvoiceTemplateViewResolver::class
            );

        $factory =
            app(
                InvoicePreviewViewModelFactory::class
            );

        $renderer =
            app(
                InvoicePdfRenderer::class
            );

        $cases = [
            'classic_blue' =>
                'classic',

            'modern_emerald' =>
                'modern',

            'minimal_slate' =>
                'minimal',
        ];

        foreach (
            $cases
            as $templateKey =>
                $layout
        ) {
            $resolved =
                $resolver->resolve(
                    $templateKey
                );

            $viewModel =
                $factory->make(
                    $resolved
                );

            $html =
                $renderer->renderHtml(
                    $resolved['view'],
                    $viewModel
                );

            $this->assertStringContainsString(
                'data-invoice-layout="'
                . $layout
                . '"',
                $html
            );

            $this->assertStringContainsString(
                'INV-202609-0001',
                $html
            );

            $this->assertStringContainsString(
                'PT Contoh Pelanggan',
                $html
            );

            $pdf =
                $renderer->render(
                    $resolved['view'],
                    $viewModel
                );

            $this->assertStringStartsWith(
                '%PDF-',
                $pdf
            );

            $this->assertGreaterThan(
                1000,
                strlen(
                    $pdf
                )
            );
        }
    }
}
