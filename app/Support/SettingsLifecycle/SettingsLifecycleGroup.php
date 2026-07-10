<?php

namespace App\Support\SettingsLifecycle;

use Spatie\LaravelSettings\Settings;

class SettingsLifecycleGroup
{
    /**
     * @param  class-string<Settings>  $settingsClass
     */
    public function __construct(
        public readonly string $name,
        public readonly string $settingsClass,
        public readonly SettingsLifecycleOverlay $overlay,
    ) {}

    public function settings(): Settings
    {
        return app($this->settingsClass);
    }

    /**
     * @return array<string, mixed>
     */
    public function currentPayload(): array
    {
        return $this->settings()->getRepository()->getPropertiesInGroup($this->name);
    }
}
