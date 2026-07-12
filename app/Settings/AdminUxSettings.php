<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Admin UX settings currently contain only the IMG-A media naming strategy.
 * EP1 may add broader workspace preferences to this settings group later.
 */
class AdminUxSettings extends Settings
{
    public string $media_naming_strategy;

    public static function group(): string
    {
        return 'admin_ux';
    }
}
