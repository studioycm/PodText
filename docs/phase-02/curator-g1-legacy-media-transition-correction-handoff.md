# CURATOR-G1 Legacy Media Transition Correction Handoff

Date: 2026-07-21

## Approved contract

- Audit ID:
  `LS-20260721-CURATOR-G1-LEGACY-MEDIA-TRANSITION-CORRECTION-03`
- Approved option:
  `CURATOR-G1-LMTC-O1-IN-PLACE-JOURNALED-TRANSITION-DEFAULT-FALLBACK`
- Execution: five sequential work packages in the existing `main` checkout.
- Dependencies/schema: no additions or upgrades.

## Outcome

The strict app-owned Curator surface now has a supported transition for the
previously stranded shape: an existing valid null-key Curator raster whose
bytes require canonical re-encoding and whose legacy path is still actively
owned. The transition keeps the numeric Curator ID, proves normalized bytes,
issues the immutable key inside the fenced commit, switches reviewed
owner/settings/attachment identity, and leaves journaled retry/cleanup state.

The same executor supports reusable staged SVG sanitation and one exact
fixed-root rowless import. It has no real-ID branch and did not operate on
Media IDs 6 or 7. Null `reference_key` is never treated as proof: a row becomes
`key_only`, `normalize_existing`, `sanitize_svg`, `detach_to_default`, or
`blocked` only from its current bytes, metadata, root/purpose, references, and
journal state.

ContentGroup and ContentItem lists, relation tables, edit surfaces, and image
actions now mount safely when the current owner identity is disallowed. They
show a localized warning and let an Admin choose a trusted replacement or
explicitly detach to the configured default. The unsafe row/file remains
unavailable and unchanged. Public resolution treats only the typed unsafe
condition as absent and continues through the configured family/global/system
fallback chain; unrelated exceptions still propagate.

## Exact baseline and drift

Stage 2 preflight observed:

- cwd and Git root: `/Users/studioycm/Herd/PodText`;
- branch: `main`;
- starting HEAD:
  `d1732f8269fe9b647e435a0f36785380f92f4496`;
- `origin/main`:
  `7c55dca4012ce48779b32b2e3c4d2076d9198807`;
- local main two commits ahead;
- clean worktree with no overlapping Curator/media/content/settings work;
- preserved G1 commits `fa5b57c` and `d1732f8`; and
- no active prompt under `prompts/pre-13-prompts/`.

Installed source remained authoritative: Laravel 13.19.0, Filament 5.6.7,
Livewire 4.3.3, Pest 4.7.4, and `awcodes/filament-curator` 5.1.2 at source
reference `2a79bf031099d2d75351377eae15322fb590ab43`.

No material dependency, schema, security-boundary, task-count, effort, or
production-action drift occurred. Documentation drift was corrected:

- the current stack line now says Laravel 13.19.0 while historical snapshots
  remain historical;
- only the three relational G1 migrations are called data-free; the Spatie
  settings migration rewrites three payload shapes; and
- production migration uses the exact four-file `--path` allowlist with
  `--step --force`, never broad `migrate --force`.

Laravel Boost supplied installed-version guidance. FilamentExamples research
returned search-only Filament v4 snippets and exposed no source/detail reader;
those snippets were treated as neighboring ideas, not Filament 5 proof.

## Implemented transition contract

`media:preflight-legacy-transition` emits a stable human table or canonical
JSON manifest. Its digest excludes the observation timestamp and includes
decision-bearing schema capabilities, G1 migration file hashes, row/file
proofs, owner/attachment/settings fingerprints, journal state, disposition,
and next action. Partial Curator schema and an unclassified active reference
fail the closure gate.

Apply is one exact selector only:

```text
php artisan media:transition-legacy <media-id> --actor=<admin-id> --digest=<digest> --apply
php artisan media:transition-legacy --path=<exact-path> --actor=<admin-id> --digest=<digest> --apply
```

For an existing raster/SVG row, O1 performs:

1. exact Admin authorization, digest, row, source, owner, settings,
   attachment, and open-journal review;
2. a durable operation/fence and checksum-verified private original copy;
3. private normalization/sanitation and validation;
4. generated fixed-root public destination copy plus independent SHA-256
   verification;
5. a short locked replan/commit preserving numeric ID, issuing the first
   immutable key, updating coherent metadata, and switching reviewed
   owner/attachment/settings identity;
6. committed-state marking before cleanup; and
7. settings/Curator/Glide/application cache invalidation, zero-reference old
   public-source cleanup, staging cleanup, retained quarantine, and journal
   completion.

A pre-commit failure leaves the old database identity and public source active
and marks checksum-owned artifacts for compensation. A post-commit cleanup
failure returns `cleanup_pending`; exact-operation repair verifies the
committed destination and resumes cleanup. Conflicting destinations, missing
or changed source, changed references/settings, duplicate identities, stale
digest/fingerprint, foreign/open mutation, malicious/spoofed SVG, symlink, and
wrong root fail closed.

Rowless import requires an active app-owned owner/settings reference, exact
normalized fixed-root path, canonical local public source, no Curator row, and
the same actor/digest/checksum boundary. Completed retry checks original digest,
actor, journal/row/destination identity, file existence, and checksum before
returning `already_registered`.

## Owner repair/default behavior

`LegacyOwnerMediaDiagnostics` is the single database-metadata-only diagnostic
and fingerprint source. It classifies disallowed legacy path, disallowed
attachment, duplicate path rows, attachment/path mismatch, and missing
attachment media without carrying a path, URL, or bytes to the UI.

`LegacyOwnerMediaRepairer`:

- validates the exact owner/role pair and Admin boundary;
- locks the owner, attachment, every raw path row, distinct attachment evidence,
  and selected target;
- authorizes every evidence row through normal detach or the narrow
  `repairLegacyOwner` ability;
- rejects unfinished evidence/target journals and stale/forged fingerprint;
- allows only a current keyed, purpose-compatible strict-scope target;
- atomically replaces or detaches path/attachment identity; and
- writes one completed `legacy_owner_repair` database-only journal with sorted
  evidence IDs.

It never deletes or changes old rows/files. An unrelated edit with no picker
selection preserves the unsafe identity and writes no repair journal. Explicit
detach turns the owner into normal no-image state so configured fallback rules
apply.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| Existing valid noncanonical raster row transition | Correction required - implemented | Same-ID journaled O1 transition with staged proof and retry. |
| Generate key when proof is sufficient | Correction required - implemented | `key_only` issues only after current bytes equal normalized SHA-256; null alone is never proof. |
| Preserve numeric identity where safe | Correction required - implemented | Existing row ID is retained; rowless path alone creates a new row. No replacement-row O2 was needed. |
| Key only after copy/validation/checksum proof | Already correct and extended | Existing validator/journal boundary retained for key-only, raster, SVG, and rowless import. |
| Owner/settings/path/attachment switch | Correction required - implemented | Locked fingerprints and atomic switch; settings remain key-first/path-fallback. |
| Durable journal, fence, failure/retry/cleanup | Already correct and extended | Existing coordinator/fence/lease reused; no parallel journal. |
| Strict gallery/browse/view/download/mutations | Already correct | Disallowed rows remain excluded; policy regressions cover all ordinary abilities. |
| Safe record list/edit/action repair UX | Correction required - implemented | Typed warning, trusted replacement, confirmed detach/default, HE/EN, real Filament/Livewire actions. |
| Default on corrupt/unsafe owner identity | Correction required - implemented | Typed unsafe resolution becomes absent; no read-time write or unsafe URL. |
| Reusable SVG sanitation | Correction required - implemented locally | Same journaled executor and isolated safe/malicious fixtures; no ID-specific branch. |
| Actual Media IDs 6/7 sanitation | Production-only | Not run; separate exact one-ID approvals/runbook required. |
| Root-level IDs 12-15 | Unrelated/deferred | Remain explicit fixed-root blocks; no policy weakening or automatic disposition. |
| Rowless exact fixed-root recovery | Correction required - implemented | One exact referenced path only; no scan/gallery exposure. |
| Full recovery gallery/file-manager plugin | Unrelated/deferred | Outside correction. |
| Ordered/idempotent backfills | Correction required - implemented | Transition/key first, attachment second, settings last, with fresh digest after state changes. |
| Integrity classification | Correction required - implemented | Separates transition pending, detach-to-default, blocked, rowless, attachment/settings, and incomplete journal state. |
| Production-shaped regression fixture | Correction required - implemented | 300 group owners, default settings, two SVGs, rowless and root-level shapes. |
| Broad migration command defect | Correction required - implemented in docs | Exact four paths, `--step`, dormant permission migration excluded. |
| Empty permission tables | Unrelated/deferred | Safer left dormant than broad rollback; targeted future cleanup outside correction. |
| Dependency upgrade | Unrelated/deferred | No Composer/npm changes. |
| New schema | Not applicable | Existing nullable key, attachment, and journal schema is sufficient. |
| Production/local-development mutation | Production-only and not authorized | None executed. |
| Candidate without unique/proven/executable state | Blocked | Remains reported with an explicit reason; no guessing. |

## Files changed

### New application files

- `app/Console/Commands/PreflightLegacyMediaTransition.php`
- `app/Console/Commands/TransitionLegacyMedia.php`
- `app/Enums/LegacyMediaTransitionDisposition.php`
- `app/Enums/LegacyOwnerMediaDiagnosticCode.php`
- `app/Support/Media/LegacyMediaTransitionExecutor.php`
- `app/Support/Media/LegacyMediaTransitionManifest.php`
- `app/Support/Media/LegacyMediaTransitionPlanner.php`
- `app/Support/Media/LegacyOwnerMediaDiagnostic.php`
- `app/Support/Media/LegacyOwnerMediaDiagnosticProjector.php`
- `app/Support/Media/LegacyOwnerMediaDiagnostics.php`
- `app/Support/Media/LegacyOwnerMediaRepairer.php`
- `app/Support/Media/UnsafeLegacyOwnerMediaException.php`

### Reconciled application files

- `app/Console/Commands/BackfillMediaAttachments.php`
- `app/Console/Commands/BackfillMediaReferenceKeys.php`
- `app/Console/Commands/BackfillSettingsMediaReferenceKeys.php`
- `app/Console/Commands/RegisterExistingCuratorMedia.php`
- `app/Console/Commands/ReportMediaIntegrity.php`
- `app/Enums/MediaMutationOperationType.php`
- `app/Models/Media.php`
- `app/Models/ContentGroup.php`
- `app/Models/ContentItem.php`
- `app/Policies/CuratorMediaPolicy.php`
- `app/Support/Media/LegacyMediaReferenceSwitcher.php`
- `app/Support/Media/LegacyMediaRegistrationPlan.php`
- `app/Support/Media/LegacyMediaRegistrationPlanner.php`
- `app/Support/Media/MediaFilesystemMutationCoordinator.php`
- `app/Support/Media/MediaIntegrityReporter.php`
- `app/Support/Media/MediaMutationFence.php`
- `app/Support/Media/MediaMutationLease.php`
- `app/Support/Media/MediaIdentityResolver.php`
- `app/Support/Media/MediaAttachmentIdentityResolver.php`
- `app/Support/Media/MediaAttachmentFormState.php`
- `app/Support/PublicFront/PublicDefaultImageResolver.php`

### Filament, localization, and UX files

- `app/Filament/Actions/ContentImageActions.php`
- `app/Filament/Resources/ContentGroups/Pages/EditContentGroup.php`
- `app/Filament/Resources/ContentGroups/RelationManagers/ContentItemsRelationManager.php`
- `app/Filament/Resources/ContentGroups/Tables/ContentGroupsTable.php`
- `app/Filament/Resources/ContentItems/Pages/EditEpisodeWorkspace.php`
- `app/Filament/Resources/ContentItems/Tables/ContentItemsTable.php`
- `lang/en/admin.php`
- `lang/he/admin.php`

### Tests

- new `tests/Feature/LegacyMediaTransitionTest.php`
- new `tests/Feature/LegacyOwnerMediaRepairTest.php`
- updated `tests/Feature/LegacyMediaRegistrationTest.php`
- updated `tests/Feature/MediaBackfillAndIntegrityReportTest.php`
- updated `tests/Feature/MediaRelationshipPerformanceTest.php`

### Durable documentation

- new `docs/research/curator-g1/legacy-transition-correction-research.md`
- new `docs/research/curator-g1/legacy-transition-correction-implementation-plan.md`
- this handoff
- `docs/phase-02/ai-development-lessons.md`
- `docs/phase-02/curator-g1-existing-svg-runbook.md`
- `docs/phase-02/curator-g1-image-library-o2-handoff.md`
- `docs/phase-02/curator-g1-image-library-production-cutover-runbook.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/research/curator-g1/image-library-o2-research.md`
- `docs/research/curator-g1/image-library-o2-implementation-plan.md`

No migration, configuration, dependency lock, package, or frontend source file
changed.

## Test coverage added or extended

- stable canonical manifest/digest, partial schema, all six dispositions, and
  production-shaped closure;
- exact-byte key-only, noncanonical same-ID raster, reusable SVG, rowless
  raster/SVG, one-ID enforcement, actor/digest/source/reference drift;
- duplicate/missing/malicious/spoofed/SVG/symlink/wrong-root refusal;
- quarantine/staging/destination compensation, cleanup-pending, completed
  retry, lease/fence, and exact-operation repair behavior;
- attachment/settings order, stale locks/fingerprints, idempotence, integrity
  labels, and key/path identity;
- safe ContentGroup/ContentItem edit/list/relation/action mounts;
- real edit and table replacement/detach, ordinary-save preservation,
  forged fingerprint/role/target, unfinished evidence/target, and distinct
  attachment/path mismatch;
- public configured fallback without unsafe URL and unrelated exception
  propagation;
- strict ordinary gallery ability denial and Admin-only repair;
- English/Hebrew repair text; and
- 1/10/50 diagnostics with exact eager loads, zero diagnostic-loop SQL, and a
  failing test if the loop touches `Storage`.

## Commands and results

Read-only orientation/preflight included cwd/root/status/HEAD/origin/log checks,
full mandatory docs and skill reads, prompt search, installed Composer/package
source/version inspection, targeted `rg`/`sed` source/test/history inspection,
and repeated `git diff --check`/`git status --short`. No secret-bearing file or
header was opened or printed.

| Command / check | Result |
|---|---|
| Package 1 `git diff --check` and source reconciliation | PASS |
| Package 2 transition tests | PASS: 20 tests / 92 assertions |
| Package 2 focused matrix | PASS: 47 tests / 354 assertions |
| Package 2 executor PHP lint and `git diff --check` | PASS |
| Package 3 interim focused matrix | PASS: 50 tests / 368 assertions |
| Package 3 final registration/transition/backfill/mutation matrix | PASS: 55 tests / 396 assertions |
| Package 4 focused owner-repair file | PASS: 17 tests / 103 assertions |
| Package 4 implementation matrix | PASS: 93 tests / 956 assertions |
| Root Package 4 independent matrix rerun | PASS: 93 tests / 956 assertions |
| Package 4 PHP lint and repeated `git diff --check` | PASS |
| Iteration `vendor/bin/pint --dirty` | PASS; formatted changed PHP only |
| Requirements classification sweep | PASS: every amended-audit requirement appears in the matrix above |
| First final-sequence `vendor/bin/pint --test` | PASS |
| First final-sequence `vendor/bin/filacheck` | FAIL: the new visible warning raised the main ContentItems table from 10 to 11 default-visible columns |
| FilaCheck remediation | Kept the repair warning visible and made the lower-priority duration column toggleable but hidden by default; restarted at Pint |
| `vendor/bin/pint --test` | PASS on final code state |
| `vendor/bin/filacheck` | PASS on final code state |
| `npm run build` | PASS on final code state |
| First `php artisan test` | FAIL: 978/1,001 passed, 11,740 assertions; one established path/attachment mismatch message was replaced by the generic typed message, then Chromium hit the known macOS `MachPortRendezvousServer ... Permission denied` bootstrap failure and 22 browser tests failed/cascaded |
| Full-suite remediation | Restored specific messages while retaining the typed exception; reran the focused import/export regression; retried the identical browser/full gate rather than changing browser-tested application behavior |
| Two attempted `ImportExportTest` name filters | No tests found because the wrapper did not match the descriptive Pest name; no application/test state changed |
| `php artisan test tests/Feature/ImportExportTest.php` | PASS: 19 tests / 89 assertions, including the restored mismatch contract |
| Next restarted `vendor/bin/pint --test` | FAIL: constructor brace placement in `UnsafeLegacyOwnerMediaException`; corrected mechanically and restarted from Pint |
| Second `php artisan test` | FAIL: all 979 non-browser tests passed with 11,742 assertions; Chromium again failed macOS `MachPortRendezvousServer` bootstrap permission and the same 22 browser tests failed/cascaded |
| Browser infrastructure response | Made no browser-tested application change; restarted the exact gate and requested an unsandboxed run because the fatal error is the host sandbox permission boundary |
| `php artisan test` | PASS last, serial, and uninterrupted outside the macOS browser sandbox: 1,001 tests / 13,635 assertions |
| final `git diff --check` | PASS |

Focused TDD runs used the isolated test database and Laravel storage fakes.
No test used the local development database/storage or live production data.
No full-suite run was parallelized or interrupted.

## Production and deployment implications

The production runbook now requires:

1. new-release schema-independent preflight and evidence capture;
2. the exact four G1 migration files with `--step --force`;
3. fresh post-schema manifest/digest review;
4. one exact ID or path per approved transition, with a fresh digest after
   every decision-bearing change;
5. exact-operation repair for cleanup-pending;
6. owner repair/default disposition where transition is unsafe;
7. attachment backfill before settings-key backfill, with re-preflight between;
8. zero unexplained active reference and no incomplete journal before traffic;
   and
9. a separate one-ID-at-a-time SVG approval for real IDs 6 and 7.

The verified 300-image production shape is expected to classify each row, not
make the gallery empty without a path forward. Valid canonical rows use
key-only; valid noncanonical rows use same-ID normalization; valid SVGs use the
separately approved sanitizer; unusable owner identities expose repair/default;
ambiguous/root-invalid state remains explicit block. No bulk apply was added.

## Assumptions and deferred work

- The known 2026-07-21 database/filesystem evidence was accepted as read-only
  incident evidence and was not reproduced against local development data.
- Real environment state must be freshly inventoried before production action;
  test fixtures are proof of behavior, not production authorization.
- IDs 6/7 sanitation, root-level IDs 12-15 disposition, full filesystem
  recovery gallery/file-manager plugin, dependency upgrades, permission-table
  cleanup, path retirement/non-null proof, curation dimensions, exports,
  imports, deployment, and cache/process action remain deferred/separately
  approved.
- Empty dormant permission tables do not activate Shield and are safer left
  than removed by a broad rollback.

## Local Front Check Report

These are manual operator steps, not claims that a browser check ran:

1. Seed an isolated local/test record with a valid noncanonical null-key
   ContentGroup cover and open the ContentGroups list; expect a warning badge,
   the normal row, and no unsafe thumbnail URL or HTTP 500.
2. Open that ContentGroup edit page; expect the form to mount with an empty
   trusted-media selection and the localized repair helper.
3. Save an unrelated title change without selecting media; expect the legacy
   cover path to remain unchanged and no owner-repair journal to appear.
4. Reopen the cover action, choose one allowed keyed cover, and save; expect the
   owner path/attachment to switch, one completed owner-repair journal, and the
   old row/file to remain untouched and absent from Media.
5. Repeat with a fresh unsafe ContentGroup and choose the confirmed remove/use
   default action; expect the owner path/attachment to clear and the configured
   group/global/system default to render.
6. Open the ContentItems list and a ContentGroup's ContentItems relation table
   with an unsafe primary image; expect the warning and both pages to mount
   without extra per-row file inspection.
7. Open the episode workspace, replace the unsafe image with an allowed keyed
   primary image, and save; expect the same locked/journaled result as the group
   flow.
8. Confirm a moderator or lower role cannot apply the repair action and cannot
   call any normal operation on the unsafe Media row.
9. Open Media search, picker, direct view/download, rename, swap, and delete
   surfaces; expect the disallowed row to remain absent or denied in every
   case.
10. Run the transition preflight in an isolated fixture, retain its digest,
    change one source/reference, and try apply; expect a stale-plan failure and
    no key/path/reference write.
11. Run one exact isolated same-ID raster transition; expect the same numeric
    ID, generated fixed-root path, immutable key, switched references,
    checksum journal proof, retained quarantine, and completed cleanup.
12. Run the safe SVG fixture through the same command and run the malicious SVG
    fixture separately; expect the safe fixture to complete and the malicious
    fixture to remain unkeyed/blocked without gallery exposure.
13. Verify Hebrew RTL and English LTR labels for the warning, replacement
    helper, detach action, success notification, and mutation operation names.
14. Rerun integrity and all three backfill dry runs in the isolated fixture;
    expect explicit transition/default/block counts and no ambiguous ordinary
    quarantine label for an actively referenced legacy row.

## No-mutation statement

This Stage 2 run made no local development or production database, storage,
cache, migration, backfill, repair, sanitizer-apply, deployment, process,
export/import, dependency, branch/worktree, push, or real Media IDs 6/7 change.
Application writes occurred only inside isolated Pest databases and storage
fakes. Existing G1 commits were preserved.

## Commit hash

`f73be008b3dbc49e09f01b645327b4083d8f70f8`
