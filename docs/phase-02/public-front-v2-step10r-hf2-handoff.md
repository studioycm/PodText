# Public Front v2 Step 10R-HF2 Handoff

## Status

Step 10R-HF2 is complete pending final review. This urgent hotfix jumps the queue to
repair the production MySQL failure in the S2V snapshot migration. S1c and the
Importer Workbench remain paused until Yoni resumes them.

## Summary

- Edited the existing `2026_07_09_000010_create_settings_backup_snapshots_table`
  migration in place.
- Added a first-statement `Schema::dropIfExists('settings_backup_snapshots')` rescue
  for deploys where MySQL created the table but failed while adding the oversized
  composite unique index.
- Bounded finite-token indexed columns: `screen_key` 32, `theme` 16, `viewport` 32,
  `kind` 16, and `format` 8.
- Kept `resolved_url` at 2048 and `path` at 255 because neither participates in the
  unique index.
- Added regression coverage that the table exists after migrations and the
  `backup_snapshot_unique_target` constraint still rejects duplicate snapshot targets.
- Backfilled S1b hash `ada29fb` and S1b gate outcomes into the S1b handoff.
- Added the durable MySQL composite-index length lesson to the ledger guardrails and
  AI development lessons.

## Files Changed

- `database/migrations/2026_07_09_000010_create_settings_backup_snapshots_table.php`
- `tests/Feature/SettingsBackupSnapshotsTest.php`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/ai-development-lessons.md`
- `docs/phase-02/public-front-v2-step10r-s1b-handoff.md`
- `docs/phase-02/public-front-v2-step10r-hf2-handoff.md`

## Tests

Focused verification passed:

- `php artisan test --compact tests/Feature/SettingsBackupSnapshotsTest.php` - 8 tests passed

Full gate passed:

- `vendor/bin/pint --dirty --format agent`
- `php artisan test` - 344 tests passed
- `vendor/bin/pint --test`
- `vendor/bin/filacheck`
- `npm run build`
- `git diff --check`

## Implementation Notes

- Editing the existing migration is intentional for this hotfix. The MySQL production
  environment never recorded this migration, and the prompt states no successful MySQL
  environment has run it.
- Environments that already recorded this migration do not re-run `up()`, so the
  rescue drop does not remove completed snapshot data.
- The new unique-index math is `8 + 4 * (32 + 16 + 32 + 16 + 8) = 424 bytes`, safely
  below InnoDB's 3072-byte key limit for utf8mb4.
- SQLite cannot reproduce the MySQL key-length failure, so the regression focuses on
  clean migration creation and preserving duplicate-target uniqueness.

## Deviations

- `php artisan migrate:status` preflight failed locally because the configured default
  SQLite database path resolves to `podtext`, which does not exist. The tree was clean,
  so implementation continued and the required test gate used Laravel's test database.
- No S1c, Importer Workbench, P2/P3, AX, SL, B4, C2, 9F, Prompt 13, or Step 11 work
  was implemented.

## Commit hash

Commit hash: `f719d30`.

## Local Front Check Report

1. Production deploy/retry check: rerun migrations on the affected MySQL environment.
   Expected: the orphan empty `settings_backup_snapshots` table is dropped and
   recreated, the migration records successfully, and no `Specified key was too long`
   or `table already exists` error appears.
2. Open `/admin/settings-backups` in Hebrew RTL. Expected: backup rows and the home
   thumbnail column render as before; no visible UI change is introduced by HF2.
3. Create a manual backup from `/admin/settings-backups`. Expected: snapshot rows are
   scheduled normally with finite screen/theme/viewport/kind/format tokens.
4. Open a backup's `Snapshots` action. Expected: the gallery still groups by screen
   and theme, existing snapshot download/retry controls still render, and light/dark
   admin themes remain readable.
5. Retry a failed snapshot if one exists. Expected: retry still targets the same
   snapshot row and does not create duplicate rows for the same backup/screen/theme/
   viewport/kind/format target.
