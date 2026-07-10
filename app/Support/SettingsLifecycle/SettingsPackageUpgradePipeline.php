<?php

namespace App\Support\SettingsLifecycle;

use InvalidArgumentException;

class SettingsPackageUpgradePipeline
{
    /**
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>
     */
    public function upgrade(array $package): array
    {
        $schemaVersion = (int) ($package['schema_version'] ?? 0);

        if ($schemaVersion === PublicSettingsPackage::SCHEMA_VERSION) {
            return $package;
        }

        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Settings package schema version is invalid.');
        }

        if ($schemaVersion > PublicSettingsPackage::SCHEMA_VERSION) {
            return $package;
        }

        return $package;
    }
}
