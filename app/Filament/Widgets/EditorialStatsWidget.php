<?php

namespace App\Filament\Widgets;

use App\Enums\DashboardLens;
use App\Enums\DashboardReason;
use App\Enums\DashboardTier;
use App\Enums\FunnelStage;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Widgets\Concerns\AdminOnlyWidget;
use App\Filament\Widgets\Concerns\ReadsDashboardFilters;
use App\Filament\Widgets\Concerns\ShowsLoadingSkeleton;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * H5 · composite stat cards. Each card carries the number, a mini composition
 * strip explaining what the number is made of, and a doorway into the Resource
 * table filtered to the same slice. Doorway icons come from the subject's
 * enum home where one exists (funnel stage, tier); the rest wear the funnel
 * icon, since their doorway opens a filtered list.
 */
class EditorialStatsWidget extends Widget
{
    use AdminOnlyWidget;
    use InteractsWithPageFilters;
    use ReadsDashboardFilters;
    use ShowsLoadingSkeleton;

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
        $gap = $snapshot['gap'];
        $attention = $snapshot['attention'];
        $structure = $snapshot['structure'];

        $items = $structure['items'];
        $visible = $funnel['visible'];
        $notVisible = $gap['invisible'];
        $rest = max(0, $items - $visible);

        return [
            'cards' => [
                [
                    'key' => 'items',
                    'value' => $items,
                    'icon' => Heroicon::OutlinedFunnel,
                    'url' => ContentItemResource::getUrl('index', $this->scopedTableFilters()),
                    'segments' => [
                        ['key' => 'visible', 'value' => $visible, 'bar' => FunnelStage::Visible->barClass()],
                        // Same number as the funnel's gap, so the same colour:
                        // the invisible tier is danger, never warning.
                        ['key' => 'blocked', 'value' => $notVisible, 'bar' => DashboardReason::MissingTranscription->barClass()],
                        ['key' => 'draft', 'value' => $funnel['draft'], 'bar' => FunnelStage::Draft->barClass()],
                    ],
                ],
                [
                    'key' => 'visible',
                    'value' => $visible,
                    'icon' => FunnelStage::Visible->getIcon(),
                    'url' => ContentItemResource::getUrl('index', $this->scopedTableFilters([
                        'status' => ['value' => 'published'],
                    ])),
                    'segments' => [
                        ['key' => 'visible', 'value' => $visible, 'bar' => FunnelStage::Visible->barClass()],
                        ['key' => 'other', 'value' => $rest, 'bar' => 'bg-gray-200 dark:bg-white/10'],
                    ],
                ],
                // Two tiers, never merged: invisible is what the public cannot
                // see; needs-attention episodes may well be visible already.
                [
                    'key' => 'invisible',
                    'value' => $gap['invisible'],
                    'icon' => DashboardTier::Invisible->getIcon(),
                    'action' => 'openBlockers',
                    'segments' => [
                        ['key' => DashboardReason::MissingTranscription->value, 'value' => $gap['missing_transcription'], 'bar' => DashboardReason::MissingTranscription->barClass()],
                        ['key' => DashboardReason::UnpublishedGroup->value, 'value' => $gap['unpublished_group'], 'bar' => DashboardReason::UnpublishedGroup->barClass()],
                    ],
                ],
                [
                    'key' => 'attention',
                    'value' => $attention['total'],
                    'icon' => DashboardTier::Attention->getIcon(),
                    'action' => 'openBlockers',
                    'segments' => [
                        ['key' => DashboardReason::MissingMedia->value, 'value' => $attention['missing_media'], 'bar' => DashboardReason::MissingMedia->barClass()],
                        ['key' => DashboardReason::MissingCategory->value, 'value' => $attention['missing_category'], 'bar' => DashboardReason::MissingCategory->barClass()],
                    ],
                ],
                [
                    'key' => 'pinned',
                    'value' => $structure['pinned'],
                    'icon' => Heroicon::OutlinedStar,
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
                    'icon' => Heroicon::OutlinedDocumentDuplicate,
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
