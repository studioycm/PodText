# Media Program Package 3 Acquisition Picker Handoff

## Scope and baseline

- Audit: `LS-20260723-PODTEXT-MEDIA-P3-ACQUISITION-PICKER-01`
- Approved option: `MEDIA-P3-O1-IMMEDIATE-SHARED-ADMISSION`
- Repository: `/Users/studioycm/Herd/PodText`
- Starting branch/HEAD: clean `main` at
  `a65de4c3c4c22b89cd2934dc01faa602877d924c`, 17 commits ahead of
  `origin/main`
- Package 1 implementation/hash stamp:
  `ca483c0c0072e0791fe6c26755aadae341ece0a5` /
  `39420d1f21fbe43e193913fc59d6d9efea5ced66`
- Package 2 implementation/hash stamp:
  `2a6de67816b9a7c8e53bcd29795a5b306a36dbaf` /
  `a65de4c3c4c22b89cd2934dc01faa602877d924c`
- Installed source remained Laravel 13.21.1, Filament 5.7.1, Livewire 4.3.3
  and Curator 5.1.2.

No overlapping media work or baseline drift was present. Stage 2 first
reconciled the Package 3 research, plan, media context, requirements registry
and master plan, then implemented Package 3 only.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| `MI-R001`-`MI-R018` inventory-first authority/kernel | Already existed / preserved | `media_attachments.media_id` remains owner authority, Curator numeric ID remains provider identity, `curator.path` remains file-location authority, and every new row receives one immutable portable key plus asset/binding immediately. |
| `MI-R019`-`MI-R027` complete inventory, D01 and attachment-only Gallery | Already existed / regression-preserved | All Media, Needs Repair, context clearing, visible repair reasons, D01 and mutation-free Gallery selection remain unchanged. |
| `MI-R028` shared Upload/URL/Storage admission | Implemented | All three sources converge on `MediaAcquisitionManager` and `CuratorMediaAdmission`; the Media Resource upload uses the same path. |
| `MI-R029` SVG boundary | Implemented | `SvgUploadSanitizer` remains the only transformer; sanitized SVG is allowed for every image purpose. Existing unsafe SVG remains visible but nonselectable/non-inline. |
| `MI-R030` URL/Spotify controls | Implemented | Existing HTTPS/DNS/redirect/size/timeout controls remain in `SafeExternalImageFetcher`; Spotify supplies URLs to the same acquisition path. |
| `MI-R031`-`MI-R032` lifecycle journals/reference protection | Deferred to Package 5 / preserved | No Package 3 acquisition uses the legacy mutation coordinator. Its normalization, hashes, staging and journals remain isolated for existing lifecycle/transition operations. |
| `MI-R033` schema timing | Implemented | One Spatie settings migration adds bounded Admin UX values. No relational schema, folder or lifecycle field was added. |
| `MI-R034`-`MI-R035` rejected proof machinery | Implemented | No trust status, provenance, manifest, quarantine, checksum proof, byte-equality proof or admission journal was added. |
| `MI-R036`-`MI-R040` fixtures, boundary checks and no live/dependency action | Implemented | Committed fixtures, fake storage/test DB, `Http::preventStrayRequests()`, existing authorization boundaries, unchanged lockfiles and current-checkout execution were used. |
| `MI-R041` immediate permanence/cancel | Implemented | Successful Upload/URL/Storage creates a permanent library item immediately. Picker/owner cancellation drops only pending attachment state; Gallery cancellation is a no-op. |
| `MI-R042` atomic kernel | Implemented | Curator row, MediaAsset and Curator provider binding are created in one database transaction with one ULID; a database failure rolls back all rows. |
| `MI-R043` one acquisition/attachment boundary | Implemented | Source-specific byte acquisition converges once; owner changes remain exclusively in `MediaAttachmentManager`. |
| `MI-R044` byte-preserving new admission | Implemented | New raster admission preserves bytes while retaining MIME/extension, structural decode, size and dimension rejection. The historical normalizing validator method remains isolated for legacy lifecycle compatibility. |
| `MI-R045` original filename and collision handling | Implemented | Full cleaned original filename is stored in Curator metadata; app-generated or cleaned-original-plus-ULID destination naming is configurable and collision-safe. |
| `MI-R046` Admin UX settings | Implemented | Size, dimension, Upload batch, Gallery browse, Gallery search and filename strategy are bounded settings with Hebrew/English UI. Allowlists, URL protections, parallel uploads, selection cap and Storage roots remain code/config boundaries. |
| `MI-R047` URL queue decision | Implemented | Picker URL is synchronous and selects the new row immediately. Existing record/import downloads remain queued after commit; attachment-race failure leaves the valid library item and notifies the user. |
| `MI-R048` Storage | Implemented | Only encrypted opaque identities from configured nonrecursive disk/root sources are accepted. Existing rows are reused, safe public rasters register in place, and private/copy-required/SVG inputs copy without mutating the source. |
| `MI-R049` Spotify | Implemented | Podcast create/edit, episode create/edit/workspace and direct-import flows feed artwork URLs into the shared URL path. Direct-import network work is dispatched after importer transactions. |
| `MI-R050` Package boundary | Implemented | No dependency, relational migration, Package 4 owner-tool expansion, or Package 5 discovery/directory/move/rename/trash/restore/purge work was added. |

## Acquisition, cancellation and failure behavior

1. Gallery selects an existing Curator row and changes no file or row.
2. Upload validates the full batch before admitting any item.
3. URL fetches through the existing pinned safe fetcher; picker URL is
   synchronous while record/import URL work stays queued.
4. Storage resolves an encrypted server-issued candidate against a configured
   direct-child root; no client path is accepted.
5. Raster bytes pass unchanged; SVG passes only after the existing sanitizer.
6. A new destination is written before database admission. The admission
   transaction creates the Curator row, MediaAsset and provider binding.
7. Database failure deletes only a newly written destination. Register-in-place
   failure never deletes the Storage source.
8. Successful acquisition is permanent immediately. Attachment runs afterward
   through `MediaAttachmentManager`; a later attachment failure or owner-form
   cancellation does not delete the library item.

## Files changed

### Shared admission, validation, Storage and settings

- `app/Enums/ImageUploadPurpose.php`
- `app/Enums/MediaAcquisitionFilenameStrategy.php`
- `app/Settings/AdminUxSettings.php`
- `app/Support/Media/CuratorImageUploadPolicy.php`
- `app/Support/Media/CuratorMediaAdmission.php`
- `app/Support/Media/ImageUploadValidator.php`
- `app/Support/Media/MediaAcquisitionManager.php`
- `app/Support/Media/MediaAcquisitionNamer.php`
- `app/Support/Media/PinnedExternalImageTransport.php`
- `app/Support/Media/SafeExternalImageFetcher.php`
- `app/Support/Media/StorageImageCandidate.php`
- `app/Support/Media/StorageImageCandidateBrowser.php`
- `app/Support/Media/ValidatedImage.php`
- `config/media.php`
- `database/settings/2026_07_23_000000_add_media_acquisition_admin_ux_settings.php`

### Picker, Filament, Spotify and queues

- `app/Filament/Forms/SpotifyShowInput.php`
- `app/Filament/Pages/AdminUxSettings.php`
- `app/Filament/Pages/SpotifyLinksFetcher.php`
- `app/Filament/Resources/ContentGroups/Schemas/ContentGroupForm.php`
- `app/Filament/Resources/ContentItems/Pages/CreateContentItem.php`
- `app/Filament/Resources/ContentItems/Pages/EditContentItem.php`
- `app/Filament/Resources/ContentItems/Schemas/ContentItemForm.php`
- `app/Filament/Resources/ContentItems/Schemas/EpisodeWorkspaceForm.php`
- `app/Filament/Resources/Media/Pages/CreateMedia.php`
- `app/Filament/Resources/Media/Schemas/MediaForm.php`
- `app/Jobs/DownloadExternalContentGroupImage.php`
- `app/Jobs/DownloadExternalContentItemImage.php`
- `app/Livewire/Admin/MediaPickerPanel.php`
- `resources/views/livewire/admin/media-picker-panel.blade.php`
- `lang/en/admin.php`
- `lang/he/admin.php`

### Tests

- `tests/Feature/AppOwnedMediaPickerTest.php`
- `tests/Feature/AppOwnedMediaResourceTest.php`
- `tests/Feature/ExternalImageSecurityTest.php`
- `tests/Feature/FetcherWorkspaceFix1Test.php`
- `tests/Feature/ImageUploadValidatorTest.php`
- `tests/Feature/MediaAcquisitionTest.php`
- `tests/Feature/SpotifyMediaAcquisitionTest.php`
- `tests/Unit/CuratorImageUploadPolicyTest.php`

### Documentation

- `docs/phase-02/current-project-state.md`
- `docs/phase-02/media-program-context.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/research/media-program/00-media-program-requirements-decisions-and-method.md`
- `docs/research/media-program/02-media-program-master-plan.md`
- `docs/research/media-program/packages/03-acquisition-picker-plan.md`
- `docs/research/media-program/packages/03-acquisition-picker-research.md`
- this handoff

## Tests added or updated

- Atomic Curator/asset/binding admission and destination compensation.
- Raster byte preservation, positive allowlist, polyglot/structure, dynamic
  size/dimension and all-purpose sanitized SVG behavior.
- Full Upload batch prevalidation and immediate-library cancellation semantics.
- Filename strategies, original-filename metadata and bounded Admin UX saves.
- Opaque Storage browse/resolve, register-in-place, existing-row reuse,
  copy-required behavior, SVG sanitation and forged-token rejection.
- Picker four-source labels/actions, dynamic browse/search/batch limits,
  context/All Media/search/pagination/repair-reason regressions.
- URL fetch/extensionless MIME/redirect/DNS/size/timeout behavior with committed
  fixtures and stray-request refusal.
- Queued item/group downloads, owner-attachment races and retryable transient
  fetch failures.
- Spotify podcast and episode create/edit/workspace acquisition plus
  post-transaction direct-import job dispatch.
- Media Resource acquisition no longer creates mutation journals.
- Historical transition normalization/journal tests prove that old behavior is
  isolated rather than used by new acquisition.

## Commands and results

| Command / check | Result |
|---|---|
| Mandatory orientation, baseline and installed-source verification | PASS; exact clean approved `main` baseline, Package 1/2 hashes and no overlapping changes. |
| Stage 2 approval check | PASS; Audit ID and Option ID exactly matched. |
| Five controlling Package 3 docs reconciled before PHP | PASS; `git diff --check` green. |
| Laravel Boost version-aware research | PASS; installed-version guidance used without dependency update. |
| FilamentExamples multi-query/refinement protocol | Search-only limitation; names/snippets/paths were available, no source/detail tool was exposed. Installed Filament 5.7.1 source remained authoritative. |
| Validator TDD | Expected RED showed raster bytes changed; corrected new-admission tests passed 49 tests / 108 assertions. |
| Storage/picker/settings TDD | Expected REDs for missing settings/labels/partial batch; final focused checks passed. |
| External-image/Spotify/direct-import TDD | Expected REDs exposed legacy coordinator use, missing classic/show actions and missing queued group artwork; final focused checks passed. |
| Resumed complete Package 3 matrix | PASS: 170 tests / 1,397 assertions. |
| First requirements sweep | PASS; no dependency/relational migration diff, no legacy acquisition callers, MI-R041-R050 mapped, Package 4/5 exclusions clean. |
| First ordered Pint/FilaCheck/Vite | PASS; FilaCheck 0 issues; Vite passed with the existing optional Fontaine advisory. |
| First full `php artisan test` | FAIL: 1,007 passed / 1,037 tests / 11,701 assertions. Eight real legacy-media expectations exposed global validator drift; Chromium then hit the known sandbox `MachPortRendezvousServer ... Permission denied` failure and 22 browser cascades. |
| Validator-isolation correction | PASS: 94 tests / 322 assertions. New admission preserves bytes/config limits; historical lifecycle validation retains normalization/journals. |
| Widened Package 3 plus historical media matrix | PASS: 218 tests / 1,631 assertions. |
| Corrected ordered requirements/Pint/FilaCheck/Vite | PASS; dependency/relational migration diffs empty, legacy acquisition callers none, FilaCheck 0 issues, Vite passed with the existing optional Fontaine advisory. |
| Corrected full `php artisan test` last | PASS outside the macOS browser sandbox: 1,037 tests / 13,633 assertions in 444.846 seconds. |
| Final exact-documentation-state requirements/Pint/FilaCheck/Vite | PASS; FilaCheck 0 issues and the existing optional Fontaine advisory only. |
| Final exact-documentation-state full `php artisan test` last | PASS outside the macOS browser sandbox: 1,037 tests / 13,633 assertions. |

All automated behavior used test databases, committed fixtures and fake/test
storage. No local-development or production application database, storage,
cache or media operation was used.

## Drift and deferred work

- No relational schema or dependency change.
- No filesystem scan, arbitrary path, directory management, move, rename,
  trash, restore or purge.
- No Package 4 hover/detail/copy/download-column work.
- No logical folders or extra admin-managed roots; configured roots are not
  visibility gates.
- `sha256`, normalization, staging and journals remain only in historical
  lifecycle/transition compatibility code. New admission does not consume
  them as proof.
- Package 4 and Package 5 require their own fresh Stage 1 audits and exact
  approvals.

## Local Front Check Report

Run only against a disposable test copy:

1. Open a podcast or episode Add/Replace Image action; expect the same picker
   with **Gallery**, **Upload**, **URL** and **Storage** sources.
2. Switch between the starting context and **All Media**; expect All Media to
   show every Curator row and retain exact disabled repair reasons.
3. Select an existing Gallery row, close the picker and cancel the owner form;
   reload and expect no attachment, row or file change.
4. Upload a valid raster; expect it selected immediately and expect closing
   the picker or cancelling the owner form not to delete the new library item.
5. Paste a valid HTTPS fixture URL; expect the image acquired and selected
   synchronously. Paste HTTP, private-address, oversized or invalid content;
   expect a bilingual field error and no new row.
6. Choose a configured Storage candidate; expect an existing row reused, a
   safe public raster registered in place, or copy-required input copied while
   the source remains unchanged.
7. Upload or acquire a valid SVG for podcast/episode/default/header use; expect
   sanitizer-approved SVG to be selectable. Expect an existing unsafe SVG to
   remain visible but disabled and non-inline.
8. In Admin UX settings, change new-input size/dimension, batch, browse, search
   and filename strategy within their bounds; expect only new acquisition and
   picker limits to change, never existing inventory visibility.
9. Fetch Spotify metadata on podcast create/edit and episode
   create/edit/workspace; expect artwork to become a permanent Media item
   through the same URL path.
10. Run Spotify direct import with test fixtures and a worker; expect artwork
    jobs after import completion and expect imported owner attachments to use
    the resulting Media rows.
11. Switch between Hebrew and English; expect all four source labels, help,
    success and error copy localized, with Hebrew RTL-first.

Do not run these steps against local development or production without a new
exact environment-action approval.

## No-environment-mutation statement

No production or local-development database, storage, cache, acquisition,
import, sanitation, migration, deployment, process, dependency, branch,
worktree or push action occurred.

## Commit hash

`pending`
