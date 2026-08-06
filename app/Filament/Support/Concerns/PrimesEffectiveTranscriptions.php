<?php

namespace App\Filament\Support\Concerns;

use App\Models\ContentItem;
use App\Support\Transcriptions\EffectiveTranscriptionResolver;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * The second half of the effective-transcription two-pass.
 *
 * `EffectiveTranscriptionColumn` eager-loads the featured branch at
 * query-build time, because that is the only seam a column has. The fallback
 * branch cannot be loaded there without loading it for every row — and
 * measured on this library, 0 of 10 rows ever read it, so 3 of 7 relation
 * queries were hydrating rows nobody looked at.
 *
 * `getTableRecords()` is the seam. It is public, and Filament memoises its
 * result into `$cachedTableRecords`, so overriding it primes exactly once per
 * render no matter how many times the table asks for its rows.
 *
 * REQUIRED, not optional: `forLoaded()` deliberately does not lazy-load, so a
 * page that renders the column without this trait raises a lazy-loading
 * violation the first time a row falls back. That is the intended failure —
 * loud in tests rather than an N+1 in production — and the reason a test pins
 * every consumer of the column against this trait.
 */
trait PrimesEffectiveTranscriptions
{
    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        $records = parent::getTableRecords();

        $items = $records instanceof Collection
            ? $records
            : collect($records->items());

        app(EffectiveTranscriptionResolver::class)->primeFallback(
            $items->filter(fn (mixed $record): bool => $record instanceof ContentItem),
        );

        return $records;
    }
}
