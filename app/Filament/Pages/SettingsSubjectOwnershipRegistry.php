<?php

namespace App\Filament\Pages;

use InvalidArgumentException;

final class SettingsSubjectOwnershipRegistry
{
    public const HOMEPAGE = 'homepage';

    public const DISPLAY = 'display';

    public const EPISODE_PAGE = 'episode-page';

    public const MENU_HEADER = 'menu-header';

    public const PODCASTS = 'podcasts';

    public const CONTRIBUTORS = 'contributors';

    public const ABOUT = 'about';

    public const MAINTENANCE = 'maintenance';

    public const PUBLIC_FORMS = 'public-forms';

    public const CARD_TEMPLATES = 'card-templates';

    /**
     * @var array<string, array{properties: array<int, string>, validator_groups: array<int, string>, editable: bool}>
     */
    private const SUBJECTS = [
        self::HOMEPAGE => [
            'properties' => ['homepage_item_limit', 'pinned_item_limit', 'show_latest_section'],
            'validator_groups' => [],
            'editable' => true,
        ],
        self::DISPLAY => [
            'properties' => [
                'default_public_sort',
                'default_result_layout',
                'homepage_card_image_size',
                'homepage_card_density',
                'homepage_card_title_size',
                'homepage_card_image_fit',
                'homepage_card_image_radius',
                'homepage_show_group_badge',
                'homepage_group_badge_mode',
                'homepage_group_title_separator',
                'homepage_group_badge_duplicate_thumbnail',
                'homepage_show_authors',
                'homepage_show_categories',
                'homepage_show_tags',
                'homepage_show_duration',
                'homepage_show_effective_date',
                'homepage_show_description',
                'homepage_description_lines',
                'homepage_cards_per_page',
                'display_defaults',
                'default_images',
                'transcription_policy',
            ],
            'validator_groups' => ['display_defaults', 'default_images', 'transcription_policy'],
            'editable' => true,
        ],
        self::EPISODE_PAGE => [
            'properties' => ['item_page_layout', 'item_page'],
            'validator_groups' => ['item_page'],
            'editable' => true,
        ],
        self::MENU_HEADER => [
            'properties' => ['menu_config', 'route_labels'],
            'validator_groups' => ['menu_config', 'route_labels'],
            'editable' => true,
        ],
        self::PODCASTS => [
            'properties' => ['podcasts_page'],
            'validator_groups' => ['podcasts_page'],
            'editable' => true,
        ],
        self::CONTRIBUTORS => [
            'properties' => ['contributors_page'],
            'validator_groups' => ['contributors_page'],
            'editable' => true,
        ],
        self::ABOUT => [
            'properties' => ['about_page'],
            'validator_groups' => ['about_page'],
            'editable' => true,
        ],
        self::MAINTENANCE => [
            'properties' => ['maintenance'],
            'validator_groups' => ['maintenance'],
            'editable' => true,
        ],
        self::PUBLIC_FORMS => [
            'properties' => ['public_forms'],
            'validator_groups' => ['public_forms'],
            'editable' => true,
        ],
        self::CARD_TEMPLATES => [
            'properties' => ['card_templates'],
            'validator_groups' => ['card_templates'],
            'editable' => true,
        ],
        'import-locks' => [
            'properties' => ['import_locks'],
            'validator_groups' => ['import_locks'],
            'editable' => false,
        ],
        'settings-backups' => [
            'properties' => ['settings_backups'],
            'validator_groups' => ['settings_backups'],
            'editable' => false,
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function ownedProperties(string $subject): array
    {
        return self::definition($subject)['properties'];
    }

    /**
     * @return array<int, string>
     */
    public static function validatorGroups(string $subject): array
    {
        return self::definition($subject)['validator_groups'];
    }

    public static function isEditable(string $subject): bool
    {
        return self::definition($subject)['editable'];
    }

    /**
     * @return array<string, array{properties: array<int, string>, validator_groups: array<int, string>, editable: bool}>
     */
    public static function all(): array
    {
        return self::SUBJECTS;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function extractOwned(array $settings, string $subject): array
    {
        $owned = [];

        foreach (self::ownedProperties($subject) as $property) {
            if (array_key_exists($property, $settings)) {
                $owned[$property] = $settings[$property];
            }
        }

        return $owned;
    }

    /**
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $owned
     * @return array<string, mixed>
     */
    public static function overlayOwned(array $stored, array $owned, string $subject): array
    {
        foreach (self::ownedProperties($subject) as $property) {
            if (array_key_exists($property, $owned)) {
                $stored[$property] = $owned[$property];
            }
        }

        return $stored;
    }

    /**
     * @return array{properties: array<int, string>, validator_groups: array<int, string>, editable: bool}
     */
    private static function definition(string $subject): array
    {
        if (! array_key_exists($subject, self::SUBJECTS)) {
            throw new InvalidArgumentException("Unknown settings subject [{$subject}].");
        }

        return self::SUBJECTS[$subject];
    }
}
