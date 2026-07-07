<?php

namespace App\Support\PublicContent;

use App\Enums\PublicationStatus;
use App\Models\ContentItem;
use App\Models\Transcription;
use Illuminate\Database\Eloquent\Builder;

class PublicContentItemQueries
{
    public static function base(): Builder
    {
        return ContentItem::query()
            ->published()
            ->with([
                'categories',
                'contentGroup.categories',
                'enabledContentTags',
                'featuredTranscription.authors',
                'latestPublishedTranscription.authors',
            ])
            ->withEffectiveTranscriptionPublishedAt();
    }

    public static function pinnedFirst(Builder $query): Builder
    {
        $now = now();

        return $query
            ->orderByRaw(
                'case when is_pinned = 1 and (pinned_at is null or pinned_at <= ?) and (pinned_until is null or pinned_until > ?) then 0 else 1 end',
                [$now, $now],
            )
            ->orderByRaw('pin_order is null')
            ->orderBy('pin_order')
            ->orderByDesc('pinned_at')
            ->orderByEffectiveTranscriptionPublishedAt();
    }

    public static function effectiveTranscriptionPublishedAtSql(): string
    {
        $contentItemsTable = (new ContentItem)->getTable();
        $transcriptionsTable = (new Transcription)->getTable();
        $published = str_replace("'", "''", PublicationStatus::Published->value);
        $publishedWhere = "status = '{$published}' and transcript_markdown is not null and transcript_markdown != '' and (published_at is null or published_at <= CURRENT_TIMESTAMP)";

        return "coalesce(
            (select published_at from {$transcriptionsTable} where id = {$contentItemsTable}.featured_transcription_id and content_item_id = {$contentItemsTable}.id and {$publishedWhere} limit 1),
            (select published_at from {$transcriptionsTable} where content_item_id = {$contentItemsTable}.id and {$publishedWhere} order by published_at desc, id desc limit 1)
        )";
    }
}
