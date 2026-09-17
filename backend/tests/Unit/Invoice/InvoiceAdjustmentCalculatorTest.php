<?php

namespace Tests\Unit\Invoice;

use App\Services\Invoice\InvoiceAdjustmentCalculator;
use Tests\TestCase;

class InvoiceAdjustmentCalculatorTest extends TestCase
{
    public function test_without_global_adjustment(): void
    {
        $result =
            app(
                InvoiceAdjustmentCalculator::class
            )->calculate(
                [
                    'subtotal' =>
                        '100000.00',

                    'discount_total' =>
                        '10000.00',
                ],
                [
                    'tax_enabled' =>
                        false,
                ]
            );

        $this->assertSame(
            '100000.00',
            $result['subtotal']
        );

        $this->assertSame(
            '10000.00',
            $result[
                'item_discount_total'
            ]
        );

        $this->assertSame(
            '0.00',
            $result[
                'global_discount_amount'
            ]
        );

        $this->assertFalse(
            $result['tax_enabled']
        );

        $this->assertSame(
            '0.00',
            $result['tax_total']
        );

        $this->assertSame(
            '90000.00',
            $result['total']
        );
    }

    public function test_percent_global_discount(): void
    {
        $result =
            app(
                InvoiceAdjustmentCalculator::class
            )->calculate(
                [
                    'subtotal' =>
                        '100000.00',

                    'discount_total' =>
                        '10000.00',
                ],
                [
                    'global_discount_type' =>
                        'PERCENT',

                    'global_discount_value' =>
                        10,

                    'tax_enabled' =>
                        false,
                ]
            );

        $this->assertSame(
            '9000.00',
            $result[
                'global_discount_amount'
            ]
        );

        $this->assertSame(
            '19000.00',
            $result[
                'discount_total'
            ]
        );

        $this->assertSame(
            '81000.00',
            $result['total']
        );
    }

    public function test_nominal_global_discount(): void
    {
        $result =
            app(
                InvoiceAdjustmentCalculator::class
            )->calculate(
                [
                    'subtotal' =>
                        '100000.00',

                    'discount_total' =>
                        '10000.00',
                ],
                [
                    'global_discount_type' =>
                        'NOMINAL',

                    'global_discount_value' =>
                        5000,

                    'tax_enabled' =>
                        false,
                ]
            );

        $this->assertSame(
            '5000.00',
            $result[
                'global_discount_amount'
            ]
        );

        $this->assertSame(
            '85000.00',
            $result['total']
        );
    }

    public function test_tax_is_applied_after_global_discount(): void
    {
        $result =
            app(
                InvoiceAdjustmentCalculator::class
            )->calculate(
                [
                    'subtotal' =>
                        '100000.00',

                    'discount_total' =>
                        '10000.00',
                ],
                [
                    'global_discount_type' =>
                        'PERCENT',

                    'global_discount_value' =>
                        10,

                    'tax_enabled' =>
                        true,

                    'tax_rate' =>
                        11,
                ]
            );

        $this->assertSame(
            '9000.00',
            $result[
                'global_discount_amount'
            ]
        );

        $this->assertSame(
            '11.0000',
            $result['tax_rate']
        );

        $this->assertSame(
            '8910.00',
            $result['tax_total']
        );

        $this->assertSame(
            '89910.00',
            $result['total']
        );
    }
}
