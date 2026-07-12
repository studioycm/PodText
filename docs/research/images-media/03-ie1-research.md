# IE-1 Research - Relation Import Modes And Tag Export Scope

Date: 2026-07-13

## Scope

This note supports `prompts/pre-13-prompts/import-relations-ie1-codex-prompt.md`.
It implements only IE-1 plus the carried Job 0 corrections from that prompt. No
Composer changes are allowed.

Yoni's binding decisions override the older IMG-R/track-plan wording:

- Relation import modes are `replace` and `add_only` only. The older proposed
  `merge` mode is dropped.
- The default relation mode is `replace`.
- Blank relation cells leave existing categories, tags, and transcribers unchanged
  in both modes. There is no clear-via-import in v1.
- Content item tag export defaults to `enabled_only`; `all_tags` remains available
  for admin full-fidelity exports.
- Disabled content tags in an imported row warn and skip only those tags; the row
  still imports and enabled tags in the same row still attach.

## Preflight Findings

- `git status --short --branch` was clean on `main...origin/main [ahead 1]`.
- Recent history includes TOOLS1 at `a6d6408 feat: add admin tools page and
  spotify links fetcher`, directly after the IE-1 prompt commit.
- The next prompt has not started; Prompt 13 remains blocked/not started in
  `docs/phase-02/current-project-state.md`.
- `rg` is unavailable in this environment, so discovery used `grep` and `find`.

## Source Documents Read

- `docs/phase-02/images-media-track-plan.md`
- `docs/research/images-media/00-images-media-research.md`, Job 6
- `.ai/guidelines/import-export.md`
- `.ai/guidelines/media-embeds.md`
- `.ai/guidelines/tooling-quality.md`
- `.ai/guidelines/taxonomy-tags.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/admin-tools-tools1-handoff.md`
- `docs/phase-02/maintenance-form-mp2-handoff.md`

## Laravel Boost Findings

Laravel Boost `search_docs` was available and used for Filament 5 import/export
options and completion notifications.

Relevant installed-version guidance:

- Importers can expose modal options through `getOptionsFormComponents()` and read
  them through `$this->options`.
- Exporters can expose options through `getOptionsFormComponents()` and export
  column closures can receive `array $options`.
- Import completion notifications can be customized by overriding
  `getCompletedNotificationBody()` or `modifyCompletedNotification()`.
- The `Import` model exposes selected options at notification time through
  `$import->getOptions()` when Filament's import action finishes.

PodText adaptation:

- Use the existing shared `ConfiguresContentImports::getOptionsFormComponents()`
  for the relation mode select.
- Append disabled-tag warnings to the existing localized import completion body.
- Use a content-item exporter option for tag scope and make the export column choose
  `enabledContentTags` or `contentTags` from eager-loaded relations.

## FilamentExamples Findings

FilamentExamples MCP was available with `search_examples` only. No read/fetch/source
detail tool was exposed.

Queries used:

- `Filament Importer getOptionsFormComponents select option`
- `Filament Importer completed notification body warning`
- `Filament Exporter getOptionsFormComponents toggle`
- `Filament ExportColumn formatStateUsing options`
- `Filament import CSV warnings notification body`
- `Filament importer lifecycle afterSave sync relation`
- `Filament export options toggle relation eager load`
- `Filament import Select options helperText`

Useful returned examples:

- `v4/full-projects/stock-management/app/Filament/Imports/ItemImporter.php`
  showed a native importer overriding `getCompletedNotificationBody()`.
- `v4/full-projects/stock-management/app/Filament/Exports/ItemExporter.php`
  showed a native exporter overriding completion notification copy.
- `v4/full-projects/stock-management/app/Filament/Resources/Items/Pages/ListItems.php`
  showed `ImportAction` and `ExportAction` mounted together on a resource list page.
- `v4/tables/public-products-table/app/Filament/Exports/ProductExporter.php`
  showed exporter columns and completion notification body customization.

Patterns to copy:

- Keep notification customization inside importer/exporter classes rather than
  replacing Filament jobs.
- Use native import/export option mechanisms instead of custom controllers.

Patterns to avoid:

- No custom CSV controller.
- No per-row warning UI claim: native Filament imports do not expose successful-row
  warning rows, so IE-1 uses a translated completion notification summary.

## Local Code Findings

- `ContentGroupImporter` syncs `category_paths` with `$record->categories()->sync()`.
- `ContentItemImporter` syncs `category_paths` with `$record->categories()->sync()`.
- `ContentItemImporter` resolves only enabled `content` tags and currently fails
  disabled tags as unresolved.
- `ContentItemImporter` currently calls `$record->tags()->sync()` for
  `content_tag_slugs`, which can detach disabled content tags and unrelated tag
  types.
- `TranscriptionImporter` resolves transcriber reference keys and names, sets
  `author_id` before save when transcribers resolve, then calls
  `Transcription::syncTranscribers()` after save.
- `Transcription::syncTranscribers()` replaces the pivot set and synchronizes legacy
  `author_id` to the first author.
- `ContentItemExporter` currently exports `content_tag_slugs` from `contentTags`,
  so disabled tags are included by default.
- `ContentItemExporter::modifyQuery()` eager-loads `contentTags` but not
  `enabledContentTags`.
- `config/curator.php` reads `env('CURATOR_GLIDE_TOKEN')` without fallback.
- No tracked `.env.example` currently exists, so Job 0 needs to create one.

## Warning Mechanism Decision

Native Filament imports can customize the completed notification body but do not
provide successful-row warnings in failed-row CSVs. IE-1 will therefore aggregate
disabled content tag names per import and append a translated skipped-tags summary
to the import completion notification body.

The row still succeeds. Missing tags and wrong-type tags remain validation failures.

## Test Targets

- Relation option defaults and translated option controls.
- `replace` and `add_only` relation behavior for group categories, item categories,
  item tags, and transcribers.
- Blank relation cells leave existing relations untouched in both modes.
- Disabled content tags are skipped with a surfaced warning while enabled tags on
  the same row attach.
- Default tag export excludes disabled tags; `all_tags` includes them.
- Content tag export remains N+1-free for both tag scopes.
- Default export then import does not silently detach existing disabled tags.
- Curator Glide token config resolves a non-empty fallback when the dedicated env
  variable is unset.
