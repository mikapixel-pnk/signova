<?php

namespace App\Services\Invoice\Document;

use InvalidArgumentException;
use RuntimeException;

class InvoiceTemplateLoader
{
    private ?array $loaded = null;

    public function __construct(
        private readonly ?string $basePath = null
    ) {
    }

    public function templates(): array
    {
        return $this->load()['templates'];
    }

    public function palettes(): array
    {
        return $this->load()['palettes'];
    }

    private function load(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $basePath =
            $this->basePath
            ?? dirname(__DIR__, 4)
                . '/resources/document-templates/invoice';

        if (! is_dir($basePath)) {
            throw new RuntimeException(
                'Invoice template directory does not exist.'
            );
        }

        $files =
            glob(
                $basePath
                . '/*/*/template.php'
            );

        if ($files === false) {
            throw new RuntimeException(
                'Unable to scan invoice templates.'
            );
        }

        sort(
            $files,
            SORT_STRING
        );

        $templates = [];
        $palettes = [];

        foreach ($files as $file) {
            $definition = require $file;

            if (! is_array($definition)) {
                throw new InvalidArgumentException(
                    "Invalid invoice template definition: {$file}"
                );
            }

            if (
                ($definition['enabled'] ?? true)
                !== true
            ) {
                continue;
            }

            $this->validate(
                $definition,
                $file
            );

            $key =
                $definition['key'];

            if (isset($templates[$key])) {
                throw new InvalidArgumentException(
                    "Duplicate invoice template key: {$key}"
                );
            }

            $templatePalettes = [];

            foreach (
                $definition['palettes']
                as $paletteKey =>
                    $palette
            ) {
                if (isset($palettes[$paletteKey])) {
                    throw new InvalidArgumentException(
                        "Duplicate invoice palette key: {$paletteKey}"
                    );
                }

                $palettes[$paletteKey] = [
                    'key' =>
                        $paletteKey,

                    'name' =>
                        $palette['name'],

                    'tokens' =>
                        $palette['tokens'],
                ];

                $templatePalettes[] =
                    $paletteKey;
            }

            $templates[$key] = [
                'key' =>
                    $key,

                'name' =>
                    $definition['name'],

                'tier' =>
                    $definition['tier'],

                'layout' =>
                    $definition['layout'],

                'version' =>
                    $definition['version'],

                'default_palette' =>
                    $definition['default_palette'],

                'palettes' =>
                    $templatePalettes,

                'logo' =>
                    $definition['logo']
                    ?? null,

                'background' =>
                    $definition['background']
                    ?? null,
            ];
        }

        uasort(
            $templates,
            function (
                array $left,
                array $right
            ): int {
                $order = [
                    'classic_blue' => 10,
                    'modern_emerald' => 20,
                    'minimal_slate' => 30,
                ];

                return (
                    $order[$left['key']]
                    ?? 1000
                ) <=> (
                    $order[$right['key']]
                    ?? 1000
                );
            }
        );

        return $this->loaded = [
            'templates' =>
                $templates,

            'palettes' =>
                $palettes,
        ];
    }

    private function validate(
        array $definition,
        string $file
    ): void {
        foreach (
            [
                'key',
                'name',
                'tier',
                'layout',
                'version',
                'default_palette',
                'palettes',
            ] as $required
        ) {
            if (! array_key_exists(
                $required,
                $definition
            )) {
                throw new InvalidArgumentException(
                    "Missing {$required} in invoice template: {$file}"
                );
            }
        }

        if (
            ! is_string($definition['key'])
            || $definition['key'] === ''
            || ! is_string($definition['name'])
            || $definition['name'] === ''
            || ! is_string($definition['tier'])
            || $definition['tier'] === ''
            || ! is_string($definition['layout'])
            || $definition['layout'] === ''
            || ! is_int($definition['version'])
            || $definition['version'] < 1
            || ! is_string(
                $definition['default_palette']
            )
            || ! is_array(
                $definition['palettes']
            )
        ) {
            throw new InvalidArgumentException(
                "Invalid invoice template contract: {$file}"
            );
        }

        if (
            ! array_key_exists(
                $definition['default_palette'],
                $definition['palettes']
            )
        ) {
            throw new InvalidArgumentException(
                "Default palette is missing from invoice template: {$file}"
            );
        }

        foreach (
            $definition['palettes']
            as $paletteKey =>
                $palette
        ) {
            if (
                ! is_string($paletteKey)
                || $paletteKey === ''
                || ! is_array($palette)
                || ! isset(
                    $palette['name'],
                    $palette['tokens']
                )
                || ! is_string(
                    $palette['name']
                )
                || ! is_array(
                    $palette['tokens']
                )
            ) {
                throw new InvalidArgumentException(
                    "Invalid palette contract in invoice template: {$file}"
                );
            }
        }
    }
}
