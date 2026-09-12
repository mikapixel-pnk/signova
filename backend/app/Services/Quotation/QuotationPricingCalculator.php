<?php

namespace App\Services\Quotation;

use Brick\Math\BigDecimal;
use App\Exceptions\Quotation\QuotationPricingValidationException;
use Brick\Math\RoundingMode;

class QuotationPricingCalculator
{
    private const PRICING_METHODS = [
        'STANDARD',
        'AREA',
        'LENGTH',
        'VOLUME',
        'TIME',
        'PACKAGE',
        'MANUAL',
    ];

    public function calculate(
        array $items
    ): array {
        if ($items === []) {
            throw new QuotationPricingValidationException(
                'Penawaran harus memiliki minimal satu item.',
                'items'
            );
        }

        $calculatedItems = [];

        $subtotal = BigDecimal::zero();
        $discountTotal = BigDecimal::zero();
        $taxTotal = BigDecimal::zero();
        $total = BigDecimal::zero();

        foreach ($items as $index => $item) {
            try {
                $calculated =
                    $this->calculateLine($item);
            } catch (
                QuotationPricingValidationException $exception
            ) {
                throw $exception->withPrefix(
                    "items.{$index}."
                );
            }

            $calculatedItems[] = $calculated;

            $subtotal = $subtotal->plus(
                $calculated['gross_amount']
            );

            $discountTotal =
                $discountTotal->plus(
                    $calculated['discount_amount']
                );

            $taxTotal = $taxTotal->plus(
                $calculated['tax_amount']
            );

            $total = $total->plus(
                $calculated['amount']
            );
        }

        return [
            'items' => $calculatedItems,

            'subtotal' => $this->money(
                $subtotal
            ),

            'discount_total' => $this->money(
                $discountTotal
            ),

            'tax_total' => $this->money(
                $taxTotal
            ),

            'total' => $this->money(
                $total
            ),
        ];
    }

    public function calculateLine(
        array $item
    ): array {
        $pricingMethod = strtoupper(
            trim(
                (string) (
                    $item['pricing_method']
                    ?? 'MANUAL'
                )
            )
        );

        if (
            ! in_array(
                $pricingMethod,
                self::PRICING_METHODS,
                true
            )
        ) {
            throw new QuotationPricingValidationException(
                'Metode harga tidak valid.',
                'pricing_method'
            );
        }

        $quantity = $this->positiveDecimal(
            $item['quantity'] ?? 1,
            'quantity'
        );

        $unitPrice =
            $this->nonNegativeDecimal(
                $item['unit_price'] ?? 0,
                'unit_price'
            );

        $config =
            is_array(
                $item['pricing_config']
                ?? null
            )
                ? $item['pricing_config']
                : [];

        $factor = $this->pricingFactor(
            $pricingMethod,
            $config
        );

        $gross = $quantity
            ->multipliedBy($factor)
            ->multipliedBy($unitPrice)
            ->toScale(
                2,
                RoundingMode::HalfUp
            );

        $discount =
            $this->nonNegativeDecimal(
                $item['discount_amount'] ?? 0,
                'discount_amount'
            )->toScale(
                2,
                RoundingMode::HalfUp
            );

        if (
            $discount->compareTo($gross) > 0
        ) {
            throw new QuotationPricingValidationException(
                'Diskon item tidak boleh melebihi nilai bruto.',
                'discount_amount'
            );
        }

        $taxRate =
            $this->nonNegativeDecimal(
                $item['tax_rate']
                ?? $config['tax_rate']
                ?? 0,
                'tax_rate'
            );

        if (
            $taxRate->compareTo(
                BigDecimal::of('100')
            ) > 0
        ) {
            throw new QuotationPricingValidationException(
                'Tarif pajak tidak boleh melebihi 100 persen.',
                'tax_rate'
            );
        }

        $taxableBase =
            $gross->minus($discount);

        $taxAmount = $taxableBase
            ->multipliedBy($taxRate)
            ->dividedBy(
                '100',
                8,
                RoundingMode::HalfUp
            )
            ->toScale(
                2,
                RoundingMode::HalfUp
            );

        $amount = $taxableBase
            ->plus($taxAmount)
            ->toScale(
                2,
                RoundingMode::HalfUp
            );

        $snapshotConfig =
            $this->normalizedConfig(
                $pricingMethod,
                $config,
                $taxRate
            );

        return array_merge(
            $item,
            [
                'pricing_method' =>
                    $pricingMethod,

                'quantity' =>
                    $quantity
                        ->toScale(
                            4,
                            RoundingMode::HalfUp
                        )
                        ->__toString(),

                'pricing_config' =>
                    $snapshotConfig,

                'unit_price' =>
                    $this->money(
                        $unitPrice
                    ),

                'gross_amount' =>
                    $this->money($gross),

                'discount_amount' =>
                    $this->money(
                        $discount
                    ),

                'tax_amount' =>
                    $this->money(
                        $taxAmount
                    ),

                'amount' =>
                    $this->money(
                        $amount
                    ),
            ]
        );
    }

    private function pricingFactor(
        string $pricingMethod,
        array $config
    ): BigDecimal {
        return match ($pricingMethod) {
            'AREA' =>
                $this->requiredMeasurement(
                    $config,
                    'width'
                )->multipliedBy(
                    $this->requiredMeasurement(
                        $config,
                        'height'
                    )
                ),

            'LENGTH' =>
                $this->requiredMeasurement(
                    $config,
                    'length'
                ),

            'VOLUME' =>
                $this->requiredMeasurement(
                    $config,
                    'width'
                )
                    ->multipliedBy(
                        $this->requiredMeasurement(
                            $config,
                            'height'
                        )
                    )
                    ->multipliedBy(
                        $this->requiredMeasurement(
                            $config,
                            'depth'
                        )
                    ),

            'TIME' =>
                $this->requiredMeasurement(
                    $config,
                    'duration'
                ),

            default => BigDecimal::one(),
        };
    }

    private function normalizedConfig(
        string $pricingMethod,
        array $config,
        BigDecimal $taxRate
    ): array {
        $result = $config;

        foreach (
            $this->measurementKeys(
                $pricingMethod
            )
            as $key
        ) {
            $result[$key] =
                $this->requiredMeasurement(
                    $config,
                    $key
                )
                    ->toScale(
                        4,
                        RoundingMode::HalfUp
                    )
                    ->__toString();
        }

        $result['tax_rate'] =
            $taxRate
                ->toScale(
                    4,
                    RoundingMode::HalfUp
                )
                ->__toString();

        return $result;
    }

    private function measurementKeys(
        string $pricingMethod
    ): array {
        return match ($pricingMethod) {
            'AREA' => [
                'width',
                'height',
            ],

            'LENGTH' => [
                'length',
            ],

            'VOLUME' => [
                'width',
                'height',
                'depth',
            ],

            'TIME' => [
                'duration',
            ],

            default => [],
        };
    }

    private function requiredMeasurement(
        array $config,
        string $key
    ): BigDecimal {
        if (
            ! array_key_exists(
                $key,
                $config
            )
        ) {
            throw new QuotationPricingValidationException(
                "Nilai {$key} wajib diisi untuk metode harga ini.",
                'pricing_config.' . $key
            );
        }

        return $this->positiveDecimal(
            $config[$key],
            $key
        );
    }

    private function positiveDecimal(
        mixed $value,
        string $field
    ): BigDecimal {
        $decimal =
            $this->decimal(
                $value,
                $field
            );

        if (
            $decimal->compareTo(
                BigDecimal::zero()
            ) <= 0
        ) {
            throw new QuotationPricingValidationException(
                "{$field} harus lebih besar dari nol.",
                $field
            );
        }

        return $decimal;
    }

    private function nonNegativeDecimal(
        mixed $value,
        string $field
    ): BigDecimal {
        $decimal =
            $this->decimal(
                $value,
                $field
            );

        if (
            $decimal->compareTo(
                BigDecimal::zero()
            ) < 0
        ) {
            throw new QuotationPricingValidationException(
                "{$field} tidak boleh negatif.",
                $field
            );
        }

        return $decimal;
    }

    private function decimal(
        mixed $value,
        string $field
    ): BigDecimal {
        if (
            ! is_int($value)
            && ! is_float($value)
            && ! is_string($value)
        ) {
            throw new QuotationPricingValidationException(
                "{$field} harus berupa angka.",
                $field
            );
        }

        $value = trim(
            (string) $value
        );

        if (
            $value === ''
            || ! is_numeric($value)
        ) {
            throw new QuotationPricingValidationException(
                "{$field} harus berupa angka.",
                $field
            );
        }

        return BigDecimal::of($value);
    }

    private function money(
        BigDecimal $value
    ): string {
        return $value
            ->toScale(
                2,
                RoundingMode::HalfUp
            )
            ->__toString();
    }
}
