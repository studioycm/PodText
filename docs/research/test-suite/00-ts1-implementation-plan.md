# TS1 Test Suite Performance Implementation Plan

Date: 2026-07-12

## Preflight

- `git status --short --branch` was clean, with `main` ahead of `origin/main` by the local TS1 prompt commit.
- Recent commits confirmed TS1 prompt at `a4abb66`, MP2 at `465967f`, and NAV1 at `e59705b`.
- No Composer changes are planned.

## Tool Evidence

- Laravel Boost was available. `application_info` reported Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3, and Pest 4.7.4.
- Boost docs confirmed Laravel queue fakes can fake specific job classes while allowing other jobs to run normally.
- Boost docs confirmed Filament navigation sort and group behavior, including ungrouped items at the start of navigation.
- FilamentExamples exposed `search_examples` only. Relevant snippets showed `NavigationItem::sort()`, grouped/ungrouped navigation items, and the panel including `Dashboard::class` in `pages()`. No read/fetch/source-detail tool was exposed.

## Local Findings

- NAV1 intentionally hid Dashboard navigation. TS1 reverses that assumption: Dashboard must be the first ungrouped sidebar item through `AdminNavigationOrder`.
- MP2 handoff still says the final gate result is only in the session final, and the MP2 hash is described by message rather than stamped as `465967f`.
- `bootstrap/app.php` duplicates identical maintenance 419 rendering logic in both `render()` and `respond()` exception hooks.
- The hot settings-page tests still run the `SettingsSaved` listener. That listener clears the public front cache, creates a system settings backup, and schedules `SettingsBackupSnapshotJob`; with the test sync queue, the snapshot job can execute the Node script unless faked.
- Backup and snapshot tests already cover real backup/snapshot behavior and keep their assertions. Non-backup settings-page tests do not assert snapshot processing.

## Implementation Plan

1. Job 0 corrections:
   - Restore Dashboard as the first ungrouped admin navigation item in the central map and update the central-map test.
   - Add the missing NAV1 ledger row and stamp MP2 commit `465967f` wherever the MP2 handoff/current ledger reference it by message only.
   - Record the user-provided MP2 final gate text gap exactly as provided: `<PASTE THE SUITE LINE FROM MP2'S SESSION FINAL HERE, e.g. "php artisan test passed once, NNN tests, N,NNN assertions">`.
   - Refine the AI lesson so handoff file gate results are required after the gate passes and before commit.
   - Deduplicate maintenance 419 rendering through one shared helper while preserving behavior.
2. Job 1 performance fix:
   - Add a focused Pest helper that fakes only `SettingsBackupSnapshotJob`.
   - Call that helper in non-backup settings-page test files that save `PublicContentSettings`.
   - Do not fake queue behavior in backup/snapshot tests that assert backup rows or snapshot dispatch.
   - Leave proposal #2 deferred as TS2.
   - Leave proposal #3 unless a bounded mechanical setup extraction proves useful; the main removable side effect is snapshot processing.
   - Do not split the 110s `PublicFrontJsonSettingsArchitectureTest` unless every assertion is preserved without increasing save side effects. Current plan is to preserve the test and remove only the snapshot side effect.
3. Verification:
   - Run targeted tests while iterating.
   - Final gate order will be: requirements sweep, `vendor/bin/pint --test`, `vendor/bin/filacheck`, `npm run build`, then one green `php artisan test --profile` last on the final code state.
   - If Artisan profile emits only JSON without slowest-test details, run `vendor/bin/pest --profile` once sequentially afterward to recover the top profile list.

