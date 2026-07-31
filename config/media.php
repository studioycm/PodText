<?php

return [
    'picker' => [
        'driver' => env('PODTEXT_MEDIA_PICKER_DRIVER', 'curator'),
    ],

    'acquisition' => [
        'external_image_timeout_seconds' => 20,
        'storage_lock_seconds' => 60,
        'storage_lock_wait_seconds' => 3,
        'storage_candidate_limit' => 50,
        'storage_traversal_limit' => 2000,
        'storage_directory_limit' => 200,
        'storage_sources' => [
            'laravel_public' => [
                'disk' => 'public',
                'root' => '',
                'mode' => 'register',
                'label' => [
                    'en' => 'Laravel public disk',
                    'he' => 'דיסק Laravel הציבורי',
                ],
            ],
            'public_images' => [
                'disk' => 'public_assets',
                'root' => 'images',
                'mode' => 'copy',
                'label' => [
                    'en' => 'Public images',
                    'he' => 'תמונות public',
                ],
            ],
        ],
    ],

    'quarantine' => [
        // Completed mutation quarantine copies older than this many days may be
        // pruned by `media:prune-quarantine`. 0 keeps them forever. Incomplete
        // operations are never pruned regardless of age.
        'retention_days' => (int) env('MEDIA_QUARANTINE_RETENTION_DAYS', 90),
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
