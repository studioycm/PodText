# TS1 Test Suite Performance Handoff

Date: 2026-07-12

## Scope

Implemented `prompts/pre-13-prompts/test-suite-performance-ts1-codex-prompt.md` as the single session step.

No Composer changes were made.

## Implemented

- Restored Dashboard to the admin sidebar as the first ungrouped navigation item, before `פרק חדש`, through `AdminNavigationOrder`.
- Inserted the missing NAV1 mini-step ledger row with commit `e59705b`.
- Backfilled MP2 commit `465967f` into the MP2 handoff, current state, and mini-step ledger.
- Recorded the MP2 final-gate gap explicitly because the kickoff message did not include the exact MP2 suite line.
- Refined the AI development lesson: gate outcomes must be written into the handoff file, not only reported in chat.
- Deduplicated the two maintenance 419 response bodies in `bootstrap/app.php` through one shared closure.
- Added `fakeSettingsBackupSnapshotQueue()` as a Pest helper that fakes only `SettingsBackupSnapshotJob`.
- Applied that helper to non-backup settings-page test files that save `PublicContentSettings`.

## Requirement Classification

- Implemented: Job 0.1 Dashboard restoration, Job 0.2 NAV1 ledger row, Job 0.3 MP2 hash backfill and gate-outcome gap, Job 0.4 handoff-record lesson, Job 0.5 maintenance 419 dedupe, Job 1.1 snapshot side-effect suppression, TS1 plan doc, current-state update, mini-step ledger update, and TS1 handoff.
- Already existed: NAV1 timing report before-measurement, backup/snapshot behavior tests, and `SettingsImportExportTest` queue fake coverage.
- Deferred by prompt: proposal #2, moving normalization assertions to validator/unit tests, remains the TS2 candidate.
- Deferred by evidence: `--parallel` remains parked pending post-TS1 review; the suite still has high settings-page form/validator cost.
- Not applied: proposal #3 shared payload extraction and proposal #4 splitting the 110s test, because this session was constrained to removing side-effect cost only and preserving all assertions.
- Not applicable: Composer changes, UI translation edits, test deletion, assertion removal, `filacheck --fix`, remote push.
- Blocked: none.

## Files Changed

- Navigation/code: `app/Filament/Pages/Dashboard.php`, `app/Filament/Support/AdminNavigationOrder.php`.
- Maintenance exception handling: `bootstrap/app.php`.
- Test side-effect control: `tests/Pest.php` and non-backup settings-page test files.
- Tests updated: `tests/Feature/AdminPhase02ResourcesTest.php`.
- Docs: TS1 plan/handoff, MP2 handoff, current project state, AI lessons, and the mini-step ledger.

## Tests Added Or Updated

- Updated the central admin navigation test to assert Dashboard is registered, ungrouped, and first.
- Added file-level snapshot-job fakes in non-backup settings-page tests without removing assertions.
- Backup/snapshot tests were intentionally not weakened.

## Commands Run

- Preflight: `git status --short --branch` reported `## main...origin/main [ahead 1]`; `git log --oneline -4` confirmed `a4abb66`, `465967f`, `a76a1a3`, and `e59705b`.
- Syntax checks passed: `php -l bootstrap/app.php`, `php -l app/Filament/Support/AdminNavigationOrder.php`, `php -l app/Filament/Pages/Dashboard.php`, `php -l tests/Pest.php`.
- Targeted: `php artisan test --compact tests/Feature/AdminPhase02ResourcesTest.php --filter="orders every registered admin navigation resource and page through the central map|saves public content settings through the settings page"` passed: 2 tests, 59 assertions, 39.495s.
- Targeted: `php artisan test --compact tests/Feature/PublicMaintenanceModeTest.php --filter="renders stale csrf maintenance form errors without exposing the live site"` passed: 1 test, 4 assertions, 6.122s.
- Targeted hot settings batch passed: 8 tests, 138 assertions, 229.024s.
- Final gate:
  - Requirements sweep passed: `git diff --check` clean; no Composer/package diffs; test diff review showed no test deletions or assertion removals.
  - `vendor/bin/pint --test` passed.
  - `vendor/bin/filacheck` passed with 0 issues.
  - `npm run build` passed.
  - `php artisan test --profile` passed once on the final code state: 431 tests, 3,891 assertions, 472.585s.
  - `vendor/bin/pest --profile` recovery run passed: 431 tests, 3,891 assertions, 481.962s, and produced the slowest-test list missing from Artisan's JSON output.

## Suite Timing Report

Before measurement from NAV1:

- `php artisan test --profile` passed: 423 tests, 3,838 assertions, 485.000s.
- `vendor/bin/pest --profile` passed: 423 tests, 3,838 assertions, 488.943s.
- NAV1 top three settings-save tests: 111.899s, 63.520s, and 44.997s.

After measurement from TS1 final gate:

```text
php artisan test --profile
{"tool":"pest","result":"passed","tests":431,"passed":431,"assertions":3891,"duration_ms":472585}
```

Artisan emitted only the JSON summary without the slowest-test list, so the prompt-authorized recovery command was run once:

```text
vendor/bin/pest --profile
{"tool":"pest","result":"passed","tests":431,"passed":431,"assertions":3891,"duration_ms":481962,"profile":[{"test":"P\\Tests\\Feature\\PublicFrontJsonSettingsArchitectureTest::__pest_evaluable_it_saves_sanitized_public_front_config_through_the_settings_page_while_preserving_card_settings","duration_ms":104813},{"test":"P\\Tests\\Feature\\PublicFrontCardTemplateBuilderTest::__pest_evaluable_it_saves_a_simple_card_template_definition_through_the_public_content_settings_page","duration_ms":57908},{"test":"P\\Tests\\Feature\\AdminPhase02ResourcesTest::__pest_evaluable_it_saves_public_content_settings_through_the_settings_page","duration_ms":40851},{"test":"P\\Tests\\Feature\\PublicFrontCustomColorsTest::__pest_evaluable_it_saves_custom_colors_through_the_settings_page_and_clears_stale_custom_values_for_semantic_tokens","duration_ms":21641},{"test":"P\\Tests\\Feature\\PublicAboutPageContentTeamTest::__pest_evaluable_it_saves_about_content_blocks_and_team_profiles_through_the_admin_settings_page","duration_ms":21018},{"test":"P\\Tests\\Feature\\SettingsImportExportTest::__pest_evaluable_it_renders_and_saves_maintenance_settings_from_the_admin_form","duration_ms":14602},{"test":"P\\Tests\\Feature\\PublicFrontIconRegistryTest::__pest_evaluable_it_normalizes_saved_icon_aliases_through_the_settings_page","duration_ms":11561},{"test":"P\\Tests\\Feature\\PublicMaintenanceModeTest::__pest_evaluable_it_keeps_the_maintenance_form_submission_route_unavailable_when_maintenance_or_the_form_is_disabled","duration_ms":10966},{"test":"P\\Tests\\Feature\\SettingsImportExportTest::__pest_evaluable_it_toggles_import_locks_from_inline_section_and_deep_field_actions","duration_ms":8148},{"test":"P\\Tests\\Feature\\PublicMaintenanceModeTest::__pest_evaluable_it_injects_the_maintenance_form_at_the_raw_html_marker_and_falls_back_when_the_marker_is_missing","duration_ms":8136}]}
```

Top-10 after list:

| Rank | Test | Duration |
|---|---:|---:|
| 1 | `PublicFrontJsonSettingsArchitectureTest::it saves sanitized public front config...` | 104.813s |
| 2 | `PublicFrontCardTemplateBuilderTest::it saves a simple card template definition...` | 57.908s |
| 3 | `AdminPhase02ResourcesTest::it saves public content settings through the settings page` | 40.851s |
| 4 | `PublicFrontCustomColorsTest::it saves custom colors...` | 21.641s |
| 5 | `PublicAboutPageContentTeamTest::it saves about content blocks...` | 21.018s |
| 6 | `SettingsImportExportTest::it renders and saves maintenance settings from the admin form` | 14.602s |
| 7 | `PublicFrontIconRegistryTest::it normalizes saved icon aliases...` | 11.561s |
| 8 | `PublicMaintenanceModeTest::it keeps the maintenance form submission route unavailable...` | 10.966s |
| 9 | `SettingsImportExportTest::it toggles import locks...` | 8.148s |
| 10 | `PublicMaintenanceModeTest::it injects the maintenance form...` | 8.136s |

Attribution:

- Required Artisan gate improved from NAV1's 485.000s to 472.585s while the suite grew from 423 to 431 tests and from 3,838 to 3,891 assertions.
- Pest profile recovery improved from NAV1's 488.943s to 481.962s with the same larger suite.
- The original top five settings-page saves improved from 111.899s, 63.520s, 44.997s, 28.232s, and 24.532s to 104.813s, 57.908s, 40.851s, 21.641s, and 21.018s.
- Remaining cost is still dominated by full Filament settings-page form construction and whole-config validation. That supports leaving proposal #2 as the TS2 candidate.
- `--parallel` stays parked. TS1 reduced one sync-queue side effect, but storage/queue safety and the remaining form-lifecycle cost still need evidence before enabling parallel runs.

## Tooling Notes

- Laravel Boost was available. Used `application_info` and version-aware `search_docs` for Laravel queue fakes, Pest hooks, and Filament navigation sort/group behavior.
- FilamentExamples MCP exposed search-only access. Searches covered navigation sort, grouped/ungrouped sidebar placement, and Dashboard page registration. No source/read/detail tool was available.

## Local Front Check Report

1. Run `php artisan test` locally and compare total wall time against NAV1's 485-489s baseline.
2. Open `/admin` as an authenticated admin.
3. Confirm Dashboard is the first sidebar item.
4. Confirm `פרק חדש` is immediately after Dashboard in the ungrouped section.
5. Confirm `רשומות טפסים` and `מדיה` remain after `פרק חדש`.
6. Confirm the grouped sections still appear after the ungrouped items.

## Assumptions

- The MP2 final gate numbers were not provided in the TS1 kickoff message; the placeholder text is not a usable suite line.
- Faking only `SettingsBackupSnapshotJob` in non-backup settings-page tests preserves system-backup row creation while removing Node snapshot processing.

## Deferred Issues

- TS2 candidate: migrate normalization assertions from full Filament settings-page saves to focused validator/settings tests.
- `--parallel` remains parked pending post-TS1 evidence review and storage/queue safety decisions.
- The full settings page lifecycle remains expensive because form construction and whole-config validation still run.

## Current Git Status Before Commit

```text
## main...origin/main [ahead 1]
 M app/Filament/Pages/Dashboard.php
 M app/Filament/Support/AdminNavigationOrder.php
 M bootstrap/app.php
 M docs/phase-02/ai-development-lessons.md
 M docs/phase-02/current-project-state.md
 M docs/phase-02/maintenance-form-mp2-handoff.md
 M docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md
 M tests/Feature/AdminPhase02ResourcesTest.php
 M tests/Feature/ImageMediaCuratorTest.php
 M tests/Feature/PublicAboutPageContentTeamTest.php
 M tests/Feature/PublicDefaultImagesSettingsTest.php
 M tests/Feature/PublicFormsSubmissionsTest.php
 M tests/Feature/PublicFrontCardTemplateBuilderTest.php
 M tests/Feature/PublicFrontCustomColorsTest.php
 M tests/Feature/PublicFrontIconRegistryTest.php
 M tests/Feature/PublicFrontJsonSettingsArchitectureTest.php
 M tests/Pest.php
?? docs/phase-02/test-suite-performance-ts1-handoff.md
?? docs/research/test-suite/
```

## Commit hash

Pending final TS1 commit: `perf: cut settings test suite cost and restore dashboard navigation`.
