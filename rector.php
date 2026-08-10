<?php

use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/app', __DIR__.'/database', __DIR__.'/routes'])
    ->withPHPStanConfigs([__DIR__.'/phpstan.neon', __DIR__.'/vendor/larastan/larastan/extension.neon'])
    ->withCache(__DIR__.'/storage/framework/cache/rector')
    ->withSets([LaravelSetList::LARAVEL_CODE_QUALITY])
    // Serial on purpose: parallel mode is nondeterministic and lossy here (measured 2026-08-10: parallel 17 vs 8 changed files run-to-run; serial 69, byte-identical twice) — see the dry-run report §0c.
    ->withoutParallel();
