# Prompt 13 Dashboard — Phase 1 & 2 Remediation Audit

Written before phase 3, after the round-2 operator decisions
(`dashboard-metrics-combined-ux-plan.md`, "Locked operator decisions, round 2").

Phases 1 and 2 were built without those decisions and — more consequentially —
without ever using filawidgets, which the plan had costed in from the start.
This audit lists what is actually wrong or missing in the code, so the fixes
land before Board 3 rather than being retrofitted through three boards.

**State of the tree when this was written:** phase 2 is committed as `894870e`
and was gate-green. The blocked/attention split is *mid-implementation* on top
of it — `EditorialMetrics` is converted, the widgets and copy are not, and the
suite is red. Nothing here is pushed; auto-deploy is untouched.

## A · Contract and correctness

| | Finding | Severity | Status |
|---|---|---|---|
| A1 ✅ | **`blocked` merged two unrelated failure kinds.** Missing media and missing category never affected visibility, yet were counted and captioned as "published but not publicly visible". | High — the dashboard asserted something false | Fix in flight (round-2 decision 1) |
| A2 ✅ | **Nothing tracked "podcast not published".** `ContentItem::scopePublished()` requires a published group; `blockedQuery()` never checked it. A complete episode under a draft podcast was invisible to the public and appeared in **no metric and no queue**. `invisible` was therefore not equal to status-published minus visible. | High — a whole class of invisible episodes was unreportable | Fix in flight |
| A3 ✅ | **`EditorialMetrics::forget()` is dead code.** Nothing calls it. The only refresh is the 60-second TTL, and there is no way to force one after an editorial write. | Medium — numbers can contradict a change the editor just made | Open, needs a decision |
| A4 | **The funnel's `visible` series is a proxy.** It buckets currently-visible episodes by `published_at`, not by the day they became visible — an episode published in May whose transcript went live in July lands on the May cell. | Low — documented, but the sparkline is not what its label implies | Open, needs a decision |

## B · filawidgets — the locked decision was never implemented

The plan costed H1, H2, H5 and H7 as "package data layer, our own Blade views",
roughly three hours up front. **Neither phase used the package at all.** Every
widget invented its own array shapes. This is the largest single gap.

Round-2 decision 2 resolves the direction: adopt the package's *data contracts*,
reject its period math and `SparklineSeries::daily()` (both compute days on the
database/server timezone and are wrong for a Jerusalem-walls board), and supply
a Jerusalem-correct series helper producing the shape the DTOs expect.

| | Widget | Built as | Should be |
|---|---|---|---|
| B1 ✅ | H1 funnel sparklines | plain `array<int,int>` per stage | `SparklineTableRowData` (label, value, previousValue, sparkline, format) — also gives each stage a previous-period delta it does not have today |
| B2 ✅ | H2 gap gauge (phase 1) | own Blade bar; package gauge explicitly skipped | `CompletionRateWidgetData` + `getThresholds()` colours |
| B3 ✅ | H3 podcast health, H9 transcriber board | ad-hoc associative arrays | `BreakdownItemData` (label, value, previousValue, color, icon, url) |
| B4 ✅ | H4 heatmap | `array<string,int>` | `HeatmapCalendarWidgetData` — note it carries `entryUrls`, which is exactly H4's per-day click target |
| B5 ✅ | H7 burn-down | inline `<span>` markup | `ProgressWidgetData` (currentValue, goalValue, projectionValue, projectionLabel) — `projectionValue` is the clearance forecast the code already computes |
| B6 ✅ | Gap/attention reason bars, and Board 3's finding bars | ad-hoc arrays | `BreakdownItemData`, giving each bar the doorway URL the plan asks for |
| B7 ✅ | Daily series | `EditorialMetrics::dailyMap()`, correct but bespoke | Keep the Jerusalem logic, return the DTO-expected shape, and name it so the rejection of `SparklineSeries::daily()` is explicit rather than silent |

Adopting B1–B6 is mostly a change of return type plus view field names; the
queries and the Jerusalem bucketing stay exactly as they are.

## C · Plan and rule compliance

| | Finding | Severity |
|---|---|---|
| C1 ✅ | **H6 is display-only.** A legend chip writes `filters['status']`, which highlights a funnel segment and prints in the scope echo — but filters no data anywhere. Synthesis rule 1 ("the legend is the filter") and the hybrid's "one filter moves everything" are both unmet. Phase 1 recorded legend-as-filter as partial; phase 2 closed the *plumbing* and left the *effect* open. | High — a control that looks like a filter and is not |
| C2 ✅ | **The stream lost the RecentPublishedItems columns.** The honesty audit promised the stream filtered to transcriptions would show "exactly those columns": item title, group title, effective transcription date, status, admin link. It shows title, date and link only — no podcast, no status. | Medium — a spec requirement absorbed and then dropped |
| C3 ✅ | **Stream timestamps drop the year** (`d/m H:i`). The cross-cutting rule is `dd/mm/yyyy HH:mm`. Truncated for column width, which is not a licence the rule grants. | Medium — house locale rule violated |
| C4 ✅ | Gap reason colour for missing-category is `info`; the plan drew violet. | Low — cosmetic |

## D · Security and robustness

| | Finding | Severity |
|---|---|---|
| D1 ✅ | **No widget declares `canView()`.** Every dashboard widget is a registered Livewire component and relies solely on the panel's page-level guard (`User::canAccessPanel()` requires the Admin role). Exploitability is limited — Livewire needs a valid signed snapshot to update a component — but the guard belongs on the component, and the class returns editorial counts. | Low, cheap to close |
| D2 | Fixed this session, recorded for completeness: the queue table shared the page's bare `filters`/`search`/`sort`/`page` query keys; `pageFilters` was consumed unvalidated despite Filament's docs warning; command-bar components had implicit keys; the command bar overlapped on wide screens. | Closed |

## E · Evidence gaps

| | Finding |
|---|---|
| E1 | Phase 1 and 2 tests assert that widgets *render*, never that their numbers *agree*. All four consistency pairs are still unwritten (phase 4). |
| E2 ✅ | No test covered an episode under a draft podcast — which is why A2 survived two phases. Now covered. |
| E3 | No RTL evidence beyond this session's manual browser check (phase 4, own group). |

## Proposed remediation order

Everything below is phase 2R, before Board 3 is started.

1. **Finish A1 + A2** — the split, the fourth reason, two-tier burn-down, and all
   en/he copy. Already in flight.
2. **B1–B7 · filawidgets adoption**, while the widgets are open anyway. Doing it
   now means Board 3's finding bars are written against `BreakdownItemData`
   first time, instead of being the fourth ad-hoc shape.
3. **C1 · make the legend actually filter.** Decide the semantics (below) —
   this is the one item that needs an operator ruling, not just work.
4. **C2 + C3** — restore the stream's podcast and status columns, and the full
   `dd/mm/yyyy HH:mm`.
5. **D1** — `canView()` on every widget.
6. **A3** — wire `forget()` to editorial writes, or delete it.
7. **C4, A4** — cosmetic colour, and either relabel the visible sparkline or
   compute it honestly.

Phase 3 then builds Board 3 on a settled contract, and phase 4's consistency
tests have something stable to assert against.

## Resolution (2026-07-31)

Everything above except A4 and E1/E3 was fixed in phase 2R, on top of `894870e`:

- **A1, A2** — two tiers in `EditorialMetrics` (`gap`, `attention`), the fourth
  reason `unpublished_group`, `invisibleQuery()` / `attentionQuery()` /
  `queueQuery()`, two-tier `blockersProgress()`, and en/he copy that never calls
  a needs-attention episode invisible.
- **A3** — `EditorialMetricsCacheObserver` on `ContentItem`, `ContentGroup` and
  `Transcription`; the 60-second TTL stays as a backstop.
- **B1–B7** — `SparklineTableRowData` (funnel, now with a previous-period delta
  per stage), `BreakdownItemData` (podcast health, transcriber board, every
  reason bar), `HeatmapCalendarWidgetData`, `ProgressWidgetData` (both burn-down
  bars, forecast via `projectionLabel`), `CompletionRateWidgetData` (H2's rate).
  `App\Support\Dashboard\JerusalemDailySeries` replaces the package's
  timezone-wrong `SparklineSeries::daily()` while returning the shape the DTOs
  expect.
- **C1** — `EditorialMetrics::streamTypeForStatus()`; a legend chip narrows the
  stream, an explicit chip still wins.
- **C2, C3** — stream transcription rows carry podcast and status again, and
  timestamps are full `dd/mm/yyyy HH:mm`.
- **C4** — missing-category renders violet.
- **D1** — `AdminOnlyWidget` on all eight widgets.

**Still open:** A4 (the visible sparkline is a publication-date proxy), and
E1/E3, which are phase 4's job.

**Process note:** C1, D1 and A3 were implemented before their tests rather than
after a red run — a TDD lapse in an otherwise test-first pass. Their tests exist
and pass, but they did not earn a watched failure.
