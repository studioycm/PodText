# Defect cause-pattern ledger

Started 2026-08-03 by the dashboard route-plan orchestrator session. Every
defect found in the Prompt 13 round traces to a repeatable cause-pattern; each
entry here turns one finding into a search prompt for similar cases elsewhere.

**Protocol.** This file has **one owner** — the orchestrator session curates
it (operator-confirmed 2026-08-03; two writers on one ledger would be P1
itself). Side-sessions contribute candidate entries and sightings via the
"open flags + pattern evidence" section of their final reports — with
evidence: file:line or commit, one-line mechanism, ACTUAL vs POTENTIAL — and
the orchestrator merges, dedupes, and curates. (Exception noted: the F-block
delegate was instructed pre-rule that it may append directly; its appends get
curated on merge at verification.) A dedicated read-only bad-practices
research task (chip `task_8d7e8616`) crunches the subject systematically via
the Filament/Livewire audit skills, the Boost/CLAUDE.md guidelines, and
FilaCheck's rule catalogue; its findings are held against the route
delegates' evidence sections so duplicates converge. When a pattern
accumulates **2+ sightings beyond its founding evidence**, it gets a targeted
sweep task of its own.

Entry format: **name** · one-line cause · evidence (where it already bit, with
commits) · where else to look (concrete greps/paths) · status.

---

## P1 · Value duplicated instead of routed through one home

- **Cause:** a constant (colour, timezone, format string, label) is retyped at
  each use site instead of read from one owner, so sites drift independently.
- **Evidence:** dashboard colour drifts fixed by `FunnelStage`/`DashboardReason`
  (`dc0cd7a`), three more by `DashboardTier` (`4bd4030`); `Asia/Jerusalem` in
  ~50 files consolidated (`44219ad`); enum labels hand-written in two places
  (E4, `96af988`); date/number formats still scattered (F1/F2 open).
- **Where else:** `grep -rn "d/m/Y" app resources` (52 app-wide, 10 dashboard);
  `grep -rn "number_format(" app resources` (5 dashboard); raw Tailwind palette
  classes outside enum homes (218 app-wide — parked, separate budget);
  duplicated literals across `lang/en` + `lang/he`.
- **Status:** dashboard-scope formats closed (`b24490a`, `UiFormats` +
  guard); app-wide sweep (63 `d/m/Y` + 7 `number_format(` grep lines, plus
  57 `H:i` lines that overlap heavily) stays parked deliberate work.
  Orchestrator review note: the guard scans the pinned atoms only, so it
  prevents drift-back of the consolidated formats but would not catch a
  *novel* format literal (e.g. `j.n.Y`) — acceptable for a drift guard;
  worth revisiting if the app-wide sweep ever lands a broader pattern set.

## P2 · An enum beside hand-written values protects nothing

- **Cause:** creating the enum is half the work; a value is only safe from
  drift once **every call site** reads it from the enum. Unrouted sites keep
  drifting next to a correct-looking type.
- **Evidence:** proven three separate times in phase 2R (handoff contract
  line); colour drifts persisted after the enums existed until call sites were
  routed (`4bd4030`).
- **Where else:** for each enum in `app/Enums`, grep its literal backing values
  outside the enum file; `ActivityStreamWidget` hand-writes event-type colours
  (known open → `StreamEventType`, phase 3).
- **Sighting (2026-08-03, V1):** the generalized form — a *contract* enforced
  only on hand-listed members protects nothing outside the list. The stock/flow
  tag rule was asserted per named widget, so `PublicFormTargetWarningsWidget`
  shipped untagged and `BlockersQueueWidget` had never been tagged. Fixed with
  a structural loop over every lens registration in
  `DashboardOverviewLensTest`.
- **Sighting (2026-08-03, phase-3 session, ACTUAL):**
  `app/Livewire/Admin/MediaPickerPanel.php:1305` hand-writes the disposition
  ternary `'reused' : 'created'` beside `MediaAcquisitionDisposition` (line
  674 already uses the enum instance) — an unrouted call site of an existing
  enum. The phase-3 plan's Task 8 (E4 pair) routes it through the enum.
- **Status:** open as a rule; `StreamEventType` lands in phase 3. Two
  sightings now on the ledger — per protocol this pattern is **sweep-eligible**;
  the sweep is deferred until the bad-practices research report lands so one
  sweep covers all its instances.

## P3 · Live unvalidated state read raw

- **Cause:** state persisting across requests (`#[Url]`, session-persisted
  filters, Filament `pageFilters`) is consumed without narrowing, so a stale or
  hostile value reaches queries/rendering.
- **Evidence:** `$this->pageFilters` consumed unvalidated (Filament docs warn
  it is user input); fixed by narrowing in `ReadsDashboardFilters` (phase 2R).
- **Where else:** `grep -rn "#\[Url" app`; `grep -rn "pageFilters" app`;
  Livewire public properties fed into queries without validation;
  session-persisted table filter state.
- **Status:** dashboard fixed; app-wide sweep candidate (needs a second
  sighting).

## P4 · Shared implicit keys

- **Cause:** components rely on a key (query-string, pagination, `wire:key`,
  schema component key) that is implicitly derived, so it collides between
  siblings or shifts when a list changes.
- **Evidence:** blockers queue table shared the page's query-string keys
  (fixed, `queryStringIdentifier`); pagination keys namespaced per component
  (`2e786ed`); M2 stale-memo clone graft fixed by per-mount key nonce
  (`0b99bf8`); `->key()` on a schema Section re-keys its **children** (`HasKey`
  inheritance, E5 session finding).
- **Where else:** admin tables lacking `queryStringIdentifier`; index-derived
  Livewire widget keys that shift when a lens's widget list changes (V3 checks
  this round); `grep -rn '\->key(' app/Filament`.
- **Status:** multiple fixes landed; V3 verifies the residual this round.

## P5 · Proxy metrics teach tests wrong expectations

- **Cause:** a metric computed from a proxy column gets encoded into a test, so
  the test asserts the bug and defends it against the fix.
- **Evidence:** "became visible" bucketed by `published_at`; the test encoded
  the proxy; fixed with `becameVisibleAt()` (`ce15d96`).
- **Where else:** any day/count derived from a column that is not the event
  itself; the 12 deliberately-hardcoded-timezone test files are a **partial**
  oracle until F2 adds a near-midnight fixture (Jerusalem vs UTC only diverge
  within an hour of midnight).
- **Status:** fixed instance; F2's near-midnight fixture landed (`b24490a`)
  and was proven discriminating — the UTC-day expectation fails against it.

## P6 · Silent caps

- **Cause:** `take($limit)` / traversal caps drop the tail with no signal —
  the display reads as "everything" when it is not (no-silent-caps rule).
- **Evidence:** breakdown `take($limit)` drops podcasts beyond six (F3 open);
  Storage panel candidate list caps at 50 breadth-first over the whole public
  disk (M1, decided: real pagination).
- **Where else:** `grep -rn "take(" app/Support/Dashboard app/Filament/Widgets`;
  `grep -rn "limit(" app/Support/Dashboard`; `storage_candidate_limit` users.
- **Sighting (2026-08-03, F3):** assessed and cleared —
  `EditorialMetrics::activityStream(limit: 12)` and its per-type
  `limit($limit)` queries do truncate, but the stream presents as a latest-N
  feed rather than a totality, so a roll-up row would be meaningless there.
  Bounded-by-design, not a violation.
- **Status:** breakdown caps closed (`b3d6de4`, `rollUpTail` + a reconciling
  "Other" row on `BreakdownRow::meta`); M1 queued separately.

## P7 · "Flake" labels hiding real defects

- **Cause:** an intermittent failure is the click/timing distribution of a
  deterministic defect; the flake label stops the investigation.
- **Evidence:** M2 at ~13% was called a flake twice before the deterministic
  race repro (closed, `0b99bf8`); the `return_guard_released` "flake"
  was a real test defect (`3cc4906`); `OwnerImageWorkspaceBrowserTest` failed
  ~1-in-4 standalone — **resolved 2026-08-03: 10/10 clean post-M2**, same
  per-mount-key mechanism; a 1/30 Storage-listing timeout remains
  unclassified.
- **Where else:** any browser test with a re-run habit; the parallel-worker
  port/storage collision hypothesis (`local_51579218`) for the residual
  intermittents (now only the 1/30 Storage-listing timeout).
- **Status:** V2 closed the owner-image sighting; only the unclassified 1/30
  timeout remains under watch.

## P8 · Line-based guards miss multi-line call sites

- **Cause:** an anti-drift guard that greps single lines misses the same call
  formatted across lines; guards must scan statements, not lines.
- **Evidence:** phase 2R anti-drift trap notes (handoff); the timezone guard's
  shape cannot be copied naively.
- **Where else:** the F1 formats/colour guard (binding requirement); audit any
  existing source-scanning tests for line-anchored regexes.
- **Status:** met — F1's guard whitespace-collapses file contents before
  matching (`b24490a`). The timezone guard needs no retrofit: its literal is
  a single string that cannot split across lines.

## P9 · `->options(Enum::class)` changes state type, not just labels

- **Cause:** passing an enum class to `->options()` installs an
  `EnumStateCast`, so field state becomes an enum instance; a string-typed
  backing property then mismatches (or the inverse). The field's state type and
  the property's type must agree.
- **Evidence:** E4 hit on `AdminUxSettings::$media_naming_strategy`; E5 typed
  the four properties (`9859145`) with a repair migration and the settings-row
  invariant test.
- **Where else:** `grep -rn "options(.*::class" app/Filament` cross-checked
  against backing property types; `PublicContentSettings`'s ten string
  properties (deliberately untyped — six enums must exist first); dynamic
  settings writers (`SettingsBackupManager::applyPayload()`,
  `NormalizePublicContentSettings`) — V4 decided: **guarded**.
- **Status:** E5 closed; V4 (2026-08-03) added the raw-writer invariant to
  `SettingsRowInvariantTest` — CI fails if `PublicContentSettings` ever gains
  an enum-typed property while the raw writers exist, with the detector
  sanity-checked against `AdminUxSettings`. Both writer sites carry the
  constraint note.

## P10 · Concept without a type home

- **Cause:** a domain concept exists only as query shapes or array literals;
  nothing owns its invariants until a type does, so every use site re-derives
  them slightly differently.
- **Evidence:** blocker tiers existed only as query shapes until
  `DashboardTier` (`4bd4030`); reasons until `DashboardReason` (`dc0cd7a`).
- **Where else:** stream event types (phase 3, `StreamEventType`); the
  "format" concept until F1's localization home; provider/source strings on
  intake paths (Board 3).
- **Status:** recurring; formats instance closed (`b24490a`, `UiFormats`).
  `StreamEventType` (phase 3) and intake provider strings remain.

## P11 · Docblock promises behavior no test pins

*(Promoted 2026-08-03 from a phase-3-session candidate, `7ffcaa5`.)*

- **Cause:** a docblock/comment states a behavioral invariant that no test
  asserts, so the code drifts from the promise and every reader inherits a
  false belief.
- **Evidence (ACTUAL):** `EditorialMetrics::reasonBreakdown()` promises rows
  carry "the queue doorway filtered to that reason", but the built URL is
  `ContentItemResource::getUrl('index', ['filters' => ['content_group_id' => …]])`
  — no reason key, and `ContentItemsTable` has no reason filter to receive
  one, so all four Board-2 reason bars open the same unfiltered list.
  User-visible on Board 2.
- **Where else:** dashboard docblocks claiming doorway/filter behavior
  (`grep -rn "filtered to\|doorway" app/Support/Dashboard app/Filament/Widgets`)
  cross-checked against URL-shape assertions in tests; any comment of the
  form "so that X happens" with no test named for X.
- **Status:** open — the fix is routed into this route's **A block as A4**
  (make the promise true, preferring real reason filtering; else correct the
  docblock and the UX). The phase-3 plan already asserts its own doorway URL
  shapes because of this sighting.

## P12 · Binding-by-reference to an unarchived source

*(Promoted 2026-08-03 from a phase-3-session candidate, `7ffcaa5`.)*

- **Cause:** a doc declares an external artifact's content binding without
  restating it, so the "contract" has no readable text to enforce — kin of
  P2's generalized form: a contract enforced only where its text happens to
  exist protects nothing.
- **Evidence (ACTUAL):** `dashboard-metrics-combined-ux-plan.md`'s
  honesty-audit row declares "Empty-state designs and principles P1–P7 …
  binding for every widget's build spec", but the P1–P7 text exists in no
  repo file, and neither linked artifact contains it (both fetched and
  searched 2026-08-03 by the phase-3 session; the principles were authored
  in an earlier, unlinked design pass). The phase-3 plan restates concrete
  per-widget empty states instead of citing P1–P7.
- **Where else:** `grep -rn "claude.ai/code/artifact" docs/phase-02`
  cross-checked against "binding"/"principles"/"declared" claims near the
  links; any spec sentence of the form "as designed in the mockup" with no
  repo copy.
- **Status:** open — operator decision requested (phase-3 plan open question
  5): supply the original P1–P7 text into the combined plan, or accept the
  plan's restated empty states as the binding source. A3's empty-state work
  should follow whichever is chosen.

---

## Route checklist (2026-08-03 round)

Mark each step with commit hash + gate result when done.

| Step | What | Status |
|---|---|---|
| Ledger | Create this file | ✅ `7728a51` |
| V1 | Audit `PublicFormTargetWarningsWidget` vs board contracts; re-run lens/order tests | ✅ see below |
| V2 | `OwnerImageWorkspaceTest` standalone ×10; close or spawn fix | ✅ 10/10 clean; closed in M2 brief |
| V3 | Pagination-key + dashboard row keys didn't shift component-key assertions | ✅ pinned by tests, all green, no vacuous asserts |
| V4 | Close M1/M2 in media brief; guard-or-document dynamic settings writes | ✅ guarded (SettingsRowInvariantTest) |
| F1-pre | Pin format-count definition (pattern set, paths, recorded number) | ✅ pinned in the guard docblock (`b24490a`): `d/m/Y`+`H:i`+`number_format(`, 3 dashboard paths, 7 date + 5 number = 12 |
| F1 | Localization home beside `UiTimezone` + statement-scanned anti-drift guard | ✅ `b24490a` — `UiFormats` + `UiFormatsPolicyTest`; guard watched red on exactly the 12 sites |
| F2 | Adopt across widgets/Blade/DTOs + near-midnight fixture | ✅ `b24490a` — all 12 routed; 00:30 fixture proven discriminating (UTC-day expectation fails) |
| F3 | "Group other" bucketing via `BreakdownRow::meta` | ✅ `b3d6de4` — `rollUpTail` in `EditorialMetrics`, totals reconcile; F gate: full pest 1,571/19,430, pint, filacheck 0, build ok. **Orchestrator-verified 2026-08-03: identical numbers from a direct run (pest 1571/19,430, pint --test pass, full filacheck 0, tree clean)** |
| A1 | Sparkline min/max normalisation (defect) | ⏳ delegated (chip `task_eae85161`, 2026-08-03) |
| A2 | Trend-coloured stroke from `SeriesRow::delta()` | ⏳ delegated (same chip) |
| A3 | Dashed empty states + `x-filament::link` doorways | ⏳ delegated (same chip) |
| A4 | Make the Board-2 reason-bar doorway promise true (P11 fix) | ⏳ delegated (same chip; added 2026-08-03 from `7ffcaa5` finding) |
| B1 | Alpine hover crosshair + tooltip on SVG sparklines | ☐ |
| Ledger research | Dedicated read-only bad-practices hunt (skills + guidelines + FilaCheck catalogue), report-only | ⏳ chip `task_8d7e8616` |
| Phase-3 re-plan | Board 3 researched and planned fresh against locked decisions | 🟡 plan landed (`7183996`) but ran pre-A/B (orchestrator sequencing error — "after push" followed literally once the push moved mid-route); held as DRAFT, reconciliation pass against landed A/B patterns at route end |
| Docs | Refresh 2R-handoff commit table + gate; current-project-state Prompt-13 row; fold flags | ✅ minimal refresh 2026-08-03 pre-push; full fold again at route end |
| Push gate | Full pest/pint/filacheck/build; push ONLY on operator's word (deploys production) | ✅ 2026-08-03: pest 1563/19,386, full filacheck 0, build ok; pushed pinned `987b92f` (F's concurrent `b24490a` deliberately excluded); Forge release `74621206` = `987b92f`, `/up` 200 |

### V1 record (2026-08-03)

`PublicFormTargetWarningsWidget` audit: `AdminOnlyWidget` ✅, no polling ✅,
skeleton ✅, en+he keys ✅, lens/order tests green after the index shift ✅.
Fixed: missing stock/flow tag (and the same gap on `BlockersQueueWidget`),
replaced the hand-listed tag test with a structural loop over every lens
registration; `PublicFormTargetStatus` scoped per request with a counts memo so
canView + render compute once (was two full recounts per render).
**EditorialMetrics verdict: legitimately domain-owned.** Its write paths
(settings saves, `HomepageSection`) are outside `EditorialMetrics`' observer
invalidation, so folding the counts into the cached snapshot would show a stale
warning for up to 60s after the very edit that fixes it.

### F record (2026-08-03)

The F block ran in the delegated side-session (chip `task_cf5972af`), TDD
with watched red throughout. Commits: `b24490a` (F1+F2), `b3d6de4` (F3);
the side-session also committed the inherited delegation bookkeeping as
`0ad1d68` before starting, to begin from a clean tree.

**The pinned definition (F1-pre), verbatim from the guard docblock in
`tests/Feature/UiFormatsPolicyTest.php`:** pattern set = literal `d/m/Y`,
`d/m/Y H:i`, `H:i` + `number_format(` calls (scanned as the atoms `d/m/Y`,
`H:i`, `number_format(`); scope = `app/Filament/Widgets`,
`app/Support/Dashboard`, `resources/views/filament/widgets`; count at pin
time = 7 date + 5 number = 12 sites. App-wide (63 `d/m/Y` + 7
`number_format(` grep lines) parked. The earlier 7/10/12 + 52/64 count
confusion came from mixing atoms-per-line, sites and file counts; the
pinned unit is **format-string occurrences** (a `d/m/Y H:i` line is one
site).

Notes for the next session:

- `UiFormats` mirrors `UiTimezone` exactly: final class, typed static
  readers, values in `config/localization.php`. `number()` runs
  `Illuminate\Support\Number::format` on the configured `he` locale —
  output for non-negative integers is byte-identical to the old
  `number_format()`, so **zero existing test expectations changed** in the
  entire F block. Negatives gain an LTR mark under `he` (documented in the
  class docblock); no dashboard surface renders negatives today.
- The guard went red listing exactly the 12 pinned sites before adoption
  and green after — the drift-proof loop decision 18 asks for.
- The F3 roll-up lives in `EditorialMetrics`, not the widget: filawidgets
  owned `groupOther` at the widget layer, but PodText widgets must not
  compute. `rollUpTail` is shared by both breakdowns; the Other row has no
  doorway URL and the composition view renders doorway-less rows as plain
  text (never `href=""`).
- The transcriber Other row sums `previous` from the tail rows themselves,
  so its delta stays honest for the tail as displayed — it does not go
  hunting for previous-period-only transcribers the current board never
  listed.

---

*Curation note (2026-08-03): the phase-3 session's contributed candidates
(`7ffcaa5`) were promoted to P11/P12 and its P2 sighting folded in above;
the raw candidate section was absorbed and removed.*
