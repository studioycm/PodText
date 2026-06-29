# Prompt 09 Blueprint: Admin Content Management

## Commands

- `php artisan make:filament-resource Transcription --generate --no-interaction`
- `php artisan make:filament-resource Category --generate --no-interaction`
- `php artisan make:filament-resource HomepageSection --generate --no-interaction`
- `php artisan make:test --pest AdminPhase02ResourcesTest --no-interaction`

## Resources

## Shared Admin Form Rules

- Slug fields auto-generate from the relevant name/title field using current Filament v5 patterns, preferably live-on-blur / `afterStateUpdated`, and must not overwrite a manually edited slug.
- Slug labels should be Hebrew-friendly, for example `מזהה כתובת`, with helper text explaining URL use.
- Technical fields such as `reference_key`, `slug`, provider, `external_id`, metadata JSON, pin fields, language codes, parser JSON, and `featured_transcription_id` need hints, helper text, or descriptions.
- Group technical/system fields under Advanced or Technical details sections where practical.
- Date fields use `dd/mm/yyyy`.
- Date-time fields use `dd/mm/yyyy HH:mm` unless a field-specific doc defines another day-first Israeli format.
- UI timezone is `Asia/Jerusalem`; dates are stored using Laravel's normal conventions.
- Admin table date columns also use day-first Israeli/Hebrew format.
- Use translation keys for labels, helpers, hints, section headings, validation messages, date labels, and sort labels.
- Check FilamentExamples/Povilas-style slug auto-generation examples through MCP or Boost docs before implementation.

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

Slug behavior: auto-generate from `name`, allow manual override, and include helper text.

### `HomepageSectionResource`

Fields: name, slug, type select, optional relationship selects, limit, sort order, visible toggle.

Slug behavior: auto-generate from `name`, allow manual override, and include helper text.

## Relation Managers

### `ContentItemResource\RelationManagers\TranscriptionsRelationManager`

Purpose:

- Manage all `ContentItem::transcriptions()` records directly from the item edit page.
- Primary admin UX for adding/editing transcript bodies for one item.
- Do not use legacy `content_items.transcript_markdown` for new edits.
- Keep the standalone global `TranscriptionResource` for cross-item search, filtering, maintenance, and direct admin links.

Official Filament 5 API decisions:

- Create the relation manager with the current `make:filament-relation-manager` command shape and register it in `ContentItemResource::getRelations()`.
- Use `EditContentItem::hasCombinedRelationManagerTabsWithContent(): bool` returning `true` so the item form/content tab and relation manager tabs share one tab set.
- Use `EditContentItem::getContentTabComponent(): Filament\Schemas\Components\Tabs\Tab` to customize the item form tab label/icon through translation keys.
- Keep the content/form tab before relation tabs, which is Filament's default. Only override `getContentTabPosition()` if implementation proves the transcriptions tab should appear first.
- Customize `TranscriptionsRelationManager::getTabComponent(Model $ownerRecord, string $pageClass): Tab` with a translation-key label, `Heroicon` enum icon where supported, badge count, badge color/tooltip if useful, and no expensive eager count work in table closures.
- Use deferred tab badges only after verifying the relation-manager tab component supports the required `deferBadge()` keying in the installed Filament version.
- Use `Table::modifyQueryUsing()` if needed to eager-load author/featured state or apply default ordering without N+1 table closures.

Table:

- Title/fallback label.
- Author.
- Status badge.
- `published_at` formatted `dd/mm/yyyy HH:mm` in `Asia/Jerusalem`.
- Language.
- Word count.
- Featured/main indicator based on the owner item's `featured_transcription_id` and published/effective state.
- Updated at formatted `dd/mm/yyyy HH:mm` in `Asia/Jerusalem`.

Filters:

- Status.
- Author.
- Language.
- Published/draft.
- Featured/not featured.

Actions:

- Header create action for a new transcription on the current item.
- Row edit action for the transcription modal.
- Row action to set a transcription as featured/main.
- Optional duplicate/copy-as-draft action only if simple, safe, and covered by tests.
- Optional row action to open the full `TranscriptionResource` edit page with Resource URL helpers, not hard-coded admin route names.
- Keep bulk actions minimal unless a safe use case is clear.

Form:

- `author_id` searchable/preloaded relationship select.
- `title`.
- `language_code`.
- `status`.
- `published_at` with `dd/mm/yyyy HH:mm` and `Asia/Jerusalem` UI presentation.
- `transcript_markdown` Markdown editor with file attachments disabled.
- Technical/derived fields such as `reference_key`, `word_count`, `speakers`, and `parsed_segments` hidden, read-only, or placed under an advanced section only where needed.
- Do not include `content_item_id`; relation manager owner context supplies the item.

Rules:

- A transcription created through the relation manager is automatically attached to the current content item.
- The featured action must validate that the transcription belongs to the current item.
- Only a published transcription can become publicly effective. If an unpublished transcription can be selected as featured for editorial workflow, the UI must clearly explain that public effective resolution falls back to the latest published transcription.
- Draft transcriptions must never appear publicly.
- Date-time fields use `dd/mm/yyyy HH:mm` and `Asia/Jerusalem`.
- All labels, hints, helper text, section headings, tab labels, tab tooltips, actions, notifications, and validation messages use translation keys.
- The item form must not reintroduce or write to the legacy `content_items.transcript_markdown` field.

Tests:

- Relation manager renders on the item edit page.
- Relation manager table sees only the owner item's transcriptions.
- Admin can create a transcription for an item without submitting `content_item_id`.
- Admin can edit a transcription.
- Admin can set a published same-item transcription as featured/main.
- Featured action rejects or hides invalid cross-item choices.
- Draft transcription is not public.
- Item form no longer writes to the legacy transcript field.
- Combined item-details/transcriptions tabs render with the researched tab labels, icon, and badge behavior.

### Relation page and Repeater decision

- Use the relation manager for Prompt 09 item-scoped transcription CRUD.
- Do not use a `Repeater` for full transcript Markdown; the child form is too large for nested inline rows.
- Defer a dedicated `ManageRelatedRecords` page until transcript management needs separate sub-navigation, larger editing workspace, bulk transcript workflows, or future studio-style tooling.

## Standalone Resource Redirects

- Standalone Prompt 09 Create pages should redirect to their Resource index after successful create by overriding `getRedirectUrl()` and returning `$this->getResource()::getUrl('index')`, unless a Resource-specific reason to continue editing is documented.
- Standalone Prompt 09 Edit pages should redirect to their Resource index after save for list-driven admin maintenance unless the page is designed for continued owner-scoped work.
- `EditContentItem` may stay on the edit page after save because relation manager work continues there.
- Relation manager create/edit actions should stay on the owner item edit page.
- Disable "create another" for standalone transcript create pages and transcript relation-manager create modals unless repeated creation is intentionally tested.

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
- `ContentItemResource` transcriptions relation manager rendering, create, edit, filtering, featured action, and owner scoping.
- Combined relation manager/content tabs and researched tab customization.
- Standalone Create/Edit redirect behavior and disabled create-another behavior where specified.
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
