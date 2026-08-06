<?php

namespace App\Support\Search;

/**
 * The one normalizer behind every search in the app.
 *
 * `LIKE` folds nothing ignorable in any utf8mb4 collation — niqqud carries zero
 * primary weight, so `=` and `LOCATE` fold it while `LIKE` walks
 * character-by-character and cannot consume a zero-weight character the pattern
 * has no counterpart for. SQLite has no analogue of the collation trick at all.
 * So instead of asking the database to fold, both sides are folded here in PHP:
 * stored text into a `*_search` shadow column, and the search term on its way
 * into the predicate. Whatever the driver, the comparison that ships is a plain
 * portable `LIKE` between two already-folded strings.
 *
 * See docs/phase-02/hebrew-search-folding-spec.md for the measurements and for
 * why the driver-branched `LOCATE` alternative was disqualified.
 */
final class HebrewSearchFold
{
    /**
     * Combining marks — niqqud (U+05B0–U+05BC, U+05BF), cantillation
     * (U+0591–U+05AF), and the shin/sin dots (U+05C1–U+05C2) all fall in
     * Unicode category Mn, along with every other language's accents. Spacing
     * punctuation an author chose deliberately — geresh, gershayim, maqaf — is
     * category Po/Pd and survives.
     */
    private const CombiningMarks = '/\p{Mn}+/u';

    public static function fold(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) preg_replace(
            self::CombiningMarks,
            '',
            mb_strtolower($value, 'UTF-8'),
        );
    }
}
