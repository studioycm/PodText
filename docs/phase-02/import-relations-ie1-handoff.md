# Import Relations IE-1 Handoff

Date: 2026-07-13

## Scope

Executed only `prompts/pre-13-prompts/import-relations-ie1-codex-prompt.md`.
No Composer changes were made.

IE-1 adds native Filament import relation modes and content-item tag export scope:

- Relation import modes are `replace` and `add_only` only.
- The default relation import mode is `replace`.
- Blank relation cells leave existing categories, content tags, and transcribers
  unchanged in both modes.
- Content item tag exports default to enabled content tags only and can opt into
  all content tags.
- Disabled content tags in imported rows are skipped with a completion warning;
  the row still succeeds and enabled tags in the same row still attach.

## Requirements Sweep

1. Preflight: completed before code. Worktree was clean on `main...origin/main
   [ahead 1]`, recent commits included TOOLS1 at `a6d6408`, and Prompt 13 had not
   started.
2. Research first: implemented. Added
   `docs/research/images-media/03-ie1-research.md` and
   `docs/research/images-media/03-ie1-implementation-plan.md` before code.
3. Laravel Boost: used for installed-version Filament importer/exporter option
   and notification behavior.
4. FilamentExamples MCP: used before Filament import/export changes; access was
   search/snippet only, no source/detail tool exposed.
5. Job 0 Curator Glide token fallback: implemented through
   `env('CURATOR_GLIDE_TOKEN', env('APP_KEY'))`, `.env.example`, deploy notes,
   and a config fallback test.
6. TOOLS1 hash backfill: implemented with `a6d6408`.
7. MP2 handoff gap: closed as documented without inventing unrecoverable suite
   counts.
8. Shared import option: implemented in the existing native Filament importer
   options form.
9. ContentGroup categories: implemented for `replace`, `add_only`, default
   replace, and blank-cell preserve.
10. ContentItem categories: implemented for `replace`, `add_only`, default
    replace, and blank-cell preserve.
11. ContentItem tags: implemented for enabled-tag replacement/add-only behavior,
    disabled-tag skip warning, unrelated tag preservation, and blank-cell
    preserve.
12. Transcription transcribers: implemented for `replace`, `add_only`,
    `author_id` compatibility, and blank-cell preserve.
13. Content item tag export scope: implemented with enabled-only default and
    all-tags option, with eager loading for both relations.
14. Round trip: implemented. Default enabled-only export/import preserves existing
    disabled content tags instead of silently dropping them.
15. Image uploads, zip import packages, remote media fetching, Composer changes,
    and any other prompt step: not applicable/out of scope.

## Files Changed

- `app/Enums/RelationImportMode.php`
- `app/Enums/ContentItemTagExportScope.php`
- `app/Filament/Imports/Concerns/ConfiguresContentImports.php`
- `app/Filament/Imports/ContentGroupImporter.php`
- `app/Filament/Imports/ContentItemImporter.php`
- `app/Filament/Imports/TranscriptionImporter.php`
- `app/Filament/Exports/ContentItemExporter.php`
- `config/curator.php`
- `.env.example`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `tests/Feature/Phase02ImportExportTest.php`
- `tests/Feature/ImageMediaCuratorTest.php`
- `docs/research/images-media/03-ie1-research.md`
- `docs/research/images-media/03-ie1-implementation-plan.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/images-media-track-plan.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/admin-tools-tools1-handoff.md`
- `docs/phase-02/maintenance-form-mp2-handoff.md`
- `docs/phase-02/ai-development-lessons.md`
- `docs/phase-02/import-relations-ie1-handoff.md`

## Tests Added Or Updated

- Added relation-mode tests for ContentGroup categories, ContentItem categories,
  ContentItem tags, and Transcription transcribers.
- Added disabled content tag warning/skip coverage using Filament's import job.
- Added content-item tag export scope coverage for enabled-only and all-tags
  modes under `Model::preventLazyLoading()`.
- Added default export/import round-trip coverage proving existing disabled tags
  are preserved.
- Updated the old disabled-tag import expectation from validation failure to
  warning/skip behavior.
- Added Curator Glide token fallback coverage.

## Commands Run

Iteration commands already run:

- `pwd && git status --short --branch && git log --oneline -8` - passed; clean
  preflight with TOOLS1 in recent history.
- `php artisan tinker --execute="..."` - passed; confirmed
  `config('curator.glide_token')` and `config('app.key')` were non-empty without
  recording secret values here.
- `php -l` on edited PHP app/test files - passed.
- `php artisan test tests/Feature/Phase02ImportExportTest.php --filter="imports category relation modes|imports content tag relation modes|imports transcriber relation modes"` - passed, 3 tests, 25 assertions.
- `php artisan test tests/Feature/ImageMediaCuratorTest.php --filter="resolves a non empty curator glide token fallback"` - passed, 1 test, 1 assertion.
- `php artisan test tests/Feature/Phase02ImportExportTest.php` - passed, 14 tests, 118 assertions.
- Token-level duplicate-key scan for `lang/en/admin.php` and `lang/he/admin.php`
  - passed; no duplicate literal array keys found.

- Final requirements sweep:
  `git diff --check && git status --short && git diff --quiet -- composer.json composer.lock package.json package-lock.json pnpm-lock.yaml yarn.lock`
  - passed. No whitespace errors and no Composer/npm dependency or lockfile
  changes.
- Final Pint run 1: `vendor/bin/pint --test` - failed on
  `app/Filament/Imports/TranscriptionImporter.php` style only
  (`unary_operator_spaces`, `not_operator_with_successor_space`,
  `ordered_imports`).
- Style correction: `vendor/bin/pint app/Filament/Imports/TranscriptionImporter.php`
  - fixed the reported style issues.
- Final Pint run 2 after re-entering from Pint:
  `vendor/bin/pint --test` - passed.
- Final FilaCheck: `vendor/bin/filacheck` - passed with 0 issues.
- Final frontend build: `npm run build` - passed.
- Final full suite last: `php artisan test` - passed, 444 tests, 3,971
  assertions, 484.723s.

## FilaCheck Result

`vendor/bin/filacheck` passed with 0 issues.

## Deploy Notes

- Add `CURATOR_GLIDE_TOKEN` to production if Curator Glide signatures should use
  a stable token separate from `APP_KEY`.
- If `CURATOR_GLIDE_TOKEN` is unset, Curator Glide now falls back to `APP_KEY`.
- After changing the production env value, run the normal config-cache deploy step.

## Commit Hash

Pending final local commit: `feat: add relation import modes and tag export scope`.

## Local Front Check Report

1. MANUAL: Open an admin Content Groups import action and confirm the import modal
   shows `Relation import mode` with `Replace` selected by default and `Add only`
   available.
2. MANUAL: Import a Content Group CSV row with an existing group, one new
   `category_paths` value, and default relation mode; confirm existing categories
   are replaced by the provided category.
3. MANUAL: Re-import the same group with `Add only`; confirm the provided category
   is attached without detaching the existing category.
4. MANUAL: Re-import the same group with a blank `category_paths` cell in both
   modes; confirm categories are unchanged.
5. MANUAL: Repeat the category checks on the Content Items import action.
6. MANUAL: Import a Content Item row containing one enabled content tag and one
   disabled content tag; confirm the row succeeds, the enabled tag attaches, the
   disabled tag does not attach, and the completion notification warns about the
   skipped disabled tag.
7. MANUAL: Export Content Items with default tag scope; confirm disabled content
   tags are absent from `content_tag_slugs`.
8. MANUAL: Export Content Items with `All tags`; confirm disabled content tags are
   present in `content_tag_slugs`.
9. MANUAL: Import a Transcription row with replacement transcribers; confirm the
   transcriber pivot and compatibility `author_id` point at the new primary
   transcriber.
10. MANUAL: Import the same Transcription with `Add only`; confirm the new
    transcriber is appended and the existing primary remains primary.
11. MANUAL: Import the same Transcription with blank transcriber cells in both
    modes; confirm transcribers are unchanged.
12. MANUAL: Check Hebrew and English labels for the new import relation mode,
    export tag scope, and disabled-tag warning copy.

## Assumptions

- Native Filament import completion notification copy is the correct place to
  surface successful-row disabled-tag skips because failed-row CSVs are reserved
  for failed rows.
- Disabled content tags are admin-only metadata, so default portable exports keep
  enabled-only behavior while preserving disabled tags already attached to records.
- `.env.example` did not exist as a tracked file before IE-1, so this run adds a
  minimal tracked example containing only the keys needed for the new fallback
  contract.

## Deferred Issues

- Zip import packages remain future-gated.
- Remote media fetching remains out of scope.
- Clear-via-import behavior for relation cells is not implemented in IE-1.
- Prompt 13 dashboard metrics remains not started.

## Current Git Status

Before final commit:

```text
 M app/Filament/Exports/ContentItemExporter.php
 M app/Filament/Imports/Concerns/ConfiguresContentImports.php
 M app/Filament/Imports/ContentGroupImporter.php
 M app/Filament/Imports/ContentItemImporter.php
 M app/Filament/Imports/TranscriptionImporter.php
 M config/curator.php
 M docs/phase-02/admin-tools-tools1-handoff.md
 M docs/phase-02/ai-development-lessons.md
 M docs/phase-02/current-project-state.md
 M docs/phase-02/images-media-track-plan.md
 M docs/phase-02/maintenance-form-mp2-handoff.md
 M docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md
 M lang/en/admin.php
 M lang/he/admin.php
 M tests/Feature/ImageMediaCuratorTest.php
 M tests/Feature/Phase02ImportExportTest.php
?? .env.example
?? app/Enums/ContentItemTagExportScope.php
?? app/Enums/RelationImportMode.php
?? docs/phase-02/import-relations-ie1-handoff.md
?? docs/research/images-media/03-ie1-implementation-plan.md
?? docs/research/images-media/03-ie1-research.md
```
