<?php

namespace App\Support\Dashboard;

use App\Enums\DashboardRange;
use App\Enums\PublicationStatus;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource;
use App\Filament\Resources\Transcriptions\TranscriptionResource;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\MediaAttachment;
use App\Models\PublicFormSubmission;
use App\Models\Transcription;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The single source of truth for every number on the dashboard: any figure
 * that appears in more than one widget reads from this service, so widgets
 * cannot silently disagree. Bounded cache instead of polling.
 *
 * Item-derived numbers accept an optional podcast scope. Library-wide counts
 * (categories, tags, podcasts, transcribers) stay global by design — the
 * composition band describes the library, not the selected podcast.
 */
class EditorialMetrics
{
    private const CACHE_SECONDS = 60;

    private const CACHE_PREFIX = 'dashboard:editorial-metrics:v2';

    private const TIMEZONE = 'Asia/Jerusalem';

    /** @var array<int, bool> */
    private array $knownPodcasts = [];

    /**
     * @return array{
     *     funnel: array{draft: int, published: int, transcribed: int, visible: int},
     *     blockers: array{missing_transcription: int, missing_media: int, missing_category: int, total: int},
     *     structure: array{items: int, groups: int, authors: int, categories: int, tags_enabled: int, tags_disabled: int, pinned: int, multi_transcription: int},
     *     generated_at: string
     * }
     */
    public function snapshot(?int $contentGroupId = null): array
    {
        return Cache::remember($this->cacheKey($contentGroupId), self::CACHE_SECONDS, fn (): array => [
            'funnel' => [
                'draft' => $this->scoped(ContentItem::query(), $contentGroupId)
                    ->where('status', PublicationStatus::Draft)
                    ->count(),
                'published' => $this->statusPublished($contentGroupId)->count(),
                'transcribed' => $this->statusPublished($contentGroupId)
                    ->whereHas('transcriptions', fn (Builder $query): Builder => $query->published())
                    ->count(),
                'visible' => $this->visible($contentGroupId)->count(),
            ],
            'blockers' => [
                'missing_transcription' => $this->missingTranscription($contentGroupId)->count(),
                'missing_media' => $this->missingMedia($contentGroupId)->count(),
                'missing_category' => $this->missingCategory($contentGroupId)->count(),
                'total' => $this->blockedQuery($contentGroupId)->count(),
            ],
            'structure' => [
                'items' => $this->scoped(ContentItem::query(), $contentGroupId)->count(),
                'groups' => ContentGroup::query()->count(),
                'authors' => Author::query()->count(),
                'categories' => Category::query()->count(),
                'tags_enabled' => ContentTag::query()->content()->enabled()->count(),
                'tags_disabled' => ContentTag::query()->content()->where('is_enabled', false)->count(),
                'pinned' => $this->scoped(ContentItem::query(), $contentGroupId)->where('is_pinned', true)->count(),
                'multi_transcription' => $this->scoped(ContentItem::query(), $contentGroupId)
                    ->whereHas('transcriptions', operator: '>', count: 1)
                    ->count(),
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function forget(): void
    {
        Cache::forget($this->cacheKey(null));

        ContentGroup::query()
            ->pluck('id')
            ->each(fn (int $id): bool => Cache::forget($this->cacheKey($id)));
    }

    /**
     * Daily movement through each funnel stage, aligned to the range's
     * Jerusalem day keys. Draft counts episodes added, published counts
     * publication dates, transcribed counts transcripts published, and visible
     * counts publication dates of episodes the public can currently see.
     *
     * @return array{draft: array<int, int>, published: array<int, int>, transcribed: array<int, int>, visible: array<int, int>}
     */
    public function funnelSeries(DashboardRange $range, ?int $contentGroupId = null): array
    {
        return [
            'draft' => array_values($this->dailyMap($this->scoped(ContentItem::query(), $contentGroupId), 'created_at', $range)),
            'published' => array_values($this->publicationsPerDay($range, $contentGroupId)),
            'transcribed' => array_values($this->dailyMap($this->publishedTranscriptions($contentGroupId), 'published_at', $range)),
            'visible' => array_values($this->dailyMap($this->visible($contentGroupId), 'published_at', $range)),
        ];
    }

    /**
     * The same publication flow the funnel's published segment reports, laid
     * out as a calendar.
     *
     * @return array<string, int>
     */
    public function publicationHeatmap(DashboardRange $range, ?int $contentGroupId = null): array
    {
        return $this->publicationsPerDay($range, $contentGroupId);
    }

    /**
     * Per-podcast publication health: how much of what a podcast published is
     * actually visible, and how much is stuck.
     *
     * @return array<int, array{id: int, label: string, total: int, visible: int, blocked: int, percent: int, url: string}>
     */
    public function podcastHealth(?int $contentGroupId = null, int $limit = 6): array
    {
        $totals = $this->countsByGroup($this->statusPublished($contentGroupId));
        $visible = $this->countsByGroup($this->visible($contentGroupId));

        return ContentGroup::query()
            ->whereKey($totals->keys())
            ->get(['id', 'title'])
            ->map(function (ContentGroup $group) use ($totals, $visible): array {
                $total = (int) $totals->get($group->getKey(), 0);
                $visibleCount = (int) $visible->get($group->getKey(), 0);

                return [
                    'id' => $group->getKey(),
                    'label' => (string) $group->title,
                    'total' => $total,
                    'visible' => $visibleCount,
                    'blocked' => $total - $visibleCount,
                    'percent' => $total > 0 ? (int) round(($visibleCount / $total) * 100) : 0,
                    'url' => ContentItemResource::getUrl('index', [
                        'tableFilters' => ['content_group_id' => ['value' => $group->getKey()]],
                    ]),
                ];
            })
            ->sortByDesc('total')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * The spec's "transcriptions by author": who published transcripts in the
     * range, how many words, and how that compares with the previous period.
     * A multi-transcriber transcript counts in full for each of its
     * transcribers.
     *
     * @return array<int, array{id: int, label: string, transcriptions: int, words: int, previous: int, url: string}>
     */
    public function transcriberBoard(DashboardRange $range, ?int $contentGroupId = null, int $limit = 6): array
    {
        [$currentStart, $currentEnd] = $range->currentPeriod();
        [$previousStart, $previousEnd] = $range->previousPeriod();

        $current = $this->transcriptionsByTranscriber($currentStart, $currentEnd, $contentGroupId);
        $previous = $this->transcriptionsByTranscriber($previousStart, $previousEnd, $contentGroupId);

        return $current
            ->map(fn (array $row, int $authorId): array => [
                'id' => $authorId,
                'label' => $row['label'],
                'transcriptions' => $row['transcriptions'],
                'words' => $row['words'],
                'previous' => (int) ($previous[$authorId]['transcriptions'] ?? 0),
                'url' => TranscriptionResource::getUrl('index', [
                    'tableFilters' => ['transcriber_id' => ['value' => $authorId]],
                ]),
            ])
            ->sortByDesc('transcriptions')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * The typed editorial event stream. `$type` is a legend/chip value and
     * `$day` is a Jerusalem `Y-m-d` key coming from a heatmap cell.
     *
     * @return array<int, array{type: string, title: string, subtitle: ?string, url: ?string, at: Carbon}>
     */
    public function activityStream(
        DashboardRange $range,
        ?string $type = null,
        ?string $day = null,
        ?int $contentGroupId = null,
        int $limit = 12,
    ): array {
        [$start, $end] = $range->currentPeriod();

        $events = collect()
            ->when($this->wants($type, 'transcription'), fn (Collection $events): Collection => $events
                ->concat($this->transcriptionEvents($start, $end, $contentGroupId, $limit)))
            ->when($this->wants($type, 'media'), fn (Collection $events): Collection => $events
                ->concat($this->mediaEvents($start, $end, $contentGroupId, $limit)))
            ->when($this->wants($type, 'import'), fn (Collection $events): Collection => $events
                ->concat($this->importEvents($start, $end, $limit)))
            ->when($this->wants($type, 'submission'), fn (Collection $events): Collection => $events
                ->concat($this->submissionEvents($start, $end, $limit)));

        return $events
            ->when(filled($day), fn (Collection $events): Collection => $events->filter(
                fn (array $event): bool => $event['at']->copy()->timezone(self::TIMEZONE)->format('Y-m-d') === $day,
            ))
            ->sortByDesc(fn (array $event): int => $event['at']->getTimestamp())
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * H7's finish line: how many blocked episodes remain out of everything
     * that is status-published.
     *
     * @return array{remaining: int, total: int}
     */
    public function blockersProgress(?int $contentGroupId = null): array
    {
        $snapshot = $this->snapshot($contentGroupId);

        return [
            'remaining' => $snapshot['blockers']['total'],
            'total' => $snapshot['funnel']['published'],
        ];
    }

    /** @return array<int, string> */
    public function podcastOptions(): array
    {
        return ContentGroup::query()
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * Guard for the unvalidated `podcast` page filter. Memoized because every
     * widget on the board asks, and the service is request-scoped.
     */
    public function podcastExists(int $contentGroupId): bool
    {
        return $this->knownPodcasts[$contentGroupId] ??= ContentGroup::query()
            ->whereKey($contentGroupId)
            ->exists();
    }

    /**
     * Status-published items carrying at least one publication blocker —
     * the queue population behind the blockers lens.
     *
     * @return Builder<ContentItem>
     */
    public function blockedQuery(?int $contentGroupId = null): Builder
    {
        return $this->statusPublished($contentGroupId)->where(function (Builder $query): void {
            $query
                ->whereDoesntHave('transcriptions', fn (Builder $inner): Builder => $inner->published())
                ->orWhere(fn (Builder $inner): Builder => $this->applyMissingMedia($inner))
                ->orWhere(fn (Builder $inner) => $this->applyMissingCategory($inner));
        });
    }

    /** @return array<int, string> */
    public function blockerReasonsFor(ContentItem $item): array
    {
        $reasons = [];

        if (! $item->transcriptions()->published()->exists()) {
            $reasons[] = 'missing_transcription';
        }

        if (blank($item->embed_url) && blank($item->media_url)) {
            $reasons[] = 'missing_media';
        }

        if ($item->categories()->doesntExist() && $item->contentGroup?->categories()->doesntExist()) {
            $reasons[] = 'missing_category';
        }

        return $reasons;
    }

    public function clearanceForecast(?int $contentGroupId = null): ?Carbon
    {
        $remaining = $this->snapshot($contentGroupId)['blockers']['missing_transcription'];

        if ($remaining < 1) {
            return null;
        }

        $recent = $this->publishedTranscriptions($contentGroupId)
            ->where('published_at', '>=', now()->subDays(14))
            ->count();

        if ($recent < 1) {
            return null;
        }

        return now()->addDays((int) ceil($remaining / ($recent / 14)));
    }

    /**
     * Narrow a blocked-queue query to a single blocker reason, using the same
     * conditions the snapshot counts with.
     *
     * @param  Builder<ContentItem>  $query
     * @return Builder<ContentItem>
     */
    public function applyReason(Builder $query, string $reason): Builder
    {
        return match ($reason) {
            'missing_transcription' => $query->whereDoesntHave('transcriptions', fn (Builder $inner): Builder => $inner->published()),
            'missing_media' => $this->applyMissingMedia($query),
            'missing_category' => $this->applyMissingCategory($query),
            default => $query,
        };
    }

    private function cacheKey(?int $contentGroupId): string
    {
        return self::CACHE_PREFIX.':'.($contentGroupId ?? 'all');
    }

    /**
     * @param  Builder<ContentItem>  $query
     * @return Builder<ContentItem>
     */
    private function scoped(Builder $query, ?int $contentGroupId): Builder
    {
        return $query->when($contentGroupId, fn (Builder $inner): Builder => $inner->where('content_group_id', $contentGroupId));
    }

    /** @return Builder<ContentItem> */
    private function statusPublished(?int $contentGroupId = null): Builder
    {
        return $this->scoped(ContentItem::query(), $contentGroupId)
            ->where('status', PublicationStatus::Published)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    /** @return Builder<ContentItem> */
    private function visible(?int $contentGroupId = null): Builder
    {
        return $this->scoped(ContentItem::query()->published(), $contentGroupId);
    }

    /** @return Builder<Transcription> */
    private function publishedTranscriptions(?int $contentGroupId = null): Builder
    {
        return Transcription::query()
            ->published()
            ->when($contentGroupId, fn (Builder $query): Builder => $query->whereHas(
                'contentItem',
                fn (Builder $inner): Builder => $inner->where('content_group_id', $contentGroupId),
            ));
    }

    /** @return array<string, int> */
    private function publicationsPerDay(DashboardRange $range, ?int $contentGroupId): array
    {
        return $this->dailyMap($this->statusPublished($contentGroupId), 'published_at', $range);
    }

    /**
     * Zero-filled Jerusalem-day counts. Bucketing happens in PHP so the day
     * boundaries stay Jerusalem walls on both MySQL and SQLite, and stay
     * correct across daylight-saving shifts.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return array<string, int>
     */
    private function dailyMap(Builder $query, string $column, DashboardRange $range): array
    {
        [$start, $end] = $range->currentPeriod();
        $buckets = array_fill_keys($range->dayKeys(), 0);

        (clone $query)
            ->whereBetween($column, [$start, $end])
            ->pluck($column)
            ->each(function ($value) use (&$buckets): void {
                $day = Carbon::parse($value)->timezone(self::TIMEZONE)->format('Y-m-d');

                if (array_key_exists($day, $buckets)) {
                    $buckets[$day]++;
                }
            });

        return $buckets;
    }

    /**
     * @param  Builder<ContentItem>  $query
     * @return Collection<int, int>
     */
    private function countsByGroup(Builder $query): Collection
    {
        return (clone $query)
            ->selectRaw('content_group_id, COUNT(*) as aggregate')
            ->groupBy('content_group_id')
            ->pluck('aggregate', 'content_group_id')
            ->map(fn ($count): int => (int) $count);
    }

    /**
     * @return Collection<int, array{label: string, transcriptions: int, words: int}>
     */
    private function transcriptionsByTranscriber(Carbon|\DateTimeInterface $start, Carbon|\DateTimeInterface $end, ?int $contentGroupId): Collection
    {
        $rows = collect();

        $this->publishedTranscriptions($contentGroupId)
            ->whereBetween('published_at', [$start, $end])
            ->with('authors:id,name')
            ->get(['id', 'word_count'])
            ->each(function (Transcription $transcription) use ($rows): void {
                foreach ($transcription->authors as $author) {
                    $row = $rows->get($author->getKey(), [
                        'label' => (string) $author->name,
                        'transcriptions' => 0,
                        'words' => 0,
                    ]);

                    $row['transcriptions']++;
                    $row['words'] += (int) $transcription->word_count;

                    $rows->put($author->getKey(), $row);
                }
            });

        return $rows;
    }

    private function wants(?string $type, string $candidate): bool
    {
        return blank($type) || $type === $candidate;
    }

    /** @return Collection<int, array{type: string, title: string, subtitle: ?string, url: ?string, at: Carbon}> */
    private function transcriptionEvents(\DateTimeInterface $start, \DateTimeInterface $end, ?int $contentGroupId, int $limit): Collection
    {
        return $this->publishedTranscriptions($contentGroupId)
            ->whereBetween('published_at', [$start, $end])
            ->with('contentItem:id,title')
            ->latest('published_at')
            ->limit($limit)
            ->get(['id', 'content_item_id', 'published_at'])
            ->map(fn (Transcription $transcription): array => [
                'type' => 'transcription',
                'title' => (string) ($transcription->contentItem?->title ?? ''),
                'subtitle' => null,
                'url' => $transcription->content_item_id
                    ? ContentItemResource::getUrl('workspace', ['record' => $transcription->content_item_id])
                    : null,
                'at' => Carbon::parse($transcription->published_at),
            ]);
    }

    /** @return Collection<int, array{type: string, title: string, subtitle: ?string, url: ?string, at: Carbon}> */
    private function mediaEvents(\DateTimeInterface $start, \DateTimeInterface $end, ?int $contentGroupId, int $limit): Collection
    {
        return MediaAttachment::query()
            ->where('attachable_type', ContentItem::class)
            ->whereBetween('created_at', [$start, $end])
            ->when($contentGroupId, fn (Builder $query): Builder => $query->whereHasMorph(
                'attachable',
                [ContentItem::class],
                fn (Builder $inner): Builder => $inner->where('content_group_id', $contentGroupId),
            ))
            ->with('attachable:id,title')
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'attachable_type', 'attachable_id', 'created_at'])
            ->map(fn (MediaAttachment $attachment): array => [
                'type' => 'media',
                'title' => (string) ($attachment->attachable?->title ?? ''),
                'subtitle' => null,
                'url' => $attachment->attachable_id
                    ? ContentItemResource::getUrl('workspace', ['record' => $attachment->attachable_id])
                    : null,
                'at' => Carbon::parse($attachment->created_at),
            ]);
    }

    /** @return Collection<int, array{type: string, title: string, subtitle: ?string, url: ?string, at: Carbon}> */
    private function importEvents(\DateTimeInterface $start, \DateTimeInterface $end, int $limit): Collection
    {
        return Import::query()
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Import $import): array => [
                'type' => 'import',
                'title' => (string) $import->file_name,
                'subtitle' => __('admin.dashboard.stream.import_rows', [
                    'successful' => (int) $import->successful_rows,
                    'total' => (int) $import->total_rows,
                ]),
                'url' => ContentItemResource::getUrl('index'),
                'at' => Carbon::parse($import->created_at),
            ]);
    }

    /** @return Collection<int, array{type: string, title: string, subtitle: ?string, url: ?string, at: Carbon}> */
    private function submissionEvents(\DateTimeInterface $start, \DateTimeInterface $end, int $limit): Collection
    {
        return PublicFormSubmission::query()
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'form_name_snapshot', 'form_key', 'created_at'])
            ->map(fn (PublicFormSubmission $submission): array => [
                'type' => 'submission',
                'title' => (string) ($submission->form_name_snapshot ?: $submission->form_key),
                'subtitle' => null,
                'url' => PublicFormSubmissionResource::getUrl('edit', ['record' => $submission->getKey()]),
                'at' => Carbon::parse($submission->created_at),
            ]);
    }

    /** @return Builder<ContentItem> */
    private function missingTranscription(?int $contentGroupId = null): Builder
    {
        return $this->statusPublished($contentGroupId)
            ->whereDoesntHave('transcriptions', fn (Builder $query): Builder => $query->published());
    }

    /** @return Builder<ContentItem> */
    private function missingMedia(?int $contentGroupId = null): Builder
    {
        return $this->applyMissingMedia($this->statusPublished($contentGroupId));
    }

    /**
     * @param  Builder<ContentItem>  $query
     * @return Builder<ContentItem>
     */
    private function applyMissingMedia(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $inner): Builder => $inner->whereNull('embed_url')->orWhere('embed_url', ''))
            ->where(fn (Builder $inner): Builder => $inner->whereNull('media_url')->orWhere('media_url', ''));
    }

    /** @return Builder<ContentItem> */
    private function missingCategory(?int $contentGroupId = null): Builder
    {
        return $this->applyMissingCategory($this->statusPublished($contentGroupId));
    }

    /**
     * @param  Builder<ContentItem>  $query
     * @return Builder<ContentItem>
     */
    private function applyMissingCategory(Builder $query): Builder
    {
        return $query
            ->whereDoesntHave('categories')
            ->whereDoesntHave('contentGroup.categories');
    }
}
