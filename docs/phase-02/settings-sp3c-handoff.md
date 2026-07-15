# Settings SP3C Handoff

> **Historical shipped-state notice — 2026-07-16:** This remains authoritative
> for SP3C behavior and measurements. Its settings-backed Template architecture
> is now migration-source and rollback evidence. ARCH1 versioned Resources must
> be accepted before SP3D; see
> `docs/research/settings-performance/07-sp3d-pre-research.md`.

Date: 2026-07-15

## Scope

Executed only `prompts/pre-13-prompts/settings-sp3c-codex-prompt.md`, prompt
version v3 (2026-07-15). Kickoff corrections were `none`.
`docs/research/settings-performance/06-sp3c-prompt-review.md` was historical
audit evidence only; v3 governed every decision. No Composer/npm change,
migration, production action, persistent cache, database/advisory/cache lock,
`lockForUpdate()`, queue change, push, or simultaneous-request test was made.

## Commit hash

`4cb70f2 feat: add template library and one-template editor`

## Requirement classification

- **Implemented — Job 0:** added the durable imperative commit-message rule and
  allowed prefixes to `AGENTS.md` without a hook; added only the genuinely
  missing UI-HF1 logo/favicon and UI-HF2 collapsible-navigation ledger rows.
- **Implemented — research/canary:** wrote the research note and self-contained
  provisional plan before application code; ran the exact green baseline;
  built a separate deepest SP3C fixture; exercised the complete nested Builder
  interaction matrix; selected Filament Builder previews; measured a
  production-shaped seven-column/action custom-data library; repeated stable
  samples; and froze maxima plus 20% before final production adoption.
- **Implemented — library/references:** replaced the temporary writable
  whole-list page with an unpaginated read-only Filament custom-data table;
  projects configured, virtual-default, configured-default-override, and safe
  diagnostic rows from one snapshot; exposes no raw `parts` in rows/actions;
  preserves stored order; implements label/identity search and default-override
  filtering inside `records()`; and scans all HomepageSection rows in one
  projected, ordered query with settings paths, family fallback,
  implicit/default use, ambiguous-key blockers, invisible rows, and
  display-safe labels.
- **Implemented — routes/editor:** retained the existing library slug and added
  hidden create/edit Pages at the exact v3 slugs. Route family/key constraints,
  repeated mount/action validation, missing/corrupt/duplicate 404 behavior,
  invalid-UTF route normalization, deterministic blank/clone/override draft
  construction, locked source transport, one-template state, Builder previews,
  local dirty-navigation alerts, SPA-aware redirects, and canonical post-save
  URLs are covered.
- **Implemented — focused writer:** one injected writer owns create, edit,
  allowed rename, clone-save, override, and guarded delete. Each mutation uses
  one fresh settings refresh/snapshot, exact-once target/source lookup,
  canonical one-way fingerprints, current authorization/capability, fresh
  reference/default/collision checks, candidate-only validation, strict sibling
  and foreign-root preservation, one `fill()`, one `save()`, and the existing
  hooks/profiler/notification/backup/cache lifecycle.
- **Implemented — authorization/state:** protected data is recursively detected.
  A non-capable actor receives a generic shell with `parts` absent from HTML,
  summaries, action state, and serialized draft state; a permitted shell edit
  restores current protected parts server-side. Protected additions, clone, and
  delete are refused; role or multi→single capability loss sanitizes future
  responses; original identity remains authoritative through allowed rename.
- **Implemented — measurement/profiler:** library/editor local measurement uses
  the unchanged SP3A runtime fixture and locked fixture/profile/mode state;
  every mutation is refused before dehydration. Profiler subject scopes restore
  in `finally`, nest safely, preserve even a subjectless timer's start context,
  reach the synchronous SettingsSaved listener, and do not leak.
- **Implemented — tests/regressions:** added canary and production SP3C suites;
  retargeted SP3B, Roles/Gates, Card Template Builder, Icon Registry, and admin
  navigation tests; preserved the ownership registry, lifecycle SHA, SP3A
  fixture, import/restore/normalize/import-lock/backup behavior, public card
  rendering, and no-stray-request/mail-fake boundaries.
- **Already existed and preserved:** `PublicContentSettings.card_templates`
  ownership, validator/registry definitions, family-level lifecycle locks,
  SettingsSaved listeners, backup deduplication, public renderers, Admin UX
  transcription-mode authority, SP3A middleware/fixture/SHA, and the library's
  existing navigation position.
- **Deferred by v3:** template-level reorder; template/part lifecycle locks;
  repoint-on-rename; new cache layers; browser recovery after a full remount;
  and every future SP3D/budget or main-queue feature.
- **Not applicable:** Composer/npm changes, database schema changes, raw iframe
  or network work, production diagnostics, local development-database probes,
  literal settings-table JSON/payload-byte preservation, and an active database
  transaction/atomic concurrency claim.
- **Blocked only as manual evidence:** an authenticated browser DOM/listener/
  heap/TTFB and real Back-warning sample was not collected because the known
  local in-app browser runtime is unavailable. The operator steps remain
  pending; no sample was fabricated, and this does not replace the green
  deterministic component stop/go evidence.

## Frozen canary verdict and budgets

The isolated preview candidate passed. Against the same deepest fixture, every
candidate state reduced rendered wrappers and controls by more than 93%, above
the required 70%. Three final samples were identical. Filament may still build
Block schemas in PHP; the accepted claim is rendered DOM/control reduction,
not complete backend schema deferral.

| Surface | Elements | Wrappers | Controls | IDs | `wire:model` | HTML bytes | State bytes | Frozen +20% elements / HTML / state |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Full Builder control | 8,756 | 396 | 146 | 146 | 146 | 3,690,183 | 18,118 | control only |
| Preview unselected | 3,510 | 8 | 2 | 2 | 2 | 1,257,416 | 18,118 | 4,212 / 1,508,900 / 21,742 |
| One selected top part | 3,617 | 16 | 6 | 6 | 6 | 1,301,734 | 18,118 | 4,341 / 1,562,081 / 21,742 |
| Selected group + one child | 3,724 | 24 | 10 | 10 | 10 | 1,347,225 | 18,118 | 4,469 / 1,616,670 / 21,742 |
| Production-shaped library, 30 rows | 1,051 | 1 | 1 | 1 | 1 | 479,927 | 11,644 | 1,262 / 575,913 / 13,973 |

The canary library issued zero database/settings/reference/lifecycle work at 10
and 100 rows. Production separately proves one settings read, one projected
reference query, and zero duplicate lifecycle loads on the measured library
request. The reference scan remained one query at 9 and 49 rows; the recorded
test samples were 1.177 ms and 1.607 ms respectively. Those times are evidence,
not universal performance promises.

## Evidence table 1 — independent initial GETs

These are server-response measurements from the unchanged SP3A runtime fixture.
The reference-query column is classified by the dedicated query listener; the
five middleware headers describe only the initial GET.

| Surface | Uncompressed bytes | Total queries | Settings reads | Reference queries | Lifecycle derivations | Duplicate loads | Elements | Wrappers | Controls |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Library | 302,924 | 4 | 1 | 1 | 0 | 0 | 705 | 1 | 1 |
| Editor, unselected | 447,536 | 3 | 1 | 0 | 0 | 0 | 1,174 | 8 | 2 |

## Evidence table 2 — Livewire/component states

`[data-field-wrapper]` is the installed wrapper selector. Controls are native
inputs/selects/textareas, contenteditable/custom controls, IDs, and
`wire:model` only inside those wrappers. Summary/edit overlay chrome is counted
separately. Serialized state uses UTF-8 JSON with unescaped Unicode/slashes.

| Surface | Elements | Wrappers | Controls | IDs | `wire:model` | Summary / edit chrome | HTML bytes | State bytes |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Production library, SP3A fixture | 426 | 1 | 1 | 1 | 1 | 0 / 0 | 189,835 | 6,964 |
| Production editor, unselected SP3A fixture | 897 | 8 | 2 | 2 | 2 | 0 / 6 | 334,035 | 4,466 |
| Isolated preview, unselected deepest fixture | 3,510 | 8 | 2 | 2 | 2 | 29 / 29 | 1,257,416 | 18,118 |
| Isolated preview, one top selected | 3,617 | 16 | 6 | 6 | 6 | 29 / 29 | 1,301,734 | 18,118 |
| Isolated preview, group + nested selected | 3,724 | 24 | 10 | 10 | 10 | 29 / 29 | 1,347,225 | 18,118 |

Livewire's test transport does not merge teleported modal DOM into
`Testable::html()`. Native modal action state/mutations are tested directly,
while selected counts render the exact selected schema against the same parent
draft path. No unstable Livewire delta-byte claim is made.

## Evidence table 3 — browser/operator evidence

| Evidence | Status | Honest boundary |
|---|---|---|
| Authenticated fixed-viewport TTFB | Pending operator run | No browser sample recorded. |
| Real browser DOM and listener count | Pending operator run | Server/Livewire counts above are not relabeled as browser DOM. |
| Heap before/after selected editing | Pending operator run | No heap percentage fabricated. |
| Library→editor→save/clone navigation | Pending operator run | Route/action/redirect behavior is automated. |
| Dirty Back warning | Pending operator run | Filament unsaved-data hash behavior is automated; real dialog remains manual. |
| Top-level and nested modal editing | Pending operator run | Native action state and selected wrapper/control counts are automated. |

The historical Advanced-page 29,404 DOM value remains contrast only and is not
used to compute a cross-surface percentage.

## Files changed

- Repository/docs: `AGENTS.md`; SP3C research/plan; current state; step ledger;
  this handoff.
- Pages/views: read-only `CardTemplateSettings`; shared
  `CardTemplateEditorPage`; hidden `CreateCardTemplate` and `EditCardTemplate`;
  library/editor/escaped summary Blade views; shared schema preview exposure.
- Focused support: identity/canonical fingerprint, access policy, draft factory,
  projection/projector, references/scanner, focused writer, result/exception,
  and part-summary formatter under
  `app/Support/Settings/CardTemplates/`.
- Cross-cutting: profiler subject support and the invalid-UTF card-template edit
  route's scoped 404 response normalization.
- Localization: bilingual SP3C action, editor, diagnostic, lock, error,
  notification, and canary strings.
- Tests/fixtures: new `SettingsSp3cTest`, `SettingsSp3cCanaryTest`, test-only
  deepest fixture/canary pages/measurement helper/views; profiler test; and
  deliberate old whole-list page retargets in SP3B, Roles/Gates, Card Template
  Builder, Icon Registry, and admin navigation coverage.

## Tests added or updated

- Canary: all 14 registered part types twice, top/nested protected sentinels,
  hostile summaries, native edit/confirm/cancel/validation/clone/delete/reorder/
  reopen, repeated-type isolation, parent-draft failure retention, non-capable
  absence, three deterministic samples, production-shaped library, and 10/100
  zero-query scale.
- Production: library/default/diagnostic/action state; complete reference paths,
  family derivation, ambiguity, implicit use, query scale; route/role/malformed/
  missing/corrupt/duplicate behavior; blank/clone/override/collision/source
  lifecycle; edit/rename/delete preservation; sequential conflicts; lifecycle
  counts; import-lock informational behavior; protected state and capability
  loss; measurement/profiler/ownership regressions.
- Existing behavior: SP3A/SP3B, backups/snapshots/import/export/normalize,
  default images/taxonomy/Curator, admin navigation, roles/single lens, public
  Card Template Builder, and icon registry were included in the settled
  affected suite.

## Command and test record

- Preflight: `git status --short --branch`, recent `git log`, and ancestor checks
  passed on a completely clean `main` tree, one commit ahead of `origin/main`.
  The v3 prompt version matched. Mandatory instructions, the full lessons file,
  state, ledger head, two newest handoffs, audit, specs/guidelines, installed
  source, nearby code, and affected tests were read before changes.
- Research tools: Laravel Boost returned installed-version guidance;
  FilamentExamples was queried in decomposed/refined batches and exposed search
  snippets only, so no deep-source claim is made. The required read-only Laravel
  review subagent reported no code changes.
- Exact v3 baseline: the first sandbox run could not bind Pest's local socket;
  the identical approved runner retry passed **191 tests / 2,289 assertions /
  105.255 s**. This was infrastructure-only and application-green.
- Canary iterations: early isolated red runs exposed and corrected test-view
  registration, Builder action-path/state-shape, cancel/validation locality,
  protected-state, and honest measurement-helper assumptions. No production
  code was adopted while those assertions were red. The selected preview suite
  then passed **9 tests / 79 assertions**. A later review correctly rejected a
  plain-Blade/three-column library measurement as insufficiently representative;
  the canary was upgraded to the seven-column boolean/action Filament table
  shape and again passed **9 / 79**. Its report-only run passed **1 / 21** with
  three identical samples and the final budgets above.
- Production iterations: the first old focused regression was **18/21** because
  three tests still addressed the removed whole-list `data` form; they were
  deliberately retargeted. Initial new SP3C runs progressed from **4 pass, 5
  fail, 1 error**, to **9/10**, to **18/19**, exposing Builder dehydration,
  protected canonical comparison, role, lock, collision, measurement, and
  query-listener setup issues. The retargeted affected subset then passed **44
  tests / 475 assertions**. Expanded production coverage progressed through
  **15/18** and **17/18** before the fixed query-listener boundary passed.
- Focused settled runs: production+profiler passed **22 / 164**; the expanded
  suite intentionally went red at **30/32** for invalid-UTF status and direct
  Livewire 403 semantics, then **31/32** until the scoped raw-request-URI 404
  normalization was applied. Single-path reruns verified the correction. The
  pre-gate consolidated SP3C canary+production+profiler run passed **43 tests /
  382 assertions / 10.453 s**. A library URL-action redirect assertion was
  corrected to the real URL-action contract after one **42/43** run; no
  persistence behavior changed. After the FilaCheck-required custom-data
  search/filter addition and remeasurement, the final focused run passed **43
  tests / 391 assertions / 10.617 s**.
- Measurement reports: the final canary report passed **1 / 21**; production
  initial/library/editor report passed **1 / 29**; reference scan report passed
  **1 / 18**. Values are recorded in the evidence tables.
- Pre-final affected regression (the exact 16 v3 baseline files plus SP3C
  canary and production): passed **231 tests / 2,649 assertions / 111.634 s**;
  the later search/filter change is covered by the final focused and full-suite
  runs.
- Formatting during iteration: repeated `vendor/bin/pint --dirty` runs passed
  after applying mechanical formatting. No `filacheck --fix` was run.
- Documentation checks: `git diff --check` passed at inspected checkpoints.
- Final gate first entry: `vendor/bin/pint --test` passed, then
  `vendor/bin/filacheck` correctly stopped on missing custom-data search/filter
  support. Per v3, that quality-gate evidence justified enabling the behavior;
  search/filter were implemented inside `records()`, bilingual copy/tests were
  added, and the production-shaped canary/budgets were rerun. A focused
  `vendor/bin/filacheck` rerun then passed with 0 issues.
- Final ordered gate after that file change: `vendor/bin/pint --test` passed;
  `vendor/bin/filacheck` passed with 0 issues; `npm run build` passed; full
  `php artisan test` passed last. The final suite was not parallelized or
  interrupted.

## Tooling deviations

- Pest required the already-approved unsandboxed runner because its PAO socket
  cannot bind inside the sandbox. The test command itself was unchanged.
- FilamentExamples provided search/snippet access only. Installed package source
  and official installed-version docs governed exact APIs.
- The initial final-gate FilaCheck result was treated as the v3 measurement of
  need for read-only library search/filter. No column-only SQL behavior was
  assumed: both behaviors operate in the custom `records()` callback, and the
  changed table was remeasured before the gate restarted.
- No usable authenticated in-app browser runtime was available, so browser
  sampling remains explicitly pending rather than replaced with Playwright or
  fabricated numbers.

## Preservation and claim boundaries

- Untouched sibling rows preserve strict decoded PHP-array equality and
  deterministic canonical per-row JSON at the same indices for edit/rename;
  delete compacts list keys while preserving surviving relative order; create
  appends. Foreign roots come from the same fresh snapshot and survive.
- The writer uses optimistic sequential fingerprints. It does not serialize
  simultaneous requests and does not close the scan-to-save TOCTOU window.
- No literal database-payload byte, whitespace, JSON key-order, or timestamp
  preservation is claimed. Existing backup hash deduplication remains valid.
- Existing transaction hooks/configuration are retained. No active DB
  transaction or atomic concurrent serialization is claimed.

## Local Front Check Report

1. Open Admin → Settings → Card Templates; expect an unpaginated read-only
   library with label/identity search and a default-override filter, no inline/
   bulk/reorder controls, and each registry default identity exactly once as
   either a virtual row or configured override.
2. Open one configured template; expect only that template draft in editor
   state and no sibling templates or foreign settings roots.
3. Select one top-level part, then a group and one nested child; expect controls
   only for the selected top part or selected group+child while other parts stay
   escaped summaries.
4. Edit and save one template; expect the public card to change and every
   edit-time sibling to remain strict/canonical equal at the same index.
5. Rename a template referenced by settings or a Homepage Section; expect the
   save to be blocked with the deterministic settings/section reference list.
6. Choose Clone on a configured template; expect an unsaved draft with a
   deterministic `_copy`, `_copy_2`, or later collision-free key and no backup,
   cache invalidation, or settings save before confirmation.
7. Open the same template in two tabs, save the first, then save the second;
   expect the second save to report a stale conflict and retain its draft.
8. As an administrator without current protected capability, open a protected
   template; expect only generic restricted copy, no protected token in page
   source/state, and hidden plus hard-refused clone/delete actions.
9. Change a draft and use browser Back; expect a warning before abandoning the
   dirty draft. Do not expect recovery after accepting the warning and causing
   a full remount.
10. Record fixed-viewport authenticated TTFB, DOM, listener, heap, navigation,
    Back-warning, and nested-edit samples; if the browser runtime remains
    unavailable, record them as pending and do not substitute server/Livewire
    counts.

## Assumptions, deferrals, and working-tree status

- Hebrew is primary; English translations are present. Date presentation was
  not changed by this settings-only step.
- Family import locks are informational for ordinary editor saves and remain
  authoritative only for import/restore paths.
- Browser acceptance is pending as recorded; all deterministic stop/go and
  security boundaries are automated and green.
- Before the implementation commit, `git status --short` is expected to show
  only the SP3C implementation, tests, and documentation. The implementation
  commit is followed immediately by the docs-only hash backfill; no push is
  performed.
