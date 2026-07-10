# Public Front v2 Admin and Settings Enhancement Plan

Date: 09/07/2026 (v4 — supersedes v3/v2/v1; v3 added the flip-slider display-type and
result display-template builder work package, requests 12-17, steps SL1-SL4; v4 adds
the GSAP motion work package, requests 18-21, steps AX1-AX3, per Yoni's decisions:
foundation-first, expressive catalog including loading/transition concealment,
reveals + scroll-linked, per-template/section admin control)

## Purpose

This plan expands the post-M6 Public Front v2 continuation queue with Yoni's admin,
visual-settings, and settings-lifecycle requests before resuming the performance/cache
sequence. Version 2 folded in the deep-dive amendments: global Filament defaults instead
of per-file edits, a two-tier transcription resolution for the episode-list edit action,
the V1 split into V1a/V1b/V1c, the S2-before-S1a flip, the P1 settings-migration cache
watermark, and the persistent podcast-palette cache. Version 3 adds the flip-card
slider display type, quick-view modal, and reusable result display-template builder
(requests 12-17, mini-steps SL1-SL4), sequenced after P2/P3 and before B4/C2.

The central ledger remains authoritative for per-run selection:

`docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

## Current State

- Step 10R-M6 is complete and closed the M1-M5 plus IP1-IP3 arc; R1-R23 landed.
- Step 10R-C1 is superseded.
- The v1 addendum inserted UX1/UX2/V1/S1/S2; v2 split V1 into V1a/V1b/V1c and
  flips S2 before S1. The first implementation run must amend the ledger accordingly.
- This plan is documentation-only; no app code, migrations, or settings rows change here.

## Decisions

- D29: import locks are import-only everywhere. Inline locks on the Public Content
  Settings page toggle the existing `SettingsImportLocks` store, affect imports and
  dry-runs only, and must never make settings fields read-only or block normal settings
  form saves.
- D30: maintenance content is trusted admin-authored raw HTML and renders
  unsanitized only on the standalone maintenance response. Normal public pages must
  never render this raw HTML.
- D31: maintenance mode returns HTTP 503 with `Retry-After` in place for public URLs.
  It does not redirect and does not use Laravel's native `artisan down` mode.
- D32: settings-import result reports are anchored on the `before_import` backup row.
  The report is review metadata for that import and is deleted with the backup row.
- D33: the `sensitive` lifecycle semantic marks import units that must remain
  selectable but never be preselected by imported packages. Maintenance enablement and
  maintenance HTML fields are sensitive; importing them is strictly opt-in.

## Verified Code Facts Driving the Amendments

- `PublicItemPagePodcastPalette` (`app/Support/PublicFront/ItemPage/`) GD-decodes the
  cover and samples pixels on every call — no caching, no light/dark contrast handling.
- `PublicFrontCardTemplateRegistry::icons()` holds 17 finite keys; the icon picker work
  replaces this with a Heroicon-enum-backed registry plus compatibility aliases.
- `EditContentItem` is the only page using `hasCombinedRelationManagerTabsWithContent()`.
- Spatie settings migrations do NOT fire `SettingsSaved`; any persistent config cache
  must key on a settings-migration watermark, not rely on the save event alone.

## Active Sequence Addendum (v4 order)

| # | Step | Depends on | Request IDs | One-line scope |
|---|---|---|---|---|
| 1 | Step 10R-UX1 | M6 | 1, 6, 7, 8 | Admin navigation order via a central map, relation-manager tabs above content, record actions before columns, wide/full-width modal standards — set globally via `configureUsing()` defaults where supported. |
| 2 | Step 10R-UX2 | UX1 | 9 | Reusable effective-transcription edit action on episode lists with two-tier resolution and status-aware modal heading. |
| 3 | Step 10R-V1a | M6, UX1 | 2 | Default/no-image fallback settings with per-family `inherit\|custom\|none` modes and public fallback rendering. |
| 4 | Step 10R-V1b | V1a | 3 | Heroicon-enum icon registry + shared searchable icon-picker helper (lazy search results, live preview, legacy aliases). |
| 5 | Step 10R-V1c | V1a | 4, 5 | Custom hex color mode with ColorPicker + theme-safe podcast-palette sampling with persistent cache. |
| 6 | Step 10R-P1 | UX1, UX2, V1a-V1c | F1 | Cache validated public-front config (`public_front.config.v1`), keyed with the settings-migration watermark; palette cache shares the infrastructure. |
| 7 | Step 10R-S2 | P1 | 11 | Settings backup versions: schema, auto system backups on save (hash-deduped), manual backup, compare/download, retention, restore. |
| 8 | Step 10R-S2V | S2 | NEW | Backup visual snapshots: Playwright thumbnails/full captures, private storage, queued jobs, and gallery UI. S1a/S1b do not depend on S2V. |
| 9 | Step 10R-S1a | P1, S2 | 10 | Settings export and import wizard core: schema boundary, export, source validation, dry-run selection, replace-mode apply, before-import backup, and warnings. |
| 10 | Step 10R-S1b | S1a | 10 | Import locks and add-only mode: persistent locks, lock manager, hard wizard enforcement, add-only merge semantics, and outcome chips. Importer Workbench opens after S1b. |
| 11 | Step 10R-P2 | S1b | F2, F7, F12, F15 | Listing fetch-window, lazy options/form definitions, opt-in aggregate subselects. |
| 12 | Step 10R-P3 | P2 | F3 | Derived transcript segments and viewer render economy. |
| 13 | Step 10R-AX1 | P2 (bounded-fetch), P1 (cache conventions) | 18 | GSAP motion foundation: dependency, public-panel JS wiring, `PodTextMotion` preset registry + data-attribute contract, motion settings tokens, always-on reduced-motion policy, FOUC/SEO guard. |
| 14 | Step 10R-SL1 | AX1, M5 | 17 | Result display-template builder foundation: `display_templates` settings group (including per-template `motion` config), finite vocabularies, admin builder, surface selectors, grid default template. |
| 15 | Step 10R-SL2 | SL1 | 12, 14 | Flip-slider rendering engine: scroll-snap track, responsive columns×rows paging with bounded lazy page fetch, front-face cards, RTL-aware hover/always controls, AX1 entrance/load presets. |
| 16 | Step 10R-SL3 | SL2 | 13 | Flip animation and smart side-open back face rendered from existing card templates, implemented with the GSAP Flip plugin from AX1, server-computed logical open direction. |
| 17 | Step 10R-SL4 | SL2 | 15, 16 | Quick-view modal: flat app-owned modal embedding episode/podcast page content with density/label-size controls, a modal card template, and AX1 open/close choreography. |
| 18 | Step 10R-AX2 | AX1 (SL not required) | 19, 20 | Retrofit motion presets onto existing grids/sections/load-more + loading/update/page-transition concealment (Livewire loading choreography, morph-added staggers, cross-document View Transitions). |
| 19 | Step 10R-AX3 | AX2 | 21 | Scroll-linked effects: episode/podcast header parallax, transcript reading-progress bar, scroll-linked cover emphasis — ScrollTrigger, capped and reduced-motion-safe. |
| 20 | Step 10R-B4 | M1-M6, IP1-IP3, P1-P3, SL1-SL4, AX1-AX3 | F11 | Converge legacy card options with card presentation services (now covering slider/modal/motion surfaces). |
| 21 | Step 10R-C2 | B4 | F13 | Card layout consistency and semantic layout tokens (now covering slider front/back faces). |
| 22 | Step 9F-A → 9F-B → 9F-C | all 10R above | 9F | Rich homepage columns; footer config/renderer; admin polish. |
| 23 | Step 11 | all above + explicit Yoni approval | Step 11 | Seeders/demo/assets/cleanup incl. promoting the local evaluation seed, demo display templates, and demo motion presets. |
| 24 | Prompt 13 | explicit Yoni approval | Prompt 13 | Dashboard metrics. |

AX placement rationale: AX1 sits directly before SL1 (Yoni: foundation-first) so the
slider consumes motion natively; AX2/AX3 follow SL4 so the slider ships sooner, but AX2
does not depend on SL and MAY be pulled before SL1 by editing the ledger if
loading-concealment is more urgent than the slider.

Sequencing rationale for SL placement: SL builds on perf-hardened foundations (P1 cached
config, P2 bounded fetching) so the slider never ships with the F2-style over-fetch
pattern; B4/C2 run after SL so card-option convergence and semantic layout tokens cover
the new slider/modal surfaces instead of being reworked for them. Yoni may pull SL1-SL4
before P2/P3 by editing the ledger, accepting that SL2 must then implement its own
bounded fetching independently (that requirement holds in either order).

## Step 10R-UX1 Plan (amended)

Goal: normalize admin navigation and table/page UX before adding more settings and actions.

Scope:

- Navigation order (Podcasts `ContentGroupResource`, Episodes `ContentItemResource`,
  Transcriptions `TranscriptionResource`, Transcribers `AuthorResource`, Categories
  `CategoryResource`, Tags `ContentTagResource`, Form submissions
  `PublicFormSubmissionResource`, Homepage sections `HomepageSectionResource`, Settings
  `PublicContentSettings`) implemented through ONE `AdminNavigationOrder` support map
  (class => sort) consumed by each resource/page `getNavigationSort()`. Add a Pest
  completeness test asserting every registered panel resource/page has a map entry so
  future resources fail fast instead of landing unsorted.
- Global interaction defaults in a service provider (verify exact Filament 5.6 static
  APIs via Boost before relying on them; fall back to per-class configuration only where
  a component lacks the hook):
  - `Table::configureUsing(...)` sets `recordActionsPosition(RecordActionsPosition::BeforeColumns)`
    so record actions render before data columns while bulk checkboxes stay first.
  - `Action::configureUsing(...)` (and table-action equivalents) default
    `modalWidth(Width::SevenExtraLarge)`; individual dense forms may opt into
    `Width::Screen`; individual confirmations may opt down.
  - Default major `Section` components to `columnSpanFull()` for admin schemas, or apply
    per-schema where a global default is unsafe.
  - Document every intentional exception in the handoff.
- Relation managers as tabs above content: audit all edit/view pages exposing relation
  managers; enable combined relation-manager tabs with an EXPLICIT, pinned content-tab
  position (content tab first) so all pages present identically. Create pages that
  cannot show relation managers before a record exists are documented as not applicable.
- Larger relation-manager tab labels via admin theme CSS scoped to the combined-tabs
  container only; record the upgrade-fragility note (Filament class names may change).

Tests:

- Navigation order smoke test + map completeness test.
- Record-action position assertions on representative resource and relation-manager tables.
- Combined-tab behavior on `EditContentItem` and `EditContentGroup`.
- Existing admin resource smoke tests; FilaCheck full gate.

Out of scope: public navigation/menu behavior; new resources/clusters; settings
import/export/backups.

Commit: `feat: standardize admin navigation tables and modals`

## Step 10R-UX2 Plan (amended)

Goal: one reusable edit action for the episode's main transcription on episode lists.

Scope:

- One shared action class (e.g. `app/Filament/Actions/EditEffectiveTranscriptionAction`)
  mounted on `ContentItemsTable` (Episodes resource) and
  `ContentGroups\RelationManagers\ContentItemsRelationManager`.
- Two-tier resolution (this is the documented fallback policy): effective published
  transcription → featured transcription even if unpublished → latest transcription by
  id. Hide the action only when the episode has zero transcriptions. The modal heading
  shows the resolved transcription's title and status badge so admins always know what
  they are editing.
- Cross-model pattern: the row record is a `ContentItem`; the modal edits a
  `Transcription`. Use `->fillForm()` from the resolved transcription and `->action()`
  applying updates through the existing `TranscriptionForm` schema (minus
  `content_item_id`), persisting transcribers via `Transcription::syncTranscribers()` so
  multi-transcriber pivot state and `transcriptions.author_id` compatibility stay
  synchronized.
- `extraModalFooterActions()`: link to the full transcription edit page for the
  big-workspace case.
- Optional context column on both lists: effective transcription status/title, sourced
  from the existing M3/M4 aggregate selects (zero extra queries).
- Wide modal + full-width sections come from UX1 defaults.

Tests: action present on both surfaces; edits title/body/status/transcribers of the
resolved transcription; two-tier resolution covered (published, featured-unpublished,
latest-only fixtures); hidden with zero transcriptions; public rendering and the bounded
query-count harness stay green.

Out of scope: associate-existing transcription; studio/autosave; public transcript changes.

Commit: `feat: add effective transcription edit action to episode lists`

## Step 10R-V1a Plan (default/no-image fallbacks)

Goal: admin-managed fallback images with an explicit "no image" mode.

Settings shape:

```json
{
  "default_images": {
    "content_item": { "mode": "inherit", "path": null },
    "content_group": { "mode": "inherit", "path": null },
    "contributor": { "mode": "inherit", "path": null },
    "global": { "mode": "inherit", "path": null }
  }
}
```

- `mode`: `inherit` (current behavior), `custom` (use uploaded `path`), `none`
  (suppress the fallback chain and force the initials/placeholder block). `none` is the
  literal "no image" half of the request that a nullable upload cannot express.
- Registry defaults + validator normalization + settings migration +
  `PublicFrontRenderContext` accessor + admin fields on the settings page.
- Uploads: public disk, constrained directory, accepted image MIME types, max size,
  image preview, `columnSpanFull()`.
- Public fallback order stays specific-before-generic: episode surfaces
  item-image → podcast cover → content_item fallback → global fallback; podcast surfaces
  cover → content_group fallback → global; contributor surfaces contributor image →
  contributor fallback → global. `none` at any configured level stops the chain.
- No remote image fetching during public rendering.

Tests: normalization/migration; each mode per family on card + page surfaces
(fixture-owned); precedence preserved; harness green.

Commit: `feat: add default image fallback settings`

## Step 10R-V1b Plan (icon registry + picker)

Goal: replace the 17-key icon list with a Heroicon-enum-backed registry and one shared
searchable picker used by every icon setting (card templates, `item_page`, future).

- App-owned `PublicFrontIconRegistry` built from `Filament\Support\Icons\Heroicon`
  cases; stored tokens are validated enum-name strings; the 17 legacy keys
  (`calendar`, `document`, `podcast`, `arrow_right`, ...) remain permanent aliases with
  a test asserting every legacy key resolves.
- One shared form helper (e.g. `IconSelect::make()`), adapting Yoni's selected
  FilamentExamples reference (Icon Picker Select Field with Live Icon Display —
  `filamentexamples.com/project/filament-v4-filament-icon-select-field-with-preview`):
  `searchable()`, `allowHtml()`, HTML option labels with a live icon preview.
- LAZY results, not preloaded: use `getSearchResultsUsing()` + `getOptionLabelUsing()`
  so hundreds of enum cases are never materialized into the form payload; add a static
  per-request cache for label rendering. HTML labels are generated app-side from the
  enum only — never from stored strings.
- Rendering stays exclusively through `PublicFrontCardIconResolver` (extended for enum
  tokens + aliases); JSON never carries raw component names/SVG.

Tests: expanded options normalize; invalid values fall back; legacy aliases render;
picker search returns expected subsets; settings page render stays performant
(no full-enum option payload); harness green.

Commit: `feat: expand icon settings with searchable heroicon picker`

## Step 10R-V1c Plan (custom colors + theme-safe palette)

Goal: safe custom colors and podcast-image sampled colors that are always dark on light
theme and light on dark theme.

- Custom color mode: wherever a finite color token select exists, add a `custom` option
  revealing a `ColorPicker` (visible only when selected). Store strict `#rrggbb` only;
  validator normalizes 3-digit hex to 6-digit lowercase and rejects anything else. This
  is the single sanctioned exception to finite tokens — record it as decision D9 in
  `public-front-v2-transcription-display-decisions.md`.
- Rendering reality: dynamic hex can never be a Tailwind class (JIT cannot see it).
  Custom and sampled colors render only as CSS custom properties via inline `style`
  attributes from validated hex.
- Palette: extend `PublicItemPagePodcastPalette` to produce BOTH theme variants per
  sample — convert to HSL, clamp lightness (light theme ≤ ~0.4, dark theme ≥ ~0.65),
  and enforce a WCAG ≥ 4.5:1 contrast target against the theme background while
  preserving hue/saturation. Fall back to semantic colors when GD is unavailable, the
  cover is remote, or unreadable.
- PERSISTENT PALETTE CACHE (required): cache computed palettes keyed by cover path +
  file mtime (versioned key, no tags) so GD decoding never runs per public request.
  This cache shares the P1 infrastructure/conventions; if V1c lands before P1, use a
  plain versioned `Cache::rememberForever` key now and align naming in P1.

Tests: hex validation/normalization; custom mode reveals picker and renders style vars;
sampled palettes are contrast-normalized per theme (deterministic fixture image);
palette cache hit avoids re-decoding (assert single computation); no remote fetching;
harness green.

Commit: `feat: add custom colors and theme safe podcast palette`

## Step 10R-P1 Plan (amendments only)

Unchanged goal: cache the validated public-front config behind the scoped context with
versioned key `public_front.config.v1`, invalidated on `SettingsSaved`; Forge env
checklist in the handoff.

Injected requirements:

1. The cache key MUST incorporate a settings-migration watermark (count or latest
   settings-migration name) because Spatie settings migrations do not fire
   `SettingsSaved`; without the watermark every future settings migration would serve a
   stale config until the next admin save.
2. Adopt/align the V1c palette cache under the same conventions (documented key naming,
   invalidation notes); palette entries stay content-addressed (path + mtime) and do not
   need the settings watermark.
3. Corrupted/missing cache falls back to a fresh validated read; tests cover
   save-visibility, migration-watermark rotation, and fallback.

Commit: `perf: cache validated public front config`

## Step 10R-S2 Plan (backups — now BEFORE S1)

Goal: admin-only settings backup versions and restore, delivering the safety net before
import/export needs it.

Schema:

```text
settings_backup_versions
- id
- scope (string, e.g. public_content)
- label (nullable string)
- payload_json (longText/json per project convention; MySQL+SQLite compatible)
- checksum (string)
- payload_hash (string, indexed — dedupe key)
- source: manual|before_import|before_restore|system
- created_by_user_id (nullable FK)
- created_at / updated_at
```

- Single shared serializer `PublicSettingsPackage` (schema version, generated-at, app
  version, settings-migration watermark, settings group, verbatim full Spatie payload,
  checksum) —
  the SAME format S1a exports and the backup download produces. Build it here; S1a reuses it.
- Add `settings_backups` inside `public_content` now so S2V needs no second settings
  migration: `thumbnail_max_width` 400|600|800, `snapshot_formats` png|pdf|html, and
  `snapshot_themes` light|dark. No public render-context accessor is needed in S2.
- Automatic `system` backup on every `PublicContentSettings` save, deduped by
  `payload_hash` (identical consecutive payloads skip), retained last N (finite, e.g. 25)
  with prune-on-create — no scheduler dependency.
- Manual backup, download (package format), compare (grouped changed-keys diff of
  normalized payloads), restore. Restore: create `before_restore` backup → apply inside
  a DB transaction → invalidate settings + public-front config caches via the P1 path.
- Admin-only; no public exposure of `User` or backup metadata.

Tests: manual + system backup creation and dedupe; retention prune; restore round-trip
with cache invalidation; before-restore backup created; unauthorized access absent;
MySQL/SQLite-compatible schema.

Commit: `feat: add settings backup versions and restore`

## Step 10R-S2V Plan (backup visual snapshots)

Goal: add visual context to backup rows without blocking backup creation or restore.
S2V is inserted after S2 as a NEW step. S1a/S1b have no dependency on S2V and can run first if
Yoni selects it explicitly.

Research refinements:

- Playwright is already available in the JavaScript toolchain; S2V reuses it.
- v1 captures desktop only. Mobile snapshots stay later.
- System backups get thumbnails only. Manual, before-import, and before-restore backups
  may get full snapshot sets by policy.
- Snapshot failures never fail or block the backup row.

Engine:

- Move Playwright to the runtime dependency set if production queue workers need it.
- Add `scripts/settings-snapshots.mjs` taking a JSON job file with finite targets:
  URL, screen key, theme, formats, full/thumbnail mode, max width, and output paths.
- Use Chromium. Set the public theme through the same persistence path as the public
  theme selector. Do not hack CSS.
- Capture full-page PNG, PDF, and reference-only HTML. Thumbnails are scaled to
  `settings_backups.thumbnail_max_width`.
- Laravel runs the script from a queued `SettingsBackupSnapshotJob` via `Process`.
  Jobs write per-shot rows sequentially; each shot can fail independently.

Manifest:

- Screens: `home`, `search`, `podcasts`, first public podcast, first public episode,
  `contributors`, and first public contributor.
- Themes come from `settings_backups.snapshot_themes`.
- Desktop viewport width is 1440px.
- Base URL is `config('app.url')`.

Policy:

- Every backup source gets two thumbnail shots: home and podcasts.
- Full sets cover all manifest screens, selected themes, and selected formats for
  manual, before-import, and before-restore sources.
- The manual-backup modal later adds format/theme controls prefilled from
  `settings_backups`.

Schema/UI:

- Add `settings_backup_snapshots`: backup FK cascade, screen key, theme, viewport, kind
  `thumbnail|full`, format, resolved URL, private-disk path, status `pending|done|failed`,
  nullable error, timestamps.
- Store files under private disk `settings-backups/{backup_id}/`.
- The backup table gains the home thumbnail as row identity.
- A Snapshots action opens a gallery with screen tabs, theme switcher, scrollable full
  image container, per-shot download, download-all zip, PDF/HTML links where present,
  and per-shot retry for failures.

Deploy notes:

- Forge/server setup must run `npx playwright install chromium --with-deps` once.
- The queue worker user must be allowed to execute Chromium.
- `APP_URL` must point to the public site that the worker can reach.

Tests:

- Process-faked job creates expected pending rows and command contract.
- System source creates thumbnails only; manual source creates the full configured set.
- Per-shot failure marks that row failed and continues.
- Gallery renders rows plus scroll-container markers.
- Image column appears on the backup table.
- Deleting/pruning a backup removes private snapshot storage.

Commit: `feat: add backup visual snapshots`

## Step 10R-S1a/S1b Plans (settings lifecycle import/export)

Goal: versioned JSON settings export/import built on S2 backups, with lifecycle
machinery that survives the next settings-structure changes.

### Binding design decisions D21-D28

- D21 — one schema boundary. `SettingsLifecycleSchema` is the only component that knows
  the settings shape. It exposes managed groups, selectable units, structural type per
  path derived from registry defaults/current payload, expected scalar PHP types via
  reflection, and label translation keys with graceful raw-path fallback.
- D22 — segmentation is a policy, not a hard depth. Scalars are their own units; array
  groups default to first-level units; per-path overrides live in one lifecycle overlay.
- D23 — semantic overlay + drift guard. The overlay owns non-derived semantics such as
  front-facing free text and asset paths, and a drift test asserts every overlay path
  still exists in merged defaults.
- D24 — persistent import locks. `import_locks` lives inside `public_content`, validates
  locked paths against the schema service, is never importable, and is hard-enforced by
  the wizard. S2 backup restore ignores locks and restores them verbatim.
- D25 — import modes. Each import run chooses `replace` or `add_only`; add-only merges
  associative maps by current-wins recursive union and fills lists/scalars only when
  current is empty. Locks beat mode and outcome chips show the server resolution.
- D26 — backups are import sources. Wizard source step offers JSON upload or an existing
  backup row, using its `payload_json` package for selective restore.
- D27 — packages survive restructures. `PublicSettingsPackage` gains a schema-versioned
  payload upgrade pipeline; v1 is identity and future versions map old paths to new.
- D28 — group-parametric lifecycle. A registration point maps group name to settings
  class, defaults provider, and overlay. v1 registers only `public_content`.

### Step 10R-S1a - Settings export and import wizard core

Scope:

- Add `SettingsLifecycleGroups` and `SettingsLifecycleSchema` per D21/D22/D23/D28, with
  an overlay skeleton for the current public-content structure and a drift test.
- Add a v1 identity upgrade pipeline to `PublicSettingsPackage` per D27.
- Refactor diff labels/grouping to consume the schema service while keeping generic
  diff mechanics.
- Add export header actions on the Public Content Settings page and backups list that
  stream `PublicSettingsPackage::fromCurrentSettings()` JSON.
- Add a dedicated import wizard admin page launched from the backups list header action:
  source upload or backup source, checksum/newer-schema refusal, watermark warning,
  scalar type-check errors, dry-run selection table, missing-file warnings, validator
  warnings, confirm/apply summary.
- Apply replace mode only in S1a: create a `before_import` backup, apply selected units
  through the settings instance inside a transaction, validate lifecycle groups, and
  rely on the existing settings-save listener for cache invalidation.

Tests:

- Derived-count export/import round trip; partial selection on scalar and nested unit;
  tri-state group toggle semantics; tamper/newer-schema refusal; watermark warning;
  scalar type mismatch error row; missing-file warning; backup-as-source parity;
  identity upgrader; before-import backup; cache invalidation; guest/unauthorized
  blocking; drift test; bounded public harness.

Commit: `feat: add settings export and import wizard`

### Step 10R-S1b - Import locks and add-only mode

Scope:

- Add `import_locks` inside `public_content` with `locked_paths`, validated against the
  schema service. Unknown/stale paths are dropped with warnings.
- Add a lock manager header action on the backups list, reusing the S1a selection-table
  component in lock mode with group/unit lock toggles, "Lock all front texts", and
  "Unlock all".
- Integrate locks into the wizard: locked rows are greyed, lock-iconed,
  forced-unselected, included in excluded-count summaries, and server-enforced.
- Add `replace | add_only` mode selector and a pure merge engine. Add-only recursively
  adds new map keys while current wins on conflicts, and fills lists/scalars only when
  current is empty.

Tests:

- Lock persistence; front-text preset paths derived from overlay; locked unit excluded
  even if forged selected server-side; `import_locks` never importable; restore ignores
  locks and restores lock values; add-only map/scalar/list behavior; lock beats mode;
  outcome chips; drift test remains green; bounded public harness.

Commit: `feat: add settings import locks and add-only mode`

## Requests 12-17 — Flip-Slider Display Type and Result Display Templates (v3)

Consolidated from Yoni's three request formats (short, long, list). Numbering continues
the addendum's request IDs:

12. Flip-slider display type for podcast or episode cards: image front face with
    configurable badge slots, title at top or bottom with an overlay background token.
13. Interaction: hover/click flips the card to an information back face; alternative
    `side_open` mode where the back face opens beside the image and covers part of the
    slider grid, with SMART direction: corner cards always open toward the inner side of
    the screen; bottom-row cards open upward, never downward.
14. Slider layout: columns configurable 6 → 1; ~3 rows per slide page so page size =
    columns × rows (6 cols → 18/page, 5 cols → 15/page); mobile defaults to 2 × 3 = 6;
    gap tokens including NONE (seamless); prev/next controls hidden on desktop unless
    the slider is hovered (and hidden while a card back is open — resolves the
    short-vs-long format contradiction), always visible on touch devices.
15. Quick-view modal: clicking the image/back face (anywhere that is not an explicit
    action link) opens the episode page content — or the podcast all-episodes content —
    in a modal. Modal is deliberately NOT Filament-styled: flat background, section
    separators/borders, the image as the HEADER BACKGROUND (not image-on-the-side),
    closable only by a corner X or outside press (ESC retained for accessibility).
16. Modal density controls: label size `sm|md|hidden` (icons-only when hidden); reduced
    children (fewer episodes, smaller episode cards); a SEPARATE card template selection
    for modal results.
17. All of it packaged as a reusable RESULT DISPLAY TEMPLATE builder: create several
    named configurations (slider layout + card faces + open behavior + modal config) and
    choose which homepage section or listing page uses which, repeatable for both
    podcasts (content groups) and episodes (content items).

### Design decisions D10-D14 (defaults; Yoni may override in review)

- D10 — Back face = an existing card template. The back face and the modal results each
  reference a normal `card_templates` entry (per family). No second parts schema is
  invented; M5 labels/icons/groups work on back faces for free.
- D11 — Display templates generalize, not specialize: `display_templates` entries carry
  `display_type: grid | flip_slider` (extensible to future types), so today's grid
  rendering becomes the default display template and every listing surface selects a
  display template key. This is the literal "section template builder" request.
- D12 — Slider mechanics are platform-first: CSS scroll-snap track + Alpine for
  controls/state; NO third-party carousel/JS library without explicit approval.
  Page fetching is server-side and bounded: first page (columns × rows, computed from
  the LARGEST configured breakpoint) rendered initially; subsequent pages lazy-load
  through Livewire on navigation. The slider must never fetch-all-hydrate-all (the F2
  anti-pattern).
- D13 — Smart open direction is computed server-side from grid position (column/row
  index known at render time) using LOGICAL sides (start/end) so RTL works by
  construction; a light Alpine fallback recomputes on viewport resize. One card open at
  a time; opening a card closes the previous one.
- D14 — Accessibility and touch are requirements, not extras: keyboard navigation for
  slider controls; focus trap + ESC close in the modal; `prefers-reduced-motion`
  replaces flip with fade; touch devices flip on tap and always show controls; deep
  "open full page" link inside modal and on the back face so no content is
  modal-only/SEO-invisible (public URLs remain the crawlable source of truth).

## Step 10R-SL1 Plan (display-template builder foundation)

Goal: the settings/registry/admin foundation for reusable result display templates.

Scope:

- New `display_templates` settings group (repeater/builder in the settings page, modeled
  on `card_templates`): entries carry `key`, `label`, `result_family`
  (`content_item|content_group`), `display_type` (`grid|flip_slider`), and type-specific
  config:
  - shared: `columns` (1-6), `rows` (1-3, default 3), `gap`
    (`none|compact|comfortable|spacious`), `card_template_key` (validated against the
    matching family);
  - flip_slider: `title_position` (`top|bottom`), `overlay` token
    (`none|soft|strong`), badge slots (limited finite parts subset positioned
    `top_start|top_end|bottom_start|bottom_end`), `back_mode` (`flip|side_open`),
    `back_card_template_key`, `controls_visibility` (`hover|always`),
    `open_modal_on_click` (bool);
  - modal config: `modal_card_template_key`, `label_size` (`sm|md|hidden`),
    `children_limit` (finite range), `modal_density` (`compact|comfortable`).
- Registry defaults (a `default_grid` per family reproducing current grid behavior for
  compatibility) + validator normalization + settings migration +
  `PublicFrontRenderContext::displayTemplates()`.
- Surface selectors: `display_template_key` on `HomepageSection.display_config`, the
  podcasts page (group index + group-page episode grid), and search/latest defaults —
  selects populated per family via a resolver `optionsForFamily()` mirroring the card
  template resolver.
- No public rendering change yet beyond the default-grid passthrough; SL2 owns rendering.

Tests: normalization/defaults/migration; invalid tokens and unknown template-key
references rejected safely (fallback to default grid); admin builder saves entries;
selects populated; harness green.

Commit: `feat: add result display template builder foundation`

## Step 10R-SL2 Plan (slider rendering engine)

Goal: render `flip_slider` display templates on real surfaces.

Scope:

- Public slider rendering (Livewire component or section-renderer extension) consuming a
  resolved display template: scroll-snap track of slide pages, each page a
  columns × rows grid of FRONT faces (image with existing fallback chain incl. V1a
  defaults, badge slots, title top/bottom with overlay token classes).
- Responsive behavior: columns derive from fixed breakpoint maps (PHP/Blade class maps,
  no raw classes in JSON); page size = columns × rows per breakpoint; mobile default
  2 × 3.
- Bounded fetching per D12: initial page server-rendered; next pages lazy-loaded on
  navigation; total window capped by the section's existing limit config; query-count
  harness extended to a slider fixture and green.
- Controls: prev/next with `hover|always` visibility (desktop hover-reveal; always on
  touch), RTL-flipped direction and icons, keyboard accessible, page indicators.
- Wire into homepage sections + podcast surfaces selected via SL1 keys; grid display
  templates keep rendering through the existing grid path.

Tests: rendered slider markup (pages/columns/gap markers incl. `none`), RTL direction
markers, lazy page fetch (bounded counts asserted), controls visibility modes, fallback
to default grid on invalid template, harness green.

Commit: `feat: render flip slider display sections`

## Step 10R-SL3 Plan (flip and smart side-open back face)

Goal: the interactive back face.

Scope:

- Back face renders the configured card template's parts (D10) prepared in the same
  presenter pass as the front face — zero additional queries per card.
- `flip` mode: CSS 3D transform driven by Alpine state (hover intent on desktop, tap on
  touch); one open card at a time; `prefers-reduced-motion` fade variant.
- `side_open` mode: back face expands beside the card over the grid within the slider
  viewport; direction from server-computed logical position per D13 (corner → inner
  side, bottom row → upward), Alpine resize fallback; z-index/overflow contained to the
  slider; controls hidden while a card is open.
- All interaction state is Alpine-local; nothing persists server-side.

Tests: back-face parts render from the configured template; direction data attributes
correct for corner/bottom fixtures in LTR and RTL; single-open behavior markers;
reduced-motion class present; harness green.

Commit: `feat: add card flip and smart side open behavior`

## Step 10R-SL4 Plan (quick-view modal)

Goal: the flat quick-view modal per requests 15-16.

Scope:

- App-owned Blade/Alpine modal component (NOT Filament-styled): flat background,
  bordered/separated sections, image-as-header-background with overlay for text
  contrast, corner X + outside-press + ESC close, focus trap, scroll lock.
- Content: for episodes, the episode page content (header + transcript summary +
  actions) rendered by a lazy-mounted Livewire quick-view component reusing
  `PublicContentItemQueries`/existing presenters; for podcasts, the podcast episodes
  browser embedded with density props. Modal content loads ON OPEN only (lazy Livewire),
  never preloaded per card.
- Density/label controls from the display template's modal config: `label_size`
  (`sm|md|hidden` → icons-only), `children_limit`, smaller episode-card variant via the
  configured `modal_card_template_key`.
- Deep-link affordance: visible "open full page" action in the modal header/footer;
  clicking explicit action links inside cards still navigates normally (modal only
  intercepts the non-action surface per request 15).
- No URL swap in v1 of this feature (recorded as a future decision if wanted).

Tests: modal opens from front/back non-action clicks and NOT from explicit action
links; lazy mount asserted (no modal queries until opened); label-size and
children-limit behavior; close paths (X, outside, ESC); focus trap markers; episode and
podcast variants; harness green.

Commit: `feat: add quick view modal for slider cards`

## Requests 18-21 — GSAP Motion Work Package (v4)

Research basis: GSAP and ALL formerly-paid plugins (ScrollTrigger, Flip, SplitText,
ScrollSmoother, MorphSVG, Inertia) are 100% free including commercial use since
Webflow's April 2025 change. `package.json` currently has zero runtime JS dependencies
(Alpine ships inside Livewire/Filament); adding `gsap` is a new-dependency decision and
YONI HAS APPROVED IT for this package. Livewire integration uses `wire:ignore` for
GSAP-owned DOM plus Livewire morph hooks (`morph.added` per inserted element is the
load-more animation anchor — verify exact hook names against installed Livewire 4.3 via
Boost). SPA mode remains OFF (removed in `2b1c6b3`); page-to-page transitions therefore
use the native cross-document View Transitions API as progressive enhancement, NOT
`wire:navigate`.

18. Motion foundation: one `PodTextMotion` boundary; finite motion preset tokens per
    display template/section plus a global default; expressive catalog.
19. Expressive presets incl. hover micro-interactions and slider page choreography.
20. Loading/update/page-transition concealment: Livewire loading-state choreography,
    staggered content swap-in on updates, View Transitions between pages — animation
    conceals REAL waiting only.
21. Scroll-linked effects: reveals via `ScrollTrigger.batch(..., once: true)` plus
    scroll-linked header parallax, transcript reading-progress bar, cover emphasis.

### Motion decisions D15-D20 (defaults; Yoni may override in review)

- D15 — GSAP enters behind ONE app-owned boundary (`PodTextMotion` registry + Alpine
  data-attribute contract, e.g. `data-motion="fade_up_stagger"`). Blade/presenters emit
  finite tokens only; JSON settings never contain durations/easings/raw values; the JS
  registry maps tokens → timelines. Same philosophy as the icon/color resolvers.
- D16 — Catalog is expressive per Yoni: entrance (`none|fade_up|fade_up_stagger|
  scale_in|slide_start|flip_reveal`), hover (`none|lift|tilt`), load-more
  (`stagger_in|fade_in`), loading (`skeleton_shimmer|pulse|none`), transition
  (`view_transition|none`), with `stagger: tight|normal|relaxed` and
  `duration: fast|normal|slow`. "start"-based directions keep RTL correct.
- D17 — Scroll-snap remains the slider transport (D12 stands); GSAP never takes over
  scrolling. ScrollSmoother is BANNED (scroll-jacking vs RTL/a11y/Livewire is a bad
  trade for a content site).
- D18 — Page transitions = cross-document View Transitions API (progressive
  enhancement; unsupported browsers get instant navigation). SPA mode stays off;
  revisit only if View Transitions coverage proves insufficient.
- D19 — `prefers-reduced-motion` always wins via `gsap.matchMedia()`; it is NOT an
  admin setting. Reduced-motion users get opacity-only or no animation.
- D20 — Never add artificial latency: loading/transition animations conceal real waits
  only; no minimum-duration loaders; entrance animations must not delay LCP or hide
  above-the-fold content for no-JS/crawlers (initial hidden states set by JS, not CSS).

## Step 10R-AX1 Plan (motion foundation)

Goal: adopt GSAP once, correctly, behind one boundary, before the slider consumes it.

Scope:

- `npm i gsap` (approved); import in the public JS bundle; register only ScrollTrigger
  and Flip in AX1. VERIFY FIRST where public-panel JS actually loads today (Filament
  panels do not auto-include `resources/js/app.js`) — wire the bundle via the panel's
  asset registration or render hook and record the mechanism in the handoff, including
  the built bundle size before/after.
- `PodTextMotion` module: preset registry (D16 tokens → GSAP timelines/config), Alpine
  directive or `data-motion-*` attribute contract, group/stagger orchestration,
  `gsap.matchMedia()` reduced-motion gating (D19), FOUC/SEO guard (content visible by
  default; JS applies initial states then animates).
- Livewire bridges: `morph.added` handler animating only newly inserted nodes tagged
  with motion attributes; `ScrollTrigger.refresh()` after appends; `wire:ignore`
  guidance for GSAP-owned DOM documented in the module.
- Settings: `motion` config group — global defaults in `display_defaults.motion` plus
  per-homepage-section and (from SL1) per-display-template overrides; finite tokens per
  D16; registry defaults + validator + settings migration + render-context accessor +
  translated admin fields (en+he).
- No visible behavior change yet beyond an optional single demo surface; AX2/SL own
  broad application.

Tests: token normalization/migration; data-attribute contract emitted from presenters;
reduced-motion gating markers; FOUC guard (content present without JS-applied classes);
harness green; bundle builds.

Commit: `feat: add gsap motion foundation and presets`

## Step 10R-AX2 Plan (retrofit + loading/transition concealment)

Goal: apply motion to existing surfaces and make waiting feel intentional.

Scope:

- Retrofit entrance/load-more presets onto existing homepage sections, latest/search
  grids, podcast episode grids, contributor grids — per-section/template settings from
  AX1; `ScrollTrigger.batch(..., once: true)` for on-scroll reveals.
- Livewire update concealment: loading-state choreography coordinated with
  `wire:loading` targets (skeleton shimmer/pulse presets on the results region),
  staggered swap-in of updated content via morph hooks instead of content "popping".
- Page-to-page transitions per D18: `@view-transition` opt-in CSS + named transition
  elements for stable chrome (header/logo), GSAP-enhanced where it adds value;
  unsupported browsers degrade to instant navigation; no artificial delay (D20).
- Slider integration check: SL2/SL3/SL4 surfaces already consume AX1 presets; AX2
  verifies homepage sections using sliders and grids compose motion consistently.

Tests: new-node-only stagger on load-more (existing nodes untouched); loading preset
markers appear during `wire:loading` and vanish after; view-transition opt-in present;
reduced-motion path; no-JS content visibility; harness green.

Commit: `feat: add loading and page transition motion`

## Step 10R-AX3 Plan (scroll-linked effects)

Goal: the scroll-linked layer, capped and safe.

Scope:

- Episode/podcast page header parallax (cover background subtle translate/scale,
  transform-only, small amplitude).
- Transcript reading-progress bar on the episode page (scroll-linked, client-only,
  pairs with the IP3 reading controls; no server state).
- Optional scroll-linked cover/palette emphasis on podcast pages (uses V1c palette
  variables).
- All via ScrollTrigger with `scrub`; transform/opacity only; disabled entirely under
  reduced motion; no pinning that changes document height on mobile unless proven
  CLS-free.

Tests: markers/data hooks present; progress bar bounds; reduced-motion disable;
no CLS on fixture pages; harness green.

Commit: `feat: add scroll linked motion effects`

## Impact on Existing Queue

- P1 waits for UX1/UX2/V1a-V1c (settings shape churn finishes first); with the
  migration-watermark key this ordering is a convenience, not a correctness requirement.
- S2 before S1a removes the v1 plan's "backup-before-import if S2 storage exists" interim
  behavior — the safety net exists before import ships.
- SL1-SL4 run after P2/P3 so the slider is built on cached config and bounded-fetch
  discipline, and before B4/C2 so option convergence and semantic layout tokens cover
  the slider/modal surfaces. If Yoni pulls SL earlier, SL2's bounded-fetch requirement
  still applies in full.
- B4/C2 scopes now explicitly include the slider front/back faces and modal card
  surfaces. 9F rich sections remain content sections and stay separate from result
  display templates; they share the render-context/registry patterns only.
- Step 11 gains: seed one or two demo display templates alongside the evaluation card
  templates.
- P2/P3/9F/Step 11/Prompt 13 otherwise keep their prior scopes, owners, and guardrails.

## Stop Conditions

- Stop before app-code changes if the ledger and current state disagree on the first
  pending mini-step, or if the ledger still shows the v1 rows (single V1, S1-before-S2)
  and this v4 plan has not been applied to the ledger in the same run's docs step.
- Stop if custom-color implementation would require storing anything other than strict
  normalized hex.
- Stop if palette sampling would require remote fetches or per-request GD decoding after
  the cache is specified.
- Stop if a `configureUsing()` global default is unavailable in installed Filament 5.6
  for a needed component AND per-class fallback would contradict the standard — record
  and continue with per-class application instead.
- Stop if a create page cannot support relation managers; document non-applicability.
- Stop if settings import/export scope expands into demo/content seeding (Step 11).
- Stop if an SL step would require a third-party carousel/JS library — scroll-snap +
  Alpine (+ GSAP from AX1 for motion only) is the approved mechanism unless Yoni
  explicitly approves another dependency. `gsap` itself is approved (v4).
- Stop if any motion work would introduce ScrollSmoother/scroll-jacking, take over the
  slider's scroll transport, add artificial loading latency, animate layout properties
  (width/height/top/left) instead of transforms/opacity, or hide above-the-fold content
  from no-JS visitors/crawlers.
- Stop if the public panel has no safe JS bundle attachment point — resolve the asset
  wiring question in AX1's plan before writing motion code.
- Stop if the slider or modal would fetch/hydrate all pages up front or preload modal
  content per card — bounded first-page fetch and lazy-on-open are requirements.
- Stop if modal content would become the only public path to any content (SEO/crawl
  regression); public URLs remain canonical and the deep "open full page" link is
  mandatory.
- Stop if display-template JSON would need raw classes/HTML/JS to express a requested
  behavior; extend the finite vocabularies instead.
