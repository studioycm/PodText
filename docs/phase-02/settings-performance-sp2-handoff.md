# Settings Performance SP2 Handoff

Date: 2026-07-13

## Scope

Executed only `prompts/pre-13-prompts/settings-performance-sp2-codex-prompt.md`.

No Composer changes were made. No push was performed.

SP2 was evidence-gated. Job 1 showed the missing settings-page cost came from
repeated inline import-lock hint unit-path lookup, not from irreducible Filament
component construction. After memoizing that lookup, the existing monolith fell
below the prompt's canary target, so the split, TS2 page-test relocation, and
card-template clone rider were stopped by the gate. Job 2 foundations still
landed.

## Commit Hash

Pending final SP2 commit.

## Requirement Classification

- Implemented: SP1 commit hash backfills, Horizon multi-tenant process lesson
  amendment, Job 1 attribution probes and evidence table, memoized inline
  import-lock hint lookup, permanent `form.inline_import_lock_hints` profiler
  phase, `PublicFrontConfigValidator::validateGroups()`, and
  `settings:normalize-public-content` with dry-run plus backup-first apply.
- Already existed: `PublicContentSettings` monolith, `ManagePublicForms`,
  `ManageSettingsImportLocks`, settings backup lifecycle, settings saved
  listener, public-front config registry/defaults, and SP1 profiler.
- Deferred by gate: domain settings pages, old settings-page redirect, shared
  split-page profiler concern, cross-page preservation tests, TS2 test-file
  relocation, per-page measurements, and the card-template clone rider.
- Not applicable after gate: new domain-page translations/navigation/icons,
  canary page manual checks, per-page payload tables, and old-slug redirect
  behavior.
- Blocked: none. The split was intentionally stopped because the measured
  numbers no longer supported the refactor.

## Files Changed

- Performance/settings page:
  `app/Filament/Pages/PublicContentSettings.php`.
- Validation and command:
  `app/Support/PublicFront/PublicFrontConfigValidator.php`,
  `app/Console/Commands/NormalizePublicContentSettings.php`.
- Tests:
  `tests/Feature/PublicContentSettingsNormalizeCommandTest.php`.
- Docs:
  `docs/research/settings-performance/01-sp2-research.md`,
  `docs/research/settings-performance/01-sp2-implementation-plan.md`,
  `docs/phase-02/settings-performance-sp2-handoff.md`,
  `docs/phase-02/settings-performance-sp1-handoff.md`,
  `docs/phase-02/current-project-state.md`,
  `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`,
  `docs/phase-02/ai-development-lessons.md`.

## Tests Added Or Updated

- Added scoped validator coverage proving `validateGroups()` normalizes only
  selected top-level groups and ignores legacy-invalid siblings.
- Added normalize-command dry-run coverage proving the report is printed,
  invalid/missing/dropped values are surfaced, no settings are written, and no
  backup is created.
- Added normalize-command apply coverage proving a pre-change system backup is
  created and normalized JSON settings are persisted.

## Settings Performance Report

### Attribution Gate

Before memoization, run `sp2-attribution-20260713022520` reproduced the
missing SP1-sized cost on Yoni's local 37 KB payload:

| Phase | Cold | Warm 1 | Warm 2 |
|---|---:|---:|---:|
| `form.total_build` | 1286.839 ms | 1319.253 ms | 1321.055 ms |
| `form.inline_import_lock_hints` | 1248.850 ms | 1263.535 ms | 1261.816 ms |
| `form.components_array` / `form.tabs_component` | ~37.8 ms | ~55.5 ms | ~59.0 ms |
| `form.schema_components_assign` | ~0.001 ms | ~0.001 ms | ~0.001 ms |

The dominant cost was recursive inline import-lock hint attachment repeatedly
calling `SettingsLifecycleSchema::unitPathsForSemanticPath()` for duplicate
semantic paths.

### Final Optimized Monolith

Final run id: `sp2-final-20260713023719`.

Payload: 37,293 bytes.

| Phase | Cold | Warm 1 | Warm 2 |
|---|---:|---:|---:|
| `form.total_build` | 71.169 ms | 81.550 ms | 82.792 ms |
| `form.inline_import_lock_hints` | 33.338 ms | 22.938 ms | 23.152 ms |
| `settings.read_hydrate` | 17.543 ms | 4.214 ms | 4.325 ms |

Gate decision: stop after Job 2. The optimized monolith is already below the
prompt's canary target, so a large split would add risk without supporting
performance evidence.

## Normalize Command Notes

`php artisan settings:normalize-public-content` is the cleanup tool for legacy
stored public-front JSON. Default mode is dry-run and writes nothing.

`php artisan settings:normalize-public-content --apply` creates a system backup
before saving normalized JSON. The existing `SettingsSaved` listener may also
create the usual post-save normalized backup.

Yoni should run dry-run locally first, review the per-group report, then use
`--apply` only after confirming the backup row exists. Production should follow
the same dry-run-first pattern after deploy.

## Local Front Check Report

1. Existing settings-page mount was exercised by the final profiling run rather
   than a browser click-through; it mounted the monolith three times against the
   local 37 KB payload and logged 71-83 ms `form.total_build`.
2. New split-domain settings pages were not opened because they were not built;
   the attribution gate stopped that scope.
3. Normalize dry-run behavior was checked through focused Pest coverage and the
   registered command list; it reports changes and leaves settings unchanged.
4. Normalize apply behavior was checked through focused Pest coverage; it
   creates a pre-change system backup and writes normalized JSON.
5. Maintenance marker copy was not manually rechecked in a browser in this run;
   the existing SP1/maintenance tests remain the regression boundary.

## Commands Run

- Preflight: `git status --short --branch`; `git log --oneline --decorate -n 12`.
- Context/research: Laravel Boost `application_info`, `database_schema`, and
  `search_docs`; FilamentExamples `search_examples`; local source/vendor/docs
  inspection with `find`, `grep`, and `sed`.
- Syntax checks:
  `php -l app/Filament/Pages/PublicContentSettings.php`;
  `php -l app/Support/PublicFront/PublicFrontConfigValidator.php`;
  `php -l app/Console/Commands/NormalizePublicContentSettings.php`;
  `php -l tests/Feature/PublicContentSettingsNormalizeCommandTest.php`.
- Targeted tests:
  `php artisan test --compact tests/Feature/PublicContentSettingsNormalizeCommandTest.php`
  passed 3 tests, 42 assertions;
  `php artisan test --compact tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
  passed 13 tests, 310 assertions.
- Command registration:
  `php artisan list settings | grep normalize-public-content`.
- Profiling:
  `SETTINGS_PROFILING=true php artisan tinker --execute='...'` for
  `sp2-attribution-20260713022520`,
  `sp2-attribution-deduped-20260713022634`, and
  `sp2-final-20260713023719`.
- Documentation check:
  `git diff --check` passed before the handoff was written.

## Final Gate

Run 1:

1. Requirements sweep: passed. `git diff --check` was clean; pre-commit
   status showed only the SP2 code/docs/test changes; backlog triage was already
   tracked and unchanged.
2. `vendor/bin/pint --test`: passed.
3. `vendor/bin/filacheck`: passed with 0 issues.
4. `npm run build`: passed.
5. `php artisan test`: passed 450 tests, 4,035 assertions, 355.533 seconds.

## Assumptions

- The tracked backlog file is `docs/phase-02/back-log-triage-2026-07-13.md`;
  the prompt's `backlog-triage-2026-07-13.md` spelling is treated as the same
  expected backlog artifact referenced by the user attachment.
- Since the gate stopped the split, the prompt's final commit title is not used
  literally; the commit should describe the shipped evidence-backed fix and
  foundations.

## Deferred Issues

- The actual settings split should only be reconsidered if future profiling
  again shows page build cost above target after the memoization fix.
- TS2 page-test relocation remains unnecessary until domain settings pages
  exist.
- Card-template clone wiring remains a future rider because the card-template
  page was not created.
- Public listing fetch-window/lazy-options work remains the next kept pre-13
  main-queue item per the backlog triage, unless Yoni resequences it.

## Current Git Status

Before final commit: `main...origin/main [ahead 1]` with only SP2 tracked
modifications and new SP2 files staged/ready for the final commit.
