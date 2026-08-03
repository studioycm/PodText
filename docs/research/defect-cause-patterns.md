# Defect cause-pattern ledger

Started 2026-08-03 by the dashboard route-plan orchestrator session. Every
defect found in the Prompt 13 round traces to a repeatable cause-pattern; each
entry here turns one finding into a search prompt for similar cases elsewhere.

**Protocol.** This file has **one owner** — the orchestrator session curates
it (operator-confirmed 2026-08-03; two writers on one ledger would be one-home
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

**Registration discipline (operator directive, 2026-08-03).** Every new
sighting or finding is registered in this ledger (or the rightful doc for its
domain) when it is found — not batched, not held in chat. Every *deferral*
decision — a postponed sweep, parked work, an unfiled upstream report, a
draft held for reconciliation — is registered at the moment the deferral is
decided, stating what, why, and what unblocks it. A finding or deferral that
exists only in a conversation does not exist.

**Naming convention (operator directive, 2026-08-03).** Bare letter+number
ids (`P1`, `A4`, `F2`…) collided across at least five families in this repo
(ledger patterns, empty-state design principles, media-program packages,
route steps, research findings). The rules now:

- **Cause-patterns:** the canonical id is the kebab **slug** in each entry's
  header (`silent-cap`, `unscanned-home`). The old `P<n>` ids survive as
  *(alias P\<n\>)* markers because pre-2026-08-03 commit messages use them —
  they are historical aliases, never to be minted again.
- **Other families carry a family prefix:** `ES-<n>` = the empty-state
  design principles (formerly "P1–P7"); `Q<n>` = operator decision questions
  (phase-3 plan register); research-session findings are report-local
  (`F-<n>` etc.) and must be renamed to slugs or folded on curation, never
  cited bare outside their report.
- **Route steps** (V/F/A/B + digit) are scoped to one round's checklist and
  are only meaningful next to it.
- **Never mint a bare letter+digit id for a new family.** New registries get
  either slugs or a distinct prefix.

Entry format: **name** · one-line cause · evidence (where it already bit, with
commits) · where else to look (concrete greps/paths) · status.

---

## one-home · Value duplicated instead of routed through one home *(alias P1)*

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
  Research addendum (2026-08-03): the guard also hunts only the *correct*
  literal — a wrong `m/d/Y` in dashboard scope would sail through; a
  positive-form atom (any literal inside `->date(`/`->dateTime(` in scanned
  paths) would close that half. Routing debt outside the guard's scope:
  exactly two `number_format()` sites bypass `UiFormats::number()` —
  `PublicFormSubmissionResource.php:58` (admin nav badge) and
  `PublicContentItemCardPresenter.php:581` (**public-facing** word counts,
  the Hebrew-grouping case the home exists for). Queued as a quick fix with
  the post-B1 batch. Census re-verified by git: 63 literals at `987b92f`,
  57 from `b24490a` through HEAD — F2 removed six, no growth since.

## unrouted-enum · An enum beside hand-written values protects nothing *(alias P2)*

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
- **Sighting (2026-08-03, research session, POTENTIAL — page-tier variant):**
  authorization intent parked in resource `can*()` overrides with **no policy
  behind it**. In Filament v5, record/bulk actions authorize via policy, not
  the resource's `can*()`; `UserResource.php:47-77` encodes everything in
  static overrides (`canDelete(): false`) with no `UserPolicy` — safe today
  only because no delete surface exists; the day a `DeleteAction` is added,
  the override right above it stops nothing. Same shape:
  `PublicFormSubmissionResource.php:105`, `SettingsBackupResource.php:43`.
  Suggested guard: structural loop over resource registrations (policy exists
  OR no mutation actions exposed).
- **Sighting (2026-08-03, research session, POTENTIAL — default-tier
  variant):** `AppServiceProvider.php:169-173` sets
  `Select::configureUsing(->preload()->optionsLimit(50))` — the global
  default encodes the unsafe branch (growing sets preload) and safety lives
  in 10+ hand-written per-site `preload(false)` opt-outs. The durable fix is
  inverting the default; that is an operator decision (registered in the
  decisions row).
- **Status:** open as a rule; `StreamEventType` lands in phase 3. Four+
  sightings — the **unrouted-enum sweep is now unblocked** (research report landed) and
  is queued as its own task after B1 verifies: route unrouted enum literals,
  and decide the two tier-variant guards.

## raw-state · Live unvalidated state read raw *(alias P3)*

- **Cause:** state persisting across requests (`#[Url]`, session-persisted
  filters, Filament `pageFilters`) is consumed without narrowing, so a stale or
  hostile value reaches queries/rendering.
- **Evidence:** `$this->pageFilters` consumed unvalidated (Filament docs warn
  it is user input); fixed by narrowing in `ReadsDashboardFilters` (phase 2R).
- **Where else:** `grep -rn "#\[Url" app`; `grep -rn "pageFilters" app`;
  Livewire public properties fed into queries without validation;
  session-persisted table filter state.
- **Status:** dashboard fixed. **Public tier swept clean 2026-08-03**
  (research session): all 7 public Livewire components narrow `#[Url]` state
  — tampered transcript keys resolve against the published set only
  (`ContentItemTranscriptViewer.php:84-113`), search tag IDs re-validate
  against `enabled()`, dates try/catch, `perPage` is clamped. No second
  sighting exists; pattern moves to watch.

## implicit-keys · Shared implicit keys *(alias P4)*

- **Cause:** components rely on a key (query-string, pagination, `wire:key`,
  schema component key) that is implicitly derived, so it collides between
  siblings or shifts when a list changes.
- **Evidence:** blockers queue table shared the page's query-string keys
  (fixed, `queryStringIdentifier`); pagination keys namespaced per component
  (`2e786ed`); M2 stale-memo clone graft fixed by per-mount key nonce
  (`0b99bf8`); `->key()` on a schema Section re-keys its **children** (`HasKey`
  inheritance, E5 session finding).
- **Where else:** index-derived Livewire widget keys that shift when a lens's
  widget list changes (V3 checked this round); `grep -rn '\->key(' app/Filament`.
  *(Retired 2026-08-03: the "admin tables lacking `queryStringIdentifier`"
  grep — `AppServiceProvider.php:228-231` now assigns it globally from the
  component class via `Table::configureUsing`, a structural default.)*
- **Sighting (2026-08-03, research session, POTENTIAL — Blade tier):**
  filter-driven widget views re-render unkeyed dynamic loops (e.g.
  `activity-stream.blade.php:48`; 11 widget views, zero `wire:key`s; also
  `public-header.blade.php`). Cosmetic today (plain-HTML rows, no Alpine
  state inside) — converts to M2's family the day a row gains Alpine state
  or a nested Livewire child. Contract note added to the handoff: a loop
  that re-renders under filters gets a stable-id `wire:key` before it gets
  interactive content.
- **Status:** multiple fixes landed; V3 verified the residual; Blade-tier
  rule registered as a handoff contract.

## proxy-oracle · Proxy metrics teach tests wrong expectations *(alias P5)*

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

## silent-cap · Silent caps *(alias P6)*

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
- **Sighting (2026-08-03, research session, POTENTIAL — silent-fallback
  cousin):** the `relationLoaded('enabledContentTags') ? loaded : query()`
  shape (`ContentItemSearch.php:845`, `ShowContentItem.php:227`,
  `PublicContentItemCardPresenter.php:665`, `ContentItemExporter.php:163`)
  gracefully absorbs a future missing eager-load into an invisible per-card
  N+1 instead of surfacing it. All current paths load correctly — a fallback
  that reads as correct while degrading. Watch.
- **Status:** breakdown caps closed (`b3d6de4`, `rollUpTail` + a reconciling
  "Other" row on `BreakdownRow::meta`); M1 queued separately.

## flake-label · "Flake" labels hiding real defects *(alias P7)*

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

## line-guard · Line-based guards miss multi-line call sites *(alias P8)*

- **Cause:** an anti-drift guard that greps single lines misses the same call
  formatted across lines; guards must scan statements, not lines.
- **Evidence:** phase 2R anti-drift trap notes (handoff); the timezone guard's
  shape cannot be copied naively.
- **Where else:** the F1 formats/colour guard (binding requirement); audit any
  existing source-scanning tests for line-anchored regexes.
- **Status:** met — F1's guard whitespace-collapses file contents before
  matching (`b24490a`). The timezone guard needs no retrofit: its literal is
  a single string that cannot split across lines. Research verification
  (2026-08-03): both project guards confirmed statement-safe; note FilaCheck
  itself is AST-based (PhpParser), so line-guard applies to the project's grep-style
  guards only — FilaCheck's real blind spots are path scope (`app/Filament`
  only), closure-built options, service hops, and authorization (see
  decorative-cap/service-hop-cost).

## options-state-cast · `->options(Enum::class)` changes state type, not just labels *(alias P9)*

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

## no-type-home · Concept without a type home *(alias P10)*

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

## unpinned-promise · Docblock promises behavior no test pins *(alias P11)*

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
- **Status:** **closed** (A4, `c36f6c4`) — reason bars are on-board doorways
  dispatching `dashboard-reason-selected` into the queue's existing reason
  SelectFilter (the URL form was proven infeasible in vendor source: widget
  `tableFilters` carries no `#[Url]` binding), both ends validate via
  `DashboardReason::tryFrom`, and the docblock now states exactly what tests
  pin. The A session's sweep of the remaining dashboard doorway URLs found
  them all backed by receiving filters (`TranscriptionsTable.php:93`,
  `ContentItemsTable.php:138`) — no further instances.

## unarchived-binding · Binding-by-reference to an unarchived source *(alias P12)*

*(Promoted 2026-08-03 from a phase-3-session candidate, `7ffcaa5`.)*

- **Cause:** a doc declares an external artifact's content binding without
  restating it, so the "contract" has no readable text to enforce — kin of
  unrouted-enum's generalized form: a contract enforced only where its text happens to
  exist protects nothing.
- **Evidence (ACTUAL):** `dashboard-metrics-combined-ux-plan.md`'s
  honesty-audit row declares "Empty-state designs and principles ES-1–ES-7 …
  binding for every widget's build spec", but the ES-1–ES-7 text exists in no
  repo file, and neither linked artifact contains it (both fetched and
  searched 2026-08-03 by the phase-3 session; the principles were authored
  in an earlier, unlinked design pass). The phase-3 plan restates concrete
  per-widget empty states instead of citing ES-1–ES-7.
- **Where else:** `grep -rn "claude.ai/code/artifact" docs/phase-02`
  cross-checked against "binding"/"principles"/"declared" claims near the
  links; any spec sentence of the form "as designed in the mockup" with no
  repo copy.
- **Status:** open — operator decision requested (phase-3 plan open question
  5): supply the original ES-1–ES-7 text into the combined plan, or accept the
  plan's restated empty states as the binding source. A3's empty-state work
  should follow whichever is chosen. (A3 landed the concrete dashed idiom
  without reinventing principles — compliant either way.)

## unscanned-home · Style home outside the compiler's scan scope *(alias P13)*

*(Promoted 2026-08-03 from the A-block session's report; found and fixed in
`b9825c6`.)*

- **Cause:** consolidating class literals into a PHP home (one-home/unrouted-enum's cure)
  silently fails if the asset compiler's scan scope does not include that
  home — Tailwind only emits classes it can read, so a routed-but-unscanned
  class renders as no styling at all. Routing protects nothing the compiler
  doesn't also read.
- **Evidence (ACTUAL, fixed):** `app/Enums` was absent from the admin theme's
  `@source` globs (`resources/css/filament/admin/theme.css`), so colour
  classes living only in enum homes were never compiled: the draft funnel
  bar (`bg-gray-400`) and the unpublished-group / missing-category reason
  bars (`bg-danger-400`, `bg-violet-500`) rendered **colourless in
  production** — verified absent from the live compiled theme, present
  post-fix. Every call site read from the enum (unrouted-enum satisfied) and it still
  broke. Pinned by the scan-scope test at `DashboardEnumsTest.php:102`.
- **Where else:** every PHP surface that emits class strings — enums,
  data objects, `HtmlString` builders, Livewire component classes — checked
  against the theme's `@source` globs; any future style home added outside
  `app/Filament`/`resources` needs a glob and a scan-scope pin.
- **Status:** instance fixed + pinned (`b9825c6`); the general check rides
  the scan-scope test. The related dormant-arm note is resolved (`1efeb35`):
  `bandClass()` stays deliberately case-complete — only the visible band
  renders today, the arms were born whole in `4bd4030` with no renderer ever
  added or removed (`git -S`), a partial match would put
  `UnhandledMatchError` in reach of future all-stages loops, and the docblock
  now says so in place. Same-shaped sibling (A-session report-only nudge,
  verified by grep and resolved identically 2026-08-03):
  `DashboardTier::Attention->bandClass()` is dormant — only
  `Invisible->bandClass()` renders (gap bar, funnel gap button); kept
  case-complete with the mirrored docblock.

## decorative-cap · Guard incantation attached to a mechanism that isn't in play *(alias P14)*

*(Promoted 2026-08-03 from the research session's F-2, "decorative-cap".)*

- **Cause:** when the sanctioned pattern doesn't fit, the fallback API
  silently lacks the cap semantics — and pasting the guard boilerplate anyway
  makes the site *look* compliant, so reviews pass it. Filament specifics:
  `->preload(false)->optionsLimit(50)` are **inert** on a static
  `->options(closure)` — they only govern `getSearchResultsUsing()` /
  relationship selects.
- **Evidence (ACTUAL, mechanism verified):** transcriber filters at
  `ContentItemsTable.php:147-156` and `TranscriptionsTable.php:93-102` pluck
  the full authors table on every render while wearing both modifiers;
  `TranscriptionsTable.php:105-113` plucks **all** groups uncapped while the
  same concept on the items table (`ContentItemsTable.php:138-143`) uses the
  correct capped `relationship()` shape. The in-repo correct non-relationship
  pattern exists: `IconSelect.php:18-19` (`getSearchResultsUsing()` +
  `getOptionLabelUsing()`).
- **Where else:** `grep -rn -A6 "SelectFilter::make" app | grep -B2 "::query()"`;
  any `->options(fn` adjacent to `optionsLimit(`; future provider/connection
  filters.
- **Suggested guard:** FilaCheck-style AST rule — flag `optionsLimit()`/
  `preload()` co-occurring with static `->options()` and no
  `getSearchResultsUsing()`.
- **Status:** open — fix queued in the post-B1 batch (rewire the three
  filters to the capped shape).

## service-hop-cost · Per-row cost hidden one service hop away *(alias P15)*

*(Promoted 2026-08-03 from the research session's F-3.)*

- **Cause:** a per-row table closure calls a service whose query cost is
  invisible at the call site; an eager load added at the query level doesn't
  help because the service calls relation *methods* (fresh queries), not
  loaded relations. Sub-mechanism: `eager-load-defeated-by-relation-method-call`
  (`->categories()` vs `->categories`).
- **Evidence (ACTUAL):** `BlockersQueueWidget.php:70` `->state(fn ($record) =>
  …blockerReasonsFor($record))` → `EditorialMetrics.php:521-541` runs up to
  4 queries per rendered row (30–100 per render at page size 10–25, re-run on
  every Livewire update) — while the table eager-loads
  `contentGroup.categories`+`categories` as dead weight. The repo's own
  correct pattern: `MediaInventoryDiagnostics.php:25-28` memoizes per record
  and batch-primes. Export-scope twins (same shape, cheaper):
  `ContentItemExporter.php:62`, `ContentGroupExporter.php:50`.
- **Where else:** `grep -rn -e '->state(fn' -e '->tooltip(fn' -e '->color(fn'
  app/Filament | grep 'app('`; any new `EditorialMetrics` per-record method
  reached from a column.
- **Suggested guard:** query-count regression test — render the widget with a
  25-row fixture under `DB::listen`, assert a fixed budget.
- **Status:** open — fix queued in the post-B1 batch (batch-prime the reasons
  like `MediaInventoryDiagnostics`, or fold reasons into `queueQuery()`
  selects; plus the query-budget test).

## client-payload · Cross-step payload carried through the client *(alias P16)*

*(Promoted 2026-08-03 from the research session's F-5.)*

- **Cause:** multi-step flow state (a whole uploaded document plus derived
  rows) held in public Livewire properties serializes into every snapshot and
  round-trips per step — a payload cost and a client-writable surface at
  once.
- **Evidence (ACTUAL mechanism, admin-scope, bounded):**
  `SettingsImportWizard.php:31` holds the entire decoded settings package
  (≤2 MB validated) in `public array $packageArray`; `:244`/`:260` assign
  full diff-row arrays to public `$rows`. Tamper value low (admin-only; the
  apply path is the V4-guarded writer). Same family:
  `CardTemplateEditorPage.php:135` (`public ?string $previewHtml`).
  Coupled note: the wizard re-runs `analyzeArray` on every step transition
  (`:240`, `:258`) — CPU-only today.
- **Where else:** `grep -rn "public array" app/Livewire app/Filament/Pages`
  reviewed against what fills them.
- **Suggested guard:** handoff contract note (server-side holding: cache/temp
  file keyed by a token prop; rows re-derived per render) — architectural,
  registered in the handoff.
- **Status:** open, watch-tier; no route fix queued (out of dashboard scope).

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
| A1 | Sparkline min/max normalisation (defect) | ✅ `2831ee9` — min−max over a 2px-inset band, exact-coordinate tests watched red against peak-only maths |
| A2 | Trend-coloured stroke from `SeriesRow::delta()` | ✅ `b9825c6` — `SparklineTrend` enum as the one palette home + source-scanning literal ban; also fixed/pinned unscanned-home (theme `@source` scan scope) |
| A3 | Dashed empty states + `x-filament::link` doorways | ✅ `103b728` — shared dashed empty-state partial (en+he), Heroicon doorways; F3 `href=""` pin intact |
| A4 | Make the Board-2 reason-bar doorway promise true (unpinned-promise fix) | ✅ `c36f6c4` — on-board dispatch into the queue's reason filter (URL form vendor-proven infeasible); unpinned-promise closed. **Orchestrator-verified 2026-08-03: pest 1587/19,563 direct run (identical to claim), pint --test pass, full filacheck 0, tree clean.** Follow-up `1efeb35` resolved bandClass keep-vs-delete (keep, docblock-only, `git -S` evidence) |
| B1 | Alpine hover crosshair + tooltip on SVG sparklines | ⏳ delegated (chip `task_92e7305d`, session running) |
| Naming | Retire bare P-number ids: pattern slugs + aliases, ES- prefix for design principles, convention block | ✅ `27c5f3b` |
| Scan-scope research | How Filament/FilamentExamples/LaravelDaily recommend Tailwind source scanning for class-emitting PHP (enums, Livewire, presenters, DB-driven templates) → policy + guard recommendation (`unscanned-home` follow-through) | ⏳ chip `task_30ef637c` |
| Ledger research | Dedicated read-only bad-practices hunt (skills + guidelines + FilaCheck catalogue), report-only | ✅ merged 2026-08-03 — 7 findings + flags curated: decorative-cap/service-hop-cost/client-payload promoted, unrouted-enum gained two tier-variant sightings, raw-state public-tier all-clear, implicit-keys queryStringIdentifier grep retired, security battery zero ACTUAL. Coverage gaps it declared: ~74/89 Blade views grep-only, importer connector internals, browser-test contents |
| Operator decisions | Phase-3 plan "Open questions" Q1–Q6 (Q5 = unarchived-binding empty-state principles) **+ Q7 (from research F-4): invert the global Select default to `preload(false)` and opt bounded sets in?** — block phase-3 implementation, not B1 | ⏳ surfaced to operator 2026-08-03 |
| Post-B1 fix batch | decorative-cap rewire (three decorative-cap filters → capped shape) · service-hop-cost fix (batch-prime blocker reasons + query-budget test) · one-home stragglers (two `number_format()` sites → `UiFormats::number()`, one public-facing) · unrouted-enum sweep (unrouted enum literals + tier-variant guard decisions incl. F-1 structural policy test) | 📌 registered 2026-08-03, chips go out after B1 verifies |
| Deferred register | one-home app-wide format/colour sweeps (parked, budgeted separately) · M2 upstream Filament report (worth filing, unfiled — M2 brief/handoff) · phase-3 plan reconciliation (after B1) · 1/30 Storage-listing timeout (flake-label watch) · client-payload wizard architecture (watch-tier, out of dashboard scope) · research watch items: `embed_provider` full-table `distinct` per render (`ContentItemsTable.php:173`), 9 enums without Filament contracts (E4 residual), Blade string-icon duplication, importer authz panel-only | 📌 standing register per the registration discipline |
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
(`7ffcaa5`) were promoted to unpinned-promise/unarchived-binding and its unrouted-enum sighting folded in above;
the raw candidate section was absorbed and removed.*
