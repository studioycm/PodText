<?php

namespace App\Models\Contracts;

use App\Models\Concerns\HasFoldedSearchColumns;

/**
 * Implemented by every model carrying `*_search` shadow columns, so the
 * backfill can be typed against the contract rather than against a trait.
 *
 * @see HasFoldedSearchColumns
 */
interface FoldsSearchColumns
{
    /**
     * Source column => shadow column. Mirrored by the migration that adds them.
     *
     * @return array<string, string>
     */
    public static function foldedSearchColumns(): array;

    /**
     * Re-derives every shadow from its source without saving.
     */
    public function refreshFoldedSearchColumns(): static;
}
