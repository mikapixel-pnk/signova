<?php

namespace App\Services\Invoice;

use App\Models\InvoiceItem;

class InvoiceItemPricingPresenter
{
    public function present(
        InvoiceItem $item
    ): array {
        $method = strtoupper(
            (string) (
                $item->pricing_method
                ?? 'MANUAL'
            )
        );

        $config =
            is_array($item->pricing_config)
                ? $item->pricing_config
                : [];

        $inputQuantity =
            $this->number(
                $item->quantity
            );

        $displayQuantity =
            $this->number(
                $item->pricing_quantity
                ?? $item->quantity
            );

        $unit =
            $item->unit_symbol
            ?: $item->unit_name
            ?: $item->unit_code
            ?: null;

        $formula = match ($method) {
            'AREA' =>
                $this->formula(
                    'P × L × Qty',
                    [
                        $config['width']
                            ?? null,

                        $config['height']
                            ?? null,

                        $item->quantity,
                    ]
                ),

            'LENGTH' =>
                $this->formula(
                    'Panjang × Qty',
                    [
                        $config['length']
                            ?? null,

                        $item->quantity,
                    ]
                ),

            'VOLUME' =>
                $this->formula(
                    'P × L × T × Qty',
                    [
                        $config['width']
                            ?? null,

                        $config['height']
                            ?? null,

                        $config['depth']
                            ?? null,

                        $item->quantity,
                    ]
                ),

            'TIME' =>
                $this->formula(
                    'Durasi × Qty',
                    [
                        $config['duration']
                            ?? null,

                        $item->quantity,
                    ]
                ),

            default => null,
        };

        $resultLabel = match ($method) {
            'AREA' =>
                'Luas total',

            'LENGTH' =>
                'Panjang total',

            'VOLUME' =>
                'Volume total',

            'TIME' =>
                'Durasi total',

            default =>
                null,
        };

        return [
            'input_quantity' =>
                $inputQuantity,

            'display_quantity' =>
                $displayQuantity,

            'display_unit' =>
                $unit,

            'formula' =>
                $formula,

            'result_label' =>
                $resultLabel,

            'result_text' =>
                $resultLabel !== null
                    ? trim(
                        $resultLabel
                        . ': '
                        . $displayQuantity
                        . (
                            $unit
                                ? ' ' . $unit
                                : ''
                        )
                    )
                    : null,
        ];
    }

    private function formula(
        string $label,
        array $values
    ): ?string {
        foreach ($values as $value) {
            if (
                $value === null
                || $value === ''
            ) {
                return null;
            }
        }

        return $label
            . ': '
            . implode(
                ' × ',
                array_map(
                    fn ($value) =>
                        $this->number(
                            $value
                        ),
                    $values
                )
            );
    }

    private function number(
        mixed $value
    ): string {
        $formatted =
            number_format(
                (float) $value,
                4,
                ',',
                '.'
            );

        return rtrim(
            rtrim(
                $formatted,
                '0'
            ),
            ','
        );
    }
}
