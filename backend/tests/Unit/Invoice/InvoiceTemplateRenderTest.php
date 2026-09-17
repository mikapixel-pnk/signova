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

            'ocean_blue' =>
                'ocean',
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
                    $viewModel,
                    $resolved['view_path']
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
                    $viewModel,
                    $resolved['view_path']
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
    public function test_ocean_blue_renders_from_package_view(): void
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

        $resolved =
            $resolver->resolve(
                'ocean_blue'
            );

        $this->assertNull(
            $resolved['view']
        );

        $this->assertIsString(
            $resolved['view_path']
        );

        $this->assertFileExists(
            $resolved['view_path']
        );

        $this->assertSame(
            'STARTER',
            $resolved[
                'template'
            ]['tier']
        );

        $this->assertSame(
            'Novel',
            $resolved[
                'template'
            ]['author']
        );

        $backgroundPath =
            $resolved[
                'template'
            ]['package_path']
            . '/'
            . $resolved[
                'template'
            ]['background']['asset'];

        $this->assertFileExists(
            $backgroundPath
        );

        $viewModel =
            $factory->make(
                $resolved
            );

        $html =
            $renderer->renderHtml(
                $resolved['view'],
                $viewModel,
                $resolved['view_path']
            );

        $this->assertStringContainsString(
            'data-invoice-layout="ocean"',
            $html
        );

        $this->assertStringContainsString(
            'INV-202609-0001',
            $html
        );

        $this->assertStringContainsString(
            'data:image/png;base64,',
            $html
        );

        $pdf =
            $renderer->render(
                $resolved['view'],
                $viewModel,
                $resolved['view_path']
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

    public function test_premium_navy_renders_from_package_view(): void
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

        $resolved =
            $resolver->resolve(
                'premium_navy'
            );

        $this->assertNull(
            $resolved['view']
        );

        $this->assertIsString(
            $resolved['view_path']
        );

        $this->assertFileExists(
            $resolved['view_path']
        );

        $this->assertSame(
            'Novel',
            $resolved[
                'template'
            ]['author']
        );

        $backgroundPath =
            $resolved[
                'template'
            ]['package_path']
            . '/'
            . $resolved[
                'template'
            ]['background']['asset'];

        $this->assertFileExists(
            $backgroundPath
        );

        $viewModel =
            $factory->make(
                $resolved
            );

        $html =
            $renderer->renderHtml(
                $resolved['view'],
                $viewModel,
                $resolved['view_path']
            );

        $this->assertStringContainsString(
            'data-invoice-layout="premium"',
            $html
        );

        $this->assertStringContainsString(
            'INV-202609-0001',
            $html
        );

        $this->assertStringContainsString(
            'data:image/png;base64,',
            $html
        );

        $pdf =
            $renderer->render(
                $resolved['view'],
                $viewModel,
                $resolved['view_path']
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

}
