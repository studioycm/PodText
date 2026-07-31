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
