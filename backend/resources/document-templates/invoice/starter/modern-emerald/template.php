<?php

return [
    'key' => 'modern_emerald',
    'name' => 'Modern Emerald',
    'author' => 'Novel',
    'sort_order' => 20,
    'tier' => 'STARTER',
    'enabled' => true,

    'layout' => 'modern',
    'view' => 'view.blade.php',
    'version' => 1,

    'default_palette' => 'emerald',

    'palettes' => [
        'emerald' => [
            'name' => 'Emerald',

            'tokens' => [
                'primary' => '#059669',
                'primary_dark' => '#047857',
                'accent' => '#D1FAE5',
                'text' => '#1F2937',
                'muted' => '#6B7280',
                'border' => '#D1D5DB',
                'surface' => '#F8FAFC',
            ],
        ],
    ],

    'logo' => [
        'show' => true,
        'placement' => 'HEADER_INLINE',
        'max_width' => 145,
        'max_height' => 60,
        'fit' => 'contain',
        'alignment' => 'left',
        'padding' => 0,
    ],

    'background' => null,
];
