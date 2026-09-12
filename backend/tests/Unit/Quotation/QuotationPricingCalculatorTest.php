<?php

namespace Tests\Unit\Quotation;

use App\Services\Quotation\QuotationPricingCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuotationPricingCalculatorTest extends TestCase
{
    private QuotationPricingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator =
            new QuotationPricingCalculator();
    }

    #[DataProvider('pricingMethodProvider')]
    public function test_pricing_methods_calculate_expected_gross(
        array $item,
        string $expectedGross
    ): void {
        $result =
            $this->calculator
                ->calculateLine($item);

        $this->assertSame(
            $expectedGross,
            $result['gross_amount']
        );

        $this->assertSame(
            $expectedGross,
            $result['amount']
        );
    }

    public static function pricingMethodProvider(): array
    {
        return [
            'standard' => [
                [
                    'pricing_method' => 'STANDARD',
                    'quantity' => '2',
                    'unit_price' => '15000',
                ],
                '30000.00',
            ],

            'area' => [
                [
                    'pricing_method' => 'AREA',
                    'quantity' => '2',
                    'unit_price' => '25000',
                    'pricing_config' => [
                        'width' => '3.5',
                        'height' => '1.2',
                    ],
                ],
                '210000.00',
            ],

            'length' => [
                [
                    'pricing_method' => 'LENGTH',
                    'quantity' => '3',
                    'unit_price' => '10000',
                    'pricing_config' => [
                        'length' => '2.5',
                    ],
                ],
                '75000.00',
            ],

            'volume' => [
                [
                    'pricing_method' => 'VOLUME',
                    'quantity' => '2',
                    'unit_price' => '100000',
                    'pricing_config' => [
                        'width' => '1.5',
                        'height' => '2',
                        'depth' => '0.5',
                    ],
                ],
                '300000.00',
            ],

            'time' => [
                [
                    'pricing_method' => 'TIME',
                    'quantity' => '2',
                    'unit_price' => '50000',
                    'pricing_config' => [
                        'duration' => '1.5',
                    ],
                ],
                '150000.00',
            ],

            'package' => [
                [
                    'pricing_method' => 'PACKAGE',
                    'quantity' => '3',
                    'unit_price' => '100000',
                ],
                '300000.00',
            ],

            'manual' => [
                [
                    'pricing_method' => 'MANUAL',
                    'quantity' => '2',
                    'unit_price' => '55000',
                ],
                '110000.00',
            ],
        ];
    }

    public function test_discount_and_tax_are_calculated_by_backend(): void
    {
        $result =
            $this->calculator
                ->calculateLine([
                    'pricing_method' =>
                        'STANDARD',
                    'quantity' => '1',
                    'unit_price' =>
                        '100000',
                    'discount_amount' =>
                        '10000',
                    'tax_rate' => '11',
                ]);

        $this->assertSame(
            '100000.00',
            $result['gross_amount']
        );

        $this->assertSame(
            '10000.00',
            $result['discount_amount']
        );

        $this->assertSame(
            '9900.00',
            $result['tax_amount']
        );

        $this->assertSame(
            '99900.00',
            $result['amount']
        );

        $this->assertSame(
            '11.0000',
            $result['pricing_config']['tax_rate']
        );
    }

    public function test_version_totals_are_sum_of_calculated_lines(): void
    {
        $result =
            $this->calculator
                ->calculate([
                    [
                        'pricing_method' =>
                            'STANDARD',
                        'quantity' => '2',
                        'unit_price' => '10000',
                    ],
                    [
                        'pricing_method' =>
                            'MANUAL',
                        'quantity' => '1',
                        'unit_price' => '50000',
                        'discount_amount' =>
                            '5000',
                        'tax_rate' => '10',
                    ],
                ]);

        $this->assertSame(
            '70000.00',
            $result['subtotal']
        );

        $this->assertSame(
            '5000.00',
            $result['discount_total']
        );

        $this->assertSame(
            '4500.00',
            $result['tax_total']
        );

        $this->assertSame(
            '69500.00',
            $result['total']
        );
    }

    public function test_area_requires_width_and_height(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->calculator
            ->calculateLine([
                'pricing_method' => 'AREA',
                'quantity' => 1,
                'unit_price' => 25000,
                'pricing_config' => [
                    'width' => 2,
                ],
            ]);
    }

    public function test_discount_cannot_exceed_gross(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->calculator
            ->calculateLine([
                'pricing_method' =>
                    'STANDARD',
                'quantity' => 1,
                'unit_price' => 10000,
                'discount_amount' => 11000,
            ]);
    }

    public function test_tax_rate_cannot_exceed_one_hundred(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->calculator
            ->calculateLine([
                'pricing_method' =>
                    'STANDARD',
                'quantity' => 1,
                'unit_price' => 10000,
                'tax_rate' => 101,
            ]);
    }

    public function test_quantity_must_be_positive(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->calculator
            ->calculateLine([
                'pricing_method' =>
                    'STANDARD',
                'quantity' => 0,
                'unit_price' => 10000,
            ]);
    }

    public function test_unknown_pricing_method_is_rejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->calculator
            ->calculateLine([
                'pricing_method' =>
                    'RANDOM',
                'quantity' => 1,
                'unit_price' => 10000,
            ]);
    }
}
