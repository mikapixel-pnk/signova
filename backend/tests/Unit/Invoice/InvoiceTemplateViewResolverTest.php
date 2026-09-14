<?php

namespace Tests\Unit\Invoice;

use App\Services\Invoice\Document\InvoiceTemplateRegistry;
use App\Services\Invoice\Document\InvoiceTemplateViewResolver;
use PHPUnit\Framework\TestCase;

class InvoiceTemplateViewResolverTest extends TestCase
{
    public function test_classic_template_resolves_classic_view(): void
    {
        $resolver =
            new InvoiceTemplateViewResolver(
                new InvoiceTemplateRegistry()
            );

        $resolved =
            $resolver->resolve(
                'classic_blue'
            );

        $this->assertSame(
            'pdf.invoices.classic',
            $resolved['view']
        );

        $this->assertSame(
            'blue',
            $resolved[
                'palette'
            ]['key']
        );
    }

    public function test_modern_template_resolves_modern_view(): void
    {
        $resolver =
            new InvoiceTemplateViewResolver(
                new InvoiceTemplateRegistry()
            );

        $resolved =
            $resolver->resolve(
                'modern_emerald'
            );

        $this->assertSame(
            'pdf.invoices.modern',
            $resolved['view']
        );

        $this->assertSame(
            'emerald',
            $resolved[
                'palette'
            ]['key']
        );
    }

    public function test_minimal_template_resolves_minimal_view(): void
    {
        $resolver =
            new InvoiceTemplateViewResolver(
                new InvoiceTemplateRegistry()
            );

        $resolved =
            $resolver->resolve(
                'minimal_slate'
            );

        $this->assertSame(
            'pdf.invoices.minimal',
            $resolved['view']
        );

        $this->assertSame(
            'slate',
            $resolved[
                'palette'
            ]['key']
        );
    }
    public function test_legacy_snapshot_uses_classic_blue(): void
    {
        $resolver =
            new InvoiceTemplateViewResolver(
                new InvoiceTemplateRegistry()
            );

        $resolved =
            $resolver->resolveSnapshot(
                null,
                null,
                null
            );

        $this->assertSame(
            'classic_blue',
            $resolved['template']['key']
        );

        $this->assertSame(
            'blue',
            $resolved['palette']['key']
        );

        $this->assertSame(
            'pdf.invoices.classic',
            $resolved['view']
        );
    }

    public function test_snapshot_resolves_saved_template(): void
    {
        $resolver =
            new InvoiceTemplateViewResolver(
                new InvoiceTemplateRegistry()
            );

        $resolved =
            $resolver->resolveSnapshot(
                'modern_emerald',
                'emerald',
                1
            );

        $this->assertSame(
            'modern_emerald',
            $resolved['template']['key']
        );

        $this->assertSame(
            'pdf.invoices.modern',
            $resolved['view']
        );
    }

    public function test_snapshot_rejects_unsupported_version(): void
    {
        $this->expectException(
            \InvalidArgumentException::class
        );

        $resolver =
            new InvoiceTemplateViewResolver(
                new InvoiceTemplateRegistry()
            );

        $resolver->resolveSnapshot(
            'minimal_slate',
            'slate',
            999
        );
    }

}
