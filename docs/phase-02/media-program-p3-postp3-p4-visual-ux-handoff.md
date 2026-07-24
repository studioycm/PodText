# Media Program Post-P3 / Package 4 Visual UX Handoff

## Scope and baseline

- Audit:
  `LS-20260724-PODTEXT-MEDIA-P3-POSTP3-P4-VISUAL-UX-01`
- Approved option:
  `MEDIA-P3-POSTP3-P4-VUX-O2-NATIVE-CARD-GALLERY`
- Repository: `/Users/studioycm/Herd/PodText`
- Starting branch/HEAD: clean `main` at
  `5b737e1e0f3d355a8dbb51a6887c2e0e785ed463`, 27 commits ahead of
  `origin/main`
- Package 4 correction implementation:
  `f905de83e996d34c65b767deb7ce121283f0786a`
- Package 4 correction hash stamp:
  `5b737e1e0f3d355a8dbb51a6887c2e0e785ed463`
- Installed stack: Laravel 13.21.1, Filament 5.7.3, Livewire 4.3.3,
  Curator 5.1.2, Boost 2.4.13, Pest 4.7.5 and Tailwind 4.3.3.

Preflight matched the approved baseline exactly. There was no active prompt
under `prompts/pre-13-prompts/`, no overlapping writer and no unexpected PHP,
Blade, migration, test, configuration, dependency or documentation drift.

This run implemented only the approved visual correction. The operator
amended the topbar requirement before implementation: the real admin topbar
remains sticky. Full-page capture tooling temporarily applied a scoped
capture-only class/style and removed it after each capture. No runtime topbar
or Card Template Editor layout changed.

No dependency, schema, migration, settings property, provider, Package 5,
local-development data, production or push action was included.

## Outcome

### Media Resource

The existing Filament Table now renders the complete Media inventory through
native card-layout columns and `contentGrid()`:

- one column on narrow screens, two from `md`, three from `lg` and four from
  `2xl`;
- one lazy, contain-fit safe preview;
- display title, original filename when known, stored basename, MIME,
  extension, dimensions, size, disk/directory, repair status and day-first
  timestamp;
- unchanged search, MIME and Needs Repair filters, 25-record pagination,
  selection checkboxes, bulk deletion and all six existing record actions;
- unchanged authorization, safe preview/download routes and filesystem
  mutation coordinators.

`MediaReferenceFinder` is primed once for the current page before record-action
policy evaluation. A correlated selected count avoids duplicate-storage
identity queries per card. `PublicMediaDelivery` owns the request-local
file-existence decision used by inventory diagnostics, so each projected
raster performs one existence probe rather than repeating it for selection,
preview and repair state.

### Owner-image action

The shared podcast/episode action now:

- uses `Width::SevenExtraLarge`;
- disables Filament modal autofocus so it opens at the top;
- keeps Replace Image first/default and Details and Effective Image second;
- renders one compact selected-image summary using Curator's constrained
  contain-fit behavior;
- does not add a duplicate selected-image banner;
- keeps the complete schema-owned Gallery/Upload/URL/Storage picker inline;
- places acquisition controls before Gallery below the `lg` breakpoint;
- retains one outer action modal, one schema-owned Livewire child and no
  nested form or second picker modal.

Reopening the detail workspace refreshes canonical owner/media state and
explicitly forgets the relevant effective-delivery and inventory decisions.
A file removed after an earlier presentation is therefore shown as a broken
direct association with preserved fallback evidence instead of a stale
preview.

### Storage acquisition source

Storage now has a 1500-millisecond server-backed search and explicit Refresh.
Search and Refresh execute the same normalized server query.

Discovery is breadth-first and recursive only inside:

1. the Laravel `public` disk rooted at `storage/app/public`; and
2. the explicitly configured `public/images` source.

It applies separate candidate, examined-entry and directory ceilings, matches
filename, relative path and translated source label, keeps encrypted opaque
candidate identities and rechecks source containment, extension and existence
before admission. Safe public-disk rasters may be registered in place;
`public/images` input is copied through the existing admission boundary.
Source files are not mutated.

This is still a bounded acquisition source, not Package 5 Files Discovery.
It does not scan `public/build`, arbitrary public roots or the host
filesystem.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| Filament-native responsive Media card gallery | Implemented | Native layout columns and `contentGrid()` retain the existing Table state model and controls. |
| Complete inventory and Needs Repair visibility | Already correct / regression-preserved | No inventory scope or diagnostic row is hidden. |
| Safe preview/download, mutation actions and authorization | Already correct / regression-preserved | Existing controller routes, policies and coordinators remain authoritative. |
| Bulk selection, search, filters and pagination | Already correct / regression-preserved | Native Table checkboxes, filters and fixed 25-record pages remain available in card mode. |
| Seven-extra-large owner modal | Implemented | Only the shared owner-image action changed width. |
| Disable modal autofocus scrolling | Implemented | The action uses `modalAutofocus(false)` and browser proof opens at scroll position zero. |
| Replace first, Details second | Already correct / regression-preserved | Tab order and one-modal topology remain unchanged. |
| Compact contain-fit selected image | Implemented | The existing selected block is compact only in inline owner mode; no second selected banner exists. |
| Acquisition controls before Gallery on narrow screens | Implemented | Responsive order changes below `lg`; desktop keeps Gallery beside the source rail. |
| Storage search at 1500 milliseconds and Refresh | Implemented | Livewire sends normalized server queries; Refresh reruns the current search. |
| Nested Laravel-public and `public/images` discovery | Implemented | Recursive traversal is allowlisted, opaque and containment-checked. |
| Candidate/traversal/directory budgets | Implemented | Config caps and fake-filesystem regressions stop result, entry and directory traversal. |
| Media query and filesystem-probe budgets | Implemented | Page priming, selected storage count and one request-local existence decision remove count-dependent policy queries and duplicate raster probes. |
| Fresh metadata/file evidence when details reopen | Implemented / regression-preserved | Fresh owner rows plus explicit delivery/diagnostic invalidation preserve stale-write and broken-association truth. |
| Hebrew RTL, English LTR, keyboard, focus, touch and narrow screens | Implemented / regression-preserved | Feature and real-browser matrices cover both locales and 390-pixel layouts. |
| Capture without repeated sticky topbar | Implemented in capture tooling only | A temporary DOM class/style was added and removed; runtime remains sticky. |
| Runtime non-sticky topbar or Card Template regression adjustment | Not applicable after operator amendment | No application CSS or panel configuration changed. |
| Schema, migration, settings property, dependency or provider | Not applicable | None is required or changed. |
| Package 5, arbitrary directory browsing and physical lifecycle | Deferred / excluded | No part was implemented. |
| Broader Media/picker workflow redesign | Deferred | Requires a fresh post-Package-4 Stage 1 audit with new IDs. |
| Production, deployment and push | Not applicable / excluded | No such action occurred. |

## Files changed

### Application and presentation

- `app/Filament/Actions/ContentImageActions.php`
- `app/Filament/Resources/Media/MediaResource.php`
- `app/Filament/Resources/Media/Pages/ListMedia.php`
- `app/Filament/Resources/Media/Tables/MediaTable.php`
- `app/Livewire/Admin/MediaPickerPanel.php`
- `app/Policies/CuratorMediaPolicy.php`
- `app/Support/Media/MediaInventoryDiagnostics.php`
- `app/Support/Media/MediaRecordScope.php`
- `app/Support/Media/OwnerImagePresenter.php`
- `app/Support/Media/PublicMediaDelivery.php`
- `app/Support/Media/StorageImageCandidateBrowser.php`
- `app/Support/PublicFront/PublicDefaultImageResolver.php`
- `config/filesystems.php`
- `config/media.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `resources/views/filament/forms/components/path-curator-picker.blade.php`
- `resources/views/livewire/admin/media-picker-panel.blade.php`

### Tests

- `tests/Browser/MediaPickerBrowserTest.php`
- `tests/Browser/MediaResourceGalleryBrowserTest.php`
- `tests/Browser/OwnerImageWorkspaceBrowserTest.php`
- `tests/Feature/AppOwnedMediaPickerTest.php`
- `tests/Feature/AppOwnedMediaResourceTest.php`
- `tests/Feature/MediaAcquisitionTest.php`
- `tests/Feature/OwnerImageWorkspaceTest.php`

### Documentation

- `docs/phase-02/current-project-state.md`
- `docs/phase-02/media-program-context.md`
- this handoff
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/research/media-program/00-media-program-requirements-decisions-and-method.md`
- `docs/research/media-program/02-media-program-master-plan.md`
- `docs/research/media-program/04-active-document-supersession-map.md`
- `docs/research/media-program/packages/04-postp3-visual-ux-research.md`
- `docs/research/media-program/packages/04-postp3-visual-ux-plan.md`

## Tests added or updated

- Native Media card layout, metadata, retained filters/actions/pagination and
  complete inventory.
- Count-independent reference-query ceiling for 1, 10 and 25 Media records.
- One filesystem existence probe per projected raster for 1, 10 and 25
  records.
- Seven-extra-large owner action, disabled autofocus, unchanged tab order and
  one compact constrained selected summary.
- Explicit regression that no duplicate selected-image banner renders.
- Reopening owner details after the direct file disappears shows fresh
  missing-file evidence.
- Source controls before Gallery on narrow screens.
- 1500-millisecond Storage binding, normalized server result replacement and
  Refresh with the current search.
- Nested Laravel-public and `public/images` results, excluded
  `public/build`, opaque tokens and forged out-of-root rejection.
- Candidate, examined-entry and directory traversal ceilings.
- Hebrew RTL and English LTR browser proof for card columns, metadata,
  Needs Repair, bulk selection, modal fit, focus, contain-fit preview, tabs,
  safe detail actions and narrow overflow.
- Existing acquisition permanence, pending owner attachment, cancellation,
  busy/offline, focus restoration, touch and nested child-action ownership
  remain covered by the adjacent Package 3/4 matrices.

## Visual verification

The final browser pass inspected:

- `package4-media-card-gallery-final-viewport.png`
- `package4-media-card-gallery-final-full.png`
- `package4-media-card-gallery-final-narrow.png`
- `package4-podcast-image-modal-final-desktop.png`
- `package4-podcast-image-modal-final-narrow.png`
- `package4-podcast-image-modal-final-details.png`
- `package4-podcast-image-modal-final-storage.png`

Observed evidence:

- desktop Media uses three native cards at the tested width; 390 pixels uses
  one column with no horizontal overflow;
- the full inventory remains scrollable, lazy images load during bounded
  scrolling and the capture contains no repeated topbar;
- the owner window is 1280 pixels within a 1470-pixel viewport, opens at
  scroll position zero and contains one dialog;
- one selected summary is 106 pixels high with an 80-pixel contain-fit image;
- the 390-pixel owner window stays within 16-pixel viewport edges, does not
  overflow and puts source controls before Gallery;
- Details shows a bounded 300-by-300 effective preview and existing metadata;
- Storage shows search and Refresh, returned 28 initial local candidates,
  returned 12 nested `covers` matches after the 1500-millisecond server
  debounce and retained the search/result state after Refresh;
- closing the action left the same podcast-list URL and no open dialog; no
  attachment or acquisition action was submitted.

The capture-only class/style was removed after each full-page capture.
Post-capture inspection confirmed no capture class/style remained and the
runtime topbar retained its configured sticky behavior.

## Installed-version research record

- Laravel Boost application information returned installed-version evidence
  for PHP 8.4, Laravel 13.21.1, Filament 5.7.3, Livewire 4.3.3, Boost 2.4.13,
  Pest 4.7.5 and Tailwind 4.3.3.
- Boost Filament docs returned installed-version guidance for native Table
  layouts, content grids, image columns, record actions, action modal width
  and autofocus.
- Boost Livewire docs returned installed-version guidance for
  `wire:model.live.debounce.1500ms` and bounded serialized state.
- Boost Laravel docs returned installed-version filesystem/public-disk
  guidance.
- FilamentExamples used multiple short searches and refinement passes.
  “Table Rendered as a Card Grid” returned complete source embedded in the
  search response. Other relevant results supplied full snippets or search
  summaries; the server exposed no separate source/details reader.
- Installed Filament 5.7.3 source supplied full evidence for content grids,
  layout columns, image existence behavior, modal width/autofocus and action
  placement.
- Repository Filament form UX and performance skills guided tab/focus/mobile
  ordering, Livewire-state, query and filesystem checks.

## Commands and results

| Command / check | Result |
|---|---|
| Git root/status/HEAD/log/ahead and prompt check | PASS; exact clean starting baseline at `5b737e1`, 27 ahead and no active prompt. |
| Mandatory orientation, Package 2/3/post-P3/Package 4/dependency handoffs and current source/tests | PASS; approved boundaries reconciled before edits. |
| Laravel Simplifier Stage 2 gate | PASS; exact Audit ID and Option ID matched the approved scope. |
| Laravel Boost installed-version docs and application information | PASS; installed-version guidance recorded above. |
| FilamentExamples multi-query/refinement and installed vendor source | PASS with the evidence levels recorded above. |
| Initial focused Media/owner/acquisition matrix | PASS: 89 tests / 1,096 assertions. |
| Adjacent Media regression groups | PASS: 75 / 646 and 107 / 1,083. |
| Duplicate selected-summary regression | Expected RED, then PASS after using the existing compact selected block: 1 / 14. |
| Fresh direct-file availability regression | Expected RED with stale effective state, then PASS after bounded resolver/diagnostic invalidation: 1 / 7. |
| Explicit traversal-budget regression | PASS: 1 / 2. |
| Final focused Media/owner/acquisition matrix | PASS: 117 tests / 1,454 assertions. |
| First owner browser run inside the macOS sandbox | Infrastructure FAIL: Chromium `MachPortRendezvousServer ... Permission denied`; identical browser commands passed outside the sandbox. |
| Owner workspace browser matrix | PASS outside the sandbox: 2 tests / 96 assertions. |
| Shared picker browser matrix | PASS outside the sandbox: 6 tests / 128 assertions. |
| Media Resource gallery browser matrix | PASS outside the sandbox: 2 tests / 42 assertions. |
| Iteration `npm run build` | PASS; production assets built and the Fontaine warning did not return. |
| Capture-only desktop/full/narrow/detail/Storage browser pass | PASS; measurements and artifacts recorded above, with capture CSS cleanup verified. |
| Requirements/scope/drift sweep and `git diff --check` | PASS; MI-R066-MI-R071 are covered and no dependency/migration/Package 5/runtime-topbar/data drift exists. |
| `vendor/bin/pint --test` | PASS. |
| `vendor/bin/filacheck` | PASS with 0 issues. |
| `npm run build` | PASS; production assets built and the Fontaine warning did not return. |
| Full `php artisan test` last | PASS outside the macOS browser sandbox: 1,144 tests / 14,943 assertions in 452.618 seconds. |

No test used live HTTP or local-development storage/database state. Feature
tests used isolated databases, `Storage::fake()` and
`Http::preventStrayRequests()`. Browser checks did not submit an acquisition
or owner mutation.

## Performance and safety boundaries

- Resource reference-policy queries stay bounded by the current page rather
  than multiplying by card count.
- Raster existence work is one cached probe per projected record.
- Storage is lazy until selected and remains bounded by candidate,
  examined-entry and directory caps.
- Search returns at most the existing bounded candidate limit and does not
  serialize filesystem trees into Livewire state.
- Detail metadata remains lazy and is not added to every picker/gallery tile.
- Existing authorization, D01 fallback, SVG sanitation, safe admin delivery,
  stale owner checks, diagnostic evidence and attachment/acquisition
  transaction boundaries remain authoritative.
- No Composer/npm file, migration, database row, cache, queue, media byte,
  existing file path, filesystem journal or production state changed.

## Deferred deep Media UX findings

The final visual review found broader workflow issues that were not part of
this approved option and were intentionally not folded into it:

1. The owner Change Image window still combines pending selection,
   acquisition, gallery maintenance and outer confirmation without one clear
   end-to-end operator story.
2. “Use selected image” inside the picker and the outer “Change image”
   submission remain two confirmation concepts whose relationship needs
   dedicated UX research.
3. Gallery tiles expose maintenance actions inside a selection task, while
   selection, pending state and acquisition permanence compete for attention.
4. Media cards show useful evidence but are verbose; six adjacent icon
   actions are visually noisy and need a fresh hierarchy/detail-surface
   decision.
5. Media Create/Edit and rename/swap/delete dialogs need a separate audit of
   batch intent, preview/identity/usage context, destructive impact and
   operator wording.

These are evidence for a dedicated post-Package-4 deep Media UX Stage 1 audit.
That task must receive a fresh Audit ID and fresh Option IDs. It must inspect
the Media gallery, create/edit pages, all record-action dialogs and the full
owner picker workflow as distinct jobs. No recommendation or implementation
authority is carried forward by this handoff.

## Local Front Check Report

1. Open Media in Hebrew and expect a responsive native card gallery rather
   than the old dense row layout.
2. Confirm original and stored filenames are distinct, file facts and
   day-first date are visible, and Needs Repair rows remain present.
3. Use search, MIME and Needs Repair filters, pagination, a card checkbox and
   the bulk-action group; expect the existing Table behavior to remain.
4. Open safe preview and download from a card, then inspect edit, rename,
   replace-file and delete actions; expect existing authorization and
   confirmation behavior.
5. Resize Media to 390 pixels and expect one in-viewport card column with no
   horizontal scrolling.
6. Open a podcast or episode image action and expect one wide window at the
   top of its scroll area with Replace Image selected first.
7. Confirm there is one compact contain-fit selected-image summary and no
   repeated selected banner.
8. Switch to Details and Effective Image and expect the bounded effective
   preview, truthful source, warnings, metadata, copy feedback and safe
   actions.
9. At 390 pixels, reopen Replace Image and expect Upload/URL/Storage controls
   before the Gallery without horizontal overflow.
10. Open Storage, search for a nested filename or folder path, wait 1500
    milliseconds and expect server results from the allowlisted Laravel public
    disk or `public/images`.
11. Click Refresh and expect the current search to remain while candidates
    reload.
12. Cancel the owner action and expect the owner attachment to remain
    unchanged and no completed acquisition to be removed.
13. Repeat the owner checks from podcast list/relation/edit and episode
    list/classic-edit/workspace surfaces.
14. Repeat in English and expect LTR layout, the same keyboard/touch access
    and no narrow-screen overflow.
15. For a full-page screenshot only, add the dedicated capture class/style,
    capture, remove both immediately and confirm the runtime topbar remains
    sticky afterward.

## Deferred and excluded

- Dedicated post-Package-4 deep Media UX audit and redesign.
- Package 5 Files Discovery and physical lifecycle.
- Arbitrary public/build/host filesystem browsing.
- Image editing, crop, optimization, downsizing or normalization.
- Composer/npm/toolchain changes and Boost discovery.
- New schema, migrations, settings properties, providers or journals.
- Local-development or production data/storage actions.
- Deployment and push.

## Commit hash

Pending implementation commit.
