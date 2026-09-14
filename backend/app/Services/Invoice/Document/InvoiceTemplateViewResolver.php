<?php

namespace App\Services\Invoice\Document;

class InvoiceTemplateViewResolver
{
    public function __construct(
        private readonly InvoiceTemplateRegistry $registry
    ) {
    }

    public function resolveSnapshot(
        ?string $templateKey,
        ?string $paletteKey,
        ?int $templateVersion
    ): array {
        if ($templateKey === null) {
            return $this->resolve(
                InvoiceTemplateRegistry::DEFAULT_TEMPLATE_KEY,
                InvoiceTemplateRegistry::DEFAULT_PALETTE_KEY
            );
        }

        $resolved =
            $this->resolve(
                $templateKey,
                $paletteKey
            );

        if (
            $templateVersion !== null
            && $templateVersion
                !== $resolved['template']['version']
        ) {
            throw new \InvalidArgumentException(
                'Unsupported invoice template version.'
            );
        }

        return $resolved;
    }

    public function resolve(
        ?string $templateKey = null,
        ?string $paletteKey = null
    ): array {
        $resolved =
            $this->registry->resolve(
                $templateKey,
                $paletteKey
            );

        return [
            'template' =>
                $resolved['template'],

            'palette' =>
                $resolved['palette'],

            'view' =>
                'pdf.invoices.'
                . $resolved[
                    'template'
                ]['layout'],
        ];
    }
}
