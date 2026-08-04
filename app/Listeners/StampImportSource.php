<?php

namespace App\Listeners;

use App\Enums\ImportConnectionProvider;
use Filament\Actions\Imports\Events\ImportStarted;

/**
 * Registered by the framework's listener auto-discovery (the handle()
 * type-hint) — an explicit Event::listen here would DOUBLE-register it,
 * verified via event:list during implementation.
 */
final class StampImportSource
{
    public function handle(ImportStarted $event): void
    {
        // The Filament import modal is the only producer of this event
        // today, and the modal IS the manual process: stamp manual
        // unconditionally (operator override 2026-08-04 — source is
        // process-decided, never user-declared). Future programmatic
        // producers (WB fetch runs) write their own provider at creation
        // and never rely on this listener.
        $name = $event->getOptions()['name'] ?? null;

        // raw-state: the name is browser-supplied free text — narrow it.
        $name = is_string($name) ? trim($name) : '';

        // Fires saved → EditorialMetricsCacheObserver invalidates (Task 2).
        $event->getImport()->forceFill([
            'provider' => ImportConnectionProvider::Manual->value,
            'name' => ($name !== '' && mb_strlen($name) <= 120) ? $name : null,
        ])->save();
    }
}
