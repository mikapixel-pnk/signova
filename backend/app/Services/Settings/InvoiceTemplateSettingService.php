<?php

namespace App\Services\Settings;

use App\Models\TenantDocumentSetting;
use App\Services\Invoice\Document\InvoiceTemplateRegistry;
use App\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class InvoiceTemplateSettingService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InvoiceTemplateRegistry $registry
    ) {
    }

    public function catalog(): array
    {
        $settings =
            $this->currentSettings();

        $selected =
            $this->registry->resolve(
                $settings
                    ?->invoice_template_key,
                $settings
                    ?->invoice_palette_key
            );

        return [
            'templates' =>
                array_map(
                    function (
                        array $template
                    ) use (
                        $selected
                    ): array {
                        return [
                            'key' =>
                                $template['key'],

                            'name' =>
                                $template['name'],

                            'tier' =>
                                $template['tier'],

                            'layout' =>
                                $template['layout'],

                            'version' =>
                                $template['version'],

                            'default_palette' =>
                                $template[
                                    'default_palette'
                                ],

                            'palettes' =>
                                $template[
                                    'palettes'
                                ],

                            'is_available' =>
                                $template['tier']
                                === 'STARTER',

                            'is_selected' =>
                                $template['key']
                                ===
                                $selected[
                                    'template'
                                ]['key'],
                        ];
                    },
                    $this->registry->all()
                ),

            'selected' => [
                'template_key' =>
                    $selected[
                        'template'
                    ]['key'],

                'palette_key' =>
                    $selected[
                        'palette'
                    ]['key'],
            ],
        ];
    }

    public function update(
        string $templateKey,
        ?string $paletteKey = null
    ): TenantDocumentSetting {
        if (
            ! $this->registry->has(
                $templateKey
            )
        ) {
            throw ValidationException::withMessages([
                'invoice_template_key' => [
                    'Template invoice tidak valid.',
                ],
            ]);
        }

        $template =
            $this->registry->get(
                $templateKey
            );

        if (
            $template['tier']
            !== 'STARTER'
        ) {
            throw ValidationException::withMessages([
                'invoice_template_key' => [
                    'Template invoice belum tersedia untuk paket ini.',
                ],
            ]);
        }

        $resolvedPalette =
            $paletteKey
            ?: $template[
                'default_palette'
            ];

        if (
            ! $this->registry
                ->supportsPalette(
                    $templateKey,
                    $resolvedPalette
                )
        ) {
            throw ValidationException::withMessages([
                'invoice_palette_key' => [
                    'Palet tidak tersedia untuk template ini.',
                ],
            ]);
        }

        $settings =
            $this->mutableSettings();

        $settings
            ->invoice_template_key =
            $templateKey;

        $settings
            ->invoice_palette_key =
            $resolvedPalette;

        $settings->save();

        return $settings->fresh();
    }

    private function currentSettings(): ?TenantDocumentSetting
    {
        return TenantDocumentSetting::query()
            ->where(
                'tenant_id',
                $this->tenantContext
                    ->tenantId()
            )
            ->first();
    }

    private function mutableSettings(): TenantDocumentSetting
    {
        return TenantDocumentSetting::query()
            ->firstOrCreate([
                'tenant_id' =>
                    $this->tenantContext
                        ->tenantId(),
            ]);
    }
}
