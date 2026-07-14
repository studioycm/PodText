# Codex Prompt — SP3B: Settings Subject Pages + Fresh Owned-Path Saves

Prompt version: v3 — 2026-07-14. Supersedes SP3B v1 and v2 (neither
executed). Standing rule: stop and ask on a version mismatch with the
kickoff. Stage 2 of SP3A→SP3B→SP3C→SP3D.

Work in the current local clone of `studioycm/PodText`.

ONE run: replace the Public Content Settings monolith's eight ordinary
tabs with EIGHT focused subject pages under a dedicated Settings
navigation group. Add one temporary Card Templates page at its own slug.
Convert the old monolith slug into a lightweight hidden compatibility
redirect. The eight pages, Card Templates, and existing Manage Public
Forms page use one shared fresh owned-path save contract.

GUARANTEE, stated narrowly: two disjoint settings pages may be mounted in
stale browser tabs and saved sequentially without the later save erasing
the earlier page's changes. Same-owner edits remain last-write-wins.
Truly simultaneous in-flight writes are NOT solved or claimed in SP3B;
do not add database/advisory/cache locks or `lockForUpdate()`. A future
approved step may add cross-request concurrency control if evidence
requires it.

SCOPE GUARD — explicitly prohibited in SP3B: database migrations;
splitting the Spatie settings class or storage group; changing stored
properties, lifecycle/import/export formats, or settings migrations; new
persistent cache layers or cache configuration; rewrites of import,
restore, normalize, import-lock, backup, or Admin UX persistence paths;
template-editor redesign (SP3C); islands; and any change to lifecycle
unit definitions, paths, structure, or serialized bytes. The existing
SP3A SHA-256 lifecycle regression
`61e551a60016b1ac0c9aa8051463818adf31677bea465ac0e9b269fe3d2386b8`
must pass untouched.

Standing runner rules: read `docs/phase-02/ai-development-lessons.md` IN
FULL during preflight; research note + implementation plan docs BEFORE
code; no push unless asked; no `filacheck --fix`; fixture-owned tests;
en+he translations for every new label; NO Composer/npm changes. No
general Filament `->deferred()` exists in installed 5.6.7; collapse is
never proof of lazy loading.

CANONICAL RUN ENDING (standing): implementation commit (`## Commit hash`
pending) → immediately a docs-only commit stamping that hash into the
handoff + ledger (`docs: backfill settings sp3b hash`).

Handoff: `docs/phase-02/settings-sp3b-handoff.md`; gate outcomes written
in before committing; requirement classification covers every item as
Implemented / Already existed / Deferred / Not applicable / Blocked.
Local Front Check Report = numbered MANUAL OPERATOR STEPS in imperative
voice with objective expectations. Kickoff corrections: an enumerated
list or the word `none`.

FINAL GATE ORDER (standing): requirements sweep → `vendor/bin/pint --test`
→ `vendor/bin/filacheck` → `npm run build` → FULL `php artisan test` LAST
(once = once GREEN on final state; after ANY file change re-enter from
Pint; record every run, including failures; never interrupt or parallelize
the full suite).

## Preflight and required research

```bash
git status --short --branch
git log --oneline -8
```

Require a completely clean tree. Expect SP3A implementation `88fdda2`,
SP3A backfill `27c4d41`, and MAIL2 implementation/backfill if MAIL2 ran
first. Stop if an earlier run still has staged or uncommitted hash-backfill
work, or if SP3B has already started.

Read before planning or code:

- this prompt and `AGENTS.md`;
- `docs/phase-02/ai-development-lessons.md` in full;
- `docs/phase-02/current-project-state.md`;
- the ledger head and newest relevant handoffs;
- `docs/research/settings-performance/02-sp3-prompt-review-and-alternatives-report.md`;
- SP2 and SP3A settings-performance research, plans, tests, and handoffs;
- relevant `.ai/guidelines/`, installed Filament SettingsPage/Spatie
  Settings source, current settings pages, support classes, and tests.

Use installed-version Laravel Boost documentation and the required
FilamentExamples research protocol for custom/settings pages, forms,
navigation, and loading behavior. Record exactly what was available
(installed-version guidance, search snippets, source detail, or
unavailable); do not claim deep source research from search-only output.

Before code, run this targeted baseline on the unmodified tree and STOP
on any failure:

```bash
php artisan test --compact \
  tests/Feature/SettingsSp3aTest.php \
  tests/Feature/AdminPhase02ResourcesTest.php \
  tests/Feature/SettingsImportExportTest.php \
  tests/Feature/SettingsBackupsTest.php \
  tests/Feature/SettingsBackupSnapshotsTest.php \
  tests/Feature/PublicContentSettingsNormalizeCommandTest.php \
  tests/Feature/SettingsPageProfilerTest.php \
  tests/Feature/SingleTranscriptionLensTest.php \
  tests/Feature/PublicTranscriptionPolicyTest.php \
  tests/Feature/PublicFrontCardTemplateBuilderTest.php \
  tests/Feature/PublicFormsSubmissionsTest.php \
  tests/Feature/PublicMaintenanceModeTest.php
```

Inventory every test and application caller that directly references
`App\Filament\Pages\PublicContentSettings`, `public-content-tab`, or an
old tab state path. Put the inventory and disposition in the plan before
code. Retarget only direct page/tab coverage; do not mechanically move
unrelated domain, rendering, validator, or service tests.

## Job 1 — research and frozen maps (deliverables before code)

Create:

- `docs/research/settings-performance/04-sp3b-research.md`
- `docs/research/settings-performance/04-sp3b-implementation-plan.md`

The plan contains three frozen tables.

### 1. Navigation map

Use one Hebrew-first Settings navigation group, ordered after Taxonomy
and before Site Management. Register it through `AdminNavigationOrder`.
The table columns are page class, stable slug, label translation key,
Heroicon enum, sort, visible/hidden, and settings ownership.

Visible order:

1. `HomepageSettings` — `settings/homepage`
2. `DisplaySettings` — `settings/display`
3. `EpisodePageSettings` — `settings/episode-page`
4. `MenuHeaderSettings` — `settings/menu-header`
5. `PodcastSettings` — `settings/podcasts`
6. `ContributorSettings` — `settings/contributors`
7. `AboutSettings` — `settings/about`
8. `MaintenanceSettings` — `settings/maintenance`
9. existing `ManagePublicForms` — retain its current slug
10. `CardTemplateSettings` — `settings/card-templates`
11. existing `AdminUxSettings` — retain its current slug and separate
    `admin_ux` settings persistence
12. existing `SettingsBackupResource` — retain its current resource URL

Hidden and reached only through their existing authorized actions:
`ImportPublicSettings` and `ManageSettingsImportLocks`; both keep
`shouldRegisterNavigation() === false`.

Remain under Site Management: `HomepageSectionResource`,
`ImporterSettings`, `AdminTools`, `SpotifyLinksFetcher`, and Users.

### 2. Complete ownership map

Use one authoritative ownership/classification registry. Only rows
classified as editable page owners drive subject-page form/save metadata;
operational rows remain non-page classifications. Classify all 37 public
non-static properties of
`App\Settings\PublicContentSettings` exactly once:

| Owner / classification | Exact properties |
|---|---|
| Homepage Settings | `homepage_item_limit`, `pinned_item_limit`, `show_latest_section` |
| Display Settings | `default_public_sort`, `default_result_layout`, `homepage_card_image_size`, `homepage_card_density`, `homepage_card_title_size`, `homepage_card_image_fit`, `homepage_card_image_radius`, `homepage_show_group_badge`, `homepage_group_badge_mode`, `homepage_group_title_separator`, `homepage_group_badge_duplicate_thumbnail`, `homepage_show_authors`, `homepage_show_categories`, `homepage_show_tags`, `homepage_show_duration`, `homepage_show_effective_date`, `homepage_show_description`, `homepage_description_lines`, `homepage_cards_per_page`, `display_defaults`, `default_images`, `transcription_policy` |
| Episode Page Settings | `item_page_layout`, `item_page` |
| Menu/Header Settings | `menu_config`, `route_labels` |
| Podcast Settings | `podcasts_page` |
| Contributor Settings | `contributors_page` |
| About Settings | `about_page` |
| Maintenance Settings | `maintenance` |
| Manage Public Forms | `public_forms` |
| temporary Card Templates | `card_templates` |
| lifecycle service writer, no subject-page editor | `import_locks` via existing `SettingsImportLocks` |
| intentionally non-editable consumer configuration | `settings_backups`, consumed by Settings Backups/import lifecycle; do not invent a new editor |

An independent reflection test compares the settings class properties
with flattened ownership metadata and fails on a missing or duplicate
property. Do not derive both sides of the assertion from the registry.
A separate schema-isolation test proves every editable page contains all
properties assigned to its owner identifier by the registry and
dehydrates no property assigned to another owner.

### 3. Writer-scope and lifecycle map

State explicitly:

- The shared subject-page save contract applies ONLY to the eight new
  subject pages, `CardTemplateSettings`, and `ManagePublicForms`.
- Import, restore, normalize, `SettingsImportLocks`, backup, and Admin UX
  persistence stay on their existing SP3A paths and are regression-tested,
  not rewritten.
- Ordinary subject-page saves call `$settings->save()` once and do not
  dispatch events, create backups, or clear caches manually. Existing
  `SettingsSaved` subscribers/listeners remain authoritative for Spatie
  cache updates, public-config invalidation, backups, render context, and
  transcription-policy invalidation.
- Non-owned integrity means decoded value identity plus per-property
  canonical JSON identity. Physical database JSON bytes and timestamps
  are not the assertion. Literal byte identity remains required for the
  lifecycle-unit SHA regression.

## Job 2 — shared fresh owned-path page contract

Build a small shared base page/concern for page lifecycle only. It owns
authorization, owned-state allowlisting, fresh merge, existing Filament
save hooks/transaction behavior, profiling, measurement save refusal,
header actions, notifications, redirects, and lock-surface helpers. It
contains NO domain field definitions and must not become a generic
settings framework or global settings mutation service.

Every subject-page initial fill also stays ownership-scoped: resolve the
settings payload once, extract only declared owned roots/scalars, apply
only that page's explicit fill transform (for example Builder state),
overlay only the owned portion of a local measurement fixture, and fill
only that focused form. No sibling-page state may enter the form or
Livewire snapshot. Profile the fill/build/render phases under the page's
subject key.

Every subject-page save performs this order:

1. `canAccess()` and `canEdit()` require an authenticated `User` with
   `UserRole::Admin` or higher. Re-authorize at the start of direct
   Livewire `save()` and abort 403 BEFORE reading submitted state.
2. Refuse persistence in local measurement mode.
3. Run the existing Filament before/after validation and save hooks and
   transaction/error behavior.
4. Read only the page's declared form state. Reject/ignore forged
   undeclared top-level roots and scalars.
5. Resolve the canonical scoped `PublicContentSettings`, call its
   installed `refresh()` immediately before merging, then take `toArray()`
   as the fresh complete stored snapshot. Do not use
   `PublicFrontConfigReader` as the merge source and do not clear caches
   to manufacture freshness.
6. Apply page-specific explicit transforms and omission preservation from
   that snapshot BEFORE group validation. Preserve dehydrated/gated
   maintenance, policy, and other protected fields. Never use a generic
   recursive merge: an explicit list deletion remains a deletion.
7. Validate only owned array groups with `validateGroups($groups,
   $ownedGroupNames)`; scalar validation remains in the typed page schema.
   Legacy-invalid sibling groups must not block or be normalized.
8. Overlay only owned validated roots/scalars onto the same fresh complete
   snapshot.
9. Apply `MultiTranscriptionSurfaces::overlayUnauthorizedSettings()`
   against that exact fresh snapshot. Add a backward-compatible optional
   stored-snapshot argument so this page path cannot independently re-read
   stale state; preserve every existing external call site's SP3A behavior.
10. Fill the complete guarded candidate into the same refreshed settings
    object and call `$settings->save()` exactly once. Preserve
    `rememberData()`, success notification, redirect, and profiling; logs
    may contain phase, subject, timing, and byte counts, never values.

Do not claim or test simultaneous-request serialization. The required
stale-page regression is sequential: mount A and B, save B, then save
stale A; B survives because A refreshes and overlays only its ownership.

The ownership/classification registry is the SINGLE source for owned
groups/scalars and which owned properties are validator groups. Each page
declares only its stable owner identifier, typed schema factory, owned
lock-surface identifiers, and profiler subject key; the shared base
resolves ownership from the registry. Do not duplicate path lists in page
classes. Authorization overlays remain centralized; pages must not create
a second gate registry.

## Job 3 — pages, schema moves, locks, and compatibility routing

- Move each ordinary tab schema into its focused page/factory. Preserve
  validation, defaults, reactivity, upload normalization, trusted HTML
  marker behavior, helper copy, SP3A Select option sources/loading policy,
  and ROLES1 macros. Do not rewrite field behavior while moving it.
- Keep the shared base small. Domain helpers live with their page or a
  focused domain schema/support class. No new 2,000-line base page and no
  construction of sibling schemas during a request.
- `CardTemplateSettings` owns only `card_templates` and preserves the
  existing Advanced editor unchanged. It uses the shared owned-path save
  contract; it never runs whole-configuration validation. Template
  library/editor redesign remains SP3C.
- Upgrade existing `ManagePublicForms` to the shared contract without
  changing public-form definitions or MAIL1/MAIL2 behavior.
- Render only each page's owned section locks and approved important-field
  locks through `SettingsImportLockSurfaceRegistry`. Manage Public Forms
  shows `public_forms.require_email_verification`; Display shows the three
  approved `transcription_policy` fields; Maintenance shows
  `maintenance.enabled` and `maintenance.raw_html_override`; Templates
  shows its section lock only, never per-template/per-part lock UI.
  Lifecycle units and stored legacy locks remain unchanged and enforced.
- The old `PublicContentSettings` class/slug remains only as a lightweight
  hidden redirect and must not build or hydrate the old form. Remove moved
  schema builders and dead duplicate form helpers; "monolith deletion in
  SP3D" means the compatibility class/slug remains until then.
- Map the exact `public-content-tab` values:
  `homepage`, `display`, `item-page`, `menu-header`, `podcasts`,
  `contributors`, `about`, `maintenance`, `advanced`. `advanced` goes to
  Card Templates; missing/unknown goes to Homepage. Preserve only a safe
  whitelist of meaningful local measurement/profile query flags:
  `sp3a_measure`, `sp3a_profile`, and `sp3b_subject_fixture`.
- Sweep every internal caller. In particular, the default-images hint in
  `ContentGroupForm` must target the new Display page, not Homepage.
- No subject page or temporary Templates page contains a top-level Tabs
  component or sibling-page fields.

## Job 4 — measurement and subject stress canaries

- `SettingsSp3aMeasurementFixture` remains byte-identical and keeps its
  existing size/determinism tests. Every comparable baseline row uses
  unchanged `?sp3a_measure=1` semantics.
- Add one focused keyed SP3B subject-canary provider, not unrelated fixture
  classes, for growing ordinary collections:
  `item_page.info_fields`; `menu_config.items` + `route_labels`;
  `about_page.blocks` + `team_profiles`; and `public_forms.definitions`
  with nested fields/options. Overlay canaries only through a local-only
  `sp3b_subject_fixture` query flag combined with measurement mode. The
  allowed values are `item-page`, `menu-header`, `about`, and
  `public-forms`; reject/ignore unknown values. They are read-only and
  save refused. They never replace the SP3A comparable baseline.
- Adapt `scripts/settings-sp3a-browser-metrics.js`: retain the frozen core
  metrics, add page/subject and fixture identity plus owned-section element
  counts; tab-panel reporting no longer applies.
- Measure the eight subject pages, Manage Public Forms, and Card Templates:
  one cold and five warm visits under the unchanged baseline; add separately
  labeled stress-canary rows for the four growing subjects. Record every
  sample and warm median. Table fields: fixture, cold/warm, TTFB,
  encoded/decoded/uncompressed bytes, DOM, listener estimate, heap, DCL,
  load, total queries, settings reads, lifecycle derivations, duplicate
  loads.
- Non-blocking SP3B targets: each of the eight ordinary pages and Manage
  Public Forms has initial DOM < 3,000 and warm median TTFB < 800 ms.
  Card Templates is explicitly exempt until SP3C. Record and classify all
  misses for SP3C/D; add NO islands or improvised lazy forms.

## Tests

- Guest, non-admin, admin, and super-admin route access for every new page
  and the changed Manage Public Forms page; direct Livewire save
  authorization fails before state read/mutation.
- Ownership reflection completeness/uniqueness and schema isolation.
- Page A save changes only owned properties; every non-owned property
  retains decoded and canonical JSON identity.
- Sequential stale-page regression with distinct page/component request
  scopes: mount A and B, save B, then stale A without clearing persistent
  caches; both changes survive. Add a cache-enabled/memo-enabled variant
  with `PublicFrontConfigCache` warmed, and assert a fresh settings resolve
  plus `PublicFrontConfigReader` both see A+B. Do not let a shared in-memory
  settings instance make the test pass falsely.
- Same-owner last-write-wins is documented, not upgraded to optimistic
  locking here. No database-level concurrency claim/test belongs in SP3B.
- Dehydrated protected fields survive their owning page's save; explicit
  list deletion remains deleted; forged undeclared roots are ignored;
  legacy-invalid siblings neither block nor change.
- Admin forged gated policy/template state remains equal to the fresh
  snapshot; super-admin/multi behavior remains available.
- One ordinary page save dispatches one `SettingsSaved`; existing observable
  backup/cache/render-context behavior occurs through current listeners,
  with no manual duplicate lifecycle calls.
- Manage Public Forms important lock UI and all relevant inline section-lock
  actions work after relocation. Existing import/restore/normalize and
  legacy-lock regressions remain green.
- Old-route mapping covers all nine values, missing, unknown, unauthorized,
  query whitelist, and confirms the redirect builds no settings form.
  Internal Resource URLs, including the default-images hint, target the
  correct new page.
- Measurement mode is local-only and save-refused on all measured pages;
  frozen fixture and lifecycle SHA regressions remain untouched.
- Retarget direct monolith/tab tests and add page-owned workflow tests.
  Keep unrelated domain/service/rendering tests in place. The handoff
  classifies each inventoried file as retargeted, newly covered, or unchanged.
- Browser history/back behavior requires a real browser test if the existing
  browser stack is available; otherwise keep it only as a numbered manual
  front check and do not claim automated coverage.

## Docs and handoff

Update `docs/phase-02/current-project-state.md` and add ledger row
`SP3B - Settings subject pages and fresh owned-path saves`. Commit the two
research/plan docs and `docs/phase-02/settings-sp3b-handoff.md` with the
implementation. Do not change stable specs, blueprints, feature maps,
guidelines, or `prompts/README.md` unless a stable requirement/ownership
contract actually changed; classify any such edit.

The handoff includes: requirement classification; frozen navigation,
ownership, and writer-scope decisions; files changed; tests added/retargeted;
every command and result; measurement samples/medians/targets; gate results;
assumptions; deferrals; and `## Commit hash` pending.

Local Front Check Report, as numbered imperative operator steps with
objective expectations:

1. Open the Settings group; expect exactly the 12 visible entries in the
   frozen order and no Settings Import or Import Locks sidebar item.
2. Open every subject page; expect only its owned sections/fields and no
   sibling-page inputs or top-level settings tabs.
3. Open Manage Public Forms, Display, and Maintenance; expect only their
   approved inline important-field locks and relevant section locks.
4. Run the baseline protocol on Homepage and one heavier page; expect the
   recorded target result or an explicitly classified miss. Run one subject
   canary and expect it labeled separately from the SP3A baseline.
5. Edit and save two different pages; expect both values to persist and
   unrelated settings to remain unchanged.
6. Open two browser tabs on disjoint settings pages, save B and then stale A;
   expect both changes to persist. Do not present this as simultaneous-write
   proof.
7. Open `/admin/public-content-settings`; expect Homepage. Repeat with every
   legacy `public-content-tab` value; expect its mapped page, with `advanced`
   opening Card Templates.
8. Open the podcast/content-group default-images hint; expect the new Display
   settings page.
9. As admin, inspect the changed pages; expect gated fields absent. Review
   the recorded automated import regression and expect gated values to have
   remained unchanged; do not mutate a non-disposable settings database for
   this manual check.

Commit: `perf: split settings into subject pages with fresh owned-path saves`
Then immediately create the canonical docs-only backfill commit described
above. Do not push.

End with exactly:

```text
Settings SP3B is complete. Waiting for Yoni review before continuing.
```
