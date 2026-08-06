<?php

namespace App\Models\Concerns;

use App\Models\Contracts\FoldsSearchColumns;
use App\Support\Search\HebrewSearchFold;

/**
 * Keeps each `*_search` shadow column in step with the text it folds.
 *
 * The write hook is `setAttribute()`, not a model event. `Model::fill()` routes
 * every assignment through `setAttribute()` and `forceFill()` delegates to
 * `fill()`, so this fires on the `saveQuietly()` writes live in `app/` today —
 * which `saving`/`saved` listeners never see. It also catches Filament's
 * importers, whose `ImportColumn::fillRecord()` ends in `data_set($record, …)`
 * and so reaches `Model::__set()`.
 *
 * Hydration from the database goes through `setRawAttributes()`, not
 * `setAttribute()`, so reads pay nothing for this.
 *
 * Models using this trait declare `foldedSearchColumns()` and implement
 * {@see FoldsSearchColumns}, which is what the backfill
 * types against.
 */
trait HasFoldedSearchColumns
{
    /**
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function setAttribute($key, $value)
    {
        $result = parent::setAttribute($key, $value);

        $shadow = static::foldedSearchColumns()[$key] ?? null;

        if ($shadow !== null) {
            $this->writeFoldedSearchColumn($key, $shadow);
        }

        return $result;
    }

    /**
     * Re-derives every shadow from its source. The backfill uses this rather
     * than re-assigning the source through `setAttribute()`, which for Spatie's
     * translatable `name` would read back one locale's translation and write it
     * over the current locale.
     */
    public function refreshFoldedSearchColumns(): static
    {
        foreach (static::foldedSearchColumns() as $source => $shadow) {
            $this->writeFoldedSearchColumn($source, $shadow);
        }

        return $this;
    }

    private function writeFoldedSearchColumn(string $source, string $shadow): void
    {
        $this->attributes[$shadow] = $this->foldAttributeForSearch(
            $this->getAttributes()[$source] ?? null,
        );
    }

    /**
     * Folds the raw stored value — read back after the parent setter ran, so
     * casts and Spatie's translatable encoding have already been applied.
     */
    protected function foldAttributeForSearch(mixed $stored): ?string
    {
        if ($stored === null) {
            return null;
        }

        return HebrewSearchFold::fold((string) $stored);
    }
}
