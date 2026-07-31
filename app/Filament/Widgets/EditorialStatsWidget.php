<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentTags\ContentTagResource;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EditorialStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected ?string $pollingInterval = null;

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $metrics = app(EditorialMetrics::class)->snapshot();

        return [
            Stat::make(__('admin.dashboard.stats.visible'), $metrics['funnel']['visible'])
                ->description(__('admin.dashboard.stats.visible_hint'))
                ->color('success')
                ->url(ContentItemResource::getUrl('index')),
            Stat::make(__('admin.dashboard.stats.blocked'), $metrics['blockers']['total'])
                ->description(__('admin.dashboard.stats.blocked_hint'))
                ->color($metrics['blockers']['total'] > 0 ? 'warning' : 'success')
                ->url(ContentItemResource::getUrl('index')),
            Stat::make(__('admin.dashboard.stats.draft'), $metrics['funnel']['draft'])
                ->color('gray')
                ->url(ContentItemResource::getUrl('index')),
            Stat::make(__('admin.dashboard.stats.pinned'), $metrics['structure']['pinned'])
                ->color('gray')
                ->url(ContentItemResource::getUrl('index')),
            Stat::make(__('admin.dashboard.stats.groups'), $metrics['structure']['groups'])
                ->color('gray')
                ->url(ContentGroupResource::getUrl('index')),
            Stat::make(__('admin.dashboard.stats.authors'), $metrics['structure']['authors'])
                ->color('gray')
                ->url(AuthorResource::getUrl('index')),
            Stat::make(__('admin.dashboard.stats.categories'), $metrics['structure']['categories'])
                ->color('gray')
                ->url(CategoryResource::getUrl('index')),
            Stat::make(
                __('admin.dashboard.stats.tags'),
                __('admin.dashboard.stats.tags_value', [
                    'enabled' => $metrics['structure']['tags_enabled'],
                    'disabled' => $metrics['structure']['tags_disabled'],
                ]),
            )
                ->color('gray')
                ->url(ContentTagResource::getUrl('index')),
            Stat::make(__('admin.dashboard.stats.multi_transcription'), $metrics['structure']['multi_transcription'])
                ->color('gray')
                ->url(ContentItemResource::getUrl('index')),
        ];
    }
}
