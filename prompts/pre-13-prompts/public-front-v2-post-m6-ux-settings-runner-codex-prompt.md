# Codex Master Prompt — Public Front v2 Post-M6 Admin/Settings/Performance Continuation Runner (UX1 First)

Work in the current local clone of `studioycm/PodText`.

This is the authoritative continuation runner after Step 10R-M6 and the post-M6
admin/settings enhancement planning addendum, updated to the v4 amended plan (v3 added
the flip-slider display-type and result display-template builder, requests 12-17, steps
SL1-SL4; v4 adds the GSAP motion work package, requests 18-21, steps AX1-AX3):

`docs/phase-02/public-front-v2-admin-settings-enhancement-plan.md`

Run this prompt repeatedly. Each run: preflight → select exactly the first pending
mini-step from the central ledger → research (Laravel Boost + FilamentExamples MCP) →
write the implementation plan → implement only that mini-step → focused tests → full
quality gate → update docs/ledger/state/handoff → commit → stop for Yoni/ChatGPT review.

Do not batch multiple implementation mini-steps into one run.

## Current reality to verify

- Steps A1-A2, B1-B3, M1-M6, HF1, IP1-IP3 are complete. R1-R23 landed and verified by M6.
  C1 is superseded. `transcription_display` defaults are `effective_only` (Yoni override).
- Latest expected commits include `ebfa68e` (M6 docs) and `6e7a74c` (post-M6 addendum docs).
- The amended v4 enhancement plan defines the active order:
  UX1 → UX2 → V1a → V1b → V1c → P1 → S2 → S1 → P2 → P3 → AX1 → SL1 → SL2 → SL3 → SL4 →
  AX2 → AX3 → B4 → C2 → 9F-A → 9F-B → 9F-C → Step 11 (approval-gated) → Prompt 13
  (approval-gated).
- FIRST RUN LEDGER AMENDMENT: if the ledger still shows the v1 rows (single `Step 10R-V1`;
  `S1` before `S2`; no SL/AX rows), amend it in this run's docs step to match the v4 plan:
  split V1 into `Step 10R-V1a/V1b/V1c` rows, reorder `Step 10R-S2` before `Step 10R-S1`,
  insert `Step 10R-AX1` after `Step 10R-P3`, insert `Step 10R-SL1/SL2/SL3/SL4` after AX1,
  insert `Step 10R-AX2/AX3` after SL4 and before `Step 10R-B4`, extend the B4/C2 row
  notes with "now covers slider/modal/motion surfaces", update the guardrail lines, and
  refresh `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md` to the
  v4 order. Then proceed with Step 10R-UX1 as the first pending implementation step.
- GSAP dependency: `npm i gsap` is APPROVED by Yoni for Step 10R-AX1 (the package plus
  all plugins are free including commercial use since April 2025). No other new JS
  dependency is approved.
- If repository reality contradicts any of this, stop and report before code changes.

## Deployment reality

- Production: Laravel Forge, MySQL, Redis, Horizon. Local: Herd; SQLite for dev/tests.
- All SQL and schema must run on MySQL AND SQLite. Caching uses single versioned keys,
  never cache tags. Env changes (`SETTINGS_CACHE_ENABLED`, cache store) are deploy notes
  in handoffs, never `.env` edits. Public rendering never waits on queued work.
- Spatie settings migrations do NOT fire `SettingsSaved` — any persistent config cache
  must key on a settings-migration watermark (P1 requirement below).

## Non-negotiable guardrails

Do not start a step before its dependencies in the v4 order are complete.
Do not run Step 11 or Prompt 13 without explicit Yoni approval; Prompt 14/15 never run from this runner.
Do not implement the full Step 2 publication workflow.
Do not create `Podcast`, `Episode`, `ContributorProfile`, `VolunteerProfile`, `PublicFooter`, `FooterSection`, `PublicMenu`, or `PublicMenuItem` models.
Do not expose `User` records publicly (including backup metadata).
Do not reintroduce public Filament Tables or item-level episode authors.
Do not remove `transcriptions.author_id`.
Do not store raw Tailwind classes, CSS, Blade paths, PHP class names, HTML, SVG, scripts, SQL, or arbitrary icon component names in JSON settings. The ONLY sanctioned exception is strict normalized `#rrggbb` custom colors (decision D9, Step 10R-V1c).
Do not fetch remote images during public rendering or run GD sampling per public request once the palette cache exists.
Viewer/browser preferences stay Alpine + localStorage, never Livewire server state.
Tests are fixture-owned, never depend on local seeded data or local settings values, and set `transcription_policy` / `transcription_display` explicitly where behavior depends on them.
The bounded public rendering query-count harness (`tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php`) must stay green every step.
New settings keys require registry defaults + validator normalization + a Spatie settings migration + render-context accessor + translated admin labels/helper text in `lang/en` AND `lang/he`; UI must stay RTL-safe; dates day-first in `Asia/Jerusalem`.
SL-series additions: no third-party carousel/JS libraries (scroll-snap + Alpine + GSAP
motion from AX1 only, unless Yoni explicitly approves another dependency); the slider
fetches the first page bounded and lazy-loads subsequent pages — never
fetch-all/hydrate-all; modal content lazy-mounts on open only, never preloaded per card;
every modal has a deep "open full page" link so no content becomes modal-only (public
URLs stay canonical for SEO); slider/flip/modal interaction state is Alpine-local only;
keyboard navigation, focus trap, ESC close, and `prefers-reduced-motion` fallbacks are
requirements, not extras.
AX-series (motion) additions: all motion goes through the ONE `PodTextMotion` boundary —
Blade/presenters emit finite preset tokens only (`data-motion-*`), never durations/
easings/raw values in JSON; `prefers-reduced-motion` always wins via `gsap.matchMedia()`
and is NOT an admin setting; ScrollSmoother/scroll-jacking is banned; GSAP never takes
over the slider's scroll transport; never add artificial loading latency — conceal real
waits only; animate transforms/opacity only, never layout properties; content must be
visible without JS (initial hidden states applied by JS, no-JS/crawlers see everything);
page-to-page transitions use the cross-document View Transitions API as progressive
enhancement — SPA mode stays OFF, do not re-enable `wire:navigate`; Livewire bridges use
`wire:ignore` for GSAP-owned DOM and morph hooks (verify exact hook names such as
`morph.added` against installed Livewire 4.3 via Boost before relying on them);
`ScrollTrigger.refresh()` after content appends; record the built public JS bundle size
before/after in the AX1 handoff.
Do not run `vendor/bin/filacheck --fix`. Do not push unless explicitly asked. Do not use worktrees or parallel agents. Do not use `php artisan model:show`.

## Required docs to read every run

- `AGENTS.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-admin-settings-enhancement-plan.md` (v4 — the detailed spec for UX/V/S/SL/AX steps)
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`
- `docs/phase-02/public-front-v2-transcription-display-decisions.md`
- `docs/phase-02/public-front-v2-performance-efficiency-audit.md` (findings F1-F16 statuses)
- `docs/research/public-front-v2/19-admin-settings-enhancement-mcp-research.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/phase-02/ai-development-lessons.md`
- The previous step's handoff.

Then inspect only the code relevant to the selected mini-step.

## Preflight every run

```bash
git status --short --branch
git log --oneline --decorate -40
php artisan migrate:status
php artisan route:list --path=podcasts
php artisan route:list --path=contributors
php artisan route:list --path=search
```

Confirm: clean working tree; ledger and current state agree on the first pending step;
Step 11 / Prompt 13 not started. If unexpected app-code dirt exists, stop and report.

## Required research every run

Laravel Boost: `application_info`, `database_schema`, `search_docs` before touching
Filament navigation/tables/actions/relation managers, `configureUsing()` component
defaults, FileUpload/ColorPicker/Select fields, Spatie settings, migrations, caching,
transactions, or Pest/Livewire assertions.

FilamentExamples MCP (`mcp__filament_examples.search_examples`): short query batches +
a refined second pass; record honest access level; research note at
`docs/research/public-front-v2/20-<mini-step-id>-mcp-research.md`.
For V1b use Yoni's selected reference: "Icon Picker Select Field with Live Icon Display"
(`filamentexamples.com/project/filament-v4-filament-icon-select-field-with-preview`) —
adapt the searchable `allowHtml()` Select pattern with LAZY `getSearchResultsUsing()` /
`getOptionLabelUsing()` results.
For SL steps, useful FilamentExamples/Boost query directions: builder repeater nested
config forms (SL1); Livewire lazy loading, pagination-on-interaction, scroll-snap
carousel patterns, Alpine `x-data` component patterns (SL2/SL3); modal/slide-over
component patterns, lazy Livewire mount, focus trap (SL4). Boost `search_docs` for
Livewire `#[Lazy]`, `wire:init`/load-on-interaction patterns, Blade component slots,
and Alpine directives before implementing.
For AX steps: Boost `search_docs` for Livewire JS hooks (`morph.added` and siblings),
`wire:loading` targets, Vite asset bundling, and Filament panel asset registration;
GSAP official docs/learning center for `gsap.matchMedia()`, `ScrollTrigger.batch`,
`Flip.from`, and register-plugin patterns; MDN for the cross-document View Transitions
API (`@view-transition`, `view-transition-name`).

## Per-mini-step implementation plan (before app code)

Create `docs/phase-02/public-front-v2-step10r-<id>-implementation-plan.md` with:
selected step + dependencies; current repo evidence; files inspected; Boost findings;
FilamentExamples findings; settings/render-context impact; admin/public impact;
query/cache impact; exact files to change; tests; risks; out-of-scope; stop conditions
(carry the step's stop conditions from the v4 plan doc).

---

# Mini-step definitions (v4 order)

The v2 enhancement plan doc is the detailed spec for UX1-S1; the blocks below are the
binding summary. Later steps carry their established definitions.

## Step 10R-UX1 — Admin navigation and table/modal standards

- One `AdminNavigationOrder` support map (class => sort) consumed by every resource/page
  `getNavigationSort()`; order: Podcasts, Episodes, Transcriptions, Transcribers,
  Categories, Tags, Form submissions, Homepage sections, Settings. Pest completeness
  test: every registered panel resource/page has a map entry.
- GLOBAL defaults via `configureUsing()` in a service provider (verify exact Filament
  5.6 APIs via Boost; fall back per-class only where the hook is missing):
  record actions `RecordActionsPosition::BeforeColumns` (checkboxes stay first);
  action `modalWidth(Width::SevenExtraLarge)` default (dense forms may use
  `Width::Screen`, confirmations may opt down); major admin `Section`s `columnSpanFull()`.
  Document every exception in the handoff.
- Relation managers as tabs above content on all edit/view pages that have them, with an
  explicit pinned content-tab-first position; document create-page non-applicability.
- Larger relation-manager tab labels via admin theme CSS scoped to the combined-tabs
  container; note upgrade fragility.
- Tests: nav order + completeness; action position on representative tables; combined
  tabs on `EditContentItem`/`EditContentGroup`; admin smoke suites; harness green.
- Commit: `feat: standardize admin navigation tables and modals`

## Step 10R-UX2 — Effective transcription edit action on episode lists

- One shared action class (e.g. `app/Filament/Actions/EditEffectiveTranscriptionAction`)
  on `ContentItemsTable` and the podcast `ContentItemsRelationManager`.
- TWO-TIER RESOLUTION (documented fallback policy): effective published → featured even
  if unpublished → latest by id; hide only when zero transcriptions exist; modal heading
  shows resolved transcription title + status badge.
- Cross-model modal: `fillForm` from the resolved `Transcription`, apply via the
  existing `TranscriptionForm` schema (minus `content_item_id`), persist transcribers
  through `Transcription::syncTranscribers()` (pivot + `author_id` compat stay in sync).
- `extraModalFooterActions()` link to the full transcription edit page.
- Optional: effective-transcription status/title context column on both lists using the
  existing aggregate selects (zero extra queries).
- Tests: presence on both surfaces; all three resolution tiers; edits incl. transcriber
  state; hidden at zero transcriptions; harness green.
- Commit: `feat: add effective transcription edit action to episode lists`

## Step 10R-V1a — Default/no-image fallback settings

- `default_images` settings group with per-family `{mode: inherit|custom|none, path}` for
  `content_item`, `content_group`, `contributor`, `global`; `none` suppresses the
  fallback chain and forces the initials/placeholder block.
- Registry + validator + settings migration + `PublicFrontRenderContext` accessor +
  admin FileUploads (public disk, constrained directory, image MIMEs, max size, preview).
- Fallback order stays specific-before-generic (item image → podcast cover →
  content_item fallback → global; cover → content_group fallback → global; contributor
  image → contributor fallback → global). No remote fetching.
- Tests: normalization/migration; each mode per family on card + page surfaces;
  precedence preserved; harness green.
- Commit: `feat: add default image fallback settings`

## Step 10R-V1b — Heroicon registry and shared icon picker

- `PublicFrontIconRegistry` backed by `Filament\Support\Icons\Heroicon` cases; stored
  tokens are validated enum-name strings; the 17 legacy keys remain permanent aliases
  (test: every legacy key resolves through `PublicFrontCardIconResolver`).
- One shared `IconSelect` form helper used by ALL icon settings (card templates,
  `item_page`, future): `searchable()`, `allowHtml()`, live icon preview, and LAZY
  `getSearchResultsUsing()` / `getOptionLabelUsing()` — never preload the full enum into
  the form payload; static per-request label cache; HTML generated app-side only.
- Rendering stays exclusively through the resolver; JSON never carries SVG/component names.
- Tests: normalization + fallback; alias compatibility; search subsets; settings page
  payload stays bounded; harness green.
- Commit: `feat: expand icon settings with searchable heroicon picker`

## Step 10R-V1c — Custom colors and theme-safe podcast palette

- `custom` color mode on finite color selects revealing a `ColorPicker`; store strict
  `#rrggbb` only; validator normalizes 3-digit → 6-digit lowercase. Record decision D9
  in the decisions doc (single sanctioned finite-token exception).
- Dynamic hex is NEVER a Tailwind class; render custom/sampled colors only as CSS custom
  properties via inline `style` from validated hex.
- `PublicItemPagePodcastPalette`: produce BOTH theme variants (HSL lightness clamp —
  light theme ≤ ~0.4, dark theme ≥ ~0.65 — with WCAG ≥ 4.5:1 contrast target vs theme
  background); semantic-color fallback when GD/image unavailable.
- REQUIRED persistent palette cache keyed by cover path + file mtime (versioned key, no
  tags) so GD never decodes per public request; align naming with P1 conventions.
- Tests: hex validation; picker reveal; deterministic fixture image yields
  contrast-normalized variants per theme; cache prevents re-decoding (assert single
  computation); no remote fetch; harness green.
- Commit: `feat: add custom colors and theme safe podcast palette`

## Step 10R-P1 — Validated public-front config caching (resolves F1)

- Cache the validated/normalized public-front config behind the scoped context with
  versioned key `public_front.config.v1`; invalidate on `SettingsSaved`.
- REQUIRED: the cache key incorporates a settings-migration watermark (count or latest
  settings-migration name) because settings migrations bypass `SettingsSaved`.
- Adopt/align the V1c palette cache under the same conventions (palette entries stay
  content-addressed by path + mtime; no watermark needed there).
- Corrupted/missing cache falls back to a fresh validated read. Handoff includes the
  Forge env checklist (`SETTINGS_CACHE_ENABLED`, Redis store notes).
- Tests: warm cache skips revalidation; save visibility; watermark rotation on a new
  settings migration; corrupted-cache fallback; harness green.
- Commit: `perf: cache validated public front config`

## Step 10R-S2 — Settings backup versions and restore (BEFORE S1)

- `settings_backup_versions` table: id, scope, label, payload_json, checksum,
  payload_hash (indexed, dedupe), source `manual|before_import|before_restore|system`,
  nullable created_by_user_id, timestamps. MySQL+SQLite compatible.
- Build the shared `PublicSettingsPackage` serializer HERE (schema version, generated-at,
  app version, settings-migration watermark, group list, normalized payload, checksum);
  S1 reuses it; backup download emits the same format.
- Automatic `system` backup on every `PublicContentSettings` save, deduped by
  payload_hash; finite retention (e.g. last 25) with prune-on-create.
- Manual backup, download, compare (grouped changed-keys diff of normalized payloads),
  restore (before_restore backup → transaction → apply → invalidate caches via P1 path).
- Admin-only; no public `User`/metadata exposure.
- Tests: creation + dedupe; retention; restore round-trip + cache invalidation;
  before-restore backup; authorization; cross-DB schema.
- Commit: `feat: add settings backup versions and restore`

## Step 10R-S1 — Settings import/export package (AFTER S2)

- Export = `PublicSettingsPackage` download of current normalized `public_content`.
- Import: upload → validate schema version + settings-migration watermark (refuse newer;
  warn older) + checksum → DRY-RUN grouped diff (with compare-to-latest-backup) → on
  confirm: `before_import` backup (S2) → transaction → APPLY-WITH-WARNINGS (normalized
  result saved; invalid-config entries surfaced as warnings) → invalidate caches via P1.
- Scope: Spatie `public_content` settings only; homepage sections/public forms excluded;
  demo/content seeding stays Step 11.
- Tests: export validity; dry-run without save; apply + backup + invalidation;
  newer-schema rejected; tampered checksum rejected; normalization warnings surfaced.
- Commit: `feat: add settings import export package`

## Step 10R-P2 — Public listing fetch-window and lazy options (resolves F2, F7, F12, F15)

- Remove the latest-section `max(50, ...)` fetch floor / fetch only the visible window
  or real per-section pagination; `ContentItemSearch` filter options become
  `#[Computed]`/lazy and skip homepage-section renders; memoize
  `PublicFormModal::definition()`; make unconsumed aggregate subselects opt-in per
  surface. URL state preserved; query-count assertions before/after.
- Commit: `perf: bound public listing queries and lazy filter options`

## Step 10R-P3 — Derived transcript segments and viewer economy (resolves F3 remainder)

- Persist `parsed_segments` + `word_count` on transcription save/import (optional
  Horizon backfill command); viewer renders from stored segments with safe live-parse
  fallback; one decided strategy for segment HTML (cached per transcription version or
  request-memoized); `SafeMarkdownRenderer` remains the only Markdown boundary.
- Tests: identical output for fixture transcripts; stale-segment regeneration;
  parse/query counts drop; long-transcript regression stays green.
- Commit: `perf: render transcripts from derived segments`

## Step 10R-AX1 — GSAP motion foundation (request 18)

- `npm i gsap` (approved); register ONLY ScrollTrigger and Flip. VERIFY FIRST where
  public-panel JS loads today (Filament panels do not auto-include `resources/js/app.js`)
  — wire the bundle via panel asset registration or a render hook, record the mechanism
  and built bundle size before/after in the handoff.
- `PodTextMotion` module: preset registry mapping finite tokens → GSAP timelines
  (entrance `none|fade_up|fade_up_stagger|scale_in|slide_start|flip_reveal`; hover
  `none|lift|tilt`; load-more `stagger_in|fade_in`; loading `skeleton_shimmer|pulse|none`;
  transition `view_transition|none`; `stagger tight|normal|relaxed`;
  `duration fast|normal|slow`), an Alpine directive or `data-motion-*` attribute
  contract, group/stagger orchestration, `gsap.matchMedia()` reduced-motion gating, and
  the FOUC/SEO guard (content visible by default; JS applies initial states).
- Livewire bridges: `morph.added` handler animating ONLY newly inserted nodes carrying
  motion attributes; `ScrollTrigger.refresh()` after appends; documented `wire:ignore`
  guidance for GSAP-owned DOM.
- Settings: `motion` tokens — global defaults in `display_defaults.motion`, per
  homepage section, and (from SL1 on) per display template; registry defaults +
  validator + settings migration + render-context accessor + translated admin fields
  (en+he). "start"-based directions keep RTL correct.
- No broad visible change yet (one demo surface allowed); AX2/SL own application.
- Tests: token normalization/migration; presenter-emitted data attributes; reduced-motion
  gating markers; FOUC guard (content present without JS); harness green; build passes.
- Commit: `feat: add gsap motion foundation and presets`

## Step 10R-SL1 — Result display-template builder foundation (request 17)

- New `display_templates` settings group (builder/repeater modeled on `card_templates`):
  entries = `key`, `label`, `result_family` (`content_item|content_group`),
  `display_type` (`grid|flip_slider`), shared config (`columns` 1-6, `rows` 1-3 default
  3, `gap` `none|compact|comfortable|spacious`, `card_template_key` validated against
  the family), flip_slider config (`title_position` `top|bottom`, `overlay`
  `none|soft|strong`, badge slots as a limited finite parts subset positioned
  `top_start|top_end|bottom_start|bottom_end`, `back_mode` `flip|side_open`,
  `back_card_template_key`, `controls_visibility` `hover|always`,
  `open_modal_on_click`), modal config (`modal_card_template_key`, `label_size`
  `sm|md|hidden`, `children_limit` finite range, `modal_density`), and per-template
  motion config (AX1 tokens: `entrance`, `hover`, `load_more`, `stagger`, `duration`
  validated against the AX1 vocabulary).
- Registry defaults include a `default_grid` per family reproducing current grid
  behavior (compatibility); validator normalization; settings migration;
  `PublicFrontRenderContext::displayTemplates()`; resolver with `optionsForFamily()`.
- Surface selectors: `display_template_key` on `HomepageSection.display_config`, podcast
  index/group-page settings, and search/latest defaults. No rendering change yet beyond
  the default-grid passthrough.
- Tests: normalization/defaults/migration; invalid tokens and unknown template
  references fall back to default grid; admin builder saves; selects populated;
  harness green.
- Commit: `feat: add result display template builder foundation`

## Step 10R-SL2 — Flip-slider rendering engine (requests 12, 14)

- Render `flip_slider` templates: CSS scroll-snap track of slide pages; each page a
  columns × rows grid of FRONT faces (image with the existing fallback chain incl. V1a
  defaults, badge slots, title top/bottom with overlay token); responsive columns from
  fixed breakpoint class maps; mobile default 2 × 3; page size = columns × rows
  (6 → 18, 5 → 15).
- Bounded fetching: initial page server-rendered; later pages lazy-load via Livewire on
  navigation; total window capped by section limits; extend the query-count harness
  with a slider fixture.
- Controls: prev/next `hover|always` (hover-reveal desktop, always on touch),
  RTL-flipped direction/icons, keyboard accessible, page indicators; controls hidden
  while a card back is open.
- Apply the display template's AX1 motion config: entrance/stagger presets on slide
  pages via `data-motion-*` attributes; load-more preset on lazy-loaded pages.
- Grid display templates keep rendering through the existing grid path.
- Tests: slider markup (pages/columns/gap incl. `none`), RTL markers, bounded lazy page
  fetch query counts, controls visibility modes, invalid-template fallback, harness green.
- Commit: `feat: render flip slider display sections`

## Step 10R-SL3 — Flip and smart side-open back face (request 13)

- Back face renders the configured EXISTING card template's parts (decision D10),
  prepared in the same presenter pass as the front — zero extra queries per card.
- `flip`: GSAP-driven 3D flip through the AX1 `PodTextMotion` boundary (hover intent
  desktop, tap on touch); one card open at a time; `prefers-reduced-motion` renders
  fade instead.
- `side_open`: implemented with the GSAP Flip plugin (state capture -> layout change ->
  animate) from AX1; back face expands beside the card over the grid inside the slider
  viewport; open direction computed SERVER-SIDE from grid position with logical sides
  (corner → inner side of screen, bottom row → upward; RTL-correct by construction) plus
  an Alpine resize fallback; z-index/overflow contained to the slider.
- All interaction state Alpine-local; nothing persists server-side.
- Tests: back-face parts from configured template; direction data attributes for
  corner/bottom fixtures in LTR and RTL; single-open markers; reduced-motion variant;
  harness green.
- Commit: `feat: add card flip and smart side open behavior`

## Step 10R-SL4 — Quick-view modal (requests 15, 16)

- App-owned flat Blade/Alpine modal (NOT Filament-styled): flat background, bordered/
  separated sections, image-as-header-background with contrast overlay, corner X +
  outside press + ESC close, focus trap, scroll lock.
- Content lazy-mounts ON OPEN only: episodes → a quick-view Livewire component reusing
  `PublicContentItemQueries` and existing presenters; podcasts → the episodes browser
  embedded with density props. Non-action clicks on front/back faces open the modal;
  explicit action links still navigate normally.
- Display-template modal config applies: `label_size` (`sm|md|hidden` → icons-only),
  `children_limit`, `modal_card_template_key` for smaller result cards.
- Modal open/close choreography through AX1 presets (image-header settle, section
  stagger-in), reduced-motion safe.
- Mandatory deep "open full page" link; no URL swap in this version (record as a future
  decision if wanted).
- Tests: opens from non-action clicks only; zero modal queries until opened (lazy mount
  asserted); label-size/children-limit behavior; all three close paths; focus trap
  markers; episode and podcast variants; harness green.
- Commit: `feat: add quick view modal for slider cards`

## Step 10R-AX2 — Loading/transition concealment and motion retrofit (requests 19, 20)

- Retrofit AX1 entrance/load-more presets onto existing homepage sections,
  latest/search grids, podcast episode grids, and contributor grids via per-section/
  template motion settings; on-scroll reveals via `ScrollTrigger.batch(..., once: true)`.
- Livewire update concealment: loading choreography coordinated with `wire:loading`
  targets (skeleton shimmer/pulse on the results region), staggered swap-in of updated
  content through morph hooks instead of popping.
- Page-to-page transitions: cross-document View Transitions API opt-in with named
  transition elements for stable chrome (header/logo); GSAP-enhanced only where it adds
  value; unsupported browsers get instant navigation; zero artificial delay.
- Verify slider + grid sections compose motion consistently on mixed homepages.
- Tests: load-more staggers ONLY new nodes; loading markers appear during updates and
  vanish after; view-transition opt-in present; reduced-motion path; no-JS content
  visibility; harness green.
- Commit: `feat: add loading and page transition motion`

## Step 10R-AX3 — Scroll-linked effects (request 21)

- Episode/podcast header parallax (transform-only, small amplitude); transcript
  reading-progress bar on the episode page (client-only, pairs with IP3 controls);
  optional scroll-linked cover/palette emphasis using V1c palette variables.
- ScrollTrigger `scrub`; transforms/opacity only; fully disabled under reduced motion;
  no pinning that changes document height on mobile unless proven CLS-free.
- Tests: data hooks present; progress-bar bounds; reduced-motion disable; no CLS on
  fixture pages; harness green.
- Commit: `feat: add scroll linked motion effects`

## Step 10R-B4 — Legacy card-options convergence (resolves F11 remainder)

- Converge `PublicContentCardOptions` with the card presentation services as a legacy
  adapter or compatibility wrapper; scalar settings still affect cards; template +
  scalar settings compose predictably; reduce Blade duplication; preserve M5 grouped
  parts, IP1 date attributes, `effective_only` defaults, transcription-backed
  transcriber display, and the SL1-SL4 slider/modal surfaces plus AX motion attributes.
- Commit: `refactor: converge public card options with template renderer`

## Step 10R-C2 — Card layout consistency and semantic layout tokens (resolves F13)

- Normalize layout across item/group/contributor/contributor-item/top-transcriber cards
  AND the slider front/back faces and modal result cards; centralize semantic token maps
  (height_policy, image_ratio, title/description lines, metadata/taxonomy/footer
  policies, image source + duplicate-thumbnail policies); unify the duplicated grid
  class maps. Fixed PHP/Blade class maps only.
- Commit: `fix: normalize public card layout presentation`

## Step 9F-A / 9F-B / 9F-C — Rich sections and footer (after all 10R steps)

- 9F-A: constrained `rich_columns` homepage section type (heading, markdown,
  rich_content, smart_rich_content, link, link_group, form_cta, image, callout) through
  `PublicFrontRenderContext`, safe renderers, fixed class maps. No CMS, no page models.
  Commit: `feat: add rich homepage column sections`
- 9F-B: `footer_config` settings group + app-owned footer renderer reusing the 9F-A
  block/column concepts. No footer models. Commit: `feat: add public footer configuration renderer`
- 9F-C: settings UX polish for rich sections/footer; full-width/collapsible sections;
  optional preview only if it reuses the public renderer.
  Commit: `feat: polish footer and rich section settings ux`

## Step 11 — Seeders/demo/assets/cleanup (explicit Yoni approval required)

Includes promoting the local multi-transcription evaluation seed state (episode/
transcription/transcriber distribution, the five card templates, policy demo scenarios)
into the tracked idempotent demo seeder, plus assets normalization and cleanup.

## Prompt 13 — Dashboard metrics (explicit Yoni approval required)

Editorial widgets counting real implemented states only.

---

## Per-run implementation process

1. Mark the selected mini-step in progress in the ledger.
2. Inspect the code for the selected scope only.
3. Laravel Boost research; FilamentExamples MCP batches + refined pass.
4. Write/update the MCP research note.
5. Write the implementation plan doc.
6. Implement only the selected mini-step.
7. Add/update focused tests; run them.
8. Run the full quality gate.
9. Update: ledger row (status/commit/files/tests/deviations); `current-project-state.md`;
   the step handoff `docs/phase-02/public-front-v2-step10r-<id>-handoff.md`; the
   decisions doc when a display/product decision changes (e.g. D9 in V1c); the
   performance audit doc when an F-status changes (P1/P2/P3/B4/C2) — honestly.
10. Commit if and only if all gates pass. Do not push unless explicitly asked.
11. Stop for Yoni/ChatGPT review.

## Required handoff sections

Purpose; what was implemented; request/finding IDs landed (addendum request numbers
and/or F-numbers); files changed; migrations/settings/schema changes; settings keys +
defaults; admin behavior; public behavior; query/cache/performance behavior incl.
harness result; translation keys added (en+he); tests added/updated/changed (with
reasons); security/fallback behavior; impact on later steps; open questions; quality
gate summary; commit hash note.

## Quality gate

Focused tests first (real file names), then:

```bash
vendor/bin/pint --dirty --format agent
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
git diff --check
```

## End-of-run review checklist for Yoni

What changed / did not change; exact admin screens to click (navigation order, relation
manager tabs, action column, wide modals, new settings tabs/fields, display-template
builder entries); public pages to inspect in Hebrew RTL + light/dark (`/`, `/search`,
`/podcasts`, a podcast page, an episode page, `/contributors`); for SL steps also: a
homepage section switched to a `flip_slider` display template — desktop hover flip,
side-open direction at corners/bottom row, mobile 2×3 tap behavior, controls
hover/always modes, gap `none`, quick-view modal open/close paths and density controls;
for AX steps also: entrance/stagger presets toggled per section/template, load-more
stagger on new cards only, loading shimmer during Livewire updates, a page navigation
with View Transitions in a supporting browser, and everything re-checked with
reduced-motion emulation enabled;
settings to toggle both ways; what was deferred; the next mini-step; whether it is safe
to continue by replying `continue`.

## Final report every run

Selected step; preflight state; Boost/MCP usage + access level; research/plan/handoff
paths; files changed; migrations run; settings/schema changes; admin/public changes;
request/finding IDs resolved; tests/commands run + FilaCheck summary; harness result;
ledger status; commit hash; git status; next mini-step; confirmation Step 11 / Prompt 13
have not started without approval.

End with exactly:

```text
Public Front v2 mini-step <MINI_STEP_ID> is complete. Waiting for Yoni/ChatGPT review before continuing.
```
