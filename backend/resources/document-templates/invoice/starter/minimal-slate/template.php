<?php

return [
    'key' => 'minimal_slate',
    'name' => 'Minimal Slate',
    'author' => 'Novel',
    'sort_order' => 30,
    'tier' => 'STARTER',
    'enabled' => true,

    'layout' => 'minimal',
    'view' => 'view.blade.php',
    'version' => 1,

    'default_palette' => 'slate',

    'palettes' => [
        'slate' => [
            'name' => 'Slate',

            'tokens' => [
                'primary' => '#475569',
                'primary_dark' => '#334155',
                'accent' => '#E2E8F0',
                'text' => '#0F172A',
                'muted' => '#64748B',
                'border' => '#CBD5E1',
                'surface' => '#F8FAFC',
            ],
        ],
    ],

    'logo' => [
        'show' => true,
        'placement' => 'TOP_CENTER',
        'max_width' => 125,
        'max_height' => 56,
        'fit' => 'contain',
        'alignment' => 'center',
        'padding' => 0,
    ],

    'background' => null,
];
