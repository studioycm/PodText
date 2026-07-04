<?php

namespace App\Support\PublicFront\Sections;

use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;

class PublicDisplaySectionRegistry
{
    public const LATEST_CONTENT_ITEMS = 'latest_content_items';

    public const CATEGORY_CONTENT_ITEMS = 'category_content_items';

    public const TAG_CONTENT_ITEMS = 'tag_content_items';

    public const CONTENT_GROUP_ITEMS = 'content_group_items';

    public const MANUAL_CONTENT_ITEMS = 'manual_content_items';

    public const CONTENT_GROUPS = 'content_groups';

    public const CATEGORIES = 'categories';

    public const CONTRIBUTORS = 'contributors';

    public const TOP_TRANSCRIBERS = 'top_transcribers';

    /**
     * @return array<string>
     */
    public static function sourceTypes(): array
    {
        return [
            self::LATEST_CONTENT_ITEMS,
            self::CATEGORY_CONTENT_ITEMS,
            self::TAG_CONTENT_ITEMS,
            self::CONTENT_GROUP_ITEMS,
            self::MANUAL_CONTENT_ITEMS,
            self::CONTENT_GROUPS,
            self::CATEGORIES,
            self::CONTRIBUTORS,
            self::TOP_TRANSCRIBERS,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourceTypeOptions(): array
    {
        return self::translatedOptions(self::sourceTypes(), 'admin.public_display_section_sources');
    }

    /**
     * @return array<string>
     */
    public static function sortTypes(): array
    {
        return [
            'latest_transcription',
            'oldest_transcription',
            'original_newest',
            'original_oldest',
            'title_asc',
            'title_desc',
            'homepage_order',
            'newest',
            'name_asc',
            'top_transcriptions',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sortOptions(): array
    {
        return self::translatedOptions(self::sortTypes(), 'admin.public_display_section_sorts');
    }

    /**
     * @return array<string>
     */
    public static function directions(): array
    {
        return ['asc', 'desc'];
    }

    /**
     * @return array<string>
     */
    public static function paginationModes(): array
    {
        return [
            'none',
            'simple',
            'load_more',
            'next_previous',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function paginationModeOptions(): array
    {
        return self::translatedOptions(self::paginationModes(), 'admin.public_display_section_pagination_modes');
    }

    public static function defaultSourceTypeForLegacyType(?string $legacyType): ?string
    {
        return match ($legacyType) {
            'latest' => self::LATEST_CONTENT_ITEMS,
            'category' => self::CATEGORY_CONTENT_ITEMS,
            'tag' => self::TAG_CONTENT_ITEMS,
            'content_group' => self::CONTENT_GROUP_ITEMS,
            'top_transcribers' => self::TOP_TRANSCRIBERS,
            default => null,
        };
    }

    public static function defaultTemplateFamilyForSourceType(?string $sourceType): ?string
    {
        return match ($sourceType) {
            self::CONTENT_GROUPS => PublicFrontCardTemplateRegistry::CONTENT_GROUP_FAMILY,
            self::CONTRIBUTORS, self::TOP_TRANSCRIBERS => PublicFrontCardTemplateRegistry::CONTRIBUTOR_FAMILY,
            self::LATEST_CONTENT_ITEMS,
            self::CATEGORY_CONTENT_ITEMS,
            self::TAG_CONTENT_ITEMS,
            self::CONTENT_GROUP_ITEMS,
            self::MANUAL_CONTENT_ITEMS => PublicFrontCardTemplateRegistry::CONTENT_ITEM_FAMILY,
            default => null,
        };
    }

    public static function isContentItemSource(?string $sourceType): bool
    {
        return in_array($sourceType, [
            self::LATEST_CONTENT_ITEMS,
            self::CATEGORY_CONTENT_ITEMS,
            self::TAG_CONTENT_ITEMS,
            self::CONTENT_GROUP_ITEMS,
            self::MANUAL_CONTENT_ITEMS,
        ], true);
    }

    public static function isContributorSource(?string $sourceType): bool
    {
        return in_array($sourceType, [
            self::CONTRIBUTORS,
            self::TOP_TRANSCRIBERS,
        ], true);
    }

    /**
     * @param  array<string>  $values
     * @return array<string, string>
     */
    private static function translatedOptions(array $values, string $translationGroup): array
    {
        return collect($values)
            ->mapWithKeys(fn (string $value): array => [$value => __("{$translationGroup}.{$value}")])
            ->all();
    }
}
