<?php

namespace Tests\Unit\Invoice;

use App\Services\Invoice\Document\InvoiceTemplateLoader;
use PHPUnit\Framework\TestCase;

class InvoiceTemplateLoaderTest extends TestCase
{
    public function test_loader_discovers_enabled_template_packages(): void
    {
        $loader =
            new InvoiceTemplateLoader();

        $templates =
            $loader->templates();

        $this->assertSame(
            [
                'classic_blue',
                'modern_emerald',
                'minimal_slate',
            ],
            array_keys(
                $templates
            )
        );

        $this->assertArrayNotHasKey(
            'premium_navy',
            $templates
        );
    }

    public function test_loader_normalizes_palette_contract(): void
    {
        $loader =
            new InvoiceTemplateLoader();

        $templates =
            $loader->templates();

        $palettes =
            $loader->palettes();

        $this->assertSame(
            'blue',
            $templates[
                'classic_blue'
            ]['default_palette']
        );

        $this->assertSame(
            ['blue'],
            $templates[
                'classic_blue'
            ]['palettes']
        );

        $this->assertSame(
            '#2563EB',
            $palettes[
                'blue'
            ]['tokens']['primary']
        );
    }

    public function test_template_contract_exposes_logo_and_background(): void
    {
        $template =
            (new InvoiceTemplateLoader())
                ->templates()[
                    'classic_blue'
                ];

        $this->assertTrue(
            $template[
                'logo'
            ]['show']
        );

        $this->assertSame(
            'TOP_LEFT',
            $template[
                'logo'
            ]['placement']
        );

        $this->assertNull(
            $template['background']
        );
    }
}
