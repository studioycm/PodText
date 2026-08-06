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
 * drafts + visible + scheduled + the two blocked scopes = all, exactly. Counts reuse the
 * same predicates through counting subqueries, so every badge resolves in a
 * single round trip without a second home for the conditions.
 */
class EpisodeListScopeQuery
{
    /** @var array<string, int>|null */
    private ?array $counts = null;

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
            // One switch away, on another record: the episode and its
            // transcript are ready and the podcast is not published.
            EpisodeListScope::BlockedGroup => $this->whereBlocked($query)
                ->whereNot(fn (Builder $q): Builder => $this->whereGroupReleased($q)),
            // Real work: the podcast is fine, the transcript is not there.
            // Podcast-blocked rows are excluded so the two scopes stay
            // disjoint under the same precedence EpisodePublicState uses.
            EpisodeListScope::BlockedTranscription => $this->whereBlocked($query)
                ->where(fn (Builder $q): Builder => $this->whereGroupReleased($q))
                ->whereNot(fn (Builder $q): Builder => $this->whereTranscriptionReleased($q)),
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

    /**
     * Published rows the public cannot see — released ones missing a
     * prerequisite now, plus scheduled ones that will still be missing one on
     * the day. Blockers outrank the clock, which is why a future-dated
     * episode with an unpublished podcast lives here and not in `scheduled`.
     */
    private function whereBlocked(Builder $query): Builder
    {
        return $query
            ->where('status', PublicationStatus::Published)
            ->where(function (Builder $query): void {
                $query
                    ->where(fn (Builder $released): Builder => $released
                        ->where(fn (Builder $q): Builder => $this->whereReleased($q))
                        ->whereNot(fn (Builder $q): Builder => $this->wherePrerequisitesMetNow($q)))
                    ->orWhere(fn (Builder $scheduled): Builder => $scheduled
                        ->where('published_at', '>', now())
                        ->whereNot(fn (Builder $q): Builder => $this->wherePrerequisitesMetByAirTime($q)));
            });
    }

    /**
     * Judged at the later of now and the air time, matching
     * EpisodePublicState: a past-dated row is asked "are you out?", a
     * future-dated one "will you be out by then?".
     */
    private function whereGroupReleased(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->where(fn (Builder $now): Builder => $now
                    ->where(fn (Builder $q): Builder => $this->whereReleased($q))
                    ->whereHas('contentGroup', fn (Builder $group): Builder => $group->releasedBy(now())))
                ->orWhere(fn (Builder $later): Builder => $later
                    ->where('published_at', '>', now())
                    ->whereHas('contentGroup', fn (Builder $group): Builder => $group->releasedBy('content_items.published_at')));
        });
    }

    private function whereTranscriptionReleased(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->where(fn (Builder $now): Builder => $now
                    ->where(fn (Builder $q): Builder => $this->whereReleased($q))
                    ->whereHas('transcriptions', fn (Builder $t): Builder => $t->releasedBy(now())))
                ->orWhere(fn (Builder $later): Builder => $later
                    ->where('published_at', '>', now())
                    ->whereHas('transcriptions', fn (Builder $t): Builder => $t->releasedBy('content_items.published_at')));
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
     * Deliberately uncached and deliberately library-wide: the badges answer
     * "how many drafts exist", not "how many match my current filter", and
     * one aggregate per render is cheap at this library's size. Revisit both
     * choices — a short cache, or filter-aware counts — if the catalogue
     * passes a few thousand episodes or the anti-joins start showing up in
     * query timings.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        if ($this->counts !== null) {
            return $this->counts;
        }

        $query = DB::query();

        foreach (EpisodeListScope::cases() as $scope) {
            $query->selectSub(
                $this->apply(ContentItem::query(), $scope)->toBase()->selectRaw('count(*)'),
                $scope->value,
            );
        }

        return $this->counts = collect((array) $query->first())
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }
}
