<?php

/**
 * Partial override of the FilaCheck Pro defaults.
 *
 * Only the keys named here are overridden. Both of the package's config
 * readers default a missing rule to enabled — `config(…, true)` under the
 * container and `$config[$name]['enabled'] ?? true` standalone — so every
 * other rule keeps its packaged setting.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | table-without-searchable-columns
    |--------------------------------------------------------------------------
    |
    | Disabled because it cannot see through a macro, not because the tables
    | are unsearchable. TableWithoutSearchableColumnsRule.php:137 decides with
    | `preg_match('/->searchable\s*\(/', $snippet)` — a plain regex over the
    | source — so `->foldedSearchable()`, which is the Hebrew-folding macro
    | that calls `->searchable(query: …)` underneath, reads to it as no
    | searchable column at all. Six genuinely searchable tables were reported
    | as having none.
    |
    | This is a UX suggestion rather than a deprecation or an error, and a
    | false negative in a static heuristic is not a reason to change working
    | code. The signal it provided is replaced, macro-aware, by
    | tests/Feature/AdminTableSearchabilityTest.php.
    |
    */
    'table-without-searchable-columns' => [
        'enabled' => false,
    ],
];
