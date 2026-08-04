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

**Governance (2026-08-03).** The general rules this ledger runs under —
`register-at-the-moment` (findings at discovery, deferrals at decision, in
the rightful doc), `names-carry-family` (slugs canonical, `P<n>` historical
aliases, no new bare letter+digit families), `one-owner-registry`,
`readable-binding`, `pinned-promise`, `provenance-stated`,
`verify-dont-trust` — now live in their one home,
`docs/phase-02/dashboard-governance-principles.md` (dashboard-scoped by
operator ruling). Ledger-specific mechanics stay here: entry format, the
side-session report-contribution flow, and the 2+-sightings sweep trigger.

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
  OR no mutation actions exposed). **Correction (2026-08-03, fix-batch
  session, ACTUAL):** the "no mutation surface exists" line was wrong for
  `SettingsBackupResource` — `SettingsBackupsTable:191` carries a live,
  policy-less `DeleteAction`, allowed to every panel user in non-strict
  mode. `ResourcePolicyCoverageTest` (`73e4c17`) carries it as the single
  allow-listed entry with its why (superseded: the policy landed `7768442`
  and the ruling flip `ce95a35`; the guard now runs with an EMPTY
  allow-list, and the session's sweep confirmed no other resource has a
  policy-less mutation surface). **Tier-semantics pin (2026-08-04,
  `c399eaa`, proven by a deny-test mutation):** "registered policy +
  missing method = deny" is TRUE for bare `Gate` but FALSE at Filament's
  resource tier — Filament ALLOWS on a missing policy method in non-strict
  mode. A policy method like `ImportPolicy::viewAny` is therefore
  load-bearing only for its deny direction; never reason about Filament
  authorization from bare-Gate semantics (the coverage-test docblock
  states this correctly).
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

## double-registration · Explicit wiring beside framework auto-discovery

*(Founded 2026-08-04 from the `imports-provider-stamp` task — mechanism
verified via `event:list`.)*

- **Cause:** Laravel's listener auto-discovery (default-on; no
  `->withEvents()` override in `bootstrap/app.php`) registers any
  `app/Listeners` class by its event type-hint — an ADDITIONAL explicit
  `Event::listen` line for the same listener registers it twice, and the
  handler fires twice per event: no error, just silently duplicated side
  effects.
- **Evidence (caught pre-land, `6e6a03a`):** `StampImportSource` was
  explicitly registered while discovery had already picked it up —
  exposed by a SURVIVING listener-registration mutation (removing the
  explicit line changed nothing because discovery still registered it);
  `event:list` showed both rows. Resolved: explicit line removed,
  discovery documented on the listener class.
- **Where else:** any typed `app/Listeners` class that also appears in an
  `Event::listen`/`->listen` map; sweep candidate = one `php artisan
  event:list` duplicate-row scan (the repo's other listener is a
  `Event::subscribe` subscriber — different path, still worth the scan).
- **Suggested guard:** ONE registration home per listener — discovery with
  a class-level docblock, or explicit wiring with discovery disabled;
  never both. The `event:list` duplicate scan is the census.
- **Status:** HANDLED end-to-end 2026-08-04. Instance fixed pre-land
  (`6e6a03a`, phase-3 session); census executed by the orchestrator —
  **clean, zero true duplicates** across all 71 events (two census-pipeline
  false-positives en route — misattributed lowercase `eloquent.*` headers,
  then collapsed model classes — caught by reconciling against source
  before acting: detector-sanity applies to one-off scan pipelines too);
  standing guard landed: `tests/Feature/ListenerRegistrationInvariantTest`
  pins the whole listener map (closures keyed by definition site) with a
  deliberate-duplicate detector leg, so an empty result can never be
  detector failure. Watch-register sweep entry closed.

## planned-fixture-drift · Plan-authored fixtures inherit unverified assumptions

*(Founded 2026-08-04 during the phase-3 implementation — three sightings in
five tasks.)*

- **Cause:** a plan's test specs are written without being executed, so
  their fixtures/expectations encode the planner's assumptions about repo
  facts — factory defaults, cast semantics, guard atoms — and drift from
  repo reality; implemented verbatim, they produce wrong-boundary or
  tautological tests.
- **Evidence (all caught by the implementing session's watched-red
  discipline):** (1) the plan's "non-admin" fixture was an admin —
  `User::factory()` defaults to admin here (`f0ac4df`); (2) the chips test
  read its expectation from the enum under test (`e2010b8`,
  `expectation-from-home` sighting); (3) the timestamp fixture assumed
  tz-convert-on-write, but Laravel casts store the Carbon's literal wall
  time — the repo's own overview suite pins those semantics (`d353482`).
- **Where else:** every remaining phase-3 plan test spec; any future plan's
  fixture prose; the episodes design's eventual implementation plan.
- **Suggested guard:** already in force and named here so it survives this
  round — every plan-specced fixture/expectation is a HYPOTHESIS until
  watched red-green against repo reality; the implementing session corrects
  with a why-comment and reports the drift (plans stay valuable as intent;
  fixtures are never copied on faith).
- **Status:** founded at three sightings; the discipline is the fix — no
  sweep needed, but plan-writing sessions should be briefed to mark
  fixture-level claims as unverified. **Author-time proof (2026-08-04,
  `ea22fae`):** the phase-4 plan's retroactive evidence-label audit
  (VERIFIED-at-HEAD list vs five labelled UNVERIFIED hypotheses with
  resolution paths) caught a real plan bug pre-implementation —
  `SeriesRow::$points` is `array<int, float>`, so a strict `toBe` against
  `array_sum` needed an `(int)` cast; fixed with a why-comment. The
  load-bearing labelled hypothesis (queue population semantics,
  `queueQuery` body unread at planning) is the implementer's first
  resolution target. This is the pattern's guard operating at AUTHOR time
  — drift caught before it could teach anyone anything wrong.

## event-halting-return · An implicit bool return halts model-event propagation

*(Founded 2026-08-04 from phase-3 Task 2 — ACTUAL, pre-existing, latent in
production until the first second listener arrived.)*

- **Cause:** a model-event closure written as an arrow fn implicitly returns
  its body's value; when the body is a bool-returning helper
  (`Cache::forget()` returns FALSE on an absent key), a `false` return hits
  Laravel's dispatcher break-on-false semantics (vendor
  `Events/Dispatcher.php:335`) and SILENTLY halts the listener loop — every
  later listener for that model event is skipped.
- **Evidence (ACTUAL, fixed `9b494a4`):** `PublicFormSubmission::booted()`
  registered `static::saved(fn (): bool => Cache::forget(badge_key))`; the
  new `EditorialMetricsCacheObserver` registration became the first-ever
  second listener and was silently skipped — caught by the watched-red
  invalidation test, diagnosed cleanly (sentinel probes → control model →
  listener-registry reflection → vendor dispatcher read; no
  re-run-and-hope, `flake-label` discipline in practice). Both closures now
  return void with a why-comment.
- **Where else:** `grep -rn "static::\(saved\|created\|updated\|deleted\)(fn" app`
  (clean at fix time); any event/observer closure ending in a
  bool-returning call (cache ops, file ops, `Model::save()`).
- **Suggested guard:** model-event closures return void, stated in the
  why-comment at the fixed sites; the grep above is the census trail; an
  arch-level pin is an open wish (no clean Pest arch expression for
  "closure must not return bool" yet).
- **Status:** founded, one sighting, fixed; grep-clean for siblings.

## expectation-from-home · The test's oracle is the code under test

*(Founded 2026-08-04 from phase-3 Task 1; a second, earlier evidence point
was already on record from the A-block.)*

- **Cause:** a test's expected value is READ FROM the very home it should
  be pinning (`expect($html)->toContain($enum->chipClass())`) — a tautology
  that passes under any mutation of the home. Kin of `proxy-oracle`, but
  here the oracle isn't a wrong column — it IS the unit under test.
- **Evidence:** (1) A-block, A2: a rendered-HTML assertion passed against a
  hand-written copy of the routed literal — why the trend palette got a
  source-scanning literal ban instead (`b9825c6` era, recorded in the A
  row). (2) Phase-3 Task 1 (`e2010b8`): the plan's chips test asserted
  `$type->chipClass()` against the widget HTML; mutation survived; re-pinned
  as independent literals over a four-kind fixture, after which mutation
  failed correctly.
- **Where else:** any assertion whose expected side calls the
  production accessor it renders (`grep -rn "toContain(\$" tests | grep
  "->get\|->chip\|->class\|->label"` as a first trail); enum
  label/colour tests; URL-shape tests built from the same builder.
- **Suggested guard:** the widget-principles' mutation-check rule is the
  detector (strip the home, watch the test); pin independent literals or
  fixtures for vocabulary contracts. Doctrine refinement (Task 4,
  `55a30b6`; extended `6e6a03a`): a SURVIVING mutation is not always a
  vacuous test — it can mean a masking upper layer (the blade's provider
  gate) or a DUPLICATED mechanism (`double-registration`'s redundant
  listener wiring). A survivor demands identification: vacuous test,
  masking layer, or redundancy defect — each has a different fix.
  Concrete vacuous-assertion instance (`c399eaa`): `assertSee` on provider
  labels was satisfied by the SelectFilter's own option labels elsewhere in
  the page HTML — the fix is the precise-state assertion
  (`assertTableColumnStateSet`), after which the mutation kills.
  Two authoring-tier additions (2026-08-05, phase-4 Tasks 1–2): (a)
  **Livewire v4 has NO component-level `assertViewHas`** — the call
  silently falls through to the HTTP wrapper's `TestResponse`, whose view
  lacks widget data, so the closure receives null and the assertion is
  structurally vacuous; the honest lever is `Testable::viewData()`. Census
  candidate: `grep -rn "assertViewHas" tests` in Livewire component tests.
  (b) **A specced mutation must be provably lethal**: the plan's
  `->limit(2)` queue mutation was SQL-INERT (LIMIT never affects COUNT
  aggregates) — a mutation that cannot fail is `decorative-cap`'s
  test-tier cousin; replaced with dropping a reason arm, which kills via
  the orphan fixture. (c) The plan's `->repeat(3)` soak call was a
  labelled hypothesis that resolved to NO — no repeat API exists in the
  house browser corpus; external runs are the mechanism. All three
  authoring-tier traps were caught by the labelled-hypothesis discipline
  before costing anything.
- **Status:** two evidence points at founding; watch for a third before a
  sweep.

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
- **Kin note (2026-08-04, phase-3 Task 8, self-reported):** a pest
  pathspec that matches NOTHING silently runs the FULL suite — silent
  scope-WIDENING, the cap's inverse; a glob typo briefly launched a full
  run mid-task (killed, no state impact — sqlite/array drivers). Rule: 
  explicit paths, and notice a suspiciously large test count immediately.
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
- **Sighting (2026-08-03, fix-batch hardening round, residual family):**
  `OwnerImageWorkspaceBrowserTest` "Hebrew RTL iPhone 15" hit one Playwright
  30s timeout during a FULL run executed while another session's uncommitted
  work sat in the shared tree (the scan-scope session's phpunit.xml/theme
  edits — not the round's diffs; stash-baseline discipline applied), then
  passed standalone 12/12. Second data point for the parallel-worker/
  concurrent-activity contention hypothesis (`local_51518218`-era).
- **Status:** V2 closed the owner-image mechanism sighting. **The
  contention hypothesis is RESOLVED (2026-08-04):** all three residual
  data points are explained by `fake-root-purge` (see that entry; 108-run
  investigation, controls clean). The ~4% settle-wait residual
  is now DIAGNOSED (2026-08-04): a real app-side accessibility defect —
  see `two-writer-channel` sighting #2. Watch closed into that entry.

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
- **Sighting #2 (2026-08-03, ACTUAL — media program):** the media UX
  redesign's own design principles — "P1 Answer before ask … P7 Three kinds
  of trouble, three chips" (v2 adds "P8 Leave seats for what's coming") —
  exist ONLY in operator-local PDFs
  (`~/Downloads/podtext-media-ux-redesign{,-v2}.pdf`; v1 has six, v2 has
  eight), in **no repo doc** (grep: "Answer before ask" / "One green thread"
  / "Quiet by default" — zero hits under `docs/`), while the media UX3
  handoffs build on that redesign. Also the FIFTH bare `P<n>` family found,
  vindicating the naming convention. Text extracted 2026-08-03 (session
  scratchpad, `pdf-v1/v2/codex.txt`) — archival into a repo doc offered to
  the operator. Checked and negative: none of the three PDFs contains the
  *dashboard* empty-state list (zero dashboard/widget mentions; the Q5
  hypothesis tested 2026-08-03). **Sighting #2 CLOSED same day:** principles
  archived verbatim (Hebrew un-reversed) into
  `docs/research/media-program/media-ux-design-principles.md` on operator
  instruction.
- **Sighting #1 CLOSED (2026-08-03):** the ES-1–ES-7 text was RECOVERED
  from the phase-1 design session's transcript (`local_c94db27d` — the
  "original dossier" the HE combined artifact cites) via chained transcript
  searches, and archived into `dashboard-metrics-combined-ux-plan.md` § "The
  dossier principles (ES-1–ES-7), recovered" with a reconciliation showing
  every principle already enforced by a live contract. Correction folded in:
  they were the dashboard's seven *design principles* (only ES-3 is the
  empty-state one); deep-search verdict 2026-08-03: no separate empty-state
  design spec ever existed — the Hebrew audit row's "(אפס אמיתי)" names ES-3
  itself, verified by exhausting the dossier session, both mockup artifacts,
  and the six-options document. Q5 is
  thereby RESOLVED (original text supplied, no conflict found).
- **Status:** both sightings closed; the sweep remains registered — hunt
  binding references to unarchived externals (`grep -rn
  "claude.ai/code/artifact" docs/` cross-checked against binding claims,
  plus operator-local design PDFs and session transcripts cited by
  handoffs) and archive what binds. Recovery method worth reusing: chained
  snippet-window searches over session transcripts reconstruct lost text.

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
- **Sightings #2 + #3 (2026-08-03, scan-scope research, ACTUAL — live in
  production now, predating this route's push):**
  `SafeMarkdownRenderer.php:30,35` — the entire public prose contract
  (`[&_h1]:`, `[&_a]:`, `[&_blockquote]:` …) is absent from BOTH compiled
  themes (0 hits in the built assets), so public markdown/about/transcript
  prose renders with Preflight-flattened defaults; and
  `PublicItemPageRegistry.php:249-280` — badge size/colour/identity maps
  absent from the compiled public theme (the *delegation variant*: the
  scanned page `ShowContentItem.php:381-401` delegates to the unscanned
  registry — the literal's home decides scanning, not the renderer's).
  POTENTIAL, accidental-coverage-only: `PublicFrontIconRegistry.php:276+`
  and `PublicContentCardOptions.php:111-146` (admin). Structural: admin
  scans `app/Enums`, public does not. Full analysis, policy (targeted globs
  as a shared root list + DB-token invariant + discovery guard
  `ThemeScanScopeTest`), and the FilaCheck blind-spot proof (its single
  theme rule is Blade-only and permanently silent once any theme exists):
  `docs/research/tailwind-scan-scope-research.md` (`57027b1`).
- **Status:** ALL sightings closed. #1 `b9825c6`; #2/#3 closed at
  `12285a7` (7 glob additions, discovery guard `ThemeScanScopeTest`
  watched-red on exactly the 11 known violations) + `ecc9eda` (on-demand
  `compiled-sentinels` group, watched red 4 failures against the stale
  build, 6/6 after) with compiled pre→post proof (`_blockquote` 0→6,
  `text-sky-700` 0→1 both themes); the POTENTIAL pair (Icons, CardOptions)
  promoted from accidental to direct coverage; enum asymmetry closed
  symmetric. Guard discovery found 23 emitters (6 beyond the research's
  list, all covered) behind 3 self-invalidating exception rows. Footnote
  correcting the research: compiled CSS escapes selectors (`px-2\.5`,
  `\[\&_a\]`), so raw substring probes structurally cannot hit built
  output — the research's absence conclusions stand under escape-safe
  probes except §3.1 row 7's `px-2.5` (was accidentally compiled pre-fix;
  non-load-bearing). Detector note: the arbitrary-value regex needs a known
  utility root before `-[` or Spotify URL parsers false-positive. The related dormant-arm note is resolved (`1efeb35`):
  `bandClass()` stays deliberately case-complete — only the visible band
  renders today, the arms were born whole in `4bd4030` with no renderer ever
  added or removed (`git -S`), a partial match would put
  `UnhandledMatchError` in reach of future all-stages loops, and the docblock
  now says so in place. Same-shaped sibling (A-session report-only nudge,
  verified by grep and resolved identically 2026-08-03):
  `DashboardTier::Attention->bandClass()` is dormant — only
  `Invisible->bandClass()` renders (gap bar, funnel gap button); kept
  case-complete with the mirrored docblock.

## single-read-race · One-shot reads of `x-show` state race Alpine's frame cadence

*(Registered 2026-08-03 from the B1 session's report-only relay; test-authoring
family, kin of `flake-label`'s discipline rather than an app defect.)*

- **Cause:** after an Alpine state write, sibling `x-bind:style` effects apply
  in the flush while `x-show`'s display flip lands on Alpine's frame cadence —
  so a one-shot read (`getComputedStyle`, `offsetParent`) of an `x-show`
  element right after an interaction can see stale visibility in headless
  Playwright. Visibility must be *waited as a labelled condition*, never
  single-read.
- **Evidence (ACTUAL, found and fixed pre-commit):**
  `tests/Browser/DashboardSparklineBrowserTest.php:161-165` (`87aa8bf`) — the
  post-Escape crosshair check failed on a single `getComputedStyle` read while
  the tooltip (style-bound off the same `active` write) had already hidden; a
  reactive probe driving `$data.active` both ways proved both effect
  directions healthy — pure frame timing. Nothing broken was committed.
  Sub-finding, same commit: Alpine boot probes must target the `x-data`
  element — `_x_dataStack` never exists on children
  (`DashboardSparklineBrowserTest.php:81` uses `closest('[x-data]')`).
- **Where else:** the B1 session's recon — 6 other browser-test files read
  visibility via `offsetParent`/`getComputedStyle`
  (`PublicFormModalBrowserTest`, `MediaPickerBrowserTest`,
  `GalleryKeyboardNavBrowserTest`, `MediaResourceGalleryBrowserTest`,
  `OwnerImageWorkspaceBrowserTest`, `CardTemplatePreviewBrowserTest`) against
  15 views using `x-show`; whether any is a post-interaction single-read of an
  `x-show` element is UNCHECKED — that is the sweep question.
- **Status:** first sighting; per the 2+-sightings rule no sweep yet — the
  sweep trigger is registered in the deferred register. Both lessons also
  live in the `browser-script-step-labels` memory.

## test-residue · Suites leave real-disk artifacts later runs trip over

*(Registered 2026-08-03 from the fix-batch hardening round's report.)*

- **Cause:** browser/feature fixtures that write to the real storage disk
  outlive their run; a later test asserting disk state (or a copy-failure
  path) trips over the stray file — cross-run, cross-session pollution that
  reads as a flake.
- **Evidence (ACTUAL, once):** `MediaMutationCoordinatorTest`'s copy-failure
  test failed on a stray `content-groups/covers/browser-owner-podcast.jpg`
  (a browser-fixture name from earlier browser verification), green after
  the disk state cycled; file-level and standalone reruns green.
- **Where else:** browser tests writing under `storage/app/public` with
  fixed names; any feature test asserting absence/failure on paths a
  browser fixture also uses.
- **Suggested guard:** per-run unique fixture path segments + teardown
  cleanup in browser tests, or scoped/fake disks where the flow allows;
  cheapest first step — a fixed-name census (`grep -rn "browser-owner-"
  tests/Browser`) and renaming to run-scoped names.
- **Status:** census executed by the hygiene session (`a14d50a`,
  card-preview fixtures run-scoped). **Boundary note (2026-08-04):**
  distinct from `fake-root-purge` — residue is stale REAL-disk files;
  the purge is live deletion of the FAKE root; renames fix only the
  former.

## two-writer-channel · Single-writer consumers on a two-writer property

*(Founded 2026-08-04 from the contention session's residual diagnosis
(`9318e62`); first sighting was test-side, second is the APP as consumer.)*

- **Cause:** a property with TWO independent writers — Alpine `x-bind` and
  Filament's `wire:loading.attr="disabled"` on every icon-button — is
  consumed as if single-writer: a one-shot read or one-shot action aimed at
  it silently misfires whenever the other writer holds the channel.
- **Evidence:** (1, test-side, fixed) `return_guard_released` read
  `disabled` while Filament's loading writer held it — fixed by asserting
  the guard's own observable (`3cc4906`). (2, APP-side, ACTUAL, diagnosed
  NOT fixed) the media picker's post-upload focus restore: FilePond's inner
  input never takes focus (`activeElement` stays BODY → `uploadFocusId`
  always null), so the restore always takes its fallback — a single
  `$nextTick` `focus()` on `media-picker-source-upload`
  (`media-picker-panel.blade.php:17-28`), a button that is `disabled` at
  the very instant `livewire-upload-finish` fires; held disabled across
  that tick, the restore silently no-ops and the keyboard user lands on
  `<body>`. A genuine accessibility regression, ~4% under quiesced timing,
  worse under load. Diagnosis + proposed fix (verify-and-retry, or target
  an element no loading binding disables):
  `docs/research/browser-timeout-contention-investigation.md` (`9318e62`).
- **Where else:** every consumer (app or test) of `disabled`/loading-bound
  attributes near Filament icon-buttons; any one-shot focus/scroll/click
  aimed at elements with loading bindings.
- **Mechanism enrichment (2026-08-04, frame-traced during the fix):** the
  trap has a SECOND failure shape — the one-shot hand-off can succeed and
  then LOSE: the late `disabled` write (Filament `wire:loading`) blurs the
  just-focused control back to `<body>`, and fire-and-forget never
  notices. And a THIRD mover: the modal focus trap parks body-focus on
  dialog chrome, so "focus is on a real element" ≠ "somebody chose it".
  Rule: **a focus hand-off aimed at a control with a loading-state
  `disabled` writer must be verified ACROSS the settle window, never fired
  once** — one-shot `focus()` loses both before (no-op on disabled) and
  after (blur on late disable).
- **Suggested guard:** consumers verify-and-retry across the settle window
  targeting single-writer observables where possible; test-side,
  per-condition labelled waits (landed: hygiene `af33122`, `83e8fef`).
- **Status:** BOTH sightings closed. #1 `3cc4906` (test-side). #2 FIXED
  `914de1c` + docs `4e7c68f` (operator-directed): two-phase verified
  restore — per-frame retry until a target (remembered → upload source →
  close) takes focus, then only genuine drops to `<body>` recovered (no
  focus-stealing; bounded 120 frames; stops if the workspace leaves the
  DOM). Pinned by `MediaPickerUploadFocusReturnBrowserTest` (watched red
  on the held-disabled case; no-steal boundary pinned). Claimed gate
  1615/19,741 zero failures — **orchestrator-reproduced 2026-08-04:
  1615/19,741 identical, zero failures, pint --test pass, full filacheck
  0, tree clean, no interleaves.** Note:
  pint's blade fixer reformatted `media-picker-panel.blade.php`
  wholesale; the behavioral diff is the root `x-data` block only.

## fake-root-purge · Storage::fake purges a shared root under concurrent runs

*(Registered 2026-08-04 from the contention investigation's verdict — the
mechanism behind all three registered browser-timeout data points.)*

- **Cause:** `Storage::fake('public')` cleans the shared root
  `storage/framework/testing/disks/public` on every call; the
  `ParallelTesting::token()` root suffix that would isolate processes only
  exists under paratest, which is not installed — so ANY concurrent process
  faking the disk deletes an in-flight browser test's files mid-run, and
  the victim's waits time out.
- **Evidence (ACTUAL, 108 logged runs):** quiesced baseline acq 23/24,
  owner 12/12; a second pest process looping ONE public-faking feature test
  → acq **0/10** at load ~3; distilled purge loop → acq 0/10, owner 0/5
  with the RTL "Hebrew iPhone 15" 30s timeout reproduced exactly (3/5) and
  failures concentrated on precisely the 4 storage-driven owner datasets;
  controls exonerate mere concurrency (non-faking second pest 10/10), tree
  churn (10/10), and realistic CPU load (mild 9/10; only absurd load 24–65
  starves). Explains DP1 (1/30 Storage-listing), DP2 (RTL timeout), DP3
  (scan-scope first-run failures).
- **Where else:** every feature test calling `Storage::fake('public')`
  while browser suites may run; any future parallelization.
- **Suggested guard/fix:** per-process fake-root isolation (TestCase-level
  root suffix keyed to PID) — makes concurrent sessions structurally safe
  instead of hold-disciplined; ranked fixes in
  `docs/research/browser-timeout-contention-investigation.md`.
  **Distinct from `test-residue`** (stale REAL-disk files): fixture renames
  do not touch this mechanism.
- **Status:** **FIXED (`a3fa4f2`, operator-authorized 2026-08-04):**
  `tests/Pest.php` fills `TEST_TOKEN` with `p<pid>` when the runner
  supplies none (paratest keeps its own); cleanup at BOTH ends (shutdown
  hook for own roots + boot sweep for dead-PID roots past an age floor);
  28 stale parallel roots deleted; pinned by `TestDiskIsolationTest`
  (watched red 4/4 with the token disabled). Under the previously-fatal
  interferers: acq 6/6 + 6/6, owner 3/3; claimed full suite 1612/19,734
  zero failures — **orchestrator-reproduced 2026-08-04: 1612/19,734
  identical, zero failures, pint --test pass, full filacheck 0** (run
  bracketed hygiene's `af33122` landing; counts match the pre-`af33122`
  claim exactly). Blast radius
  MEASURED, not assumed: cache prefix, compiled views and database
  byte-identical with/without the token outside the parallel runner.
  Consequence for process: concurrent sessions are now structurally safe
  against test-storage interference — hold discipline for TEST runs is
  retired; tree/commit hygiene rules stand.

## shared-index-entanglement · Targeted adds don't protect commits in a shared tree

*(Registered 2026-08-03; found by the fix-batch session's flag, cause owned
by the orchestrator.)*

- **Cause:** `git add <paths>` discipline protects against another writer's
  *unstaged* files, but `git commit -m` commits the whole index — another
  session's already-STAGED files ride along silently. Two writers, one
  index.
- **Evidence (ACTUAL):** `11afc21` ("co-created widget and governance
  principle sets", a docs commit) swept the fix-batch session's 18 staged
  Q7 files (the preload-default inversion in `AppServiceProvider`, ~30
  per-site `preload(false)` opt-out removals, cap-test additions). Nothing
  lost — the full suite passed on exactly that state — but the docs-labelled
  commit carries unmentioned app-behavior changes (`provenance-stated`
  breach, recorded below). Ruling: leave-and-record; no history surgery in
  the shared live tree (hash citations everywhere, active sessions).
- **Where else:** every orchestrator/delegate commit in the shared tree.
- **Suggested guard (adopted immediately):** commit with an explicit
  pathspec — `git commit <paths> -m …` — which commits only those paths
  regardless of index state; inspect `git status` staged section when in
  doubt.
- **Sibling sighting (2026-08-04, hygiene session, ACTUAL):**
  `pint --dirty` swept another session's in-flight blade file (the
  focus-fix work in `media-picker-panel.blade.php`) — `--dirty`-scoped
  tools sweep other sessions' dirty files exactly like a bare commit
  sweeps the index. Edit content was intact (formatting-only fixer); no
  foreign file was committed. Guard extension: in a multi-session tree,
  run pint with explicit pathspecs (`vendor/bin/pint <files>`).
  **Registered tension:** the FilaCheck-Pro guideline prescribes
  `--fix --dirty`, assuming a single-writer tree — guideline amendment
  queued for the next docs pass.
- **Status:** instance recorded; practice adopted. **Provenance register:**
  `11afc21` additionally carries the Q7 code batch; `ce23313` (opt-in-wins
  pin test) is the fix-batch session's. The handoff commit table states
  this at the docs fold.

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
| B1 | Alpine hover crosshair + tooltip on SVG sparklines | ✅ `168a618` (hover layer) + `87aa8bf` (browser evidence) + `cbee0d3` (docs). **Orchestrator-verified 2026-08-03: pest 1594/19,614 direct run (identical to claim), pint --test pass, full filacheck 0, tree clean** |
| Naming | Retire bare P-number ids: pattern slugs + aliases, ES- prefix for design principles, convention block | ✅ `27c5f3b` |
| Scan-scope research | Tailwind source-scanning research → policy + guard recommendation (`unscanned-home` follow-through) | ✅ merged 2026-08-03 — deliverable `57027b1`; found 2 ACTUAL live public-styling gaps + 2 POTENTIAL + the FilaCheck blind-spot proof; policy chosen: targeted globs hardened by a discovery guard |
| Scan-scope fix | Glob additions per settled scope + build + sentinel checks + `ThemeScanScopeTest` discovery guard — ships the public-prose fix with the route-end push | ✅ landed: `12285a7` + `ecc9eda` (chip `task_e10a5070`). Claimed gate: full pest 1608/19,719 (second run clean; first run's 2 failures were the registered Storage-listing flake, isolated 4/4), sentinels 6/6, pint, filacheck 0, build ok — **Orchestrator-verified 2026-08-03 (combined run at route end): pest 1608/19,724 zero failures, pint --test pass, full filacheck 0, build ✓, sentinels 6/6 after fresh build.** **Scope decided 2026-08-03:** enums = symmetric superset in BOTH themes (directory partition registered as future refinement, by-directory-only if ever); dead `app.css` KEPT as-is (future seat); badge-home split = glob fix only (one-home cleanup registered as candidate); guard = discovery test + on-demand compiled-output sentinels |
| Principles docs | Co-created widget principles (14, merged ES + route-earned) + dashboard governance principles (7) with pointers and ledger shrink | ✅ 2026-08-03, this commit |
| Ledger research | Dedicated read-only bad-practices hunt (skills + guidelines + FilaCheck catalogue), report-only | ✅ merged 2026-08-03 — 7 findings + flags curated: decorative-cap/service-hop-cost/client-payload promoted, unrouted-enum gained two tier-variant sightings, raw-state public-tier all-clear, implicit-keys queryStringIdentifier grep retired, security battery zero ACTUAL. Coverage gaps it declared: ~74/89 Blade views grep-only, importer connector internals, browser-test contents |
| Operator decisions | **Decided 2026-08-03:** Q2 per-failed-import · Q3 podcast filter hidden on Intake · Q4 **overruled — phase 3 adds a minimal read-only imports listing** · Q6 warnings widget stays on both lenses · **Q7 INVERT the global Select preload default** (bounded sets opt in; scope-extended to the running fix-batch session). **Q5 RESOLVED 2026-08-03:** the dossier principles were recovered from the phase-1 session transcript and archived into the combined plan; no conflict with the built board. **Q1 DECIDED 2026-08-03, mechanism OVERRIDDEN by operator ruling 2026-08-04 (in the re-reconciliation session):** source is *process-decided, never user-declared* — no modal source select; `StampImportSource` stamps `provider='manual'` unconditionally (the Filament import modal IS the manual process), the FK stays WB-reserved, and the user's only control is an optional custom import `name`; `google_drive`/`spotify` scopes are honestly empty AND user-unreachable until WB stamps real rows. Task renamed `imports-provider-stamp` (old slug aliased); amendment landing on the session's GO. **ES-1 operator qualifier registered** in the combined plan: a doorway-less number ships rather than being dropped when no filterable surface exists yet. **SettingsBackupPolicy DECIDED + LANDED 2026-08-03:** the fix-batch session had already created the policy at admin level (`73e4c17`); the operator ruled delete is **super-admin only for now** — flipped watched-red by the orchestrator (policy `delete`/`deleteAny` → `hasRoleAtLeast(SuperAdmin)`, table action hidden from plain admins and proven working for super-admins). No operator decisions remain open | 🟡 updated 2026-08-03 |
| Post-B1 fix batch | decorative-cap rewire · service-hop-cost fix (105→3 queries/page claimed) · one-home stragglers · unrouted-enum sweep (37 enums) + page-tier policy guard · **Q7 preload-default inversion (scope-extended)** | 🟡 landed: `abd46f3`, `c129f5f`, `9a761a2`, `73e4c17`, Q7 riding `11afc21` (see `shared-index-entanglement`), `ce23313`. Claimed gate 1605/19,661. **Orchestrator-verified 2026-08-03 at `ce23313`: pest 1605/19,663 direct run, pint --test pass, full filacheck 0.** **Hardening round (operator-directed, same session) landed after:** `7768442` (SettingsBackupPolicy, admin-level) + `5da7acc` (missing-category one home + filter↔badge parity pin) + `f494f0a` (two type-homes: `MediaMutationRepairResult`/`SettingsImportRowOutcome`; inert optionsLimit dropped; relation-manager language filter scoped to owner; slide_over vocabulary documented-only — spelling change = data migration) + orchestrator `ce95a35` (super-admin ruling flip, watched red). Round's claimed gate: 1608/19,696 with ONE standalone-green browser timeout, run on a tree mixed with the scan-scope session's uncommitted work — **Orchestrator-verified 2026-08-03 in the combined route-end run (pest 1608/19,724 zero failures — no flake reproduced; both claims arbitrated).** Sweep judgment calls RATIFIED by the operator 2026-08-03 (snapshot shape keys stay literal; stats-widget card ids stay widget-own; no app-wide literal ban inside the batch) — fold as settled |
| Deferred register | one-home app-wide format/colour sweeps (parked, budgeted separately) · M2 upstream Filament report (worth filing, unfiled — M2 brief/handoff) · phase-3 plan reconciliation (after B1) · 1/30 Storage-listing timeout (flake-label watch) · client-payload wizard architecture (watch-tier, out of dashboard scope) · `single-read-race` sweep (trigger: a second sighting; recon scope = 6 browser files × 15 `x-show` views, unchecked) · *(promoted to the post-route round 2026-08-04: contention investigation, `test-residue` census, `single-read-race` sweep — see the round table)* · *(media-picker focus-restore fix: DONE `914de1c` — operator directed it in-session; see `two-writer-channel`)* · **app-wide enum-literal ban guard** (operator-approved 2026-08-03; what = repo-wide enum-literal drift guard; why = guard-widening parked during the batch, now approved; unblocks = operator declares the dashboard program done/OK. **First chip run consumed 2026-08-03 without starting** — the session verified its timing gate against route state, found it closed (verification/fold/reconciliation ahead of the push; phases 3–4 open), and stood down producing nothing; a FRESH launch with the same constraints — homonym surface, UiFormatsPolicyTest/A2 precedents, statement-scan rule, ratified exceptions, mutation-check, no push — is needed at unblock time) · M2 upstream Filament report: IN MOTION, **do not file yet** (located 2026-08-03: the original M2 session built a pinned-stack reproduction scaffold that does NOT reproduce yet — the batched-message race is necessary but not sufficient; missing ingredient hypothesis = the live child sitting outside the replaced partial container, three ranked candidates, ~one more focused session, state saved in that session's `REPRO.md`; a healing repro would be closed unread) · governance globalization (operator ruling: dashboard-only for now; wider adoption is its own future thinking and plan) · enum theme-scope partition (operator: symmetric superset now; partition later only if size matters, by directory so globs/guard can see it) · badge-home split (`ShowContentItem` base literals vs `PublicItemPageRegistry` maps — one-home cleanup candidate once globs land) · dead `resources/css/app.css` entry kept as future non-panel seat (operator ruling) · admin-defaulting `User::factory()` — now THREE bites (plan fixture `f0ac4df`; and in `bdc0b78` a PRE-EXISTING `ImportExportTest` owner-boundary fixture whose 403 expectation the new policy legitimately changed — moved to plain role): the trap spans plan-authored AND pre-existing tests. Census trail `grep -rn "actingAs(User::factory()->create())" tests` in auth-flavored tests; the STRUCTURAL fix candidate — flip the factory default to the plain role and opt admin fixtures in explicitly — is an OPERATOR DECISION (repo-wide test-assumption change), queued for the next decisions round · *(`event:list` census: DONE clean + standing guard landed — see `double-registration`)* · phase-3 Task-1 watch: legacy-alias morph rows never enter `mediaEvents()` (FQN-first map; fixture matches today's FQN writes; POTENTIAL only if legacy rows exist) · research watch items: `embed_provider` full-table `distinct` per render (`ContentItemsTable.php:173`), 9 enums without Filament contracts (E4 residual), Blade string-icon duplication, importer authz panel-only | 📌 standing register per the registration discipline |
| Phase-3 re-plan | Board 3 researched and planned fresh against locked decisions | 🟡 plan landed (`7183996`) but ran pre-A/B (orchestrator sequencing error — "after push" followed literally once the push moved mid-route); held as DRAFT, reconciliation pass against landed A/B patterns at route end |
| Docs | Refresh 2R-handoff commit table + gate; current-project-state Prompt-13 row; fold flags | ✅ FULL fold 2026-08-03 at route end: handoff block map + `11afc21` provenance note + gate; six session reports folded; project-state row rewritten; phase-3 plan reconciled (`35aa226`) |
| Push gate | Full pest/pint/filacheck/build; push ONLY on operator's word (deploys production) | ✅ mid-route push 2026-08-03: pest 1563/19,386, full filacheck 0, build ok; pushed pinned `987b92f`; Forge release `74621206` = `987b92f`, `/up` 200. **Route-end push DONE 2026-08-03:** `987b92f..64479eb` (59 commits), release `74682025`, prose/badge cure verified live. **Post-route push DONE 2026-08-04 on the operator's word, executed by the phase-3 session:** `64479eb..dabb70d` (52 commits — Board 3 complete, both investigation fixes, hygiene conversions, listener guard, all registers + the verification stamp at the tip). **Orchestrator-verified independently:** production REVISION = `dabb70d` exactly, `/up` 200, `add_provider_to_imports_table` [41] Ran (81 migrations total). Phase-4 plan AUTHORED (`1dc8679`, local docs-only): decision-10's four pairs across independent query implementations with per-assertion mutation checks and `expectation-from-home`/vacuous-assertSee guarded; D-3 intake extension with HONEST inequalities (flagged ≤ sum — reasons overlap) **flagged as beyond decision 10's literal scope, implemented-unless-overruled (operator window open until Task 3)**; `rtl-board` on-demand group via the sentinels exclusion mechanism with the contention verdict folded (run alone); gate + Prompt-13-COMPLETE docs. Fixture-level claims verified against HEAD per `planned-fixture-drift`; green-first-run honesty stated (pins, not TDD theater — any red is a finding). Evidence-labels audit applied retroactively (`ea22fae`) — five UNVERIFIED hypotheses labelled with resolution paths; the audit caught one real plan bug (float-points cast). **Orchestrator sequencing: GO issued 2026-08-04, same session implements.** Tasks 1–2 LANDED (`55e5a22`, `3d5af8a`): `DashboardConsistencyTest` 4/4 (27 asserts), **ZERO decision-10 findings — all four pairs reconcile green-first**; labelled hypothesis 1 (queue population) RESOLVED by reading `queueQuery` (the doc-inference held); six mutations all killing after two authoring-tier corrections (curated under the mutation doctrine). Task 3 released 2026-08-05, no operator overrule received. **PHASE 4 COMPLETE — PROMPT 13 CLOSED, orchestrator-verified 2026-08-05: pest 1658/19,971 identical, zero failures; pint --test pass; FULL filacheck 0; `rtl-board` reproduced alone 1/10 green with the default-targeting exclusion confirmed (0 tests found); tree clean, no interleaves.** `9426dc6` (intake reconciliation + range-switch/legend pins, zero findings), `a62fe38` (`rtl-board` group ×4 alone, exclusion verified both directions), `86d2eca` (Prompt-13-COMPLETE state docs). Session gate: pest 1658/19,971, pint, FULL filacheck 0. Requirements classification: all decision-10 pairs, D-3, range/legend, RTL (decisions 6/9) — DONE. Remaining Prompt-13 scope: **none**. Local stack awaits the operator's push word |

## Post-route round (2026-08-04)

Operator-directed follow-on work after the route-end push (`64479eb`,
release `74682025`). **Sequencing (delegated to the orchestrator 2026-08-04; TIGHTENED same
day on operator observation):** contention investigation alone has GO —
ALL three peers hold for its collection window (the docs-only background
allowance is withdrawn; an unmeasured "negligible" has no place in a
timing experiment). Release authority: explicit GO from orchestrator or
operator after collection-complete. **Process lesson (registered):
cross-session orders are TURN-GRANULAR** — a queued HOLD lands only at
the target's next turn boundary, proven when hygiene's `a14d50a`
(card-preview fixture renames, item-1 partial — target suites
unaffected, investigation told to verify) landed after the first hold;
for hard freezes the operator's own pause is the instant lever, and the
investigation is instructed to trust its own quiet-checks over order
timestamps. Refinement (same day): chip sessions typically execute their
ENTIRE task as one turn, so a queued hold can land only after the task is
done — holds are effectively advisory for single-turn chip sessions; the
experiment survived because its own gate checks and target md5s did the
real protecting.

| Item | What | Status |
|---|---|---|
| Browser-test hygiene | `test-residue` census + `single-read-race` sweep + labelled-wait retrofit | ✅ **CLOSED** — `a14d50a` (residue renames), `171deeb` (single-read conversion), `af33122` (settle split + defect-marked focus wait), `83e8fef` (engage split, offline/reconnect/keyboard-nav labels). No flagged candidates remain (CardTemplatePreview presence-polls noted as later file-wide candidates). Its 2c gate's mixed-tree caveat is superseded by the orchestrator's clean full-suite reproduction (1615/19,741). Incident curated as the pint-dirty sibling under `shared-index-entanglement` |
| Contention investigation | Mechanism hunt for the three timeout data points | ✅ **DONE end-to-end, both defects fixed:** `fake-root-purge` proven (108 runs) + fixed (`a3fa4f2`, verified identical 1612/19,734); residual diagnosed AND fixed (`914de1c` two-phase verified focus restore, operator-directed; pattern enriched with the succeed-then-lose shape + focus-trap mover); deliverable + docs landed (`9318e62`, `4e7c68f`). Final claim 1615/19,741 — **orchestrator-reproduced identical**. **CLOSED** after the sweep: raw run tables (123+ runs incl. fix-verification arms) archived as `docs/research/browser-timeout-contention-run-log.md` (`b0f9bb1`), repairing the main doc's would-have-dangled scratchpad pointer (`readable-binding` in practice); throwaway probes deliberately deleted, durable form is the landed regression test |
| Phase-3 fresh re-reconciliation → **IMPLEMENTATION** | Board 3 built end-to-end from the re-reconciled plan | ✅ **COMPLETE 2026-08-04** — 12 commits: `e2010b8` T1 · `9b494a4` T2 (+ the `event-halting-return` production fix) · `f0ac4df` T3 · `55a30b6` T4 · `d353482` T5 · `1e64467` T6 · `90b7fb9` T7 (floor 11) · `0e80cc0` T8 · `6e6a03a` provider-stamp · `c399eaa` imports listing · `bdc0b78` gate fold (central nav-map guard caught the missing ImportResource entry — `whole-set-contracts` in action; + admin-factory bite #3) · `44e999c` state docs. Session gate: pest 1651/19,931, pint, FULL filacheck 0, build ✓, chip classes in compiled theme, tree clean. TDD watched-red throughout; 12 mutation checks, 4 survivals each run to ground truth. **Orchestrator-verified 2026-08-04: pest 1651/19,931 identical, zero failures; pint --test pass; FULL filacheck 0; build ✓; sentinels 6/6 post-fresh-build; tree clean, no interleaves.** Remaining Prompt-13 scope: phase 4 (on-demand RTL group) only. **DEPLOY NOTE: the next push's migrate step runs the imports migration (name/provider/FK, all nullable, no seed)** |
| Episodes/nav mini-project | Design for episodes as the main lens of content managing — one-place consolidation, list + create/edit scope | 🟡 spec landed (`641b429`, EQ-1..EQ-12); **operator continues MANUALLY in the design session** (EQ decisions + next steps there); orchestrator stands by to collect outcomes and curate |

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
