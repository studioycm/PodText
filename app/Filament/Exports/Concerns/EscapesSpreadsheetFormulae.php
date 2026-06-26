<?php

namespace App\Filament\Exports\Concerns;

use BackedEnum;
use DateTimeInterface;

trait EscapesSpreadsheetFormulae
{
    protected static function safeSpreadsheetText(mixed $state): ?string
    {
        if ($state === null) {
            return null;
        }

        if ($state instanceof BackedEnum) {
            $state = $state->value;
        }

        if ($state instanceof DateTimeInterface) {
            $state = $state->format('Y-m-d H:i:s');
        }

        $state = (string) $state;

        if ($state === '') {
            return '';
        }

        return str_starts_with($state, '=')
            || str_starts_with($state, '+')
            || str_starts_with($state, '-')
            || str_starts_with($state, '@')
                ? "'{$state}"
                : $state;
    }
}
