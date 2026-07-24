# Media Operations UX3 Mini-task 1 Library Card Hierarchy Handoff

## Scope and baseline

- Audit: `LS-20260724-PODTEXT-MEDIA-OPERATIONS-UX-03`
- Approved option:
  `MEDIA-OPS-UX3-O2-PDF-CONTRACT-TARGETED-WORKSPACES`
- Binding design contract: `PODTEXT-MEDIA-UX-CONTRACT-20260724-CORRECTED`
- Approved slice: Mini-task 1 only
- Repository: `/Users/studioycm/Herd/PodText`
- Starting branch/HEAD: clean `main` at
  `6d8f7f73742448a7671fb5b8f238bf01ebf6b5ad`, 29 commits ahead of
  `origin/main`
- Installed stack: Laravel 13.21.1, Filament 5.7.3, Livewire 4.3.3,
  Pest 4.7.5 and Tailwind CSS 4.3.3

Preflight found no active prompt under `prompts/pre-13-prompts/`, no
overlapping writer and no unexpected PHP, Blade, migration, test,
configuration, dependency or documentation drift.

This run implemented only the approved Media Library card/action hierarchy.
It did not start Mini-task 2, add task views or return-state URLs, build the
complete Care workspace, add repair actions/results, or implement any Package
5 lifecycle. No migration, dependency, local-development data/storage,
production, branch/worktree, deployment or push action occurred.

## Outcome

The existing native Filament Media card gallery now follows the corrected
contract hierarchy:

1. safe contain-fit preview;
2. stable human identity using title, original filename, stored basename and
   record key as bounded fallbacks;
3. localized known-reference summary;
4. Ready or Needs attention state;
5. persistent concrete primary issue plus an additional-issue count;
6. quiet MIME, extension, dimensions, size, location and day-first timestamp;
7. one visible Open details button and one quiet More actions menu.

The card body and visible details action now share the existing authorized
Media edit/details destination. The five existing secondary actions remain
named `view`, `download`, `rename`, `swap` and `delete` inside one native
`ActionGroup`. Their routes, authorization, confirmation/form behavior and
`MediaFilesystemMutationCoordinator` callbacks are unchanged.

Current irreversible record and bulk deletion are labelled **Delete
permanently** and **Delete selected permanently**. No Trash, restore, purge or
new deletion authority is implied.

## Safe known-reference semantics

`ListMedia` continues to prime `MediaReferenceFinder` for the bounded current
page. Public-disk cards count its unique translated reference results. Zero is
reported as **No known references**, never **unused**.

The existing finder deliberately does not project non-public-disk rows. Those
cards therefore say **Reference count unavailable** rather than presenting a
false zero. This is presentation-only: the finder, typed attachments, policies
and mutation guards remain unchanged.

The summary is intentionally not called owners or usages. A typed direct,
effective, inherited and settings usage model belongs to a later approved
workspace.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| Image-first card hierarchy | Implemented | Record actions explicitly render after the existing preview and content. |
| Stable human identity | Implemented | Title falls back to original filename, stored basename, Media name and finally key; preview alt text uses the same identity. |
| Bounded known-reference summary | Implemented | Uses the existing current-page prime and localized cardinality without per-card query growth. |
| Honest zero-reference wording | Implemented | Zero says No known references; non-public rows say the count is unavailable. |
| Persistent primary issue | Implemented | The first ordered `MediaInventoryDiagnostics` reason is visible in the card rather than tooltip-only. |
| Additional issue count | Implemented | Remaining reasons are represented by localized pluralized count. |
| Quiet technical facts | Implemented | Existing file summary, location and created date follow usage/issue evidence and use gray hierarchy. |
| One visible details action | Implemented | Existing stable `edit` action is a labelled Open details button. |
| One quiet secondary menu | Implemented | Native accessible `ActionGroup` contains the five unchanged secondary actions. |
| Card activation opens details | Implemented | Explicit authorized `recordUrl()` prevents the old raw-preview action-name fallback. |
| Permanent deletion wording | Implemented | Record and selected-record delete labels state permanence without adding lifecycle behavior. |
| Hebrew RTL and English LTR | Implemented / regression-preserved | All new copy has HE/EN keys; the real-browser matrix covers both directions and 390-pixel layout. |
| All Media completeness and Needs Repair | Already correct / regression-preserved | Inventory query and diagnostic filter are unchanged. |
| Search, MIME filter, selection and 25-record pagination | Already correct / regression-preserved | Native Table controls remain unchanged. |
| Safe preview/download authorities | Already correct / regression-preserved | Existing protected routes and fresh trusted-record download resolution remain. |
| Mutation authorization and coordination | Already correct / regression-preserved | Existing policies, referenced-file blocks and mutation coordinator callbacks remain authoritative. |
| Gallery mutation-free selection and immediate acquisition permanence | Already correct / not touched | Package 2–4 picker and acquisition code did not change. |
| Task views, URL return context and whole-library counts | Deferred | Mini-task 2 was not started. |
| Complete Care, typed usage, reason-specific fixes, recheck and results | Deferred | Requires later explicitly approved mini-tasks. |
| Package 5 Files Discovery, move, Trash, restore and purge | Deferred / excluded | No lifecycle state, route, action or placeholder was added. |
| Migration, dependency, production or push | Not applicable / excluded | None occurred. |

## Package 1–4 authority preservation

- Every Curator row remains visible through the existing inventory query.
- Needs Repair remains diagnostic and uses
  `MediaInventoryDiagnostics`.
- `media_attachments.media_id` remains local owner authority.
- Curator `path` remains file-location authority.
- Existing Gallery selection remains mutation-free.
- Successful Upload, URL and Storage admission remains immediately permanent.
- Owner cancellation remains a no-op for existing selection and does not
  delete admitted Media.
- Shared referenced bytes remain ineligible for rename, replacement and
  deletion through the existing policy/coordinator boundaries.
- Safe preview/download controllers remain authoritative.
- No attachment, asset/binding, acquisition, filesystem or operation-journal
  write path changed.

## Files changed

### Application and localization

- `app/Filament/Resources/Media/Tables/MediaTable.php`
- `lang/en/admin.php`
- `lang/he/admin.php`

### Tests

- `tests/Feature/AppOwnedMediaResourceTest.php`
- `tests/Feature/ResourceTableIconActionsTest.php`
- `tests/Browser/MediaResourceGalleryBrowserTest.php`

### Documentation

- `docs/research/media-operations-ux3/01-mini-task-1-library-card-hierarchy-research.md`
- `docs/research/media-operations-ux3/02-mini-task-1-library-card-hierarchy-implementation-plan.md`
- `docs/phase-02/current-project-state.md`
- this handoff
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

No migration, manifest, lockfile, configuration, model, policy, controller,
service, Livewire component or Blade file changed.

## Tests added or updated

- Card body and primary details action use the same edit/details URL.
- Display identity falls back from absent title to original filename.
- The title, original-filename, stored-basename and Media-name fallback order
  is directly asserted.
- Two known legacy-path references render localized cardinality.
- A zero-reference public row says No known references and never unused.
- A referenced non-public row reports that the bounded count is unavailable
  instead of falsely reporting zero.
- Multiple ordered diagnostic reasons render one persistent primary reason
  and one additional-reason count.
- Card text order places reference and issue evidence before technical facts.
- Two top-level actions remain six stable flat action names.
- The primary action is a visible labelled button; the second is a gray
  accessible icon-button group.
- Record actions explicitly render after card content.
- The general Resource icon-only rider remains unchanged for the other 37
  actions.
- Existing inventory, filter, pagination, mutation authorization, query and
  storage-probe coverage remains active.
- The real browser opens one healthy-card menu and verifies all five labelled
  entries in Hebrew and English.
- The real browser retains three desktop columns, one 390-pixel column,
  persistent issue text, known-reference summary, visible details, accessible
  group trigger, no horizontal overflow and no JavaScript errors.

## Installed-version research record

- Laravel Boost returned installed-version application information and
  Filament 5 action/action-group/testing documentation.
- FilamentExamples was queried in multiple short first- and second-pass
  searches. Relevant search snippets included Table Rendered as a Card Grid,
  Repair Salon CRM OrdersTable, Complex Orders Table and Tournament
  `view_stats`. The configured server exposed search snippets only and no
  separate source/detail reader.
- Installed Filament 5.7.3 source confirmed visible button labels,
  action-group icon triggers, localized accessible names, labelled dropdown
  items, Enter/Space activation, teleported record dropdowns and
  `RecordActionsPosition::AfterContent`.
- The repository Filament form UX, performance, Laravel Simplifier,
  Laravel/PHP, Livewire, Pest and Spatie standards were applied.
- PhpStorm MCP inspections were requested but no callable PhpStorm inspection
  tool was available in this task; focused tests, FilaCheck and the full gate
  provide the recorded verification instead.

## Commands and results

| Command / check | Result |
|---|---|
| Git root/status/HEAD/log/ahead and prompt check | PASS; clean `main` at `6d8f7f7`, 29 ahead, no active prompt. |
| Mandatory repository lessons/state/ledger/latest handoffs and approved contract orientation | PASS; Mini-task 1 and Package 1–4 boundaries reconciled before edits. |
| Laravel Simplifier Stage 2 gate | PASS; exact Audit ID and Option ID matched the operator approval. |
| Laravel Boost, FilamentExamples and installed-source research | PASS with evidence levels recorded above. |
| Initial focused baseline | PASS: 18 tests / 551 assertions. |
| Tests-only focused run | Expected RED: 20 tests, 17 passed, 3 failed / 513 assertions; missing hierarchy/copy/grouping behavior. |
| Explicit card-record URL test | Expected RED: 1 failed / 3 assertions; old card destination resolved to protected raw preview. |
| First implementation focused run | 19 passed, 1 failed / 529 assertions; test fixture creation correctly issued a portable key, so it did not represent the intended two-reason legacy row. |
| Corrected focused feature/action run | PASS: 20 tests / 546 assertions. |
| Focused Media gallery browser inside macOS sandbox | Infrastructure FAIL: Chromium `MachPortRendezvousServer ... Permission denied`. |
| Identical focused browser command outside sandbox | PASS: 2 tests / 54 assertions. |
| Package 1–4 mutation/inventory/performance/picker/owner regression set | PASS: 102 tests / 1,374 assertions. |
| Iteration `vendor/bin/filacheck --dirty` | PASS with 0 issues. |
| Focused run after non-public reference-truth correction | PASS: 21 tests / 550 assertions. |
| Independent bounded diff review | One low test-coverage gap found and closed for stored-basename/Media-name identity fallbacks; no production, contract, authorization, query, accessibility or scope defect found. |
| First stable-identity fallback test run | 21 passed, 4 fixture errors / 554 assertions; unsaved test Media had no key for Filament column state caching. |
| Corrected stable-identity fallback run | PASS: 25 tests / 558 assertions using persisted isolated fixtures. |
| Requirements/scope/authority sweep and `git diff --check` | PASS; no Mini-task 2, Package 5, migration, dependency, data, production or push drift. |
| First final `vendor/bin/pint --test` | FAIL: import ordering in `MediaTable` and import/array/unary spacing in the focused feature test. |
| Targeted `vendor/bin/pint` on the two reported PHP files | PASS; only the reported mechanical formatting was changed. |
| `vendor/bin/pint --test` | PASS in the final canonical gate. |
| `vendor/bin/filacheck` | PASS with 0 issues in the final canonical gate. |
| `npm run build` | PASS in the final canonical gate. |
| Full `php artisan test` last | PASS in the final canonical gate. |

Feature and browser tests used isolated databases, fake storage and
`Http::preventStrayRequests()`. No test used the local development database,
live HTTP, live mail or production state.

## Performance and security boundaries

- The current 25-card page limit remains.
- `ListMedia` continues to prime references once per current page.
- The existing 1/10/25 card reference-query ceiling remains green.
- The existing 1/10/25 raster storage-probe budget remains green.
- No whole-library count, task badge, filesystem scan, cache or materialized
  health state was added.
- No Blade query, raw file URL, unsafe path, raw operation error or secret is
  exposed.
- Per-action policy checks remain on every grouped child.
- Download still resolves and reauthorizes the current trusted Media row.
- Replacement retains purpose, MIME, size and temporary-upload validation.
- Rename, replacement and deletion still execute only through
  `MediaFilesystemMutationCoordinator`.

## Assumptions

- The existing Media edit page is the bounded details destination for this
  mini-task; it is not represented as the complete later Care workspace.
- Known references means the unique references projected by the current
  bounded finder, not a claim about every effective or inherited usage.
- Non-public rows cannot receive a complete count from that existing
  projector, so explicit unavailable wording is more truthful than a partial
  or false zero.

## Local Front Check Report

1. Open Admin > Media in Hebrew and expect every existing Media row to remain
   available in the native card gallery.
2. Inspect a healthy card and expect preview, human identity, known-reference
   summary, Ready status, quiet file facts and actions in that order.
3. Inspect a Needs Repair card and expect Needs attention plus one concrete
   reason visible without hovering.
4. Inspect a row with multiple diagnostic reasons and expect one primary
   reason plus an additional-issues count.
5. Inspect a public row with no projected references and expect No known
   references; do not expect unused.
6. Inspect a non-public diagnostic row and expect Reference count unavailable
   rather than a zero claim.
7. Click the card body or Open details and expect the existing Media details
   page rather than the raw image preview.
8. Open More actions and expect labelled Preview, Download, Regenerate stored
   filename, Replace file and Delete permanently entries only when their
   existing authorization allows them.
9. Inspect a referenced Media row and expect file-surgery actions to remain
   blocked by the existing policy.
10. Use search, MIME and Needs Repair filters, a selection checkbox and the
    selected-record action group; expect existing native Table behavior.
11. Resize to 390 by 844 and expect one in-viewport card column with no
    horizontal scrolling or clipped menu.
12. Repeat in English and expect LTR layout with the same visible hierarchy,
    accessible controls and precise permanent-delete wording.

## Deferred and excluded

- Mini-task 2 task views, bounded task counts and URL/return context.
- A complete Care page and typed direct/effective/inherited/settings usage.
- Reason-specific fix actions, blockers, recheck, structured results and
  retry/next-issue flow.
- Intake, batch, provider and recovery workspace redesign.
- Package 5 Files Discovery, move, lifecycle, Trash, restore and purge.
- Schema, migration, settings, cache, queue, dependency or provider changes.
- Local-development or production data/storage actions.
- Deployment and push.

## Commit hash

`0e42ea47d2813141fa8583fc36532c3a85250c33`
