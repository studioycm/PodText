# filawidgets — Adoption Analysis After Actually Adopting It

Written after phase 2R implemented round-2 decision 2 (full adoption). The
decision was made on the plan's intent; this is what the code looks like now
that the intent has met the package.

## What we actually consume

The package ships **31 PHP classes** plus Blade views, console commands,
contracts and widget definitions. We import **five**, all of them readonly value
objects totalling **256 lines**:

`SparklineTableRowData`, `BreakdownItemData`, `HeatmapCalendarWidgetData`,
`ProgressWidgetData`, `CompletionRateWidgetData`.

We use none of its widget classes, none of its Blade views, none of its console
commands, not `WidgetDataCache` (we cache in `EditorialMetrics`), not
`WidgetValueFormatter`, not `WidgetMetricCalculator`, not `DateRangeFilter`, and
not `SparklineSeries` — which we rejected outright because it buckets days on the
database timezone.

That is roughly **16% of the package, and the 16% with no behaviour in it.**

## What adoption bought

- A field vocabulary we did not have to invent.
- `toArray()`/`fromArray()`, useful if payloads are ever cached as arrays.
- One genuine win: `SparklineTableRowData::$previousValue` prompted a
  previous-period delta on every funnel stage that the hand-rolled version
  lacked. That idea was worth having.

## What adoption cost — measured, not predicted

Three of the five adoptions did not fit and had to be bent:

| Adoption | How it bent |
|---|---|
| `podcastHealth()` | `previousValue` now means **"total published"**, not a previous period. A reader who trusts the field name will misread the number. The `percent` the widget needs was dropped from the payload and is recomputed in the Blade view. |
| `transcriberBoard()` | Returns `['item' => BreakdownItemData, 'words' => int]`, because the DTO has no field for a second measure. The package object is wrapped in the array it was meant to replace. |
| Funnel `visible` row | The DTO shape is fine, but the *day* had to be derived (`becameVisibleAt()`), so the package's series helper could not be used even in principle. |

Plus the structural costs: the DTOs are `readonly` with no interface, so we
cannot extend them to carry `percent`, `words` or a tier; a field rename in
`0.2.0` breaks `EditorialMetrics`; and the admin theme scans a `@source`
directory of views we will never render.

## The decisive question

Would we ever render with the package's widget classes? **No** — the plan
commits to our own Blade views for the RTL board, and that is not negotiable
given the layout, the LTR-inside-RTL axes and the doorway behaviour. The DTOs
exist to feed the package's views. Feeding *our* views with them means speaking
a dialect whose only native speaker is code we have chosen never to run.

## Recommendation: write our own, keep what it taught us

Not a rejection of the decision's spirit — the decision was right that the board
needed typed payloads instead of loose arrays. It is a correction of where those
types should come from.

1. Replace the five DTOs with in-house readonly value objects under
   `App\Support\Dashboard\Data`, carrying the fields the board actually needs:
   `percent`, `words`, `becameVisibleAt`, `tier`, per-entry URLs.
2. Keep the discipline the package taught: one value object per widget payload,
   `toArray()` for cache safety, previous-period values as a first-class field.
3. Keep `JerusalemDailySeries` exactly as it is — it already replaced the one
   piece of package behaviour we tried to use.
4. Remove the dependency and the theme `@source` line.

**Cost:** about one to two hours — `EditorialMetrics` (five methods), four Blade
views, one test file. **Benefit:** fields that mean what they say, freedom to add
computed helpers, and no upgrade coupling to a package we run none of.

**Cost of not doing it:** the `previousValue`-means-total mistranslation stays in
the code permanently, `transcriberBoard()` keeps its wrapper array, and every
package or Filament upgrade carries a rework budget the plan already priced at
about an hour.

## What would change the recommendation

If the board ever wants the package's rendered widgets — its JS sparklines, its
heatmap view, its progress bar markup — adopt the whole thing rather than its
data layer. Half-adoption is the one position with the costs of both.

## Outcome (2026-07-31): recommendation accepted

The dependency is removed. Five in-house value objects under
`App\Support\Dashboard\Data` replace it, each fixing something the package
could not express:

| In-house | Replaces | What it fixes |
|---|---|---|
| `SeriesRow` | `SparklineTableRowData` | Keeps the previous-period idea, adds `delta()` so no view recomputes it, and states that `value` is movement, never stock. |
| `BreakdownRow` | `BreakdownItemData` | Adds `of` (the whole) so `percent()`/`remainder()` are honest, and `meta` for a second measure — killing both the `previousValue`-means-total mistranslation and the transcriber-board wrapper array. `previous` now only ever means a period. |
| `Heatmap` | `HeatmapCalendarWidgetData` | Adds `cells()`, returning day, count, day-first label and shading level, so the view stops computing peaks. |
| `Burndown` | `ProgressWidgetData` | Speaks remaining-of-total, the domain's own direction, and carries the forecast as a `Carbon` so the view owns formatting. Forecast stays optional by design. |
| `Rate` | `CompletionRateWidgetData` | Keeps `covered` and `of` alongside the percentage — a percentage without its denominator is exactly what this board exists to avoid — and folds the threshold bands in. |

`JerusalemDailySeries` is unchanged; its docblock no longer references the
package it once worked around.

**What the package taught us, and we kept:** one value object per widget
payload rather than loose arrays, `toArray()` for cache safety, and a
first-class previous-period companion to every period figure. That last idea is
the reason each funnel stage now shows a delta at all.
