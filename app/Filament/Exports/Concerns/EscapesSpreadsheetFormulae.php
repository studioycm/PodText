<?php

namespace App\Filament\Exports\Concerns;

use App\Models\Category;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

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

    protected static function safeSpreadsheetDateTime(mixed $state): ?string
    {
        if (! $state instanceof DateTimeInterface) {
            return self::safeSpreadsheetText($state);
        }

        return self::safeSpreadsheetText(CarbonImmutable::instance($state)->setTimezone('Asia/Jerusalem')->format('d/m/Y H:i'));
    }

    protected static function safeSpreadsheetDate(mixed $state): ?string
    {
        if (! $state instanceof DateTimeInterface) {
            return self::safeSpreadsheetText($state);
        }

        return self::safeSpreadsheetText(CarbonImmutable::instance($state)->setTimezone('Asia/Jerusalem')->format('d/m/Y'));
    }

    protected static function safeSpreadsheetJson(mixed $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        return self::safeSpreadsheetText(json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected static function categoryPath(Category $category): string
    {
        $segments = collect([$category->slug]);
        $category->loadMissing('parent');
        $parent = $category->parent;

        while ($parent) {
            $segments->prepend($parent->slug);
            $parent->loadMissing('parent');
            $parent = $parent->parent;
        }

        return $segments->implode('/');
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    protected static function categoryPaths(Collection $categories): string
    {
        return $categories
            ->map(fn (Category $category): string => self::categoryPath($category))
            ->implode('|');
    }
}
