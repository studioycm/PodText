<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentTags\ContentTagResource;
use App\Filament\Widgets\Concerns\AdminOnlyWidget;
use App\Filament\Widgets\Concerns\ReadsDashboardFilters;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * The structural counts the merged boards had lost, restored as quiet chips —
 * plus H3 (podcast health breakdown) and H9 (transcriber board, the spec's
 * "transcriptions by author"). The chips describe the whole library, so they
 * ignore the podcast scope by design; H3 and H9 honour it.
 */
class LibraryCompositionWidget extends Widget
{
    use AdminOnlyWidget;
    use InteractsWithPageFilters;
    use ReadsDashboardFilters;

    protected string $view = 'filament.widgets.library-composition';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $metrics = app(EditorialMetrics::class);
        $podcastId = $this->dashboardPodcastId();
        $structure = $metrics->snapshot($podcastId)['structure'];

        return [
            'chips' => [
                ['key' => 'groups', 'value' => $structure['groups'], 'url' => ContentGroupResource::getUrl('index')],
                ['key' => 'authors', 'value' => $structure['authors'], 'url' => AuthorResource::getUrl('index')],
                ['key' => 'categories', 'value' => $structure['categories'], 'url' => CategoryResource::getUrl('index')],
                [
                    'key' => 'tags',
                    'value' => __('admin.dashboard.stats.tags_value', [
                        'enabled' => $structure['tags_enabled'],
                        'disabled' => $structure['tags_disabled'],
                    ]),
                    'url' => ContentTagResource::getUrl('index'),
                ],
                ['key' => 'pinned', 'value' => $structure['pinned'], 'url' => ContentItemResource::getUrl('index')],
                [
                    'key' => 'multi_transcription',
                    'value' => $structure['multi_transcription'],
                    'url' => ContentItemResource::getUrl('index'),
                ],
            ],
            'health' => $metrics->podcastHealth($podcastId),
            'transcribers' => $metrics->transcriberBoard($this->dashboardRange(), $podcastId),
        ];
    }
}
