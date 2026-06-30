# Prompt 11 Blueprint: Public Homepage and Search

## Commands

- `php artisan make:livewire Public/ContentItemSearch --no-interaction`
- `php artisan make:test --pest PublicHomepageSearchTest --no-interaction`

## Public Page/Component

Create/update:

- `App\Filament\Public\Pages\BrowseContentItems` or replace root `BrowseContentGroups` behavior.
- `App\Livewire\Public\ContentItemSearch`.
- Blade card view for public item result.

## Query Rules

Base query:

- `ContentItem` only.
- published item.
- published group.
- effective/main published transcription exists.
- eager load group, authors, categories, tags, effective transcription.

Sort `latest_transcription` by effective/main transcription `published_at`.

Homepage order is one combined pinned-first/latest `ContentItem` list. Default layout may mix pinned cards and latest rows, but all records remain `ContentItem` records.

Prompt 11 must read `App\Settings\PublicContentSettings` for global defaults and limits, and must read visible ordered `HomepageSection` records for homepage content slices. Homepage sections decide which latest/category/tag/content-group slices appear; settings provide defaults such as limits, layout, and sort. Item pinning remains separate ordering behavior.

## Filament Table Plan

Component:

- `Filament\Tables\Table`, Docs `https://filamentphp.com/docs/5.x/tables`
- Livewire implements `Filament\Tables\Contracts\HasTable`, `Filament\Forms\Contracts\HasForms`, `Filament\Actions\Contracts\HasActions`.

Columns:

- `Filament\Tables\Columns\ViewColumn` for item card; Config `->view('filament.tables.columns.public-content-item-card')`.
- Searchable backing columns: item title, group title, enabled tags, categories.
- Search result cards use a consistent card grid.
- Content group badge shows cover image when available and initials/title fallback otherwise.

Filters:

- `Filament\Tables\Filters\SelectFilter` category; searchable/preload.
- `Filament\Tables\Filters\SelectFilter` tag; enabled content tags only.
- `Filament\Tables\Filters\SelectFilter` group; searchable/preload.
- `Filament\Tables\Filters\SelectFilter` author; searchable/preload.
- `Filament\Tables\Filters\Filter` date ranges with `Filament\Forms\Components\DatePicker`.
- `Filament\Tables\Filters\Filter` duration range with `Filament\Forms\Components\TextInput`.

Default text search fields are item title, content group title, enabled tag names, and category names.

Author and provider are cheap metadata dropdown filters, not default full-text search fields.

Transcript body search is explicit/deferred and not default live table search.

Date filters and result date displays use:

- `dd/mm/yyyy` for dates;
- `dd/mm/yyyy HH:mm` for date-times;
- `Asia/Jerusalem` UI timezone.

Sort labels must use translation keys and be Hebrew-first.

Filter layout:

- desktop above content/modal as selected by final UI.
- mobile drawer via Blade/Alpine if needed.
- active indicators for custom filters.

## Public Routes

- `/` homepage item search/listing.
- `/search` optional alias if needed.
- `/categories/{categorySlug}`.
- `/tags/{tagSlug}`.

Category and tag landing pages reuse the same public item-card component as search results.

The homepage should use enabled content tags only and should ignore deferred `curated_query` sections until a concrete query-builder spec is implemented.

Required UX:

- search result count;
- sort dropdown;
- clear filters;
- URL-backed search/sort/filter state where practical.

## Tests

- Guest access.
- Draft/no-transcription hidden.
- Search default fields.
- Tag disabled hidden.
- Category descendant filter.
- Sort options.
- Pinned order.
- URL state.
- RTL markers.

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
