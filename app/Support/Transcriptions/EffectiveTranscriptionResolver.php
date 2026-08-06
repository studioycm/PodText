<?php

namespace App\Support\Transcriptions;

use App\Models\ContentItem;
use App\Models\Transcription;

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
     * The relations a page needs to answer `forLoaded()` for every row.
     *
     * Both branches, because a page cannot know in advance which rows will
     * fall back — a per-page two-pass load would need a records-resolved seam
     * the table query does not offer.
     *
     * @return array<int, string>
     */
    public function eagerLoadPaths(): array
    {
        return [
            'featuredTranscription.authors',
            'featuredTranscription.author',
            'latestPublishedTranscription.authors',
            'latestPublishedTranscription.author',
        ];
    }

    private function isOwnPublishedFeatured(ContentItem $item, ?Transcription $featured): bool
    {
        return $featured instanceof Transcription
            && $featured->content_item_id === $item->getKey()
            && $featured->isPublished();
    }
}
