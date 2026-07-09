<?php

namespace App\Support\PublicFront\ItemPage;

use App\Support\PublicFront\Icons\PublicFrontIconRegistry;

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
            'title',
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
    public static function podcastIdentityPositions(): array
    {
        return [
            'above_title',
            'below_title',
            'title_row_before',
            'title_row_after',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function podcastIdentityPositionOptions(): array
    {
        return self::translatedOptions(self::podcastIdentityPositions(), 'admin.item_page_podcast_identity_positions');
    }

    /**
     * @return array<string>
     */
    public static function podcastIdentitySizes(): array
    {
        return [
            'xs',
            'sm',
            'md',
            'lg',
            'title',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function podcastIdentitySizeOptions(): array
    {
        return self::translatedOptions(self::podcastIdentitySizes(), 'admin.item_page_podcast_identity_sizes');
    }

    /**
     * @return array<string>
     */
    public static function podcastIdentityColors(): array
    {
        return [
            ...self::badgeColors(),
            ...self::podcastImageColors(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function podcastIdentityColorOptions(): array
    {
        return self::translatedOptions(self::podcastIdentityColors(), 'admin.item_page_podcast_identity_colors');
    }

    /**
     * @return array<string>
     */
    public static function podcastImageColors(): array
    {
        return [
            'image_1',
            'image_2',
            'image_3',
        ];
    }

    public static function isPodcastImageColor(?string $color): bool
    {
        return in_array($color, self::podcastImageColors(), true);
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
            self::infoField('site_published_date', 'long', PublicFrontIconRegistry::DEFAULT_CALENDAR),
            self::infoField('original_published_date', 'short', PublicFrontIconRegistry::DEFAULT_CALENDAR),
            self::infoField('transcription_date', 'short', PublicFrontIconRegistry::DEFAULT_CONTENT),
            self::infoField('duration', 'hidden', 'OutlinedClock'),
            self::infoField('transcribers', 'hidden', 'OutlinedUsers'),
            self::infoField('categories', 'hidden', 'OutlinedFolder'),
            self::infoField('tags', 'hidden', 'OutlinedTag'),
            self::infoField('transcription_count', 'hidden', PublicFrontIconRegistry::DEFAULT_CONTENT),
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

    public static function podcastIdentityTextColorClass(?string $color): string
    {
        return match ($color) {
            'primary' => 'text-primary-700 hover:text-primary-900 dark:text-primary-300 dark:hover:text-primary-100',
            'info' => 'text-sky-700 hover:text-sky-900 dark:text-sky-300 dark:hover:text-sky-100',
            'success' => 'text-emerald-700 hover:text-emerald-900 dark:text-emerald-300 dark:hover:text-emerald-100',
            'warning' => 'text-amber-700 hover:text-amber-900 dark:text-amber-300 dark:hover:text-amber-100',
            'danger' => 'text-rose-700 hover:text-rose-900 dark:text-rose-300 dark:hover:text-rose-100',
            default => 'text-gray-700 hover:text-gray-950 dark:text-gray-200 dark:hover:text-white',
        };
    }

    public static function podcastIdentityTextSizeClass(?string $size): string
    {
        return match ($size) {
            'xs' => 'text-xs',
            'md' => 'text-base',
            'lg' => 'text-lg',
            'title' => 'text-3xl leading-tight',
            default => 'text-sm',
        };
    }

    public static function podcastIdentityBadgeSizeClass(?string $size): string
    {
        return match ($size) {
            'xs' => 'gap-1 px-1.5 py-0.5 text-xs',
            'md' => 'gap-2 px-2.5 py-1.5 text-sm',
            'lg' => 'gap-2.5 px-3 py-2 text-base',
            'title' => 'gap-2.5 px-3.5 py-2 text-lg',
            default => 'gap-1.5 px-2 py-1 text-xs',
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
