# Media Program Package 2 Inventory, Picker and Replacement Handoff

## Scope and baseline

This handoff closes only Option
`MEDIA-P2-O1-REUSE-PICKER-SAME-PAGE-REPLACE` from Laravel Simplifier audit
`LS-20260723-PODTEXT-MEDIA-P2-INVENTORY-PICKER-REPLACE-01`.

Implementation began on `main` at
`39420d1f21fbe43e193913fc59d6d9efea5ced66`, 15 commits ahead of
`origin/main`, with a clean Package 1 hash-stamped baseline. Preflight confirmed
that Package 1 commits were present and Package 2 had not started. The current
media context helper, requirements registry, Package 1 handoff, Package 2
research and Package 2 plan were reread before editing.

Only Package 2 was implemented: complete admin inventory, visible repair
diagnostics, authoritative attachment and D01 resolution, picker context/All
Media behavior, and always-visible same-page podcast/episode Add/Replace Image
actions. Packages 3-5, dependencies, live data actions, production actions and
push remain outside this result.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| `MI-R001` complete All Media inventory | Implemented | The Resource and inventory query include every Curator row, including configured private-disk, private-visibility, missing-file and nonstandard-metadata rows. |
| `MI-R002` Files Discovery | Deferred to Package 5 | No rowless-file discovery or lifecycle surface was added. |
| `MI-R003` visible Needs Repair | Implemented | Needs Repair is a filter and badge/diagnostic layer, never an inventory exclusion. Its global filter uses set-based metadata plus existence-only checks and does not read every SVG body; byte-level SVG decisions remain bounded to visible-row, picker-selection and delivery boundaries. |
| `MI-R004`-`MI-R007` one authority per job | Already existed / preserved and corrected | `media_attachments.media_id` selects the owner image, `curator.path` selects the file, and key/path mirrors no longer veto an authoritative attachment. |
| `MI-R008`-`MI-R012` minimal asset kernel | Already existed / preserved | Package 2 adds no asset, binding or bridge schema and does not change the Package 1 authority model. |
| `MI-R013` authoritative attachments beat stale mirrors | Implemented | Public rendering, forms, replacement actions and Card Template sample ranking use the attached inventory row despite stale owner paths or metadata drift. |
| `MI-R014`-`MI-R020` conversion reconciliation and visible diagnostics | Already existed / preserved | Package 1 conversion behavior is unchanged. Rowless invalid legacy paths now take D01 fallback and can be replaced without a public 500 or unrelated-save mutation. |
| `MI-R021` exact D01 fallback | Implemented | Fallback is limited to missing canonical row, missing physical file, audience denial or unsafe inline SVG. |
| `MI-R022` no metadata/key/size display veto | Implemented | Root, folder, key shape, filename, metadata, upload-size limits, dimensions and stale mirrors do not veto existing display. A sanitized legacy SVG above the new-upload limit remains renderable. |
| `MI-R023`-`MI-R024` complete configured-disk inventory | Implemented | Admin inventory/view/download includes configured public and private disks; audience placement affects delivery and diagnostics only. |
| `MI-R025` picker All Media | Implemented | The picker starts in its logical purpose context and All Media clears that filter without changing existing media. |
| `MI-R026` attachment-only existing selection | Implemented | Selecting an existing row updates only the owner attachment and compatibility mirror. Tests prove no copy, move, rename, normalization, row edit or mutation journal. |
| `MI-R027` visible nonselectable rows | Implemented | Private, missing, unsafe-SVG and nonportable-key rows remain visible with exact disabled reasons and a Media review link. Forged selection state is rejected in generic fields, actions, imports and again under the attachment transaction lock. |
| `MI-R028` new Upload/URL/Storage admission | Deferred to Package 3 / existing upload preserved | Package 2 does not add URL or Storage acquisition and does not change dependency or admission limits. |
| `MI-R029` SVG inline boundary | Implemented for Package 2 delivery | SVG MIME parameters/case are normalized, stored bytes must already equal sanitizer output, unsafe SVG is download-only, and existing-media display no longer reuses the new-upload size ceiling. Package 3 still owns new-source admission. |
| `MI-R030` URL/Spotify controls | Deferred to Package 3 / preserved | Existing SSRF, redirect, DNS and response controls are unchanged. |
| `MI-R031`-`MI-R032` lifecycle journals and reference protection | Deferred to Package 5 / preserved | No lifecycle feature was added. Existing physical mutations retain strict scope, journals and active-reference protection. |
| `MI-R033` schema timing | Implemented | Logical context/All Media UI shipped without folder or lifecycle schema. |
| `MI-R034`-`MI-R035` rejected proof machinery | Already existed / preserved | No trust status, normalization/checksum, provenance, quarantine, relocation, manifest, digest or new journal returned. |
| `MI-R036` production-shaped fixtures | Already existed / regression-preserved | Package 1 fixtures remain green; Package 2 adds focused inventory, delivery, picker and replacement proof. |
| `MI-R037` existing boundary correctness | Implemented / regression-tested | Authorization, containment, SVG inline protection, locked selection rechecks, imports and URL controls remain at their actual boundaries. No separate security-audit phase was added. |
| `MI-R038` no dedicated security phase | Implemented | A normal independent correctness review was used; no new program phase or architecture was introduced. |
| `MI-R039` no live environment action | Implemented | All automated behavior used test databases and fake storage. No local-development or production action ran. |
| `MI-R040` no dependencies/worktree/branch/push | Implemented | Manifests and lockfiles are unchanged; the existing checkout and branch were preserved and no push occurred. |

## Required replacement UX

- Add/Replace Image remains visible with or without a current image.
- Podcast and episode list actions plus podcast edit, episode edit and episode
  workspace header actions all use the same form action.
- The action stays on the current page and opens the existing Gallery/Upload
  picker.
- The modal shows the current image and preselects its authoritative numeric
  attachment even when its portable key is null or malformed.
- All Media may select any delivery-eligible existing row regardless of old
  folder/metadata conventions.
- Save changes only the attachment and compatibility mirror; cancellation of a
  staged Gallery selection leaves the owner, rows, files and journal unchanged.
- The reused picker's **Upload** button is an existing explicit library write:
  clicking it creates the row/file immediately. It is not rolled back by later
  cancelling the owner action. Deferring or trashing that explicit upload would
  expand into Packages 3 or 5; this handoff uses “cancel no-op” for uncommitted
  owner attachment state.

## Files changed

### Inventory, delivery, identity and attachment boundaries

- `app/Support/Media/MediaInventoryDiagnostics.php`
- `app/Support/Media/PublicMediaDelivery.php`
- `app/Support/Media/UnresolvableMediaIdentityException.php`
- `app/Support/Media/MediaRecordScope.php`
- `app/Support/Media/MediaRecordProjector.php`
- `app/Support/Media/MediaIdentityResolver.php`
- `app/Support/Media/MediaAttachmentIdentityResolver.php`
- `app/Support/Media/MediaAttachmentFormState.php`
- `app/Support/Media/MediaAttachmentManager.php`
- `app/Support/Media/LegacyOwnerMediaDiagnostics.php`
- `app/Support/Media/LegacyOwnerMediaRepairer.php`
- `app/Support/PublicFront/PublicDefaultImageResolver.php`
- `app/Support/Settings/CardTemplates/CardTemplatePreviewer.php`
- `app/Providers/AppServiceProvider.php`
- `app/Policies/CuratorMediaPolicy.php`

### Filament, Livewire, controller and views

- `app/Filament/Actions/ContentImageActions.php`
- `app/Filament/Forms/Components/PathCuratorPicker.php`
- `app/Filament/Imports/ContentGroupImporter.php`
- `app/Filament/Imports/ContentItemImporter.php`
- `app/Filament/Resources/ContentGroups/Pages/EditContentGroup.php`
- `app/Filament/Resources/ContentItems/Pages/EditContentItem.php`
- `app/Filament/Resources/ContentItems/Pages/EditEpisodeWorkspace.php`
- `app/Filament/Resources/Media/MediaResource.php`
- `app/Filament/Resources/Media/Tables/MediaTable.php`
- `app/Http/Controllers/AdminMediaFileController.php`
- `app/Livewire/Admin/MediaPickerPanel.php`
- `resources/views/filament/actions/current-content-image.blade.php`
- `resources/views/filament/forms/components/path-curator-picker.blade.php`
- `resources/views/livewire/admin/media-picker-panel.blade.php`
- `lang/en/admin.php`
- `lang/he/admin.php`

### Tests

- `tests/Feature/MediaInventoryPickerReplacementTest.php`
- `tests/Feature/AppOwnedMediaPickerTest.php`
- `tests/Feature/AppOwnedMediaResourceTest.php`
- `tests/Feature/CardTemplateEditorPreviewTest.php`
- `tests/Feature/CardTemplatePreviewerTest.php`
- `tests/Feature/ImageMediaCuratorTest.php`
- `tests/Feature/ImportExportTest.php`
- `tests/Feature/LegacyOwnerMediaRepairTest.php`
- `tests/Feature/MediaAttachmentModelTest.php`
- `tests/Feature/MediaBackfillAndIntegrityReportTest.php`
- `tests/Feature/MediaRecordScopeAndAuthorizationTest.php`
- `tests/Feature/MediaRelationshipPerformanceTest.php`
- `tests/Feature/PublicDefaultImagesSettingsTest.php`
- `tests/Feature/PublicItemPageMediaParserTest.php`
- `tests/Feature/PublicPodcastsGroupsUxTest.php`
- `tests/Feature/PublicStep9RMenuHeaderUxFixesTest.php`
- `tests/Browser/CardTemplatePreviewBrowserTest.php`

### Active documentation and closeout

- `docs/phase-02/current-project-state.md`
- `docs/phase-02/media-program-context.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/research/media-program/00-media-program-requirements-decisions-and-method.md`
- `docs/research/media-program/02-media-program-master-plan.md`
- `docs/research/media-program/packages/02-gallery-repair-plan.md`
- `docs/research/media-program/packages/02-gallery-repair-research.md`
- this handoff

## Tests added or updated

- Complete inventory, configured private disk, missing file, nonstandard
  metadata and Needs Repair visibility/filtering.
- Bounded global repair filtering without whole-inventory SVG byte reads.
- Logical context start, All Media clearing, exact nonselectable reasons and
  unchanged row/file proof.
- D01 attachment authority, missing row/file, audience denial, rowless malformed
  path and metadata/key/root/size non-veto cases.
- Parameterized/case-varied SVG MIME, sanitizer-transformation rejection,
  download-only fallback and sanitized oversized legacy SVG delivery.
- Podcast/episode list/edit action visibility, current-image display, cancel
  no-op and attachment-only replacement from another logical folder.
- Null/malformed current-key hydration and unrelated-save preservation.
- Generic settings tamper rejection while preserving already-stored
  nonselectable identities.
- Locked attachment selection recheck, stale-request-cache invalidation and
  unchanged-current preservation.
- Card Template automatic/preload/search ranking parity for authoritative
  attachment IDs despite stale mirrors and metadata drift.
- Import/export, legacy repair, authorization and relationship performance
  expectations reconciled with the inventory-first model.

## Commands and results

| Command / check | Result |
|---|---|
| Mandatory session orientation, full lessons/state/ledger/handoff reads, Package 2 contract reads and Git preflight | PASS; cwd/Git root `/Users/studioycm/Herd/PodText`, clean `main` baseline `39420d1`, 15 commits ahead, Package 1 present and Package 2 not started. |
| Stage 2 approval verification | PASS; exact current Audit ID and Option ID matched the user approval. |
| Laravel Boost installed-version documentation and multi-query FilamentExamples research | PASS; Boost returned installed-version guidance. FilamentExamples exposed search/snippet results only, so installed Filament 5.7.1 source remained authoritative. |
| Baseline affected regression matrix | PASS: 64 tests / 646 assertions. |
| New Package 2 test before implementation | Expected RED: 0 passed / 6 failed. |
| First Package 2 implementation proof | PASS: 6 tests / 72 assertions. |
| Iterative inventory/picker/resource/legacy/public/import reconciliation runs | Expected failures exposed superseded strict visibility, null-key and path-authority expectations; production code was not weakened to satisfy obsolete tests. |
| First combined affected matrix | FAIL: 69 passed / 858 assertions with remaining expected reconciliation failures. |
| Boundary regressions for lowercase keys, unsafe SVG and forged action state | Expected RED, then PASS: 12 tests / 188 assertions after fixes. |
| Primary affected media matrix | Initial FAIL: 96 passed / 3 failed on obsolete performance null-key fixtures; corrected final PASS: 99 / 1,424. |
| Adjacent preview/import/lifecycle matrix | Initial FAIL: 175 passed / 12 failed on canonical-row/file fixture assumptions; corrected final PASS: 187 / 1,894. |
| Independent read-only correctness review | Found SVG transformation/MIME, generic picker tamper, locked attachment, nonportable current identity, oversized SVG, rowless D01 fallback, repair-filter byte work, preview ranking and lowercase-key gaps. No files were changed by the reviewer. |
| First review regression pair | Expected RED: 11 passed / 4 failed for forged settings state, existing nonselectable preservation, lowercase mutation eligibility and SVG MIME/transformation. |
| Repair-filter and preview-ranking regressions | Expected RED: 2 tests, both failed. The old filter sanitized all 30 SVG bodies and authoritative stale-mirror attachment ranking lost to a newer empty sample. |
| Oversized sanitized SVG regression | Expected RED: 1 failed with 404. |
| Null/malformed current-key edit regressions | Expected RED: 2 failed; null hydrated empty and malformed hydration returned 422. |
| Rowless invalid legacy fallback regressions | Expected RED: 2 errors from old path validation. |
| Locked selection regression | Expected RED: nonselectable direct attachment was accepted; stale diagnostics cache also reproduced before refresh support. |
| First combined review-correction matrix | FAIL: 40 passed / 5 issues, exposing two nonportable hydration authorization details, action-error timing, one missing-file fixture and the visible-page SVG read budget. |
| Review-corrected focused matrix | PASS: 45 tests / 533 assertions. |
| First widened attachment/import/preview matrix | FAIL: 187 passed / 7 errors; all seven were test fixtures that created attachable media without their owned fake-storage files. |
| Corrected attachment/import fixtures | PASS: 30 tests / 143 assertions. |
| Final widened implementation matrix before ordered gates | PASS: 195 tests / 2,278 assertions. |
| PHP inspection workflow | PhpStorm inspection connector unavailable; disclosed fallback used syntax checks, Pint/FilaCheck, focused and broad tests, source sweeps and independent read-only review. |
| `git diff --check` before handoff | PASS. |
| Final requirements/drift sweep | PASS: MI-R001-R040 mapping reviewed; dependency/lockfile and migration diffs empty; no Package 3-5 or physical-media mutation mechanics entered the diff. |
| First ordered `vendor/bin/pint --test` attempt | FAIL: formatting-only import/spacing/braces findings in five changed files. Exact-file `vendor/bin/pint ...` formatting passed, followed by a green `vendor/bin/pint --test`. |
| First ordered `vendor/bin/filacheck` attempt | PASS: 0 issues. |
| First ordered `npm run build` attempt | PASS with the existing optional Fontaine advisory; Vite 8.1.5 built successfully. |
| First full `php artisan test` attempt | FAIL: 991 passed / 1,016 tests / 11,579 assertions; three old public-image fixtures had paths without canonical rows/files, then Chromium hit the known macOS `MachPortRendezvousServer ... Permission denied` sandbox failure and 22 browser failures/cascades were reported. |
| Unit/Feature isolation after the first full run | FAIL: 991 passed / 994 tests / 11,571 assertions; exactly three path-only fixture expectations remained. |
| Corrected public-image feature fixtures | PASS: 38 tests / 381 assertions. Fixtures now own both the canonical Media row and fake-storage file required by D01. |
| Corrected Card Template browser fixture outside the macOS sandbox | PASS: 14 tests / 1,832 assertions. |
| First complete final `vendor/bin/pint --test` after fixture corrections | PASS. |
| First complete final `vendor/bin/filacheck` after fixture corrections | PASS: 0 issues. |
| First complete final `npm run build` after fixture corrections | PASS with Vite 8.1.5; the existing optional Fontaine advisory repeated. |
| First complete final full `php artisan test` last and serial | PASS outside the macOS browser sandbox: 1,016 tests / 13,488 assertions in 429.080 seconds. |
| Post-result-documentation `vendor/bin/pint --test` | PASS. |
| Post-result-documentation `vendor/bin/filacheck` | PASS: 0 issues. |
| Post-result-documentation `npm run build` | PASS with Vite 8.1.5; the existing optional Fontaine advisory repeated. |
| Post-result-documentation full `php artisan test` last and serial | PASS outside the macOS browser sandbox: 1,016 tests / 13,488 assertions in 427.424 seconds. |
| Literal result-text commit candidate | Requirements, dependency/scope sweeps, `git diff --check`, Pint, FilaCheck and Vite were repeated after replacing the pending result labels. A third identical full-suite run was intentionally not repeated because two consecutive outside-sandbox runs already passed 1,016 / 13,488 and the final edit recorded only those verified outcomes. |

All automated tests used the test database. Feature coverage used fake storage;
the existing browser harness created and removed only its named test-owned image
fixtures on the test public disk. Local-development and production application
data, databases, cache and media operations were not probed or mutated.

## Drift checklist

- Every Curator row remains in inventory; Files Discovery remains Package 5.
- Reference-key shape is portable-operation eligibility, not inventory or
  display trust. Existing null/malformed-key attachments hydrate by numeric ID.
- Folder, filename, metadata, size, dimensions and stale mirrors do not veto
  existing public display.
- `media_attachments.media_id` remains owner authority and `curator.path`
  remains file-location authority.
- D01 remains exactly missing row, missing file, audience denial or unsafe
  inline SVG.
- Global Needs Repair avoids whole-inventory byte reads; inline SVG bytes are
  checked only at bounded visible/selection/delivery boundaries.
- Existing-media selection is attachment-only and transactionally rechecked;
  no normalization, checksum, relocation, quarantine or journal was added.
- Picker All Media clears the logical starting context.
- No Package 3-5 feature, dependency, dedicated security phase or live action
  entered the implementation.

## Assumptions and deferred work

- “Cancel changes nothing” means cancelling uncommitted owner attachment state.
  Gallery selection is staged and proven to leave owner, rows, files and
  journals unchanged. The existing explicit Upload command commits a library
  row/file immediately and is not an outer-action temporary upload.
- Package 3 owns URL/Storage acquisition and any redesign that stages new
  uploads until owner save.
- Package 4 owner image tools beyond the requested Add/Replace surface remain
  gated.
- Package 5 owns Files Discovery, trash/restore/purge and any cleanup of an
  explicitly committed but later unused upload.
- Production/local conversion, repair, sanitation, cache, migration and media
  operations require separate exact approval and runbooks.

## Local Front Check Report

These are numbered manual operator steps for a disposable test copy. They are
not claims that a live or production check ran:

1. Open the admin Podcasts list with a podcast that already has a cover; expect
   **Add/Replace Image** to remain visible.
2. Click **Add/Replace Image**; expect the current cover above the existing
   Gallery/Upload picker and expect the page URL not to change.
3. Open **All Media**; expect the initial folder filter to clear and expect rows
   from other logical folders to appear without being moved or renamed.
4. Choose a delivery-eligible existing row, click **Use selected image**, then
   cancel the outer action; reload and expect the original attachment, path,
   inventory rows and files to remain unchanged.
5. Repeat the selection and save the outer action; expect the podcast cover to
   use the selected row and expect that row's path/metadata/file to remain
   byte-identical.
6. Repeat steps 1-5 from the Episodes list, standard episode edit page and
   Episode Workspace edit page; expect the same current-image and same-page
   behavior.
7. Open Media and select **All Media**; expect every Curator row, including
   configured private-disk and repair rows, to remain visible.
8. Apply **Needs Repair**; expect missing, private/nonpublic, nonportable and
   nonstandard metadata rows without any disappearance from All Media.
9. Open the picker on a repair row; expect selection disabled with an exact
   bilingual reason and a link to review the Media record.
10. Attempt to view an unsafe SVG inline; expect not found. Download the same
    row; expect an attachment response instead of inline rendering.
11. Click the picker's **Upload** button only when intending to add a new
    library item; expect its success notification and remember that this
    explicit library write is separate from saving/cancelling the owner action.
12. Switch the admin locale between Hebrew and English; expect Add/Replace,
    All Media, Needs Repair, repair reasons and current-image labels in the
    selected locale with Hebrew remaining RTL-first.

Do not run these steps against local development or production without a new
exact environment-action approval.

## No-environment-mutation statement

No production or local-development database, storage, cache, migration,
conversion, repair, sanitation, deployment, process, dependency, branch,
worktree or push action occurred.

## Commit hash

`pending`
