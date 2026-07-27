<?php

namespace App\Filament\Support;

final class IntegerTextInputState
{
    public static function dehydrate(mixed $state): mixed
    {
        if (
            is_float($state)
            && is_finite($state)
            && floor($state) === $state
            && $state >= PHP_INT_MIN
            && $state < PHP_INT_MAX
        ) {
            return (int) $state;
        }

        return $state;
    }
}
