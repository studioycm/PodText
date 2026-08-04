<?php

namespace App\Models\Concerns;

use App\Enums\PublicationStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * One home for the publication-date rules shared by every model carrying a
 * `status` + `published_at` pair (ContentItem, ContentGroup, Transcription).
 *
 * publish-stamps-date (operator rule 2026-08-04): saving a record as
 * published with a null publish date stamps it with now. An explicitly
 * provided date is never overwritten, and unpublishing keeps the date.
 *
 * effective-published-date (operator rule 2026-08-05): where a publish date
 * is read, published rows with a null date resolve to `created_at` —
 * read-side only, no backfill.
 */
trait InteractsWithPublicationDate
{
    public static function bootInteractsWithPublicationDate(): void
    {
        static::saving(function (Model $model): void {
            if ($model->status === PublicationStatus::Published && blank($model->published_at)) {
                $model->published_at = now();
            }
        });
    }

    protected function effectivePublishedAt(): Attribute
    {
        return Attribute::get(fn (): ?CarbonInterface => $this->published_at
            ?? ($this->status === PublicationStatus::Published ? $this->created_at : null));
    }

    public function scopeOrderByEffectivePublishedAt(Builder $query, string $direction = 'desc'): Builder
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderByRaw(
                "coalesce(published_at, case when status = ? then created_at end) {$direction}",
                [PublicationStatus::Published->value],
            )
            ->orderBy('id', $direction);
    }
}
