<?php

return [
    'key' => 'ocean_blue',
    'name' => 'Ocean Blue',
    'author' => 'Novel',
    'sort_order' => 40,

    'tier' => 'STARTER',
    'enabled' => true,

    'layout' => 'ocean',
    'view' => 'view.blade.php',
    'version' => 1,

    'default_palette' => 'ocean',

    'palettes' => [
        'ocean' => [
            'name' => 'Ocean',

            'tokens' => [
                'primary' => '#155E75',
                'primary_dark' => '#164E63',
                'accent' => '#38BDF8',
                'text' => '#172033',
                'muted' => '#64748B',
                'border' => '#D8E3EA',
                'surface' => '#F5FAFC',
            ],
        ],
    ],

    'logo' => [
        'show' => true,
        'placement' => 'TOP_LEFT',
        'max_width' => 150,
        'max_height' => 60,
        'fit' => 'contain',
        'alignment' => 'left',
        'padding' => 0,
    ],

    'background' => [
        'asset' => 'assets/background.png',
        'mode' => 'cover',
    ],
];
