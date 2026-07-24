# Media Program Package 4 Inline Owner Picker Correction Handoff

## Scope and baseline

- Audit: `LS-20260724-PODTEXT-MEDIA-OWNER-PICKER-CORRECTIONS-01`
- Approved option: `MEDIA-OWNER-CORR-O3-INLINE-PICKER-TABS`
- Repository: `/Users/studioycm/Herd/PodText`
- Starting branch/HEAD: clean `main` at
  `75249da2c6de7dcdc82cd938d2a722449d87aa47`, 25 commits ahead of
  `origin/main`
- Package 4 implementation:
  `52875222916558542cfde19f8a1987b78e72c121`
- Package 4 hash stamp:
  `75249da2c6de7dcdc82cd938d2a722449d87aa47`
- Installed stack: Laravel 13.21.1, Filament 5.7.3, Livewire 4.3.3,
  Curator 5.1.2, Boost 2.4.13 and Pest 4.7.5

Preflight matched the approved baseline exactly. There was no prompt under
`prompts/pre-13-prompts/`, no overlapping writer and no unexpected PHP,
Blade, migration, test, configuration, dependency or documentation drift.

This run implemented only the approved Package 4 owner-picker correction and
the explicit standalone Media batch-upload entry point. It did not update
dependencies, run Boost discovery, broaden Storage discovery, start Package 5,
touch production or push.

## Package 3 migration answer and local database activation

The Package 3 acquisition mechanism, Admin UX settings and migrations were
built and committed during Package 3. That package explicitly excluded running
migrations or local data actions, so environment activation remained pending
until a separate backup-first approval. The operator supplied that approval in
this run.

The observed target was local MySQL database `podtext` on `127.0.0.1` with
`APP_ENV=local`. No credentials were printed.

- Backup:
  `/Users/studioycm/Herd/PodText/storage/app/private/database-backups/podtext-pre-media-schema-20260724-054521.sql`
- Permissions: mode `0600`
- Size: 1,477,649 bytes
- SHA-256:
  `4d7c81d93b9dc69796d0fcb572b2cc9efceab817cbe78feec7c45daaedc6bd07`

Initial migration status contained exactly the three approved pending files:

1. `database/settings/2026_07_23_000000_add_media_acquisition_admin_ux_settings.php`
2. `database/migrations/2026_07_23_000000_create_media_asset_kernel_tables.php`
3. `database/migrations/2026_07_23_000001_add_media_asset_id_to_media_attachments_table.php`

They were applied one at a time in that order. No other migration was pending.

The first report found 15 Media rows, zero bound and 15 unbound, with zero
missing-file, duplicate-path, unresolved-owner or unresolved-settings
diagnostics. Apply created 15 MediaAsset rows and 15 Curator bindings, reused
15 valid keys, created five owner attachments and filled five settings keys.
The final report found 15 bound, zero unbound and zero diagnostics.

The converter performed database-only work. No media file, storage path,
cache, queue or production state was changed.

## Outcome

Podcast and episode owner-image actions now use one outer Filament action
modal or configured slide-over:

```text
owner Resource/Page Livewire component
└── ContentImageActions modal or slide-over
    └── schema Tabs
        ├── Replace Image (first/default)
        │   ├── trusted single owner field
        │   └── schema-owned MediaPickerPanel
        │       ├── Gallery
        │       ├── Upload
        │       ├── URL
        │       ├── Storage
        │       └── existing picker-owned item actions
        └── Details and Effective Image
            └── existing bounded detail view
```

- The extra picker-launch modal is gone from the owner action.
- The picker remains one schema-owned Livewire child with no form wrapper and
  no duplicate action-modal partial.
- Gallery choice remains mutation-free and pending until the outer action is
  submitted.
- One successful Upload, URL or Storage acquisition can become the pending
  owner choice.
- Inline Upload accepts one or multiple files up to the existing 10-file
  limit with two parallel transfers.
- A multi-file owner upload permanently admits every success, reloads the
  gallery and chooses no arbitrary owner image. The operator must choose
  exactly one.
- Partial successes remain permanent. Cancelling the owner action changes no
  attachment and deletes no completed acquisition.
- Inline owner mode removes picker-only Close, Clear Selection and Delete
  Selected controls. Remove Direct Image / Use Automatic Image remains the
  bounded owner action.
- The standalone Media Resource now exposes its existing batch admission as
  the translated Upload Images action with one-or-many field/help copy.
- Storage still loads lazily from configured bounded roots and shows explicit
  unconfigured, empty, search-empty, failure or candidate states. General
  directory browsing remains Package 5.

The original Package 4 effective-source detail, safe preview/download, copy
feedback, broken-association diagnostics, fallback evidence, stale-write
protection and 43-action Resource-table rider remain unchanged.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| Replace Image first/default; Details and Effective Image second | Implemented | Shared schema tabs serve all six existing owner surfaces. |
| Complete picker directly in Replace Image | Implemented | One schema-owned `MediaPickerPanel` renders Gallery/Upload/URL/Storage without a picker-launch modal. |
| One outer action owner and no nested form/duplicate modal partial | Implemented / regression-preserved | The existing exposed trusted field bridge receives child selection without unmounting the outer action. |
| Single owner attachment | Already correct / regression-preserved | Owner state remains one trusted Media identity; choosing a new gallery row replaces the pending identity. |
| Acquisition-only owner multi-upload | Implemented | Every success becomes permanent, no arbitrary owner row is chosen and partial results remain truthful. |
| One-file acquisition and Gallery behavior | Already correct / regression-preserved | Direct single choice and mutation-free gallery selection remain intact. |
| Cancellation and stale-write safety | Already correct / regression-preserved | Outer cancellation is an attachment no-op; expected Media/path and diagnostic fingerprint checks remain root-owned. |
| Busy/offline, focus, keyboard, touch, RTL/LTR and narrow screens | Implemented / regression-preserved | Focused real-browser coverage passes in Hebrew and English at wide and narrow viewports. |
| Standalone Media batch upload visibility | Implemented | Existing `CreateMedia` batch admission has an explicit Upload Images entry and batch copy. |
| Storage state clarity | Already correct / regression-preserved | Bounded lazy configured-root candidates and explicit empty/error states remain visible inline. |
| Broader Storage/directory discovery | Deferred | Package 5 only. |
| Broken association, fallback, copy and safe download behavior | Already correct / regression-preserved | Original Package 4 presenter/action boundaries were not replaced. |
| Local database activation | Implemented under separate approval | Exact three committed migrations and report/apply/report conversion settled normally after a verified backup. |
| New schema, migration, dependency, provider or filesystem journal | Not applicable | None was added or changed by the correction. |
| Package 5, file lifecycle, image editing or public redesign | Deferred / excluded | No part was implemented. |
| Production, deployment and push | Not applicable / excluded | No such action occurred. |

## Files changed

### Application and presentation

- `app/Filament/Actions/ContentImageActions.php`
- `app/Filament/Forms/Components/PathCuratorPicker.php`
- `app/Filament/Resources/Media/Pages/ListMedia.php`
- `app/Filament/Resources/Media/Schemas/MediaForm.php`
- `app/Livewire/Admin/MediaPickerPanel.php`
- `resources/views/filament/forms/components/path-curator-picker.blade.php`
- `resources/views/livewire/admin/media-picker-panel.blade.php`
- `lang/en/admin.php`
- `lang/he/admin.php`

### Tests

- `tests/Browser/MediaPickerBrowserTest.php`
- `tests/Browser/OwnerImageWorkspaceBrowserTest.php`
- `tests/Feature/AppOwnedMediaPickerTest.php`
- `tests/Feature/AppOwnedMediaResourceTest.php`
- `tests/Feature/MediaInventoryPickerReplacementTest.php`
- `tests/Feature/OwnerImageWorkspaceTest.php`

### Documentation

- `docs/phase-02/current-project-state.md`
- `docs/phase-02/media-program-context.md`
- `docs/phase-02/media-program-p4-owner-image-ux-handoff.md`
- this handoff
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/research/media-program/00-media-program-requirements-decisions-and-method.md`
- `docs/research/media-program/02-media-program-master-plan.md`
- `docs/research/media-program/04-active-document-supersession-map.md`
- `docs/research/media-program/packages/04-owner-image-ux-research.md`
- `docs/research/media-program/packages/04-owner-image-ux-plan.md`
- `docs/research/media-program/packages/04-owner-image-inline-picker-correction-research.md`
- `docs/research/media-program/packages/04-owner-image-inline-picker-correction-plan.md`

## Tests added or updated

- First/default owner tab, second detail tab and one schema-owned inline picker.
- Trusted child-to-parent pending selection and cancellation no-op.
- One-file auto-choice versus multi-file acquisition-only behavior.
- Permanent full and partial owner batches with no arbitrary selection.
- Inline authority properties locked against Livewire tampering.
- Inline picker Close/Clear/Delete Selected absence.
- Standalone Media Upload Images action, one-or-many field and full/partial
  batch admission.
- Existing Package 2 same-page action expectation updated from launcher modal
  to inline picker.
- Real browser proof of one dialog, no nested form, all four sources, a real
  bounded Storage candidate, native multiple-file input, tab switching, safe
  download/copy, focus restoration, touch, RTL/LTR and no narrow overflow.
- Existing direct form picker, acquisition child actions, busy/offline and
  permanent-cancel browser contracts remain covered.

## Commands and results

| Command / check | Result |
|---|---|
| Git root/status/log, prompt check and mandatory orientation | PASS; exact clean baseline, 25 ahead, no active prompt or unexpected drift. |
| Laravel Simplifier Stage 2 gate | PASS; exact correction Audit ID and Option ID plus separate local-database approval matched. |
| Laravel Boost installed-version application info/docs | PASS; Laravel 13.21.1, Filament 5.7.3, Livewire 4.3.3, Boost 2.4.13 and Pest 4.7.5 guidance returned. |
| FilamentExamples multi-query/refinement | PASS with search snippets and paths only; no full-source/details reader was available. |
| Installed Filament source inspection | PASS; full source confirmed schema Tabs, schema-owned Livewire closures, exposed field methods and bounded FileUpload multiple behavior. |
| Sanitized local target/environment check | PASS; local `podtext` MySQL target, no secrets printed. |
| Sanitized `mysqldump` to the recorded private path | PASS; mode 0600, 1,477,649 bytes and recorded SHA-256 verified. Credential options are intentionally omitted. |
| `php artisan migrate:status` before repair | PASS; exactly the three approved migrations were pending. |
| `php artisan migrate --path=database/settings/2026_07_23_000000_add_media_acquisition_admin_ux_settings.php --no-interaction` | PASS. |
| `php artisan migrate --path=database/migrations/2026_07_23_000000_create_media_asset_kernel_tables.php --no-interaction` | PASS. |
| `php artisan migrate --path=database/migrations/2026_07_23_000001_add_media_asset_id_to_media_attachments_table.php --no-interaction` | PASS. |
| `php artisan migrate:status` after repair | PASS; no pending migration remained. |
| `php artisan media-assets:convert-curator --json` | PASS; 15 unbound before apply, all diagnostic counts zero. |
| `php artisan media-assets:convert-curator --apply --json` | PASS; 15 assets/bindings, five attachments and five settings keys settled. |
| Final `php artisan media-assets:convert-curator --json` | PASS; 15 bound, zero unbound and all diagnostic counts zero. |
| Initial correction feature matrix | Expected RED: missing inline tabs, owner batch rejection and generic Media create trigger. |
| Focused correction iterations | PASS after implementation; one test-construction argument shape was corrected without application change. |
| Initial focused Chromium command inside sandbox | Infrastructure FAIL: Chromium `MachPortRendezvousServer ... Permission denied`; identical commands passed outside the sandbox. |
| Owner browser iterations | One Alpine initialization race and one FilePond wrapper selector were corrected in the test. Browser evidence also exposed a real inline Delete Selected presentation leak; Blade now excludes the destructive action and feature/browser regressions cover it. |
| PhpStorm WARNING-or-higher inspection of changed PHP/tests | Changed application PHP and correction tests have 0 warnings after removing two unused browser-test constants. One pre-existing factory return-inference warning remains in the unchanged helper body of `MediaInventoryPickerReplacementTest`. |
| Focused correction feature matrix | PASS: 105 tests / 1,517 assertions. |
| Combined owner/picker browser matrix | PASS outside the sandbox: 8 tests / 212 assertions. |
| Pre-close full `php artisan test --compact` | PASS outside the sandbox: 1,131 tests / 14,797 assertions in 446.684 seconds. |
| Requirements/scope/drift sweep and `git diff --check` | PASS; MI-R062-MI-R065 are covered and no dependency/new-migration/Package 5/file-mutation drift exists. |
| Initial final `vendor/bin/pint --test` | FAIL: import order and fully-qualified-type style only in `OwnerImageWorkspaceTest`. Targeted `vendor/bin/pint tests/Feature/OwnerImageWorkspaceTest.php` corrected the mechanical diff; final gates restarted from Pint. |
| `vendor/bin/pint --test` | PASS. |
| `vendor/bin/filacheck` | PASS with 0 issues. |
| `npm run build` | PASS; production assets built and the Fontaine warning did not return. |
| Full `php artisan test` last | PASS outside the macOS sandbox: 1,131 tests / 14,797 assertions. |
| Hash-stamp-only closeout | `git diff --check` PASS. No third full-suite run was added after two identical complete green suites because only the recorded implementation hash changed. |

The original operator-reported upload error was consistent with the observed
missing local schema activation. No live upload experiment was authorized or
performed. The required local schema is now present, the converter is settled,
and isolated mounted FileUpload plus browser/feature regressions pass.

## Performance and safety boundaries

- Inline mode removes one picker-modal mount/unmount round trip.
- Table eager loading and per-row query behavior are unchanged.
- Gallery pages and search remain bounded by existing settings.
- Storage candidates remain lazy until the Storage source is opened and remain
  bounded to configured roots.
- The parent serializes only its trusted scalar owner identity/fingerprint
  state; the bounded gallery stays in the child.
- Existing authorization, SVG, SSRF, safe admin delivery, stale owner and
  diagnostic boundaries remain authoritative.
- No Composer/npm file, migration source, provider, cache/queue protocol,
  storage root, media byte, file path or filesystem journal changed.

## Local Front Check Report

1. Open the admin Episodes table in Hebrew and click an image thumbnail.
2. Expect one Image Details action window with Replace Image selected first.
3. Expect Gallery, Upload, URL and Storage directly inside Replace Image and
   no second picker-launch button or picker Close button.
4. Open Storage and expect configured bounded candidates or an explicit
   unconfigured/empty/error message, never a silent search-only blank state.
5. Open Upload and select one image from the computer; expect the upload to be
   admitted permanently and selected as the pending owner image.
6. Cancel the owner action and expect the current episode attachment to remain
   unchanged while the acquired image remains in All Media.
7. Reopen Upload and select several images; expect every successful image to
   remain in the library and no owner image to be chosen automatically.
8. Choose exactly one of those images in Gallery, submit Change Image and
   expect only that image to become the direct owner attachment.
9. Switch to Details and Effective Image and expect the effective preview,
   real source, original/stored filenames, metadata, copy feedback and safe
   download/review links without another modal.
10. Open a broken direct association and expect fallback preview and warning
    evidence to remain separate with Replace, Detach and Review choices.
11. Repeat from Podcasts, a podcast's Episodes relation manager, podcast edit,
    classic episode edit and Episode Workspace.
12. Open Media and expect a clearly visible Upload Images header action; select
    multiple images and expect the existing bounded batch admission behavior.
13. Repeat in English and at a narrow mobile viewport; expect LTR copy, stable
    keyboard/touch focus, no horizontal overflow and the same one-dialog
    topology.
14. Toggle the browser offline while the picker is open and expect acquisition
    controls to disable truthfully until connectivity returns.

## Deferred and excluded

- Package 5 Files Discovery and broader Storage/directory browsing.
- Physical move, rename, trash, restore, purge or cleanup lifecycle.
- Image editing, cropping, optimization, downsizing or curation.
- Dependency/toolchain updates and Boost discovery.
- New migrations, schema, providers, roots, journals or queue protocols.
- Unrelated Package 3 corrections.
- Production access, deployment, live upload experiment and push.

## Commit hash

`f905de83e996d34c65b767deb7ce121283f0786a`
