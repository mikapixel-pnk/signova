<?php

return [
    'key' => 'premium_navy',
    'name' => 'Premium Navy',
    'author' => 'Novel',
    'sort_order' => 100,
    'tier' => 'PREMIUM',
    'enabled' => true,

    'layout' => 'premium',
    'view' => 'view.blade.php',
    'version' => 1,

    'default_palette' => 'navy',

    'palettes' => [
        'navy' => [
            'name' => 'Navy',

            'tokens' => [
                'primary' => '#0F2747',
                'primary_dark' => '#09192F',
                'accent' => '#2EA8FF',
                'text' => '#172033',
                'muted' => '#667085',
                'border' => '#D7DEE8',
                'surface' => '#F8FAFC',
            ],
        ],
    ],

    'logo' => [
        'show' => true,
        'placement' => 'TOP_LEFT',
        'max_width' => 155,
        'max_height' => 64,
        'fit' => 'contain',
        'alignment' => 'left',
        'padding' => 0,
    ],

    'background' => [
        'asset' => 'assets/background.png',
        'mode' => 'cover',
    ],
];
