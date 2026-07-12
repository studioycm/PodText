# NAV1 Admin Navigation Handoff

Date: 2026-07-12

## Scope

Implemented `prompts/pre-13-prompts/admin-navigation-nav1-codex-prompt.md` as the single session step.

No Composer changes were made.

## Implemented

- Expanded `App\Filament\Support\AdminNavigationOrder` into the central admin navigation map for sort, group, and badge-deferred intent.
- Added ordered admin navigation groups: Content management, Taxonomy management, and Site management, with Hebrew labels and `Heroicon` enum icons.
- Moved the episode workspace create item to the ungrouped first position with `New episode` / `פרק חדש`.
- Moved public form submissions and Curator Media into the ungrouped pre-group segment.
- Moved Importer Settings out of the old import group and recorded the WB placement note in `current-project-state.md`.
- Added a public form submissions navigation badge for new submissions. Filament 5.6 has no native async sidebar badge API; this run defers the query until badge evaluation by passing a closure to `NavigationItem::badge()`, caches the computed count, and invalidates the cache on submission save/delete.
- Disabled the Curator Media badge to avoid an eager package count.
- Applied the workspace/default and classic/system naming convention to episode list header actions and row actions, including relation manager rows.
- Backfilled the IMG-B commit hash into the IMG-B handoff and current-state ledger.

## Requirement Classification

- Implemented: target navigation order, group order, group membership, translated labels, ungrouped-first placement, Curator group/sort override, public form submission badge, cached badge count, classic/system action suffixes, central-map test coverage, IMG-B hash backfill, NAV1 ledger row, research doc, implementation plan, and suite timing diagnosis.
- Already existed: `PublicFormSubmission` storage and admin resource, Curator Media resource, EP1 workspace pages/actions, public settings page, and test SQLite safety canaries.
- Deferred by prompt / TS1: suite performance fixes beyond zero-risk test-env verification.
- Not applicable: creating a new public form submissions resource, Composer/package changes, pushing to a remote, and `filacheck --fix`.
- Blocked: none.

## Files Changed

- Navigation/support: `app/Filament/Support/AdminNavigationOrder.php`, `app/Filament/Support/Concerns/UsesAdminNavigationOrder.php`, `app/Providers/Filament/AdminPanelProvider.php`, `app/Filament/Pages/Dashboard.php`.
- Resource/page placement: admin resources and pages that now rely on the central group map, plus `ContentItemResource` workspace navigation.
- Badge behavior: `app/Filament/Resources/PublicFormSubmissions/PublicFormSubmissionResource.php`, `app/Models/PublicFormSubmission.php`.
- Labels: `lang/en/admin.php`, `lang/he/admin.php`.
- Tests: `tests/Feature/AdminPhase02ResourcesTest.php`.
- Docs: this handoff, NAV1 research/plan docs, IMG-B handoff backfill, current state, and AI development lessons.

## Tests Added Or Updated

- Updated `orders every registered admin navigation resource and page through the central map` to assert the new central map, ungrouped-first placement, group order, group membership, translated labels, hidden Dashboard navigation, and no untracked registered navigation surfaces.
- Added `defers the public form submission navigation badge query until badge evaluation` to assert closure-backed badge evaluation, count/color/tooltip behavior, and cache invalidation after a new submission.
- Added `labels episode workspace actions as the defaults and classic actions as system actions` for list and relation-manager header/row action labels.

## Commands Run

- `php artisan test --compact tests/Feature/AdminPhase02ResourcesTest.php --filter="orders every registered admin navigation resource and page through the central map|defers the public form submission navigation badge query until badge evaluation|labels episode workspace actions as the defaults"` passed: 3 tests, 48 assertions.
- `php artisan test --compact tests/Feature/AdminPhase02ResourcesTest.php` passed: 23 tests, 403 assertions.
- Translation duplicate-key scan for `lang/en/admin.php` and `lang/he/admin.php` passed with no duplicates.
- `git diff --check` passed during the pre-gate sweep.
- `vendor/bin/pint --test` failed once on `UsesAdminNavigationOrder.php`; `vendor/bin/pint app/Filament/Support/Concerns/UsesAdminNavigationOrder.php` fixed formatting; later `vendor/bin/pint --test` passed.
- `vendor/bin/filacheck` failed once on uncached `getNavigationBadge()`; the badge was cached and invalidated; later `vendor/bin/filacheck` passed with 0 issues.
- `npm run build` passed.
- `php artisan test --profile` passed: 423 tests, 3,838 assertions, 485.000s.
- `vendor/bin/pest --profile` passed: 423 tests, 3,838 assertions, 488.943s, and produced the slowest-test profile list because the Laravel Artisan runner emitted only its JSON summary in this non-TTY session.

## Suite Timing Report

Required gate run:

```text
php artisan test --profile
{"tool":"pest","result":"passed","tests":423,"passed":423,"assertions":3838,"duration_ms":485000}
```

The required Artisan command passed, but in this environment it emitted only the machine-readable Pest summary and did not include the slowest-test list. Direct Pest profiling was run sequentially afterward to recover the profile list without changing code:

```text
vendor/bin/pest --profile
{"tool":"pest","result":"passed","tests":423,"passed":423,"assertions":3838,"duration_ms":488943,"profile":[{"test":"P\\Tests\\Feature\\PublicFrontJsonSettingsArchitectureTest::__pest_evaluable_it_saves_sanitized_public_front_config_through_the_settings_page_while_preserving_card_settings","file":"/Users/studioycm/Herd/PodText/vendor/pestphp/pest/src/Factories/TestCaseFactory.php(175) : eval()'d code","duration_ms":111899},{"test":"P\\Tests\\Feature\\PublicFrontCardTemplateBuilderTest::__pest_evaluable_it_saves_a_simple_card_template_definition_through_the_public_content_settings_page","file":"/Users/studioycm/Herd/PodText/vendor/pestphp/pest/src/Factories/TestCaseFactory.php(175) : eval()'d code","duration_ms":63520},{"test":"P\\Tests\\Feature\\AdminPhase02ResourcesTest::__pest_evaluable_it_saves_public_content_settings_through_the_settings_page","file":"/Users/studioycm/Herd/PodText/vendor/pestphp/pest/src/Factories/TestCaseFactory.php(175) : eval()'d code","duration_ms":44997},{"test":"P\\Tests\\Feature\\PublicFrontCustomColorsTest::__pest_evaluable_it_saves_custom_colors_through_the_settings_page_and_clears_stale_custom_values_for_semantic_tokens","file":"/Users/studioycm/Herd/PodText/vendor/pestphp/pest/src/Factories/TestCaseFactory.php(175) : eval()'d code","duration_ms":28232},{"test":"P\\Tests\\Feature\\PublicAboutPageContentTeamTest::__pest_evaluable_it_saves_about_content_blocks_and_team_profiles_through_the_admin_settings_page","file":"/Users/studioycm/Herd/PodText/vendor/pestphp/pest/src/Factories/TestCaseFactory.php(175) : eval()'d code","duration_ms":24532},{"test":"P\\Tests\\Feature\\SettingsImportExportTest::__pest_evaluable_it_renders_and_saves_maintenance_settings_from_the_admin_form","file":"/Users/studioycm/Herd/PodText/vendor/pestphp/pest/src/Factories/TestCaseFactory.php(175) : eval()'d code","duration_ms":15286},{"test":"P\\Tests\\Feature\\PublicFrontIconRegistryTest::__pest_evaluable_it_normalizes_saved_icon_aliases_through_the_settings_page","file":"/Users/studioycm/Herd/PodText/vendor/pestphp/pest/src/Factories/TestCaseFactory.php(175) : eval()'d code","duration_ms":14948},{"test":"P\\Tests\\Feature\\ImageMediaCuratorTest::__pest_evaluable_it_round_trips_public_settings_image_paths_through_the_curator_picker_without_changing_bytes","file":"/Users/studioycm/Herd/PodText/vendor/pestphp/pest/src/Factories/TestCaseFactory.php(175) : eval()'d code","duration_ms":9594},{"test":"P\\Tests\\Feature\\PublicFormsSubmissionsTest::__pest_evaluable_it_saves_public_form_definitions_through_the_admin_settings_page_as_JSON_settings","file":"/Users/studioycm/Herd/PodText/vendor/pestphp/pest/src/Factories/TestCaseFactory.php(175) : eval()'d code","duration_ms":9281},{"test":"P\\Tests\\Feature\\PublicDefaultImagesSettingsTest::__pest_evaluable_it_saves_no_image_mode_through_the_public_settings_page","file":"/Users/studioycm/Herd/PodText/vendor/pestphp/pest/src/Factories/TestCaseFactory.php(175) : eval()'d code","duration_ms":9176}]}
```

Cost drivers with evidence:

- The top 10 are all `PublicContentSettings` page save workflows. The first three alone are 111.899s, 63.520s, and 44.997s.
- Each settings-page save mounts the full Filament settings page schema. `PublicContentSettings::form()` builds many tabs/sections, card-template Builder blocks, public-form Builder blocks, About Builder blocks, icon selects, media pickers, rich editors, color pickers, import-lock sections, and maintenance fields even when a test edits one small subsection.
- Each save runs `mutateFormDataBeforeSave()`, which normalizes upload state and maintenance fields, then calls `PublicFrontConfigValidator::validate($data)` across every public-front settings group: card templates, menu, About, public forms, route labels, display defaults, default images, transcription policy, item page, podcasts page, contributors page, settings backups, import locks, and maintenance.
- Every `PublicContentSettings` save fires `SettingsSaved` in `AppServiceProvider`, which clears the public-front cache, creates a system settings backup, and forgets render-context/policy instances.
- `SettingsBackupManager::createSystem()` packages current settings, hashes the payload, writes backup rows when changed, and schedules snapshot rows. For system backups, `SettingsBackupSnapshotManager::scheduleForBackup()` creates thumbnail targets for `/` and `/podcasts`; with the test queue driver set to `sync`, `SettingsBackupSnapshotJob` can process those rows immediately and call `node scripts/settings-snapshots.mjs`.
- Most hot settings tests do not call `Queue::fake()`. `SettingsImportExportTest` does fake the queue in its file setup, and its hot settings save is notably lower at 15.286s despite still mounting the full form and validator path.
- The inspected hot tests do not show HTTP calls, sleeps, retries, or backoff loops. The dominant cost is full settings page/form/validator/save lifecycle work plus backup/snapshot side effects, not NAV1 code.
- `phpunit.xml` already has zero-risk test optimizations: `BCRYPT_ROUNDS=4`, `CACHE_STORE=array`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`, and `SESSION_DRIVER=array`. `tests/Pest.php` also forces the key test env values before app boot. No config-only optimization was missing.

TS1 proposal:

1. Highest expected savings: fake or disable settings backup snapshot jobs in settings-page tests that are not explicitly testing backups/snapshots. Evidence: hot tests trigger `SettingsSaved -> SettingsBackupManager::createSystem() -> SettingsBackupSnapshotJob`, and the file with `Queue::fake()` is much lower in the profile.
2. Split settings-page save tests by responsibility and move most public-front normalization assertions to `PublicFrontConfigValidator` unit tests or focused settings payload tests. Keep one or two full Filament form save smoke tests.
3. Add shared fixture/helper state for public settings page tests so repeated default payload construction and deep nested `.set()` chains are minimized.
4. Split or isolate the 110s-class settings save test so it does not validate every public-front subsystem through a single Livewire save path.
5. Re-evaluate parked `--parallel` only after the backup/snapshot side effect is controlled. Safety rationale remains good because the suite forces per-process SQLite `:memory:`, but queue/storage side effects need explicit fakes before parallelism.
6. Icon registry flake: the profiled run did include `PublicFrontIconRegistryTest::normalizes saved icon aliases through the settings page` at 14.948s and it passed. The evidence still points to shared static/cache/render-context state as the likely flake class, but NAV1 did not expose a new failure.

## Local Front Check Report

1. Open `/admin` in Hebrew and confirm the first sidebar item is `פרק חדש`, ungrouped.
2. Confirm the next ungrouped items are `רשומות טפסים` with a new-submissions badge and then `מדיה`.
3. Confirm the first group is `ניהול תוכן` and contains `פודקאסטים`, `פרקים`, and `תמלולים` in that order.
4. Confirm the second group is `ניהול סיווג` and contains `מתמללים`, `קטגוריות`, and `תגיות` in that order.
5. Confirm the third group is `ניהול אתר` and contains `מקטעי דף הבית`, `הגדרות תוכן ציבורי`, Admin UX settings, Settings backups, and Importer settings.
6. Create a new public form submission locally and refresh the admin navigation; confirm the form-submissions badge updates without the sidebar blocking initial navigation.
7. Open the Episodes list and confirm the primary header action says `פרק חדש`, while the classic create action says `פרק חדש (מערכת)`.
8. In the Episodes list row actions, confirm the workspace action says `עריכה` and the classic edit action says `עריכה (מערכת)`.
9. Repeat the action-label check inside a podcast's Episodes relation manager.
10. Switch between light and dark mode and confirm the Hebrew RTL navigation remains ordered and readable.
11. Confirm every previously reachable admin page remains reachable through the new navigation or direct URL, including Dashboard at `/admin`.

## Assumptions

- Dashboard should remain reachable at `/admin` but not appear in sidebar navigation, because Yoni's exact target list starts with the episode workspace create item.
- Filament 5.6 sidebar navigation has no native async/deferred badge API; closure-backed badge evaluation plus caching is the closest supported implementation.
- The public form submissions badge should count `PublicFormSubmissionStatus::New`, because that is the existing unhandled status.

## Deferred Issues

- TS1 performance fixes are written above only; no suite-performance behavior changes were made in NAV1.
- Future TOOLS1 should decide placement for future importer tools.

## Current Git Status Before Commit

```text
## main...origin/main [ahead 1]
M  app/Filament/Pages/AdminUxSettings.php
M  app/Filament/Pages/Dashboard.php
M  app/Filament/Pages/ImporterSettings.php
M  app/Filament/Pages/PublicContentSettings.php
M  app/Filament/Resources/Authors/AuthorResource.php
M  app/Filament/Resources/Categories/CategoryResource.php
M  app/Filament/Resources/ContentGroups/ContentGroupResource.php
M  app/Filament/Resources/ContentItems/ContentItemResource.php
M  app/Filament/Resources/ContentItems/Pages/ListContentItems.php
M  app/Filament/Resources/ContentTags/ContentTagResource.php
M  app/Filament/Resources/HomepageSections/HomepageSectionResource.php
M  app/Filament/Resources/PublicFormSubmissions/PublicFormSubmissionResource.php
M  app/Filament/Resources/SettingsBackups/SettingsBackupResource.php
M  app/Filament/Resources/Transcriptions/TranscriptionResource.php
M  app/Filament/Support/AdminNavigationOrder.php
M  app/Filament/Support/Concerns/UsesAdminNavigationOrder.php
M  app/Models/PublicFormSubmission.php
M  app/Providers/Filament/AdminPanelProvider.php
M  docs/phase-02/ai-development-lessons.md
M  docs/phase-02/current-project-state.md
M  docs/phase-02/images-arc-imgb-handoff.md
M  lang/en/admin.php
M  lang/he/admin.php
M  tests/Feature/AdminPhase02ResourcesTest.php
?? docs/phase-02/admin-navigation-nav1-handoff.md
?? docs/phase-02/admin-navigation-nav1-implementation-plan.md
?? docs/research/admin-navigation/
```

## Commit hash

Final NAV1 commit hash is reported in the session final after this handoff is committed.
