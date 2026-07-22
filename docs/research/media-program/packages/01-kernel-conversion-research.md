# MEDIA-P1 Kernel and Curator Conversion Research

## Status

- Package ID: `MEDIA-P1-KERNEL-CONVERSION`
- Stage: Gate 0 draft source reconciliation complete; independent review and
  Gate 0 closeout are pending before package implementation.
- Parent audit/option: `LS-20260722-PODTEXT-MEDIA-ASSET-PROGRAM-06` /
  `MEDIA-CUTOVER-O1-DIRECT-ASSET-HYBRID-ROOT-MAINTENANCE`.

Read the registry, master research, master plan and dual baselines before this
file. Reconcile this file again against the exact post-Gate-0 HEAD before the
first test.

## Current source map

### Curator/provider identity

- `app/Models/Media.php`: Curator subclass, lease-guarded creation/key issuance,
  inherited destructive observer disabled.
- `database/migrations/2026_07_12_140228_create_curator_table.php`: original
  Curator table.
- `database/migrations/2026_07_20_000001_add_reference_key_to_curator_table.php`:
  nullable unique Curator key.
- `app/Observers/CuratorMediaObserver.php`: application storage-identity and
  deletion containment.
- `app/Support/Media/MediaRecordScope.php`: current Curator metadata/root/key
  trust query; must become provider-only compatibility logic.

### Relationships and journal

- `MediaAttachment`, `ContentGroup`, `ContentItem`, and
  `MediaAttachmentManager` own shared singleton owner roles and legacy path
  mirrors.
- `MediaMutationOperation`, `MediaFilesystemMutationCoordinator`,
  `MediaMutationFence`, `MediaMutationLease` own durable copy/verify/commit/
  cleanup state.
- G1 migrations currently point attachment/journal FKs to `curator.id`.

### Identity consumers

- `MediaIdentityResolver`
- `MediaAttachmentIdentityResolver`
- `MediaAttachmentFormState`
- `LegacyOwnerMediaDiagnostics` / `LegacyOwnerMediaRepairer`
- `SettingsMediaIdentityProjector`
- public default/Menu/About renderers and resolver services
- ContentGroup/ContentItem importers and exporters
- `MediaReferenceFinder` and `MediaIntegrityReporter`

### Transition primitives

- `LegacyMediaTransitionManifest`
- `LegacyMediaTransitionPlanner`
- `LegacyMediaTransitionExecutor`
- `LegacyMediaReferenceSwitcher`
- `StoredMediaValidator`
- `ImageUploadValidator`
- `SvgUploadSanitizer`
- `MediaCacheInvalidator`

These are Curator-typed but contain the proven digest, journal, fence, checksum,
reference-switch and compensation algorithms to reuse.

## Schema evidence

Fresh Boost schema confirms local post-G1 tables and exact FKs/indexes. Future
production pre-G1 lacks them. New migration B must not pretend it can run before
G1 attachment/journal migrations; the future runbook supplies the exact order.

The approved schema is:

- `media_folders` with six protected flat system keys;
- `media_assets` with immutable key, trust/lifecycle, canonical storage proof,
  filenames/provenance/repair data and no unique checksum;
- `media_provider_bindings` with one exact Curator ID per asset;
- nullable asset bridges on attachment/journal while retaining Curator fields.

Needs Repair canonical disk/path/hash stay null. Provider snapshot and repair
provenance retain the old Curator path/metadata. This prevents accidental raw
URL projection.

## Key compatibility risk

Current `MediaRecordScope` considers a non-null Curator key plus acceptable
metadata/root sufficient for ordinary gallery use. If conversion mirrors an
asset key onto a Needs Repair Curator row before asset authority is active, the
row may become browseable. Package 1 must prove:

1. ordinary reads use MediaAsset status before unsafe keys are mirrored;
2. unbound/partially converted rows are unavailable during maintenance;
3. provider scope cannot be invoked by normal picker/controller paths;
4. Needs Repair never produces a canonical/public URL.

## Relationship bridge decision

- `media_attachments.media_asset_id` is nullable during compatibility and dual
  written with `media_id`.
- Both identities must resolve to the same provider binding when present.
- Existing owner-role uniqueness remains authoritative.
- Active unsafe associations may point to a Needs Repair asset and Curator row;
  they remain diagnostic and resolve fallback.
- No owner table receives another image FK.

Mutation fences must query open operations by both asset and Curator provider
identity so pre-cutover and post-cutover jobs cannot race.

## Conversion decision

One planner/executor accepts `local_post_g1` and `production_pre_g1` profiles.
It never scans/imports filesystem-only files. Each Curator row becomes exactly
one asset/binding; duplicate checksums never merge.

Raster candidates may be:

- trusted without byte change after exact proof;
- trusted after journaled normalization and hybrid-root placement;
- Needs Repair when proof/safe transition is unavailable.

SVG candidates become Needs Repair in Package 1 unless already proven by an
approved sanitation proof contract. Package 2 supplies the reusable sanitizer.
No real SVG is sanitized.

## Hybrid placement decision

- one active purpose: purpose root and purpose logical folder;
- mixed active purposes: physical `media-library`, logical Legacy library;
- unassigned: physical `media-library`, logical Legacy library;
- path collision: preserve cleaned stem and add deterministic Curator-ID
  suffix; never overwrite;
- later attachment reuse does not trigger move/copy.

## Provider adapter decision

`CuratorMediaProvider` is the only first implementation. It:

- loads/locks provider row by binding record key;
- creates/updates provider row only under the existing mutation lease;
- mirrors the asset key as Curator key after asset authority is effective;
- projects provider metadata/snapshot;
- never decides trust, compatibility, folder, owner or portable identity;
- never exposes vendor uploader/observer mutations.

## Test fixture decision

Reuse committed valid raster and safe/malicious SVG fixtures. Add builders for:

- root-level raster;
- noncanonical raster;
- metadata mismatch;
- missing file;
- ambiguous duplicate path;
- same checksum/different Curator rows;
- local 15-row aggregate;
- production 403-row aggregate.

The 403-row fixture must be economical: a small set of fixture bytes may be
copied under distinct fake paths, but row counts, checksums, duplicate-pair
semantics, references and dispositions must match the verified shape.

## Existing tests to extend

- `MediaAttachmentModelTest`
- `MediaRecordScopeAndAuthorizationTest`
- `MediaRelationshipPerformanceTest`
- `LegacyMediaTransitionTest`
- `MediaMutationCoordinatorTest`
- `MediaBackfillAndIntegrityReportTest`
- `ImageMediaCuratorTest`
- `LegacyOwnerMediaRepairTest`
- importer/exporter/settings tests that assert media keys

Create focused Package 1 files rather than overloading one giant test:

- `MediaAssetKernelTest`
- `CuratorMediaAssetConversionTest`
- `CuratorMediaAssetProductionShapeTest`
- `MediaAssetCompatibilityTest`

## Security and performance findings

- ULID syntax and Livewire lock are not authorization.
- Source/destination paths are server planned; no client path enters executor.
- Asset key immutability and storage mutation lease need model tests.
- Needs Repair bytes cannot use the current direct public URL projector.
- Settings/About/Menu/public resolvers must keep typed catch/fallback behavior.
- Manifest aggregation must batch references and file facts, not query settings
  or owners per Curator row.
- Closure queries need indexes on binding and bridge fields.
- No filesystem hash/decode occurs in owner/gallery row rendering.

## Source reconciliation conclusion

The package fits the approved schema and security boundary with no dependency
or package-count drift. The main implementation risk is the authority switch:
asset status must become authoritative before unsafe provider keys are
available. Tests must prove the partial-conversion/maintenance state as well as
final closure.

No application, database, storage, cache, dependency, Git or production state
was changed by this package research.
