# Package 1 Plan — Minimal Kernel and Database Conversion

## Approved outcome

Create the smallest provider-neutral identity kernel, bridge current numeric
attachments, and provide an idempotent database-only Curator conversion. Stop
after Package 1.

## Job 0 — documentation reset and attributable cleanup

1. Rewrite every canonical media program document to the inventory-first rule.
2. Record the untracked old Package 1 handoff invalid/incomplete.
3. Recheck the drift checklist and Git diff.
4. Restore only attributable tracked hunks outside the rebased documents.
5. Delete only attributable untracked draft files.
6. Verify the remaining diff contains the approved documentation reset only.

No reset, checkout, stash, clean, broad deletion or whole-tree reversal.

## Job 1 — minimal schema and relationships

### RED

Write `tests/Feature/MediaAssetKernelTest.php` first. Prove:

- exact assets/bindings columns, indexes and restrictive foreign keys;
- attachment bridge retains `media_id` and existing owner-role uniqueness;
- asset key generation and immutability;
- binding uniqueness in both directions;
- Media, asset, binding and attachment relationships;
- no rejected kernel columns/tables/enums.

Run the test and confirm it fails because the new schema/classes are absent.

### GREEN

Generate two migrations, then implement only `MediaAsset`,
`MediaProviderBinding` and focused relationship changes to `Media` and
`MediaAttachment`. Add factories only where tests require them. Do not create a
provider interface, policy, status enum, folder, storage service or asset scope.

Run the focused kernel/model tests, Pint on touched PHP and `git diff --check`.

## Job 2 — conversion core

### RED

Write focused tests for:

- null/malformed/duplicate key replacement;
- valid unique key reuse;
- one asset/binding per row and exact Curator numeric ID preservation;
- idempotent rerun and incomplete-bridge reconciliation;
- existing attachment authority and stale mirror repair;
- unique-path owner attachment and duplicate-path refusal;
- unique-path settings key fill and ambiguous preservation;
- transaction rollback;
- no file writes, moves, copies, deletion, hashing or byte reads.

Confirm each test fails for the missing behavior before implementation.

### GREEN

Implement one focused `CuratorMediaAssetConverter` and a small immutable
`CuratorMediaAssetConversionReport`. Keep matching explicit and use Eloquent
relationships plus one database transaction. Generate ULIDs with Laravel's
`Str::ulid()`.

Do not call the current validator, normalizer, mutation coordinator, journal,
lease, sanitation or registration commands.

## Job 3 — command and production-shaped proof

### RED

Write command tests for report-only versus `--apply`, human/JSON summaries,
15-null, 15-valid and 403-row fixtures, missing-file diagnostics and no real
environment interaction.

### GREEN

Implement `ConvertCuratorMediaAssets` as a thin console adapter over the
converter. It accepts `--apply` and `--json` only. Report-only mode never
mutates. Apply returns non-zero on failure and prints exact counts.

The command is implemented and tested but not executed against the local
development or production databases.

## Job 4 — reconciliation and closeout

1. Recheck context, registry, audit, this plan and current diff.
2. Answer every drift-check question in the handoff.
3. Run affected relationship/settings/import-export regressions.
4. Perform a simplification review: remove any rejected status/storage/proof or
   speculative provider abstraction.
5. Run requirements sweep, Pint, FilaCheck, Vite build, then full tests last.
6. Create `media-program-p1-kernel-conversion-handoff.md` with pending hash.
7. Commit locally as `feat: add minimal media asset conversion`.
8. Immediately stamp its hash in the handoff, ledger and current state and
   commit `docs: backfill media asset package 1 hash`.

Do not push or start Package 2.

## Acceptance

- Every Curator row converts to one asset and one binding.
- `media_id` remains local owner authority.
- Paths/metadata/IDs are preserved.
- Valid keys are reused; invalid/missing/duplicate keys are repaired.
- Owner/settings relationships are preserved or explicitly unresolved.
- Missing files remain represented and reported.
- No filesystem write or byte-processing path is invoked.
- No rejected kernel fields/classes remain.
- Package 1 docs, tests, gates and local commits are complete.

## Checkpoint log

| Checkpoint | Inventory/key/path/byte-rule result | Authority/D01/repair/picker result | Audit/live result |
|---|---|---|---|
| Stage 2 start | Committed gallery/files inventory is incomplete; committed scope hides rows by key/root/name/metadata/size/dimensions; draft restores normalization/checksum/manifest/quarantine. | Committed `media_id` exists but path disagreement vetoes; D01 and Needs Repair are too broad; picker has no All Media clear. | Stale docs contain dedicated review language; no such audit or live action was run. |
| Canonical docs reset | Active docs now require every row/file to remain findable and reject key/path/byte rules as visibility gates. Existing application behavior remains a known Package 2 defect; unfinished draft still awaits cleanup. | Active docs preserve `media_id` authority, mirror-only legacy paths, narrow D01, visible Needs Repair and picker All Media. | Dedicated security-audit route removed; no live action assumed or run. |
| Draft cleanup complete | All rejected draft code/tests/migrations are removed; only 19 approved canonical docs remain modified. Committed inventory exclusions remain unchanged and explicitly deferred to Package 2. | Committed `media_id` authority is restored without asset/trust override; committed path/D01/repair/picker defects remain documented future work. | No dedicated audit or live action added; exact cleanup corrected 54 collapsed entries to 55 files. |
| Job 1 GREEN | Minimal assets/bindings schema and nullable attachment bridge contain no trust/storage/folder/lifecycle fields. | `media_id` remains required and authoritative; portable asset relation is additive. | 14 focused tests / 77 assertions and touched-file Pint passed; no live action. |
| Job 2 GREEN | Every test Curator row receives one asset/binding; valid unique keys are reused; invalid/missing/duplicate keys are replaced; only existence checks touch storage. | Existing numeric attachments win, stale mirrors repair, unique legacy paths bridge and ambiguous paths remain reported; new attachments dual-write the optional asset bridge. | 32 tests / 240 assertions, touched-file Pint and diff check passed; no live action. |
| Job 3 GREEN | Report-only inspection and explicit apply conversion preserve every represented row, including the 15-null, 15-valid and production-shaped 403-row fixtures; no file bytes or mutations are used. | The 403-row proof preserves 108 owner attachments and compatibility settings while missing files and duplicate paths remain represented and reported. | 18 kernel/conversion/command/production-shape tests / 150 assertions plus 190 affected regressions / 2,031 assertions passed; touched-file Pint and diff check passed; no live action. |
| Read-only review corrections GREEN | Valid lowercase ULIDs are reused byte-for-byte; obsolete settings keys repair only when the path resolves uniquely; already-resolving portable keys remain authoritative over compatibility paths. | No key rule became a visibility rule, no legacy path became an attachment veto and no byte/filesystem machinery returned. | Two focused review regressions failed for the defects, then 18 tests / 150 assertions and the 190-test affected matrix passed; no live action. |
| Pre-gate drift checkpoint | Package 1 preserves every Curator row through conversion and adds no gallery exclusion; managed rowless files remain explicitly deferred to Files Discovery. No folder/name/metadata/size/dimension rule or rejected byte proof entered production code. | `media_id` remains owner authority; compatibility paths repair/report without veto; narrow D01, visible Needs Repair and picker All Media remain controlling Package 2 requirements. | Exact Audit/Option and Package 1 boundary reconfirmed; no dedicated security phase, dependency change or live action occurred. |
| First final gates | Requirements, Pint, FilaCheck and Vite passed; identical full-suite retry outside the known macOS browser sandbox passed 1,005 tests / 13,316 assertions. | No gate required an application change or altered the authority/deferred UI boundaries. | Initial sandbox browser bootstrap failed on `MachPortRendezvousServer ... Permission denied`; no application edit followed and the permitted identical retry passed. |
| Exact-state final gates | Requirements, Pint, FilaCheck and Vite passed again; the full suite passed outside the known macOS browser sandbox with 1,005 tests / 13,316 assertions. | Final gate proof preserves `media_id` authority and all Package 2 deferrals. | No application, dependency or live-environment correction was required. |
