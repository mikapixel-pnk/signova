<?php

namespace Tests\Unit\Invoice;

use App\Services\Invoice\Document\InvoiceTemplateRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class InvoiceTemplateRegistryTest extends TestCase
{
    public function test_registry_contains_three_starter_templates(): void
    {
        $templates =
            (new InvoiceTemplateRegistry())
                ->starter();

        $this->assertCount(
            3,
            $templates
        );

        $this->assertSame(
            [
                'classic_blue',
                'modern_emerald',
                'minimal_slate',
            ],
            array_column(
                $templates,
                'key'
            )
        );

        $this->assertSame(
            [
                'Classic Blue',
                'Modern Emerald',
                'Minimal Slate',
            ],
            array_column(
                $templates,
                'name'
            )
        );
    }

    public function test_starter_templates_use_distinct_layouts(): void
    {
        $templates =
            (new InvoiceTemplateRegistry())
                ->starter();

        $layouts =
            array_column(
                $templates,
                'layout'
            );

        $this->assertSame(
            [
                'classic',
                'modern',
                'minimal',
            ],
            $layouts
        );

        $this->assertCount(
            3,
            array_unique(
                $layouts
            )
        );
    }

    public function test_default_template_is_classic_blue(): void
    {
        $registry =
            new InvoiceTemplateRegistry();

        $this->assertSame(
            'classic_blue',
            $registry->default()['key']
        );

        $resolved =
            $registry->resolve();

        $this->assertSame(
            'classic_blue',
            $resolved[
                'template'
            ]['key']
        );

        $this->assertSame(
            'blue',
            $resolved[
                'palette'
            ]['key']
        );
    }

    public function test_each_starter_template_resolves_its_palette(): void
    {
        $registry =
            new InvoiceTemplateRegistry();

        $cases = [
            'classic_blue' =>
                'blue',

            'modern_emerald' =>
                'emerald',

            'minimal_slate' =>
                'slate',
        ];

        foreach (
            $cases
            as $templateKey =>
                $paletteKey
        ) {
            $resolved =
                $registry->resolve(
                    $templateKey
                );

            $this->assertSame(
                $paletteKey,
                $resolved[
                    'palette'
                ]['key']
            );

            $this->assertArrayHasKey(
                'primary',
                $resolved[
                    'palette'
                ]['tokens']
            );
        }
    }

    public function test_template_rejects_unsupported_palette(): void
    {
        $registry =
            new InvoiceTemplateRegistry();

        $this->expectException(
            InvalidArgumentException::class
        );

        $registry->resolve(
            'classic_blue',
            'emerald'
        );
    }

    public function test_unknown_template_is_rejected(): void
    {
        $registry =
            new InvoiceTemplateRegistry();

        $this->expectException(
            InvalidArgumentException::class
        );

        $registry->get(
            'unknown_template'
        );
    }
}
