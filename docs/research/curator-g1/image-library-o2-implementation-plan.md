# CURATOR-G1 O2 Full App-Owned Image Library Implementation Plan

Date: 2026-07-20

Audit ID: `LS-20260720-CURATOR-G1-IMAGE-LIBRARY-01`

Approved option: `CURATOR-G1-O2-FULL-APP-OWNED-CURATOR-SURFACE`

This plan is the source-reconciled execution contract for the seven approved
mini-tasks. It depends on
`docs/research/curator-g1/image-library-o2-research.md`. The current source
reveals no material conflict, so implementation follows immediately after this
document review without another approval request.

## Invariants across all mini-tasks

- Work only in the existing `/Users/studioycm/Herd/PodText` checkout on `main`.
- Preserve unrelated work; stop if overlapping uncommitted changes appear.
- Do not inspect secrets, use the local development database, touch production,
  publish vendor files, add dependencies, install Spatie Media Library, create
  a branch/worktree, push, or deploy.
- Migrations contain schema only. Backfills and reports are separate commands.
- Every upload/mutation entry point uses the same app-owned policy, validator,
  scope, policy abilities, and coordinator.
- RichEditor file attachments remain disabled.
- Existing rows/files are never silently removed.
- Tests use the isolated SQLite database, Storage fakes, committed fixtures,
  and `Http::preventStrayRequests()` for every HTTP-touching test.
- Application edits and repository-modifying agents run sequentially.

## Core architecture

### Finite domain types

Create:

- `app/Enums/ImageUploadPurpose.php`
- `app/Enums/MediaAttachmentRole.php`
- `app/Enums/MediaMutationOperationType.php`
- `app/Enums/MediaMutationStatus.php`

`ImageUploadPurpose` owns exact root and type compatibility:

| Purpose | Root | Allowed type |
|---|---|---|
| `content_group_cover` | `content-groups/covers` | raster |
| `content_item_primary_image` | `content-items/images` | raster |
| `header_logo` | `header` | raster or strict SVG |
| `team_image` | `team` | raster |
| `about_image` | `about` | raster |
| `default_image` | `default-images` | raster |

No arbitrary root or wildcard type enters these enums.

### Policy/validation foundation

Create or replace focused classes under `app/Support/Media/`:

- `CuratorImageUploadPolicy`
- `ImageUploadValidator`
- `ValidatedImage`
- `SvgUploadSanitizer`
- `MediaRecordScope`
- `MediaRecordProjector`
- `StoredMediaValidator`
- `MediaMutationLease`
- `MediaMutationFence`
- `MediaFilesystemMutationCoordinator`
- `MediaAttachmentManager`
- `MediaIdentityResolver`
- `MediaAttachmentIdentityResolver`
- `MediaAttachmentFormState`
- `SettingsMediaIdentityProjector` under `app/Support/SettingsLifecycle/`
- `MediaIntegrityReporter`
- `SafeExternalImageFetcher`
- `ExternalImageDnsResolver`
- `PinnedExternalImageTransport`
- `LegacyMediaRegistrationPlan`
- `LegacyMediaRegistrationPlanner`
- `LegacyMediaReferenceSwitcher`

Keep `ImageUploadRules` as the Filament-facing union/size helper, delegating
canonical types to the new policy. Restrict `ImageFileNamer` storage output to
generated ULIDs and canonical validated extensions; legacy slug strategies
remain egress-only.

## Schema design

### Migration 1: nullable portable Media identity

`database/migrations/2026_07_20_000001_add_reference_key_to_curator_table.php`

- `char('reference_key', 26)->nullable()->unique()->after('id')`
- no data update
- reversible unique-index and column removal

New app Media receives a ULID during `creating` only while the scoped
coordinator creation lease is active. A non-null key cannot change. The one
allowed legacy transition is null to a generated key in the dedicated
content-normalization backfill, which writes a completed SHA-256 proof journal
atomically with the key.

### Migration 2: shared attachments

`database/migrations/2026_07_20_000002_create_media_attachments_table.php`

| Column | Definition |
|---|---|
| `id` | bigint primary key |
| `media_id` | unsigned bigint, FK `curator.id`, delete restrict |
| `attachable_type` | string(32) |
| `attachable_id` | unsigned bigint |
| `role` | string(32) |
| `position` | unsigned integer default 0 |
| timestamps | standard |

Constraints and indexes:

- unique `media_attachments_owner_role_unique` on
  `(attachable_type, attachable_id, role)`;
- index `media_attachments_media_role_index` on `(media_id, role)`;
- restrictive `media_id` foreign key.

Stable attachment type values are `content_group` and `content_item`.
Allowed type/role pairs are `content_group` + `cover` and `content_item` +
`primary_image`. The same `media_id` can appear for many owners. `position`
remains for deterministic ordering; no owner gallery is introduced.

### Migration 3: durable mutation journal

`database/migrations/2026_07_20_000003_create_media_mutation_operations_table.php`

Columns:

- `id`, unique ULID `operation_key`;
- nullable `media_id`, `media_id_snapshot`, and `media_reference_key`;
- nullable `user_id`;
- finite `operation`, `status`, and nullable `purpose` strings;
- nullable indexed `idempotency_key` plus normal mutation-worker and repair
  `lease_token` / `lease_expires_at` ownership fields;
- source, staging, destination, and quarantine disk/path/SHA-256 triples;
- JSON `context`, unsigned integer `attempts`, text `last_error`;
- `started_at`, `committed_at`, `cleanup_completed_at`, `completed_at`,
  `failed_at`, and timestamps.

Foreign keys use null-on-delete so repair evidence survives Media/user removal.
Indexes:

- unique operation key;
- `(status, updated_at, id)` for repair;
- `(media_id, status)` for record history.

### Settings shape migration

`database/settings/2026_07_20_000000_add_public_media_reference_keys.php`

This reversible Spatie settings-shape migration adds nullable adjacent media
reference-key fields for menu logos, about images, team images, and default
images. It does not resolve paths, populate identities, move files, or perform a
backfill; the separate settings backfill command owns that production-gated
work.

### Morph compatibility

Register a non-enforced compatibility map with FQCN entries before aliases:

```php
Relation::morphMap([
    ContentGroup::class => ContentGroup::class,
    ContentItem::class => ContentItem::class,
    'content_group' => ContentGroup::class,
    'content_item' => ContentItem::class,
]);
```

This preserves Spatie taggable writes using the existing FQCN. Attachment
writers explicitly set the stable alias. Do not use `enforceMorphMap()`.

## Model and relationship API

Create:

- `app/Models/Media.php` extending Curator Media;
- `app/Models/MediaAttachment.php`;
- `app/Models/MediaMutationOperation.php`;
- focused factories under `database/factories/`.

The app Media model:

- keeps the `curator` table and numeric primary key;
- generates/locks `reference_key`;
- has many `attachments` and mutation operations;
- overrides `resolveObserveAttributes()` so Curator's destructive observer is
  not inherited and only the app observer is registered;
- excludes mutable storage fields from ordinary mass assignment.

ContentGroup and ContentItem expose alias-constrained, ordered
`mediaAttachments()` relationships and singleton convenience relationships for
cover / primary image. Collection consumers eager load
`mediaAttachments.media`.

`MediaAttachmentManager` is the normal interactive/import attach/detach writer.
It validates
the type/role combination, reloads Media through `MediaRecordScope`, authorizes
the actor, performs the singleton write transactionally, and dual-writes the
legacy owner path as a compatibility mirror. It preserves old bytes and leaves
retirement to report/proof rather than observer deletion.
`LegacyMediaReferenceSwitcher` is the exceptional registration writer: under
locked/fingerprinted owner and raw-settings state, it creates the reviewed
attachments and switches compatibility identities in the same transaction as
the new Media row.

## Authorization enforcement points

Replace `CuratorMediaPolicy` with explicit Admin-or-higher checks for:

- `viewAny`
- `view`
- `create`
- `bulkUpload`
- `update`
- `delete`
- `deleteAny`
- `download`
- `rename`
- `swap`
- `select`
- `attach`
- `detach`

Record abilities also require `MediaRecordScope::allows($media)`. Rename, swap,
and delete additionally deny any attachment or legacy/settings reference.
Bulk operations first scope and batch-load all requested IDs, then authorize
every record before the first mutation. UI visibility is only a convenience.

Enforcement occurs in:

- Resource `getEloquentQuery()` and CRUD pages;
- every custom Resource/picker action;
- picker browse/search/load-more/select dispatch;
- attachment manager;
- mutation coordinator and lease-aware observer;
- download/view controller routes;
- queued external-image job reauthorization;
- direct model storage-field update/delete observer backstop.

## Data flows

### Standalone upload and multi-upload

1. Admin selects one finite `ImageUploadPurpose`.
2. Filament FileUpload accepts the exact global union, stores no final file,
   caps 10 files and two parallel transfers, and is schema-restricted.
3. Action re-parses purpose server-side and authorizes create/bulkUpload.
4. The coordinator reads the private Livewire temporary upload and the validator
   verifies extension, content MIME, agreement, size, strict format,
   decode/dimensions, then re-encodes raster or sanitizes/reparses SVG.
5. Coordinator creates the journal row and copies only normalized output to
   its private operation-staging path.
6. Coordinator writes normalized bytes to generated purpose-root destination,
   verifies SHA-256, then creates the app Media row in a short transaction.
7. After commit it removes staging, invalidates applicable caches, and marks
   complete. Failures remove uncommitted destination bytes and persist failure.

### Picker browse, search, load-more, and selection

1. Field passes only its server component key, enum purpose, and selected record
   IDs to the app modal component.
2. Each component request re-parses purpose and checks Admin access.
3. Browse loads one 25-record scoped projected page. Forward/back pagination
   replaces that bounded page; search returns at most 50 projected records.
4. Select receives an untrusted numeric ID, reloads through the purpose scope,
   authorizes `select`, and stores only selected IDs separately from the
   projection.
5. Insert dispatches IDs to the server-owned field component key. The field
   reloads every ID through the same scope and builds the minimal display
   projection.
6. Dehydration returns the immutable reference key. No client disk/path/root,
   URL, MIME, extension, or full Media array is trusted.

### Attachment and detach

1. Owner form/action submits an untrusted Media reference key.
2. Page/action reloads Media through the role-derived purpose scope.
3. Manager locks the owner, existing/target attachment rows, and sorted Media
   rows; authorizes attach/detach; refuses an active filesystem mutation; and
   writes the singleton attachment in the transaction.
4. Legacy `cover_path` / `image_path` is set from the trusted Media record.
5. Public/admin readers use attachment first and legacy path only as fallback.

### Metadata edit

Resource/picker receives an ID, uses the scope, authorizes `update`, and
allowlists only `alt`, `title`, `caption`, and `description`. Display filename
metadata never becomes a storage filename.

### Rename

Rename accepts no client filename, path, root, or display metadata. It is a
server-generated storage-identity rotation: the coordinator revalidates the
existing bytes, generates a new ULID path in the same purpose root, copies and
verifies the normalized bytes, locks/updates Media path/name under a mutation
lease, then quarantines/removes old bytes and invalidates old/new caches.
Attached or legacy-referenced rows are denied.

### Swap/replacement

Swap derives purpose from the trusted current record, performs full private
staging and validation, writes a new generated destination, verifies it,
locks/updates the Media metadata/path, then cleans old bytes/caches after
commit. Attached/referenced rows are denied. SVG never enters raster curation.

### Delete and bulk delete

Every record is scoped and authorized before mutation. Coordinator copies
source bytes to private quarantine, verifies the copy, deletes the row under a
lease in a short transaction, then deletes the public source, invalidates
caches, and marks complete. Quarantine is retained for separately approved
recovery/retention handling. Attached/referenced/disallowed rows are denied.

### Existing legacy-path registration

1. The command is dry-run-only by default. Apply requires one exact normalized
   `--path` and one existing Admin-or-higher `--actor` ID.
2. Planner derives purpose, source SHA-256, owners, and raw settings locations;
   a fingerprint binds the reviewed plan.
3. Apply re-plans before staging, copies the original to checksum-verified
   private quarantine, and writes normalized/sanitized output to private
   staging.
4. Coordinator allocates a generated destination, verifies its checksum, then
   locks/rechecks the operation, owners, attachments, and settings snapshots.
5. One short transaction creates the immutable Media identity, creates all
   reviewed attachments, and switches legacy owner paths plus settings
   key/path pairs.
6. Journaled cleanup clears Spatie/derived settings caches before removing the
   old public source, then clears staging and marks complete. A failed cache or
   cleanup step remains repairable; completed reruns are idempotent.
7. If the old path later reappears with new raw references, it is a new plan
   and cannot be hidden by an older completed registration.

### External download

The queued job reloads actor/item and rechecks Admin access. Safe fetcher
requires HTTPS, approved port, public DNS answers, pinned address, revalidates
each bounded redirect, rejects private/reserved/rebinding targets, and reads a
stream with a 2 MB cap. The same validator normalizes raster-only content. The
coordinator creates Media and the attachment manager sets primary image. No
native import fetches remote media.

## Mini-task 1 - Durable docs and centralized policy foundation

Dependencies: none beyond installed source.

Files:

- the two required research files;
- new purpose/role enums and validation value object/classes;
- update `ImageUploadRules`, `ImageFileNamer`, `CuratorPathGenerator`, and
  `config/curator.php` for the finite contract;
- `tests/Unit/CuratorImageUploadPolicyTest.php`;
- `tests/Feature/ImageUploadValidatorTest.php`;
- committed fixtures under `tests/Fixtures/media/`.

Coverage:

- valid JPEG/PNG/WebP/clean SVG;
- all excluded families represented by fixtures/table cases;
- mismatched and executable-renamed files;
- strict image structure/polyglot failures;
- malicious SVG variants;
- size/dimension failures;
- traversal, backslash, encoded, absolute, and sibling-prefix roots;
- generated names and canonical extension.

Exit: focused tests green; research/plan reconciled with actual class names.

## Mini-task 2 - App-owned gallery Resource and uploads

Dependencies: Mini-task 1.

Files:

- `app/Filament/Resources/Media/MediaResource.php`;
- app pages `ListMedia`, `CreateMedia`, `EditMedia`;
- app `MediaForm` and `MediaTable`;
- temporary app Media model wiring as needed before full schema in Mini-task 4;
- update `config/curator.php`, `AdminPanelProvider`, and
  `AdminNavigationOrder`;
- `tests/Feature/AppOwnedMediaResourceTest.php`.

Resource uses 25-only pagination, projected columns, exact metadata fields,
finite-purpose create, max 10 / concurrency 2 uploads, and no rename/swap action
until coordinator support lands. Vendor create/multi-upload/edit pages are not
reachable.

## Mini-task 3 - App-owned picker boundary

Dependencies: Mini-tasks 1-2.

Files:

- rewrite `PathCuratorPicker` as the app ID/reference-key field boundary;
- app modal Blade and `App\Livewire\Admin\MediaPickerPanel`;
- update `MediaPickerField` to require purpose enum;
- authorized app view/download endpoints if needed;
- `tests/Feature/AppOwnedMediaPickerTest.php` and picker compatibility updates.

Coverage includes browse/search/load-more filters, upload, selection, metadata
edit, view/download, delete/bulk delete, forged IDs/state paths/selected arrays,
wrong root/disk/visibility/type, and projected Livewire state.

## Mini-task 4 - Stable identity, attachments, and journal

Dependencies: Mini-tasks 1-3 interfaces.

Files:

- three data-free migrations described above;
- app Media, MediaAttachment, MediaMutationOperation models/factories;
- attachment role and mutation enums;
- relationship methods on ContentGroup/ContentItem;
- morph map and app observer/policy/lease registration in `AppServiceProvider`;
- durable `MediaMutationFence` and coordinator-creation lease enforcement;
- `tests/Feature/MediaAttachmentModelTest.php`;
- journal behavior in `tests/Feature/MediaMutationCoordinatorTest.php`.

Coverage includes key immutability, nullable legacy rows, shared Media, singleton
roles, ordering, duplicate constraints, alias resolution/taggable compatibility,
restrictive delete, journal casts/history, and storage-field lease denial.
Coverage also includes concurrent/open-operation exclusion, stale/foreign/
expired lease denial, and active-mutation attach/detach refusal.

## Mini-task 5 - Backfill, compatibility, settings, import/export, UI writes

Dependencies: Mini-task 4 schema/API.

Commands/classes:

- `media:backfill-reference-keys`;
- `media:backfill-attachments`;
- `media:backfill-settings-reference-keys`;
- `media:report-integrity` using `MediaIntegrityReporter`;
- `StoredMediaValidator` and completed reference-key checksum proofs;
- settings reference resolver and portable settings package output.

Application files:

- ContentGroup/ContentItem create/edit/workspace pages and forms;
- `ContentImageActions`;
- settings form builder, registry defaults, validator, reader/package analyzer,
  overlay asset semantics, and portable export action;
- ContentGroup/ContentItem importers/exporters;
- ZIP manager manifest;
- public resolver, badge, public/admin query services/tables/preview ranking.

ZIP `manifest.json` entries contain Media key, owner key, role, archive name,
validated MIME/extension, and SHA-256. Export reads attachment Media, never
numeric identity or mutable path as portable identity.

Tests:

- `MediaBackfillAndIntegrityReportTest`;
- settings key-first/mismatch/import/export tests;
- native content portable import/export tests;
- ZIP manifest/checksum tests;
- ContentGroup/ContentItem real form/action attachment-write tests;
- public fallback compatibility tests.

## Mini-task 6 - Coordinator mutations, jobs, caches, and performance

Dependencies: Mini-tasks 1-5.

Files:

- coordinator, lease, repair logic, cache invalidator, safe remote fetcher;
- `media:repair-mutations` dry-run plus `--apply`;
- Resource/picker rename/swap/delete actions enabled through coordinator;
- update registration command and external-image job;
- registration plan/planner/reference switcher and exact-path apply flow;
- update curation/cache integration;
- eager loading/projection/aggregate query changes;
- `tests/Feature/MediaMutationCoordinatorTest.php`;
- `tests/Feature/ExternalImageSecurityTest.php`;
- `tests/Feature/MediaRelationshipPerformanceTest.php`;
- `tests/Feature/LegacyMediaRegistrationTest.php`.

Failure tests cover copy failure, DB rollback, crash-after-commit journal state,
missing source, duplicate destination, retry, delete quarantine, stale
curations, Glide/palette/placeholder invalidation, and orphan reporting.
Performance tests count relationship queries for 1/10/50 owners and use the
isolated database for relevant `EXPLAIN QUERY PLAN` assertions.

## Mini-task 7 - Threat matrix, docs, and closeout

Dependencies: all prior mini-tasks.

Complete:

- every accepted threat/integrity/relationship/performance regression;
- all English and Hebrew keys (or document G2 prose ownership while retaining
  complete keys/fallbacks);
- requirement classification sweep;
- updates to current project state and ledger;
- `docs/phase-02/curator-g1-image-library-o2-handoff.md`;
- `docs/phase-02/curator-g1-image-library-production-cutover-runbook.md`;
- `docs/phase-02/curator-g1-existing-svg-runbook.md`.

The handoff includes files, migrations, tests, every command/result, focused
and final gates, Boost/FilamentExamples usage, assumptions, deferred production
work, rollback, export/import cutover, SVG procedure, numbered imperative Local
Front Check steps, and final Git status.

## Requirement-to-test traceability

| Requirement | Primary test target |
|---|---|
| Exact allowlist and canonical extensions | `ImageUploadValidatorTest` |
| Excluded families / mismatch / executable / polyglot | `ImageUploadValidatorTest` datasets |
| Strict SVG and staging | `ImageUploadValidatorTest` + coordinator failure tests |
| Root/path/disk/visibility constraints | policy/scope and picker tests |
| Bounded gallery/picker payloads | Resource and picker tests |
| Forged Livewire/action inputs | `AppOwnedMediaPickerTest` / `AppOwnedMediaResourceTest` |
| Full ability matrix and mixed bulk denial | policy/Resource/picker tests |
| Immutable key and data-free migration | identity tests |
| Shared attachment/singleton/order/duplicate | attachment tests |
| Backfill idempotence/orphan/duplicate report | backfill/report tests |
| Stored-byte normalization and checksum-backed key issuance | backfill/report and coordinator tests |
| Durable mutation fence / stale lease / active-attachment denial | coordinator and attachment tests |
| Exact-path shared-owner/settings registration | `LegacyMediaRegistrationTest` |
| Settings key first and mismatch | settings compatibility tests |
| Portable content/settings import/export | import/export tests |
| ZIP manifest/type/checksum | content image export tests |
| Rename/swap/delete compensation/repair | coordinator/journal tests |
| Remote fetch SSRF/redirect/DNS/size/content | external security tests |
| Query bounds 1/10/50 | relationship performance tests |
| Curations/cache invalidation | mutation coordinator tests |
| Existing disallowed rows excluded/reported | scope/report tests |

## Compatibility and rollback

Application rollback order:

1. disable app write surfaces and restore the preceding release;
2. retain the additive columns/tables while the old release runs; old code
   ignores them and continues reading legacy paths;
3. restore settings/package payload from the pre-cutover backup if application
   compatibility requires it;
4. restore quarantined/copied files only after checksum verification;
5. only after application rollback is stable may the reversible migrations be
   rolled down in reverse order, and only if attachment/journal/reference-key
   data has been exported and explicitly approved for removal.

The app never assumes a migration rollback can undo filesystem effects. The
journal and backup/checksum record are the recovery source.

## Production/deployment-gated work

The local implementation prepares but does not execute:

- deployment and maintenance mode;
- database migration;
- pre-cutover database/storage backup and checksums;
- disallowed/integrity/orphan reports against production;
- media key, attachment, and settings-key backfills;
- exact-path legacy registration apply;
- dual-read verification and UI cutover observation;
- export/import manifest verification;
- mutation repair with `--apply`;
- curation/Glide/public cache invalidation;
- SVG IDs 6 and 7 dry-run/apply/visual verification;
- legacy path retirement or non-null key proof migration.

No partial stage is deployed.

## Quality-gate sequence

Focused tests run after each mini-task. On the final code state:

1. requirements classification sweep;
2. `vendor/bin/pint --test`;
3. `vendor/bin/filacheck`;
4. `npm run build`;
5. full `php artisan test` last and never parallelized/interrupted.

After any file change following a gate, restart at Pint. Do not run
`vendor/bin/filacheck --fix`.

## Commit and handoff strategy

1. Complete all seven mini-tasks in this checkout.
2. Finish final gates in the binding order.
3. Commit code, migrations, tests, research, plan, state, runbooks, and handoff
   with the handoff commit hash marked pending. Use an imperative prefixed
   implementation message.
4. Immediately write the implementation hash into handoff and ledger.
5. Commit that docs-only stamp as
   `docs: backfill CURATOR-G1 O2 hash`.
6. Do not push. Confirm no pending-hash debt and a clean worktree.

## Source reconciliation result

All planned seams exist in the installed versions: complete Resource
configuration, FileUpload manual-store and concurrency hooks, schema upload
restriction, scoped Resource route binding, individual-record bulk
authorization, and Livewire/Filament test APIs. The app model must explicitly
replace inherited vendor observer resolution, and the morph map must preserve
Spatie taggable FQCN behavior. Those constraints are incorporated above.

No material conflict exists. Implementation may proceed directly.

## Post-implementation source reconciliation

The completed seven-mini implementation was reconciled back to the current
source before closeout. The provisional names and nominal flows above now match
the actual classes and schema. Additional implemented outcomes are:

- app-owned `MediaResource`, create/edit/list pages, form, and table replace the
  configured vendor Resource; the vendor `curator-panel` and
  `curator-curation` Livewire aliases resolve to a fail-closed 404 component;
- `CreateMedia`, `ListMedia`, and `MediaPickerPanel` all use Filament's
  upload-to-schema restriction; every final upload still passes through the
  shared content validator and coordinator;
- Media browse/select/mutation scope requires a valid non-null immutable key as
  well as the public disk/visibility, finite root, type, extension, dimensions,
  size, and normalized storage identity;
- authenticated ID-only view/download routes reload through that scope and add
  CSP sandbox plus `nosniff` response headers;
- settings selection paths expand immutable-key/legacy-path pairs together in
  both directions, including nested about blocks and team profiles;
- ContentGroup and ContentItem importer rows use a transaction/savepoint around
  the native importer invocation and attachment callback, so a late attachment
  race rolls back the owner change;
- bulk deletion primes attachment, legacy-path, and settings references once,
  authorizes all records before mutation, and keeps the prime through the
  coordinated deletes;
- measured isolated-test budgets are exactly three relationship queries for
  1/10/50 groups, six for mixed item/group collections, four for about/team
  identities, and no more than six for bulk reference discovery; five relevant
  indexes are SQLite `EXPLAIN QUERY PLAN` verified;
- Curator formats are raster-only (`jpg`, `png`, `webp`), curations remain
  disabled until dimensions are measured, the installed WebP preset quality 60
  remains unchanged, and source-wide regressions keep every Filament
  RichEditor/MarkdownEditor attachment surface disabled;
- `media:register-existing-curator-assets` is dry-run-only by default and has a
  one-exact-path/Admin-actor apply mode. Its registration planner, private
  quarantine/staging, generated destination, checksum journal, locked
  owner/settings switch, cache-before-source cleanup, and idempotent completed
  state implement the full local conversion path. Only executing it against
  staging/production remains separately approval-gated.
- `StoredMediaValidator` and `reference_key_backfill` proof operations prevent
  a metadata-only or fresh lossy re-encode guess from minting/retaining a
  trusted key. Null-key legacy raster is eligible only when already canonical;
  otherwise it uses the registration flow. Existing SVG is always deferred to
  the exact IDs 6/7 runbook.
- `MediaMutationFence` is separate from the observer lease. It owns database
  identity locks, open-operation exclusion, commit state/token checks, cleanup
  ownership, and terminal repair-token rechecks.

No dependency, security-boundary, schema-direction, task-count, or forecast
drift was found. The implementation remains within the approved Option O2.
