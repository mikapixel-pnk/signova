<?php

namespace App\Services\Invoice\Document;

use InvalidArgumentException;

class InvoiceTemplateRegistry
{
    public const DEFAULT_TEMPLATE_KEY =
        'classic_blue';

    public const DEFAULT_PALETTE_KEY =
        'blue';

    private const TEMPLATES = [
        'classic_blue' => [
            'key' =>
                'classic_blue',

            'name' =>
                'Classic Blue',

            'tier' =>
                'STARTER',

            'layout' =>
                'classic',

            'version' =>
                1,

            'default_palette' =>
                'blue',

            'palettes' => [
                'blue',
            ],
        ],

        'modern_emerald' => [
            'key' =>
                'modern_emerald',

            'name' =>
                'Modern Emerald',

            'tier' =>
                'STARTER',

            'layout' =>
                'modern',

            'version' =>
                1,

            'default_palette' =>
                'emerald',

            'palettes' => [
                'emerald',
            ],
        ],

        'minimal_slate' => [
            'key' =>
                'minimal_slate',

            'name' =>
                'Minimal Slate',

            'tier' =>
                'STARTER',

            'layout' =>
                'minimal',

            'version' =>
                1,

            'default_palette' =>
                'slate',

            'palettes' => [
                'slate',
            ],
        ],
    ];

    private const PALETTES = [
        'blue' => [
            'key' =>
                'blue',

            'name' =>
                'Blue',

            'tokens' => [
                'primary' =>
                    '#2563EB',

                'primary_dark' =>
                    '#1E40AF',

                'accent' =>
                    '#DBEAFE',

                'text' =>
                    '#1F2937',

                'muted' =>
                    '#6B7280',

                'border' =>
                    '#D1D5DB',

                'surface' =>
                    '#F8FAFC',
            ],
        ],

        'emerald' => [
            'key' =>
                'emerald',

            'name' =>
                'Emerald',

            'tokens' => [
                'primary' =>
                    '#059669',

                'primary_dark' =>
                    '#047857',

                'accent' =>
                    '#D1FAE5',

                'text' =>
                    '#1F2937',

                'muted' =>
                    '#6B7280',

                'border' =>
                    '#D1D5DB',

                'surface' =>
                    '#F8FAFC',
            ],
        ],

        'slate' => [
            'key' =>
                'slate',

            'name' =>
                'Slate',

            'tokens' => [
                'primary' =>
                    '#475569',

                'primary_dark' =>
                    '#334155',

                'accent' =>
                    '#E2E8F0',

                'text' =>
                    '#0F172A',

                'muted' =>
                    '#64748B',

                'border' =>
                    '#CBD5E1',

                'surface' =>
                    '#F8FAFC',
            ],
        ],
    ];

    public function all(): array
    {
        return array_values(
            self::TEMPLATES
        );
    }

    public function starter(): array
    {
        return array_values(
            array_filter(
                self::TEMPLATES,
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
            self::TEMPLATES[
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
            self::TEMPLATES[
                $templateKey
            ]
        );
    }

    public function palette(
        string $paletteKey
    ): array {
        $palette =
            self::PALETTES[
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
}
