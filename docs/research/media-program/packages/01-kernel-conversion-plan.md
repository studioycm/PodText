# MEDIA-P1 Kernel and Curator Conversion Implementation Plan

## Scope

Implement Package 1 only. Do not begin gallery/folder management UI,
MediaAssetPicker acquisition, owner hover/detail UX, or Files Discovery. The
kernel includes `media_folders` schema/system rows because assets require the
approved logical organization, but folder admin UX belongs to Package 2.

No real migration/conversion/backfill/repair/sanitation command is run. Tests
use isolated databases and fake storage.

## Job 1: schema and domain kernel

### RED tests

Create `tests/Feature/MediaAssetKernelTest.php` proving:

- migration A creates exact tables/columns/indexes/FKs and deterministically
  upserts six system rows before assets can exist;
- migration B adds nullable attachment/journal bridges without removing G1
  columns/indexes;
- both migrations reverse on an isolated schema;
- asset reference key is immutable;
- direct storage identity mutation outside lease is refused;
- trusted invariant requires canonical fields/checksum;
- Needs Repair may retain provenance but has no canonical disk/path/hash;
- checksum duplicates create separate assets;
- provider/record and asset/provider uniqueness;
- system key protection marker and flat folder relationship;
- non-null folder membership for trusted and Needs Repair assets;
- system-folder enum/migration parity, deterministic positions, duplicate-key
  retry behavior, forbidden concurrent migration expectation, and reversal;
- attachment/journal asset relationships and casts.

Run the file and confirm missing classes/tables are the reason for RED.

### Implementation

Create:

- the two named migrations from the master plan;
- models/factories `MediaAsset`, `MediaProviderBinding`, `MediaFolder`;
- enums `MediaAssetValidationStatus`, `MediaAssetLifecycleStatus`,
  `MediaProvider`, `MediaFolderSystemKey`;
- focused model guards/lease integration, without observers that hide workflow.

Migration A owns the only initial bootstrap: after creating `media_folders`, it
upserts the six rows in enum order using unique `system_key`, then creates
`media_assets` with a non-null folder FK. It is explicitly not data-free. The
future cutover runs it once under Laravel's migration lock and full maintenance;
the unique-key upsert makes the row bootstrap idempotent if that boundary is
retried. Down drops bindings, assets, then the folder table/rows. No seeder or
request-time lazy bootstrap may create a second source of truth.

Modify:

- `MediaAttachment` fillable/casts/asset relation;
- `MediaMutationOperation` fillable/casts/asset relation;
- `Media` Curator binding relation;
- ContentGroup/ContentItem asset attachment relations only where needed;
- factories.

Use explicit FK/index names where MySQL length or reversible drops require
them. Do not create database-native enums. Do not unique `sha256`.

### GREEN proof

Run `MediaAssetKernelTest.php`, relevant model tests, Pint dirty check and
`git diff --check`.

## Job 2: asset authority, provider adapter and compatibility bridge

### RED tests

Create `tests/Feature/MediaAssetCompatibilityTest.php` proving:

- trusted active asset is found by key;
- Needs Repair, trashed, incomplete proof, missing binding and forged key are
  denied;
- asset in any physical root is compatible with podcast/episode/header/default/
  About/team image slots after trust proof;
- logical folder visibility does not bypass integrity/authorization;
- a Curator row with a mirrored key and valid-looking metadata is still denied
  when bound asset is Needs Repair;
- provider adapter preserves exact Curator numeric ID and only mutates under
  lease;
- attachment manager dual-writes and validates both identities;
- asset/Curator mismatch or stale owner identity fails atomically;
- mutation fence catches open operations by either identity;
- legacy unsafe owner exception/default fallback remains intact.

### Implementation

Create:

- `MediaAssetScope` with trusted+active query/find/lock methods;
- `MediaAssetIdentityResolver` for asset key + compatibility path fallback;
- `MediaAssetCompatibilityPolicy` independent of physical root/folder;
- `MediaAssetStoragePolicy` for owned roots/path normalization;
- `CuratorMediaProvider` implementing a small provider contract.

Modify current identity/relationship/settings/import/export/public resolvers to
prefer asset identity while retaining legacy fallback before conversion.
Ordinary gallery/picker/controller code must not accept a provider row without
a trusted active asset. Keep `MediaRecordScope` only for explicit provider/
legacy operations.

Update `MediaAttachmentManager`, `MediaMutationFence`, `MediaMutationLease`,
`MediaAttachmentIdentityResolver`, `MediaAttachmentFormState`, settings
projector, importers/exporters and public Menu/About/default resolvers. Preserve
exception inheritance/catches and existing owner fingerprint behavior.

Use a schema capability service memoized per request/command so pre-migration
legacy reads do not query missing tables and post-schema reads do not silently
fall back for bound unsafe rows.

### GREEN proof

Run the new compatibility file plus:

- `MediaRecordScopeAndAuthorizationTest`
- `MediaAttachmentModelTest`
- `LegacyOwnerMediaRepairTest`
- relevant settings/import/export/media tests
- `MediaRelationshipPerformanceTest`

## Job 3: deterministic dual-profile manifest and closure gate

### RED tests

Create `tests/Feature/CuratorMediaAssetConversionTest.php` for:

- exact `local_post_g1` and `production_pre_g1` capability detection;
- canonical manifest sorting/digest independent of insertion order;
- schema fingerprint and exact row/file/reference/open-operation fields;
- hybrid root/logical folder classifications;
- duplicate checksums retained and duplicate paths distinguished;
- filesystem-only files counted but excluded;
- exhaustive trusted-normalize/Needs Repair dispositions;
- stale bytes/schema/owner/settings/open-journal/digest refusal;
- closure command exits non-zero for any silent/unplanned row.

### Implementation

Create:

- schema profile enum/value;
- immutable manifest/entry/result values;
- `CuratorConversionPlanner` reusing LMTC canonical serialization and reference
  inventory;
- `CuratorConversionClosureReporter`;
- preflight and closure commands with human/JSON output.

The planner reads all relevant references in bounded batches and records
filesystem facts once. It never imports a rowless file and never mutates.

### GREEN proof

Run the new conversion file filtered to schema/manifest/closure plus existing
LMTC manifest/backfill/integrity tests.

## Job 4: journaled all-row executor and relationship preservation

### RED tests

Extend the conversion test for:

- exact one-row trusted canonical conversion;
- noncanonical/root-level normalization with hybrid destination;
- mixed/unassigned placement;
- cleaned-name Curator-ID collision suffix;
- exactly one asset/binding and same mirrored key;
- owner paths, dual attachments, nested settings pairs and portable key
  preservation;
- Needs Repair creation for missing/corrupt/ambiguous/unsanitized SVG;
- private quarantine evidence and no public canonical URL for repair rows;
- retry/failure at every pre/post-commit boundary;
- matching/conflicting destination, foreign lease, changed source/reference;
- idempotent exact-digest rerun.

### Implementation

Create `CuratorConversionExecutor` and the apply command. Reuse/extend the
existing coordinator, validator, cache invalidator, fence, lease and reference
switcher. Do not create a parallel journal.

Executor order:

1. authorize/maintenance/profile/digest;
2. lock/replan exact row/references/open operation;
3. create/resume idempotent operation;
4. quarantine source when present;
5. normalize/validate raster or classify repair;
6. copy/verify deterministic destination for trusted output;
7. short transaction creates asset/binding, mirrors provider key/metadata,
   dual-writes attachments/settings/paths and journal identity;
8. mark committed;
9. cache cleanup and zero-reference old-source cleanup;
10. retain private original with 90-day planned retention and mark complete.

Package 1 does not sanitize SVG. It records typed Needs Repair state.

### GREEN proof

Run full conversion and mutation coordinator tests, then legacy transition,
backfill/integrity and owner-repair regressions.

## Job 5: production-shaped closure, compatibility sweep and package closeout

### RED tests

Create `tests/Feature/CuratorMediaAssetProductionShapeTest.php` with builders
for exact local and production aggregate counts. Prove:

- 15/403 Curator rows yield 15/403 assets and bindings;
- all active owner/settings identities preserved or explicit Needs Repair;
- duplicate pairs remain separate;
- five filesystem-only files remain excluded;
- no unfinished journal/silent legacy row;
- query/manifest work is bounded;
- unsafe active assets return fallback and no unsafe URL.

### Reconciliation

- reconcile all Package 1 files/class names in research/plan/master docs;
- update context helper to Package 1 complete / Package 2 next;
- update current state/ledger/package route;
- create the Package 1 handoff with pending hash;
- add the future exact cutover command/migration information to
  `docs/phase-02/media-asset-production-cutover-runbook.md` without executing
  it; reserve `docs/phase-02/media-asset-svg-sanitation-runbook.md` for the
  Package 2 reusable sanitizer route.

### Review and gates

1. Requirements and security sweep.
2. Sol read-only implementation review; fix findings and rerun focused tests.
3. `vendor/bin/pint --test`.
4. `vendor/bin/filacheck`.
5. `npm run build`.
6. Full `php artisan test` last, serial and uninterrupted.

After any later file change, restart at Pint for the canonical state.

### Canonical commits

1. `feat: add media asset kernel and curator conversion`
2. `docs: backfill media asset package 1 hash`

Do not push. Finish Package 1 clean before starting Package 2.

## Out of scope

- real schema/conversion/backfill/sanitation action;
- gallery/folder CRUD UI;
- four-source acquisition/picker;
- owner hover/detail/change presentation;
- filesystem-only discovery/import and lifecycle;
- dependency changes;
- compatibility field removal.
