<?php

namespace App\Filament\Support;

use App\Filament\Clusters\SettingsCluster;
use App\Filament\Clusters\SystemCluster;
use App\Filament\Pages\AboutSettings;
use App\Filament\Pages\AdminTools;
use App\Filament\Pages\AdminUxSettings;
use App\Filament\Pages\CardTemplateSettings;
use App\Filament\Pages\ContributorSettings;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\DisplaySettings;
use App\Filament\Pages\EpisodePageSettings;
use App\Filament\Pages\HomepageSettings;
use App\Filament\Pages\ImporterSettings;
use App\Filament\Pages\MaintenanceSettings;
use App\Filament\Pages\ManagePublicForms;
use App\Filament\Pages\MenuHeaderSettings;
use App\Filament\Pages\PodcastSettings;
use App\Filament\Pages\SpotifyLinksFetcher;
use App\Filament\Public\Pages\BrowseContentGroups;
use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentTags\ContentTagResource;
use App\Filament\Resources\HomepageSections\HomepageSectionResource;
use App\Filament\Resources\Imports\ImportResource;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource;
use App\Filament\Resources\SettingsBackups\SettingsBackupResource;
use App\Filament\Resources\Transcriptions\TranscriptionResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Support\Icons\Heroicon;

class AdminNavigationOrder
{
    public const CONTENT_MANAGEMENT = 'content_management';

    public const TAXONOMY_MANAGEMENT = 'taxonomy_management';

    public const TOOLS_AND_SYSTEM = 'tools_and_system';

    public const EPISODE_WORKSPACE_CREATE_SORT = 10;

    /**
     * Group order is the sidebar order. Episodes left this block for the
     * ungrouped front door (EQ-1, 2026-08-05), so taxonomy — the more
     * frequently opened of the two survivors — leads, and the content group
     * (podcasts + transcripts) trails, collapsed by default.
     *
     * The icons are not decoration: with `sidebarCollapsibleOnDesktop()`, a
     * group without an icon loses its label and spills its items when the
     * sidebar collapses; with one, it becomes a single icon opening a
     * dropdown.
     *
     * @var array<string, array{label: string, icon: Heroicon, collapsed?: bool}>
     */
    private const GROUPS = [
        self::TAXONOMY_MANAGEMENT => [
            'label' => 'admin.navigation.groups.taxonomy_management',
            'icon' => Heroicon::OutlinedTag,
        ],
        self::CONTENT_MANAGEMENT => [
            'label' => 'admin.navigation.groups.content_management',
            'icon' => Heroicon::OutlinedRectangleGroup,
            'collapsed' => true,
        ],
        // The tools block used to be a second ungrouped run, placed after the
        // labelled groups by a hand-written builder. Filament sorts ungrouped
        // items first with no override, so keeping that position natively
        // means giving the block a name — and an icon, which an icon-less
        // group needs anyway or it spills its items when the sidebar
        // collapses.
        self::TOOLS_AND_SYSTEM => [
            'label' => 'admin.navigation.groups.tools_and_system',
            'icon' => Heroicon::OutlinedWrenchScrewdriver,
        ],
    ];

    /**
     * @var array<class-string, array{sort: int, group: string|null}>
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
        // The front door (EQ-1): episodes lead the ungrouped block, directly
        // under «פרק חדש», above the form submissions and media items.
        ContentItemResource::class => [
            'sort' => 15,
            'group' => null,
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
        HomepageSettings::class => [
            'sort' => 300,
            'group' => null,
        ],
        HomepageSectionResource::class => [
            'sort' => 310,
            'group' => null,
        ],
        PodcastSettings::class => [
            'sort' => 320,
            'group' => null,
        ],
        EpisodePageSettings::class => [
            'sort' => 330,
            'group' => null,
        ],
        ContributorSettings::class => [
            'sort' => 340,
            'group' => null,
        ],
        AboutSettings::class => [
            'sort' => 350,
            'group' => null,
        ],
        DisplaySettings::class => [
            'sort' => 360,
            'group' => null,
        ],
        MenuHeaderSettings::class => [
            'sort' => 370,
            'group' => null,
        ],
        MaintenanceSettings::class => [
            'sort' => 300,
            'group' => null,
        ],
        UserResource::class => [
            'sort' => 310,
            'group' => null,
        ],
        ImporterSettings::class => [
            'sort' => 320,
            'group' => null,
        ],
        ImportResource::class => [
            'sort' => 325,
            'group' => null,
        ],
        ManagePublicForms::class => [
            'sort' => 380,
            'group' => null,
        ],
        CardTemplateSettings::class => [
            'sort' => 390,
            'group' => null,
        ],
        SettingsBackupResource::class => [
            'sort' => 350,
            'group' => null,
        ],
        AdminUxSettings::class => [
            'sort' => 360,
            'group' => null,
        ],
        PublicFormSubmissionResource::class => [
            'sort' => 20,
            'group' => null,
        ],
        MediaResource::class => [
            'sort' => 30,
            'group' => null,
        ],
        AdminTools::class => [
            'sort' => 40,
            'group' => self::TOOLS_AND_SYSTEM,
        ],
        SpotifyLinksFetcher::class => [
            'sort' => 50,
            'group' => self::TOOLS_AND_SYSTEM,
        ],
        SettingsCluster::class => [
            'sort' => 52,
            'group' => self::TOOLS_AND_SYSTEM,
        ],
        SystemCluster::class => [
            'sort' => 54,
            'group' => self::TOOLS_AND_SYSTEM,
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

    public static function groupIcon(string $group): Heroicon
    {
        return self::GROUPS[$group]['icon'];
    }

    public static function isGroupCollapsed(string $group): bool
    {
        return self::GROUPS[$group]['collapsed'] ?? false;
    }

    /**
     * @return array<int, NavigationGroup>
     */
    public static function panelNavigationGroups(): array
    {
        return collect(array_keys(self::GROUPS))
            ->map(function (string $group): NavigationGroup {
                $navigationGroup = NavigationGroup::make(fn (): string => self::groupLabel($group))
                    ->icon(self::groupIcon($group))
                    ->collapsible();

                // collapsed() also *sets* collapsibility from the same value in
                // Filament 5, so passing false would strip an expanded group's
                // toggle. Only the collapsed groups say it.
                if (self::isGroupCollapsed($group)) {
                    $navigationGroup->collapsed();
                }

                return $navigationGroup;
            })
            ->all();
    }

    /**
     * Items that belong to no resource or page — currently the one link out
     * of the panel, which rides in the tools group with the rest of the block
     * it has always sat in.
     *
     * @return array<int, NavigationItem>
     */
    public static function panelNavigationItems(): array
    {
        return [
            NavigationItem::make(fn (): string => __('admin.navigation.public_homepage'))
                ->group(fn (): string => self::groupLabel(self::TOOLS_AND_SYSTEM))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->sort(60)
                ->url(fn (): string => BrowseContentGroups::getUrl(panel: 'public'), shouldOpenInNewTab: true),
        ];
    }

    public static function has(string $class): bool
    {
        return array_key_exists($class, self::ITEMS);
    }

    /**
     * @return array<class-string, array{sort: int, group: string|null}>
     */
    public static function all(): array
    {
        return self::ITEMS;
    }
}
