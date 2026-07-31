<?php

namespace App\Filament\Widgets\Concerns;

use Illuminate\Contracts\View\View;

/**
 * Filament's lazy widgets ship a screen-reader-only "Loading..." and nothing
 * visible, so an un-hydrated board reads as broken rather than as pending —
 * convincingly enough that it was mistaken for a regression during review.
 * A shaped skeleton says "this is a widget, it is coming".
 */
trait ShowsLoadingSkeleton
{
    /** @param array<string, mixed> $params */
    public function placeholder(array $params = []): View
    {
        return view('filament.widgets.partials.widget-skeleton', [
            'rows' => $this->skeletonRows(),
        ]);
    }

    /** How many shimmer rows this widget's shape needs. */
    protected function skeletonRows(): int
    {
        return 3;
    }
}
