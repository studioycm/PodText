# Media Program Package 1 Minimal Kernel and Conversion Handoff

## Scope and baseline

This handoff closes only Option
`MEDIA-INV-O1-RESET-CLEANUP-P1-MINIMAL-KERNEL` from Laravel Simplifier audit
`LS-20260723-MEDIA-INVENTORY-FIRST-RESET-01`.

Implementation began on `main` at
`b455d5d546c5902edebaade2ad31c34bbfef3d2f`, 13 commits ahead of
`origin/main`. The initial unfinished overbuilt draft comprised 79 modified
tracked files and 54 default-status untracked entries; exact enumeration found
55 files because `tests/Support/` was collapsed. Only attributable draft files
and hunks were removed. No reset, checkout, stash, clean, branch, worktree or
push occurred.

The active media documents were rebased before code. Package 1 adds only the
portable identity kernel, attachment bridge and database-only Curator
conversion. Packages 2-5 remain separately gated.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| `MI-R001`-`MI-R003` All Media, Files Discovery and visible Needs Repair | Deferred to Packages 2 and 5 | Package 1 preserves every Curator row but does not change gallery queries or add Files Discovery. |
| `MI-R004`-`MI-R007` one authority per job | Implemented | `media_id` remains local owner authority, MediaAsset key is portable identity, Curator ID is provider identity and `curator.path` remains file location. |
| `MI-R008`-`MI-R012` minimal assets/bindings/bridge | Implemented | One minimal asset/binding per Curator row, immutable unique keys, restrictive relations and nullable attachment bridge are covered. |
| `MI-R013`-`MI-R017` owner/settings reconciliation, preservation, transaction and idempotence | Implemented | Existing attachments win, unique paths may bridge, ambiguous paths report, settings repair only through unique paths, compatibility values remain and all writes share one transaction. |
| `MI-R018`-`MI-R020` database-only conversion and visible diagnostics | Implemented | Only existence checks touch storage; missing files, duplicate paths and unresolved owners/settings remain represented and reported. |
| `MI-R021`-`MI-R027` D01, inventory delivery and picker All Media | Deferred to Package 2 | No public resolver, gallery or picker behavior was changed. |
| `MI-R028`-`MI-R030` new acquisition validation and SVG/URL boundaries | Deferred to Package 3 | Existing controls remain; Package 1 adds no validator, sanitizer or network client. |
| `MI-R031`-`MI-R032` physical lifecycle journals and reference protection | Deferred to Package 5 | Package 1 performs no physical mutation or deletion. |
| `MI-R033`-`MI-R035` schema timing and rejected proof machinery | Implemented | No folders, lifecycle, trust, checksum, normalization, storage duplication, capability state, manifest, digest or conversion journal was added. |
| `MI-R036` production-shaped fixtures | Implemented | 15-null, 15-valid and 403-row/108-owner fixtures pass, including SVG/oversized/duplicate-byte/rowless-file facts without file import. |
| `MI-R037` ordinary existing boundary correctness | Already existed / regression-tested | Focused media, settings, import/export, authorization and public-media regressions pass; no dedicated security audit was performed. |
| `MI-R038` no dedicated security phase | Implemented | None was added. |
| `MI-R039` no live environment action | Implemented | No local-development or production database, storage, cache, migration or conversion action ran. |
| `MI-R040` no dependencies/worktree/branch/push | Implemented | Manifests and lockfiles are unchanged; the existing checkout and branch were preserved. |

## Files changed

### Minimal kernel and conversion

- `app/Console/Commands/ConvertCuratorMediaAssets.php`
- `app/Models/MediaAsset.php`
- `app/Models/MediaProviderBinding.php`
- `app/Models/Media.php`
- `app/Models/MediaAttachment.php`
- `app/Support/Media/CuratorMediaAssetConversionReport.php`
- `app/Support/Media/CuratorMediaAssetConverter.php`
- `app/Support/Media/MediaAttachmentManager.php`
- `app/Support/SettingsLifecycle/SettingsMediaIdentityProjector.php`
- `database/migrations/2026_07_23_000000_create_media_asset_kernel_tables.php`
- `database/migrations/2026_07_23_000001_add_media_asset_id_to_media_attachments_table.php`

### Tests

- `tests/Feature/MediaAssetKernelTest.php`
- `tests/Feature/CuratorMediaAssetConversionTest.php`
- `tests/Feature/CuratorMediaAssetConversionCommandTest.php`
- `tests/Feature/CuratorMediaAssetProductionShapeTest.php`

### Canonical documentation reset and closeout

- `docs/phase-02/media-program-context.md`
- `docs/phase-02/images-media-track-plan.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/research/media-program/00-media-program-requirements-decisions-and-method.md`
- `docs/research/media-program/01-media-program-research.md`
- `docs/research/media-program/02-media-program-master-plan.md`
- `docs/research/media-program/03-local-production-baselines.md`
- `docs/research/media-program/04-active-document-supersession-map.md`
- all five pairs under `docs/research/media-program/packages/`
- this handoff

The invalid untracked Package 1 draft handoff was deleted with the other exact
task-owned draft files. Historical committed handoffs were not rewritten.

## Tests added or updated

- Exact minimal schema, index, foreign-key, relationship and key-immutability
  contracts.
- Unique valid, lowercase valid, missing, malformed and duplicate key behavior.
- Existing attachment authority, stale compatibility-path repair, unique-path
  bridging, duplicate-path refusal, settings reconciliation and rollback.
- Report-only and explicit-apply JSON behavior plus human report output.
- 15-null, 15-valid and production-shaped 403-row/108-owner fixtures.
- Existence-only storage proof and exact file/path/metadata preservation.
- Review regressions for exact lowercase-key reuse and obsolete settings keys.

## Commands and results

| Command / check | Result |
|---|---|
| Mandatory orientation, Git history/status and committed-versus-draft inventory | PASS; baseline and exact task ownership confirmed without live probes. |
| Canonical documentation reset plus `git diff --check` | PASS. |
| Exact reverse-patch cleanup | PASS; 72 tracked non-doc draft files restored and 55 exact untracked draft files removed. |
| First kernel test invocation | Tool-input import escaping error; corrected before counting a RED result. |
| Kernel test before implementation | Expected RED: tables/classes absent. |
| Kernel/model focused proof | PASS: 14 tests / 77 assertions. |
| Conversion tests before implementation | Expected RED: converter absent. |
| Conversion focused proof | PASS: 7 tests / 65 assertions; combined kernel/attachment/backfill proof 32 / 240. |
| First production-shape test | Test-fixture setup FAIL: stale collection produced 105 rather than 108 matches; fixture reloaded, with no production-code workaround. |
| Command tests before implementation | Expected RED: command absent. |
| Initial command/production proof | PASS: 5 tests / 47 assertions. |
| Pre-compaction combined verification | Output truncated; not counted and repeated after context restoration. |
| Fresh post-compaction kernel/conversion/command/production proof | PASS before review corrections: 15 tests / 135 assertions. |
| Attachment/settings focused regressions | PASS: 27 tests / 192 assertions. |
| First touched-file Pint review | FAIL: one ordered-import issue in the new command; mechanically fixed. |
| Repeated touched-file Pint and `git diff --check` | PASS. |
| Broad affected media/settings/import-export regression matrix | PASS before and after review corrections: 190 tests / 2,031 assertions. |
| PHP inspection workflow | Connector unavailable; disclosed fallback used Pint, FilaCheck, tests, source sweeps and independent read-only review. |
| Independent read-only correctness review | Found lowercase-key normalization and stale settings-key defects; no schema, byte/filesystem or authority drift. |
| Review regressions before fixes | Expected RED: 7 passed, 2 failed for the two reported defects. |
| Review-corrected focused proof | PASS: 18 tests / 150 assertions. |
| Human command-summary coverage | PASS: 3 tests / 22 assertions. |
| Rejected-machinery/dependency/storage-operation source sweep | PASS: rejected names occur only in negative schema assertions; converter storage use is `exists()` only; no dependency diff. |
| First final requirements sweep | PASS: exact Package 1 surface, authority boundaries, rejected-machinery absence, dependency absence and no-live-action state confirmed. |
| First final `vendor/bin/pint --test` | PASS. |
| First final `vendor/bin/filacheck` | PASS with 0 issues. |
| First final `npm run build` | PASS with Vite 8.1.5; the existing optional `fontaine` warning repeated. |
| First final full `php artisan test` in the macOS sandbox | Infrastructure FAIL after 983 passing tests: Chromium `MachPortRendezvousServer ... Permission denied`; 21 later browser assertions inherited the closed browser. No application edit followed. |
| Identical full `php artisan test` outside the browser sandbox | PASS: 1,005 tests / 13,316 assertions in 438.399s. |
| Post-result-documentation final `vendor/bin/pint --test` | PASS. |
| Post-result-documentation final `vendor/bin/filacheck` | PASS with 0 issues. |
| Post-result-documentation final `npm run build` | PASS with Vite 8.1.5; the existing optional `fontaine` warning repeated. |
| Post-result-documentation full `php artisan test` last and serial | PASS outside the macOS browser sandbox: 1,005 tests / 13,316 assertions. |
| Literal result-text commit candidate | Requirements, `git diff --check`, Pint, FilaCheck and Vite were repeated after replacing the pending result labels. A third identical full-suite run was intentionally not repeated because two consecutive outside-sandbox runs already passed 1,005 / 13,316 and the final edit recorded only those verified outcomes. |

All automated tests used the test environment and fake storage. The local
development and production databases/storage/cache were not probed.

## Drift checklist

- Every Curator row remains represented; Package 2 still owns the All Media
  query and Package 5 owns Files Discovery.
- Reference-key shape is used only for conversion/portable identity, never as
  gallery or public-display authorization.
- Folder, filename, metadata, size and dimensions do not hide or block existing
  rows in Package 1.
- No byte normalization, checksum, manifest, digest or quarantine returned.
- `media_attachments.media_id` remains the local owner authority; the new asset
  column is an optional portable bridge.
- Owner/settings paths remain compatibility mirrors and never veto an existing
  numeric attachment.
- D01, Needs Repair and picker All Media are unchanged and explicitly deferred
  to Package 2; their controlling requirements remain narrow and active.
- Validation remains at existing new-input/delivery/mutation boundaries.
- No dedicated security audit/review phase was added.
- No live local or production action was assumed or run.

## Assumptions and deferred work

- The dated 15-row local and 403-row production facts are fixture inputs, not a
  claim of a fresh environment check.
- The conversion command is implemented but intentionally not run outside the
  test environment.
- Package 2 requires a fresh Simplifier audit against this completed baseline.
- All gallery, Needs Repair, D01, picker All Media, acquisition, Files
  Discovery, lifecycle, deployment and production actions remain deferred.

## Local Front Check Report

These are numbered manual operator steps for a disposable test copy, not claims
that a manual or live conversion ran:

1. Run `php artisan media-assets:convert-curator --json` in a disposable test
   copy; expect `mode: report`, complete counts and no new asset or binding rows.
2. Run `php artisan media-assets:convert-curator` in that copy; expect a human
   report-only table and the explicit no-database-change notice.
3. Back up and seed a disposable copy, then run
   `php artisan media-assets:convert-curator --apply --json`; expect one asset
   and one Curator binding per Curator row without any managed-file change.
4. Repeat the apply command in that disposable copy; expect zero newly created
   assets, bindings or attachments and unchanged owner/settings paths except
   documented stale-mirror repairs.
5. Open the existing admin Media area in that disposable copy; expect its
   pre-Package-2 UI to load unchanged. Do not treat this as All Media/Needs
   Repair/picker acceptance, which remains separately gated.

Do not run these steps against local development or production without a new
exact environment-action approval and the required backup/runbook controls.

## No-environment-mutation statement

No production or local-development database, storage, cache, migration,
conversion, repair, sanitation, deployment, process, dependency, branch,
worktree or push action occurred.

## Commit hash

Pending
