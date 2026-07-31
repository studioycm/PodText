<?php

namespace App\Support\Dashboard;

use App\Enums\PublicationStatus;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\Transcription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The single source of truth for every number on the dashboard: any figure
 * that appears in more than one widget reads from this snapshot, so widgets
 * cannot silently disagree. Bounded cache instead of polling.
 */
class EditorialMetrics
{
    private const CACHE_SECONDS = 60;

    /**
     * @return array{
     *     funnel: array{draft: int, published: int, transcribed: int, visible: int},
     *     blockers: array{missing_transcription: int, missing_media: int, missing_category: int, total: int},
     *     structure: array{groups: int, authors: int, categories: int, tags_enabled: int, tags_disabled: int, pinned: int, multi_transcription: int},
     *     generated_at: string
     * }
     */
    public function snapshot(): array
    {
        return Cache::remember('dashboard:editorial-metrics:v1', self::CACHE_SECONDS, fn (): array => [
            'funnel' => [
                'draft' => ContentItem::query()->where('status', PublicationStatus::Draft)->count(),
                'published' => $this->statusPublished()->count(),
                'transcribed' => $this->statusPublished()
                    ->whereHas('transcriptions', fn (Builder $query): Builder => $query->published())
                    ->count(),
                'visible' => ContentItem::query()->published()->count(),
            ],
            'blockers' => [
                'missing_transcription' => $this->missingTranscription()->count(),
                'missing_media' => $this->missingMedia()->count(),
                'missing_category' => $this->missingCategory()->count(),
                'total' => $this->blockedQuery()->count(),
            ],
            'structure' => [
                'groups' => ContentGroup::query()->count(),
                'authors' => Author::query()->count(),
                'categories' => Category::query()->count(),
                'tags_enabled' => ContentTag::query()->content()->enabled()->count(),
                'tags_disabled' => ContentTag::query()->content()->where('is_enabled', false)->count(),
                'pinned' => ContentItem::query()->where('is_pinned', true)->count(),
                'multi_transcription' => ContentItem::query()
                    ->whereHas('transcriptions', operator: '>', count: 1)
                    ->count(),
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function forget(): void
    {
        Cache::forget('dashboard:editorial-metrics:v1');
    }

    /**
     * Status-published items carrying at least one publication blocker —
     * the queue population behind the blockers lens.
     *
     * @return Builder<ContentItem>
     */
    public function blockedQuery(): Builder
    {
        return $this->statusPublished()->where(function (Builder $query): void {
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

    public function clearanceForecast(): ?Carbon
    {
        $remaining = $this->snapshot()['blockers']['missing_transcription'];

        if ($remaining < 1) {
            return null;
        }

        $recent = Transcription::query()
            ->published()
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

    /** @return Builder<ContentItem> */
    private function statusPublished(): Builder
    {
        return ContentItem::query()
            ->where('status', PublicationStatus::Published)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    /** @return Builder<ContentItem> */
    private function missingTranscription(): Builder
    {
        return $this->statusPublished()
            ->whereDoesntHave('transcriptions', fn (Builder $query): Builder => $query->published());
    }

    /** @return Builder<ContentItem> */
    private function missingMedia(): Builder
    {
        return $this->applyMissingMedia($this->statusPublished());
    }

    /** @return Builder<ContentItem> */
    private function applyMissingMedia(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $inner): Builder => $inner->whereNull('embed_url')->orWhere('embed_url', ''))
            ->where(fn (Builder $inner): Builder => $inner->whereNull('media_url')->orWhere('media_url', ''));
    }

    /** @return Builder<ContentItem> */
    private function missingCategory(): Builder
    {
        return $this->applyMissingCategory($this->statusPublished());
    }

    /** @return Builder<ContentItem> */
    private function applyMissingCategory(Builder $query): Builder
    {
        return $query
            ->whereDoesntHave('categories')
            ->whereDoesntHave('contentGroup.categories');
    }
}
