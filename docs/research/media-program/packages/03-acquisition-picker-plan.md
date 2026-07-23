# Package 3 Implementation Plan — Four-Source Acquisition Picker

Status: Stage 2 approved on 2026-07-23.

- Audit: `LS-20260723-PODTEXT-MEDIA-P3-ACQUISITION-PICKER-01`
- Approved option: `MEDIA-P3-O1-IMMEDIATE-SHARED-ADMISSION`
- Scope: Package 3 only.

## Goal

Extend the existing shared picker with Gallery, Upload, URL and Storage.
Upload/URL/Storage converge on one immediate, app-owned admission boundary;
Gallery remains mutation-free selection. Every successful new admission creates
the Curator row, immutable `MediaAsset` and Curator binding together, then an
owner may attach it through `MediaAttachmentManager`.

## Sequential tasks

### Task 1 — reconcile authority documents

Update the Package 3 research and plan, context helper, requirements registry
and master plan before PHP. Lock immediate permanence, cancellation, atomic
kernel creation, validator correction, URL queue behavior, Storage semantics,
settings, schema/dependency scope and Package 4/5 exclusions.

### Task 2 — test and build shared admission

1. Add failing focused tests for byte-preserving raster validation, all-purpose
   sanitized SVG, settings limits, atomic Media/asset/binding creation,
   rollback cleanup, original-filename metadata and collision-safe naming.
2. Add a small acquisition filename enum/policy and bounded
   `AdminUxSettings` values with one Spatie settings migration.
3. Correct `ImageUploadValidator` to preserve raster bytes while retaining
   actual new-input rejection. Reuse `SvgUploadSanitizer`.
4. Add `MediaAcquisitionManager` and a focused Curator admission collaborator.
   Keep new acquisition out of `MediaFilesystemMutationCoordinator`.
5. Add bounded opaque `StorageImageCandidateBrowser` behavior and tests for
   existing-row reuse, safe public register-in-place and copy-required input.

### Task 3 — test and extend picker and Spotify paths

1. Add failing Livewire tests for four source labels, Upload/URL/Storage
   success, visible errors, immediate permanence, pending attachment behavior,
   context/search/pagination regression and cancel semantics.
2. Extend `MediaPickerPanel` and its Blade view rather than creating separate
   pickers. Use bilingual Hebrew/English labels and explicit permanence/cancel
   help.
3. Keep picker URL acquisition synchronous. Test
   `SafeExternalImageFetcher` with committed fixtures,
   `Http::preventStrayRequests()`, redirects/DNS/size/timeout regressions and
   extensionless allowed responses.
4. Route `DownloadExternalContentItemImage` through the shared admission
   boundary and keep it queued after commit. A valid acquired row survives a
   later owner-attachment failure.
5. Reuse common Spotify form actions on podcast create/edit and episode
   create/edit/workspace surfaces. Feed returned artwork URLs into URL
   acquisition.
6. Reconcile `SpotifyLinksFetcher` direct import so its post-transaction image
   work dispatches the same queued path. Keep all network acquisition outside
   importer database transactions.

### Task 4 — close Package 3

1. Sweep every Package 3 requirement and the Package 4/5 exclusions.
2. Update current project state, ledger and a Package 3 handoff with
   classifications, files, tests, every command result and numbered manual
   operator checks.
3. Run final gates serially in this exact order:
   requirements sweep, `vendor/bin/pint --test`, `vendor/bin/filacheck`,
   `npm run build`, then full `php artisan test` last.
4. Commit implementation locally with the handoff hash pending, then
   immediately commit the docs-only implementation-hash stamp. Do not push.

## Expected file surface

- `app/Support/Media/`: validator/policy corrections, admission, naming and
  Storage candidate classes.
- `app/Livewire/Admin/MediaPickerPanel.php` and
  `resources/views/livewire/admin/media-picker-panel.blade.php`.
- `app/Settings/AdminUxSettings.php`,
  `app/Filament/Pages/AdminUxSettings.php` and one `database/settings/`
  migration.
- Spotify lookup/form/import/job paths and the existing podcast/episode form
  schemas.
- `config/media.php`, `lang/he/admin.php`, `lang/en/admin.php`.
- Focused unit/feature/Livewire/Filament/HTTP/queue regression tests and
  committed HTTP fixtures.

No relational migration, Composer/npm change, Package 4 UI expansion or
Package 5 discovery/lifecycle code is expected.

## Failure and cancellation contract

- Pre-admission validation/fetch failure: no Media/kernel rows.
- Database failure after a new destination write: remove that destination.
- Register-in-place failure: never delete or alter the source.
- Attachment failure: keep the successfully admitted library item.
- Picker/outer owner cancellation: leave an admitted item in the library and
  discard only the pending attachment; Gallery cancellation is a complete
  no-op.
