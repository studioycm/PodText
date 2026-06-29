# Phase 02 Dashboard Metrics Spec

## Scope

Editorial widgets only. Do not add analytics, search logging, observability infrastructure, activity logs, retry dashboards, or custom operation logs.

Metrics already available from the current schema should be shown as admin dashboard widgets as early as Prompt 13. Metrics that require schema from Prompt 08 or later should be marked as available after the prompt that creates that schema.

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

Availability staging:

- Available after Prompt 07: published/draft item counts, content group count, author count, transcription counts, items with multiple transcriptions, items missing effective/main transcription, recently published items by effective transcription date.
- Available after Prompt 08: pinned item count, category count, enabled/disabled tag counts, media/embed missing warning list, without category warning list.
- Available after Prompt 09/10: admin management/import-export warning links where those Resources and actions exist.

## Filament Patterns

- `Filament\Widgets\StatsOverviewWidget`
- `Filament\Widgets\TableWidget`
- `Filament\Tables\Columns\TextColumn`
- `Filament\Actions\Action` for resource links where needed

Avoid widget polling unless a clear editorial need exists.

Dashboard date displays should use Israel/Hebrew day-first formatting:

- dates: `dd/mm/yyyy`;
- date-times: `dd/mm/yyyy HH:mm`;
- UI timezone: `Asia/Jerusalem`.

Dashboard links should use Filament Resource URLs.

## Blueprint

See `docs/phase-02/blueprints/13-dashboard-metrics-blueprint.md`.
