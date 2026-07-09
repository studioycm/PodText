<?php

namespace App\Support\PublicFront\ItemPage;

class PublicItemPageRegistry
{
    /**
     * @return array<string>
     */
    public static function dateDisplays(): array
    {
        return [
            'site',
            'original',
            'both',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function dateDisplayOptions(): array
    {
        return self::translatedOptions(self::dateDisplays(), 'admin.item_page_date_displays');
    }

    /**
     * @return array<string>
     */
    public static function labelModes(): array
    {
        return [
            'long',
            'short',
            'hidden',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labelModeOptions(): array
    {
        return self::translatedOptions(self::labelModes(), 'admin.item_page_label_modes');
    }

    /**
     * @return array<string>
     */
    public static function badgeSizes(): array
    {
        return [
            'xs',
            'sm',
            'md',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function badgeSizeOptions(): array
    {
        return self::translatedOptions(self::badgeSizes(), 'admin.item_page_badge_sizes');
    }

    /**
     * @return array<string>
     */
    public static function badgeColors(): array
    {
        return [
            'gray',
            'primary',
            'info',
            'success',
            'warning',
            'danger',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function badgeColorOptions(): array
    {
        return self::translatedOptions(self::badgeColors(), 'admin.item_page_badge_colors');
    }

    /**
     * @return array<string>
     */
    public static function podcastIdentityModes(): array
    {
        return [
            'badge',
            'text',
            'hidden',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function podcastIdentityModeOptions(): array
    {
        return self::translatedOptions(self::podcastIdentityModes(), 'admin.item_page_podcast_identity_modes');
    }

    /**
     * @return array<string>
     */
    public static function infoFields(): array
    {
        return [
            'site_published_date',
            'original_published_date',
            'transcription_date',
            'duration',
            'transcribers',
            'reading_time',
            'word_count',
            'transcription_count',
            'categories',
            'tags',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function infoFieldOptions(): array
    {
        return self::translatedOptions(self::infoFields(), 'admin.item_page_info_fields');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function defaultInfoFields(): array
    {
        return [
            self::infoField('site_published_date', 'long', 'calendar'),
            self::infoField('original_published_date', 'short', 'calendar'),
            self::infoField('transcription_date', 'short', 'document'),
            self::infoField('duration', 'hidden', 'clock'),
            self::infoField('transcribers', 'hidden', 'users'),
            self::infoField('categories', 'hidden', 'folder'),
            self::infoField('tags', 'hidden', 'tag'),
            self::infoField('transcription_count', 'hidden', 'document'),
        ];
    }

    public static function infoBadgeSizeClass(?string $size): string
    {
        return match ($size) {
            'xs' => 'gap-1 px-1.5 py-0.5 text-xs',
            'md' => 'gap-2 px-2.5 py-1.5 text-sm',
            default => 'gap-1.5 px-2 py-1 text-xs',
        };
    }

    public static function infoBadgeColorClass(?string $color): string
    {
        return match ($color) {
            'primary' => 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-200',
            'info' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-200',
            'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
            'warning' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200',
            'danger' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200',
            default => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200',
        };
    }

    /**
     * @param  array<string>  $values
     * @return array<string, string>
     */
    private static function translatedOptions(array $values, string $baseKey): array
    {
        return collect($values)
            ->mapWithKeys(fn (string $value): array => [$value => __("{$baseKey}.{$value}")])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function infoField(string $field, string $labelMode, string $icon): array
    {
        return [
            'field' => $field,
            'label_mode' => $labelMode,
            'label_override' => null,
            'icon' => $icon,
            'icon_position' => 'inline_before',
            'size' => self::badgeSizes()[1],
            'color' => self::badgeColors()[0],
        ];
    }
}
