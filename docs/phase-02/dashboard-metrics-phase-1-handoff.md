# Prompt 13 Dashboard Metrics — Phase 1 Handoff

## Scope and baseline

Phase 1 of 4 of the combined dashboard design
(`docs/phase-02/dashboard-metrics-combined-ux-plan.md`): **Foundation +
Blockers lens**. Implementation started from clean `main` at `f6529a0`
(filawidgets and laravel/doctor installation) and landed as `7bba038`, which
auto-deployed to production as release `74516766`.

Phase 1 covers the command bar, the metrics contract, the stats spine, the
context/scope echo, and the whole Blockers lens. Phases 2 (Overview lens),
3 (Intake lens) and 4 (evidence) were not started.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| Three-lens command bar (Overview / Blockers / Intake) | Implemented | `DashboardLens` enum + `HasFiltersForm` grouped `ToggleButtons`, default Overview. |
| Custom Jerusalem-aware range enum (7/30/60) | Implemented | `DashboardRange` computes day boundaries on Jerusalem walls and returns UTC instants; `currentPeriod()` / `previousPeriod()`. |
| G1 filler — inapplicable filters disappear | Implemented | The range toggle is `->visible()`-gated off the Blockers lens, not disabled. |
| G5 filler — one metrics service + scope echo | Implemented | `EditorialMetrics::snapshot()` is the only source of funnel/blocker/structure numbers; `DashboardContextWidget` echoes lens, range and snapshot time. |
| G6 filler — blockers-by-reason between funnel and queue | Implemented | `PublicationGapWidget` renders visible-vs-blocked, then per-reason bars. |
| H2 · reasoned gauge | Implemented as gap widget | Visible/blocked split plus the three reason bars in one owned Blade view. The package's CompletionRate gauge was not used; the same two questions are answered. |
| H7 · queue with a finish line | Partially implemented | The clearance forecast line ships (`clearanceForecast()`, 14-day published-transcription rate). The burn-down progress bar is **not** in the queue header — carry to phase 2. |
| Blockers queue with per-row exit to the fix surface | Implemented | `BlockersQueueWidget` table with reason badges and a `fix` record action into `ContentItemResource::getUrl('workspace')`. |
| Stats spine with Resource doorways | Implemented | Nine stats, each `->url()` to its Resource index. |
| No polling anywhere | Implemented | `protected ?string $pollingInterval = null;` on every widget, asserted by `assertDontSeeHtml('wire:poll')`. |
| Drop `FilamentInfoWidget` | Implemented | Removed from `AdminPanelProvider` widgets and its import deleted. |
| filawidgets theme registration | Implemented | `@source` line added to the admin theme; `npm run build` run. |
| Translation keys, en + he | Implemented | `admin.dashboard.*` block added to both `lang/en/admin.php` and `lang/he/admin.php`. |
| Day-first Asia/Jerusalem date presentation | Implemented | Queue `published_at` uses `dateTime('d/m/Y H:i', 'Asia/Jerusalem')`; the forecast uses `d/m/Y`. |
| Legend-as-filter (rule 1 / G2) | Partial — display only | The legend strip renders with funnel counts and links to the item index, but chips do not yet write to `pageFilters`. Real legend filtering is H6, phase 2. |
| Stock/flow header tags (rule 3 / G4) | Partial | The gap widget carries the *current state* tag and the keys exist for both tags; the stats spine and context widget do not display tags yet. Complete in phase 2 when flow widgets arrive. |
| Podcast/source scope filters | Not started | Phase 2 (Overview) and phase 3 (Intake). |
| H1, H3, H4, H5, H8, H9 | Not started | Phase 2 by the plan's phase delta. |
| Intake lens content | Not started | Phase 3. The lens value exists and currently renders the Overview widget set. |
| RTL browser test and cross-widget consistency tests | Not started | Phase 4. |
| FilamentExamples research record in `docs/research/filament-examples-phase-02.md` | **Gap** | The MCP research passes ran during the design stage but were never written into the tracked research doc, which the tooling guideline requires. Phase 2 should record them. |
| Schema, migrations, dependencies | Not applicable | None changed. Phase 1 is code, views, translations and one theme line only. |

## Files added

- `app/Enums/DashboardLens.php` — Overview / Blockers / Intake, `fromFilter()`
  defaulting to Overview, `options()`, `HasLabel` via `admin.dashboard.lenses.*`.
- `app/Enums/DashboardRange.php` — Last7/30/60Days, `days()`,
  `currentPeriod()` / `previousPeriod()` on Jerusalem walls returned as UTC.
- `app/Support/Dashboard/EditorialMetrics.php` — the single-source snapshot
  (60-second cache key `dashboard:editorial-metrics:v1`), `blockedQuery()`,
  `blockerReasonsFor()`, `applyReason()`, `clearanceForecast()`, `forget()`.
- `app/Filament/Widgets/DashboardContextWidget.php` + `resources/views/filament/widgets/dashboard-context.blade.php`
- `app/Filament/Widgets/EditorialStatsWidget.php`
- `app/Filament/Widgets/PublicationGapWidget.php` + `resources/views/filament/widgets/publication-gap.blade.php`
- `app/Filament/Widgets/BlockersQueueWidget.php`
- `tests/Feature/DashboardMetricsTest.php`

## Files modified

- `app/Filament/Pages/Dashboard.php` — `HasFiltersForm`, `filtersForm()`,
  `getWidgets()` delegating to `getWidgetsForLens()`.
- `app/Providers/Filament/AdminPanelProvider.php` — `FilamentInfoWidget` removed.
- `lang/en/admin.php`, `lang/he/admin.php` — `admin.dashboard.*`.
- `resources/css/filament/admin/theme.css` — filawidgets `@source`.
- `docs/phase-02/current-project-state.md` — Prompt 13 progress entry.

## Definitions this phase fixed

These are the contracts phases 2–4 must not silently redefine.

- **Visible** = the full `ContentItem::scopePublished()` contract: item status
  Published, `published_at` window open, owning group published, and at least
  one published transcription.
- **Status-published** = the item flag and date window only, ignoring group and
  transcription state. Computed separately inside `EditorialMetrics`.
- **Blocked** = status-published minus visible, resolved into three reasons:
  no published transcription; no media (`embed_url` **and** `media_url` both
  null or blank); no category (no own categories **and** no group categories).
  `media_url` is `NOT NULL` in the schema, so blank-not-null is the real-world
  case and the queries test for both.
- **פורסם ≠ גלוי** is never collapsed into one number, in any widget or copy.

## Verification

| Gate | Result |
|---|---|
| `php -d memory_limit=2G vendor/bin/pest --compact` | 1,464 tests / 18,948 assertions passed, exit 0 (no browser-sandbox flakes this run). |
| `vendor/bin/pint --dirty --format agent` | Passed. |
| `vendor/bin/filacheck` | Pass, 0 issues. `--fix` was not run. |
| `npm run build` | Built, before the suite (never concurrent). |
| Deploy | Release `74516766` = `7bba038`; `/up` 200; `/admin` 302 to login. |

`tests/Feature/DashboardMetricsTest.php` covers: guest redirect and admin 200 on
`/admin`; per-lens widget sets and the `fromFilter(null)` default; the snapshot's
funnel and blocker counts against a fixture of one visible, one blocked and one
draft item; queue inclusion/exclusion; absence of `wire:poll`; presence of the
Resource doorway URL; and the Jerusalem range periods including the fallback.

## Deviations from the plan

1. **FilaCheck forced a queue filter.** `missing-table-filters` fires on the
   reason badge column, so a reason `SelectFilter` was added and
   `EditorialMetrics::applyReason()` created to back it with the same conditions
   the counts use. This is additive and keeps rule 2 intact.
2. **H7's burn-down bar is missing**, as noted above — only the forecast line
   shipped.
3. **The Intake lens renders the Overview widget set** until phase 3 gives it
   content. It is reachable and does not error, but it is not yet its own board.

## Where phase 2 starts

Read `docs/phase-02/dashboard-metrics-combined-ux-plan.md` (Board 1 and the
phase delta), then build against the existing `EditorialMetrics` contract:
every new number goes through the snapshot or a new method on that service, not
a fresh query in a widget.
