# Prompt 13 Dashboard Metrics — Combined UX Design Plan

Leading document for the Prompt 13 dashboard. English is authoritative for the
plan; the Hebrew RTL mockups are the visual companions and stay as published
artifacts.

- Combined design (EN, this document's source): <https://claude.ai/code/artifact/f1f5e919-3650-41c8-aa91-a3a4c6e06fe4>
- Combined design (HE, RTL mockups): <https://claude.ai/code/artifact/0ffcbb76-8808-4156-a8b5-91d18f2e92b2>
- Six visual options (HE, RTL mockups): <https://claude.ai/code/artifact/d1f8f9b5-739c-4fe0-a2df-7d7d845df2a1>

Related tracked docs: `docs/phase-02/dashboard-metrics-spec.md`,
`docs/phase-02/blueprints/13-dashboard-metrics-blueprint.md`,
`.ai/settings-dashboard` guideline.

## What this design is

The union of two design passes — the three-lens structure
(Overview · Blockers · Intake) and the six visual presentations (V1–V6) — held
together by three synthesis rules, six gap-fillers, and nine hybrid widgets.

## Three synthesis rules

1. **The legend is the filter.** One enum-driven legend row serves every widget;
   clicking a legend chip filters the board. No per-widget legends.
2. **One number, one source.** Any figure appearing in more than one place
   (12 public: in the funnel, in a card, in a composition bar) reads from a
   single metrics service, so contradiction is impossible rather than merely
   unlikely.
3. **Stock or flow, declared.** Every widget carries a header tag —
   *current state* (range-immune) or *in range* (scoped to the selected window).
   This kills the question "what does the range actually affect?"

## Board 1 · Overview — funnel as hero, stream as the list

Lens tabs and range toggles; the legend row doubles as the filter; a scope echo
line ("showing: last 30 days · all podcasts · as of 08:41"). Then the
publication funnel (draft → published → transcribed → publicly visible, with the
published→visible gap clickable through to Blockers), the editorial pulse with
sparklines, the publication heatmap, the typed activity stream, and the library
composition band (structure counts + H3 + H9).

Mockup content, in order:

- Command bar row 1: lens pills סקירה / חסמים / קליטה; row 2: range pills
  7 / 30 / 60 ימים plus a podcast filter (`כל הפודקאסטים ▾`).
- Legend-filter strip: ● טיוטה 23 · ● פורסם 17 · ● מתומלל 14 · ● גלוי 12.
- Scope echo: `מציג: 30 ימים אחרונים · כל הפודקאסטים · נכון ל־08:41`.
- **משפך הפרסום** (publication funnel) — *current state*. Segmented bar
  23/17/14/12; caption: the published→visible gap (5) is clickable and moves to
  the blockers lens.
- **דופק עריכה** (editorial pulse) — *in range*. Rows: episodes added 14,
  transcriptions published 9, words transcribed 38,400 — each with a sparkline.
- **לוח פעילות פרסום** (publication heatmap) — *in range*. Day cells, LTR grid
  inside the RTL board.
- **זרם פעילות** (activity stream) — *in range*. Type chips
  הכול / תמלולים / ייבוא / מדיה / פניות, then rows of
  `event badge · linked title · dd/MM HH:mm`.
- **הרכב הספרייה** (library composition) — *current state*. Quiet count chips:
  podcasts 4, transcribers 6, categories 9, tags 15 / disabled 3, pinned 2,
  multi-transcription 3; plus H3 and H9.

## Board 2 · Blockers — from the gap to the row, through the reason

No range control by design — a blocker is a blocker at any age. The gap focus
shows published-vs-visible, then breaks the 5 down by reason (no public
transcription / no media / no category) before the queue lists the individual
rows. Each row exits directly into the surface that fixes it. The queue header
carries its own burn-down and clearance forecast.

Mockup content:

- Command bar: lens pills only, plus the podcast filter and the note
  `ללא טווח — חסם הוא חסם, בכל גיל`.
- **מוקד הפער: פורסם → גלוי** — *current state*. Bar גלוי 12 / חסומים 5, then
  reason bars: ללא תמלול פומבי 4 (danger), ללא מדיה 2 (warning),
  ללא קטגוריה 1 (violet).
- **צבירה עד ניקוי התור** — *current state*. Big number `5 נותרו מתוך 17`,
  progress bar, and `בקצב הנוכחי: פינוי עד 12/08/2026`.
- **תור החסמים** — table rows of title · podcast · reason badges · fix link;
  header note `כל שורה יוצאת למסך התיקון`.

## Board 3 · Intake — queue, breakdown, health

The work queue (failed import rows, new submissions) comes first, then the
Spotify connection card with its reduced-mode echo, then media by diagnostic
reason — each bar exiting into the gallery's needs-attention task pre-filtered
to that reason, where the R5 repair actions live.

Mockup content:

- Command bar: lens pills, range `30 ימים`, sources filter `כל המקורות ▾`.
- **תור טיפול** — *current state*. Rows: `ייבוא נכשל` → failed import row link;
  `פנייה` → submission link.
- **חיבור Spotify** — *current state*. Status `מחובר`, plus
  `שליפה אחרונה במצב מלא · 31/07/2026 07:55` (reduced-mode echo).
- **מדיה לפי ממצא** — *current state*. Bars: קובץ חסר 6, SVG לא מחוטא 4,
  זהות ניידת חסרה 2; caption: each bar exits into the gallery's
  «דורש טיפול» task filtered to that finding · 97% clean.

## Gaps the combination created — and the fillers

| Gap | Filler component |
|---|---|
| **G1 · Control collision.** Lens tabs + range + V6 filters — unclear what governs what. | **Two-row command bar:** row 1 lens navigation, row 2 contextual filters. A filter that doesn't apply to the active lens disappears rather than being disabled. |
| **G2 · Duplicate legends.** V1, V2 and V5 each arrived with their own. | **One legend-filter strip** under the bar: one enum → one color across the board, and the chips themselves filter. |
| **G3 · Two "recent" lists.** Recent visible episodes and the V4 stream overlap. | **Chip-filtered stream:** one list; "recent episodes" is the stream filtered to a single event type — spec columns preserved. |
| **G4 · What does the range affect?** Funnel and health are "now"; pulse and intake are "in period". | **Stock/flow tag** in every widget header. |
| **G5 · Same number, three places.** Silent drift between funnel, card and bar. | **One metrics service** (a contract, not just a cache) plus a **scope echo** line: "showing … · as of …". |
| **G6 · Altitude jump.** From the funnel's "5 blocked" straight to individual rows. | **Blockers-by-reason bars** between funnel and queue — answers "why 5" before "which 5". |

## Honesty audit: what the merge lost, and the repairs

| Lost | Repair |
|---|---|
| **Structural counts** — podcasts, transcribers, categories, enabled/disabled tags, pinned episodes, multi-transcription episodes. Spec-mandated; absent from both merged boards. | **Library composition band** at the bottom of Overview — quiet counts, each a filtered doorway, no charts. |
| **Spotify connection card** with reduced-mode echo — present in the original Intake mockup, dropped in the merge. | Restored to Intake. |
| **RecentPublishedItems spec columns** — absorbed into the stream. | The stream filtered to "transcription published" shows exactly those columns. |
| **"Transcriptions by author"** — an explicit spec requirement, wrongly deferred as a premature leaderboard. | Enters as **H9 · transcriber board**. The deferral was defensible on production volume only, never on the spec. |
| **Empty-state designs and principles P1–P7** — not restated, therefore at risk of quiet loss. | Declared binding for every widget's build spec. |

## Hybrid widgets — one component, two outcomes

Rather than placing one widget from each approach side by side, these merge both
functions. The technical key: the filawidgets data layer (`SparklineSeries`,
`BreakdownItemData`, period math) is usable inside views of our own.

| Hybrid | Merge | Both outcomes |
|---|---|---|
| **H1 · Living funnel** | V1 funnel × pulse sparklines | Each funnel segment carries a micro-sparkline of that stage's movement — "how many now" and "which way it's going" in one widget. |
| **H2 · Reasoned gauge** | CompletionRate × reason breakdown | Coverage percentage with the package's threshold colors, and beneath it the reason bars — "how much is missing" and "why" together. |
| **H3 · Podcast health breakdown** | BreakdownWidget × V3 scorecard | Breakdown rows (contribution + period delta) gain health cells (visible %, pending) — ranking and matrix at once. *In phase 2.* |
| **H4 · Navigating heatmap** | HeatmapCalendar × stream | Clicking a day filters the stream below it to that day — density as overview, events as detail, without leaving the board. |
| **H5 · Composite stat card** | Doorway cards × V2 composition | Under the number, a mini composition strip (draft/published/visible): count, composition and filtered doorway in one card. |
| **H6 · Legend drives the package** | V6 legend-filter × filawidgets | Status chips enter `pageFilters` alongside the range, so package widgets read them too — one filter moves everything. |
| **H7 · Queue with a finish line** | Blockers queue × ProgressWidget | The queue header carries the burn-down bar and clearance forecast; the bar disappears when the queue empties. |
| **H8 · Expandable pulse row** | SparklineTable × stream | Clicking a pulse row expands that metric's events inline — trend, then "what exactly". *Phase 2+; needs Livewire extension.* |
| **H9 · Transcriber board** | BreakdownWidget × the spec's "transcriptions by author" | Transcriber ranking in range (transcriptions + words, delta vs previous period) with a doorway to the contributor — closes a dropped spec requirement. *In phase 2.* |

## Phase delta

| Phase | Change from the original plan |
|---|---|
| **1 · Foundation + Blockers** | Adds the two-row bar, legend-filter strip, stock/flow tags and scope echo; H2 (gap focus + reasons) replaces the plain coverage gauge; the queue gains H7's finish line. |
| **2 · Overview** | H1 living funnel as hero; H5 composite cards; the filtered stream replaces the separate recent list; library composition band with H3 and H9; H4 and H6 wiring. |
| **3 · Intake** | Largely unchanged — V5's reason breakdown was already in; adds the sources filter and restores the connection card. |
| **4 · Evidence** | Adds tests for cross-widget number consistency, stock/flow behavior under range switching, legend-as-filter, plus the RTL browser test. |

## Locked operator decisions (2026-07-31)

1. **Three lens presets** — Overview / Blockers / Intake.
2. **Custom Jerusalem-aware range enum** rather than the package's range filter.
3. **filawidgets exact-pinned** (`0.1.2`) with an easy-bump note.
4. **Operations scope line** — the scope echo states lens, range and snapshot time.
5. **Drop `FilamentInfoWidget`** from the admin panel.
6. **RTL evidence via a browser test** (phase 4).
7. **H3 and H9 are IN, not deferred.** The volume-based deferral was overruled:
   local already has several podcasts and transcribers, and production volume
   alone is not a reason to drop a spec requirement.

## Locked operator decisions (2026-07-31, round 2)

Taken after phase 2 shipped, before phase 3 started. Where these contradict the
text above, these win and the text above is corrected.

1. **Blocked is split into two headline numbers.** Board 2 shows the true
   publication gap *and* a separate needs-attention block, each with its own
   reason bars and its own wording. No copy may describe a needs-attention
   episode as invisible.
   - **Invisible** (`חסום מהצגה`) = status-published minus visible, resolved
     into two reasons: no published transcription, and **owning podcast not
     published**. The second is new: `ContentItem::scopePublished()` requires a
     published group but `blockedQuery()` never checked it, so an otherwise
     complete episode under a draft podcast was invisible to the public and
     counted in no metric and no queue. Adding it makes
     invisible = status-published minus visible exactly, not approximately.
   - **Needs attention** (`דורש טיפול`) = no media URL, no category. These
     reduce quality but not visibility, and may apply to episodes the public
     can already see.
   - The funnel's published→visible gap uses the invisible number, so
     פורסם ≠ גלוי stays exact.
2. **filawidgets is fully adopted**, as the hybrid section always intended:
   `BreakdownItemData` and `SparklineTableRowData` are the data contracts for
   H3, H9 and Board 3's finding bars, inside our own Blade views. The package's
   `SparklineSeries::daily()` and period math are **not** used — both compute
   days on the database/server timezone, which contradicts the Jerusalem-walls
   contract; a Jerusalem-correct series helper produces the shape the DTOs
   expect. The cost line at the end of this document is corrected accordingly.
3. **Board 3's sources filter is the provider** — Spotify / Google Drive /
   manual, from `ImportConnectionProvider`.
4. **The Spotify card echoes the connection test**, not a fetch: status plus
   `last_tested_at`. No persisted last-fetch record exists, and none is added.
5. **Media findings show all six `MediaDiagnosticReason` values**, with
   zero-count rows hidden, rather than only the three the mockup drew.
6. **The Intake work queue carries new submissions and failed import rows**,
   with all / submissions / imports chips.
7. **The range control is hidden on the Intake lens.** Nothing on Board 3 is
   range-scoped, so gap-filler G1 applies exactly as it does on Blockers.
8. **Board state persistence stays as built:** command-bar filters persist,
   widget-local chip and day selections reset on reload.
9. **The RTL browser test lives in its own group**, run on demand, so the main
   gate stays deterministic.
10. **Phase 4's consistency tests cover** all four pairs: visible across the
    funnel, the stat card and the legend chip (rule 2's namesake case); heatmap
    total vs the funnel's published series; blocked across the gap widget, the
    queue and the burn-down; and per-podcast health totals against the scoped
    funnel.
11. **The legend scopes the flow widgets only.** A status chip narrows the
    activity stream and the publication heatmap to that stage; the stock
    widgets (funnel, composite cards, composition band) keep showing totals,
    because a total scoped to one of its own segments is meaningless. This is
    what synthesis rule 1 and H6 mean in practice — before this, the chip
    highlighted a segment and filtered nothing.
12. **The metrics cache is invalidated on editorial writes.** `ContentItem`,
    `Transcription` and `ContentGroup` saves and deletes forget the snapshot, so
    the board cannot contradict a change the editor just made. The 60-second
    TTL stays as a backstop, and the scope echo still states the snapshot time.
13. **H7 carries two burn-down bars**, one per tier. Both counts come from the
    already-cached snapshot, so the second bar adds no queries. Only tier 1
    carries a clearance forecast: transcripts have `published_at` to measure a
    pace from, while the category pivot has no timestamps at all, so a
    needs-attention forecast could not be honest and is not shown.

## Further decisions (2026-08-01)

Taken during phase 2R. See `dashboard-metrics-phase-2R-handoff.md` for the full
state; these are the design-level ones.

14. **filawidgets is removed.** Adopted per decision 2, then measured: three of
    five payloads had to be bent, and the board renders none of its views. Five
    in-house value objects under `App\Support\Dashboard\Data` replace it.
15. **Chart interactivity is staged:** static correctness now (normalisation,
    trend colour, panel-native empty states), Alpine hover/crosshair on our own
    SVG when suitable, Chart.js only later, after WB and other priorities. The
    SVG stays ours, so animation is a later CSS addition rather than a rewrite.
16. **Formats get a localization home** — the UI timezone, date formats and
    number formats belong together beside `App\Support\UiTimezone`, not in a
    dashboard-only formatter.
17. **`StreamEventType` becomes an enum** with label, colour and icon; phase 3's
    work queue needs the same types, so it lands there.
18. **Consolidations carry an anti-drift test** — scan source, permit one home,
    assert zero occurrences elsewhere. Three colour drifts and a dropped date
    year proved that "fixed once" is not enough.

## What each approach contributed

From the lenses: the structure, queue-before-graph ordering, and the
published ≠ visible separation. From the visual options: the funnel,
composition, stream, diagnostic breakdown and the color legend. From the
package: the sparkline table, completion gauge, heatmap, progress bar and shared
range filtering.

**Cost of the hybrid strategy:** four widgets (H1, H2, H5, H7) keep the
package's data layer but use our own Blade views — roughly three extra hours up
front and about an hour per Filament upgrade, in exchange for answering two
questions per glance.

*Corrected by round-2 decision 2:* "the package's data layer" means its data
contracts (`BreakdownItemData`, `SparklineTableRowData`), not its period math or
`SparklineSeries::daily()`. Both of those compute days on the database/server
timezone and are therefore wrong for a Jerusalem-walls board; a Jerusalem-correct
helper of ours produces the shape the DTOs expect.
