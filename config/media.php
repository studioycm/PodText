<?php

return [
    'picker' => [
        'driver' => env('PODTEXT_MEDIA_PICKER_DRIVER', 'curator'),
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
