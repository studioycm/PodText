# Prompt 08 Blueprint: Taxonomy, Tags, Pinning, Settings, and Media Foundation

## Commands

- `php artisan make:model Category --migration --factory --no-interaction`
- `php artisan make:model HomepageSection --migration --factory --no-interaction`
- `php artisan make:test --pest TaxonomyTagsPinningSettingsTest --no-interaction`

Spatie Tags, the Filament Spatie Tags plugin, Spatie Settings, and the Filament Spatie Settings plugin are approved for Phase 02 implementation. If they are absent when Prompt 08 runs, Prompt 08 owns adding them as part of that implementation task. Do not ask for package approval again.

## Models

### `App\Models\Category`

Fields:

- `parent_id`: nullable FK to categories, null on delete.
- `name`: string, required.
- `slug`: string, unique within parent or globally as implementation chooses in tests.
- `description_markdown`: nullable text.
- `is_visible`: boolean default true, index.
- `sort_order`: integer default 0, index.

Relationships:

- `parent(): BelongsTo`
- `children(): HasMany`
- `contentGroups(): BelongsToMany`
- `contentItems(): BelongsToMany`
- descendant helper/scope for filters.

Pivots:

- `category_content_group(category_id, content_group_id)`.
- `category_content_item(category_id, content_item_id)`.

### `App\Models\ContentItem`

Pinning fields:

- `is_pinned`: boolean default false, index.
- `pinned_at`: nullable datetime.
- `pinned_until`: nullable datetime.
- `pin_order`: unsigned integer nullable.

Media foundation fields:

- `embed_provider`: nullable string, max 50, index.
- `media_duration_seconds`: nullable unsigned integer.
- `external_id`: nullable string, indexed with provider.
- `external_title`: nullable string.
- `external_description`: nullable text.
- `external_thumbnail_url`: nullable string max 2048.
- `external_published_at`: nullable datetime.
- `media_metadata`: nullable JSON.
- `direct_media_url`: nullable string max 2048.

Relationships:

- `categories(): BelongsToMany`.

### `App\Models\ContentGroup`

Fields:

- optional `homepage_order`: nullable integer if homepage group ordering is implemented.

Relationships:

- `categories(): BelongsToMany`.

### Tags

Use Spatie's tag model/taggables. Do not create a duplicate tag pivot. Custom tag model fields:

- `is_enabled`: boolean default false.
- `enabled_at`: nullable datetime.
- `enabled_by_id`: nullable FK to users.
- `created_by_id`: nullable FK to users.
- optional future moderation state string.

Tag type: `content`.

### Settings

Use typed settings class, for example `App\Settings\PublicContentSettings`:

- `homepage_item_limit`: integer default 12.
- `pinned_item_limit`: integer default 6.
- `default_public_sort`: string default `latest_transcription`.
- `default_result_layout`: string default `cards`.
- `show_latest_section`: boolean default true.
- `item_page_layout`: string default `standard`.

### `App\Models\HomepageSection`

Fields:

- `name`: string.
- `slug`: unique string.
- `type`: string enum-like value.
- `category_id`, `tag_id`, `content_group_id`: nullable FKs.
- `limit`: unsigned integer default 6.
- `sort_order`: integer default 0.
- `is_visible`: boolean default true.

## Filament Primitive Plan

Field examples for Prompt 09:

- Field: `Filament\Forms\Components\TextInput`, Docs `https://filamentphp.com/docs/5.x/forms/text-input`, Validation `required|max:255`, Config `->required()->maxLength(255)`.
- Field: `Filament\Forms\Components\Select`, Docs `https://filamentphp.com/docs/5.x/forms/select`, Validation `nullable|exists`, Config `->relationship(...)->searchable()->preload()`.
- Field: `Filament\Forms\Components\Toggle`, Docs `https://filamentphp.com/docs/5.x/forms/toggle`, Validation `boolean`, Config `->default(false)`.
- Column: `Filament\Tables\Columns\TextColumn`, Docs `https://filamentphp.com/docs/5.x/tables/columns/text`, Config `->searchable()->sortable()`.
- Filter: `Filament\Tables\Filters\SelectFilter`, Docs `https://filamentphp.com/docs/5.x/tables/filters/select`, Config `->relationship(...)->searchable()->preload()`.

## Tests

- Category hierarchy and descendant filters.
- Group category inheritance by item.
- Item direct categories.
- Pin validity and expiration scopes.
- Pin ordering.
- Media URL validation for new fields.
- Settings defaults.
- Spatie tag `content` type and enabled-only public visibility.

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
