<?php

namespace App\Support\PublicFront\Icons;

use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Stringable;

use function Filament\Support\generate_icon_html;

class PublicFrontIconRegistry
{
    public const NONE = 'none';

    public const DEFAULT_CONTENT = 'OutlinedDocumentText';

    public const DEFAULT_CALENDAR = 'OutlinedCalendar';

    public const DEFAULT_PODCAST = 'OutlinedRectangleGroup';

    private const SEARCH_LIMIT = 50;

    /** @var array<string, string|null>|null */
    private static ?array $aliases = null;

    /** @var array<string, Heroicon>|null */
    private static ?array $casesByName = null;

    /** @var array<string, string>|null */
    private static ?array $legacyReverseAliases = null;

    /** @var array<string, string> */
    private static array $htmlLabels = [];

    /** @var array<string, string> */
    private static array $plainLabels = [];

    /**
     * @return array<string, string|null>
     */
    public static function legacyAliases(): array
    {
        return [
            'none' => self::NONE,
            'image' => 'OutlinedPhoto',
            'title' => self::DEFAULT_CONTENT,
            'description' => self::DEFAULT_CONTENT,
            'calendar' => self::DEFAULT_CALENDAR,
            'clock' => 'OutlinedClock',
            'tag' => 'OutlinedTag',
            'folder' => 'OutlinedFolder',
            'user' => 'OutlinedUser',
            'users' => 'OutlinedUsers',
            'microphone' => 'OutlinedMicrophone',
            'link' => 'OutlinedLink',
            'play' => 'OutlinedPlay',
            'document' => self::DEFAULT_CONTENT,
            'podcast' => self::DEFAULT_PODCAST,
            'sparkles' => 'OutlinedSparkles',
            'arrow_right' => 'OutlinedArrowRight',
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public static function aliases(): array
    {
        return self::$aliases ??= [
            ...self::legacyAliases(),
            'document-text' => self::DEFAULT_CONTENT,
        ];
    }

    /**
     * @return array<string>
     */
    public static function tokens(): array
    {
        return [
            self::NONE,
            ...array_keys(self::casesByName()),
        ];
    }

    public static function normalizeToken(mixed $value): ?string
    {
        if ($value instanceof Heroicon) {
            return $value->name;
        }

        if (! is_string($value) && ! $value instanceof Stringable) {
            return null;
        }

        $token = trim((string) $value);

        if ($token === '') {
            return null;
        }

        if (array_key_exists($token, self::aliases())) {
            return self::aliases()[$token] ?? self::NONE;
        }

        if (array_key_exists($token, self::casesByName())) {
            return $token;
        }

        return null;
    }

    public static function resolve(mixed $value): ?Heroicon
    {
        $token = self::normalizeToken($value);

        if ($token === null || $token === self::NONE) {
            return null;
        }

        return self::casesByName()[$token] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function searchResults(?string $search, int $limit = self::SEARCH_LIMIT): array
    {
        $search = Str::of($search ?? '')
            ->lower()
            ->trim()
            ->toString();

        $results = [];

        foreach (self::candidateNames($search) as $name) {
            if ($name === self::NONE) {
                $results[self::NONE] = self::htmlLabel(self::NONE);
            } elseif (array_key_exists($name, self::casesByName())) {
                $results[$name] = self::htmlLabel($name);
            }

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    public static function optionLabel(mixed $value): ?string
    {
        $token = self::normalizeToken($value);

        if ($token === null) {
            return null;
        }

        return self::htmlLabel($token);
    }

    /**
     * @param  array<mixed>  $values
     * @return array<string, string>
     */
    public static function optionLabels(array $values): array
    {
        return collect($values)
            ->mapWithKeys(function (mixed $value): array {
                $token = self::normalizeToken($value);

                return $token === null ? [] : [$token => self::htmlLabel($token)];
            })
            ->all();
    }

    public static function plainLabel(mixed $value): ?string
    {
        $token = self::normalizeToken($value);

        if ($token === null) {
            return null;
        }

        return self::$plainLabels[$token] ??= match ($token) {
            self::NONE => __('admin.card_template_icons.none'),
            default => Str::of($token)
                ->headline()
                ->toString(),
        };
    }

    public static function legacyAliasFor(mixed $value, ?string $preferred = null): ?string
    {
        $token = self::normalizeToken($value);

        if ($token === null) {
            return null;
        }

        if ($preferred !== null && (self::aliases()[$preferred] ?? null) === $token) {
            return $preferred;
        }

        return self::legacyReverseAliases()[$token] ?? $token;
    }

    /**
     * @return array<string, Heroicon>
     */
    private static function casesByName(): array
    {
        return self::$casesByName ??= collect(Heroicon::cases())
            ->mapWithKeys(fn (Heroicon $case): array => [$case->name => $case])
            ->all();
    }

    /**
     * @return array<string>
     */
    private static function candidateNames(string $search): array
    {
        if ($search === '') {
            return [
                self::NONE,
                ...collect(self::legacyAliases())
                    ->values()
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ];
        }

        $candidates = [];

        if (str_contains('none no icon without icon', $search)) {
            $candidates[] = self::NONE;
        }

        foreach (self::casesByName() as $name => $case) {
            $haystack = Str::of(implode(' ', [
                $name,
                $case->value,
                self::plainLabel($name),
                ...self::aliasesForToken($name),
            ]))->lower();

            if (! $haystack->contains($search)) {
                continue;
            }

            $candidates[] = $name;
        }

        return collect($candidates)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string>
     */
    private static function aliasesForToken(string $token): array
    {
        return collect(self::aliases())
            ->filter(fn (?string $caseName): bool => $caseName === $token)
            ->keys()
            ->all();
    }

    private static function htmlLabel(string $token): string
    {
        return self::$htmlLabels[$token] ??= match ($token) {
            self::NONE => '<span class="flex items-center gap-2"><span class="inline-flex h-5 w-5 items-center justify-center rounded border border-gray-300 text-xs text-gray-400 dark:border-gray-600">-</span><span>'.e(__('admin.card_template_icons.none')).'</span></span>',
            default => self::iconHtmlLabel($token),
        };
    }

    private static function iconHtmlLabel(string $token): string
    {
        $icon = self::casesByName()[$token] ?? null;

        if (! $icon instanceof Heroicon) {
            return e($token);
        }

        return '<span class="flex items-center gap-2">'
            .'<span class="inline-flex h-5 w-5 items-center justify-center text-gray-500 dark:text-gray-400">'
            .generate_icon_html($icon)->toHtml()
            .'</span>'
            .'<span class="min-w-0">'
            .'<span class="block truncate">'.e(self::plainLabel($token)).'</span>'
            .'<span class="block truncate text-xs text-gray-500 dark:text-gray-400">'.e($token).'</span>'
            .'</span>'
            .'</span>';
    }

    /**
     * @return array<string, string>
     */
    private static function legacyReverseAliases(): array
    {
        return self::$legacyReverseAliases ??= [
            self::NONE => 'none',
            'OutlinedPhoto' => 'image',
            self::DEFAULT_CONTENT => 'document',
            self::DEFAULT_CALENDAR => 'calendar',
            'OutlinedClock' => 'clock',
            'OutlinedTag' => 'tag',
            'OutlinedFolder' => 'folder',
            'OutlinedUser' => 'user',
            'OutlinedUsers' => 'users',
            'OutlinedMicrophone' => 'microphone',
            'OutlinedLink' => 'link',
            'OutlinedPlay' => 'play',
            self::DEFAULT_PODCAST => 'podcast',
            'OutlinedSparkles' => 'sparkles',
            'OutlinedArrowRight' => 'arrow_right',
        ];
    }
}
