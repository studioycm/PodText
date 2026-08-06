<?php

namespace App\Support\Search;

/**
 * The single seam every search call site routes through.
 *
 * A fix applied at one call site leaves the other twelve broken and diverges on
 * the next edit, so the term-folding and the shadow-column naming both live
 * here. Nothing in this class touches the database: it produces the pattern and
 * the column name, and the caller keeps its own query shape.
 */
final class FoldedSearch
{
    /** Suffix identifying a shadow column, matching the migration. */
    public const ShadowSuffix = '_search';

    /**
     * A `LIKE` pattern for a user-typed term, folded so it can be compared
     * against an equally folded shadow column.
     */
    public static function pattern(string $search): string
    {
        return '%'.HebrewSearchFold::fold(trim($search)).'%';
    }

    /**
     * The shadow column holding the fold of `$column`. Accepts a qualified name
     * — `content_items.title` yields `content_items.title_search`.
     */
    public static function column(string $column): string
    {
        return $column.self::ShadowSuffix;
    }

    /**
     * The in-PHP equivalent of the shipped predicate, for the one call site
     * that filters a hydrated Collection instead of issuing SQL.
     */
    public static function contains(?string $haystack, string $search): bool
    {
        return str_contains(
            HebrewSearchFold::fold($haystack),
            HebrewSearchFold::fold(trim($search)),
        );
    }
}
