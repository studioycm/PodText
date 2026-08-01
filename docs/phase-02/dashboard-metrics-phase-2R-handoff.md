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

**E5** · *Done.* The four enum-backed `AdminUxSettings` properties are typed as
their enums, with a repair migration and mutation-checked read-site coverage. A
fifth, `tb1_picker_container`, was removed outright. Adds a settings-row
invariant test. See "Enum-typed settings properties (E5)" below.

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

**M1** · *Opened 2026-08-01, not started.* Media picker Storage panel — two
findings raised while investigating the browser-test flake. **Whoever lands this
must update this entry and the media-picker bullet under "Gotchas".**

- **The de-dup filter everyone assumes exists does not.**
  `StorageImageCandidateBrowser::browse()` never consults `Media`, so a file
  already in the gallery keeps appearing in the Storage panel with no marker,
  and can be "acquired" again. The acquisition itself is idempotent —
  `MediaAcquisitionManager.php:165` reuses the existing record rather than
  duplicating — so this is a UX gap, not data corruption. **Decided 2026-08-01:
  hide them.** Already-registered files do not belong in a panel whose purpose
  is finding files that are *not* yet in the library.
- **The candidate list is a silent cap.** The `laravel_public` source uses
  `root => ''` — the entire public disk — with `storage_candidate_limit` 50 and
  breadth-first traversal, so files in deeper directories can be dropped with no
  indication. That is the same no-silent-caps violation as F3, and on a real
  disk it is the more likely of the two to bite. **Decided 2026-08-01:
  paginate.** Not a raised limit and not a "showing 50 of N" label — the panel
  gets real paging, so nothing is dropped at any disk size.

**M2** · *Opened 2026-08-01, not started. Production defect, not a test flake.*
**Start here: `docs/research/media-picker-m2-cross-session-brief.md`** — the
2026-08-01 consolidation of all four sessions' findings (mechanism verified in
vendor source at both ends, ruled-out causes, the reproduction discrepancy to
resolve, fix directions, upstream links). The entry below is the original
capture and stands, with one later correction recorded in the brief and the
gotchas bullet: after ~100 instrumented runs, two ordinary open/close cycles of
the picker reproduce it in a real browser.
Reopening the media picker leaves a **duplicate, uninitialised component root**
in the DOM, after which every Filament partial update for the host component
throws `Multiple elements found for partial [action-modals.1]` and the picker
stops working for the rest of the page's life. **Whoever lands this must update
this entry and the media-picker bullet under "Gotchas".**

Reproduced in a real browser (not Pest) on `/admin/content-groups/1/edit` with
ordinary clicks only. **It needs a nested mount**: the broken state had the
picker at `mountedActionSchema1` and the error named `action-modals.1`, while a
plain top-level open→close→reopen cycle is clean (child key registered on open,
removed on close, re-registered on reopen). Captured state when broken:

- two `[data-testid="media-picker"]` elements sharing one **`wire:id`** *and*
  one `wire:key` — a real second mount would carry a fresh id, so one of them is
  a **copy**, not an instance;
- the copy kept `wire:id`, `wire:key` and `wire:snapshot` but lost the
  `__livewire` property — the fingerprint of `cloneNode(true)`, since JS
  properties do not survive cloning and attributes do;
- because it is uninitialised, its `action-modals.1` partial resolves to the
  *host page* component instead of to itself;
- the host subtree therefore holds two `action-modals.1` containers that both
  resolve to the host → Filament's `partials.js` throws.

The `wire:id` duplication and the key are **one mechanism, not two**. The clone
at `partials.js:70` is guarded by `if (child.hasAttribute('wire:snapshot'))
return` — it only fires for an incoming child with *no* snapshot, which is
exactly what Livewire emits when it **skips** a child whose key the parent has
already seen. So: key already registered → server skips the child → emits it
snapshot-less → Filament clones the live root to graft it in → the morph keeps
both. Note also that Alpine's morph keys siblings by `wire:key`, so the live
root and the clone collide there too.

The mechanism is `filament/support/resources/js/partials.js:70`,
`child.replaceWith(existingComponent.cloneNode(true))`. The clone keeps
`wire:id` and `wire:snapshot` but not the `__livewire` property, the morph
inserts it into the live DOM, and nothing ever initialises it. The Pest fixtures
missed it because they have no media and so no nested item action, meaning no
`action-modals.1` partial exists to collide.

The likely cause is the child key, per Livewire's nesting docs: the parent keeps
a list of rendered child keys and **skips** any child whose key it has already
seen. The picker's `wire:key` is
`{hostId}.mountedActionSchema{n}.media-picker-workspace-{componentKey}` — stable
across unmount/remount at the same nesting index, so a remount can be skipped
while fresh markup for it still arrives.

Setting a different key is cheap and supported — `HasKey::key()` accepts a
Closure evaluated per render, and `Schemas\Components\Livewire::toEmbeddedHtml()`
passes the result straight to `Livewire::mount($component, $properties, $key)`
as the `wire:key`. What Filament does *not* supply is a per-mount **value** to
put in it: `mountedActions[]` entries hold only `name`/`arguments`/`context`,
pushed on mount and popped on unmount, with nothing distinguishing a second open
of the same action at the same index.

**Do not design the fix around the key until the trigger is reproduced
deliberately.** A stable key at index 0 behaves correctly (child registered on
open, removed on close, re-registered on reopen — verified). The broken state
had `launchPanel` at index 1, i.e. the picker action opened while
`chooseContentGroupCover` was already mounted. Reproduce that on purpose first,
then test whether a per-mount key actually fixes it. Weigh against an upstream
Filament report.

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
| Enum-backed settings properties typed as `string` | Fixed — E5, `AdminUxSettings` |
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

## Gotcha: `->options(Enum::class)` and the property type must agree

**Superseded 2026-08-01 by E5 — see below. The workaround this section used to
recommend is no longer the answer.**

Passing an enum class to `->options()` makes Filament install an
`EnumStateCast`, so the field's state becomes an enum instance. E4 hit this on
`AdminUxSettings::$media_naming_strategy` and read it as "`->options(Enum::class)`
is wrong for settings pages". The real rule is narrower: **the field's state type
and the backing property's type have to agree.** A string property with
`->options(Enum::class)` fails one way; an enum property with an array
`->options()` fails the other.

E5 made them agree by typing the property, which is the better half of the pair —
see "Enum-typed settings properties (E5)" below. Do not reintroduce the
`value => getLabel()` array map on `AdminUxSettings`; it is now the mismatched
half.

## Enum-typed settings properties (E5)

The four enum-backed `AdminUxSettings` properties are typed as their enums:
`media_naming_strategy`, `media_acquisition_filename_strategy`,
`transcription_presentation_mode`, `transcription_mode`. A fifth,
`tb1_picker_container`, was typed and then removed outright — see below.

What a future session needs to know before touching this:

- **Spatie auto-casts backed-enum properties with no config.**
  `SettingsCastFactory::createDefaultCast()` reaches `enum_exists()` and returns
  an `EnumCast`. Nothing goes in `settings.global_casts`.
- **`EnumCast::get()` uses `from()`, not `tryFrom()`.** A stored payload matching
  no case throws a `ValueError` on *every* request that loads the group, not
  only on the screen that reads it. Any new enum-typed setting needs a repair
  migration for the same reason.
- **A repair migration cannot use `$this->migrator->update()`.**
  `SettingsMigrator::getPropertyPayload()` applies the cast while reading, so a
  migrator-based repair throws on exactly the values it exists to fix. Go to the
  repository below the cast — see
  `database/settings/2026_08_01_000000_type_admin_ux_enum_settings.php`.
- **`PublicContentSettings` was deliberately left alone.** Its ten string
  properties are validated against private const arrays in
  `PublicContentCardOptions`, not against `App\Enums\*`. `PublicFrontLayoutVariant`
  is a grab-bag spanning four concepts and is not a per-property type. Typing
  those means creating six enums first — separate work, not a follow-on.
- **`SettingsBackupManager` assigns raw payload values** at
  `$settings->{$property} = $value`. It is hard-guarded to `PublicContentSettings`,
  so it is safe today. Widening that scope to `AdminUxSettings` would TypeError.
- **A settings property with no default is a required database row.** A missing
  row throws `MissingSettings` and takes down every screen that loads the group.
  Adding a default softens this but does not remove it: `ensureNoMissingSettings`
  folds `getDefaultValueLoadedProperties()` into the *saving* check, so a
  default-loaded property still throws on save. Both directions were verified
  against the running app, not read off the source.
- **Orphan rows are harmless.** `SettingsMapper::load()` fetches only reflected
  property names, so a row whose property no longer exists is never read. Code
  may be deployed ahead of the migration that deletes the row.

### `tb1_picker_container` removed (2026-08-01)

It chose whether the TB1 table image picker opened as a modal or a slide-over,
via `ContentImageActions::applyConfiguredContainer()`. Mini-task 3A (`6da7fda`)
replaced that action with one canonical modal, declared the runtime choice
obsolete, and kept the property, enum and row as inert data.

The retained row was still *required* — the property had no default, so losing
it fatally broke the whole `admin_ux` group over a value nothing read. The
operator chose removal over retention, since restoring the capability would mean
reversing 3A's accepted design. Property, enum, six translation keys and the row
are gone; `2026_08_01_000001_drop_admin_ux_tb1_picker_container.php` deletes it.

The historical seed in `2026_07_12_000001` is deliberately left intact — never
edit a migration that has already run in production.

`media_naming_strategy`, `show_episode_workspace_hint_line` and
`show_episode_workspace_language_code` gained defaults matching what their seed
migrations already write.

A concern raised against the `media_naming_strategy` default — that a missing
row would make exports quietly use `slug` — was checked and does not hold. Its
only read site is `defaultEgressNamingStrategy()`, which feeds `->default()` on
a visible, required Select in the export modal; the value the job receives comes
from what the operator selected there, not from settings. A wrong default is on
screen before submit.

What *was* silent is now fixed: that method's bare `catch (\Throwable)` — the
thing that hid the original E5 type mismatch — calls `report()` before falling
back, so the modal stays usable and the misconfiguration is still visible.

### The settings-row invariant

`tests/Feature/SettingsRowInvariantTest.php` asserts, for `AdminUxSettings` and
`PublicContentSettings`, that every declared property has a seeded row, that no
row survives without a property, and that each group loads *and saves* as
migrated. Both directions are mutation-checked.

This exists because a property default softens the missing-row fatal but cannot
remove it — `ensureNoMissingSettings()` folds `getDefaultValueLoadedProperties()`
into the saving check, so a default-loaded property loads fine and then throws on
save. That is Spatie's behaviour, not something app code can fix. The invariant
moves the discovery from production to CI. The realistic trigger is adding a
property and forgetting its seed migration; the orphan direction would have
caught `tb1_picker_container`'s row had its delete migration been missed.

The failure mode that made this worth doing was silent, not loud. Comparing an
enum to `Enum::Case->value` is simply `false` — no error — so
`MultiTranscriptionSurfaces::isMultiMode()` would have switched multi-mode off
for everyone, permanently. `ContentImageActions::defaultEgressNamingStrategy()`
was worse: it raised a `TypeError` and then swallowed it in its own
`catch (\Throwable)`, silently returning `Slug`. Both are covered by mutation-checked
tests in `tests/Feature/AdminUxSettingsEnumTypesTest.php`.

## Gotchas a phase-3 session will hit

- **Filament action-modal content is not in the Livewire HTML at `mountAction`
  time.** `assertSee()` cannot reach helper text or field prose inside a modal —
  77 KB of returned HTML contained none. Phase 3's repair actions are modal
  surfaces; assert at source level or via a rendered-form path instead.
- **Mutation-check render assertions.** A bare `assertSee()` can be satisfied by
  unrelated page content. Strip the implementation and confirm the specific
  assertion fails before trusting it.
- **`MediaPickerBrowserTest`'s acquisition-workspace test had two unrelated
  intermittent failures. Do not re-run and move on** — the earlier advice to do
  so was wrong, and it hid the second one. Both were investigated on 2026-08-01
  over ~100 instrumented browser runs.
  - *`return_guard_released` false at line 306 — fixed; it was a test defect.*
    3/20 baseline runs, every other key in the payload true. The picker's
    `returningSelection` guard released correctly every time: Alpine's
    `uploading` and `returningSelection` were both `false`, and `inert`,
    `aria-busy` and `aria-disabled` were all absent. The `false` came from
    `disabled="true"` on the close button, written by the
    `wire:loading.attr="disabled"` that Filament merges into every icon-button
    (`filament/support/.../icon-button.blade.php:90`) for the duration of an
    in-flight Livewire request. The snapshot read a DOM property with two
    independent writers while gating only on the guard's own observables. Fixed
    by settling Livewire (`window.__mediaPickerPendingRequests === 0`) inside
    the wait: 0/30 after.
  - *"Multiple elements found for partial [action-modals]" — real, still open,
    and not a timing problem.* ~13% of runs; it breaks the picker for the rest
    of the page's life (6 repeated errors, ~64s timeout), so no wait or timeout
    can fix it. Two action-modal containers co-existing is **normal**: four do
    when the picker is open, each resolving to a distinct component, including
    `MediaPickerPanel`'s own nested inside its host's. Filament's `partials.js`
    separates them only by `findClosestLivewireComponent(el) ===
    message.component`, so if the picker root is ever uninitialised
    (`__livewire` unset) while the host's partial updates, both match and it
    throws. **Confirmed in production — see M2.** It is not a test artifact:
    two ordinary open/close cycles of the picker in a real browser reproduce it.
  - A third, rarer wait (1/30) timed out on the Storage panel listing its file,
    with no JS errors. Unclassified.
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
