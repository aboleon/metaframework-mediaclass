<?php

declare(strict_types=1);

return [
    'disk'       => 'public',
    'dimensions' => [
        'xl' => ['width' => 1920, 'height' => 1080],
        'lg' => ['width' => 1400, 'height' => 788],
        'md' => ['width' => 700,  'height' => 394],
        'sm' => ['width' => 400,  'height' => 225],
    ],
    'subgroups' => [
        'count' => 5,
        'label' => 'Group',
        'empty_label' => 'No subgroup',
        'key_prefix' => 'group_',
        'groups' => [
            // 'gallery' => true,
            // 'gallery' => ['count' => 5, 'label' => 'Group'],
            // 'gallery' => ['options' => ['featured' => 'Featured', 'flow' => 'Flow']],
        ],
    ],
];
