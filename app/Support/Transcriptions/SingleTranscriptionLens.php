<?php

namespace App\Support\Transcriptions;

use App\Support\PublicContent\PublicTranscriptionSelector;
use Illuminate\Database\Eloquent\Builder;

class SingleTranscriptionLens
{
    public const ADMIN_CURRENT_SCOPE = 'single-transcription-current';

    public function __construct(
        private readonly PublicTranscriptionSelector $selector,
    ) {}

    public function isActive(): bool
    {
        return ! MultiTranscriptionSurfaces::isMultiMode();
    }

    public function applyAdminCurrentScope(Builder $query): Builder
    {
        if (! $this->isActive()) {
            return $query;
        }

        return $query->withGlobalScope(
            self::ADMIN_CURRENT_SCOPE,
            fn (Builder $query): Builder => $query->whereRaw(
                'transcriptions.id = '.$this->adminCurrentTranscriptionIdSql(),
            ),
        );
    }

    public function removeAdminCurrentScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(self::ADMIN_CURRENT_SCOPE);
    }

    private function adminCurrentTranscriptionIdSql(): string
    {
        $publishedWhere = $this->selector->publishedWhereSql('single_lens_latest_published');

        return "coalesce(
            (select single_lens_featured.id
                from content_items as single_lens_items
                inner join transcriptions as single_lens_featured
                    on single_lens_featured.id = single_lens_items.featured_transcription_id
                    and single_lens_featured.content_item_id = single_lens_items.id
                where single_lens_items.id = transcriptions.content_item_id
                limit 1),
            (select single_lens_latest_published.id
                from transcriptions as single_lens_latest_published
                where single_lens_latest_published.content_item_id = transcriptions.content_item_id
                    and {$publishedWhere}
                order by single_lens_latest_published.published_at desc, single_lens_latest_published.id desc
                limit 1),
            (select single_lens_latest.id
                from transcriptions as single_lens_latest
                where single_lens_latest.content_item_id = transcriptions.content_item_id
                order by single_lens_latest.id desc
                limit 1)
        )";
    }
}
