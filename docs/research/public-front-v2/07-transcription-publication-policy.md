# Public Front v2 Research: Transcription Publication Policy

## Purpose

Plan a JSON-backed setting controlling whether more than one published transcription may exist per content item.

## Topic Scope

Policy setting, Transcription Resource, ContentItem relation manager, imports, public tabs, validation, default value, and migration implications.

## Exact Search Terms Used

- Boost: "Laravel validation unique rule scoped status parent published child records"
- Boost: "Filament relation manager validation unique per owner record"
- FilamentExamples MCP: "Filament relation manager validation unique published child records"
- FilamentExamples MCP: "Filament select featured record relation manager action"
- FilamentExamples MCP: "Filament import validation status unique per parent"

## Boost Docs Used

- Laravel validation and database schema docs.
- Filament relation manager/action/form docs via search.
- Filament import/export docs were used earlier in Prompt 10 context and current guidelines.

## FilamentExamples MCP Examples Found

- `v4/full-projects/box-score-form/app/Filament/Resources/Tournaments/RelationManagers/MatchesRelationManager.php`: relation manager forms/actions on owner records.
- `v4/full-projects/box-score-form/app/Filament/Resources/Tournaments/Pages/ManagePlayerStats.php`: custom resource page tied to a record.
- No source snippet directly covered "only one published child per parent".

## Actual Files, Classes, and Snippets Observed

- Local: `app/Models/Transcription.php` has `published()` scope and auto-feature logic for the first transcription.
- Local: `app/Models/ContentItem.php` has `featured_transcription_id`, `effectiveTranscription()`, and public scope requiring a published transcription.
- Local: `app/Livewire/Public/ContentItemTranscriptViewer.php` shows published transcription tabs only.
- Local schema: no unique constraint currently prevents multiple published transcriptions per item.

## GitHub/Source Files Inspected

- No external source with this exact policy pattern was found. Use installed Laravel/Filament docs and current PodText code as source of truth.

## Pattern To Copy

- Keep policy as a settings value with a typed reader.
- Enforce policy in forms/imports/actions, not only public rendering.
- Keep featured transcription as the public chooser.

## Pattern To Avoid

- Do not create a model just to hold the setting.
- Do not rely on public tabs alone to hide duplicates while admin/imports can create invalid state.
- Do not add a database-native enum.

## PodText Adaptation Notes

Prompt 12 already supports multiple public tabs. Changing default behavior is a product decision because it can reduce public flexibility.

## JSON-First Settings Recommendation

Store under settings JSON:

```json
{
  "transcription_policy": {
    "allow_multiple_published_transcriptions_per_item": true,
    "public_selection": "featured_then_latest"
  }
}
```

## Model/Table Considered

Rejected: a model/table for the setting. Accepted only if future policy needs per-group overrides, audit history, or editorial workflows.

## Recommended Model/Schema Options

No new schema for the setting. A partial unique index for one published transcription per item is not recommended in v1 because cross-database behavior differs and the feature should be reversible by setting.

## Recommended Filament Patterns

- SettingsPage toggle with helper text describing public tabs and imports.
- Transcription form validation that checks sibling published records when setting is disabled.
- Relation manager action to "make featured" and optionally unpublish others.
- Importer row validation that fails or resolves conflicts explicitly.

## Public Livewire/Blade Implications

If disabled, item pages can still use the transcript viewer but should show only the featured public transcription. If enabled, existing tabs remain.

## Tests

- Default setting preserves current multiple-published behavior if default is true.
- Disabled setting blocks publishing a second transcription through admin and imports.
- Featured transcription is public-selected.
- First transcription auto-feature behavior remains.
- Existing multiple-published fixtures have an explicit migration/cleanup plan before switching default false.

## Security Notes

The policy is editorial integrity, not security. Public queries must still require published item, published group, and effective/main published transcription.

## Open Questions

- Should the production default be true for flexibility or false for simplicity?
- If false, should publishing a second transcription fail or automatically unpublish the old one?
- Should existing multi-published data be flagged by Prompt 13 dashboard metrics before policy enforcement?
