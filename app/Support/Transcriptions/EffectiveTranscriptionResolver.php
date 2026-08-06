<?php

namespace App\Support\Transcriptions;

use App\Models\ContentItem;
use App\Models\Transcription;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * The one home for "which transcription is this episode's".
 *
 * The rule — the featured one if it is this item's and published, otherwise
 * the latest published one — was written out in four places, and evaluating
 * it cost more than it needed to: the modal loaded the same transcription
 * twice and its authors twice, because featured and latest-published resolve
 * to the same row on every episode in production.
 *
 * Short-circuit, not a stored pointer. A maintained `effective_transcription_id`
 * was measured and rejected: "latest published" depends on published_at <= now,
 * so the correct answer changes when a scheduled transcript crosses its air
 * time — with no write to fire an observer. Every repair for that introduces a
 * third clock, in an app that has just finished removing a database-vs-app
 * clock split and has a regression test whose whole point is that one clock
 * decides both answers. A pointer would make that assertion false by
 * construction, and would invert a working contract besides:
 * `featured_transcription_id` is editorial INTENT, and staleness in it is
 * currently legal precisely because this rule makes stale intent harmless.
 *
 * The win the pointer promised lives in the loading strategy instead, and
 * costs nothing to take: ask the featured branch first and only touch the
 * fallback when it does not settle the question.
 */
class EffectiveTranscriptionResolver
{
    /**
     * Resolve without loading anything — for records whose relations are
     * already primed. Reaching a relation here would be a broken eager-load
     * contract, so it is left to raise through the app's lazy-loading policy
     * rather than answered with a null that reads as "no transcript".
     */
    public function forLoaded(ContentItem $item): ?Transcription
    {
        $featured = $item->featuredTranscription;

        if ($this->isOwnPublishedFeatured($item, $featured)) {
            return $featured;
        }

        return $item->latestPublishedTranscription;
    }

    /**
     * Resolve a single record, loading only what the answer needs.
     *
     * The saving is the whole point: when the featured transcript qualifies —
     * every episode in production today — the fallback relation and its
     * authors are never touched. Measured on the modal path: 4 relation
     * queries become 2.
     */
    public function for(ContentItem $item): ?Transcription
    {
        $item->loadMissing('featuredTranscription.authors');
        $featured = $item->featuredTranscription;

        if ($this->isOwnPublishedFeatured($item, $featured)) {
            return $featured;
        }

        $item->loadMissing('latestPublishedTranscription.authors');

        return $item->latestPublishedTranscription;
    }

    /**
     * The relations a page loads BEFORE it knows which rows will fall back.
     *
     * Only the featured branch. The fallback is a second pass, because loading
     * it up front is measured waste: on the episodes list it is 3 of 7 relation
     * queries, and 0 of 10 rows read the result — `forLoaded()` returns the
     * featured transcription for every one of them and the fallback rows are
     * hydrated and discarded.
     *
     * An earlier docblock here claimed a two-pass "would need a records-resolved
     * seam the table query does not offer". That was wrong: `getTableRecords()`
     * is public and memoises into `$cachedTableRecords`, so a page can override
     * it, call parent, and prime once — which is what
     * `PrimesEffectiveTranscriptions` does.
     *
     * @return array<int, string>
     */
    public function eagerLoadPaths(): array
    {
        return [
            'featuredTranscription.authors',
            'featuredTranscription.author',
        ];
    }

    /**
     * The second pass: load the fallback branch only for rows the featured one
     * did not settle.
     *
     * Idempotent — `loadMissing` skips anything already hydrated, so calling it
     * again on a memoised record set costs nothing.
     *
     * @param  iterable<int, ContentItem>  $items
     */
    public function primeFallback(iterable $items): void
    {
        $unsettled = collect($items)
            // Self-gating on column visibility, using the signal the column
            // itself leaves behind: `featuredTranscription` is loaded only when
            // an EffectiveTranscriptionColumn is toggled on. A row without it
            // belongs to a page that will read neither branch, so priming the
            // fallback there would re-spend the queries this pass exists to
            // save — on the default view, which shows no transcription column
            // at all.
            ->filter(fn (ContentItem $item): bool => $item->relationLoaded('featuredTranscription'))
            ->filter(fn (ContentItem $item): bool => ! $this->isOwnPublishedFeatured(
                $item,
                $item->featuredTranscription,
            ));

        if ($unsettled->isEmpty()) {
            return;
        }

        // Eloquent's collection, not the base one: `loadMissing()` lives on
        // Illuminate\Database\Eloquent\Collection, and `collect()` hands back
        // Support\Collection, which answers the call with a BadMethodCallException.
        new EloquentCollection($unsettled->all())->loadMissing([
            'latestPublishedTranscription.authors',
            'latestPublishedTranscription.author',
        ]);
    }

    private function isOwnPublishedFeatured(ContentItem $item, ?Transcription $featured): bool
    {
        return $featured instanceof Transcription
            && $featured->content_item_id === $item->getKey()
            && $featured->isPublished();
    }
}
