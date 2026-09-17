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

        $template =
            $resolved['template'];

        $viewPath =
            $template['view_path']
            ?? null;

        return [
            'template' =>
                $template,

            'palette' =>
                $resolved['palette'],

            'view' =>
                is_string($viewPath)
                && is_file($viewPath)
                    ? null
                    : 'pdf.invoices.'
                        . $template['layout'],

            'view_path' =>
                is_string($viewPath)
                && is_file($viewPath)
                    ? $viewPath
                    : null,
        ];
    }
}
