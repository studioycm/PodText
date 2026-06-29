# Prompt 13 Blueprint: Dashboard Metrics

## Commands

- `php artisan make:filament-widget EditorialStats --stats-overview --no-interaction`
- `php artisan make:filament-widget RecentPublishedItems --table --no-interaction`
- `php artisan make:filament-widget EditorialWarnings --table --no-interaction`
- `php artisan make:test --pest DashboardMetricsTest --no-interaction`

## Widgets

### `App\Filament\Widgets\EditorialStats`

Base: `Filament\Widgets\StatsOverviewWidget`.

Stats:

- published items;
- draft items;
- pinned items;
- missing effective/main transcription;
- content groups;
- authors;
- categories;
- enabled tags.

### `RecentPublishedItems`

Base: `Filament\Widgets\TableWidget`.

Query: latest public items ordered by effective/main transcription date.

Columns:

- item title;
- group title;
- effective transcription date;
- status;
- admin edit link/action.

### `EditorialWarnings`

Base: `Filament\Widgets\TableWidget`.

Warning modes:

- missing effective/main transcription;
- missing media/embed URL;
- without category.

No polling unless explicitly justified.

## Tests

- Admin dashboard renders widgets.
- Guest cannot access admin dashboard.
- Counts are accurate.
- Warning lists include expected records.
- Resource links use Resource URLs.

## Quality Gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Prompt 06S Section Alignment

This alignment block preserves the implementation scope above while exposing the exact headings required by the active AI-context prompt.

## Goal

Implement only the prompt-specific objective described in this blueprint title and body.

## Dependencies

Complete prior prompts in sequence and read `AGENTS.md`, relevant specs, durable guidelines, and this blueprint before implementation.

## Models and migrations

Use the model and schema notes above. If this prompt is documentation-only, do not create migrations.

## Relationships and casts

Use the relationship, cast, and enum notes above; keep public visibility rules queryable and tested.

## Indexes and constraints

Add indexes, unique constraints, and foreign keys only for fields created in this prompt and queries described above.

## Filament Resources / Pages / Relation Managers / Actions

Use Filament 5 Resources, Pages, Actions, Importers, Exporters, or Widgets only where this prompt scope requires them.

## Public UI / Livewire / Blade where relevant

Use public Filament Pages, class-based Livewire, Blade components, and local Alpine only where this prompt scope requires public UI.

## Forms / tables / filters / actions

Use full Filament component namespaces, searchable relationship selects, useful filters, indicators, and Resource URL helpers.

## Import/export where relevant

Use native Filament import/export only for schema fields created by earlier prompts; never build custom CSV controllers.

## Settings/widgets where relevant

Use approved Spatie Settings for global options and simple editorial widgets only where this prompt scope requires them.

## Security

Preserve admin-only access, public draft hiding, safe Markdown rendering, HTTPS allowlisted embeds, and import formula protection.

## Out of scope

Do not implement work assigned to later prompts, install unrelated packages, run migrations in planning tasks, or add speculative infrastructure.
