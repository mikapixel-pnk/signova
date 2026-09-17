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
                'ocean_blue',
                'premium_navy',
            ],
            array_keys(
                $templates
            )
        );

        $this->assertArrayHasKey(
            'premium_navy',
            $templates
        );

        $this->assertSame(
            'Novel',
            $templates[
                'premium_navy'
            ]['author']
        );

        $this->assertSame(
            100,
            $templates[
                'premium_navy'
            ]['sort_order']
        );


        $this->assertSame(
            'STARTER',
            $templates[
                'ocean_blue'
            ]['tier']
        );

        $this->assertSame(
            'ocean',
            $templates[
                'ocean_blue'
            ]['default_palette']
        );

        $this->assertSame(
            40,
            $templates[
                'ocean_blue'
            ]['sort_order']
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
