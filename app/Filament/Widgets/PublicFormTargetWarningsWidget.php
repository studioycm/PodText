<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AboutSettings;
use App\Filament\Pages\MenuHeaderSettings;
use App\Filament\Resources\HomepageSections\HomepageSectionResource;
use App\Filament\Widgets\Concerns\AdminOnlyWidget;
use App\Filament\Widgets\Concerns\ShowsLoadingSkeleton;
use App\Support\PublicFront\PublicFormTargetStatus;
use Filament\Widgets\Widget;

/**
 * Editorial warning: visible public-form CTAs whose form key has no enabled
 * definition. The public side skips those CTAs silently by design, so this
 * widget is the admin-side signal that the skip is happening. It hides itself
 * when every CTA target resolves.
 */
class PublicFormTargetWarningsWidget extends Widget
{
    use AdminOnlyWidget {
        canView as private canViewAsAdmin;
    }
    use ShowsLoadingSkeleton;

    protected string $view = 'filament.widgets.public-form-target-warnings';

    protected static ?int $sort = -40;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return self::canViewAsAdmin()
            && app(PublicFormTargetStatus::class)->misconfiguredCounts()['total'] > 0;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $counts = app(PublicFormTargetStatus::class)->misconfiguredCounts();

        $rows = collect([
            [
                'key' => 'menu_items',
                'count' => $counts['menu_items'],
                'label' => __('admin.dashboard.form_targets.menu_items'),
                'url' => MenuHeaderSettings::getUrl(),
                'link_label' => __('admin.dashboard.form_targets.open_menu_settings'),
            ],
            [
                'key' => 'about_blocks',
                'count' => $counts['about_blocks'],
                'label' => __('admin.dashboard.form_targets.about_blocks'),
                'url' => AboutSettings::getUrl(),
                'link_label' => __('admin.dashboard.form_targets.open_about_settings'),
            ],
            [
                'key' => 'homepage_buttons',
                'count' => $counts['homepage_buttons'],
                'label' => __('admin.dashboard.form_targets.homepage_buttons'),
                'url' => HomepageSectionResource::getUrl('index'),
                'link_label' => __('admin.dashboard.form_targets.open_homepage_sections'),
            ],
        ])
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->values()
            ->all();

        return [
            'rows' => $rows,
        ];
    }
}
