<?php

namespace App\Services\Invoice\Document;

class InvoiceTemplateViewResolver
{
    public function __construct(
        private readonly InvoiceTemplateRegistry $registry
    ) {
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
