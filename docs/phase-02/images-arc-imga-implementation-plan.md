# IMG-A Implementation Plan

Date: 2026-07-12

Prompt: `prompts/pre-13-prompts/images-arc-imga-curator-codex-prompt.md`

## Scope

Implement IMG-A only: image naming foundation, Curator media library, app-owned picker field factory, content group cover hardening/alt text, deterministic app-owned cleanup, legacy file registration, docs, tests, and final commit.

Out of scope stays out: episode image column/downloads, table image actions, avatars, zip packages, EP1 workspace, SF1/TL1, exporter behavior changes.

## Required Research Already Completed

- Laravel Boost: installed app info, schema, and version-aware docs.
- FilamentExamples: search-only examples for SettingsPage FileUpload, panel registration, custom field state/dehydration, and image rendering.
- Curator official/source review:
  - install command and admin-panel plugin registration;
  - theme CSS import/source requirements;
  - picker and uploader state/naming behavior;
  - path generator interface;
  - media table metadata fields;
  - destructive delete/replace semantics;
  - SVG sanitizer behavior.

Detailed notes are in `docs/research/images-media/01-imga-curator-research.md`.

## Implementation Sequence

1. Add documentation state before code.
   - Mark IMG-A in progress in the ledger/current state.
   - Update the image track D-IMG decision register after implementation details are settled.

2. Install Curator.
   - Run `composer require awcodes/filament-curator`.
   - Run `php artisan curator:install`.
   - Run `php artisan migrate --no-interaction`.
   - Inspect generated config/migration/source in `vendor/awcodes/filament-curator`.
   - Root Composer dependency enumeration must list `composer.json` and `composer.lock` changes.

3. Add media naming and validation foundation.
   - Create `AdminUxSettings` with `media_naming_strategy = slug`.
   - Add a settings migration for `admin_ux.media_naming_strategy`.
   - Add a small enum or finite validation boundary for `slug`, `reference_key`, and `slug_key`.
   - Create `App\Support\Media\ImageFileNamer`.
   - Create upload rule/helper using Laravel `File::image()` for JPEG/PNG/WebP, 2048 KB, 3000x3000.
   - Record EXIF stripping as deferred until re-encoding.

4. Integrate Curator on the admin panel only.
   - Add `CuratorPlugin::make()` to `AdminPanelProvider`.
   - Add Curator CSS import/source to `resources/css/filament/admin/theme.css`.
   - Publish/use Curator config with `public` disk and a PodText path generator where useful.

5. Add the app-owned media picker field factory.
   - Add a config flag under app-owned config, defaulting to Curator mode.
   - Factory returns CuratorPicker or FileUpload.
   - Both modes dehydrate/hydrate plain path strings.
   - Factory supports content-photo fields and trusted-logo fields, with SVG only for logos.

6. Harden content group covers and alt text.
   - Add nullable bounded `content_groups.cover_alt_text`.
   - Add model fillable/casts where needed.
   - Replace the direct cover `FileUpload` with the factory.
   - Add helper text and settings hint translations in English/Hebrew.
   - Add cover alt text field.
   - Public badge/cards/detail images use `cover_alt_text`, falling back to group title.

7. Add app-owned file cleanup.
   - Add focused cleanup service/observer for `ContentGroup`.
   - Delete old app-owned cover path on replacement.
   - Delete app-owned cover path on record delete.
   - Never delete external/legacy paths outside `content-groups/covers`.
   - Keep Curator media deletion semantics documented rather than duplicating destructive behavior.

8. Apply factory to settings images.
   - Menu logo light/dark: trusted SVG remains allowed; Curator mode sanitizes SVG.
   - Team profile images, about block images, and default images: JPEG/PNG/WebP only.
   - Preserve settings JSON path strings byte-for-byte on save when unchanged.

9. Register existing app-owned files into Curator.
   - Add an idempotent artisan command.
   - Scan current cover paths and settings asset paths in `header`, `team`, `about`, and `default-images`.
   - Create Curator media rows without moving or renaming files.
   - Report created/existing/missing/skipped counts.
   - Tests use fake public disk fixture files.

10. Tests.
   - Unit tests for `ImageFileNamer`: Hebrew slug, empty slug fallback, each strategy, lower MIME extension, collision suffix.
   - Feature tests for cover validation, alt text rendering, public resolver/public images, cleanup, field factory modes, settings path round trips, export column unchanged, and registration command.
   - Iteration uses only targeted commands: `php artisan test --compact <file> --filter='...'`.
   - Full `php artisan test` runs exactly once in the final gate after Pint.

11. Final docs and commit.
   - Update `docs/phase-02/images-media-track-plan.md` for Yoni decisions and IMG-A outcomes.
   - Update `docs/phase-02/current-project-state.md`.
   - Update ledger row to complete.
   - Add handoff with `## Commit hash` and `## Local Front Check Report`.
   - Commit as `feat: add image naming foundation and curator media library`.

## Quality Gate

Final gate order:

1. `vendor/bin/pint --test`
2. `php artisan test` exactly once
3. `vendor/bin/filacheck`
4. `npm run build`
5. `git diff --check`
6. `git status --short`

Do not run `filacheck --fix`. Do not run the full test suite before the final gate.

## Implementation Notes

- Curator mode stores selected media as plain path strings through `PathCuratorPicker`; plain FileUpload mode remains available through `config('media.picker.driver')`.
- Laravel `File::image()` rule objects are used on the plain FileUpload fallback. Curator mode constrains uploads through Curator's picker/uploader settings because the saved form state is a path string.
- Curator's uploader sanitizes SVGs. PodText still allows SVG only for menu logos.
- EXIF stripping is deferred until image re-encoding lands; no metadata-stripping pipeline was built in IMG-A.
- Local legacy registration was run once after migrations: 11 created, 0 existing, 0 missing, 0 skipped.

## Stop Conditions

- Composer tries to add a non-transitive root dependency other than `awcodes/filament-curator`.
- Curator cannot reference existing files in place after installed-source verification.
- Generated Curator migrations/config conflict with existing schema in a way that requires a separate decision.
- Any app-code dirt appears outside this IMG-A run.
