# PodText Media Asset Program Master Implementation Plan

## Approved contract

- Audit: `LS-20260722-PODTEXT-MEDIA-ASSET-PROGRAM-06`
- Option: `MEDIA-CUTOVER-O1-DIRECT-ASSET-HYBRID-ROOT-MAINTENANCE`
- Execution: Gate 0, then five sequential packages in the existing `main`
  checkout.
- Writers: one repository writer at a time.
- Research/review model route: GPT-5.6 Sol where available.
- Implementation model route: GPT-5.6 Terra where available.
- Execution mode: subagents use Fast where routing is available; the main task
  remains non-Fast.
- Dependencies: no additions or updates.
- Real data: no local-development or production mutation.

This plan is subordinate to the requirements registry. Material dependency,
schema, security, package/task count, data shape, or production-action drift
returns to amended Stage 1.

## Global execution rules

1. Before each package, recheck Git baseline, current helper/registry/master
   plan, active package research/plan, source, migrations, installed vendor
   source and affected tests.
2. Reconcile the active package research/plan before application code.
3. TDD is mandatory: write the focused failing Pest test, run it, verify the
   failure proves missing behavior, implement minimally, then refactor green.
4. Use isolated test databases and `Storage::fake()`/temporary test disks.
   Never apply schema or media commands to the local development database.
5. All HTTP tests use `Http::preventStrayRequests()` and committed fixtures.
6. Network/filesystem work stays outside owner/import database transactions.
7. Every user-facing label/hint/error/action has English and Hebrew keys.
8. Filament changes follow installed Filament 5.7.1 source, version-aware
   Boost docs, the forms/security/performance skills, and the documented
   FilamentExamples limitation.
9. Livewire IDs/keys/paths remain untrusted even when properties are locked.
10. Focused tests run serially when shared file fixtures could collide. The
    final full suite is last, serial and uninterrupted.
11. No `filacheck --fix`, dependency install/update, branch/worktree, push, or
    real environment action.
12. Update `media-program-context.md` and the package handoff after each
    package. Do not mark later packages complete early.

## Commands and file creation policy

No scaffold command is required. Create named files deliberately so migration,
Resource and package boundaries remain reviewable. Artisan commands created by
the program are dry-run-first and are exercised only against isolated test
databases during this task.

Focused verification commands are named in each package plan. Package final
gates follow repository order. No live command with `--apply` is run by the
operator shell in this implementation task.

## Shared target architecture

### Models and enums

Create:

- `App\Models\MediaAsset`
- `App\Models\MediaProviderBinding`
- `App\Models\MediaFolder`
- `App\Enums\MediaAssetValidationStatus`
- `App\Enums\MediaAssetLifecycleStatus`
- `App\Enums\MediaProvider`
- `App\Enums\MediaFolderSystemKey`
- a finite asset source enum if source comparisons otherwise repeat strings;
- a finite slot/compatibility enum separate from physical root if current
  `ImageUploadPurpose` cannot be narrowed safely.

Update:

- `MediaAttachment` with nullable asset relation and dual identity;
- `MediaMutationOperation` with nullable asset relation/key snapshot;
- `Media` with one Curator provider binding relation and the existing lease
  containment;
- ContentGroup/ContentItem asset relationships while retaining Curator and
  legacy-path compatibility relationships.

### Migration A: kernel tables

Create
`database/migrations/2026_07_22_000000_create_media_asset_kernel_tables.php`.

In order:

1. create `media_folders` with nullable unique `system_key`, custom `name`,
   `position`, `is_visible`, timestamps;
2. insert/upsert the six deterministic protected system-folder rows in
   `MediaFolderSystemKey` order, keyed by unique `system_key`; document that
   this migration is not data-free, runs once under the migration lock/full
   maintenance, and is safe to resume at the row-bootstrap boundary;
3. create `media_assets` with non-null `media_folder_id` and the exact other
   fields/indexes in master research;
4. create `media_provider_bindings` with provider-record and asset-provider
   uniqueness.

All FKs use explicit delete behavior. `media_assets.media_folder_id` and
bindings use RESTRICT. Down drops bindings, assets, then folders. Tests prove
deterministic folder order/parity, duplicate-key retry behavior, folders before
assets, and up/down on an isolated schema. Concurrent production migration
runs are forbidden; the cutover uses Laravel's migration lock in full
maintenance.

### Migration B: compatibility bridge

Create
`database/migrations/2026_07_22_000001_bridge_media_asset_identity.php`.

- add nullable `media_asset_id` to `media_attachments`, RESTRICT FK, and
  `(media_asset_id, role)` index;
- add nullable `media_asset_id` and `media_asset_reference_key` to
  `media_mutation_operations`, SET NULL FK, and the two asset/status indexes;
- preserve all existing Curator columns and constraints;
- down removes only new indexes/FKs/columns.

Migration B requires the exact G1 attachment/journal migrations first. The
future production runbook makes that dependency an explicit allowlist; it does
not hide it in conditional migration behavior.

### Application invariants

`MediaAsset` creation/update guards must enforce:

- immutable ULID reference key;
- trusted rows have active canonical storage identity, positive size, allowed
  MIME/extension, valid raster dimensions or sanitation proof, SHA-256 and
  trusted timestamp;
- Needs Repair rows cannot masquerade as trusted through provider metadata;
- storage identity mutation happens only under the journal/lease coordinator;
- trashed assets are not selectable;
- checksum is never unique.

`MediaProviderBinding` guards exact supported provider values and numeric
Curator record keys for the Curator adapter. It never owns trust.

## Package 1: `MEDIA-P1-KERNEL-CONVERSION`

### Outcome

Land the schema, app-owned asset/provider authority, compatibility bridge, one
shared deterministic converter for both schema profiles, full Curator-row
closure tooling, and production-shaped tests. Do not create gallery/folder UI
beyond what is necessary to keep existing surfaces safe.

### Test-first sequence

1. Schema/model tests:
   - migrations up/down;
   - system folders and protection marker;
   - immutable asset key/storage guards;
   - binding uniqueness and no checksum dedupe;
   - attachment/journal dual relations;
   - morph aliases unchanged.
2. Asset trust tests:
   - trusted+active allowed;
   - Needs Repair, trashed, missing proof, forged key and stale provider denied;
   - asset slot compatibility independent of physical root/folder;
   - Curator key alone cannot bypass asset status.
3. Manifest tests:
   - `local_post_g1` and `production_pre_g1` schema profiles;
   - canonical ordering/digest independent of insertion order;
   - every row/file/reference/open-journal decision field;
   - filesystem-only count present and excluded;
   - stale schema/bytes/reference/digest rejection.
4. Conversion tests:
   - one row -> one asset/binding, Curator ID preserved, mirrored single key;
   - canonical raster, noncanonical raster, root-level active, fixed-root,
     mixed-purpose, settings-only, unassigned, duplicate bytes, collisions;
   - missing/corrupt/metadata mismatch/ambiguous/unsanitized SVG -> Needs
     Repair with no canonical URL;
   - owner/settings/import-export/path/key/attachment preservation;
   - failure/retry before copy, after quarantine, after stage/destination,
     before/after commit and cleanup pending;
   - foreign lease, open operation, changed source and destination conflict;
   - idempotent rerun.
5. Aggregate fixtures:
   - exact 15-row local incident shape;
   - exact 403-row production shape, including duplicate-byte pairs and five
     excluded filesystem-only files;
   - closure digest/count and no silent legacy-only row.
6. Compatibility tests:
   - owner edit/list mounts remain safe before, during and after conversion;
   - unsafe bound asset returns diagnostic fallback;
   - settings and import/export use asset key first while retaining paths;
   - query bounds remain stable.

### Production classes

Create focused classes under `App\Support\MediaAssets` or the existing media
namespace with one consistent boundary:

- `MediaAssetScope`
- `MediaAssetIdentityResolver`
- `MediaAssetCompatibilityPolicy`
- `MediaAssetStoragePolicy`
- `CuratorMediaProvider`
- `CuratorConversionSchemaProfile`
- `CuratorConversionManifest`
- `CuratorConversionPlanner`
- `CuratorConversionExecutor`
- `CuratorConversionClosureReporter`
- typed manifest entry/result/value objects only where they remove ambiguous
  arrays.

Extend rather than duplicate:

- `MediaFilesystemMutationCoordinator`
- `MediaMutationFence`
- `MediaMutationLease`
- `LegacyMediaReferenceSwitcher`
- `StoredMediaValidator`
- `MediaReferenceFinder`
- `MediaIntegrityReporter`
- cache invalidation and existing transition digest algorithms.

Add dry-run-first commands:

- `media-assets:preflight-curator-conversion`
- `media-assets:convert-curator`
- `media-assets:report-conversion-closure`

Commands accept explicit profile, JSON/human output and expected digest. Apply
requires the exact digest and maintenance assertion but is not run against a
real environment in this task.

### Compatibility consumer changes

- `MediaAttachmentManager` dual-locks/dual-writes asset and Curator identity.
- `MediaAttachmentIdentityResolver` resolves asset first and retains typed
  legacy diagnostic fallback.
- `MediaAttachmentFormState` returns asset key, not Curator authority.
- settings projector/resolvers use asset key first and path only as fallback.
- importers/exporters use the same asset key.
- public Menu/About/default/owner resolvers reject non-trusted assets and
  retain the existing exception/fallback contract.
- `MediaRecordScope` becomes provider-only/compatibility code; ordinary asset
  selection cannot depend on physical purpose root.
- mutation fences cover the union of asset and Curator provider identity so old
  and new operations cannot race.

### Package 1 closeout

Reconcile package research/plan and create
`docs/phase-02/media-program-p1-kernel-conversion-handoff.md`. Run focused
tests and independent Sol review, then run the complete package gate in exact
order: requirements sweep, `vendor/bin/pint --test`, `vendor/bin/filacheck`,
`npm run build`, and full `php artisan test` last, serial and uninterrupted.
Any later file change restarts at Pint. Commit implementation with pending hash,
then stamp the hash in docs immediately.

## Package 2: `MEDIA-P2-GALLERY-REPAIR`

### Outcome

Make MediaAsset the trusted gallery, add flat logical folder management,
separate Needs Repair, and expose reusable raster normalization and SVG
sanitation without acting on real files.

### Filament Resource

Create/update `App\Filament\Resources\MediaAssets\MediaAssetResource` with
list/edit metadata only; no generic create until Package 3 acquisition exists.

Resource query:

- trusted + active for normal gallery;
- Needs Repair as a separate tab/page/query;
- eager-load logical folder and minimal Curator binding only where displayed;
- default 25 pagination and finite allowed page sizes;
- bounded search maximum 50;
- no physical-root default scope.

Use a custom image/card column/view where necessary. All preview/download URLs
go through app-owned authorized controllers. Needs Repair produces no byte URL.

### MediaFolder Resource/settings

Create a focused folder management Resource or settings surface:

- system rows: reorder/visibility/default mapping only; no rename/delete;
- custom rows: create/rename/reorder/visibility/delete;
- delete custom folder action reassigns its assets to Legacy library in a
  transaction;
- English/Hebrew labels;
- no hierarchy.

Create an app-wide media-library settings group for:

- default logical folder per slot/source where approved;
- browse/search/upload working defaults;
- preserve-cleaned-original-name default;
- raster working size/dimension targets;
- quarantine retention default.

Validate settings inside absolute server hard ceilings.

### Repair transitions

- `MediaAssetNormalizer`: exact trusted/repair candidate, stage, normalize,
  validate/checksum, journal commit, quarantine old source, cache cleanup.
- `MediaAssetSvgSanitizer`: exact staged SVG, malicious-content removal,
  post-sanitize validation/checksum, trusted commit.
- revalidation that performs no byte change when existing canonical bytes prove
  trust;
- typed repair/disposition reasons and retry behavior;
- no actual Curator IDs 6/7 action.

### Tests

- trusted/repair separation and authorization;
- no unsafe preview/download/Glide URL;
- system/custom folder rules, deletion reassignment and visibility semantics;
- default folder vs All Media behavior foundation;
- cross-root trusted asset visibility;
- working settings inside hard ceilings;
- raster success/failure/retry/collision/cache/quarantine;
- safe and malicious SVG fixtures; trusted sanitized SVG compatible everywhere;
- 1/10/50 query and serialized-state budgets;
- Hebrew/English and RTL-safe controls.

Close with reconciled package docs, handoff, review, gates and canonical two
commits.

## Package 3: `MEDIA-P3-ACQUISITION-PICKER`

### Outcome

Replace every Curator/path picker surface with one asset-key
`MediaAssetPicker` and one acquisition pipeline for Gallery, Upload, URL and
Storage. Route podcast and episode Spotify image URLs through it.

### Picker component

Create an app-owned Filament field/action shell and embedded Livewire 4
component. It stores only the selected MediaAsset key.

Required behavior:

- initial configured logical-folder filter;
- one-click All Media;
- trusted+active+slot-compatible query independent of physical path;
- browse 25, search result cap 50, max upload 10 by default;
- minimal card projection; detail loads on demand;
- locked state plus server re-query/authorization/expected-owner comparison;
- no Eloquent models, provider snapshots or repair details serialized into
  every card.

### Acquisition service

Create one coordinator/result contract used by all sources:

- Gallery returns existing asset without file mutation.
- Upload accepts `TemporaryUploadedFile` with `storeFiles(false)` and validates
  actual bytes.
- URL reuses safe external fetcher/pinned transport and performs work outside
  the owner transaction.
- Storage consumes a server-issued exact discovery candidate/digest, never raw
  path.
- Raster normalizes; SVG sanitizes; provider adapter creates Curator row;
  MediaAsset + provider binding + journal commit atomically; optional owner
  attach uses expected identity.
- filename option preserves a cleaned original stem or generates a server name;
  collisions are deterministic.

### Spotify integration

Both podcast and episode create/edit lookup flows may prefill/launch the URL
source with the fetched image URL. Existing Spotify metadata fetch remains
separate. No fifth tab/downloader.

### Tests

- four sources converge on the same asset/provider/key/result contract;
- selecting existing causes no copy/move/new row;
- upload filename/limits/collisions/batch behavior;
- URL SSRF/redirect/DNS/size/content/stale owner/idempotency;
- Storage forged/stale/path traversal/symlink refusal;
- podcast and episode Spotify image handoff;
- all prior settings/Menu/About/owner picker surfaces use MediaAssetPicker;
- authorization/forged Livewire values;
- query/state/performance and HE/EN/RTL.

Close with reconciled package docs, handoff, review, gates and canonical two
commits.

## Package 4: `MEDIA-P4-OWNER-IMAGE-UX`

### Outcome

Give podcast and episode tables/workspaces one reusable bounded image preview,
detail, download, copy and change action while retaining safe fallback and
repair diagnostics.

### Shared components/actions

- app-owned table image column/view with 48-64px thumbnail and hover/focus
  overlay capped at 300px;
- shared `MediaAssetDetailAction` using Filament 5.7 mounted action and
  modal/slide-over APIs;
- modal content loads asset/fallback source details on mount;
- authorized safe download;
- copy cleaned filename;
- edit metadata only for a real authorized asset;
- change image embeds the same MediaAssetPicker;
- Needs Repair notice/replacement/detach without byte preview.

### Surfaces

- ContentGroup Resource table/edit page;
- ContentItem Resource table/edit/workspace;
- ContentGroup ContentItems relation manager;
- existing reusable `ContentImageActions` boundary;
- settings/image surfaces only where the same row/record action is applicable.

### Tests

- direct asset, podcast fallback, configured default and static fallback labels;
- column action does not follow the record URL accidentally;
- hover cap/focus semantics and lazy trusted URL;
- modal info/download/copy/edit/change authorization;
- fallback change targets owner without mutating fallback;
- unsafe current association mounts and repairs without 500/unsafe URL;
- stale owner/forged asset/key/path refusal;
- 1/10/50 query and payload budgets;
- Filament/Livewire real workflow plus HE/EN/RTL and browser coverage.

Close with reconciled package docs, handoff, review, gates and canonical two
commits.

## Package 5: `MEDIA-P5-FILES-LIFECYCLE`

### Outcome

Add app-owned Files Discovery and complete journaled physical lifecycle:
import, Import-and-Use, move, rename, trash, restore and purge.

### Files Discovery

Create a Filament page/Resource backed by a bounded read-only discovery
service, not arbitrary client paths. It:

- enumerates only configured app-owned candidate roots;
- excludes canonical bound files, cache, curations, staging, quarantine, trash
  internals, symlinks/traversal and non-owned disks;
- returns opaque candidate identity/digest and metadata;
- provides explicit Import and Import-and-Use through Package 3 coordinator;
- never imports automatically during Curator conversion.

### Physical lifecycle

Add exact plan/value objects and journal operation types for:

- move and rename: copy -> verify -> DB/provider/asset switch -> cleanup;
- trash: copy/move to private quarantine, commit lifecycle state and
  `purge_after`, then remove public source;
- restore: validate conflict and bytes, restore trusted canonical output,
  commit active state;
- purge: require retention elapsed, completed operations, zero references,
  fresh digest and authorization; preserve journal/provider tombstone evidence
  as designed without leaving selectable rows.

Logical folder updates stay database-only and never call this lifecycle.

### Tests

- discovery inclusion/exclusion, pagination and opaque candidates;
- exact import and Import-and-Use;
- move/rename/trash/restore/purge success and idempotent retry;
- collisions, missing/changed bytes, stale digest/reference, foreign lease,
  failures at every commit/cleanup boundary;
- cache invalidation and 90-day default/admin bounded retention;
- zero-reference proof across owners/settings/import-export/open journals;
- unsafe/trash rows denied from gallery/picker/controller;
- authorization/forged inputs, HE/EN/RTL, query/state budgets.

Replace old G1 runbooks with
`docs/phase-02/media-asset-production-cutover-runbook.md` and
`docs/phase-02/media-asset-svg-sanitation-runbook.md`, but do not execute them.
Close with package handoff, review, final gates and canonical two commits.

## Package documentation route

Each package has research and plan files under
`docs/research/media-program/packages/`. Before code, replace preliminary route
language with exact current source/class/test decisions. The handoff must
contain:

- requirement classification;
- files and migrations changed;
- tests added/updated;
- every command and result, including expected RED/failures;
- review findings and fixes;
- schema/data/deployment implications;
- no-real-environment-mutation statement;
- numbered imperative Local Front Check steps;
- pending implementation hash before commit.

## Final verification and commit rule

For each package, use proportional focused verification during implementation.
For each package's final code state, after the last file change run this entire
gate with no conditional omissions:

1. requirements classification sweep;
2. `vendor/bin/pint --test`;
3. `vendor/bin/filacheck`;
4. `npm run build`;
5. full `php artisan test` last, serial and uninterrupted.

Any later file change restarts at Pint. Never use `filacheck --fix`.

Each package closes with:

1. implementation/code/tests/docs/handoff commit with pending hash;
2. immediate docs-only hash-stamp commit.

Do not push. Finish the complete program with a clean worktree.

## Planned requirement classification

- Implemented across Packages 1-5: MediaAsset authority, complete Curator-row
  converter, gallery/folders/repair, normalization/SVG engine, picker/
  acquisition, owner UX, Files Discovery and lifecycle.
- Already correct and preserved: image-only positive allowlist, existing SSRF
  transport, journal/fence concepts, typed owner fallback, Admin boundary,
  portable-key settings/import/export direction.
- Production-only: backups, maintenance, applying migrations/conversion,
  actual SVG sanitation, deploy/cache/process action and final real closure.
- Deferred: dependency/plugin installation, filesystem-only production import,
  compatibility-field removal, other media types/providers, permission cleanup.
- Blocked: any candidate lacking unique identity, byte proof, fresh digest,
  authorized disposition or safe destination remains Needs Repair/explicitly
  blocked; no rule is weakened to force closure.
