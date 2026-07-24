# Media Operations UX3 Mini-task 2 Canonical Task Context Handoff

## Scope and baseline

- Parent option:
  `MEDIA-OPS-UX3-O2-PDF-CONTRACT-TARGETED-WORKSPACES`
- Mini-task 2 audit:
  `LS-20260724-PODTEXT-MEDIA-OPERATIONS-UX3-M2-01`
- Approved Mini-task 2 option:
  `MEDIA-OPS-UX3-M2-O2-CANONICAL-TASK-CONTEXT`
- Binding design contract: `PODTEXT-MEDIA-UX-CONTRACT-20260724-CORRECTED`
- Approved slice: Mini-task 2 only
- Repository: `/Users/studioycm/Herd/PodText`
- Starting branch/HEAD: clean `main` at
  `69aa0ce3f4983be54a3d25124cf43ef3ee21b5d6`, 31 commits ahead of
  `origin/main`
- Installed stack: Laravel 13.21.1, Filament 5.7.3, Livewire 4.3.3,
  Pest 4.7.5 and Tailwind CSS 4.3.3

Preflight found no active prompt under `prompts/pre-13-prompts/`, no
overlapping writer and no unexpected PHP, Blade, migration, test,
configuration, dependency or documentation drift.

This run implemented only the audited canonical Media task/query/context
slice. It did not start Mini-task 3, build a complete Care workspace, add fix,
recheck or result flows, or implement any Package 5 discovery, move, Trash,
restore or purge lifecycle. No migration, dependency, local-development
database/storage, production, branch/worktree, deployment or push action
occurred.

## Outcome

The native Filament Media Library now exposes five canonical task views:

1. **All Media** — the complete Curator inventory.
2. **In Use** — canonical attachment on any disk, settings path/reference-key
   identity on any disk, or legacy podcast/episode path on the public disk.
3. **No Known Direct Attachment** — absence of
   `media_attachments.media_id`; this can overlap In Use and never means
   unused or delete-safe.
4. **Needs Attention** — the exact union of the six existing diagnostic
   reasons, with an exact reason filter.
5. **Recent (30 days)** — `created_at` in the inclusive rolling previous
   30-day interval ending at one request-stable current time; future rows are
   excluded.

Only All Media and No Known Direct Attachment show numeric badges. Their two
aggregate queries are request-memoized and invalidate after successful
same-page deletion. In Use, Needs Attention and Recent deliberately have no
badge.

The existing MIME filter, new diagnostic-reason filter, search, deterministic
Added sort, page and task predicates compose. Default ordering is Added —
newest first with Media ID as the deterministic tie-breaker in the selected
direction.

## Exact diagnostic semantics

`MediaDiagnosticReason` is the finite vocabulary:

- `portable_identity`
- `storage_disk`
- `missing_file`
- `audience_denied`
- `unsanitized_svg`
- `metadata`

Needs Attention and each reason consume one exact request-local diagnostic
snapshot. The snapshot selects the complete attributes required by
`MediaInventoryDiagnostics` and walks the inventory lazily in 250-record
chunks. Empty or invalid filters fail safely: a blank reason leaves the query
unchanged without scanning storage, while an invalid nonblank reason or MIME
returns no rows.

Normal All Media, In Use, No Known Direct Attachment and Recent requests do
not trigger the whole-inventory diagnostic snapshot. They retain Mini-task
1's current-page reference priming and bounded display probes only.

## Safe List-to-Edit return context

Every authorized card/details URL carries one namespaced, versioned `from`
context with an exact allowlisted shape:

- version;
- task;
- MIME;
- diagnostic reason;
- search;
- sort;
- page;
- focused Media ID.

The Edit page accepts only that bounded array. It rejects extra keys, unknown
tasks/MIME/reasons/sorts, malformed pages, excessive/control/format/malformed
UTF-8 searches and forged focus. It never accepts a raw return URL, referrer
or browser-history instruction.

Back and Cancel reconstruct the same safe Media Library URL. The actual edit
record always replaces supplied focus, and the returning page anchors and
autofocuses that card. A malformed or missing context falls back to All Media,
page 1, default ordering and the actual record focus. Save remains the normal
Edit operation; its destination and persistence behavior were not changed.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| All Media task | Implemented / existing authority reused | Native tab leaves the complete inventory query unchanged. |
| In Use task | Implemented | Canonical attachments, settings identities and public legacy owner paths are combined without changing ownership. |
| No Known Direct Attachment task | Implemented | Uses only absence of canonical attachments and explicitly allows overlap with In Use. |
| Needs Attention task | Implemented | Exact six-reason request-local diagnostic union. |
| Exact reason filter | Implemented | Enum-backed reason composition; blank is no-op and invalid nonblank is fail-closed. |
| Recent definition | Implemented | Inclusive rolling previous 30 days by `created_at`, ending at one request-stable now; future excluded. |
| Two bounded badges only | Implemented | Only All and No Direct count; exactly two memoized aggregate reads and post-delete invalidation. |
| Task/filter/search/sort/page composition | Implemented | Native Filament state remains composable and URL-backed. |
| Added sort | Implemented | Newest-first default plus deterministic created-at/ID ordering in either direction. |
| Honest task descriptions and empty/reset states | Implemented | Bilingual descriptions explain scope and constrained empty results offer Reset view. |
| Versioned safe return context | Implemented | Exact allowlists, bounds and fallback; no raw URL/referrer/history behavior. |
| Same Back and Cancel destination | Implemented | Both rebuild one safe index URL from locked validated state. |
| Actual record focus | Implemented | Supplied focus cannot redirect attention to another record; browser proof covers anchor/autofocus restoration. |
| Hebrew RTL and English LTR | Implemented / regression-preserved | All new copy has HE/EN keys and browser coverage in both directions. |
| Mini-task 1 card hierarchy/actions | Already correct / regression-preserved | Existing image-first hierarchy and action organization remain. |
| All Media completeness | Already correct / regression-preserved | No row is hidden because it is broken, unbound, referenced or non-public. |
| Gallery mutation-free selection | Already correct / not touched | No picker or owner-selection write path changed. |
| Immediate Upload/URL/Storage permanence | Already correct / not touched | Acquisition boundaries and admitted-item persistence remain unchanged. |
| Complete Care/fix/recheck/result flow | Deferred / excluded | Mini-task 3 or a later separately approved task owns this. |
| Package 5 Files Discovery/lifecycle | Deferred / excluded | No discovery, move, Trash, restore, purge or lifecycle placeholder was added. |
| Migration, dependency, production or push | Not applicable / excluded | None occurred. |

## Package 1–4 authority preservation

- Every Curator row remains queryable through the existing inventory scope.
- `media_attachments.media_id` remains local owner authority.
- Curator `path` remains file-location authority.
- Existing Gallery selection remains mutation-free.
- Successful Upload, URL and Storage admission remains immediately permanent.
- Owner cancellation remains a no-op for existing selection and does not
  remove already admitted Media.
- Shared referenced bytes remain protected by the existing policies and
  `MediaFilesystemMutationCoordinator`.
- Preview/download delivery and fresh trusted-record resolution remain
  authoritative.
- Settings and legacy owner-path reads classify In Use only; they create no
  attachment or ownership state.
- No asset/binding, attachment, acquisition, filesystem, operation-journal,
  policy or persistence write path changed.

## Files changed

### Application and localization

- `app/Enums/MediaDiagnosticReason.php`
- `app/Enums/MediaLibraryTask.php`
- `app/Filament/Resources/Media/Pages/EditMedia.php`
- `app/Filament/Resources/Media/Pages/ListMedia.php`
- `app/Filament/Resources/Media/Tables/MediaTable.php`
- `app/Providers/AppServiceProvider.php`
- `app/Support/Media/MediaFilesystemMutationCoordinator.php`
- `app/Support/Media/MediaInventoryDiagnostics.php`
- `app/Support/Media/MediaLibraryContext.php`
- `app/Support/Media/MediaLibraryTaskQuery.php`
- `app/Support/Media/MediaReferenceFinder.php`
- `lang/en/admin.php`
- `lang/he/admin.php`

### Tests

- `tests/Browser/MediaResourceGalleryBrowserTest.php`
- `tests/Feature/AppOwnedMediaResourceTest.php`
- `tests/Feature/MediaInventoryPickerReplacementTest.php`
- `tests/Feature/MediaLibraryTaskContextTest.php`

### Documentation

- `docs/research/media-operations-ux3/03-mini-task-2-canonical-task-context-research.md`
- `docs/research/media-operations-ux3/04-mini-task-2-canonical-task-context-implementation-plan.md`
- `docs/phase-02/current-project-state.md`
- this handoff
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

No migration, manifest, lockfile, route, controller, model, policy, queue,
cache, settings schema, Blade or JavaScript file changed.

## Tests added or updated

- Exact task enum values, labels and descriptions in Hebrew and English.
- All Media completeness.
- In Use through attachments on any disk, settings path/reference key on any
  disk, and public-disk legacy podcast/episode paths.
- No Direct overlap with settings/legacy In Use plus explicit non-unused
  wording.
- Exact six-reason Needs Attention union and each reason subset.
- Unsafe SVG inclusion and empty/invalid reason behavior.
- Inclusive 30-day boundary, one request-stable current time and future-row
  exclusion.
- MIME/reason/task/search/sort composition and forged-state failure modes.
- Exactly two request-memoized badge queries, plus record/bulk delete refresh.
- Invalid task normalization both at mount and after a Livewire update.
- 1/10/25/251 In Use query/storage budget.
- 251-row lazy diagnostic snapshot query/probe budget and conservative raw-ID
  memory forecast.
- Normal-task page-bounded diagnostic display probes.
- Strict return-context shape, bounds, allowlists, invalid UTF-8 rejection and
  actual-record focus override.
- Identical safe Back/Cancel URLs, safe missing/malformed-origin fallback and
  unchanged Save behavior.
- Real browser tab/filter/sort/page-two/edit/return/focus flows in Hebrew and
  English, keyboard activation, 390-pixel layout and zero JavaScript errors.

## Installed-version research record

- Laravel Boost returned installed-version application information and
  Filament/Livewire Table, tabs, filters, actions, URL state, pagination and
  testing guidance.
- FilamentExamples was queried in multiple short first- and second-pass
  searches for tabs, badges, filters, reset/empty-state actions, card grids
  and edit return patterns. The configured server exposed search snippets
  only and no separate source/detail reader.
- Installed Filament 5.7.3 and Livewire 4.3.3 source confirmed native resource
  tab lifecycle, URL aliases, SelectFilter query hooks, deterministic Table
  sorting, safe Action URLs and Livewire closure injection.
- The repository Filament form UX, performance, Laravel Simplifier,
  Laravel/PHP, Livewire, Pest and Spatie standards were applied.
- PhpStorm MCP inspections were requested twice but no callable inspection
  tool was available in this task; independent source review, focused tests,
  FilaCheck and the complete gate provide the recorded verification instead.

## Commands and results

| Command / check | Result |
|---|---|
| Git root/status/HEAD/log/ahead and prompt check | PASS; clean `main` at `69aa0ce`, 31 ahead, no active prompt. |
| Mandatory lessons/state/ledger/latest handoffs and binding contract orientation | PASS; exact Mini-task 2 and Package 1–4 boundaries reconciled before edits. |
| Laravel Simplifier Stage 2 gate | PASS; parent option, current Audit ID and approved Mini-task 2 Option ID matched the operator approval. |
| Laravel Boost, FilamentExamples and installed-source research | PASS with evidence levels recorded above. |
| Initial task/context tests | Expected RED: 18 errors for the not-yet-created enums and services. |
| Foundation task/context implementation | GREEN after exact task, diagnostic and return-context services were introduced. |
| Recent timezone regression | Expected RED then GREEN after the assertion was aligned with normal Laravel UTC storage and application-time interpretation. |
| Strict HTTP context serialization regression | Expected RED then GREEN after nullable fields received a stable exact query representation. |
| Malformed UTF-8 and forged MIME review regressions | Expected RED then GREEN after strict encoding validation and server-side MIME allowlisting. |
| Expanded pre-review focused matrix | PASS: 70 tests / 634 assertions. |
| First expanded browser command inside macOS sandbox | Infrastructure FAIL: Chromium `MachPortRendezvousServer ... Permission denied`. |
| Identical expanded browser command outside sandbox | PASS: 4 tests / 142 assertions. |
| 251-row diagnostic snapshot budget | PASS: 2 inventory chunk reads plus 1 result query, 251 existence decisions and a conservative 5,271-byte raw-ID forecast. |
| 1/10/25/251 In Use scaling budget | PASS: 3 relevant database reads and 0 storage calls at every size. |
| Two badge counts and memoization | PASS: exactly 2 aggregate queries and no repeat queries in the request. |
| Normal All diagnostic display budget | PASS: 25 current-page probes; selecting Needs Attention completed 30 exact decisions while reusing the first 25. |
| First independent implementation review | Found malformed UTF-8, forged MIME, diagnostic-snapshot invalidation, blank-reason, late invalid-tab and same-request badge-refresh edges. |
| Snapshot/blank-reason review tests | Expected RED: 3 cases, 2 failures plus 1 storage-call expectation error / 6 assertions. |
| Late-tab/badge-refresh review tests | Expected RED: 2 failures / 6 assertions. |
| Review-correction focused rerun | PASS: 5 tests / 18 assertions. |
| Final focused feature matrix | PASS: 77 tests / 656 assertions. |
| Final browser rerun outside sandbox | PASS: 4 tests / 142 assertions. |
| Independent re-review | PASS; both reviewers confirmed all findings closed and reported no remaining Mini-task 2 issue. |
| Requirements/scope/authority sweep and `git diff --check` | PASS; no Mini-task 3, Package 5, migration, dependency, data, production or push drift. |
| First complete `php artisan test` outside sandbox | FAIL: 1,193 passed and 1 legacy-transition assertion failed / 15,227 assertions; the new settings payload memoization survived the first same-request settings rewrite and made the second cleanup see a stale source reference. |
| Isolated existing legacy-transition reproduction | Expected RED: 1 failure / 3 assertions with the same `cleanup_pending` result. |
| Bounded cache-invalidation correction | Added no authority: the existing coordinator settings-cache boundary now clears the new request payload cache and settings-derived primes. |
| Isolated and complete legacy-transition reruns | PASS: 1 test / 9 assertions, then 26 tests / 118 assertions. |
| Tooling deviation | A documentation search accidentally interpolated and launched a duplicate full suite. A read-only process check identified its exact shell/artisan/Pest/Playwright PIDs; only those four duplicate processes were stopped before any further test run. |
| First ordered `vendor/bin/pint --test` | FAIL: one `array_indentation` issue in `MediaLibraryTaskContextTest`. |
| Targeted `vendor/bin/pint tests/Feature/MediaLibraryTaskContextTest.php` | PASS; changed only the reported mechanical formatting. |
| `vendor/bin/pint --test` | PASS in the corrected ordered gate and required final post-result repeat. |
| `vendor/bin/filacheck` | PASS with 0 issues in the corrected ordered gate and required final post-result repeat. |
| `npm run build` | PASS in the corrected ordered gate and required final post-result repeat. |
| Full `php artisan test` last | PASS in the corrected ordered gate and required final post-result repeat: 1,194 tests / 15,233 assertions. |

Feature and browser tests used isolated databases, fake storage and
`Http::preventStrayRequests()` where HTTP behavior was in scope. No test used
the local development database, live HTTP, live mail or production state.

## Performance and security boundaries

- Normal page size remains 25.
- All and No Direct badges issue exactly two request-memoized aggregate
  queries; no other task/reason has a badge.
- In Use remains bounded at three relevant database reads as inventory size
  grows and performs no storage probes.
- Normal task views do not trigger the exact whole-inventory diagnostic
  snapshot.
- Needs Attention/reason selection performs one lazy, request-local exact
  snapshot rather than storing new health state.
- The 251-row snapshot has a conservative 5,271-byte raw-ID forecast.
- Current-page reference priming and display diagnostic probes remain
  page-bounded.
- The request-local settings payload cache and any settings-derived primed
  projections are invalidated when the existing transition coordinator
  rewrites settings.
- Context accepts no arbitrary URL, route, host, referrer, script or history
  instruction.
- Context fields use finite allowlists and conservative page/search bounds.
- Invalid nonblank MIME/reason/task state cannot expose an unintended row set.
- Per-action policies and the mutation coordinator remain authoritative.
- No raw file URL, unsafe path, operation error, secret or hidden lifecycle
  authority is exposed.

## Assumptions

- Existing Media `created_at` is the binding Added timestamp for Recent and
  the default sort; no new timestamp or index was authorized.
- Settings path/reference-key values are usage evidence, not attachment or
  ownership writes.
- Public-disk legacy `cover_path`/`image_path` matches are usage evidence only
  because those compatibility paths are authoritative only on that disk.
- The existing Edit Media page remains the bounded details destination. It is
  not represented as the complete later Care workspace.
- A safe fallback may not restore a missing origin record to the same page if
  it no longer belongs there; it still focuses the actual record identity in
  the canonical All Media context when present.

## Local Front Check Report

1. Open Admin > Media in Hebrew and expect five task tabs: All Media, In Use,
   No Known Direct Attachment, Needs Attention and Recent (30 days).
2. Expect numeric badges only on All Media and No Known Direct Attachment.
3. Open In Use and expect directly attached Media, settings-referenced Media
   and public legacy podcast/episode images; do not expect the UI to create or
   change an attachment.
4. Open No Known Direct Attachment and expect Media without canonical
   attachments, including a settings/legacy-used row where applicable; do not
   interpret the view as unused or safe to delete.
5. Open Needs Attention and select each reason; expect only rows with that
   exact visible diagnostic.
6. Open Recent and expect only Media added during the inclusive rolling
   previous 30 days, with future-dated rows excluded.
7. Combine a task, MIME, reason and search; change Added ordering and page;
   expect every visible row to satisfy the combined state.
8. Clear a constrained result and expect the task-aware empty state plus Reset
   view; activate Reset and expect canonical All Media defaults.
9. From page 2 with active task/filter/search/sort state, open a Media card and
   expect the normal Edit Media page.
10. Activate Back to Media Library and expect the exact safe task, filters,
    search, sort and page to return, with the edited card anchored and focused.
11. Repeat through Cancel and expect the same return destination; save an edit
    and expect normal Edit persistence/navigation behavior.
12. Open Edit Media directly without a valid origin and expect the safe All
    Media/page-1 fallback rather than a raw or external redirect.
13. Delete one permitted unreferenced Media row and then selected permitted
    rows; expect All/No Direct badges to refresh in the same interaction.
14. Repeat the complete flow in English and expect LTR layout with the same
    semantics.
15. Resize to 390 by 844, use Tab/Enter on task and card controls, and expect
    no horizontal overflow, lost focus or JavaScript error.

## Deferred and excluded

- Mini-task 3 and a complete Care workspace.
- Typed direct/effective/inherited/settings usage presentation beyond the
  audited task predicates.
- Reason-specific fix actions, blockers, recheck, structured results and
  retry/next-issue flow.
- Intake, batch, provider and recovery workspace redesign.
- Package 5 Files Discovery, move, lifecycle, Trash, restore and purge.
- Schema, migration, index, settings, cache, queue, dependency or provider
  changes.
- Local-development or production data/storage actions.
- Deployment and push.

## Commit hash

`ce97b3d9350db966073b1fc224046d4a25cbfa68`
