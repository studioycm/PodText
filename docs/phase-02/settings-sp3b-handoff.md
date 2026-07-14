# Settings SP3B Handoff

Date: 2026-07-14

## Scope

Executed only `prompts/pre-13-prompts/settings-sp3b-codex-prompt.md`, prompt
version v3 (2026-07-14). Kickoff corrections were `none`. No migration,
settings-storage change, Composer/npm dependency change, database/advisory/cache
lock, `lockForUpdate()`, island, production action, or push was made.

## Commit hash

Pending implementation commit.

## Requirement classification

- **Implemented:** a Hebrew-first Settings navigation group with the frozen
  twelve-entry order; eight ordinary focused subject pages; temporary Card
  Templates page; relocated Manage Public Forms contract; hidden legacy route
  redirect; complete 37-property ownership/classification registry; focused
  schemas with no top-level Tabs component; fresh owned-path fill/save contract;
  page-specific omission/upload transforms; fresh-snapshot authorization overlay;
  one-save lifecycle; local-only keyed canaries; browser script page/fixture/
  owned-section reporting; translations; and direct/retargeted regression tests.
- **Already existed and preserved:** the single `PublicContentSettings`
  storage group; lifecycle units and their SHA regression; SP3A fixture;
  import/restore/normalize/import-lock/backup paths; SettingsSaved listeners;
  Admin UX persistence; public-form definitions and MAIL1/MAIL2 behavior.
- **Deferred:** same-owner conflict resolution and truly simultaneous-request
  serialization (future approved work only); Card Template editor redesign
  (SP3C); budget enforcement (SP3D); authenticated browser history automation.
- **Not applicable:** schema migrations; Composer/npm additions; new cache layers;
  public UI islands; physical JSON-byte/timestamp equality; production
  diagnostics.
- **Blocked:** browser metric sample collection. The local in-app browser
  runtime failed before navigation with `Cannot redefine property: process`;
  no alternate browser automation or credentials were used. The measurement
  harness and save-refusal behavior are covered, but no samples or medians are
  fabricated.

## Frozen decisions

### Navigation

Settings is after Taxonomy and before Site Management. Its visible order is:
Homepage (300), Display (310), Episode Page (320), Menu/Header (330), Podcasts
(340), Contributors (350), About (360), Maintenance (370), Manage Public Forms
(380), Card Templates (390), Admin UX (400), and Settings Backups (410).
`ImportPublicSettings` and `ManageSettingsImportLocks` remain hidden,
authorized-action destinations. Homepage Sections, Importer Settings, Admin
Tools, Spotify Links Fetcher, and Users remain in Site Management.

### Ownership and writer scope

`SettingsSubjectOwnershipRegistry` is the sole ownership/classification
source. It classifies all 37 public non-static `PublicContentSettings`
properties exactly once: Homepage (3 scalars), Display (22 properties),
Episode Page (`item_page_layout`, `item_page`), Menu/Header
(`menu_config`, `route_labels`), Podcasts, Contributors, About,
Maintenance, Public Forms, Card Templates, operational import locks, and
non-editable settings backups. The reflection regression obtains the other side
directly from `ReflectionClass`, and schema-isolation checks every editable
page against the registry.

The shared fresh owned-path contract applies only to the eight ordinary subject
pages, Card Templates, and Manage Public Forms. Import, restore, normalize,
import-lock, backup, and Admin UX writers remain on their existing SP3A paths.
Each ordinary save refreshes the canonical settings object immediately before
merging, preserves explicit omitted protected fields, validates only owned
groups, overlays only owned roots onto that same snapshot, applies the
authorization overlay to that snapshot, fills the same object, and calls
`save()` exactly once. It adds no manual cache/backup/event action.

## Implementation summary

- `PublicContentSettingsSubjectPage` is the 574-line shared lifecycle,
  authorization, profiling, measurement-refusal, and lock-surface base. It has
  no domain field definitions.
- `BuildsPublicContentSettingsSubjectSchemas` contains the relocated domain
  schema and form-transform helpers; each ordinary page explicitly declares its
  own schema factory, so only that page's schema is constructed.
- `PublicContentSettings` is a hidden, authorized compatibility redirect. It
  maps all nine old `public-content-tab` values, sends missing/unknown to
  Homepage, and forwards only `sp3a_measure`, `sp3a_profile`, and
  `sp3b_subject_fixture`.
- `MultiTranscriptionSurfaces::overlayUnauthorizedSettings()` accepts an
  optional stored snapshot for this page path; existing callers retain their
  SP3A behavior.
- `SettingsSp3bSubjectFixture` overlays only the selected owner during local
  measurement and supports the four keyed stress canaries. The frozen SP3A
  measurement fixture and lifecycle-unit SHA are untouched.
- The Content Group default-images hint now targets Display Settings.

## Direct test inventory and disposition

| File | Disposition |
|---|---|
| `SettingsSp3bTest.php` | New: route tiers, direct save authorization, ownership reflection/schema isolation, non-owned decoded/canonical JSON integrity, sequential stale saves with warm caches, forged roots, one SettingsSaved event, redirects, locks, canaries, and measurement refusal. |
| `AdminPhase02ResourcesTest.php` | Retargeted navigation, focused page workflow, labels, and group order. |
| `SettingsImportExportTest.php` | Retargeted direct page save and relocated lock UI assertions; import/export behavior remains in place. |
| `SettingsPageProfilerTest.php` | Retargeted profiler and measurement page assumptions. |
| `RolesGatesTest.php` | Retargeted owned Display/Card Template gate-save assertions. |
| `PublicFrontJsonSettingsArchitectureTest.php` | Retargeted direct form/schema workflow assertions only. |
| `PublicFrontCardTemplateBuilderTest.php` | Retargeted the Advanced editor to Card Templates; rendering tests unchanged. |
| `PublicFormsSubmissionsTest.php` | Retargeted public-forms settings workflow to its shared contract. |
| `PublicMenuHeaderUxFixesTest.php`, `ImageMediaCuratorTest.php` | Retargeted Menu/Header direct page use. |
| `PublicDefaultImagesSettingsTest.php` | Retargeted to Display. |
| `PublicFrontCustomColorsTest.php`, `PublicFrontIconRegistryTest.php` | Retargeted direct field assertions to their owners. |
| `PublicAboutPageContentTeamTest.php` | Retargeted About builder/upload direct workflow. |
| `SettingsSp3aTest.php`, backup/snapshot/normalize, public policy, single-lens, and maintenance behavior tests | Unchanged domain/operational regressions; run in the required focused set. |

## Files changed

- Pages/support: focused settings pages, shared lifecycle base, domain-schema
  support trait, ownership registry, legacy redirect, subject fixture,
  navigation order, fresh authorization overlay, and default-images URL.
- UI copy/measurement: English/Hebrew page labels and the SP3A browser metrics
  script.
- Tests: new SP3B coverage plus only direct monolith/tab test retargets listed
  above.
- Documentation: SP3B research/plan, this handoff, current project state, and
  step ledger. No stable spec, blueprint, feature map, guideline, or
  `prompts/README.md` changed.

## Test and command record

- Preflight on the clean tree: `git status --short --branch` and
  `git log --oneline -8`; found `30be5e5`, SP3A implementation/backfill, and
  MAIL2 implementation/backfill. No SP3B start or hash-backfill dirt existed.
- Required reads: repository instructions, the full lessons record, state,
  ledger head, newest handoffs, prompt v3, SP2/SP3A research/plans/handoffs/
  tests, relevant guidelines, installed Filament/Spatie source, current pages,
  and affected tests.
- Research: Laravel Boost returned installed-version guidance; FilamentExamples
  provided search snippets only (no source/detail endpoint). Browser-control
  skill was read before the attempted measurement session.
- Exact clean-tree baseline:
  `php artisan test --compact …SettingsSp3aTest …PublicMaintenanceModeTest`
  passed **146 tests / 1,646 assertions / 237.917 s** after its first sandbox
  attempt could not bind the PAO local port; the approved rerun passed.
- PAO update check requested by the operator:
  `composer update laravel/pao --with-all-dependencies` reported nothing to
  modify. `composer show --locked --latest --direct` reports `laravel/pao
  v1.1.2` current/latest. No Composer file or vendor change resulted.
- Syntax checks for changed page files passed. `git diff --check` passed at
  each inspected checkpoint.
- Focused SP3B/admin iterations: initial 43-test regression passed; schema
  extraction briefly exposed missing trait imports for
  `PublicFrontConfigReader`, `Block`, and `MarkdownEditor`; each was fixed
  and the final focused run passed **46 tests / 650 assertions / 15.668 s**.
  These are source-scope corrections, not behavior changes.
- Required focused suite on the settled behavior was captured through the
  PAO runner's local JUnit output after its streaming channel detached:
  **169 tests / 1,877 assertions / 153.478 s**, 0 failures/errors. The
  temporary JUnit report is outside the repository.
- Formatting: `vendor/bin/pint --test --dirty` initially reported changed
  files; the permitted `vendor/bin/pint --dirty` formatter was run during
  iteration. It also formatted the schema extraction and test import ordering.
  No `filacheck --fix` command was used.
- Measurement setup: a throwaway SQLite database under `/tmp` was migrated;
  the local server was started only after approval. The browser runtime failed
  before app navigation with `Cannot redefine property: process`; the server
  was stopped normally. No production or development database was probed.

## Measurement record

| Requested rows | Result |
|---|---|
| Eight ordinary pages, Manage Public Forms, Card Templates: one cold + five warm | Blocked before browser navigation; no samples/median recorded. |
| Item Page, Menu/Header, About, Public Forms stress canaries | Blocked before browser navigation; no samples/median recorded. |
| DOM < 3,000 / warm TTFB < 800 ms ordinary-page target | Unclassified pending a usable authenticated browser run; Card Templates remains exempt until SP3C. |

The script and local-only canary/save-refusal test coverage remain present. The
numbered manual check below is the required follow-up; this handoff does not
claim a browser result.

## Final gate outcomes

- Requirements sweep: passed. No migrations/dependency/lock/island/lifecycle
  changes; no `lockForUpdate()` or new database/advisory/cache lock; frozen
  lifecycle SHA remains exercised; all required docs/tests/redirect maps and
  ownership/schema isolation coverage are present; `git diff --check` passes.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed with 0 issues.
- `npm run build`: passed.
- `php artisan test` (last): passed on the final file state. The PAO runner
  uses its local socket and the full-suite JUnit result records 0 failures and
  0 errors.

## Local Front Check Report

1. Open Admin → Settings; expect exactly the twelve visible entries in the
   recorded order and no Settings Import or Import Locks sidebar item.
2. Open every subject page; expect only that page's owned sections/fields, no
   sibling-page inputs, and no top-level settings tabs.
3. Open Manage Public Forms, Display, and Maintenance; expect only their
   approved inline important-field locks and relevant section locks.
4. Run the SP3A baseline protocol on Homepage and one heavier page; expect a
   recorded sample or an explicit target miss. Run a subject canary and expect
   it labeled separately from the SP3A baseline.
5. Edit and save two different settings pages; expect both owned values to
   persist and unrelated settings to remain unchanged.
6. Open two browser tabs on disjoint settings pages, save B and then stale A;
   expect both changes to persist. Do not treat this as simultaneous-write
   proof.
7. Open `/admin/public-content-settings`; expect Homepage. Repeat every
   legacy `public-content-tab` value; expect its mapped page, with
   `advanced` opening Card Templates.
8. Open the podcast/content-group default-images hint; expect Display Settings.
9. As an administrator, inspect changed pages; expect gated fields absent.
   Review the automated import regression and expect protected values to remain
   unchanged; do not mutate a non-disposable settings database for this check.

## Assumptions, deferrals, and current status

- Sequential disjoint-owner saves are supported; same-owner edits remain
  last-write-wins. No claim or test of simultaneous request serialization is
  made.
- The in-app browser runtime needs repair or a later approved browser session
  before performance samples can be collected.
- `git status --short` is expected to show the SP3B implementation and
  documentation changes until the implementation commit, followed only by the
  required docs-only hash backfill.
