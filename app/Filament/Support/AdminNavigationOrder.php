<?php

namespace App\Filament\Support;

use App\Filament\Pages\AdminTools;
use App\Filament\Pages\AdminUxSettings;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ImporterSettings;
use App\Filament\Pages\ManagePublicForms;
use App\Filament\Pages\PublicContentSettings;
use App\Filament\Pages\SpotifyLinksFetcher;
use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentTags\ContentTagResource;
use App\Filament\Resources\HomepageSections\HomepageSectionResource;
use App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource;
use App\Filament\Resources\SettingsBackups\SettingsBackupResource;
use App\Filament\Resources\Transcriptions\TranscriptionResource;
use Awcodes\Curator\Resources\Media\MediaResource;
use Filament\Navigation\NavigationGroup;
use Filament\Support\Icons\Heroicon;

class AdminNavigationOrder
{
    public const CONTENT_MANAGEMENT = 'content_management';

    public const TAXONOMY_MANAGEMENT = 'taxonomy_management';

    public const SITE_MANAGEMENT = 'site_management';

    public const EPISODE_WORKSPACE_CREATE_SORT = 10;

    /**
     * @var array<string, array{label: string, icon: Heroicon}>
     */
    private const GROUPS = [
        self::CONTENT_MANAGEMENT => [
            'label' => 'admin.navigation.groups.content_management',
            'icon' => Heroicon::OutlinedFolderOpen,
        ],
        self::TAXONOMY_MANAGEMENT => [
            'label' => 'admin.navigation.groups.taxonomy_management',
            'icon' => Heroicon::OutlinedTag,
        ],
        self::SITE_MANAGEMENT => [
            'label' => 'admin.navigation.groups.site_management',
            'icon' => Heroicon::OutlinedCog6Tooth,
        ],
    ];

    /**
     * @var array<class-string, array{sort: int, group: string|null, badge_deferred?: bool}>
     */
    private const ITEMS = [
        Dashboard::class => [
            'sort' => 0,
            'group' => null,
        ],
        ContentGroupResource::class => [
            'sort' => 100,
            'group' => self::CONTENT_MANAGEMENT,
        ],
        ContentItemResource::class => [
            'sort' => 110,
            'group' => self::CONTENT_MANAGEMENT,
        ],
        TranscriptionResource::class => [
            'sort' => 120,
            'group' => self::CONTENT_MANAGEMENT,
        ],
        AuthorResource::class => [
            'sort' => 200,
            'group' => self::TAXONOMY_MANAGEMENT,
        ],
        CategoryResource::class => [
            'sort' => 210,
            'group' => self::TAXONOMY_MANAGEMENT,
        ],
        ContentTagResource::class => [
            'sort' => 220,
            'group' => self::TAXONOMY_MANAGEMENT,
        ],
        HomepageSectionResource::class => [
            'sort' => 300,
            'group' => self::SITE_MANAGEMENT,
        ],
        PublicContentSettings::class => [
            'sort' => 310,
            'group' => self::SITE_MANAGEMENT,
        ],
        ManagePublicForms::class => [
            'sort' => 315,
            'group' => self::SITE_MANAGEMENT,
        ],
        AdminUxSettings::class => [
            'sort' => 320,
            'group' => self::SITE_MANAGEMENT,
        ],
        SettingsBackupResource::class => [
            'sort' => 330,
            'group' => self::SITE_MANAGEMENT,
        ],
        AdminTools::class => [
            'sort' => 335,
            'group' => self::SITE_MANAGEMENT,
        ],
        ImporterSettings::class => [
            'sort' => 340,
            'group' => self::SITE_MANAGEMENT,
        ],
        SpotifyLinksFetcher::class => [
            'sort' => 345,
            'group' => self::SITE_MANAGEMENT,
        ],
        PublicFormSubmissionResource::class => [
            'sort' => 20,
            'group' => null,
            'badge_deferred' => true,
        ],
        MediaResource::class => [
            'sort' => 30,
            'group' => null,
        ],
    ];

    public static function sort(string $class): ?int
    {
        return self::ITEMS[$class]['sort'] ?? null;
    }

    public static function episodeWorkspaceCreateSort(): int
    {
        return self::EPISODE_WORKSPACE_CREATE_SORT;
    }

    public static function group(string $class): ?string
    {
        $group = self::groupKey($class);

        return $group ? self::groupLabel($group) : null;
    }

    public static function groupKey(string $class): ?string
    {
        return self::ITEMS[$class]['group'] ?? null;
    }

    public static function groupLabel(string $group): string
    {
        return __(self::GROUPS[$group]['label']);
    }

    public static function hasDeferredBadge(string $class): bool
    {
        return self::ITEMS[$class]['badge_deferred'] ?? false;
    }

    /**
     * @return array<string, NavigationGroup>
     */
    public static function panelNavigationGroups(): array
    {
        return collect(array_keys(self::GROUPS))
            ->mapWithKeys(fn (string $group): array => [
                $group => NavigationGroup::make(fn (): string => self::groupLabel($group))
                    ->icon(self::GROUPS[$group]['icon'])
                    ->collapsible(false),
            ])
            ->all();
    }

    public static function has(string $class): bool
    {
        return array_key_exists($class, self::ITEMS);
    }

    /**
     * @return array<class-string, array{sort: int, group: string|null, badge_deferred?: bool}>
     */
    public static function all(): array
    {
        return self::ITEMS;
    }
}
