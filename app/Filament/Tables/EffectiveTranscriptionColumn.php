<?php

namespace App\Filament\Tables;

use App\Support\Transcriptions\EffectiveTranscriptionResolver;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * A column that reads a whole transcription model, and loads it itself.
 *
 * Filament eager-loads for a visible column whose name contains a dot. These
 * columns are `state()` closures on plain names, so the framework cannot see
 * through them — which is why the table used to carry a hand-maintained list
 * of column names and prime the relations for all of them from the query
 * scope. Two files coupled by a string, and a new consumer that forgot to
 * join the list rendered blank badges to every admin, forever, suite green.
 *
 * `applyEagerLoading()` is public, receives the real builder, and Filament
 * calls it once per VISIBLE column (HasRecords::filterTableQuery). That loop
 * is the visibility test the constant was doing by hand — written by the
 * framework, keyed on the column object rather than its name. Vendor's own
 * SelectColumn overrides it for the same reason.
 *
 * Self-guarded on visibility because bulk actions iterate getColumns(), not
 * the visible ones, so an unguarded override would load on the select query
 * for a column nobody switched on.
 */
class EffectiveTranscriptionColumn extends TextColumn
{
    public function applyEagerLoading(EloquentBuilder|Relation $query): EloquentBuilder|Relation
    {
        if ($this->isToggledHidden()) {
            return parent::applyEagerLoading($query);
        }

        foreach (app(EffectiveTranscriptionResolver::class)->eagerLoadPaths() as $path) {
            if (! array_key_exists($path, $query->getEagerLoads())) {
                $query = $query->with($path);
            }
        }

        return parent::applyEagerLoading($query);
    }
}
