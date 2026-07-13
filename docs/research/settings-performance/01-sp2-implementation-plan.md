# SP2 Settings Performance Implementation Plan

Date: 2026-07-13

## Scope Guard

Execute `prompts/pre-13-prompts/settings-performance-sp2-codex-prompt.md` only.

No Composer changes. No push. No `vendor/bin/filacheck --fix`. The final gate
order is requirements sweep, `vendor/bin/pint --test`, `vendor/bin/filacheck`,
`npm run build`, then full `php artisan test` last.

## Job 0 Documentation Fixes

1. Backfill SP1 commit `c6c9587` into the SP1 handoff, mini-step ledger, and
   current-state row.
2. Amend the Horizon lesson so future operators identify each suspected master
   by `/proc/<pid>/cwd` and app `APP_NAME` before killing anything.
3. Keep the already tracked backlog triage file unchanged:
   `docs/phase-02/back-log-triage-2026-07-13.md`.

## Job 1 Attribution And Gate

Status: complete.

Temporary low-level probes were added inside `PublicContentSettings::form()` for:

- component array construction;
- root `Tabs` component configuration;
- `$schema->components($components)`;
- inline import-lock hint traversal;
- specific option/default builders where local inspection suggests repeated
  validator work.

SP1-style profiling with `SETTINGS_PROFILING=true` attributed the 1.25s missing
cost to repeated inline import-lock unit-path lookup during full-tree hint
attachment. Scaffolding probes were removed after the gate. The cheap permanent
probe retained in code is `form.inline_import_lock_hints`.

The targeted memoization reduced the local 37 KB-payload monolith from roughly
1.29-1.32s `form.total_build` to roughly 71-83 ms in the final profiling run.

Gate:

- Decision: stop after Job 2. The dominant cost was a dedupable traversal bug,
  not irreducible Filament component construction.
- Canary page not built. The optimized monolith is already under the canary
  target, so the split lacks supporting numbers.
- Produce an evidence handoff instead of splitting the monolith.

## Job 2 Foundations

Status: complete.

### Group-Scoped Validation

Change `PublicFrontConfigValidator` to add:

```php
validateGroups(array $data, ?array $only = null): PublicFrontConfigResult
```

`validate()` remains the whole-config API and delegates to `validateGroups()`.
When `$only` is provided, normalize and validate only those top-level groups,
while preserving existing `validator.group.*` profiler phases.

### Stored Settings Normalize Command

Add `settings:normalize-public-content`.

- Default dry-run: read current stored public settings, merge defaults, run full
  validation, print per-group changed/missing/dropped/reset report, and write
  nothing.
- `--apply`: create a system backup through `SettingsBackupManager::createSystem()`
  first, then persist normalized payload and print the same report.
- Tests cover seeded legacy-shaped payload dry-run and apply behavior, including
  backup creation.

Implementation notes:

- `validateGroups()` returns only selected normalized groups when `$only` is
  provided, and ignores non-selected sibling groups so legacy-invalid siblings
  can no longer block a focused save path in future split work.
- `settings:normalize-public-content` keeps dry-run as the default. `--apply`
  creates a backup before saving and then the existing `SettingsSaved` listener
  can create the usual post-save normalized backup.

## Job 3 Split Plan

Status: not executed. The Job 1 gate stopped the split.

Storage stays in `App\Settings\PublicContentSettings`. Each domain page:

- fills only its owned groups/scalars;
- validates only its owned groups through `validateGroups()`;
- persists by starting from current stored settings, overlaying only the page's
  validated groups/scalars, and saving the complete settings object so sibling
  groups remain byte-identical, including legacy-invalid siblings;
- uses the shared profiler boundaries for form build, save phases, payload
  bytes, and listener/backup behavior.

Existing single-owner groups:

| Group | Owning page | Notes |
|---|---|---|
| `public_forms` | `ManagePublicForms` | Already dedicated; keep storage unchanged and upgrade save contract if the split proceeds. |
| `import_locks` | `ManageSettingsImportLocks` | Existing lock manager page/action surface; no duplicate editing in domain pages. |

Monolith tab/page mapping:

| Current tab | New page | Owned groups/scalars |
|---|---|---|
| homepage | Homepage Settings | `homepage_item_limit`, `pinned_item_limit`, `show_latest_section` |
| display | Display Settings | display scalars, `display_defaults`, `default_images`, `transcription_policy` |
| item page | Episode Page Settings | `item_page_layout`, `item_page` |
| menu/header | Menu Header Settings | `menu_config`, `route_labels` |
| podcasts | Podcast Settings | `podcasts_page` |
| contributors | Contributor Settings | `contributors_page` |
| about | About Settings | `about_page` |
| maintenance | Maintenance Settings | `maintenance` |
| advanced | Card Template Settings | `card_templates` |

The old `PublicContentSettings` slug remains as a lightweight redirect to
Homepage Settings so bookmarks survive, but the monolith form is removed.

## Job 4 Test Reorganization

Status: not executed. The page split did not proceed, so there were no new
domain-page test files to relocate to.

Move settings-page workflow tests to page-owned test files:

- homepage/display/item/menu/podcast/contributor/about/maintenance/card-template
  settings page tests for page save behavior;
- validator and normalization tests as direct unit/feature coverage where a
  mounted Filament page is unnecessary;
- profiler tests against the shared concern and at least one domain page;
- keep duplicate card-template, maintenance marker payload, backup/listener, and
  public-form preservation regressions with equal or stronger assertions.

No test deletion without an equivalent relocation.

## Job 5 Measurement And Report

Status: reduced to gate evidence only. Per-page measurement is not applicable
because no domain pages were created.

After final state, run profiling for every new page:

- cold load;
- warm load;
- no-op save;
- single-field save;
- one live interaction on the heaviest page.

Record before/after phase tables, payload bytes, settings-area test time delta,
and target misses in `docs/phase-02/settings-performance-sp2-handoff.md`.

## Final Documentation

Before final commit:

- update `docs/phase-02/current-project-state.md`;
- add ledger row `SP2 - Settings split and scoped validation (TS2)`;
- create committed handoff
  `docs/phase-02/settings-performance-sp2-handoff.md` with gate outcomes,
  `## Commit hash` pending, and a numbered Local Front Check Report.

The ledger row title is retained for traceability to the prompt, but the row
must state that the split/TS2 parts were stopped by the attribution gate and
only the targeted performance fix plus Job 2 foundations shipped.
