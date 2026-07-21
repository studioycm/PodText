# Curator G1 Legacy Media Transition Correction Implementation Plan

## Approved contract

- Audit: `LS-20260721-CURATOR-G1-LEGACY-MEDIA-TRANSITION-CORRECTION-03`
- Option: `CURATOR-G1-LMTC-O1-IN-PLACE-JOURNALED-TRANSITION-DEFAULT-FALLBACK`
- Execution: five sequential work packages in the existing `main` checkout;
  repository writers remain sequential.
- Forecast: 60-90 engineering hours.
- Schema/dependencies: none.

This plan implements the source-reconciled design in
`legacy-transition-correction-research.md`. A material dependency, schema,
security boundary, task-count, effort or production-action change stops work
and returns to amended Stage 1.

## Global execution rules

- Write failing focused Pest coverage before production code and confirm each
  failure is for the missing behavior.
- Use isolated SQLite `:memory:` and `Storage::fake()` only. Never touch the
  local development database/storage or run migrations/backfills/repairs/
  sanitizers against it.
- Do not execute production actions or sanitize real Media IDs 6/7.
- Keep normal gallery/picker/file access strict. Recovery commands and owner
  diagnostics are separate trusted seams.
- Use exact actor authorization, server-owned paths, manifest digest and
  mutation fence checks. Never trust Livewire IDs or client paths.
- Every user-facing string has English and Hebrew translations.
- Run focused tests serially. Run the final full suite once green, last and
  uninterrupted.
- No dependency changes, branch/worktree creation or push.

## Work package 1 - Durable research and factual correction

### Deliverables

- create `legacy-transition-correction-research.md`;
- create this implementation plan;
- correct the current Laravel version without rewriting historical snapshots;
- distinguish the three data-free relational migrations from the settings
  migration that rewrites three stored payload shapes;
- replace broad production migration instructions with the four-file exact
  allowlist, `--step` and explicit permission-migration exclusion;
- add the combined-transition/closure-gate lesson.

### Verification

- `git diff --check`;
- `git status --short`;
- review every assertion against installed source and the approved audit.

## Work package 2 - Preflight and transition engine

### Tests first

Add focused Pest coverage for:

- schema capability before/after individual G1 migrations;
- stable manifest ordering and digest regardless of query/insertion order;
- `key_only`, `normalize_existing`, `sanitize_svg`, `import_exact_path`,
  `detach_to_default` and `blocked` classifications;
- non-zero Transition Closure Gate for active unhandled references;
- the verified production-shaped row/bytes/owner/settings fixture;
- apply digest mismatch, stale owner/settings state and forged actor/path;
- same-ID O1 success and idempotent rerun;
- failures before quarantine, after quarantine, after normalized stage, after
  destination copy, before/after DB commit, during cache cleanup and during old
  source cleanup;
- missing source, changed bytes, duplicate/conflicting destination, incomplete
  operation, foreign/stale fence and cleanup resume.

### Production design

Add narrow typed value objects/enums for manifest entries, dispositions and
owner diagnostics. Add a preflight planner/command that reads schema
capability without requiring full G1 schema, builds the canonical manifest,
prints JSON/human output and refuses apply when the digest changed.

Extend the existing `MediaMutationOperationType`, journal factory/model,
`MediaMutationFence`, `MediaMutationLease`, `StoredMediaValidator`,
`MediaReferenceFinder`, `LegacyMediaReferenceSwitcher`,
`MediaCacheInvalidator` and `MediaFilesystemMutationCoordinator` rather than
creating a parallel journal or broad media service.

The `key_only` executor records complete checksum proof before issuing a key.
The `normalize_existing` executor performs quarantine -> normalize/validate ->
generated destination -> independent checksum proof -> short locked same-ID
row/reference switch -> database-commit mark -> cache/old-source cleanup. It
must preserve the numeric Curator ID and portable key semantics.

### Candidate files

- `app/Enums/MediaMutationOperationType.php` and new focused transition enums;
- `app/Support/Media/StoredMediaValidator.php`;
- `app/Support/Media/MediaIntegrityReporter.php`;
- `app/Support/Media/MediaReferenceFinder.php`;
- `app/Support/Media/LegacyMediaReferenceSwitcher.php`;
- `app/Support/Media/MediaMutationFence.php`;
- `app/Support/Media/MediaFilesystemMutationCoordinator.php`;
- new focused preflight/manifest/planner/executor classes under
  `app/Support/Media/`;
- one dry-run-first console command under `app/Console/Commands/`;
- factories only as required for isolated tests;
- focused media transition/integrity/mutation Pest files.

No migration is planned.

## Work package 3 - Reusable sanitation, exact-path import and backfills

### Tests first

- safe and malicious SVG fixtures through the same staged executor;
- SVG output checksum, changed-plan and retry behavior;
- exact allowed-root raster/SVG import with no existing row;
- traversal, symlink, wrong-root, duplicate-row and duplicate-byte refusal;
- no ordinary browse/preview of a candidate before trust is established;
- ordered, repeatable reference-key, attachment and settings backfills;
- integrity output distinguishing actively referenced transition candidates,
  detach candidates, ordinary quarantine candidates and explicit blocks;
- root-level IDs 12-15 shape remains blocked;
- real IDs 6/7 are never selected or mutated by tests/commands.

### Production design

Extract/reuse `SvgUploadSanitizer` through the same journaled transition
executor. A command may plan one exact path and require actor plus expected
digest for apply. No bulk scan/apply and no ID-specific branch is allowed.

Reconcile `LegacyMediaRegistrationPlanner` and
`RegisterExistingCuratorMedia` so an existing valid noncanonical row maps to
the O1 transition instead of `existing_media`, while a truly rowless exact
fixed-root path maps to `import_exact_path`. Keep full file-manager/recovery
gallery work deferred.

Reconcile `BackfillMediaReferenceKeys`, `BackfillMediaAttachments`,
`BackfillSettingsMediaReferenceKeys`, `MediaIntegrityReporter` and report
commands around the exhaustive dispositions and required order. Dry runs must
remain side-effect free; apply must be exact-digest, idempotent and journaled.

### Candidate files

- `app/Support/Media/SvgUploadSanitizer.php`;
- transition executor/planner classes from package 2;
- `app/Support/Media/LegacyMediaRegistrationPlanner.php` and plan value;
- `app/Console/Commands/RegisterExistingCuratorMedia.php`;
- the three media backfill commands;
- `app/Support/Media/MediaIntegrityReporter.php`;
- report/repair commands only where required;
- `LegacyMediaRegistrationTest`, `MediaBackfillAndIntegrityReportTest`,
  transition/mutation tests and committed safe/malicious fixtures.

## Work package 4 - Owner-safe repair UX and default fallback

### Filament/Livewire research boundary

Use installed Filament 5 and Livewire 4 source/Boost guidance. The
FilamentExamples server returned search-only Filament v4 snippets and exposed
no source/detail reader; do not copy version-specific APIs from it.

### Tests first

- ContentGroup and ContentItem list/edit/action mount with a disallowed current
  legacy row and no HTTP 500;
- typed diagnostic catches only the expected condition;
- ordinary replacement selects only an allowed trusted Media row;
- detach clears the reviewed owner identity and lets the configured default
  resolve, without deleting or exposing the disallowed row/file;
- unauthorized and forged record/media/path/detach requests fail;
- Hebrew/English warning, help and action labels;
- strict gallery/picker/view/download remains inaccessible for the row;
- query counts remain bounded for 1, 10 and 50 listed owners;
- list diagnostics use projected database state only, not filesystem hashing,
  image decoding or per-row settings reads.

### Production design

Replace the generic legacy-path exception at the owner boundary with a typed
diagnostic carrying no untrusted bytes/URL. `MediaIdentityResolver`,
`MediaAttachmentIdentityResolver` and `MediaAttachmentFormState` continue to
fail closed for strict identity resolution, while form/action initialization
maps this one typed state to null selection plus repair metadata.

Extend `ContentImageActions` and the ContentGroup/ContentItem list/edit
surfaces with authorized replace and detach-to-default actions. Detach uses the
journal/fence/reference-switch boundary, clears compatible legacy owner path
and attachment identity, and retains the excluded Curator row/file as evidence.
Public/default resolvers treat the unusable owner reference as absent and use
the configured default chain; they never mutate on read.

### Candidate files

- a focused typed exception/diagnostic under `app/Support/Media/`;
- `MediaIdentityResolver`, `MediaAttachmentIdentityResolver`,
  `MediaAttachmentFormState`;
- `app/Filament/Actions/ContentImageActions.php`;
- ContentGroup and ContentItem Resource/list/edit/workspace files;
- public/default image resolver only where required;
- `lang/en` and `lang/he` media/admin keys;
- `ImageMediaCuratorTest`, `EpisodeWorkspaceTest`, Resource/action tests and
  `MediaRelationshipPerformanceTest`.

## Work package 5 - Reconciliation, verification and canonical closeout

### Review and documentation

- run a source/requirements classification sweep covering every audit item;
- reconcile the two durable documents with final class/command/test names;
- update the production cutover and SVG runbooks to the implemented exact
  commands, digest, ordering, retry/rollback and approval contracts;
- update `docs/phase-02/current-project-state.md`, the mini-step ledger and a
  new committed correction handoff;
- record files, tests, every command/result, gates, assumptions, deferrals,
  deployment notes and numbered imperative Local Front Check steps;
- confirm no production/local development mutations or actual IDs 6/7 action.

### Focused verification

Run focused tests during each package, serially. After the final file change,
run the required final gates in this exact order:

1. requirements classification sweep;
2. `vendor/bin/pint --test`;
3. `vendor/bin/filacheck`;
4. `npm run build`;
5. full `php artisan test` last and uninterrupted.

Any later file change restarts at Pint. Never use `filacheck --fix`.

### Canonical two commits

1. commit implementation, tests, research/plan, runbooks/state/ledger and the
   handoff whose `## Commit hash` value is pending;
2. immediately patch only documentation to stamp the implementation hash into
   the handoff and ledger, then commit with
   `docs: backfill CURATOR-G1 LMTC hash`.

Do not push. Finish with a clean worktree.

## Requirement classification forecast

| Requirement | Planned status |
|---|---|
| Same-ID valid-raster transition | Implemented in packages 2-3 |
| Immutable key only after proof | Implemented in package 2; existing invariant retained |
| Owner/settings/attachment switching | Implemented in packages 2-4 |
| Journal, compensation and retry | Extended in packages 2-3 |
| Strict gallery/media access | Already correct; regression covered |
| Safe owner repair/default fallback | Implemented in package 4 |
| Reusable SVG mechanism | Implemented in package 3 |
| Actual IDs 6/7 sanitation | Production-only and not authorized |
| Root IDs 12-15 | Explicitly blocked/deferred; root policy retained |
| File-manager/recovery gallery | Deferred outside correction |
| Exact-path fixed-root import | Implemented in package 3 |
| Backfill order/integrity categories | Implemented in package 3 |
| Production-shaped regression gate | Implemented in packages 2-3 |
| Exact migration cutover | Documentation implemented; production execution not authorized |
| Empty permission tables | Already harmless; targeted cleanup deferred outside correction |
| New schema/dependency | Not applicable |

## Implemented package reconciliation

The five-package design stayed intact and no forecast boundary returned the
work to Stage 1:

1. Package 1 created/reconciled the durable research and plan, corrected the
   Laravel version, migration semantics, closure-gate lesson, and exact
   migration allowlist.
2. Package 2 implemented
   `PreflightLegacyMediaTransition`, `TransitionLegacyMedia`,
   `LegacyMediaTransitionDisposition`, `LegacyMediaTransitionManifest`,
   `LegacyMediaTransitionPlanner`, and `LegacyMediaTransitionExecutor`, plus
   same-ID key/normalization journal, fence, lease, digest, and retry coverage.
3. Package 3 reconciled `RegisterExistingCuratorMedia`, all three backfills,
   `MediaIntegrityReporter`, exact-path import, reusable staged SVG sanitation,
   symlink/root refusal, ordered settings/attachment behavior, and the
   production-shaped fixture.
4. Package 4 implemented `LegacyOwnerMediaDiagnosticCode`,
   `LegacyOwnerMediaDiagnostic`, `LegacyOwnerMediaDiagnostics`,
   `LegacyOwnerMediaDiagnosticProjector`,
   `UnsafeLegacyOwnerMediaException`, and `LegacyOwnerMediaRepairer`; then
   wired safe owner mount/replacement/detach/default behavior through the
   existing identity/form/action/list/edit/public boundaries with HE/EN and
   1/10/50 proofs.
5. Package 5 reconciles these durable documents, both production runbooks,
   current state, ledger, and the dedicated correction handoff before the
   serial repository gates and canonical two commits.

Actual focused proof before the final gates is recorded in the correction
handoff. No migration, schema file, Composer/npm dependency, recovery gallery,
or ID-specific sanitation branch was added.
