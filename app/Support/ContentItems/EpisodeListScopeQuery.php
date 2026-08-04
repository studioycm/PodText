<?php

namespace App\Support\ContentItems;

use App\Enums\EpisodeListScope;
use App\Enums\PublicationStatus;
use App\Models\ContentItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * The one home for episode quick-scope membership (EQ-4). `visible` rides
 * the model's own published() scope so the public contract is never
 * re-derived; `scheduled`/`blocked` split the remaining published tier so
 * drafts + visible + scheduled + blocked = all, exactly. Counts reuse the
 * same predicates (correctness by construction over a hand-built aggregate;
 * a single-pass aggregate is a future seat if scale ever asks).
 */
class EpisodeListScopeQuery
{
    public function apply(Builder $query, EpisodeListScope $scope): Builder
    {
        return match ($scope) {
            EpisodeListScope::All => $query,
            EpisodeListScope::Drafts => $query->where('status', PublicationStatus::Draft),
            EpisodeListScope::Visible => $query->published(),
            EpisodeListScope::Scheduled => $query
                ->where('status', PublicationStatus::Published)
                ->where('published_at', '>', now()),
            EpisodeListScope::Blocked => $query
                ->where('status', PublicationStatus::Published)
                ->where(function (Builder $query): void {
                    $query
                        ->whereNull('published_at')
                        ->orWhere('published_at', '<=', now());
                })
                ->where(function (Builder $query): void {
                    $query
                        ->whereDoesntHave('contentGroup', fn (Builder $group): Builder => $group->published())
                        ->orWhereDoesntHave('transcriptions', fn (Builder $transcription): Builder => $transcription->published());
                }),
            EpisodeListScope::Pinned => $query->currentlyPinned(),
        };
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return collect(EpisodeListScope::cases())
            ->mapWithKeys(fn (EpisodeListScope $scope): array => [
                $scope->value => $this->apply(ContentItem::query(), $scope)->count(),
            ])
            ->all();
    }
}
