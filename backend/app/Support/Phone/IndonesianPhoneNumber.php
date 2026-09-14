<?php

namespace App\Support\Phone;

use InvalidArgumentException;

final class IndonesianPhoneNumber
{
    public static function normalize(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        /*
         * Remove formatting:
         * spaces, dash, parentheses, dots,
         * and leading plus sign.
         */
        $digits = preg_replace(
            '/\D+/',
            '',
            $value
        );

        if (
            ! is_string($digits)
            || $digits === ''
        ) {
            throw new InvalidArgumentException(
                'Nomor WhatsApp tidak valid.'
            );
        }

        /*
         * Indonesia normalization:
         *
         * 08xxxxxxxxxx
         * -> 628xxxxxxxxxx
         *
         * 8xxxxxxxxxx
         * -> 628xxxxxxxxxx
         *
         * 628xxxxxxxxxx
         * -> unchanged
         *
         * +628...
         * -> 628...
         */
        if (
            str_starts_with(
                $digits,
                '62'
            )
        ) {
            $normalized = $digits;
        } elseif (
            str_starts_with(
                $digits,
                '0'
            )
        ) {
            $normalized =
                '62'
                . substr(
                    $digits,
                    1
                );
        } elseif (
            str_starts_with(
                $digits,
                '8'
            )
        ) {
            $normalized =
                '62'
                . $digits;
        } else {
            throw new InvalidArgumentException(
                'Nomor WhatsApp harus menggunakan nomor Indonesia.'
            );
        }

        /*
         * Typical Indonesian mobile number
         * after normalization is roughly
         * 10-15 digits.
         */
        if (
            strlen($normalized) < 10
            || strlen($normalized) > 15
        ) {
            throw new InvalidArgumentException(
                'Panjang nomor WhatsApp tidak valid.'
            );
        }

        return $normalized;
    }

    public static function masked(
        ?string $value
    ): ?string {
        $normalized =
            self::normalize($value);

        if ($normalized === null) {
            return null;
        }

        $length =
            strlen($normalized);

        if ($length <= 6) {
            return str_repeat(
                '*',
                $length
            );
        }

        return
            substr(
                $normalized,
                0,
                4
            )
            . str_repeat(
                '*',
                max(
                    4,
                    $length - 8
                )
            )
            . substr(
                $normalized,
                -4
            );
    }
}
