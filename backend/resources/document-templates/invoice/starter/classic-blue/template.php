<?php

return [
    'key' => 'classic_blue',
    'name' => 'Classic Blue',
    'tier' => 'STARTER',
    'enabled' => true,

    'layout' => 'classic',
    'version' => 1,

    'default_palette' => 'blue',

    'palettes' => [
        'blue' => [
            'name' => 'Blue',

            'tokens' => [
                'primary' => '#2563EB',
                'primary_dark' => '#1E40AF',
                'accent' => '#DBEAFE',
                'text' => '#1F2937',
                'muted' => '#6B7280',
                'border' => '#D1D5DB',
                'surface' => '#F8FAFC',
            ],
        ],
    ],

    'logo' => [
        'show' => true,
        'placement' => 'TOP_LEFT',
        'max_width' => 150,
        'max_height' => 64,
        'fit' => 'contain',
        'alignment' => 'left',
        'padding' => 0,
    ],

    'background' => null,
];
