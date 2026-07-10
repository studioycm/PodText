<?php

namespace App\Support\SettingsLifecycle;

use App\Enums\SettingsImportMode;

class SettingsImportMergeEngine
{
    public function shouldApplyAddOnly(mixed $currentValue, bool $currentExists, mixed $importedValue, bool $importedExists): bool
    {
        if (! $importedExists) {
            return false;
        }

        if (! $currentExists || $this->isEmptyValue($currentValue)) {
            return true;
        }

        if ($this->isAssociativeArray($currentValue) && $this->isAssociativeArray($importedValue)) {
            return $this->mergeAssociativeCurrentWins($currentValue, $importedValue) !== $currentValue;
        }

        return false;
    }

    public function merge(SettingsImportMode $mode, mixed $currentValue, bool $currentExists, mixed $importedValue, bool $importedExists): mixed
    {
        if ($mode === SettingsImportMode::Replace) {
            return $importedValue;
        }

        if (! $importedExists) {
            return $currentValue;
        }

        if (! $currentExists || $this->isEmptyValue($currentValue)) {
            return $importedValue;
        }

        if ($this->isAssociativeArray($currentValue) && $this->isAssociativeArray($importedValue)) {
            return $this->mergeAssociativeCurrentWins($currentValue, $importedValue);
        }

        return $currentValue;
    }

    private function mergeAssociativeCurrentWins(array $currentValue, array $importedValue): array
    {
        $merged = $currentValue;

        foreach ($importedValue as $key => $value) {
            if (! array_key_exists($key, $merged)) {
                $merged[$key] = $value;

                continue;
            }

            if ($this->isAssociativeArray($merged[$key]) && $this->isAssociativeArray($value)) {
                $merged[$key] = $this->mergeAssociativeCurrentWins($merged[$key], $value);
            }
        }

        return $merged;
    }

    private function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function isAssociativeArray(mixed $value): bool
    {
        return is_array($value) && ! array_is_list($value);
    }
}
