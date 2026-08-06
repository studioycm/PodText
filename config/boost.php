<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Boost Guidelines
    |--------------------------------------------------------------------------
    |
    | Guideline keys excluded from the generated CLAUDE.md. The two FilaCheck
    | packages ship guidelines telling agents to run `vendor/bin/filacheck`
    | with `--fix`, which contradicts this project's rule that fixes are only
    | written with explicit approval — and the binary force-enables `--fix` on
    | its own whenever it detects an AI agent. PodText's own FilaCheck rules
    | live in .ai/guidelines/tooling-quality.md instead.
    |
    | Excluding them here is what makes the override durable: without it every
    | `php artisan boost:install` puts the unsafe instructions straight back.
    |
    */

    'guidelines' => [
        'exclude' => [
            'laraveldaily/filacheck',
            'laraveldaily/filacheck-pro',
        ],
    ],

];
