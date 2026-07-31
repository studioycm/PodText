# Prompt 13 Dashboard Metrics — Phase 2 Handoff

## Scope and baseline

Phase 2 of 4 of the combined dashboard design
(`docs/phase-02/dashboard-metrics-combined-ux-plan.md`): **the Overview lens,
Board 1**. Implementation started from clean `main` at `f417323` (the phase 1
docs commit), on top of phase 1's `7bba038`.

Phase 2 covers the whole of Board 1, the podcast scope filter, H6 legend
filtering, and the two partials phase 1 left open (H7's burn-down bar and
stock/flow tags). Phase 3 (Intake lens) and phase 4 (evidence) were not started.

## Two design questions settled before code

Both were raised with the operator, who referred them to the design session's
own plan of record.

1. **The editorial pulse (דופק עריכה) folds into H1** rather than shipping as a
   separate widget. The plan's phase-delta row for phase 2 lists H1, H5, the
   stream, the composition band with H3/H9, and H4/H6 wiring — no pulse — and
   H8 ("expandable pulse row") is marked *Phase 2+*. H1 is defined as
   "V1 funnel × pulse sparklines", so the funnel's per-stage micro-sparklines
   *are* the pulse. Nothing was dropped: episodes added and transcripts
   published are the draft and transcribed sparklines, and words transcribed
   lives in H9's transcriber board.
2. **Board 1 order is funnel-first**, matching "funnel as hero": context →
   funnel → composite cards → heatmap → stream → composition band. The stat
   cards moved down one slot from where phase 1 had them.

## Conformance with Filament's widget-filtering contract

Checked against <https://filamentphp.com/docs/5.x/widgets/overview#filtering-widget-data>.

- **`HasFiltersForm`, not `HasFiltersAction`.** The docs offer an action-modal
  alternative that validates before applying and frees vertical space. The
  design's two-row command bar (gap-filler G1) must be visible at all times and
  the lens pills must switch the board on click, so a modal with an Apply step
  is wrong here. The trade-off is that the data arrives unvalidated, which is
  handled below.
- **The docs' warning is load-bearing:** "this data is not validated, as it is
  available live … You must ensure that the data is valid before using it."
  Every read goes through `ReadsDashboardFilters`, which narrows each raw
  value: `range` through `DashboardRange::fromFilter()`, `podcast` through
  `EditorialMetrics::podcastExists()` (unknown ids fall back to the whole
  library), and `status` through a four-value allow-list (anything else is
  ignored rather than interpolated into a translation key). No widget reads
  `$this->pageFilters` directly.
- **`applyDashboardFilter()` allow-lists its key.** Livewire events can be
  dispatched from the browser, and `$filters` is both `#[Url]`-bound and
  session-persisted, so a write outside `lens`/`range`/`podcast`/`status` is
  dropped.
- **`$persistsFiltersInSession` is left at its default `true`.** Lens, range,
  podcast and legend focus survive a page load, which is what an editor wants
  from a dashboard. The active scope is always stated in the scope echo and the
  focused legend chip carries a ring, so a persisted filter is never silent.
- **Components are not wrapped in a `Section`.** The docs example uses one; the
  command bar is deliberately a bare row, and the filters schema already
  carries Filament's own responsive column config.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| H1 · living funnel as hero | Implemented | `PublicationFunnelWidget`, four stage cards with count, share bar, micro-sparkline and a per-stage focus button; the published→visible gap is a button into the blockers lens. |
| H5 · composite stat cards | Implemented | `EditorialStatsWidget` rebuilt from `StatsOverviewWidget` to an owned Blade card grid: five cards, each with a number, a mini composition strip, a written breakdown, and a filtered doorway. |
| Chip-filtered activity stream replacing any separate recent list | Implemented | `ActivityStreamWidget` with all/transcriptions/imports/media/submissions chips. No separate recent-published widget exists; "recent published episodes" is the transcription chip. |
| H4 · heatmap day click filters the stream | Implemented | `PublicationHeatmapWidget` dispatches `dashboard-day-selected`; the stream listens with `#[On]`. Clicking the active day clears it. |
| Library composition band with H3 and H9 | Implemented | `LibraryCompositionWidget`: six structure chips, then podcast health rows (visible/blocked split + percent) and the transcriber board (transcripts, words, delta vs previous period). |
| H9 = the spec's "transcriptions by author" | Implemented | `transcriberBoard()` reads the `author_transcription` pivot, the same relation the resource filters use, and each row exits to `TranscriptionResource` filtered to that transcriber. |
| H6 · legend chips write to `pageFilters` | Implemented | Chips call `selectStatus()`, which dispatches `dashboard-filter`; `Dashboard::applyDashboardFilter()` writes `filters.status` and toggles it off when the active chip is clicked again. Consumed by the funnel (stage highlight) and the scope echo. |
| H7 · burn-down bar in the queue header | Implemented | `queue-burndown` partial in the table description: "N of M published episodes still blocked", a progress bar, and the clearance forecast. Closes phase 1's partial. |
| Stock/flow header tags on every widget | Implemented | `stock-flow-tag` partial. Stock: funnel, cards, composition, gap. Flow: heatmap, stream. |
| Podcast scope filter | Implemented | `Select::make('podcast')`, searchable, no preload, `optionsLimit(50)`. Reaches every item-derived number and rides into Resource doorways as `filters[content_group_id][value]`. |
| Explicit distinct component keys | Implemented | Every `filtersForm()` component carries `->key('dashboardLens' \| 'dashboardRange' \| 'dashboardPodcast')` instead of falling back to its state path. |
| Distinct table query-string namespace | Implemented | `->queryStringIdentifier('blockersQueue')`. See the deviations section — this was a live collision, not hygiene. |
| No polling | Implemented | `$pollingInterval = null` on every widget; asserted per widget. |
| Day-first Jerusalem dates | Implemented | Heatmap tooltips `d/m/Y`, stream rows `d/m H:i` in `Asia/Jerusalem`, forecast `d/m/Y`. |
| Time axes render LTR inside the RTL board | Implemented | `dir="ltr"` on the sparkline SVGs, heatmap strip, every composition/progress bar, stream timestamps, and the delta column. |
| Translation keys, en + he | Implemented | `admin.dashboard.funnel/stats/heatmap/stream/composition/filters/queue.burndown/context.status_scope` in both files. |
| FilamentExamples research record | Implemented | Two new sections in `docs/research/filament-examples-phase-02.md`, including the query batches. Closes phase 1's recorded gap. |
| Schema, migrations, dependencies | Not applicable | None changed. One service-provider line (`scoped(EditorialMetrics::class)`) and one snapshot key (`structure.items`) were added. |
| Intake lens content | Not started | Phase 3. The lens still renders the Overview widget set. |
| RTL browser test, cross-widget consistency tests | Not started | Phase 4. |

## Files added

- `app/Filament/Widgets/Concerns/ReadsDashboardFilters.php` — shared reading of
  range/podcast/status plus `scopedTableFilters()` for doorways.
- `app/Filament/Widgets/PublicationFunnelWidget.php` + `resources/views/filament/widgets/publication-funnel.blade.php`
- `app/Filament/Widgets/PublicationHeatmapWidget.php` + `resources/views/filament/widgets/publication-heatmap.blade.php`
- `app/Filament/Widgets/ActivityStreamWidget.php` + `resources/views/filament/widgets/activity-stream.blade.php`
- `app/Filament/Widgets/LibraryCompositionWidget.php` + `resources/views/filament/widgets/library-composition.blade.php`
- `resources/views/filament/widgets/editorial-stats.blade.php`
- `resources/views/filament/widgets/partials/sparkline.blade.php`,
  `stock-flow-tag.blade.php`, `queue-burndown.blade.php`
- `tests/Feature/DashboardOverviewMetricsTest.php` (9 tests),
  `tests/Feature/DashboardOverviewLensTest.php` (12 tests)

## Files modified

- `app/Support/Dashboard/EditorialMetrics.php` — podcast-scoped `snapshot()`,
  `funnelSeries()`, `publicationHeatmap()`, `podcastHealth()`,
  `transcriberBoard()`, `activityStream()`, `blockersProgress()`,
  `podcastOptions()`, `structure.items`; cache key bumped to `v2` and keyed per
  podcast; `blockedQuery()` and `clearanceForecast()` take the scope.
- `app/Filament/Pages/Dashboard.php` — podcast Select, explicit keys,
  `#[On('dashboard-filter')]`, Board 1 widget order.
- `app/Filament/Widgets/EditorialStatsWidget.php` — rewritten as H5 cards.
- `app/Filament/Widgets/DashboardContextWidget.php` + view — clickable legend,
  podcast and status in the scope echo.
- `app/Filament/Widgets/PublicationGapWidget.php`,
  `app/Filament/Widgets/BlockersQueueWidget.php` — podcast scope, burn-down,
  query-string identifier.
- `app/Enums/DashboardRange.php` — `dayKeys()`.
- `app/Providers/AppServiceProvider.php` — `scoped(EditorialMetrics::class)`.
- `lang/en/admin.php`, `lang/he/admin.php`,
  `docs/research/filament-examples-phase-02.md`,
  `docs/phase-02/current-project-state.md`.

## Definitions this phase fixed

Phase 1's definitions stand unchanged. New ones phases 3–4 must not redefine:

- **Funnel movement** — each stage's sparkline is *movement into* that stage,
  not a running stock: draft = episodes created, published = publication dates
  of status-published episodes, transcribed = transcripts published, visible =
  publication dates of episodes the public can currently see.
- **Day bucketing is Jerusalem walls, computed in PHP.** `DashboardRange::dayKeys()`
  is the one day list; every series is aligned to it. Buckets are never grouped
  with SQL `DATE()`, which would use the database timezone and break across
  DST — and would differ between production MySQL and the SQLite test database.
- **The heatmap and the funnel's published series are the same numbers**, one
  as a calendar and one as a sparkline. Phase 4's consistency test can assert
  `array_values($heatmap) === $series['published']`.
- **Podcast scope reaches item-derived numbers only.** Podcasts, transcribers,
  categories and tags stay library-wide: the composition band describes the
  library, not the selected podcast. The scope echo states the active podcast.
- **A transcript with several transcribers counts in full for each of them** in
  H9, for both transcripts and words.
- **Doorway query key is `filters`, not `tableFilters`** — the alias
  `ListRecords` declares. Passing `tableFilters` to `getUrl()` mapped
  inconsistently between runs; `filters` is what the opened table reads.

## Verification

| Gate | Result |
|---|---|
| `php -d memory_limit=2G vendor/bin/pest --compact` | 1,483 tests / 19,028 assertions passed, exit 0. |
| `vendor/bin/pint --dirty --format agent` | Passed. |
| `vendor/bin/filacheck` | Pass, 0 issues. `--fix` was not run. |
| `npm run build` | Built, sequenced strictly outside the suite. |
| Browser visual check (Chrome, RTL, dark) | Both lenses checked at 1556px on `https://podtext.test/admin`: two-row command bar with no overlap, funnel with per-stage sparklines, composite cards, LTR heatmap strip, typed stream with day-first LTR timestamps, composition band with H3 and H9, and the H7 burn-down rendering inside the queue header. |

## Deviations and findings

1. **`EditorialStatsWidget` is no longer a `StatsOverviewWidget`.** H5's
   composition strip has no equivalent on `Stat`, so the widget became a plain
   `Widget` with an owned Blade card grid. This also removes the 5-second
   default poll at the source rather than suppressing it.
2. **A live query-string collision was found and fixed.** The Dashboard page
   binds `#[Url] public ?array $filters`, and an unidentified table widget
   defaults to the same bare `filters`/`search`/`sort`/`page` keys
   (`InteractsWithTable::getTableQueryStringIdentifier()` returns null). On the
   Blockers lens both were on one page. `->queryStringIdentifier('blockersQueue')`
   fixes it; a test asserts the names cannot collide.
3. **Blocked ≠ invisible — an unresolved contract conflict.** The phase-1
   handoff defines blocked as "status-published minus visible", but
   `blockedQuery()` implements "status-published carrying any of the three
   reasons". Missing media and missing category do not affect visibility, so a
   *visible* episode can also be counted blocked. Board 2 requires the three
   non-zero reason bars, so the code is the intended behavior and the
   definition sentence is what needs correcting — but the "Blocked episodes /
   published but not publicly visible" copy is currently wrong for two of the
   three reasons. Left for the operator to rule on; no behavior was changed.
4. **filawidgets ended up unused at runtime — decision 3 needs revisiting.**
   The package is installed, exact-pinned and theme-registered from phase 1, but
   phase 2 imports nothing from `LaravelDaily\FilaWidgets`. Two concrete reasons:
   `SparklineSeries::daily()` groups with SQL `DATE(column)`, i.e. database-timezone
   days, which contradicts the Jerusalem day contract and would differ between
   production MySQL and the SQLite test database; and `BreakdownItemData` cannot
   carry H3's visible/blocked/percent or H9's words/transcripts without dropping
   fields. The package's own key mechanisms (`$widgetLabel`, `$widgetCacheKey`,
   `WidgetDataCache::key()`) therefore do not apply here either. The operator
   should decide whether phase 3 uses the package, or whether the dependency is
   dropped.
5. **Chip and day state are widget-local, not URL-persisted.** A reload drops
   the stream's chip and the heatmap's selected day. Only the legend's status
   chip enters `pageFilters` (that is H6's scope). Accepted for phase 2.
6. **The command bar had to become two real grid rows.** The first build put
   all three controls in one schema row, and the grouped range ToggleButtons
   overflowed their cell and painted over the podcast select. Two things were
   needed: separate `Grid` rows (gap-filler G1 as written), and naming *every*
   breakpoint in `->columns()` — `getFiltersForm()` ships `md: 2, xl: 3, 2xl: 4`,
   and a bare `columns(1)` only overrides `default` and `lg`, so on wide screens
   the two rows still sat side by side. Verified in the browser, not just in
   tests: a Livewire render assertion cannot catch a CSS grid overflow.
7. **Widgets paint progressively on first load.** Each of the six Overview
   widgets is its own Livewire component, so the board fills in over a few
   seconds and shows Filament's placeholders until then. No polling is
   involved. Worth measuring in phase 4 if it feels slow with real volume.
8. **The Intake lens still renders the Overview widget set**, unchanged from
   phase 1. It now shows the full Board 1 rather than two widgets.

## Where phase 3 starts

Board 3 in the plan: work queue first (failed import rows, submissions), then
the Spotify connection card with its reduced-mode echo, then media by
diagnostic reason with each bar exiting into the gallery's needs-attention task.
Adds the sources filter to the command bar. Build against `EditorialMetrics` —
`activityStream()` already reads `Import`, `MediaAttachment` and
`PublicFormSubmission`, so the intake queue should extend that service rather
than query those models afresh.
