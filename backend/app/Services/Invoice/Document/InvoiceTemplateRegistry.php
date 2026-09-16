<?php

namespace App\Services\Invoice\Document;

use InvalidArgumentException;

class InvoiceTemplateRegistry
{
    public const DEFAULT_TEMPLATE_KEY =
        'classic_blue';

    public const DEFAULT_PALETTE_KEY =
        'blue';

    public function __construct(
        private readonly ?InvoiceTemplateLoader $loader = null
    ) {
    }

    public function all(): array
    {
        return array_values(
            $this->templates()
        );
    }

    public function starter(): array
    {
        return array_values(
            array_filter(
                $this->templates(),
                fn (array $template): bool =>
                    $template['tier']
                    === 'STARTER'
            )
        );
    }

    public function default(): array
    {
        return $this->get(
            self::DEFAULT_TEMPLATE_KEY
        );
    }

    public function get(
        string $templateKey
    ): array {
        $template =
            $this->templates()[
                $templateKey
            ] ?? null;

        if ($template === null) {
            throw new InvalidArgumentException(
                "Unknown invoice template: {$templateKey}"
            );
        }

        return $template;
    }

    public function has(
        string $templateKey
    ): bool {
        return isset(
            $this->templates()[
                $templateKey
            ]
        );
    }

    public function palette(
        string $paletteKey
    ): array {
        $palette =
            $this->palettes()[
                $paletteKey
            ] ?? null;

        if ($palette === null) {
            throw new InvalidArgumentException(
                "Unknown invoice palette: {$paletteKey}"
            );
        }

        return $palette;
    }

    public function supportsPalette(
        string $templateKey,
        string $paletteKey
    ): bool {
        $template =
            $this->get(
                $templateKey
            );

        return in_array(
            $paletteKey,
            $template['palettes'],
            true
        );
    }

    public function resolve(
        ?string $templateKey = null,
        ?string $paletteKey = null
    ): array {
        $template =
            $this->get(
                $templateKey
                ?: self::DEFAULT_TEMPLATE_KEY
            );

        $resolvedPaletteKey =
            $paletteKey
            ?: $template[
                'default_palette'
            ];

        if (
            ! $this->supportsPalette(
                $template['key'],
                $resolvedPaletteKey
            )
        ) {
            throw new InvalidArgumentException(
                'Palette is not supported by invoice template.'
            );
        }

        return [
            'template' =>
                $template,

            'palette' =>
                $this->palette(
                    $resolvedPaletteKey
                ),
        ];
    }

    private function templates(): array
    {
        return $this->templateLoader()
            ->templates();
    }

    private function palettes(): array
    {
        return $this->templateLoader()
            ->palettes();
    }

    private function templateLoader(): InvoiceTemplateLoader
    {
        return $this->loader
            ?? new InvoiceTemplateLoader();
    }
}
