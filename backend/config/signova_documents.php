<?php

return [
    'quotation' => [
        'prefix' => env(
            'SIGNOVA_QUOTATION_PREFIX',
            'PEN'
        ),

        'sequence_digits' => 6,

        /*
         * Format periode:
         * YYMM
         *
         * Contoh September 2026:
         * 2609
         */
        'period_format' => 'ym',
    ],

    'invoice' => [
        'prefix' => env(
            'SIGNOVA_INVOICE_PREFIX',
            'INV'
        ),

        'sequence_digits' => 6,
        'period_format' => 'ym',
    ],
];
