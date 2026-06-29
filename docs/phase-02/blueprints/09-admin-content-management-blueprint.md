# Prompt 09 Blueprint: Admin Content Management

## Commands

- `php artisan make:filament-resource Transcription --generate --no-interaction`
- `php artisan make:filament-resource Category --generate --no-interaction`
- `php artisan make:filament-resource HomepageSection --generate --no-interaction`
- `php artisan make:test --pest AdminPhase02ResourcesTest --no-interaction`

## Resources

### `App\Filament\Resources\Transcriptions\TranscriptionResource`

Form fields:

- Field: `Filament\Forms\Components\Select` for `content_item_id`; Docs `https://filamentphp.com/docs/5.x/forms/select`; Validation `required|exists:content_items,id`; Config `->relationship('contentItem', 'title')->searchable()->preload()->required()`.
- Field: `Filament\Forms\Components\Select` for `author_id`; Validation `required|exists:authors,id`; Config `->relationship('author', 'name')->searchable()->preload()->required()`.
- Field: `Filament\Forms\Components\TextInput` for `title`; Validation `nullable|max:255`; Config `->maxLength(255)`.
- Field: `Filament\Forms\Components\TextInput` for `language_code`; Validation `required|max:10`; Config `->default('he')->required()->maxLength(10)`.
- Field: `Filament\Forms\Components\MarkdownEditor` for `transcript_markdown`; Validation `required|string`; Config `->disableToolbarButtons(['attachFiles'])->fileAttachments(false)->columnSpanFull()`.
- Field: `Filament\Forms\Components\Select` for `status`; Validation `required`; Config `->options(App\Enums\PublicationStatus::class)->required()`.
- Field: `Filament\Forms\Components\DateTimePicker` for `published_at`; Validation `nullable|date`.

Table columns:

- Column: `Filament\Tables\Columns\TextColumn` `contentItem.title`; Config `->searchable()->sortable()`.
- Column: `Filament\Tables\Columns\TextColumn` `author.name`; Config `->searchable()->sortable()`.
- Column: `Filament\Tables\Columns\TextColumn` `status`; Config `->badge()->sortable()`.
- Column: `Filament\Tables\Columns\TextColumn` `published_at`; Config `->dateTime()->sortable()`.

Filters:

- `Filament\Tables\Filters\SelectFilter` for status.
- `Filament\Tables\Filters\SelectFilter` for author.
- `Filament\Tables\Filters\SelectFilter` for content group through item query if feasible.

Actions:

- `Filament\Actions\EditAction`.
- Optional `Filament\Actions\Action` to feature transcript; Behavior: validate same item, set `content_items.featured_transcription_id`, notify.

### `CategoryResource`

Use split `Schemas\CategoryForm` and `Tables\CategoriesTable` like existing resources.

Fields: parent select, name, slug, description Markdown, visible toggle, sort order.

### `HomepageSectionResource`

Fields: name, slug, type select, optional relationship selects, limit, sort order, visible toggle.

## Existing Resources To Modify

- `ContentItemForm`: replace legacy transcript field with featured transcription control, categories, tags, pinning, media metadata fields.
- `ContentItemsTable`: columns/filters for pinned, categories, tags, provider, effective transcription status.
- `ContentGroupForm`: category relationship and homepage order where implemented.

## Authorization

Authenticated admin panel users only. Future Shield ability names from `feature-map.md`.

## FilaCheck / Pro Guardrails

- Use `Filament\Actions\*` namespaces.
- Relationship selects must be searchable where record count can grow.
- Bulk actions should deselect records after completion where appropriate.
- Use `Heroicon` enum icons, not strings.

## Tests

- Resource smoke tests for new Resources.
- Create/edit validation.
- Feature transcription action.
- Pin controls.
- Category/tag assignment.
- Settings/homepage section management.

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
