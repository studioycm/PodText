<?php

return [
    'picker' => [
        'driver' => env('PODTEXT_MEDIA_PICKER_DRIVER', 'curator'),
    ],

    'acquisition' => [
        'storage_candidate_limit' => 50,
        'storage_sources' => [
            'public_imports' => [
                'disk' => 'public',
                'root' => 'media-imports',
                'mode' => 'register',
                'label' => [
                    'en' => 'Public imports',
                    'he' => 'ייבוא ציבורי',
                ],
            ],
            'private_imports' => [
                'disk' => 'local',
                'root' => 'media-imports',
                'mode' => 'copy',
                'label' => [
                    'en' => 'Private imports',
                    'he' => 'ייבוא פרטי',
                ],
            ],
        ],
    ],

    'embeds' => [
        'allowed_hosts' => [
            'embed.podcasts.apple.com',
            'open.spotify.com',
            'podcasts.apple.com',
            'player.vimeo.com',
            'soundcloud.com',
            'vimeo.com',
            'www.youtube.com',
            'youtube.com',
            'youtu.be',
        ],
    ],
];
