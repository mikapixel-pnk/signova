<?php

return [
    'public_quotation' => [
        'base_url' => rtrim(
            env(
                'SIGNOVA_PUBLIC_QUOTATION_BASE_URL',
                rtrim(
                    env(
                        'APP_URL',
                        'http://localhost'
                    ),
                    '/'
                ) . '/q'
            ),
            '/'
        ),
    ],
];
