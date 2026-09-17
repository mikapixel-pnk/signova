<?php

return [
    'key' => 'classic_blue',
    'name' => 'Classic Blue',
    'author' => 'Novel',
    'sort_order' => 10,
    'tier' => 'STARTER',
    'enabled' => true,

    'layout' => 'classic',
    'view' => 'view.blade.php',
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
