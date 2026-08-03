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
  allow-listed entry with its why; the SettingsBackupPolicy decision is on
  the operator board.
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
- **Sighting (2026-08-03, fix-batch hardening round, residual family):**
  `OwnerImageWorkspaceBrowserTest` "Hebrew RTL iPhone 15" hit one Playwright
  30s timeout during a FULL run executed while another session's uncommitted
  work sat in the shared tree (the scan-scope session's phpunit.xml/theme
  edits — not the round's diffs; stash-baseline discipline applied), then
  passed standalone 12/12. Second data point for the parallel-worker/
  concurrent-activity contention hypothesis (`local_51518218`-era).
- **Status:** V2 closed the owner-image mechanism sighting. The contention
  hypothesis now has TWO residual data points (the 1/30 Storage-listing
  timeout; this RTL timeout under concurrent tree activity) →
  investigation-eligible per the 2+ rule; registered in the deferred
  register (route-end+: reproduce browser-suite timeouts under induced
  concurrent tree activity vs a quiesced tree).

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
- **Status:** first instance fixed + pinned (`b9825c6`); research merged
  2026-08-03 — the **scan-scope fix chip** (four glob additions to the two
  themes, `npm run build`, the discovery guard, sentinel probes per operator
  choice) is queued to ride BEFORE the route-end push so the push ships the
  public-prose fix. The related dormant-arm note is resolved (`1efeb35`):
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
- **Status:** one sighting; registered, no sweep yet (2+ rule).

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
| Scan-scope fix | Glob additions per settled scope + build + sentinel checks + `ThemeScanScopeTest` discovery guard — ships the public-prose fix with the route-end push | ⏳ delegated (chip `task_e10a5070`). **Scope decided 2026-08-03:** enums = symmetric superset in BOTH themes (directory partition registered as future refinement, by-directory-only if ever); dead `app.css` KEPT as-is (future seat); badge-home split = glob fix only (one-home cleanup registered as candidate); guard = discovery test + on-demand compiled-output sentinels |
| Principles docs | Co-created widget principles (14, merged ES + route-earned) + dashboard governance principles (7) with pointers and ledger shrink | ✅ 2026-08-03, this commit |
| Ledger research | Dedicated read-only bad-practices hunt (skills + guidelines + FilaCheck catalogue), report-only | ✅ merged 2026-08-03 — 7 findings + flags curated: decorative-cap/service-hop-cost/client-payload promoted, unrouted-enum gained two tier-variant sightings, raw-state public-tier all-clear, implicit-keys queryStringIdentifier grep retired, security battery zero ACTUAL. Coverage gaps it declared: ~74/89 Blade views grep-only, importer connector internals, browser-test contents |
| Operator decisions | **Decided 2026-08-03:** Q2 per-failed-import · Q3 podcast filter hidden on Intake · Q4 **overruled — phase 3 adds a minimal read-only imports listing** · Q6 warnings widget stays on both lenses · **Q7 INVERT the global Select preload default** (bounded sets opt in; scope-extended to the running fix-batch session). **Q5 RESOLVED 2026-08-03:** the dossier principles were recovered from the phase-1 session transcript and archived into the combined plan; no conflict with the built board. **Q1 DECIDED 2026-08-03:** declare-at-upload now (provider column + modal source select, folded into the phase-3 reconciliation), fetch-run records in WB supersede later. **ES-1 operator qualifier registered** in the combined plan: a doorway-less number ships rather than being dropped when no filterable surface exists yet. **SettingsBackupPolicy DECIDED + LANDED 2026-08-03:** the fix-batch session had already created the policy at admin level (`73e4c17`); the operator ruled delete is **super-admin only for now** — flipped watched-red by the orchestrator (policy `delete`/`deleteAny` → `hasRoleAtLeast(SuperAdmin)`, table action hidden from plain admins and proven working for super-admins). No operator decisions remain open | 🟡 updated 2026-08-03 |
| Post-B1 fix batch | decorative-cap rewire · service-hop-cost fix (105→3 queries/page claimed) · one-home stragglers · unrouted-enum sweep (37 enums) + page-tier policy guard · **Q7 preload-default inversion (scope-extended)** | 🟡 landed: `abd46f3`, `c129f5f`, `9a761a2`, `73e4c17`, Q7 riding `11afc21` (see `shared-index-entanglement`), `ce23313`. Claimed gate 1605/19,661. **Orchestrator-verified 2026-08-03 at `ce23313`: pest 1605/19,663 direct run, pint --test pass, full filacheck 0.** **Hardening round (operator-directed, same session) landed after:** `7768442` (SettingsBackupPolicy, admin-level) + `5da7acc` (missing-category one home + filter↔badge parity pin) + `f494f0a` (two type-homes: `MediaMutationRepairResult`/`SettingsImportRowOutcome`; inert optionsLimit dropped; relation-manager language filter scoped to owner; slide_over vocabulary documented-only — spelling change = data migration) + orchestrator `ce95a35` (super-admin ruling flip, watched red). Round's claimed gate: 1608/19,696 with ONE standalone-green browser timeout, run on a tree mixed with the scan-scope session's uncommitted work — **verification folds into the single full-suite run at the combined HEAD when the scan-scope fix lands** |
| Deferred register | one-home app-wide format/colour sweeps (parked, budgeted separately) · M2 upstream Filament report (worth filing, unfiled — M2 brief/handoff) · phase-3 plan reconciliation (after B1) · 1/30 Storage-listing timeout (flake-label watch) · client-payload wizard architecture (watch-tier, out of dashboard scope) · `single-read-race` sweep (trigger: a second sighting; recon scope = 6 browser files × 15 `x-show` views, unchecked) · browser-timeout contention investigation (2 residual data points — the 1/30 Storage-listing timeout and the RTL timeout under concurrent tree activity; route-end+: induced-concurrency vs quiesced reproduction) · `test-residue` fixed-name census (one sighting; grep browser fixture names) · governance globalization (operator ruling: dashboard-only for now; wider adoption is its own future thinking and plan) · enum theme-scope partition (operator: symmetric superset now; partition later only if size matters, by directory so globs/guard can see it) · badge-home split (`ShowContentItem` base literals vs `PublicItemPageRegistry` maps — one-home cleanup candidate once globs land) · dead `resources/css/app.css` entry kept as future non-panel seat (operator ruling) · research watch items: `embed_provider` full-table `distinct` per render (`ContentItemsTable.php:173`), 9 enums without Filament contracts (E4 residual), Blade string-icon duplication, importer authz panel-only | 📌 standing register per the registration discipline |
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
