# Phase 02 Dashboard Metrics Spec

## Scope

Editorial widgets only. Do not add analytics, search logging, observability infrastructure, activity logs, retry dashboards, or custom operation logs.

## Widgets

Prompt 13 should add:

- published items count;
- draft items count;
- pinned items count;
- items with multiple transcriptions;
- items missing effective/main transcription;
- content group count;
- author count;
- category count;
- enabled/disabled tag counts;
- recently published items list;
- items missing media/embed URL warning list;
- items without category warning list;
- transcriptions by author.

## Filament Patterns

- `Filament\Widgets\StatsOverviewWidget`
- `Filament\Widgets\TableWidget`
- `Filament\Tables\Columns\TextColumn`
- `Filament\Actions\Action` for resource links where needed

Avoid widget polling unless a clear editorial need exists.

## Blueprint

See `docs/phase-02/blueprints/13-dashboard-metrics-blueprint.md`.
