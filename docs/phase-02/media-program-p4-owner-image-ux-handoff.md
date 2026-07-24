# Media Program Package 4 Owner Image UX Handoff

## Scope and baseline

- Audit: `LS-20260724-PODTEXT-MEDIA-P4-POSTP3-OWNER-UX-01`
- Approved option:
  `MEDIA-P4-POSTP3-O1-INTEGRATED-IMAGE-WORKSPACE`
- Repository: `/Users/studioycm/Herd/PodText`
- Starting branch/HEAD: clean `main` at
  `abd5e11b1e8db6cedd8e673a246711698fde3c5f`, 23 commits ahead of
  `origin/main`
- Package 3 post-acquisition implementation:
  `56552225d6466cd713368d249f85075be6f2a297`
- Package 3 post-acquisition hash stamp:
  `7644ebe191c4d081e4c35b5ae86de41139214e1c`
- Dependency/Resend/Fontaine implementation:
  `c9d064aa9b3921b5b99f83859f305b2fc8332cc9`
- Dependency hash stamp:
  `abd5e11b1e8db6cedd8e673a246711698fde3c5f`
- Installed stack: Laravel 13.21.1, Filament 5.7.3, Livewire 4.3.3,
  Curator 5.1.2, Boost 2.4.13 and Pest 4.7.5

Preflight matched the approved baseline exactly. The checkout had no
unexpected PHP, Blade, migration, test, configuration, dependency or
documentation drift. No prompt under `prompts/pre-13-prompts/` was active.
The old Package 4 audit and options remained superseded and supplied no
authority.

This run implemented Package 4 plus the separately approved Resource-table
record-action rider. It did not update Composer/npm packages, run Boost
discovery, start Package 5, inspect secrets, or touch local-development or
production data.

## Outcome

Podcast and episode image handling now uses one integrated owner-image
workspace.

- Podcast, episode and podcast-episode relation tables keep compact 48-pixel
  effective thumbnails. Hover offers a lazy, bounded preview; click, keyboard
  and touch open details without following the record URL.
- Podcast edit, classic episode edit and episode workspace reuse the same
  action. The configured modal/slide-over setting remains authoritative.
- The detail view names the real effective source: direct Media,
  compatibility Media, inherited podcast cover, external URL, configured
  default, global fallback or no image.
- Canonical Media details distinguish original filename from stored basename
  and include useful existing metadata, a day-first Jerusalem timestamp,
  copy feedback, authenticated preview/download and authorized Media review.
- Broken direct evidence stays visible beside, not instead of, the effective
  fallback. Missing row, missing file, audience denial and unsafe SVG cases
  mount without an error.
- Change Image still uses the completed four-source
  `MediaPickerField`/`MediaPickerPanel`. There is no second picker.
- Remove Direct Image / Use Automatic Image and external import are root
  action submissions. They add no nested confirmation modal.
- Normal replacement/removal compares the mount-time Media ID and
  compatibility path while the owner and attachment are locked. A stale
  action fails with localized validation and preserves the newer image.
  Unsafe repair remains fingerprint-guarded.

The post-P3 modal contract is preserved:

```text
owner page/table Livewire component
└── integrated owner-image action modal or slide-over
    ├── read-only detail Blade view
    ├── local copy and safe links
    ├── root change/remove/import submissions
    └── existing MediaPickerField
        └── schema-owned MediaPickerPanel
            └── existing picker-owned child actions
```

The owner action remains the single outer modal owner. The picker retains no
form wrapper, its mounted FileUpload lifecycle, direct single choice,
explicit multiple choice, busy/offline guards, focus restoration, stable
opener, RTL/LTR behavior and accessibility contracts.

The rider converts exactly 43 ungrouped actions across 12 Resource or
RelationManager tables to icon-only triggers. Every action retains its
evaluated translated label as the accessible name and tooltip, and every
surface has distinct semantic `Heroicon` values. The configurator is called
only by those 12 table builders. The Settings Backups ActionGroup stays
grouped with visible labels inside its dropdown; custom Page tables and all
excluded action locations remain untouched.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| Durable Package 4 research/plan and active-doc reconciliation | Implemented | Audit/option, requirements R051-R061, selected architecture, task boundaries and current routing are recorded. |
| Preserve Package 2/3 acquisition and attachment contracts | Already correct / regression-preserved | Gallery remains mutation-free; completed Upload/URL/Storage admission is permanent; owner attachment remains pending until outer Save. |
| Reuse one picker and post-P3 modal ownership | Already correct / regression-preserved | The existing schema-owned `MediaPickerPanel` is the only picker child; no duplicate modal partial or form wrapper was introduced. |
| Compact table thumbnail on three list surfaces | Implemented | One reusable `OwnerImageColumn` retains 48-pixel thumbnails and current eager loads. |
| Bounded hover/focus/touch/click UX | Implemented | Tooltip and detail previews are at most 300 by 300; click/keyboard/touch are authoritative and do not navigate. |
| Shared detail experience on all six owner surfaces | Implemented | One action factory and one view serve the three lists and three edit/workspace pages. |
| Truthful effective source | Implemented | Direct, compatibility, inherited, external, family/global default and empty sources are identified separately from direct-association health. |
| Useful canonical metadata | Implemented | Original/stored filenames, title, MIME/extension, dimensions, size, directory/disk, reference key and day-first timestamp are projected lazily. |
| Safe preview/download/review | Implemented | Links use `AdminMediaFileController` routes or authorized Resource URLs; no raw storage path is constructed or exposed as a download URL. |
| Copy filename with feedback | Implemented | Local Alpine clipboard handling shows an `aria-live` success message; technical values remain LTR inside Hebrew RTL. |
| Same-page Change Image | Already correct / integrated | `ContentImageActions` still owns replacement through the completed picker, now inside the detail workspace. |
| Remove Direct / Use Automatic | Implemented | Root submit detaches only the direct association and clears its compatibility mirror; Media/file remain intact. |
| External URL import | Already correct / integrated | The existing queued import path is reused and the URL is labelled as external, not Media. |
| Broken association repair choices | Implemented | Fallback, warning, replace, detach and Media review are independently visible where applicable. |
| D01 fallback boundary | Already correct / regression-preserved | Only missing row/file, audience denial and unsanitized SVG trigger the existing delivery fallback. |
| Stale owner recheck | Implemented | Expected Media ID/path are rechecked under lock for normal replace/remove; unsafe repairs keep their diagnostic fingerprint. |
| Unrelated owner saves preserve evidence | Already correct / regression-preserved | Ordinary form-save semantics were not changed. |
| Query and Livewire-state boundary | Implemented / preserved | List relationships remain eager-loaded, detail work is lazy and request-local, and only small scalar action state is serialized. |
| Hebrew/English, RTL/LTR, day-first and narrow screens | Implemented | Translations, technical-direction handling and real browser coverage are present. |
| 43-action Resource-table rider | Implemented | Exact inventory, icon-only trigger, hidden authoritative label, matching tooltip and distinct semantic icon are tested. |
| Settings Backups group/custom Page exclusions | Already correct / regression-preserved | Grouped dropdown labels remain visible and custom Page tables are not configured. |
| Schema, migration, provider or dependency work | Not applicable | None was needed or changed. |
| Package 5/file lifecycle/image editing/public redesign | Deferred by approved boundary | No part was implemented. |
| Dedicated security audit | Not applicable | Existing admin authorization and public-delivery boundaries were preserved and regression-tested only. |
| Live/local data, production, deployment and push | Not applicable / excluded | No such action occurred. |

## Files changed

### Owner-image action, presentation and mutation safety

- `app/Filament/Actions/ContentImageActions.php`
- `app/Filament/Tables/OwnerImageColumn.php`
- `app/Support/Media/MediaAttachmentFormState.php`
- `app/Support/Media/MediaAttachmentManager.php`
- `app/Support/Media/OwnerImagePresentation.php`
- `app/Support/Media/OwnerImagePresenter.php`
- `resources/views/filament/actions/current-content-image.blade.php`
- `resources/views/filament/tables/columns/owner-image-preview-tooltip.blade.php`
- `lang/en/admin.php`
- `lang/he/admin.php`

### Six owner surfaces

- `app/Filament/Resources/ContentGroups/Tables/ContentGroupsTable.php`
- `app/Filament/Resources/ContentItems/Tables/ContentItemsTable.php`
- `app/Filament/Resources/ContentGroups/RelationManagers/ContentItemsRelationManager.php`

Podcast edit, classic episode edit and episode workspace already consumed the
shared `ContentImageActions` factories, so no page-specific duplicate was
added.

### Resource-table action rider

- `app/Filament/Resources/Support/ResourceTableActions.php`
- `app/Filament/Actions/EditEffectiveTranscriptionAction.php`
- `app/Filament/Resources/Authors/Tables/AuthorsTable.php`
- `app/Filament/Resources/Categories/Tables/CategoriesTable.php`
- `app/Filament/Resources/ContentGroups/RelationManagers/ContentItemsRelationManager.php`
- `app/Filament/Resources/ContentGroups/Tables/ContentGroupsTable.php`
- `app/Filament/Resources/ContentItems/RelationManagers/TranscriptionsRelationManager.php`
- `app/Filament/Resources/ContentItems/Tables/ContentItemsTable.php`
- `app/Filament/Resources/ContentTags/Tables/ContentTagsTable.php`
- `app/Filament/Resources/HomepageSections/Tables/HomepageSectionsTable.php`
- `app/Filament/Resources/Media/Tables/MediaTable.php`
- `app/Filament/Resources/PublicFormSubmissions/Tables/PublicFormSubmissionsTable.php`
- `app/Filament/Resources/Transcriptions/Tables/TranscriptionsTable.php`
- `app/Filament/Resources/Users/Tables/UsersTable.php`

### Tests

- `tests/Browser/MediaPickerBrowserTest.php`
- `tests/Browser/OwnerImageWorkspaceBrowserTest.php`
- `tests/Feature/OwnerImageWorkspaceTest.php`
- `tests/Feature/ResourceTableIconActionsTest.php`

### Documentation

- `docs/phase-02/current-project-state.md`
- `docs/phase-02/media-program-context.md`
- this handoff
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/research/media-program/00-media-program-requirements-decisions-and-method.md`
- `docs/research/media-program/02-media-program-master-plan.md`
- `docs/research/media-program/04-active-document-supersession-map.md`
- `docs/research/media-program/packages/04-owner-image-ux-plan.md`
- `docs/research/media-program/packages/04-owner-image-ux-research.md`

## Tests added or updated

- Effective-source matrix: direct, external, inherited podcast cover,
  configured family default, global default and empty fallback.
- Broken direct matrix: missing Media row, missing file, audience denial and
  unsafe SVG, with effective fallback kept separate.
- Canonical metadata, original/stored filenames, safe URLs and fresh-on-open
  Media path/title changes.
- Root remove and external import behavior.
- Stale normal replacement and removal refusal.
- Shared table detail action and podcast/classic episode/workspace action
  reuse.
- Exact bilingual Package 4 keys.
- Exact 43-action/12-surface rider inventory, hidden accessible labels,
  matching tooltips, semantic/distinct icons and excluded groups/pages.
- Real Hebrew RTL and English LTR browser coverage at 1280 and 390 pixels:
  keyboard/touch open, bounded hover/detail, same-page URL, single dialog,
  no nested form, copy announcement, technical LTR, safe download, Escape
  close/focus restore, no overflow and no JavaScript errors.
- Existing picker browser coverage now waits for Livewire request completion
  after offline reconciliation and resolves the current schema-owned child
  after a morph before driving nested actions. This removes suite-order races
  without changing picker behavior.
- Existing Package 2/3 picker, owner form, D01, performance, Resource,
  authorization and episode-workspace regressions.

## Commands and results

| Command / check | Result |
|---|---|
| Mandatory preflight/orientation, Git root/status/log and prompt check | PASS; exact clean approved baseline, 23 ahead / 0 behind, no active prompt or overlapping writer. |
| Laravel Simplifier Stage 2 gate | PASS; exact Audit ID, Option ID, Package 4+rider scope and dependency/Package 5/no-push exclusions matched. |
| Installed manifests/locks/generated guidance and handoff verification | PASS; dependency/Boost/Fontaine work remained completed baseline and no dependency file changed. |
| Laravel Boost installed-version docs, official docs/source and FilamentExamples multi-query refinement | PASS; Boost returned installed-version guidance, installed source returned full behavior evidence, and FilamentExamples returned search snippets/paths only. |
| Initial owner workspace focused iterations | Expected REDs exposed missing detail/source/metadata, stale mutation and broken-row behavior; corrected focused runs passed. |
| Initial rider widened Resource run | Expected RED: built-in Edit/Delete actions had no explicit icon under the new invariant; bounded defaults corrected it. |
| Widened Resource regression after rider correction | PASS: 72 tests / 1,110 assertions. |
| Initial owner browser command inside managed macOS sandbox | Infrastructure FAIL: Chromium `MachPortRendezvousServer ... Permission denied`; identical browser commands ran outside the sandbox. |
| Owner browser construction iterations | Expected REDs exposed a test selector mismatch and a browser-inaccessible fake public-storage URL; the final column uses the safe admin preview route for canonical owner Media. A settled 390-pixel check then exposed a real 406-pixel intrinsic-width overflow; `min-w-0` and responsive bounded image sizing corrected it. |
| Earlier complete owner browser file | PASS outside the sandbox: 2 tests / 62 assertions. |
| Fresh-metadata test construction | Expected RED twice: the test used guarded `update()` for Curator path/name and therefore did not change them; corrected fixture uses explicit test-only `forceFill()->saveQuietly()`. |
| `php artisan test --compact tests/Feature/OwnerImageWorkspaceTest.php` | PASS: 16 tests / 155 assertions. |
| `php artisan test --compact tests/Feature/ResourceTableIconActionsTest.php` | PASS: 2 / 250. |
| `php artisan test --compact tests/Feature/OwnerImageWorkspaceTest.php tests/Feature/MediaInventoryPickerReplacementTest.php tests/Feature/ResourceTableIconActionsTest.php` | PASS: 32 / 552. |
| Final `php artisan test --compact tests/Browser/OwnerImageWorkspaceBrowserTest.php` | PASS outside the sandbox: 2 / 64. |
| PhpStorm WARNING-or-higher inspection of all changed application PHP and new tests | One real presenter return-type proof warning corrected; final new/changed Package 4 files have 0 warnings. One unrelated 2026-07-09 unreachable-return warning remains in `EditEffectiveTranscriptionAction`, where this task changed only its icon. |
| Widened Package 2/3/4, D01, performance, owner, Resource and authorization feature matrix | PASS: 189 tests / 2,813 assertions. |
| First ordered Pint / FilaCheck / Vite cycle | Pint PASS; FilaCheck PASS with 0 issues; Vite PASS without the repaired Fontaine warning. |
| First full `php artisan test` | Expected verification RED: 1,125 of 1,127 tests passed with 14,709 assertions; one existing picker child-action owner race and one new narrow owner-modal overflow failed. No non-browser application regression failed. |
| Focused picker lifecycle diagnosis outside the sandbox | Direct offline close was green; nested offline reconciliation plus later child actions reproduced the race. Tests now wait through Livewire's post-render request boundary and re-resolve the currently owned child root after morphs. |
| Final complete `tests/Browser/MediaPickerBrowserTest.php` pre-gate run | PASS outside the sandbox: 7 / 140. |
| Combined Package 3 picker and Package 4 owner browser pre-gate run | PASS outside the sandbox: 9 / 204. |
| Requirements/scope/drift sweep and `git diff --check` | PASS; all requirements MI-R051-MI-R061 are covered, exactly 12 rider configurator call sites remain, and no migration/configuration/manifest/lockfile drift exists. |
| `vendor/bin/pint --test` | PASS. |
| `vendor/bin/filacheck` | PASS with 0 issues. |
| `npm run build` | PASS; production assets built and the repaired Fontaine warning did not return. |
| Full `php artisan test` last | PASS outside the macOS sandbox: 1,127 tests / 14,725 assertions in 476.674 seconds. |

## Performance and safety boundaries

- Table queries retain their existing eager loads. The shared column performs
  no detail metadata query and adds no provider binding or Livewire Media
  model state per row.
- Effective URL resolution remains the existing list behavior. Tooltip
  markup is generated only by the supported tooltip path and its image is
  lazy; detail projection runs only after opening the action.
- Presenter results are memoized only inside one rebuilt action instance so
  modal content and form fill do not duplicate the same detail projection.
- Preview/download controllers freshly resolve authorization, Media row and
  file state. Download never accepts or constructs a raw client-visible
  storage path.
- Existing attachment policies, inventory selection checks, mutation fence
  and diagnostic fingerprint remain authoritative.
- No migration, relational/settings schema, package, manifest, lockfile,
  provider, cache protocol, queue protocol or filesystem journal changed.

## Local Front Check Report

1. Open the admin Podcasts table in Hebrew and expect a compact image button
   in each image cell and icon-only row actions with Hebrew tooltips.
2. Hover a podcast image and expect a bounded preview no larger than 300 by
   300 pixels.
3. Focus the image button with the keyboard, press Enter, and expect Image
   Details to open without leaving the Podcasts URL.
4. Confirm the effective image source, original filename, stored filename,
   metadata and safe Download/Review Media links are accurate.
5. Click each filename copy control and expect visible copied feedback.
6. Click Change Image, open Gallery, Upload, URL and Storage, then close the
   picker and expect the owner workspace and focus to remain stable.
7. Cancel the owner workspace and expect no attachment change and no deletion
   of any completed acquisition.
8. Reopen the workspace, choose a different image, submit Change Image, and
   expect only the attachment and compatibility mirror to change.
9. Open an episode that has a direct image and an external/podcast/default
   fallback, click Remove Direct Image / Use Automatic Image, and expect the
   accurate automatic source to appear while the Media row/file remains.
10. Open a deliberately broken direct association and expect the working
    fallback above a separate warning plus Replace, Detach and Review Media
    choices where applicable.
11. Repeat the episode flow from the Episodes table, a podcast's Episodes
    relation manager, classic episode edit and Episode Workspace.
12. Switch to English and a narrow mobile viewport and expect LTR copy,
    technical filenames still LTR, touch-open details, no horizontal overflow
    and restored focus after closing.
13. Open every Resource and RelationManager table and expect each ungrouped
    row action to be icon-only with its old translated label available as the
    accessible name and tooltip.
14. Open Settings Backups and expect its grouped trigger and visible labels
    inside the dropdown to remain unchanged.

## Deferred and excluded

- Package 5 Files Discovery and every physical lifecycle operation.
- Move, rename, swap, replace bytes, trash, restore, purge and cleanup.
- Image editing, crop, optimization, resizing, normalization and new
  derivatives.
- General Media Resource detail redesign or custom Page-table rider expansion.
- New schema, migration, settings shape, provider or dependency.
- Composer/npm refresh, manifest/lock change and Boost discovery.
- Unrelated Package 3 correction and public-front redesign.
- Live/local-development data or storage action, production action,
  deployment, process action and push.

## Commit hash

Pending.
