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

            $this->validate(
                $definition,
                $file
            );

            if (
                ($definition['enabled'] ?? true)
                !== true
            ) {
                continue;
            }

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

                'author' =>
                    $definition['author'],

                'tier' =>
                    $definition['tier'],

                'sort_order' =>
                    $definition['sort_order'],

                'layout' =>
                    $definition['layout'],

                'view' =>
                    $definition['view'],

                'view_path' =>
                    dirname($file)
                    . '/'
                    . $definition['view'],

                'package_path' =>
                    dirname($file),

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
                $orderComparison =
                    $left['sort_order']
                    <=> $right['sort_order'];

                if ($orderComparison !== 0) {
                    return $orderComparison;
                }

                return strcmp(
                    $left['key'],
                    $right['key']
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
                'author',
                'tier',
                'sort_order',
                'layout',
                'view',
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
            || ! is_string($definition['author'])
            || $definition['author'] === ''
            || ! is_string($definition['tier'])
            || $definition['tier'] === ''
            || ! is_int($definition['sort_order'])
            || $definition['sort_order'] < 0
            || ! is_string($definition['layout'])
            || $definition['layout'] === ''
            || ! is_string($definition['view'])
            || $definition['view'] === ''
            || basename($definition['view'])
                !== $definition['view']
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

        $folderName =
            basename(
                dirname($file)
            );

        $expectedKey =
            str_replace(
                '-',
                '_',
                $folderName
            );

        $expectedName =
            ucwords(
                str_replace(
                    '-',
                    ' ',
                    $folderName
                )
            );

        if (
            $definition['key']
            !== $expectedKey
            || $definition['name']
                !== $expectedName
        ) {
            throw new InvalidArgumentException(
                "Invoice template identity does not match package folder: {$file}"
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
