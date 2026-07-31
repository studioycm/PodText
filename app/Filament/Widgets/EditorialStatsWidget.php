<?php

namespace App\Filament\Widgets;

use App\Enums\DashboardLens;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Widgets\Concerns\ReadsDashboardFilters;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * H5 · composite stat cards. Each card carries the number, a mini composition
 * strip explaining what the number is made of, and a doorway into the Resource
 * table filtered to the same slice.
 */
class EditorialStatsWidget extends Widget
{
    use InteractsWithPageFilters;
    use ReadsDashboardFilters;

    protected string $view = 'filament.widgets.editorial-stats';

    protected static ?int $sort = -30;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public function openBlockers(): void
    {
        $this->dispatch('dashboard-filter', key: 'lens', value: DashboardLens::Blockers->value);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $snapshot = app(EditorialMetrics::class)->snapshot($this->dashboardPodcastId());
        $funnel = $snapshot['funnel'];
        $blockers = $snapshot['blockers'];
        $structure = $snapshot['structure'];

        $items = $structure['items'];
        $visible = $funnel['visible'];
        $notVisible = max(0, $funnel['published'] - $visible);
        $rest = max(0, $items - $visible);

        return [
            'cards' => [
                [
                    'key' => 'items',
                    'value' => $items,
                    'url' => ContentItemResource::getUrl('index', $this->scopedTableFilters()),
                    'segments' => [
                        ['key' => 'visible', 'value' => $visible, 'bar' => 'bg-success-500'],
                        ['key' => 'blocked', 'value' => $notVisible, 'bar' => 'bg-warning-500'],
                        ['key' => 'draft', 'value' => $funnel['draft'], 'bar' => 'bg-gray-400 dark:bg-gray-500'],
                    ],
                ],
                [
                    'key' => 'visible',
                    'value' => $visible,
                    'url' => ContentItemResource::getUrl('index', $this->scopedTableFilters([
                        'status' => ['value' => 'published'],
                    ])),
                    'segments' => [
                        ['key' => 'visible', 'value' => $visible, 'bar' => 'bg-success-500'],
                        ['key' => 'other', 'value' => $rest, 'bar' => 'bg-gray-200 dark:bg-white/10'],
                    ],
                ],
                [
                    'key' => 'blocked',
                    'value' => $blockers['total'],
                    'action' => 'openBlockers',
                    'segments' => [
                        ['key' => 'missing_transcription', 'value' => $blockers['missing_transcription'], 'bar' => 'bg-danger-500'],
                        ['key' => 'missing_media', 'value' => $blockers['missing_media'], 'bar' => 'bg-warning-500'],
                        ['key' => 'missing_category', 'value' => $blockers['missing_category'], 'bar' => 'bg-info-500'],
                    ],
                ],
                [
                    'key' => 'pinned',
                    'value' => $structure['pinned'],
                    'url' => ContentItemResource::getUrl('index', $this->scopedTableFilters([
                        'is_pinned' => ['value' => true],
                    ])),
                    'segments' => [
                        ['key' => 'pinned', 'value' => $structure['pinned'], 'bar' => 'bg-primary-500'],
                        ['key' => 'other', 'value' => max(0, $items - $structure['pinned']), 'bar' => 'bg-gray-200 dark:bg-white/10'],
                    ],
                ],
                [
                    'key' => 'multi_transcription',
                    'value' => $structure['multi_transcription'],
                    'url' => ContentItemResource::getUrl('index', $this->scopedTableFilters()),
                    'segments' => [
                        ['key' => 'multi_transcription', 'value' => $structure['multi_transcription'], 'bar' => 'bg-info-500'],
                        ['key' => 'other', 'value' => max(0, $items - $structure['multi_transcription']), 'bar' => 'bg-gray-200 dark:bg-white/10'],
                    ],
                ],
            ],
        ];
    }
}
