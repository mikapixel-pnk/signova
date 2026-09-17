<?php

namespace App\Services\Invoice;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class InvoiceAdjustmentCalculator
{
    public function calculate(
        array $pricing,
        array $adjustments
    ): array {
        $subtotal =
            BigDecimal::of(
                $pricing['subtotal']
                ?? 0
            )->toScale(
                2,
                RoundingMode::HalfUp
            );

        $itemDiscount =
            BigDecimal::of(
                $pricing[
                    'discount_total'
                ] ?? 0
            )->toScale(
                2,
                RoundingMode::HalfUp
            );

        $netSubtotal =
            $subtotal
                ->minus(
                    $itemDiscount
                )
                ->toScale(
                    2,
                    RoundingMode::HalfUp
                );

        $discountType =
            $this->discountType(
                $adjustments[
                    'global_discount_type'
                ] ?? null
            );

        $discountValue = null;

        $globalDiscount =
            BigDecimal::zero()
                ->toScale(2);

        if ($discountType !== null) {
            $discountValue =
                $this->nonNegative(
                    $adjustments[
                        'global_discount_value'
                    ] ?? 0,
                    'global_discount_value'
                );

            if (
                $discountType ===
                'PERCENT'
            ) {
                if (
                    $discountValue
                        ->compareTo(
                            BigDecimal::of(
                                '100'
                            )
                        ) > 0
                ) {
                    $this->invalid(
                        'global_discount_value',
                        'Persentase diskon tidak boleh melebihi 100 persen.'
                    );
                }

                $globalDiscount =
                    $netSubtotal
                        ->multipliedBy(
                            $discountValue
                        )
                        ->dividedBy(
                            '100',
                            8,
                            RoundingMode::HalfUp
                        )
                        ->toScale(
                            2,
                            RoundingMode::HalfUp
                        );
            } else {
                $globalDiscount =
                    $discountValue
                        ->toScale(
                            2,
                            RoundingMode::HalfUp
                        );
            }

            if (
                $globalDiscount
                    ->compareTo(
                        $netSubtotal
                    ) > 0
            ) {
                $this->invalid(
                    'global_discount_value',
                    'Diskon global tidak boleh melebihi subtotal setelah diskon item.'
                );
            }
        }

        $taxableBase =
            $netSubtotal
                ->minus(
                    $globalDiscount
                )
                ->toScale(
                    2,
                    RoundingMode::HalfUp
                );

        $taxEnabled =
            (bool) (
                $adjustments[
                    'tax_enabled'
                ] ?? false
            );

        $taxRate = null;

        $taxTotal =
            BigDecimal::zero()
                ->toScale(2);

        if ($taxEnabled) {
            if (
                ! array_key_exists(
                    'tax_rate',
                    $adjustments
                )
                || $adjustments[
                    'tax_rate'
                ] === null
                || $adjustments[
                    'tax_rate'
                ] === ''
            ) {
                $this->invalid(
                    'tax_rate',
                    'Tarif pajak wajib diisi jika pajak digunakan.'
                );
            }

            $taxRate =
                $this->nonNegative(
                    $adjustments[
                        'tax_rate'
                    ],
                    'tax_rate'
                );

            if (
                $taxRate
                    ->compareTo(
                        BigDecimal::of(
                            '100'
                        )
                    ) > 0
            ) {
                $this->invalid(
                    'tax_rate',
                    'Tarif pajak tidak boleh melebihi 100 persen.'
                );
            }

            $taxTotal =
                $taxableBase
                    ->multipliedBy(
                        $taxRate
                    )
                    ->dividedBy(
                        '100',
                        8,
                        RoundingMode::HalfUp
                    )
                    ->toScale(
                        2,
                        RoundingMode::HalfUp
                    );
        }

        $discountTotal =
            $itemDiscount
                ->plus(
                    $globalDiscount
                )
                ->toScale(
                    2,
                    RoundingMode::HalfUp
                );

        $total =
            $taxableBase
                ->plus(
                    $taxTotal
                )
                ->toScale(
                    2,
                    RoundingMode::HalfUp
                );

        return [
            'subtotal' =>
                $this->money(
                    $subtotal
                ),

            'item_discount_total' =>
                $this->money(
                    $itemDiscount
                ),

            'global_discount_type' =>
                $discountType,

            'global_discount_value' =>
                $discountValue !== null
                    ? $this->rate(
                        $discountValue
                    )
                    : null,

            'global_discount_amount' =>
                $this->money(
                    $globalDiscount
                ),

            'tax_enabled' =>
                $taxEnabled,

            'tax_rate' =>
                $taxRate !== null
                    ? $this->rate(
                        $taxRate
                    )
                    : null,

            'discount_total' =>
                $this->money(
                    $discountTotal
                ),

            'tax_total' =>
                $this->money(
                    $taxTotal
                ),

            'total' =>
                $this->money(
                    $total
                ),
        ];
    }

    private function discountType(
        mixed $value
    ): ?string {
        if (
            $value === null
            || trim(
                (string) $value
            ) === ''
        ) {
            return null;
        }

        $type =
            strtoupper(
                trim(
                    (string) $value
                )
            );

        if (
            ! in_array(
                $type,
                [
                    'PERCENT',
                    'NOMINAL',
                ],
                true
            )
        ) {
            $this->invalid(
                'global_discount_type',
                'Jenis diskon global tidak valid.'
            );
        }

        return $type;
    }

    private function nonNegative(
        mixed $value,
        string $field
    ): BigDecimal {
        try {
            $number =
                BigDecimal::of(
                    (string) $value
                );
        } catch (\Throwable) {
            $this->invalid(
                $field,
                'Nilai harus berupa angka.'
            );
        }

        if (
            $number->isNegative()
        ) {
            $this->invalid(
                $field,
                'Nilai tidak boleh negatif.'
            );
        }

        return $number;
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

    private function rate(
        BigDecimal $value
    ): string {
        return $value
            ->toScale(
                4,
                RoundingMode::HalfUp
            )
            ->__toString();
    }

    private function invalid(
        string $field,
        string $message
    ): never {
        throw ValidationException::withMessages([
            $field => [
                $message,
            ],
        ]);
    }
}
