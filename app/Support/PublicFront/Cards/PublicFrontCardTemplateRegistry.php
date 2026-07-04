<?php

namespace App\Support\PublicFront\Cards;

class PublicFrontCardTemplateRegistry
{
    public const CONTENT_ITEM_FAMILY = 'content_item';

    public const CONTENT_GROUP_FAMILY = 'content_group';

    public const CONTRIBUTOR_FAMILY = 'contributor';

    /**
     * @return array<string>
     */
    public static function families(): array
    {
        return [
            self::CONTENT_ITEM_FAMILY,
            self::CONTENT_GROUP_FAMILY,
            self::CONTRIBUTOR_FAMILY,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function familyOptions(): array
    {
        return self::translatedOptions(self::families(), 'admin.card_template_families');
    }

    /**
     * @return array<string>
     */
    public static function partTypes(): array
    {
        return [
            'image',
            'title',
            'description',
            'metadata_row',
            'entity_attribute',
            'group_identity',
            'transcriber_line',
            'date_read_time',
            'taxonomy',
            'custom_text',
            'action_link',
            'divider',
            'spacer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function partTypeOptions(): array
    {
        return self::translatedOptions(self::partTypes(), 'admin.card_template_part_types');
    }

    /**
     * @return array<string>
     */
    public static function sources(): array
    {
        return [
            'content_item',
            'content_group',
            'transcription',
            'author',
            'categories',
            'tags',
            'custom',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourceOptions(): array
    {
        return self::translatedOptions(self::sources(), 'admin.card_template_sources');
    }

    /**
     * @return array<string, array<string>>
     */
    public static function attributes(): array
    {
        return [
            'content_item' => [
                'title',
                'description',
                'image',
                'duration',
                'effective_date',
                'original_published_at',
                'read_time',
                'type_label',
                'media_provider',
                'url',
            ],
            'content_group' => [
                'title',
                'description',
                'image',
                'identity',
                'type_label',
                'item_count',
                'url',
            ],
            'transcription' => [
                'title',
                'author_name',
                'published_at',
                'read_time',
                'word_count',
            ],
            'author' => [
                'name',
                'bio',
                'transcription_count',
                'content_item_count',
                'url',
            ],
            'categories' => [
                'names',
                'links',
            ],
            'tags' => [
                'names',
                'links',
            ],
            'custom' => [
                'text',
                'url',
            ],
        ];
    }

    /**
     * @return array<string>
     */
    public static function attributesForSource(?string $source): array
    {
        if ($source === null) {
            return [];
        }

        return self::attributes()[$source] ?? [];
    }

    /**
     * @return array<string, string>
     */
    public static function attributeOptions(?string $source): array
    {
        if ($source === null) {
            return [];
        }

        return self::translatedOptions(
            self::attributesForSource($source),
            "admin.card_template_attributes.{$source}",
        );
    }

    /**
     * @return array<string>
     */
    public static function partLayouts(): array
    {
        return [
            'inline',
            'stacked',
            'badge',
            'chips',
            'link',
            'plain',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function partLayoutOptions(): array
    {
        return self::translatedOptions(self::partLayouts(), 'admin.card_template_part_layouts');
    }

    /**
     * @return array<string>
     */
    public static function labelPositions(): array
    {
        return [
            'hidden',
            'before',
            'after',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labelPositionOptions(): array
    {
        return self::translatedOptions(self::labelPositions(), 'admin.card_template_label_positions');
    }

    /**
     * @return array<string>
     */
    public static function iconPositions(): array
    {
        return [
            'hidden',
            'before',
            'after',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function iconPositionOptions(): array
    {
        return self::translatedOptions(self::iconPositions(), 'admin.card_template_icon_positions');
    }

    /**
     * @return array<string>
     */
    public static function icons(): array
    {
        return [
            'none',
            'image',
            'title',
            'description',
            'calendar',
            'clock',
            'tag',
            'folder',
            'user',
            'users',
            'microphone',
            'link',
            'play',
            'document',
            'podcast',
            'sparkles',
            'arrow_right',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function iconOptions(): array
    {
        return self::translatedOptions(self::icons(), 'admin.card_template_icons');
    }

    /**
     * @return array<string>
     */
    public static function fontSizes(): array
    {
        return [
            'xs',
            'sm',
            'base',
            'lg',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fontSizeOptions(): array
    {
        return self::translatedOptions(self::fontSizes(), 'admin.card_template_font_sizes');
    }

    /**
     * @return array<string>
     */
    public static function urlTargets(): array
    {
        return [
            'self',
            'blank',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function urlTargetOptions(): array
    {
        return self::translatedOptions(self::urlTargets(), 'admin.card_template_url_targets');
    }

    /**
     * @return array<int, int>
     */
    public static function lineClampOptions(): array
    {
        return [
            0 => 0,
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
        ];
    }

    /**
     * @return array<string, array{source: ?string, attribute: ?string}>
     */
    public static function defaultPartSources(): array
    {
        return [
            'image' => ['source' => 'content_item', 'attribute' => 'image'],
            'title' => ['source' => 'content_item', 'attribute' => 'title'],
            'description' => ['source' => 'content_item', 'attribute' => 'description'],
            'metadata_row' => ['source' => 'content_item', 'attribute' => 'duration'],
            'entity_attribute' => ['source' => 'content_item', 'attribute' => 'title'],
            'group_identity' => ['source' => 'content_group', 'attribute' => 'identity'],
            'transcriber_line' => ['source' => 'transcription', 'attribute' => 'author_name'],
            'date_read_time' => ['source' => 'transcription', 'attribute' => 'published_at'],
            'taxonomy' => ['source' => 'categories', 'attribute' => 'links'],
            'custom_text' => ['source' => 'custom', 'attribute' => 'text'],
            'action_link' => ['source' => 'content_item', 'attribute' => 'url'],
            'divider' => ['source' => null, 'attribute' => null],
            'spacer' => ['source' => null, 'attribute' => null],
        ];
    }

    public static function defaultSourceForPart(string $type): ?string
    {
        return self::defaultPartSources()[$type]['source'] ?? null;
    }

    public static function defaultAttributeForPart(string $type): ?string
    {
        return self::defaultPartSources()[$type]['attribute'] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function defaultTemplateKeys(): array
    {
        return [
            self::CONTENT_ITEM_FAMILY => 'default_content_item',
            self::CONTENT_GROUP_FAMILY => 'default_content_group',
            self::CONTRIBUTOR_FAMILY => 'default_contributor',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function defaultTemplates(): array
    {
        return [
            self::defaultContentItemTemplate(),
            self::defaultContentGroupTemplate(),
            self::defaultContributorTemplate(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function defaultTemplatesForFamily(string $family): array
    {
        return collect(self::defaultTemplates())
            ->where('family', $family)
            ->values()
            ->all();
    }

    public static function defaultTemplateForFamily(string $family): array
    {
        $defaultKey = self::defaultTemplateKeys()[$family] ?? self::defaultTemplateKeys()[self::CONTENT_ITEM_FAMILY];

        return collect(self::defaultTemplates())
            ->firstWhere('key', $defaultKey)
            ?? self::defaultContentItemTemplate();
    }

    public static function isValidAttributeForSource(?string $source, ?string $attribute): bool
    {
        if ($source === null && $attribute === null) {
            return true;
        }

        if ($source === null || $attribute === null) {
            return false;
        }

        return in_array($attribute, self::attributesForSource($source), true);
    }

    /**
     * @return array<string, string>
     */
    public static function translatedOptions(array $values, string $translationGroup): array
    {
        return collect($values)
            ->mapWithKeys(fn (string $value): array => [$value => __("{$translationGroup}.{$value}")])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultContentItemTemplate(): array
    {
        return [
            'key' => self::defaultTemplateKeys()[self::CONTENT_ITEM_FAMILY],
            'label' => 'Default content item card',
            'family' => self::CONTENT_ITEM_FAMILY,
            'layout' => 'cards',
            'density' => 'comfortable',
            'image_size' => 'medium',
            'title_size' => 'base',
            'parts' => [
                self::part('image', 'content_item', 'image', 10, layout: 'stacked'),
                self::part('group_identity', 'content_group', 'identity', 20, layout: 'badge'),
                self::part('title', 'content_item', 'title', 30, fontSize: 'base', urlTarget: 'self'),
                self::part('description', 'content_item', 'description', 40, lineClamp: 3),
                self::part('transcriber_line', 'transcription', 'author_name', 50, layout: 'badge'),
                self::part('date_read_time', 'transcription', 'published_at', 60, layout: 'inline'),
                self::part('metadata_row', 'content_item', 'duration', 70, layout: 'inline'),
                self::part('taxonomy', 'categories', 'links', 80, layout: 'chips'),
                self::part('taxonomy', 'tags', 'links', 90, layout: 'chips'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultContentGroupTemplate(): array
    {
        return [
            'key' => self::defaultTemplateKeys()[self::CONTENT_GROUP_FAMILY],
            'label' => 'Default content group card',
            'family' => self::CONTENT_GROUP_FAMILY,
            'layout' => 'cards',
            'density' => 'comfortable',
            'image_size' => 'medium',
            'title_size' => 'base',
            'parts' => [
                self::part('image', 'content_group', 'image', 10, layout: 'stacked'),
                self::part('entity_attribute', 'content_group', 'type_label', 20, layout: 'badge'),
                self::part('title', 'content_group', 'title', 30, fontSize: 'base', urlTarget: 'self'),
                self::part('description', 'content_group', 'description', 40, lineClamp: 3),
                self::part('metadata_row', 'content_group', 'item_count', 50, layout: 'inline'),
                self::part('action_link', 'content_group', 'url', 60, layout: 'link', urlTarget: 'self'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultContributorTemplate(): array
    {
        return [
            'key' => self::defaultTemplateKeys()[self::CONTRIBUTOR_FAMILY],
            'label' => 'Default contributor card',
            'family' => self::CONTRIBUTOR_FAMILY,
            'layout' => 'cards',
            'density' => 'comfortable',
            'image_size' => 'hidden',
            'title_size' => 'base',
            'parts' => [
                self::part('title', 'author', 'name', 10, fontSize: 'base', urlTarget: 'self'),
                self::part('metadata_row', 'author', 'transcription_count', 20, layout: 'badge'),
                self::part('metadata_row', 'author', 'content_item_count', 30, layout: 'badge'),
                self::part('description', 'author', 'bio', 40, lineClamp: 3),
                self::part('action_link', 'author', 'url', 50, layout: 'link', urlTarget: 'self'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function part(
        string $type,
        ?string $source,
        ?string $attribute,
        int $order,
        string $layout = 'inline',
        ?int $lineClamp = null,
        ?string $fontSize = null,
        ?string $urlTarget = null,
    ): array {
        return array_filter([
            'type' => $type,
            'source' => $source,
            'attribute' => $attribute,
            'visible' => true,
            'order' => $order,
            'layout' => $layout,
            'line_clamp' => $lineClamp,
            'font_size' => $fontSize,
            'url_target' => $urlTarget,
        ], fn (mixed $value): bool => $value !== null);
    }
}
