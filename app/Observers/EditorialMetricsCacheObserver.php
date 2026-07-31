<?php

namespace App\Observers;

use App\Support\Dashboard\EditorialMetrics;
use Illuminate\Database\Eloquent\Model;

/**
 * The dashboard trades polling for a 60-second cache, which is fine until an
 * editor publishes something and the board still shows the old number. Any
 * write to the models the snapshot counts forgets it, so the board can never
 * contradict a change the editor just made. The TTL stays as a backstop.
 */
class EditorialMetricsCacheObserver
{
    public function saved(Model $model): void
    {
        $this->forget();
    }

    public function deleted(Model $model): void
    {
        $this->forget();
    }

    private function forget(): void
    {
        app(EditorialMetrics::class)->forget();
    }
}
