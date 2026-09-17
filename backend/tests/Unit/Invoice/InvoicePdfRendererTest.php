<?php

namespace Tests\Unit\Invoice;

use App\Services\Invoice\Document\InvoicePdfRenderer;
use Tests\TestCase;

class InvoicePdfRendererTest extends TestCase
{
    public function test_paid_invoice_html_has_lunas_watermark(): void
    {
        $path =
            $this->temporaryTemplate();

        try {
            $renderer =
                app(
                    InvoicePdfRenderer::class
                );

            $html =
                $renderer->renderHtml(
                    null,
                    [
                        'document' => [
                            'status' =>
                                'PAID',
                        ],
                    ],
                    $path
                );

            $this->assertStringContainsString(
                'signova-paid-watermark',
                $html
            );

            $this->assertStringContainsString(
                'LUNAS',
                $html
            );
        } finally {
            @unlink(
                $path
            );
        }
    }

    public function test_non_paid_invoice_html_has_no_lunas_watermark(): void
    {
        $path =
            $this->temporaryTemplate();

        try {
            $renderer =
                app(
                    InvoicePdfRenderer::class
                );

            $html =
                $renderer->renderHtml(
                    null,
                    [
                        'document' => [
                            'status' =>
                                'ISSUED',
                        ],
                    ],
                    $path
                );

            $this->assertStringNotContainsString(
                'signova-paid-watermark',
                $html
            );

            $this->assertStringNotContainsString(
                'LUNAS',
                $html
            );
        } finally {
            @unlink(
                $path
            );
        }
    }

    private function temporaryTemplate(): string
    {
        $directory =
            storage_path(
                'framework/testing'
            );

        if (! is_dir($directory)) {
            mkdir(
                $directory,
                0775,
                true
            );
        }

        $path =
            $directory
            . '/invoice-watermark-test.blade.php';

        file_put_contents(
            $path,
            <<<'HTML'
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <main>
        Tagihan SIGNOVA
    </main>
</body>
</html>
HTML
        );

        return $path;
    }
}
