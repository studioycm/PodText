<?php

namespace App\Support\ContentItems;

use App\Enums\EpisodeListScope;
use App\Enums\PublicationStatus;
use App\Models\ContentItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The one home for episode quick-scope membership (EQ-4). `visible` rides
 * the model's own published() scope so the public contract is never
 * re-derived; `scheduled`/`blocked` split the remaining published tier so
 * drafts + visible + scheduled + blocked = all, exactly. Counts reuse the
 * same predicates through counting subqueries, so every badge resolves in a
 * single round trip without a second home for the conditions.
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
                ->where('published_at', '>', now())
                ->where(fn (Builder $query): Builder => $this->wherePrerequisitesMetByAirTime($query)),
            EpisodeListScope::Blocked => $query
                ->where('status', PublicationStatus::Published)
                ->where(function (Builder $query): void {
                    // Released rows that are not visible, plus scheduled rows
                    // whose prerequisites will still be missing on the day —
                    // both are the operator's actionable tier.
                    $query
                        ->where(fn (Builder $released): Builder => $released
                            ->where(fn (Builder $q): Builder => $this->whereReleased($q))
                            ->whereNot(fn (Builder $q): Builder => $this->wherePrerequisitesMetNow($q)))
                        ->orWhere(fn (Builder $scheduled): Builder => $scheduled
                            ->where('published_at', '>', now())
                            ->whereNot(fn (Builder $q): Builder => $this->wherePrerequisitesMetByAirTime($q)));
                }),
            EpisodeListScope::Pinned => $query->currentlyPinned(),
        };
    }

    /** The episode's own air time has arrived. */
    private function whereReleased(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now());
        });
    }

    /** Podcast and transcript are out right now. */
    private function wherePrerequisitesMetNow(Builder $query): Builder
    {
        return $query
            ->whereHas('contentGroup', fn (Builder $group): Builder => $group->releasedBy(now()))
            ->whereHas('transcriptions', fn (Builder $transcription): Builder => $transcription->releasedBy(now()));
    }

    /** Podcast and transcript will be out by the episode's own air time. */
    private function wherePrerequisitesMetByAirTime(Builder $query): Builder
    {
        return $query
            ->whereHas('contentGroup', fn (Builder $group): Builder => $group->releasedBy('content_items.published_at'))
            ->whereHas('transcriptions', fn (Builder $transcription): Builder => $transcription->releasedBy('content_items.published_at'));
    }

    /**
     * Every badge in one round trip: each scope becomes a counting subquery
     * built from `apply()`, so the numbers can never drift from the tab they
     * label (the alternative — hand-written CASE WHEN conditions — would be
     * a second home for the same predicates).
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $query = DB::query();

        foreach (EpisodeListScope::cases() as $scope) {
            $query->selectSub(
                $this->apply(ContentItem::query(), $scope)->toBase()->selectRaw('count(*)'),
                $scope->value,
            );
        }

        return collect((array) $query->first())
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }
}
