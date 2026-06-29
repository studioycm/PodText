# Prompt 07 Blueprint: Transcriptions Model Revision

## Commands

- `php artisan make:model Transcription --migration --factory --no-interaction`
- `php artisan make:test --pest TranscriptionsModelTest --no-interaction`
- `php artisan make:test --pest PublicTranscriptionVisibilityTest --no-interaction`

## Models

### `App\Models\Transcription`

Attributes:

- `reference_key`: string, unique, ULID, immutable after create.
- `content_item_id`: foreign key to `content_items.id`, cascade delete.
- `author_id`: foreign key to `authors.id`.
- `title`: nullable string, max 255.
- `language_code`: string, max 10, default `he`, index.
- `transcript_markdown`: long text, required for publication.
- `status`: string, default `draft`, cast to `App\Enums\PublicationStatus`.
- `published_at`: nullable datetime, indexed with `status`.
- `word_count`: nullable unsigned integer.
- `speakers`: nullable JSON array.
- `parsed_segments`: nullable JSON array.

Relationships:

- `contentItem(): Illuminate\Database\Eloquent\Relations\BelongsTo`
- `author(): Illuminate\Database\Eloquent\Relations\BelongsTo`
- `scopePublished(Builder $query): Builder`

### `App\Models\ContentItem`

Modify:

- Add `featured_transcription_id`: nullable FK to `transcriptions.id`, null on delete.
- Add `transcriptions(): HasMany`.
- Add `featuredTranscription(): BelongsTo`.
- Add a queryable effective/main transcription strategy for public listing and sorting.

Validation rules:

- Featured transcription must belong to the same item.
- Featured transcription must be published to be effective.

## Resources

No admin Resource implementation in Prompt 07 unless required for tests. Admin UI is Prompt 09.

## Authorization

Existing admin-only panel access remains. Future ability name: `manage transcriptions`.

## Tests

- `Transcription` relationship and casts.
- `ContentItem` has many transcriptions.
- `Author` has many transcriptions.
- Backfill creates transcriptions from existing item transcripts.
- Effective/main resolution order.
- Unpublished featured transcription is ignored publicly.
- Featured transcription from another item is rejected or invalid.
- Public listings hide items without effective/main published transcription.
- Safe Markdown XSS regression uses `Transcription::transcript_markdown`.

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
