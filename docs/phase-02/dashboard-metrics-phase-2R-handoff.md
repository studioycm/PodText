# Prompt 13 Dashboard — Phase 2R Handoff and Phase 3 Restart Brief

The authoritative state of the dashboard work. Written to let a fresh session
start phase 3 with no tails left behind it.

**Read this before anything else in this folder.** Where it contradicts an
earlier dashboard doc, this document wins and the earlier one is historical.

## Reading order for a fresh session

1. This document.
2. `dashboard-metrics-combined-ux-plan.md` — the design, including
   **"Locked operator decisions (2026-07-31, round 2)"**, which supersede the
   prose above it in that file.
3. `dashboard-metrics-phase-1-2-remediation-audit.md` — what phases 1 and 2 got
   wrong and how it was fixed. Its findings are all closed except E1/E3.
4. `dashboard-metrics-filawidgets-adoption-analysis.md` — why the package was
   adopted and then removed. Ends with the in-house replacement table.
5. `dashboard-metrics-phase-1-handoff.md`, `dashboard-metrics-phase-2-handoff.md`
   — historical; several of their statements were corrected by phase 2R and are
   flagged below.

## Commit state

Nine commits on `main`, **none pushed**. `origin/main` is still at `7bba038`
(phase 1). Auto-deploy is on, so pushing deploys production; the plan is to push
once, immediately before phase 3 begins.

| Commit | What |
|---|---|
| `894870e` | Board 1 — the Overview lens (phase 2) |
| `ce15d96` | A4: derive the day an episode *became* visible |
| `e2967e4` | In-house data objects; filawidgets removed; loading skeleton |
| `dc0cd7a` | `FunnelStage` + `DashboardReason` enums (E1/E2) |
| `4bd4030` | `DashboardTier`, `HasDescription`, three more colour drifts (E3) |
| `44219ad` | UI timezone consolidation |
| `86f7e85` | Timezone name parameterised in admin helper prose |

(Two further commits in that range are the docs commits `f417323` and this one.)

**Last verified gate**, run directly rather than taken on report:
pest **1,512 passed / 19,201 assertions**, pint passed, filacheck **0 issues**,
`npm run build` ok.

## Decisions ledger

Round 2 decisions live in the plan. These are the ones taken *after* it, during
phase 2R, and they are binding:

1. **Blocked is two tiers**, never merged. `DashboardTier::Invisible` (no
   published transcript, or podcast not published) and
   `DashboardTier::Attention` (no media, no category). A needs-attention episode
   may be publicly visible; no copy may call it invisible.
2. **H7 shows two burn-down bars.** Only the invisible tier carries a forecast —
   the category pivot has no timestamps, so pacing needs-attention work would be
   invented.
3. **filawidgets is removed.** Five in-house value objects under
   `App\Support\Dashboard\Data` replace its DTOs. Kept from it: one value object
   per payload, `toArray()` for cache safety, previous-period as a first-class
   field.
4. **Chart interactivity: option A now, option B when suitable, option C later**
   (after WB and other priorities). Our SVG stays ours; animation is a later CSS
   addition, not a rewrite.
5. **The legend scopes the flow widgets only** — stream and heatmap. Stock
   widgets keep their totals.
6. **The metrics cache is invalidated on editorial writes**; the 60s TTL is a
   backstop.
7. **Formats get a localization home** (new, 2026-08-01): timezone, date formats
   and number formats belong together beside `App\Support\UiTimezone`, not in a
   dashboard-only formatter. This reshapes F1 — see the queue.
8. **`StreamEventType` enum will be created** (new, 2026-08-01), covering
   transcription / import / media / submission with `HasLabel`+`HasColor`+`HasIcon`.
   Phase 3's work queue needs the same types, so it lands there.
9. **Push once, immediately before phase 3.**

## Contracts phase 3 must not redefine

- **Visible** = the full `ContentItem::scopePublished()` contract.
- **Invisible** = status-published minus visible, *exactly* — which is why
  `unpublished_group` had to be added as a fourth reason.
- **Tier ownership is inverted**: a reason declares `tier()` once;
  `DashboardTier::reasons()` and `DashboardReason::hidesFromPublic()` both
  derive from it. Adding a reason means answering one question.
- **Every number goes through `EditorialMetrics`.** No fresh query in a widget.
- **Day bucketing is Jerusalem walls computed in PHP** (`JerusalemDailySeries`),
  never SQL `DATE()`, which would use the database timezone and differ between
  production MySQL and the SQLite test database.
- **A value is only safe from drift once every call site reads it from the
  enum.** An enum sitting next to hand-written values protects nothing — this
  was proven three separate times this session.
- **No polling**; `$pollingInterval = null` on every widget.
- **The UI timezone comes from `UiTimezone::name()`**, never a literal.
- Day-first dates; translation keys in both `lang/en` and `lang/he`; time axes
  render LTR inside the RTL board.

## Open queue

**E4** · The 12 enums implementing no Filament contract, scoped to those that
actually render in Filament UI. `ExternalImageFailureReason` and
`MediaAcquisitionDisposition` matter for Board 3.

**F1** · The localization home (decision 7). Owns the UI timezone alongside date
and number formats. `Illuminate\Support\Number` rather than `number_format()`,
which currently ignores the Hebrew locale. **Plus the anti-drift guard** — see
the trap below. ~2–2.5 h.

**F2** · Adopt it across widgets, views and DTOs. ~1–1.5 h.

**F3** · "Group other" bucketing in breakdowns. Confirmed a correctness item —
we have more than six podcasts and `take($limit)` silently drops the tail, which
the tooling guideline's no-silent-caps rule forbids. `BreakdownRow::meta` can
carry what an "Other" row rolled up. ~45 min.

**A1** · Sparkline normalisation. Ours normalises against the peak only, so
`[8, 9, 10]` renders almost flat. Normalise across `max − min` with a padding
inset. **This is a defect, not polish** — every sparkline currently understates
its own variation.

**A2** · Trend-coloured stroke (up/down/neutral). `SeriesRow::delta()` already
provides the trend.

**A3** · Panel-native empty states and `x-filament::link` doorways.

**B1** · Alpine hover crosshair and tooltip on our SVG. ~4–6 h.

**Then push, then phase 3.**

### The anti-drift guard, and its trap

The timezone work established the right pattern: a test that scans source and
asserts **zero** inline occurrences, so a consolidation cannot quietly regress.
Two traps when copying it:

- **Scope.** Date formats are 10 in dashboard paths but **52 app-wide**; raw
  Tailwind colour classes are 49 in dashboard paths but **218 app-wide**. A
  guard written to the timezone guard's app-wide shape fails instantly. Scope
  the guard to dashboard paths, and treat the app-wide sweep as separate,
  deliberately budgeted work.
- **Allow-list.** Most of those 49 dashboard colour occurrences now live *inside*
  `FunnelStage`, `DashboardReason` and `DashboardTier` — which is exactly where
  they belong. The guard must permit one home and forbid everywhere else, the
  way the timezone guard permits `config/localization.php`.

## Findings raised, and where they went

| Finding | Status |
|---|---|
| `blocked` merged two unrelated failure kinds | Fixed — two tiers |
| Nothing tracked "podcast not published" | Fixed — fourth reason |
| `EditorialMetrics::forget()` was dead code | Fixed — observer on editorial writes |
| Visible sparkline bucketed by the wrong day | Fixed — `becameVisibleAt()` |
| Legend filtered nothing | Fixed — scopes stream and heatmap |
| Stream lost its RecentPublishedItems columns | Fixed — podcast and status restored |
| Stream timestamps dropped the year | Fixed — full `d/m/Y H:i` |
| No widget declared `canView()` | Fixed — `AdminOnlyWidget` on all eight |
| Queue table shared the page's query-string keys | Fixed — `queryStringIdentifier` |
| `pageFilters` consumed unvalidated | Fixed — narrowed in `ReadsDashboardFilters` |
| Command bar overlapped on wide screens | Fixed — two grid rows, every breakpoint named |
| Stat card painted invisible `warning`, funnel `danger` | Fixed via `DashboardReason` |
| Three more colour drifts (funnel CTA, podcast health, gap band) | Fixed via `DashboardTier` |
| `Asia/Jerusalem` in 50 files, three duplicate constants | Fixed — `44219ad` |
| Timezone named in six helper-text strings | Fixed — `86f7e85` |
| Lazy widgets rendered as blank boxes | Fixed — loading skeleton |
| `ActivityStreamWidget` hand-writes event-type colours | **Open** — becomes `StreamEventType` in phase 3 |
| 12 enums implement no Filament contract | **Open** — E4 |
| Date/number formats scattered | **Open** — F1/F2 |
| Silent `take($limit)` in breakdowns | **Open** — F3 |
| Sparkline normalisation understates variation | **Open** — A1 |

## Still deferred, unchanged

- **`JerusalemDailySeries`'s name** hardcodes the policy in its identity. Left
  deliberately: the class exists *because* Jerusalem days are the contract, so
  the name documents rather than duplicates.
- **The 12 test files keeping a hardcoded timezone.** Correct, not a miss — they
  *assert* the policy; deriving it from `UiTimezone::name()` would make them pass
  against a wrong config.
- **H8 (expandable pulse row)** — still phase 2+; Livewire 4 islands are the
  mechanism when it arrives.
- **Livewire islands generally** — deferred until a problem exists that they
  solve. Filament 5 uses them nowhere; the board's six lazy hydration requests
  are the real load cost, and islands do not address that.
- **Option C (Chart.js)** — after WB and other priorities.
- **The editorial pulse as a separate widget** — folded into H1 by the design
  session's plan of record. Nothing was lost: episodes-added and
  transcripts-published are funnel sparklines, words-transcribed is in H9.

## Corrections to earlier docs

- `dashboard-metrics-phase-2-handoff.md` says filawidgets was adopted. It was,
  then removed — see the analysis doc.
- The same doc's "widgets paint progressively" note has the **wrong cause**.
  Filament's lazy widgets hydrate on *intersection*, so they fill in on first
  scroll; they are not slow. This was briefly mistaken for a regression.
- The phase-1 handoff's definition of blocked ("status-published minus visible")
  described something the code did not do. That is now true of
  `DashboardTier::Invisible` specifically, not of the queue population.

## Gotcha: `->options(Enum::class)` changes the state type

Passing an enum class to `->options()` is the idiom both Povilas Korop enum
videos recommend, and it is right for a table filter. It is **wrong for a field
whose backing property is a string**: Filament then hands the property an enum
instance, and a typed `string` property rejects it.

E4 hit this on `AdminUxSettings::$mediaNamingStrategy` — the suite caught it
immediately with `Cannot assign App\Enums\MediaNamingStrategy to property ...`.
Settings pages backed by Spatie settings classes are the exposed surface here.

The safe consolidation, used instead, keeps the state a string while still
having exactly one label definition:

```php
->options(fn (): array => collect(MediaNamingStrategy::cases())
    ->mapWithKeys(fn (MediaNamingStrategy $case): array => [$case->value => $case->getLabel()])
    ->all())
```

The duplication that mattered — the same translation-key mapping copy-pasted
across two files — is gone either way, because `getLabel()` is now the only
place a label is defined. Only the *shape* of the call site differs.

## Gotchas a phase-3 session will hit

- **Filament action-modal content is not in the Livewire HTML at `mountAction`
  time.** `assertSee()` cannot reach helper text or field prose inside a modal —
  77 KB of returned HTML contained none. Phase 3's repair actions are modal
  surfaces; assert at source level or via a rendered-form path instead.
- **Mutation-check render assertions.** A bare `assertSee()` can be satisfied by
  unrelated page content. Strip the implementation and confirm the specific
  assertion fails before trusting it.
- **A media browser test flakes** intermittently with "Multiple elements found
  for partial [action-modals]". Re-run before investigating; it appeared twice
  this session and was unrelated both times.
- **Never `git checkout` a file with uncommitted work in it** to undo a
  deliberate break — it takes the real work with it. Copy to a temp file instead.
- **Doorway query key is `filters`, not `tableFilters`** — the alias
  `ListRecords` declares. Passing `tableFilters` maps inconsistently.

## Why phase 3 must be re-planned from scratch

Board 3's brief predates every decision above. It should be researched and
planned fresh, because at minimum:

- The **sources filter** is now the provider enum (Spotify / Drive / manual).
- The **Spotify card echoes the connection test** (`status`, `last_tested_at`) —
  no persisted last-fetch record exists and none is being added.
- **Media findings show all six** `MediaDiagnosticReason` values with zero-count
  rows hidden, and that enum now carries colour and icon so the bars style
  themselves.
- The **work queue** is new submissions plus failed import rows with
  all/submissions/imports chips.
- The **range control is hidden** on Intake — nothing there is range-scoped.
- Board 3's bars should be built on `BreakdownRow` from the start, with the
  formatter and `StreamEventType` already in place, so they are not the next
  thing to retrofit.
- The FilamentExamples research protocol must run again for Board 3's own
  surfaces and be recorded in `docs/research/filament-examples-phase-02.md`.
