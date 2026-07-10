<?php

namespace App\Support\SettingsLifecycle;

use App\Settings\PublicContentSettings;
use InvalidArgumentException;

class SettingsLifecycleGroups
{
    public function publicContent(): SettingsLifecycleGroup
    {
        return new SettingsLifecycleGroup(
            name: PublicContentSettings::group(),
            settingsClass: PublicContentSettings::class,
            overlay: SettingsLifecycleOverlay::publicContent(),
        );
    }

    /**
     * @return array<string, SettingsLifecycleGroup>
     */
    public function all(): array
    {
        $publicContent = $this->publicContent();

        return [
            $publicContent->name => $publicContent,
        ];
    }

    public function get(string $group): SettingsLifecycleGroup
    {
        return $this->all()[$group] ?? throw new InvalidArgumentException("Settings lifecycle group [{$group}] is not registered.");
    }

    public function defaultGroup(): SettingsLifecycleGroup
    {
        return $this->publicContent();
    }
}
