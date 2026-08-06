<?php

namespace App\Filament\Support;

use App\Support\Search\FoldedSearch;
use Closure;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Hebrew-folded table search for Filament columns, defined once instead of at
 * eighty call sites.
 *
 * `InteractsWithTableQuery::applySearchConstraint()` short-circuits the entire
 * vendor emitter the moment `$this->searchQuery` is set, so passing this
 * closure to `->searchable(query: …)` takes ownership of the predicate.
 *
 * The closure needs no arguments at the call site: Filament injects the column
 * itself into any parameter named `$column` (its `$evaluationIdentifier`), so
 * the shadow column is derived from the column's own name. It stays a factory
 * rather than a `Column::macro()` so the literal `->searchable(` survives in
 * source — FilaCheck scans statically, and a renamed macro reads to it as a
 * table with no searchable columns at all.
 */
final class FoldedTableSearch
{
    /**
     * @param  string|null  $against  Column to compare the folded term with.
     *                                Defaults to the shadow of the column's own
     *                                name; pass a column that is already its
     *                                own fold — a slug — to use it directly.
     */
    public static function query(?string $against = null): Closure
    {
        return function (Builder $query, string $search, Column $column) use ($against): Builder {
            $target = $against ?? FoldedSearch::column($column->getName());
            $relationship = str_contains($target, '.') ? Str::beforeLast($target, '.') : null;
            $name = Str::afterLast($target, '.');
            $like = FoldedSearch::pattern($search);

            return $relationship === null
                ? $query->where($query->getModel()->qualifyColumn($name), 'like', $like)
                : $query->whereRelation($relationship, $name, 'like', $like);
        };
    }
}
