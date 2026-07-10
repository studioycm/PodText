# Public Front v2 Step 10R-S1d Research

## Scope

Step 10R-S1d adds a structured settings-import result report and completes MP1
maintenance hardening. The Importer Workbench is explicitly out of scope.

## Preflight

- Active prompt: `prompts/pre-13-prompts/import-report-mp1-hardening-s1d-codex-prompt.md`.
- Working tree preflight was clean on `main`, tracking `origin/main` and ahead by one
  local prompt-doc commit.
- Recent required baseline:
  - `389cb0f feat: add inline import locks on settings page`
  - `8458a5d feat: add maintenance mode page and settings`
  - `a9c5e61 docs: update prompt for S1d middleware hardening and provider audit findings`
- Local runtime database is MySQL. Tests are forced to SQLite `:memory:` by
  `phpunit.xml`, `tests/Pest.php`, and the base `TestCase` canary.
- `php artisan migrate --no-interaction` was run before implementation planning and
  reported nothing to migrate.

## Boost Research

- Boost `application_info` reported PHP 8.4, Laravel 13.18.0, Filament 5.6.7,
  Horizon 5.47.2, Livewire 4.3.3, Pest 4.7.4, and MySQL as the runtime database.
- Boost `search_docs` confirmed Filament panel middleware can be marked persistent:
  default panel middleware runs on the first page load, while
  `Panel::middleware([...], isPersistent: true)` re-applies that middleware to
  subsequent Livewire requests.
- Boost `search_docs` confirmed Filament `authMiddleware()` is a separate panel
  concern. Public panels can intentionally have no auth middleware, while the admin
  panel gates through Filament Authenticate.
- Boost `search_docs` confirmed Horizon dashboard access is controlled by
  `Gate::define('viewHorizon', ...)` in `HorizonServiceProvider`.
- Boost `database_schema(summary, filter=settings_backup)` showed
  `settings_backup_versions` currently lacks an `import_report` column and is the
  natural persistence anchor for before-import backup rows.

## FilamentExamples Research

- Query batch 1: `Filament table action modal read only report TextEntry sections`,
  `Filament wizard summary chips filter table locked status`, `Filament SettingsPage
  panel middleware action modal`.
- Access level: search/snippet only. No source/detail fetch tool was exposed.
- Useful patterns:
  - Multi-panel provider snippets preserve the install-default middleware stack and add
    custom behavior through panel-local `middleware()` or `authMiddleware()` calls.
  - Action modal snippets use `Action::make()->schema([...])` for read-only report
    modals.
  - Infolist snippets use `Section` and `TextEntry` for grouped display, which fits
    the backup row "Import report" modal.
- Pattern to copy: keep report display inside the existing backups Resource table
  action group and render derived groups read-only.
- Pattern to avoid: do not create a custom import controller or bypass the existing
  settings import wizard/backup manager boundary.

## MP1 Hardening Audit

- Item 1 is a real risk. The maintenance content fields are currently visible-gated in
  the settings form, matching the S1c import-lock wipe class: hidden/absent fields can
  be rehydrated to defaults during unrelated saves. S1d must add the unrelated-save
  regression and preserve stored `maintenance.rich_html` and
  `maintenance.raw_html_override` byte-identical.
- Item 2 is missing. `RenderMaintenanceMode` is currently inside the public panel's
  default `->middleware([...])` array with no persistence. S1d must move it to a
  separate `->middleware([RenderMaintenanceMode::class], isPersistent: true)` call so
  only that middleware is persistent and the default stack remains untouched.
- Item 3 remains to implement. The admin-bypass coverage must include a Livewire
  interaction while maintenance mode is enabled.
- Item 4 remains to implement with the mid-run expansion. Add the `sensitive` semantic
  to `maintenance.enabled`, `maintenance.title`, `maintenance.rich_html`, and
  `maintenance.raw_html_override`. The three content fields keep their existing
  `front_text` semantic; `sensitive` is additive. Sensitive units stay selectable but
  are never preselected by imports.
- Item 5 remains to strengthen. The settings-page save path must assert
  `maintenance.rich_html` persists as an HTML string, not TipTap JSON.
- Item 6 is already mostly correct. The middleware uses `response()->view(..., 503)`
  with `Retry-After`; S1d should verify the standalone shell declares `lang="he"`,
  `dir="rtl"`, charset, and viewport.

## Panel Middleware/Auth Audit

- Verified correct: both panel providers use the Filament install-default middleware
  stack in the correct order:
  `EncryptCookies` -> `AddQueuedCookiesToResponse` -> `StartSession` ->
  `AuthenticateSession` -> `ShareErrorsFromSession` -> `PreventRequestForgery` ->
  `SubstituteBindings` -> Filament internals.
- Verified correct: the public panel has no `authMiddleware()` because it is the guest
  panel; the admin panel gates through `Filament\Http\Middleware\Authenticate` in its
  `authMiddleware()` call.
- Verified accepted: no throttling exists on public routes. This is not a defect for
  S1d. Filament login has built-in rate limiting, and public-browsing throttling
  belongs at the server/nginx layer. If app-level browsing throttling is ever wanted,
  a public-panel throttle middleware is the designated extension point.
- Verified behavior: default panel middleware is first-page-load only; persistent
  middleware reruns on Livewire requests. The maintenance middleware relies on being
  the only persistent public-panel middleware.
- Finding: `HorizonServiceProvider` currently defines `viewHorizon` as an empty email
  allowlist, which leaves production `/horizon` effectively local-only instead of
  matching the admin-panel access rule. S1d must define the gate through the admin panel
  access contract and test admin-capable user allowed / guest denied.
- Guardrail: `User::canAccessPanel()` currently admits every authenticated user to the
  admin panel. That is safe only while the only account is Yoni's. An `is_admin` gate
  must land before any non-admin account type exists.

## Import Report Findings

- `SettingsBackupManager::import()` currently returns a bare array of applied paths.
  S1d should return a small `SettingsImportReport` value object containing mode,
  source label, generated-at timestamp, before-import backup id, grouped outcomes, and
  warnings.
- The before-import backup row is created inside the import transaction and is the
  natural persistence anchor. Add a nullable JSON `import_report` column to
  `settings_backup_versions` and store the report there.
- `SettingsPackageImportAnalyzer` already emits enough row state to derive applied,
  skipped locked, skipped exists, skipped unchanged, and error groups. It needs one
  sensitive-unit selection rule: sensitive rows are selectable but default to
  unselected.
- The selection table already has changed/added/removed/all filters. S1d should add a
  locked filter and derive dry-run chips from the analysis rows instead of storing
  literal counts.

## Test-Suite Performance Profile

`php artisan test --profile` passed but the runner only emitted the compact JSON
summary. `vendor/bin/pest --profile --compact --colors=never` was then run
sequentially to capture the slow list. It passed 368 tests / 3447 assertions in
447.854 seconds.

Ten slowest tests:

1. `PublicFrontJsonSettingsArchitectureTest::it_saves_sanitized_public_front_config_through_the_settings_page_while_preserving_card_settings` - 110.264s
2. `PublicFrontCardTemplateBuilderTest::it_saves_a_simple_card_template_definition_through_the_public_content_settings_page` - 61.719s
3. `AdminPhase02ResourcesTest::it_saves_public_content_settings_through_the_settings_page` - 44.842s
4. `PublicFrontCustomColorsTest::it_saves_custom_colors_through_the_settings_page_and_clears_stale_custom_values_for_semantic_tokens` - 27.284s
5. `PublicAboutPageContentTeamTest::it_saves_about_content_blocks_and_team_profiles_through_the_admin_settings_page` - 23.530s
6. `SettingsImportExportTest::it_renders_and_saves_maintenance_settings_from_the_admin_form` - 14.717s
7. `PublicFrontIconRegistryTest::it_normalizes_saved_icon_aliases_through_the_settings_page` - 14.433s
8. `PublicFormsSubmissionsTest::it_saves_public_form_definitions_through_the_admin_settings_page_as_JSON_settings` - 8.842s
9. `PublicDefaultImagesSettingsTest::it_saves_no_image_mode_through_the_public_settings_page` - 8.693s
10. `SettingsImportExportTest::it_toggles_import_locks_from_inline_section_and_deep_field_actions` - 8.194s

Dominant cost: repeated full render/save cycles through the very large Public Content
Settings page, plus settings writes/validator work. The Browser suite exists and is
not empty, so excluding it from the default run is not a safe S1d quick win. Parallel
testing is likely safe for this repo because it is one command and each Paratest
process receives its own SQLite `:memory:` database, but adopting it should be a later
Yoni-approved performance choice.
