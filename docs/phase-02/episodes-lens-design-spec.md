# Episodes-Lens Design Spec

Date: 2026-08-04. Session: episodes/nav mini-project DESIGN session (post-route
round chip `task_345ddb95`). Companion research:
`docs/research/episodes-lens-design-research.md` (FilamentExamples protocol
record, Boost/vendor API verification, house-precedent inventory).

**Status: awaiting operator decisions (EQ-1…EQ-12). Research + UX design only —
read-only against the app; no code, migrations, or dependencies were changed.
Implementation follows only after the operator selects phases and answers the
EQ register, through the normal plan → TDD → gate workflow.**

## Operator intent (kickoff, binding)

Make the episodes list page the primary daily working surface — "the main
lens", in the spirit of the dashboard's lens concept:

1. Hide or move aside the podcasts (ContentGroups) and transcriptions
   list-page navigation items — episodes become the front door; the other
   surfaces stay reachable but stop competing.
2. Customize columns on the episodes list.
3. Re-organize the record actions.
4. Add: filters, sorting and grouping; cross-entity actions (e.g. an
   episode's "edit podcast"); input/edit columns (e.g. change status, change
   date inline).
5. Upgrade the sort/group/filter controls UX using Filament's abilities —
   reorganize how all controls display, with ease of use: e.g. toggle buttons
   carrying known filters for quick scoping.

Scope boundary: admin tier only; the public tier is untouched.

**Authoritative intent correction (2026-08-04, relayed via the ledger row —
`defect-cause-patterns.md` post-route table, commit `0f625e8`):** the goal is
**all important/main related data showing on episode records — in one
place** — not a traditional three-page CRUD split (podcasts / episodes /
transcriptions); **consolidation, not replacement**. Scope explicitly covers
the **list page AND the create/edit surfaces**. Context: the client manages
this catalogue in a Google Sheet whose records are episodes — the sheet is
mental-model context (dense scannable grid, tabs/filter views ≈ quick scopes,
inline edits), **not a yardstick**. Navigation moves/renames toward the
client's vocabulary qualify as wins on their own. This correction supersedes
any "replace the sheet" framing and is the lens through which the five scope
bullets read.

---

## 1 · Contracts (Stage 0 — ground truth)

| # | Job / object | Source of truth | Contract | Evidence |
|---|---|---|---|---|
| C1 | Public visibility of an episode | `ContentItem::scopePublished` | status `Published` AND (`published_at` null **or** ≤ now) AND podcast published AND ≥1 published transcription. Null date = live immediately; future date = scheduled; **no automation sets `published_at` on status flips** | `app/Models/ContentItem.php:277-288`, `booted()` 379-411 · Confirmed |
| C2 | Two truths | dashboard program | published ≠ visible; invisible = published − visible, exactly (`two-truths-two-cells`) | `dashboard-widget-principles.md` §2 · Confirmed |
| C3 | Transcript ontology | LENS1 | `single` mode (default): one current transcript row per episode; standalone Transcriptions resource is scoped to that row; history = super-admin filter; episode-language labels via `TranscriptionModeLabel` | `single-lens-lens1-handoff.md` · Confirmed |
| C4 | Editing home | EP1 | the workspace (`EditEpisodeWorkspace`) is the default episode editor (recordUrl + default row action); classic CRUD pages remain as «(מערכת)» | `episode-workspace-plan.md`, `ContentItemsTable.php:183-197` · Confirmed |
| C5 | Navigation | NAV1 + post-Storage-Truth rounds | one central map (`AdminNavigationOrder::ITEMS`) drives sort/group; `panelNavigation()` composes; structural guard test asserts order/groups/labels and "no untracked surfaces" | `AdminNavigationOrder.php`, `AdminPhase02ResourcesTest.php:160` · Confirmed |
| C6 | Authorization | ROLES1 + coverage guard | all-admins-equal panel authority; super-admin exceptions exist (backups delete, transcription history, multi-transcription); **no `ContentItemPolicy` exists** — Filament actions pass because no policy is registered, but a raw `Gate::allows('update', $record)` returns **false** today | `AppServiceProvider` gates/macros, `ResourcePolicyCoverageTest` ledger record · Confirmed |
| C7 | Inline-edit vendor contract | Filament v5.7.5 | `SelectColumn`/`TextInputColumn`/`ToggleColumn` save **without any policy check**; `disabled()` is the sanctioned gate (vendor source comment + docs) | `vendor/…/SelectColumn.php:41-44`, house precedent `MediaTable.php:97-105` · Confirmed |
| C8 | Global admin-table defaults | Q7 + phase-13 round | preload default inverted (searchable-without-preload, bounded sets opt in); `RecordActionsPosition::BeforeColumns` panel-wide; ListRecords pages keep bare `#[Url]` keys deliberately; deferred filters are the v5 default (`$hasDeferredFilters = true`) | `AppServiceProvider.php:169-231`, vendor `HasFilters.php` · Confirmed |
| C9 | Formats & timezone | dashboard principles | day-first via `UiFormats`/`UiTimezone`; Jerusalem walls for day bucketing; en+he keys; RTL-native | `dashboard-widget-principles.md` §5/§6/§13 · Confirmed |
| C10 | Import/export | Prompt 10 baseline | header Import/Export actions on the episodes list are part of the preserved baseline | `.ai` import-export guideline · Confirmed |

Anything the design changes in C5 (navigation map) and C6 (a new policy) is
**declared drift** and gated in the drift register (§9).

## 2 · People and jobs (Stage 1)

Users: 2–3 admins, all effectively equal authority (C6), Hebrew-first,
filling-era catalogue (local: 6 podcasts / 10 episodes / 18 transcriptions;
production same order of magnitude — tens, growing by import batches of up to
1000 rows). No evidence of a distinct "transcriber persona" using the admin —
transcribers are Author records, not users. One operator (Yoni) sets policy.

Weighted jobs (in the person's words):

| # | Job | Trigger | Frequency | Stakes | Today's entry | Verification |
|---|---|---|---|---|---|---|
| J1 | "Continue working on an episode's transcript/details" | ongoing work | daily, dominant | medium | episodes list → workspace (recordUrl) | workspace save + visibility checklist |
| J2 | "Add the next episode" | new content | weekly+ | medium | «פרק חדש» nav item / header action | workspace create |
| J3 | "Is it live? why not? make it live" | after edits / imports | daily | high (public) | scattered: status badge + dashboard blockers | public site / dashboard |
| J4 | "Find that episode" | reference, fixes | daily | low | list search (title/group/slug/urls) | — |
| J5 | "Fix status/date/pin quickly" | scheduling, curation | weekly | medium | open workspace form per record | form save |
| J6 | "Podcast-level upkeep (cover, publish, categories)" | rare | monthly | medium | podcasts list → edit | podcast page |
| J7 | "Batch intake" (import, Spotify fetch) | batches | sporadic | high | header ImportAction, Spotify fetcher page | import receipts |
| J8 | "Global transcript maintenance" | rare | rare | low | Transcriptions resource (single-mode: mirrors episodes 1:1) | — |

Design attention follows the weights: J1/J3/J4 shape the list; J5 shapes
inline editing; J2 shapes navigation; J6 shapes cross-entity actions; J8 is
the surface being demoted.

The Google-Sheet mental model (intent correction) maps onto the jobs
directly: sheet row ↔ episode record carrying its related truths; sheet
tabs/filter views ↔ quick scopes (J3/J4); in-cell edits ↔ inline editing
(J5); the sheet's one-grid-for-everything ↔ the one-place principle across
the list and the workspace. The map guides emphasis; it does not import
spreadsheet semantics the product doesn't have.

## 3 · Current-state findings (Stage 2 — evidence atlas + question audit)

Evidence IDs `E1…` cite file:line; all Confirmed by source read (2026-08-04).

**E1 · Navigation** (`AdminNavigationOrder::ITEMS` + builder): visible order is
פרק חדש (10) → רשומות טפסים (20, badge) → מדיה (30) → group «ניהול תוכן»
[פודקאסטים 100, **פרקים 110**, תמלולי פרקים 120] → group «ניהול סיווג»
[מתמללים, קטגוריות, תגיות] → trailing block [כלי ניהול, Spotify, הגדרות
cluster, ניהול מערכת cluster, קישור לאתר]. **The daily working surface sits
mid-sidebar, second inside a group, below podcasts** — the front door is not
the front door. Dashboard is the `/admin` landing and hidden from the sidebar.

**E2 · Episodes list table** (`ContentItemsTable.php`): 18 columns (7 visible
by default: image, title, podcast, type badge, transcribers, transcript
context, status, published_at, pinned; 9 toggleable-hidden); **no
`defaultSort`** — rows render in primary-key order, newest work invisible
(the relation manager, by contrast, orders `latest(published_at)`);
`published_at` uses the raw `d/m/Y H:i` literal (app-wide parked `one-home`
debt, in-scope here when touched); 7 filters (podcast, status, transcriber,
categories ×multi, tags ×multi, embed_provider, pinned ternary);
`embed_provider` filter runs a full-table `distinct` per render (registered
research-watch item); 7 record actions rendered icon-only in a row (workspace,
image, download-external ×2, effective-transcription, add-transcription,
classic edit); header: Import/Export; bulk: Export + Delete.

**E3 · Question audit** — the questions J1/J3 bring vs what E2 answers:

| Question on arrival | Answered today? | Consequence |
|---|---|---|
| "What was I working on?" | ✗ (no recency sort/scope) | scan or search every visit |
| "Which episodes aren't live, and why?" | half: status badge ≠ visibility (C2); reason lives on the dashboard blockers queue only | swivel-chair to dashboard; trust gap on the list |
| "Which are drafts?" | via filter (3 clicks: filter → select → apply) | slow for a daily scope |
| "What's pinned right now?" | pinned badge column (windowed truth via `isCurrentlyPinned`) | ok, but no one-click scope |
| "Where do I fix a blocked episode?" | ✗ — the blocker may live on the podcast or the transcript; no door from the row | navigate by memory |

**E4 · Action row** — 7 icon buttons per row with tooltips; the three image
actions and add-transcription are occasional-tier but occupy the same visual
rank as the daily workspace action; no grouping (`ActionGroup` unused
app-wide); no delete on the standalone list rows (delete = bulk or workspace
header — an intentional asymmetry to preserve).

**E5 · Podcasts list** (`ContentGroupsTable.php`): healthy operational table
(10 columns, 3 filters, image/export actions); no `defaultSort`; same
`d/m/Y H:i` literal. **E6 · Transcriptions list** (`TranscriptionsTable.php`):
in single mode shows exactly one row per episode (C3) — near-duplicate of the
episodes list for daily purposes; its unique value is transcript-field search,
word-count sort, super-admin history. **E7 · Workspace**
(`EditEpisodeWorkspace` + `EpisodeWorkspaceForm`): identity/content/image/
player/taxonomy/transcript/advanced sections + visibility checklist + replace
action — the deep-edit home is strong; the list is what lags. **E8 · Global
posture** (`AppServiceProvider`): C8 defaults; `ResourceTableActions::iconOnly`
enforces semantic icons on ungrouped row actions.

**Friction list** (operator-consequence phrasing): F-1 the daily surface is
buried in navigation (E1, J1/J2); F-2 arrival order is meaningless — no
recency (E2, J1); F-3 publication truth is split across two surfaces and two
vocabularies (E2/E3, J3, C2); F-4 known scopes cost 3 clicks through a
deferred filter dropdown (E3, J3/J4); F-5 quick fixes require a full form
round-trip (E2, J5); F-6 blocked rows have no door to their fix (E3, J3/J6);
F-7 seven same-rank icons per row bury the two daily actions (E4, J1); F-8
transcriptions nav item duplicates the episodes surface in single mode
(E6, J8, C3).

## 4 · Vocabulary (Stage 3, brief)

LENS1 already rebuilt the episode-language vocabulary; the remaining defects
this design owns:

| Term | Defect | Proposal |
|---|---|---|
| «מפורסם» (status badge) | one word carrying two truths — status and visibility (C2) | status keeps «מפורסם»; the new per-row visibility badge speaks the visible tier: «גלוי» / «מתוזמן» / «חסום» (+reason) — never re-using «מפורסם» |
| «תמלולי פרקים» beside «פרקים» | near-twin navigation nouns; in single mode the surfaces are near-identical (F-8) | demotion per EQ-1/EQ-2; label unchanged |
| «(מערכת)» suffix | established convention (NAV1) for classic CRUD | keep; new actions never take the suffix |

New-scope names (tabs) are label-only vocabulary: «הכל», «טיוטות», «גלויים»,
«חסומים», «מוצמדים» — visible-tier words, en+he keys, one enum home.

## 5 · The lens model (Stage 4)

Domain objects: Episode (ContentItem), Podcast (ContentGroup), Transcript
(Transcription, single-mode: 1 current per episode), taxonomy (categories,
tags, authors), media.

Container sentence: **one episode library, one place per posture — the list
is where you work it, the workspace is where you go deep, the dashboard is
where you watch it; an episode record carries its related truths (podcast
state, transcript state, visibility) wherever it appears.**

| Lens verb | Primary question | Jobs | Surface | Exit condition | Transitions |
|---|---|---|---|---|---|
| **Work** (the episodes lens) | "מה על השולחן ומה מצבו?" | J1 J3 J4 J5 | episodes list | found/fixed the episode; handed off to workspace | → workspace (recordUrl, back = URL-kept state); → podcast fix (remedy door); → import/fetcher (header) |
| Deep-edit | "כל הפרטים של הפרק הזה" | J1 J2 | workspace | save + visibility checklist green | → back to list (state in URL) |
| Watch | "מה דורש תשומת לב?" | J3 (observability half) | dashboard | number → doorway into Work lens | → episodes list (doorways) |
| Curate structure | "הקטלוג מסודר?" | J6 J7 taxonomy | podcasts, taxonomy resources, media | rare-visit completion | reachable from sidebar group + remedy doors |
| Audit transcripts | "חיפוש/תחזוקה רוחבית" | J8 | Transcriptions resource | rare-visit completion | direct URL / demoted nav |

Lens-linter notes: the episodes list serves ONE verb (work the library);
observability stays the dashboard's job — the list gets **row-level truth**
(visibility badge), not board-level aggregates; no new commit semantics are
invented — every mutation the list offers is an existing single-path change
(status flip, date set) executed against the same model contract the forms
use.

## 6 · Principles for this surface (Stage 5)

Inherited wholesale: `every-number-a-door` (+qualifier), `two-truths-two-cells`,
`true-zero`, `fresh-enough-no-pulse`, `jerusalem-walls`, `rtl-home-field`,
`whole-set-contracts`, `state-narrows-at-the-door`, `speaks-both-languages`,
`one-home-compiled`, `bounded-or-rolled-up`, `widgets-compute-nothing` (as
"tables compute through services"). Surface-specific derivations:

| Principle | From | Consequence |
|---|---|---|
| **P-EL1 `front-door-first`** — the daily surface is first-rank in navigation; secondary surfaces stay reachable, never competing | F-1, F-8 | §7.1 |
| **P-EL2 `row-answers-first`** — a row answers "מה זה, מה מצבו, למה" before offering actions | F-3, F-6, C2 | visibility badge + reason door precede action additions |
| **P-EL3 `one-click-known-scopes`** — the known daily scopes cost one click and live in the URL | F-4 | tabs (§7.4) |
| **P-EL4 `two-daily-actions`** — at most two ungrouped icons per row; everything else grouped | F-7 | §7.3 |
| **P-EL5 `inline-edit-is-authz`** — an inline cell is an authorization surface: policy-gated `disabled()`, validated, truthful post-write notification | C6, C7 | §7.6 |
| **P-EL6 `remedy-at-the-row`** — a blocked row offers the door to its own fix (podcast publish, transcript workspace) | F-6, media Issue-Review precedent | §7.5 |

## 7 · The design (Stage 6) — per scope bullet

### 7.1 Navigation treatment (scope bullet 1)

Recommended (pending EQ-1/EQ-2/EQ-3):

- **Episodes moves to the ungrouped leading block**, directly under
  «פרק חדש»: `ContentItemResource` sort ≈15, group `null`. The top of the
  sidebar reads: פרק חדש → **פרקים** → רשומות טפסים → מדיה.
- **Podcasts + Transcriptions stay in «ניהול תוכן», collapsed by default**
  (`NavigationGroup::collapsed()` — vendor-verified): reachable in two
  clicks, no longer competing. Group order after collapse: podcasts first,
  transcriptions second.
- Transcriptions item optionally hides in single mode (EQ-2) via
  `shouldRegisterNavigation()` reading the mode — the docs-warned caveat is
  explicit: hiding is not access control (C6 makes that acceptable — same
  authority; the URL keeps working).
- `/admin` landing stays the dashboard (EQ-3): Watch lens greets, its
  doorways land in the Work lens. No second home for board numbers.
- Mechanics: all changes land in `AdminNavigationOrder::ITEMS` (C5) and
  re-pin the central-map guard test in the same commit
  (`AdminPhase02ResourcesTest`) — the map stays the single home; the
  builder's leading/trailing partition (`sort <= media`) already supports the
  move with sort values alone.

Rejected alternatives: a "content" **cluster** (episodes as a top-tab demotes
the front door one level and breaks episode URLs); **hiding podcasts
entirely** (J6 is real, monthly; a hidden surface with no sidebar path fails
the "stays reachable" intent for a 2–3-person team; the collapsed group is
the reversible middle).

### 7.2 Columns (scope bullet 2)

Default visible set, RTL reading order after the BeforeColumns action cell:

1. **תמונה** (`OwnerImageColumn`) — unchanged.
2. **כותרת** — searchable/sortable; gains `description()` second line showing
   the podcast title on narrow widths? No — podcast keeps its own column
   (independent facts stay separate); title stays clean.
3. **פודקאסט** — searchable/sortable (existing).
4. **מצב ציבורי** (NEW, P-EL2) — derived visibility badge: «גלוי» /
   «מתוזמן ל־dd/mm/yyyy HH:mm» / «טיוטה» / «חסום: <reason>» where reason ∈
   the dashboard's `DashboardReason` vocabulary (unpublished podcast / no
   published transcript / missing category if applicable) — same enum home,
   same colors (`one-home-compiled`; `app/Enums` is already in the admin
   theme's `@source` scope with the discovery guard watching). Computed
   **batch-primed in the query/service layer** (service-hop-cost contract;
   blockers-queue fix precedent) with a query-budget test.
5. **תמלול** — the effective-transcription context badge (existing) — in
   single mode this is the transcript-state cell; keep.
6. **מתמללים** — existing badge list; toggleable (visible default).
7. **סטטוס** — enum badge (existing) — becomes the inline `SelectColumn`
   under EQ-5 (§7.6).
8. **פורסם** — `published_at`, routed through **`UiFormats`** (closing this
   table's `one-home` literal when touched), sortable.
9. **מוצמד** — windowed pin truth (existing).

Demoted to toggleable-hidden: type badge (`effective_type_label` — low
information while all content is episodes; stays for mixed-type futures),
plus the existing hidden tail (categories, tags, duration, featured title,
provider, slug, urls, reference key). Added toggleable-hidden: **עודכן**
(`updated_at`, UiFormats) — the J1 recency signal and default sort key.

**Column manager**: enable `reorderableColumns()` — the v5 column manager
(toggle + reorder + Apply, session-persisted) lets the operator self-serve
column order without code rounds. Vendor-verified; session persistence is
vendor-internal narrowing (raw-state: no custom reads).

**Density posture** (sheet mental model): the row reads as a dense scannable
grid through column choice — badges over prose, one line per row, the
toggleable tail keeping the default row narrow. No custom row-height CSS is
specced: Filament's table density is left native, and the column manager is
the density control the operator actually owns. If the filling era proves
native density insufficient, that becomes its own evidence-backed step.

### 7.3 Record actions (scope bullet 3)

P-EL4 architecture — two ungrouped icons + one group:

- Ungrouped (icon-only via `ResourceTableActions::iconOnly`):
  1. **עריכה** (workspace) — the default (also recordUrl).
  2. **תמלול** (effective-transcription quick modal) — the daily transcript
     touch without leaving the list.
- **ActionGroup** (labelled entries in a dropdown; iconOnly doesn't touch
  grouped members — verified via `modifyUngroupedRecordActionsUsing`
  semantics): שינוי תאריך פרסום (new, §7.6) · הוספת תמלול · תמונת הפרק ·
  הורדת תמונה חיצונית (±overwrite) · **עריכת הפודקאסט** (new, §7.5) ·
  עריכה (מערכת). No row delete is added (existing asymmetry preserved;
  EQ-6 records the tier decision).
- Remedy actions (P-EL6) render contextually per row state (§7.5), not in
  the standing group.

The relation manager (podcast page's episodes tab) mirrors the same
architecture by reusing the shared action builders — one home for the action
set, mirroring today's `ContentItemsTable::addTranscriptionAction()` sharing.

### 7.4 Quick scopes (scope bullet 5's "toggle buttons carrying known filters")

**Mechanism decision: native list tabs (`getTabs()`), not a
ToggleButtons-backed filter.** Rationale:

- Tabs are Filament's first-class "known quick scopes": one click, URL-kept
  (`?tab=`, vendor `#[Url]` — C8's bare-key carve-out), per-tab
  `modifyQueryUsing`, **badge counts** (schema `Tab` composes `HasBadge`),
  rendered above the table where a scope belongs.
- The house already has the full pattern proven at `ListMedia`: enum cases →
  tabs, one batched `counts()` pass for badges, `updatedActiveTab()`
  narrowing (`tryFrom ?? default`), subheading describing the active scope.
- A ToggleButtons filter would fight the v5 deferred-filters default (a
  quick scope behind an Apply button is not quick; forcing `live()` on one
  filter splits the filter grammar), carries no badges, and buries scope
  state inside `?filters[...]`. ToggleButtons remain the *dashboard's* lens
  grammar; tabs are the *list's*. (EQ-4 confirms the set, not the mechanism —
  flag if you want the mechanism itself revisited.)

The scope set (new enum, e.g. `EpisodeListScope`, + query/count service à la
`MediaLibraryTaskQuery`):

| Key | Label | Query (via C1) | Badge |
|---|---|---|---|
| `all` | הכל | none | total |
| `drafts` | טיוטות | status draft | count |
| `visible` | גלויים | full `scopePublished` | count |
| `blocked` | חסומים | status published AND NOT visible (exact complement, C2 — includes future-dated «מתוזמן» rows? **No**: scheduled ≠ blocked; see EQ-4 option b) | count |
| `pinned` | מוצמדים | `currentlyPinned` | count |

Contracts: `visible + blocked + scheduled(+drafts) = all` reconciles
(`bounded-or-rolled-up`/`two-truths-two-cells`); counts are one batched pass,
cached with mutation invalidation like `ListMedia::forgetMediaTaskCaches`
(`fresh-enough-no-pulse` — no polling); a **structural loop test** over the
enum asserts every case has en+he labels, a query, a count and a tab
(`whole-set-contracts`); `updatedActiveTab` narrows
(`state-narrows-at-the-door`). This also creates the **filterable doorway
surface** the dashboard's invisible-tier numbers were missing under the ES-1
qualifier — a registered gap this design closes (Board doorways may later
target `?tab=blocked`).

### 7.5 Cross-entity actions (scope bullet 4b)

- **עריכת הפודקאסט** (standing, in the group): navigates to
  `ContentGroupResource::getUrl('edit')` — the podcast's own first-class
  surface; no duplicate podcast form in a modal (one-home for surfaces;
  media-program precedent).
- **Remedy doors** (P-EL6, contextual): a «חסום» badge row offers the fix for
  *its* reason — «פרסום הפודקאסט» (confirmation-modal action flipping the
  podcast status, shown only when that is the blocker and the podcast is
  otherwise ready) and «פתיחת התמלול» (workspace deep-link to the transcript
  section) when the transcript is the blocker. Both validate server-side at
  execution (browser-forgeable visibility is presentation only,
  `state-narrows-at-the-door`).
- Podcasts table gains the reverse door: «פרקי הפודקאסט» action →
  episodes list URL with the podcast filter set (receiving filter verified —
  `ContentItemsTable.php:138`, the A4-audited doorway shape).

### 7.6 Inline-edit columns (scope bullet 4c) — authorization surfaces

Vendor contract (C7): inline columns bypass policies; `disabled()` is the
gate. House precedent: `MediaTable` title column. **Prerequisite: a
`ContentItemPolicy` must exist before any inline column ships** — today
`Gate::allows('update', $record)` returns false (no policy), so the
MediaTable-shaped gate would freeze every cell; and the ledger's page-tier
sighting says mutation surfaces get real policies, not conventions. Policy
shape: admin-level true for view/create/update (uniform authority preserved,
C6); `delete`/`deleteAny` per EQ-6. Registering it also makes every existing
ContentItem Filament action consult it — a behavior-preserving change only if
the methods return true for admins; pinned by tests (drift register, §9).

- **סטטוס → `SelectColumn`** (EQ-5): enum options, `->rules()`, `disabled()`
  policy gate, `afterStateUpdated` → **truthful visibility notification**
  computed from C1: «פורסם — גלוי באתר» / «פורסם — עדיין לא גלוי: <reason>» /
  «הועבר לטיוטה — ירד מהאתר». One commit path: this *replaces* any separate
  publish/unpublish row action (lens-linter: no competing commit paths;
  the workspace form remains the deep-edit path as today). Draft→Published
  with null date goes live immediately (C1) — the notification says so.
- **תאריך פרסום → modal action, not a column**: no native date-edit column
  exists in v5; a `TextInputColumn` parsing `dd/mm/yyyy HH:mm` strings is
  rejected (error-prone, manual timezone). «שינוי תאריך פרסום» action:
  `DateTimePicker` (day-first display, `Asia/Jerusalem`, helper text per
  cross-cutting rules), pre-filled, with the scheduled/immediate consequence
  stated in the modal description.
- **Pin quick-edit**: deferred (EQ-8 option) — `ToggleColumn` on `is_pinned`
  would hide the window+order semantics (collapsed-facts defect); if wanted,
  it's a small modal action («הצמדה…») editing the pin trio, phase-gated.
- Title/`TextInputColumn`: rejected for now — identity field, workspace is
  one click, low J5 value.

### 7.7 Filters, sorting, grouping controls (scope bullets 4a + 5)

- **Default sort: `updated_at` desc** («מה שנגעתי בו לאחרונה» — J1), with
  `defaultSortOptionLabel` like Media. `published_at`, title, podcast,
  status stay sortable; transcript-recency sort
  (`orderByEffectiveTranscriptionPublishedAt`) is available cheaply if a
  sort dropdown is wanted later (not specced now).
- **Filters** (dropdown layout kept; deferred default kept — EQ-7): the
  `status` SelectFilter is redundant with tabs (two homes for one scope) but
  **must not be removed unilaterally** — the design session's grep found the
  dashboard's audited doorways are its receivers:
  `PublicationFunnelWidget.php:68-72` (draft/published stage doorways) and
  `EditorialStatsWidget.php:78-80` (published doorway) build
  `filters[status]` URLs, and the A4 sweep pinned those URL shapes as
  backed-by-receiving-filters. Resolution: the filter stays until the same
  phase re-points those doorways at tab URLs (`?tab=drafts` etc. — the
  funnel's «published» doorway needs an explicit target decision there,
  since published = visible+scheduled+blocked spans tabs) and updates the
  pinned doorway URL-shape tests; only then does the filter retire. Keep
  podcast /
  categories / tags / transcriber (all already capped-searchable post-Q7);
  **repair `embed_provider`** (registered watch item): a bounded cached
  option source instead of per-render full-table `distinct`; **add** a
  published-date range filter (`DatePicker` from/until, Jerusalem-input,
  `Indicator::removeField` indicators — the FiltersLayout stays `Dropdown`
  until tabs prove insufficient; `AboveContentCollapsible` is the EQ-7
  alternative).
- **Grouping** (first in-house use of `->groups()`): offer «פודקאסט»
  (`Group::make('contentGroup.title')->collapsible()`) and «סטטוס». Grouping
  state rides vendor `?grouping` URL binding. **Month-of-publication grouping
  is gated**: vendor `date()` grouping buckets by the raw UTC column —
  a `jerusalem-walls` violation near midnight; it ships only with the full
  closure set (`getKeyFromRecordUsing` + `scopeQueryByKeyUsing` +
  `orderQueryUsing` + `getTitleFromRecordUsing`, all Jerusalem-consistent,
  vendor-verified to exist) plus a near-midnight fixture test
  (`proxy-oracle` discipline) — otherwise it drops from scope.
- **No** `groupsOnly`/summaries in this design (no aggregate contract on the
  list; the dashboard owns aggregates).

### 7.8 One place: the create/edit surfaces (intent correction scope)

The workspace already consolidates episode + transcript + image in one form
(E7) — the correction's remaining gap is **podcast context**. Today the
workspace shows the podcast only as a select; the podcast's own state
(published?, cover?, categories that the episode inherits) lives three
clicks away, yet it co-decides the episode's visibility (C1).

Design: a **podcast-context strip** on the workspace (and create page after
a podcast is chosen): the podcast's title, publication state (visible-tier
vocabulary, §4), its category chain (the inheritance the public filters use),
and two doors — «עריכת הפודקאסט» (its own page) and the same
publish-podcast remedy the list rows carry (§7.5) when it is the blocker.
Read + doors, **not** an embedded podcast edit form — consolidation shows
related truth in place; owned surfaces keep their one home (the media-program
precedent the adversarial pass reaffirmed). EQ-12 owns the shape.

The list-side one-place work is §7.2's podcast/transcript/visibility columns
and §7.5's doors — together the episode record carries its related truths on
both surfaces without duplicating either parent's form.

## 8 · Operator decision questions (EQ register)

*(Fresh `EQ-<n>` family per the naming convention. Each: options + one
recommendation.)*

- **EQ-1 — Podcasts/Transcriptions navigation treatment.**
  (a) **Episodes promoted to the ungrouped top block (under «פרק חדש»);
  podcasts + transcriptions remain in «ניהול תוכן», collapsed by default —
  recommended** (reversible, keeps J6/J8 reachable, strongest
  front-door signal for the cost);
  (b) hide both from the sidebar entirely (reachable via cross-entity doors +
  URLs only) — cleanest sidebar, weakest discoverability;
  (c) reorder only (episodes first inside the open group) — cheapest, weakest;
  (d) content cluster with top tabs — rejected above (demotes the front door,
  breaks URLs), listed for completeness.
- **EQ-2 — Transcriptions item mode-awareness.**
  (a) **Hide the Transcriptions nav item while `transcription_mode=single`
  (URL keeps working; item returns in multi mode) — recommended** (F-8; this
  EQ is the "new operator decision" the LENS1 handoff required);
  (b) always show it inside the collapsed group;
  (c) super-admin-only visibility.
- **EQ-3 — `/admin` landing.**
  (a) **Dashboard stays the landing — recommended** (Watch greets, its
  numbers door into the list; one home per lens);
  (b) episodes list becomes the landing (dashboard stays one click away).
- **EQ-4 — Quick-scope set.**
  (a) **Five tabs: הכל / טיוטות / גלויים / חסומים / מוצמדים, with «מתוזמן»
  shown as a badge state inside «גלויים»'s complement math — recommended**
  (scheduled rows count under «חסומים»? No: under (a) scheduled rows appear
  in «הכל» and carry the «מתוזמן» row badge; «חסומים» = published, past/null
  date, but group- or transcript-blocked — the actionable tier);
  (b) add a sixth «מתוזמנים» tab (exact partition: drafts+visible+scheduled+
  blocked = all);
  (c) minimal three (הכל / טיוטות / חסומים).
  The counts reconcile structurally in every variant.
- **EQ-5 — Inline status editing.**
  (a) **`SelectColumn` with policy gate + truthful visibility notification,
  replacing any separate publish row action — recommended** (single commit
  path, J5 served, C1 honesty);
  (b) confirmation-modal publish/unpublish action instead (extra click,
  but an explicit consequence dialog);
  (c) neither — status stays read-only on the list.
- **EQ-6 — `ContentItemPolicy` tiers** (prerequisite for EQ-5a).
  (a) **view/create/update all-admins; delete + deleteAny super-admin-only,
  matching the SettingsBackup ruling; bulk-delete surface keeps working for
  super-admins only — recommended** (consequential-action tiering precedent);
  (b) uniform all-admins for everything including delete (pure status quo,
  formalized);
  (c) defer the policy and gate the inline column on `hasRoleAtLeast(Admin)`
  directly — rejected-ish: repeats the parked-authz pattern the ledger warns
  about, listed for completeness.
- **EQ-7 — Filter controls posture.**
  (a) **Keep dropdown layout + v5 deferred default — recommended** (tabs
  absorb the daily scoping; the dropdown is the occasional path; quiet
  default);
  (b) `FiltersLayout::AboveContentCollapsible` + `filtersFormColumns` (more
  discoverable, more screen cost);
  (c) live filters (`deferFilters(false)`) — snappier, N queries per
  keystroke on searchable selects.
- **EQ-8 — Grouping offering.**
  (a) **Podcast + status grouping now; month-of-publication only behind the
  Jerusalem-safe gate (§7.7); pin quick-edit action deferred — recommended**;
  (b) also ship the month grouping in the first phase (accepting the closure
  work + fixture up front);
  (c) defer grouping entirely.
- **EQ-9 — Cross-entity podcast editing shape.**
  (a) **Navigation link to the podcast edit page + contextual remedy actions
  (publish-podcast, open-transcript) on blocked rows — recommended** (one
  home per surface; answer-first remediation);
  (b) full podcast edit modal from the episode row (faster, duplicates a
  first-class form surface);
  (c) link only, no remedy actions.
- **EQ-10 — Default sort.**
  (a) **`updated_at` desc — recommended** (work-recency, J1; drafts never
  sink);
  (b) `published_at` desc (public-recency; null-date drafts sink last on
  MySQL/SQLite desc);
  (c) keep unsorted (status quo) — rejected-ish, listed for completeness.
- **EQ-11 — Client vocabulary for navigation.** The correction says nav
  renames toward the client's vocabulary qualify on their own — but the
  client's actual nouns are not in evidence. (a) **Operator supplies the
  client's terms (sheet column/tab names) and the design maps them onto nav
  + group labels + scope labels in EL-P1 — recommended**; (b) keep current
  labels («פרקים», «פודקאסטים», «ניהול תוכן») until the vocabulary arrives.
  A rename is translation-key work; it must stay consistent across nav,
  breadcrumbs, resource labels and the public tier's admin-facing copy.
- **EQ-12 — Workspace podcast-context strip (one-place, §7.8).**
  (a) **Read + doors strip: podcast state, inherited categories, edit link,
  contextual publish-podcast remedy — recommended** (consolidation without
  duplicating the podcast form);
  (b) embedded editable podcast subsection on the workspace — steered away
  (two homes for the podcast form; blast radius on save semantics);
  (c) defer §7.8 entirely to a later round.

## 9 · Risks, pattern audit, drift register (Stage 7)

**Defect-cause-pattern audit** (`docs/research/defect-cause-patterns.md`) —
how the design avoids speccing each pattern back in:

- `decorative-cap`: every new/kept select filter rides a mechanism actually
  in play — relationship+searchable+optionsLimit or a bounded cached source;
  the global Q7 inverted default covers new selects; the `embed_provider`
  repair replaces the render-time `distinct`. No `optionsLimit` is pasted on
  static options anywhere in this spec.
- `service-hop-cost`: the visibility/blocker badge and remedy-door visibility
  are **batch-primed** in the list query/service with a query-budget test
  (the blockers-queue fix is the named precedent). Tab badge counts are one
  batched pass.
- `implicit-keys`: the design adds no second paginated component to the list
  page; ListRecords bare keys are the settled global posture (C8); `?tab`,
  `?grouping`, `?sort` are vendor bindings.
- `raw-state`: `updatedActiveTab` narrows via `tryFrom ?? default`
  (house pattern); remedy/inline mutations re-validate server-side; no new
  custom URL/session reads.
- `unscanned-home`: any new class-emitting enum lives in `app/Enums`
  (already in the admin theme's `@source`) and `ThemeScanScopeTest`'s
  discovery guard auto-catches new emitters; scope badges prefer Filament
  semantic badge colors (no raw palette classes).
- `unpinned-promise`: every behavior promised here names its test family in
  the phase sketch (visibility-notification truth test, tab-count
  reconciliation test, structural scope-loop test, query-budget test,
  policy watched-red tests, near-midnight grouping fixture, navigation
  central-map re-pin, relation-manager parity test).
- `one-home` / `no-type-home`: scope set gets an enum home; date formats
  route through `UiFormats` when touched; reason vocabulary reuses
  `DashboardReason` (no second reason home).
- `options-state-cast`: the `SelectColumn` writes through the model's enum
  cast (column state, not schema state) — noted, no settings-property
  involvement.
- `proxy-oracle` / `jerusalem-walls`: visibility scopes derive from
  `scopePublished` itself, not proxies; date grouping gated on
  Jerusalem-correct keys + a near-midnight fixture.
- `silent-cap`: tab badges are exact counts; nothing new truncates silently.
- `unarchived-binding`: this spec restates every contract it binds; no
  external-artifact references.
- Browser-test families (`single-read-race`, `test-residue`, `flake-label`):
  implementation tests follow the recorded memories (labelled waits,
  run-scoped fixtures); registered as phase-plan constraints, not re-derived
  here.

**Dashboard-principle transfers**: `every-number-a-door` — tab badges are
doors by construction; the row visibility badge's reason opens its remedy
door; no dead-end numbers are added. `two-truths-two-cells` — §7.4 partition
math + the vocabulary split (§4). `true-zero` — empty tab states get honest
copy («אין פרקים חסומים — הכול גלוי» class of messages), designed per tab.
`fresh-enough-no-pulse` — cached counts + mutation invalidation, zero
polling. `whole-set-contracts` — the scope enum loop test.

**Risks**:

| Risk | Severity | Containment |
|---|---|---|
| Registering `ContentItemPolicy` changes authorization semantics for every existing ContentItem action | high if silent | drift-gated (below); watched-red tests prove admin parity before/after; EQ-6 decides delete tier explicitly |
| Inline status flip publishes instantly (C1 null-date) | medium | truthful notification (§7.6); `disabled()` gate; reversible flip; EQ-5b exists if the operator wants a confirm dialog instead |
| Tab counts drift from row badges (two computations of one truth) | medium | one service computes both; reconciliation test pins `visible+blocked+scheduled+drafts = all` |
| Removing the status filter breaks the dashboard's audited doorways (funnel draft/published, stats published — `filters[status]` builders found by this session's grep) | high if unilateral | coupled-step rule in §7.7: doorways re-point to tab URLs + their URL-shape tests update in the same phase, else the filter stays; `unpinned-promise` discipline |
| Date grouping ships UTC-bucketed by accident | medium | hard gate in §7.7 — no closure set, no month grouping |
| Navigation change breaks the central-map guard | certain (by design) | same-commit re-pin; the guard is the spec's enforcement, not an obstacle |
| Relation-manager drift (list improves, podcast tab stays old) | medium | shared action/column builders + parity test (existing sharing precedent) |

**Drift register** (needs explicit approval beyond this spec):

1. `ContentItemPolicy` introduction (C6 change) — EQ-6.
2. Navigation map reorder + collapsed group + optional mode-aware hiding
   (C5 change) — EQ-1/EQ-2; supersedes the LENS1 "unchanged surfaces"
   clarification for navigation only, by new operator decision.
3. Status SelectFilter retirement — coupled to the dashboard doorway
   re-pointing (§7.7); folded into EQ-4's approval with that coupling
   explicit.
4. No schema, dependency, or public-tier changes anywhere in this design.

## 10 · Phased mini-plan sketch (Stage 8 — phases only)

Each phase is independently valuable; removing any later phase leaves the
earlier ones whole. Task-level planning happens after the EQ decisions, per
phase, through the normal implementation-plan → TDD → gate cycle.

- **EL-P1 · Front door** — navigation reorder, collapsed group, (EQ-2
  hiding), central-map test re-pin. Smallest shippable statement of the
  lens.
- **EL-P2 · List foundation** — default sort, column set + demotions +
  `updated_at`, `UiFormats` routing, column manager (reorderable), action
  re-grouping into the two-icons+group architecture (+relation-manager
  parity).
- **EL-P3 · Known scopes** — scope enum + query/count service + tabs +
  badges + narrowing + reconciliation and structural-loop tests. (Creates
  the doorway surface for dashboard invisible-tier numbers.)
- **EL-P4 · Row answers + remedy doors** — visibility badge (batch-primed,
  query-budget test), reason vocabulary reuse, contextual remedy actions,
  podcast-side reverse door.
- **EL-P5 · Inline editing** — `ContentItemPolicy` (watched-red parity
  tests), status `SelectColumn` + truthful notification, publish-date modal
  action.
- **EL-P6 · Controls polish** — date-range filter + indicators,
  `embed_provider` repair, dashboard-doorway re-pointing to tab URLs **then**
  status-filter retirement (the §7.7 coupled step), grouping (podcast/status;
  month only behind the Jerusalem gate), empty-state copy per tab.

- **EL-P7 · One place on the workspace** — the podcast-context strip
  (EQ-12 shape): state + inherited categories + doors + contextual remedy;
  shares the §7.5 remedy implementation.

Suggested order rationale: P1 is pure signal at near-zero risk (and carries
EQ-11 renames if the vocabulary arrives); P2–P3 are the daily-use payoff;
P4–P5 add the truth+touch layer; P6 is polish; P7 extends one-place to the
deep-edit surface and can ride alongside P4 (shared remedy code). P5
depends on EQ-6; P3 blocks the dashboard-doorway follow-up (outside this
project).

## 11 · Evidence gaps & closing statement

- Production row counts were not probed (local dev DB cited as indicative;
  the filling-era framing is document-confirmed). No design decision hinges
  on the exact number.
- No live-browser walkthrough was performed in this session (source-level
  audit only; the surfaces' rendered RTL behavior is covered by existing
  browser suites). Flagged as an evidence gap, not faked.
- Operator jobs J1–J8 are **Inferred** from surfaces, prior handoffs and the
  kickoff — the EQ register is where wrong inferences get corrected.
- The two mid-session operator clarifications (sheet mental model; the
  one-place correction) reached this session via the ledger row committed at
  `0f625e8`, not in-band — the spec treats the corrected ledger text as the
  authoritative intent statement and was amended against it before
  commit. The client's actual vocabulary (EQ-11) remains the one intent
  input still missing.

This session was read-only: no app code, migrations, dependencies, or tests
were changed; deliverables are this spec and the research doc. Operator EQ
answers + phase selection are required before any implementation planning.

---

## 12 · Decision annex — ALL decisions answered (2026-08-04/05)

The operator answered the full register across three interview rounds plus
the boards round. This annex is the authoritative record; where it refines
§7/§8, it wins.

**EQ answers.** EQ-1: episodes ungrouped on top; podcasts + transcriptions in
«ניהול תוכן», **collapsed**, and the group moves **below «ניהול סיווג»**.
EQ-2: transcriptions item hidden while `single` mode **for non-super-admins**
(returns in multi mode; super-admins always see it). EQ-3: dashboard stays
the `/admin` landing. EQ-4: **six scopes — exact partition**
הכל = טיוטות + גלויים + מתוזמנים + חסומים (plus מוצמדים as a pin-window
scope). EQ-5: inline status `SelectColumn` (option a). EQ-6: policy with
**delete = super-admin only** (option a; bulk-delete + workspace-delete
follow). EQ-7: **open filter panel above the table**
(`FiltersLayout::AboveContentCollapsible` — supersedes §7.7's dropdown
recommendation). EQ-8: podcast + status grouping now (option a; month stays
Jerusalem-gated). EQ-9: remedy doors + page link (option a). EQ-10: default
sort `updated_at` desc. EQ-11: labels unchanged until the client's
vocabulary arrives. EQ-12: workspace strip = read + doors (option a).

**Boards round.** Base = board A; from B: compact stat-chip strip (1 row max)
+ grouping toggles + sort toggles; from C: all four pieces. Column rule:
**only the title column is non-toggleable**; the column manager
(`reorderableColumns()`) owns everything else.

**New model rules (operator-authored).**
1. `publish-stamps-date`: saving any record as published with a null
   publish-date stamps it `now()`; an explicit date is never overwritten;
   unpublishing keeps the date. Applies to every status+published-date pair —
   `ContentItem`, `ContentGroup`, `Transcription`. (Amends contract C1's
   "no automation" corollary.)
2. `effective-published-date` resolver: where a publish date is *read*,
   published rows with a null date fall back to `created_at` — one
   centralized home (Eloquent `Attribute` accessor + a `COALESCE` sort
   scope), read-side only, **no backfill**.

**Principles.** P-EL1–P-EL6 ratified as-is; two new principles adopted:
**P-EL7 `toggles-over-selects`** (tiny bounded value sets render as grouped
ToggleButtons; selects only for growing sets) and **P-EL8
`manager-owns-columns`** (every non-identity column toggleable + reorderable;
the column manager is the density control; structural loop guard).

### R2 seeds — carry these into the R2 implementation plan

Operator instructions given after R1 shipped. They are **binding inputs to
the R2 plan**, not optional suggestions; the R2 planner starts here.

**S1 — rename the navigation group «ניהול תוכן» → «ניהול ממוקד»**
(operator, 2026-08-05). R1 moved episodes out of that group to the ungrouped
front door, so the label now describes only its survivors — podcasts and
transcripts, the surfaces you open to work one specific thing. "Content
management" stopped being true the moment the content itself left.

Mechanics (verified against HEAD, 2026-08-05 — the rename is label-only and
touches no logic):
- The only value sites are `lang/he/admin.php` and `lang/en/admin.php` under
  `admin.navigation.groups.content_management`. Every other reference — the
  builder, the two ITEMS rows, and all four test assertions — routes through
  `AdminNavigationOrder::CONTENT_MANAGEMENT` or the translation key, and
  `AdminClusterNavigationTest:29` uses `__()` rather than a literal. No test
  hardcodes the Hebrew string, so nothing breaks on the value change alone.
- **Also rename the key and constant** (`content_management` →
  `focused_management`, `CONTENT_MANAGEMENT` → `FOCUSED_MANAGEMENT`).
  Mechanical: 5 sites in `AdminNavigationOrder`, 6 in
  `AdminPhase02ResourcesTest`, 1 in `AdminClusterNavigationTest`, 2 lang
  keys. Leaving a key named `content_management` holding «ניהול ממוקד» is
  exactly the vocabulary drift the house `one-home` rule exists to prevent.
- **Decide the English counterpart**: the Hebrew is "focused management".
  Recommended `'Focused management'` for a literal pair; flag to the
  operator if they want something less literal.
- **Open question for the operator, not for the planner to answer alone:**
  the sibling group «ניהול סיווג» (taxonomy) is now the odd one out in the
  pair. Ask whether it wants a matching rename before shipping S1, so the
  sidebar reads as one deliberate vocabulary rather than a half-migration.

S1 also **partially answers EQ-11** (client vocabulary), which closed as
"keep current labels until the client's terms arrive". The first term has
arrived; EQ-11 stays open for the rest.

**Realizations.** Two operator-reviewed realizations of the blueprint exist —
R1 native-first (stock APIs + custom columns/actions only) and R2
custom-forward (chip strip, toggle rows, three-segment visibility cell,
saved views, per-column search, sticky prefs). Adopted path: **R1 ships
first** (see `episodes-lens-r1-implementation-plan.md`), R2 follows as its
own phase. A 2-agent verification pass confirmed all 14 vendor API claims
(file:line) and the boards' decision fidelity. The boards artifact is
archived at `docs/research/episodes-lens/episodes-lens-boards.html`
(readable-binding: this annex, not the artifact, is the contract).
