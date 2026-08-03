<?php

namespace App\Support\Dashboard;

use App\Enums\DashboardRange;
use App\Enums\DashboardReason;
use App\Enums\DashboardTier;
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
use App\Support\Dashboard\Data\BreakdownRow;
use App\Support\Dashboard\Data\Burndown;
use App\Support\Dashboard\Data\Heatmap;
use App\Support\Dashboard\Data\Rate;
use App\Support\Dashboard\Data\SeriesRow;
use App\Support\UiTimezone;
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

    /** @var array<int, bool> */
    private array $knownPodcasts = [];

    /**
     * Two tiers, never merged: `gap` is what the public cannot see at all, and
     * `attention` is what the public can see but is incomplete. An episode in
     * `attention` may well be visible, so no copy may call it invisible.
     *
     * @return array{
     *     funnel: array{draft: int, published: int, transcribed: int, visible: int},
     *     gap: array{invisible: int, missing_transcription: int, unpublished_group: int},
     *     attention: array{total: int, missing_media: int, missing_category: int},
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
            'gap' => [
                'invisible' => $this->invisibleQuery($contentGroupId)->count(),
                'missing_transcription' => $this->missingTranscription($contentGroupId)->count(),
                'unpublished_group' => $this->unpublishedGroup($contentGroupId)->count(),
            ],
            'attention' => [
                'total' => $this->attentionQuery($contentGroupId)->count(),
                'missing_media' => $this->missingMedia($contentGroupId)->count(),
                'missing_category' => $this->missingCategory($contentGroupId)->count(),
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
     * Daily movement through each funnel stage aligned to
     * the range's Jerusalem day keys. `value`/`previousValue` are period
     * movement, not stock — the stock count comes from the snapshot, so a row
     * never mixes the two. Draft counts episodes added, published counts
     * publication dates, transcribed counts transcripts published, and visible
     * counts publication dates of episodes the public can currently see.
     *
     * @return array<string, SeriesRow>
     */
    public function funnelSeries(DashboardRange $range, ?int $contentGroupId = null): array
    {
        $sources = [
            'draft' => [$this->scoped(ContentItem::query(), $contentGroupId), 'created_at'],
            'published' => [$this->statusPublished($contentGroupId), 'published_at'],
            'transcribed' => [$this->publishedTranscriptions($contentGroupId), 'published_at'],
        ];

        [$previousStart, $previousEnd] = $range->previousPeriod();
        $rows = [];

        foreach ($sources as $stage => [$query, $column]) {
            $series = JerusalemDailySeries::values($query, $column, $range);

            $rows[$stage] = $this->sparklineRow(
                $stage,
                $series,
                JerusalemDailySeries::total($query, $column, $previousStart, $previousEnd),
            );
        }

        // Visible is the one stage whose day is derived, not stored: an episode
        // becomes visible on the later of its publication and its effective
        // transcript's publication, so bucketing by `published_at` would put a
        // May episode transcribed in July on a May cell.
        $becameVisible = $this->becameVisibleAt($contentGroupId);

        $rows['visible'] = $this->sparklineRow(
            'visible',
            array_map(floatval(...), array_values(
                JerusalemDailySeries::fromTimestamps($becameVisible, $range),
            )),
            (float) $becameVisible
                ->filter(fn (Carbon $at): bool => $at->betweenIncluded($previousStart, $previousEnd))
                ->count(),
        );

        return $rows;
    }

    /**
     * @param  array<int, float>  $series
     */
    private function sparklineRow(string $stage, array $series, float $previous): SeriesRow
    {
        return new SeriesRow(
            key: $stage,
            label: __("admin.dashboard.legend.{$stage}"),
            value: array_sum($series),
            previous: $previous,
            points: $series,
        );
    }

    /**
     * The instant each visible episode actually became visible: the later of
     * its own publication and its effective transcript's publication, since
     * both must be true before the public can see it.
     *
     * @return Collection<int, Carbon>
     */
    private function becameVisibleAt(?int $contentGroupId): Collection
    {
        return $this->visible($contentGroupId)
            ->withEffectiveTranscriptionPublishedAt()
            ->get(['id', 'published_at'])
            ->map(function (ContentItem $item): ?Carbon {
                $transcript = $item->featured_transcription_published_at
                    ?? $item->latest_transcription_published_at;

                return collect([$item->published_at, $transcript])
                    ->filter()
                    ->map(fn ($value): Carbon => Carbon::parse($value))
                    ->sortDesc()
                    ->first();
            })
            ->filter()
            ->values();
    }

    /**
     * The same publication flow the funnel's published segment reports, laid
     * out as a calendar. Each entry carries the day's own stream doorway.
     */
    public function publicationHeatmap(DashboardRange $range, ?int $contentGroupId = null): Heatmap
    {
        $entries = $this->publicationsPerDay($range, $contentGroupId);

        return new Heatmap(
            entries: $entries,
            description: __('admin.dashboard.heatmap.description', ['count' => array_sum($entries)]),
        );
    }

    /**
     * Per-podcast publication health: how much of what a podcast published is
     * actually visible, and how much is stuck.
     *
     * `value` is what the public can see and `of` is what the podcast published,
     * so the row reads as "visible out of published" and `percent()` needs no
     * arithmetic in the view. Podcasts beyond `$limit` roll into one labelled
     * "other" row rather than being dropped, so the band's totals keep
     * agreeing with the funnel.
     *
     * @return array<int, BreakdownRow>
     */
    public function podcastHealth(?int $contentGroupId = null, int $limit = 6): array
    {
        $totals = $this->countsByGroup($this->statusPublished($contentGroupId));
        $visible = $this->countsByGroup($this->visible($contentGroupId));

        $rows = ContentGroup::query()
            ->whereKey($totals->keys())
            ->get(['id', 'title'])
            ->map(function (ContentGroup $group) use ($totals, $visible): BreakdownRow {
                $total = (int) $totals->get($group->getKey(), 0);
                $visibleCount = (int) $visible->get($group->getKey(), 0);
                $percent = $total > 0 ? (int) round(($visibleCount / $total) * 100) : 0;

                return new BreakdownRow(
                    label: (string) $group->title,
                    value: (float) $visibleCount,
                    of: (float) $total,
                    color: $this->healthColor($percent),
                    url: ContentItemResource::getUrl('index', [
                        'filters' => ['content_group_id' => ['value' => $group->getKey()]],
                    ]),
                );
            })
            ->sortByDesc(fn (BreakdownRow $item): float => $item->of ?? 0)
            ->values();

        return $this->rollUpTail($rows, $limit, function (Collection $tail): BreakdownRow {
            $value = $tail->sum(fn (BreakdownRow $row): float => $row->value);
            $of = $tail->sum(fn (BreakdownRow $row): float => $row->of ?? 0.0);
            $percent = $of > 0 ? (int) round(($value / $of) * 100) : 0;

            return new BreakdownRow(
                label: __('admin.dashboard.composition.other_podcasts', ['count' => $tail->count()]),
                value: $value,
                of: $of,
                color: $this->healthColor($percent),
                meta: ['rolled_up' => $tail->count()],
            );
        })->all();
    }

    /**
     * The spec's "transcriptions by author": who published transcripts in the
     * range, how many words, and how that compares with the previous period.
     * A multi-transcriber transcript counts in full for each of its
     * transcribers. Transcribers beyond `$limit` roll into one labelled
     * "other" row whose transcripts, words and previous period stay summed,
     * so the board still accounts for everyone who published.
     *
     * @return array<int, BreakdownRow>
     */
    public function transcriberBoard(DashboardRange $range, ?int $contentGroupId = null, int $limit = 6): array
    {
        [$currentStart, $currentEnd] = $range->currentPeriod();
        [$previousStart, $previousEnd] = $range->previousPeriod();

        $current = $this->transcriptionsByTranscriber($currentStart, $currentEnd, $contentGroupId);
        $previous = $this->transcriptionsByTranscriber($previousStart, $previousEnd, $contentGroupId);

        $rows = $current
            ->map(fn (array $row, int $authorId): BreakdownRow => new BreakdownRow(
                label: $row['label'],
                value: (float) $row['transcriptions'],
                previous: (float) ($previous[$authorId]['transcriptions'] ?? 0),
                url: TranscriptionResource::getUrl('index', [
                    'filters' => ['transcriber_id' => ['value' => $authorId]],
                ]),
                meta: ['words' => $row['words']],
            ))
            ->sortByDesc(fn (BreakdownRow $row): float => $row->value)
            ->values();

        return $this->rollUpTail($rows, $limit, fn (Collection $tail): BreakdownRow => new BreakdownRow(
            label: __('admin.dashboard.composition.other_transcribers', ['count' => $tail->count()]),
            value: $tail->sum(fn (BreakdownRow $row): float => $row->value),
            previous: $tail->sum(fn (BreakdownRow $row): float => $row->previous ?? 0.0),
            meta: [
                'words' => (int) $tail->sum(fn (BreakdownRow $row): int => (int) $row->meta('words', 0)),
                'rolled_up' => $tail->count(),
            ],
        ))->all();
    }

    /**
     * The no-silent-caps rule applied to breakdowns: rows beyond the limit
     * are rolled into one summarising row built by `$makeOther`, never
     * silently dropped, so what is on screen still sums to the whole. The
     * roll-up row carries a `rolled_up` count in `meta` and no doorway URL,
     * since it stands for several records at once.
     *
     * @param  Collection<int, BreakdownRow>  $rows
     * @param  \Closure(Collection<int, BreakdownRow>): BreakdownRow  $makeOther
     * @return Collection<int, BreakdownRow>
     */
    private function rollUpTail(Collection $rows, int $limit, \Closure $makeOther): Collection
    {
        if ($rows->count() <= $limit) {
            return $rows;
        }

        $tail = $rows->slice($limit)->values();

        return $rows->take($limit)->push($makeOther($tail))->values();
    }

    private function healthColor(int $percent): string
    {
        return match (true) {
            $percent >= 100 => 'success',
            $percent >= 75 => 'warning',
            default => 'danger',
        };
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
                fn (array $event): bool => $event['at']->copy()->timezone(UiTimezone::name())->format('Y-m-d') === $day,
            ))
            ->sortByDesc(fn (array $event): int => $event['at']->getTimestamp())
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * H7's finish lines, one per tier. Both
     * counts come from the cached snapshot, so the second bar costs no queries.
     * Only the invisible tier carries a projection: transcripts have a
     * `published_at` to pace from, while the category pivot has no timestamps
     * at all, so a needs-attention forecast could not be honest.
     *
     * @return array{invisible: array{remaining: int, total: int, forecast: ?Carbon, data: ProgressWidgetData}, attention: array{remaining: int, total: int, data: ProgressWidgetData}}
     */
    public function blockersProgress(?int $contentGroupId = null): array
    {
        $snapshot = $this->snapshot($contentGroupId);
        $total = $snapshot['funnel']['published'];
        $invisible = $snapshot['gap']['invisible'];
        $attention = $snapshot['attention']['total'];

        return [
            'invisible' => new Burndown(
                tier: DashboardTier::Invisible,
                remaining: $invisible,
                total: $total,
                description: __('admin.dashboard.queue.burndown_invisible', ['remaining' => $invisible, 'total' => $total]),
                forecast: $this->clearanceForecast($contentGroupId),
            ),
            // No forecast by design: the category pivot carries no timestamps,
            // so needs-attention work cannot be paced without inventing a rate.
            'attention' => new Burndown(
                tier: DashboardTier::Attention,
                remaining: $attention,
                total: $total,
                description: __('admin.dashboard.queue.burndown_attention', ['remaining' => $attention, 'total' => $total]),
            ),
        ];
    }

    /**
     * The gap and needs-attention reason bars. Each row carries its reason key
     * in `meta`, and the Board-2 view wires it to the blockers queue on the
     * same board: clicking a bar dispatches `dashboard-reason-selected`, which
     * the queue widget receives into its own reason filter. The rows carry no
     * URL — the queue is not a separate page, and a widget table's filters are
     * not URL-hydrated, so a Resource link here could only open an unfiltered
     * list and break the bar's promise.
     *
     * @return array{gap: array<int, BreakdownRow>, attention: array<int, BreakdownRow>}
     */
    public function reasonBreakdown(?int $contentGroupId = null): array
    {
        $snapshot = $this->snapshot($contentGroupId);
        $counts = $snapshot['gap'] + $snapshot['attention'];

        $rows = fn (array $reasons): array => array_map(
            fn (DashboardReason $reason): BreakdownRow => new BreakdownRow(
                label: $reason->getLabel(),
                value: (float) ($counts[$reason->value] ?? 0),
                color: $reason->getColor(),
                meta: [
                    'bar' => $reason->barClass(),
                    'reason' => $reason->value,
                ],
            ),
            $reasons,
        );

        return [
            'gap' => $rows(DashboardReason::gap()),
            'attention' => $rows(DashboardReason::attention()),
        ];
    }

    /** H2's coverage gauge: how much of what is published the public can see. */
    public function visibilityRate(?int $contentGroupId = null): Rate
    {
        $snapshot = $this->snapshot($contentGroupId);

        return new Rate(
            covered: $snapshot['funnel']['visible'],
            of: $snapshot['funnel']['published'],
            description: __('admin.dashboard.gap.rate_description'),
        );
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
     * Status-published items the public cannot see at all — exactly
     * status-published minus visible, resolved into two reasons.
     *
     * @return Builder<ContentItem>
     */
    public function invisibleQuery(?int $contentGroupId = null): Builder
    {
        return $this->statusPublished($contentGroupId)->where(function (Builder $query): void {
            $query
                ->whereDoesntHave('transcriptions', fn (Builder $inner): Builder => $inner->published())
                ->orWhereDoesntHave('contentGroup', fn (Builder $inner): Builder => $inner->published());
        });
    }

    /**
     * Status-published items that are incomplete but not therefore invisible —
     * the public may well be seeing these already.
     *
     * @return Builder<ContentItem>
     */
    public function attentionQuery(?int $contentGroupId = null): Builder
    {
        return $this->statusPublished($contentGroupId)->where(function (Builder $query): void {
            $query
                ->where(fn (Builder $inner): Builder => $this->applyMissingMedia($inner))
                ->orWhere(fn (Builder $inner): Builder => $this->applyMissingCategory($inner));
        });
    }

    /**
     * Everything either tier wants worked on — the queue population. Rows carry
     * their own reasons, so the queue stays one list across both tiers.
     *
     * @return Builder<ContentItem>
     */
    public function queueQuery(?int $contentGroupId = null): Builder
    {
        return $this->statusPublished($contentGroupId)->where(function (Builder $query): void {
            $query
                ->whereDoesntHave('transcriptions', fn (Builder $inner): Builder => $inner->published())
                ->orWhereDoesntHave('contentGroup', fn (Builder $inner): Builder => $inner->published())
                ->orWhere(fn (Builder $inner): Builder => $this->applyMissingMedia($inner))
                ->orWhere(fn (Builder $inner): Builder => $this->applyMissingCategory($inner));
        });
    }

    /** @return array<int, string> */
    public function blockerReasonsFor(ContentItem $item): array
    {
        $reasons = [];

        if (! $item->transcriptions()->published()->exists()) {
            $reasons[] = 'missing_transcription';
        }

        // `ContentItem::scopePublished()` requires a published group, so an
        // otherwise complete episode under a draft podcast is invisible too.
        if ($item->contentGroup === null || ! $item->contentGroup->newQuery()->published()->whereKey($item->content_group_id)->exists()) {
            $reasons[] = 'unpublished_group';
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
        $remaining = $this->snapshot($contentGroupId)['gap']['missing_transcription'];

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
            'unpublished_group' => $query->whereDoesntHave('contentGroup', fn (Builder $inner): Builder => $inner->published()),
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
        return JerusalemDailySeries::map($this->statusPublished($contentGroupId), 'published_at', $range);
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

    /**
     * Synthesis rule 1 in practice: a legend chip narrows the flow widgets to
     * the event kind that stage is made of. Stock widgets keep their totals,
     * because a total scoped to one of its own segments means nothing.
     */
    public static function streamTypeForStatus(?string $status): ?string
    {
        return match ($status) {
            'transcribed', 'visible' => 'transcription',
            default => null,
        };
    }

    /** @return Collection<int, array{type: string, title: string, subtitle: ?string, url: ?string, at: Carbon}> */
    private function transcriptionEvents(\DateTimeInterface $start, \DateTimeInterface $end, ?int $contentGroupId, int $limit): Collection
    {
        return $this->publishedTranscriptions($contentGroupId)
            ->whereBetween('published_at', [$start, $end])
            ->with(['contentItem:id,title,status,content_group_id', 'contentItem.contentGroup:id,title'])
            ->latest('published_at')
            ->limit($limit)
            ->get(['id', 'content_item_id', 'published_at'])
            ->map(fn (Transcription $transcription): array => [
                'type' => 'transcription',
                'title' => (string) ($transcription->contentItem?->title ?? ''),
                // The RecentPublishedItems columns the honesty audit promised
                // this stream would carry: podcast and episode status.
                'subtitle' => collect([
                    $transcription->contentItem?->contentGroup?->title,
                    $transcription->contentItem?->status?->getLabel(),
                ])->filter()->implode(' · ') ?: null,
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
    private function unpublishedGroup(?int $contentGroupId = null): Builder
    {
        return $this->statusPublished($contentGroupId)
            ->whereDoesntHave('contentGroup', fn (Builder $query): Builder => $query->published());
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
