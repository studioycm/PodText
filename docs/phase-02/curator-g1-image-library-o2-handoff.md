# CURATOR-G1 O2 Full App-Owned Image Library Handoff

## Status

All seven mini-tasks in the approved Stage 2 option are implemented and
verified locally. Production and the local development database were not
touched.

- Audit ID: `LS-20260720-CURATOR-G1-IMAGE-LIBRARY-01`
- Approved option: `CURATOR-G1-O2-FULL-APP-OWNED-CURATOR-SURFACE`
- Starting cwd and Git root: `/Users/studioycm/Herd/PodText`
- Starting branch: `main`
- Starting HEAD: `7c55dca4012ce48779b32b2e3c4d2076d9198807`
- Starting `main` / `origin/main`: equal
- Starting worktree: clean
- Implementation hash: `fa5b57c8dfa327cdbfd03267c94c6cd21f6a10f0`
- Docs-only hash stamp: this immediate follow-up commit after the
  implementation commit
- Push, PR, branch, worktree, dependency, production, and local-development
  database actions: not performed

This was an ad-hoc Stage 2 task. No prompt under `prompts/pre-13-prompts/` was
active. The approved audit scope, seven-task count, 46–68 hour forecast,
dependency set, schema, and Admin-or-higher security boundary did not
materially drift.

## Outcome

PodText now owns the Curator image-library boundary instead of relying on the
vendor Resource, picker state, or destructive observer flow. One centralized
positive upload policy accepts JPEG, PNG, WebP, and purpose-limited sanitized
SVG only. Raster bytes are decoded, bounded, normalized, and re-encoded; SVG
bytes are staged privately, sanitized, reparsed, and promoted without entering
raster curation logic. Names and paths are server-generated.

The admin Media Resource, picker, file controller, metadata operations,
selection, attachment writes, rename, swap, download, delete, and bulk delete
all reload untrusted IDs through the same app-owned scope and explicit policy
abilities. Browse pages contain 25 projected records, search contains at most
50, and upload surfaces accept at most 10 files with two concurrent transfers.
Full Curator model arrays, public storage identity, and arbitrary client roots
are not placed in Livewire state.

Media rows now have immutable portable reference keys, while numeric Curator
IDs remain the local relational identity. Typed and ordered attachment rows
support shared Media, with singleton cover and primary-image roles enforced per
owner. Settings resolve a reference key first and use a legacy path only as a
compatibility fallback; disagreement fails closed. Content import/export and
the image ZIP manifest use reference keys, owner reference keys, roles,
validated types, archive names, and SHA-256 values.

Every filesystem mutation uses a durable journal and the sequence
copy/verify/short locked commit/cleanup. A database mutation fence prevents
concurrent operations, detects identity changes, validates lease ownership and
expiry, and prevents stale workers from overwriting a replacement lease. The
separate in-process `MediaMutationLease` only permits model creation and
storage-identity writes inside the coordinator; it is not the durable fence.
Repair is idempotent and reports cleanup pending or lease loss honestly.

Existing rows are not deleted or silently trusted. Stored raster bytes must
prove their metadata, canonical normalized content, scope, dimensions, and
checksum before a reference-key backfill. Existing SVG rows remain unkeyed and
excluded until the separate SVG runbook is approved and executed. Legacy
registration is fully implemented as an exact-path, Admin-authorized,
journaled operation, but only isolated tests executed it; production apply is
separately gated.

## Installed source of truth

- Laravel `13.19.0`
- Filament `5.6.7`
- Livewire `4.3.3`
- `awcodes/filament-curator` `5.1.2`
- Installed Curator source commit
  `2a79bf031099d2d75351377eae15322fb590ab43`

Installed vendor source remained authoritative for Curator model/observer,
Glide, Resource, picker, upload, and curation behavior. Official primary-source
URLs, local source line references, Boost results, FilamentExamples results,
and limitations are persisted in
`docs/research/curator-g1/image-library-o2-research.md`.

## Seven mini-tasks

| Mini-task | Classification | Result |
|---|---|---|
| 1. Durable research/plan and centralized policy | Implemented | Persisted the required research and plan before application code; added purposes, roles, allowlist, path normalization, byte-derived validation, generated names, raster normalization, private SVG staging, and fixtures/tests. |
| 2. App-owned gallery Resource and upload surfaces | Implemented | Replaced the top-level vendor Resource with app-owned create/list/edit/table behavior, projected rows, fixed pagination, secure metadata updates, and coordinator-owned uploads. |
| 3. App-owned picker boundary | Implemented | Replaced vendor picker/curation aliases with fail-closed surfaces and added bounded browse/search/load-more, upload, selection, edit, view/download, rename/swap/delete, and mixed-bulk protections. |
| 4. Stable identity and shared attachments | Implemented | Added immutable nullable Media `reference_key`, attachment and operation-journal tables/models, stable morph aliases, typed ordered relationships, singleton constraints, and mutation fencing. Migrations contain no row backfill. |
| 5. Backfill, compatibility, settings, import/export, UI writes | Implemented locally | Added idempotent content-proven backfill and integrity/report commands, settings key backfill, dual-read mismatch protection, attachment writes, public resolution, portable content import/export, and the ZIP manifest. Commands were not run against development or production data. |
| 6. Mutation integrity, jobs, caches, performance | Implemented | Added journaled create/external-import/registration/rename/swap/delete, repair, quarantine, checksum verification, cache/curation invalidation, SSRF-hardened external transport, eager loading/projection, measured SQLite indexes, and bounded query tests. |
| 7. Threat matrix, documentation, and closeout | Implemented locally | Completed English/Hebrew keys, security/integrity/relationship/performance regressions, research/plan reconciliation, state/ledger/track updates, production and SVG runbooks, and canonical gate/commit preparation. |

## Data flows and enforcement points

### Upload and external import

1. Accept only an untrusted temporary upload or a size-bounded, DNS-pinned HTTPS
   response.
2. Derive the finite server-owned purpose and root.
3. Check client extension, content-derived MIME, exact MIME/extension pairing,
   two-megabyte limit, and purpose compatibility.
4. Decode dimensions before full raster normalization and reject dimensions
   above 3000 by 3000; re-encode raster bytes to the canonical extension.
5. Stage SVG privately, reject DTD/entities/scripts/events/`foreignObject`,
   unsafe or external references, executable content, and invalid reparsed
   output.
6. Create a journal, copy generated canonical bytes to a generated public
   destination, verify SHA-256, then commit the Media row under a creation lease
   and database fence.
7. Remove owned staging bytes and invalidate destination Glide/cache identity.

### Browse, search, selection, and attachment

1. Treat every Livewire record ID, selected array, search string, and action
   argument as untrusted.
2. Reload with `MediaRecordScope`, which enforces public disk/visibility,
   normalized direct purpose root, allowed MIME/extension agreement, immutable
   key presence, and field-purpose compatibility.
3. Authorize the exact server-side ability for every record; mixed bulk
   operations fail before mutating any record.
4. Project only the bounded picker fields and batch reference checks.
5. Attach by local Media ID after scope/ability checks; store stable morph
   alias, enum role, and deterministic position. Active filesystem mutations
   block attachment changes.

### Rename, swap, and delete

1. Reload and authorize the trusted Media record; swap fully revalidates the
   replacement.
2. Acquire row locks and reject another incomplete operation or changed storage
   identity.
3. Journal source/destination/quarantine checksums and a leased operation.
4. Copy and verify new/quarantine bytes before the short database commit.
5. Update immutable record identity only under the in-process observer lease;
   the portable reference key does not change.
6. After commit, verify the fence and cleanup lease, invalidate old/new Glide,
   placeholder, palette, and curation identities, then remove the old public
   source when safe.
7. Leave cleanup pending on failure and let exact-operation repair resume
   idempotently. A stale or foreign worker cannot write a terminal status.

### Legacy registration

1. Default to a read-only report and require one exact normalized path plus an
   Admin actor for apply.
2. Fingerprint source checksum, purpose, owner/settings references, and current
   plan; re-plan before staging and again under transaction locks.
3. Preserve the original in checksum-verified private quarantine, normalize in
   private staging, and create a generated destination with a new immutable
   reference key.
4. Atomically switch every reviewed owner attachment, compatible legacy path,
   and settings key/path identity.
5. Clear all Spatie settings caches and derived public-front configuration
   caches before old-source deletion.
6. Remove the old public source only after zero references remain and mark the
   journal complete. Repeated dry runs report `already_registered`; incomplete
   cleanup reports `registration_cleanup_pending`.

## Authorization and security boundary

The existing PodText Admin-or-higher role boundary is preserved. Shield or a
new permission architecture was not introduced. Explicit server-side abilities
exist for `viewAny`, `view`, `create`, `bulkUpload`, metadata `update`,
`delete`, `deleteAny`, `download`, `rename`, `swap`, `select`, `attach`, and
`detach`. UI visibility is supplemental only.

The file controller, Resource, picker actions, form writes, importers,
registration command, coordinator, attachment manager, and observers enforce
their own trusted lookup/authorization/integrity boundary. Wrong disk, private
visibility, sibling/nested/encoded/backslash/traversal roots, mismatched MIME
and extension, missing immutable key, duplicate storage identity, disallowed
types, legacy references, attachments, and active mutations fail closed.

External image download remains raster-only. It permits HTTPS only, rejects
credentials and nonstandard ports, pins each connection to a public DNS answer,
revalidates every redirect, rejects private/reserved/IPv4-mapped/IPv6 ranges,
limits redirects and response bytes, and then uses the same content validator
and mutation coordinator. Tests never perform live HTTP and call
`Http::preventStrayRequests()`.

## Schema and model changes

| File | Schema effect | Rollback |
|---|---|---|
| `database/migrations/2026_07_20_000001_add_reference_key_to_curator_table.php` | Adds nullable unique ULID `curator.reference_key`; no data mutation. | Drops the unique index and column. |
| `database/migrations/2026_07_20_000002_create_media_attachments_table.php` | Adds Media FK, stable morph alias/id, role, position, timestamps, owner/type/role singleton uniqueness, and Media/role lookup index. | Drops the table. |
| `database/migrations/2026_07_20_000003_create_media_mutation_operations_table.php` | Adds operation key, Media/key identity, operation/status/purpose, source/staging/destination/quarantine identities and checksums, actor, context/error, attempts, timestamps, lease token/expiry, and repair/fence indexes. | Drops the table. |
| `database/settings/2026_07_20_000000_add_public_media_reference_keys.php` | Adds nullable reference-key companions to settings image identities while retaining legacy paths. | Removes only the new settings properties. |

`App\Models\Media` extends Curator's Media model. New records receive a ULID
inside the coordinator creation lease and cannot change it. Existing records
are populated only by `media:backfill-reference-keys --apply`, which creates a
completed checksum-proof journal in the same transaction. A later proof-gated
migration may make the key non-null.

`App\Models\MediaAttachment` uses the stable `content_group` and
`content_item` morph aliases. One Media can be attached to multiple owners.
Current singleton roles are `cover` and `primary_image`; position remains for
deterministic ordering, and no owner gallery was invented.

## Requirement classification

| Accepted requirement | Classification | Evidence/result |
|---|---|---|
| Curator remains an image-only library with exact JPEG/PNG/WebP/SVG MIME and extension allowlists | Implemented | Central policy uses positive lists and canonical extensions; excluded families and renamed executables are tested. |
| Fixed roots and purpose narrowing; raster-only group/item and SVG-capable header | Implemented | Enum-owned roots and per-purpose policy cannot widen the global union. |
| Extension, content MIME, agreement, decode/sanitize, normalization, size/dimensions, generated identity, disk/visibility/root checks | Implemented | Central validator and coordinator enforce all ten checks on create and swap. |
| Strict staged SVG sanitation and no raster curation path | Implemented | Private staging, sanitize/reparse, malicious-vector tests, and null raster metadata/curations. |
| Replace or wrap every unsafe Resource/picker/action/file boundary | Implemented | App Resource, picker, controller, coordinator, disabled vendor aliases, and forged-action tests cover the boundary. |
| Untrusted Livewire ID/state and one trusted scope everywhere | Implemented | Locked server-owned properties, scoped reloads, projected state, and forged ID/array/action tests. |
| Browse 25, search 50, upload 10, two concurrent transfers, no full Media arrays | Implemented | Configuration and real-workflow tests assert each limit and projected keys. |
| Keep RichEditor attachment integration disabled | Already existed and preserved | Rich/Markdown editors remain `fileAttachments(false)` and Curator attachment plugin is absent. |
| Preserve Admin-or-higher and implement all named abilities | Implemented | Policy/controller/Resource/picker/manager tests include moderator denial and mixed bulk denial. |
| Report disallowed/missing/orphan/duplicate media without silent deletion | Implemented locally | `media:report-integrity` reports Media ID, disk/path/root, MIME/extension, existence, safe SHA-256, legacy references, attachment references, allowed state, and recommended disposition; no real-data command was run. |
| Numeric local ID plus immutable portable reference key | Implemented | App Media model, unique nullable schema, creation guard, immutable update guard, and content-proof backfill. |
| Shared typed ordered attachments with singleton current roles | Implemented | Attachment table/API/relationships and shared-owner, ordering, duplicate, cardinality tests. |
| Settings key-first dual read, mismatch failure, and path compatibility | Implemented | Settings schema, projector, forms, public readers, exports/import analysis, mismatch tests, and cache invalidation. |
| Portable content import/export and image ZIP manifest | Implemented | Reference-key import/export and owner-role/type/SHA manifest tests; no remote import fetch. |
| Data-free reversible DDL and separate idempotent backfills/reports | Implemented | Four focused migrations plus three backfills, integrity report, registration, and repair commands. |
| Journaled copy/verify/commit/cleanup with compensation and repair | Implemented | Failure, rollback, crash recovery, missing source, collision, retry, cleanup pending, invalid journal, foreign/stale/expired lease tests. |
| Do not rely on Curator destructive observers | Implemented | Vendor observation is disabled for the app model; the app observer denies unleased storage mutation/delete. |
| Registration of compatible legacy rows and references | Implemented locally; production gated | Full exact-path journaled apply is tested, documented, and not executed against real data. |
| Harden `DownloadExternalContentItemImage` against SSRF and reuse policy/coordinator | Implemented | DNS-pinned transport, redirect and range validation, size/content checks, retry semantics, and fixture-backed tests. |
| Eager loading, projected payloads, batch reference checks, measured indexes, and 1/10/50 budgets | Implemented | Relationship/settings/bulk-delete query tests and SQLite `EXPLAIN QUERY PLAN` index assertions pass. |
| Raster-only square WebP candidate at installed quality 60 without invented dimensions | Implemented as configuration foundation | Format/quality are pinned; no dimensions are registered until consumer/DPR measurement. Original/Glider fallback remains. |
| Invalidate old/new Curator, Glide, curation, placeholder, palette, and settings caches | Implemented | Coordinator and registration cleanup plus explicit Glide old/new path regression. |
| Do not publish config or add dependencies/Spatie Media Library | Already satisfied | Existing `config/curator.php` was edited directly; no dependency or publication command ran. |
| Preserve existing SVG IDs 6 and 7 and prepare a separate exact-approval runbook | Implemented as a production gate | Backfill refuses existing SVG; runbook preserves checksum/backup/visual/cache/rollback requirements. No sanitizer ran on those IDs. |
| English and Hebrew keys for user-facing UI | Implemented | Locale flattening reports 1,981 keys in each locale and zero missing keys. |
| Production deployment, migrations, backfills, registration apply, repair, cache mutation, or media disposition | Deferred by contract | Prepared in runbooks; none was executed. |
| Legacy-path retirement and non-null reference-key proof migration | Deferred by transition proof | Compatibility paths remain reversible until production proof. |
| Browser DOM/heap/listener/navigation/modal/TTFB claims | Not applicable | No browser-performance claim is made; PHP, query, Livewire state, and build evidence stay distinct. |
| Blocked requirement | Not applicable | None. |

## Files changed

### New app-owned Curator foundation

- Commands: `BackfillMediaAttachments.php`, `BackfillMediaReferenceKeys.php`,
  `BackfillSettingsMediaReferenceKeys.php`, `RepairMediaMutations.php`,
  `ReportMediaIntegrity.php` under `app/Console/Commands/`; and the expanded
  `RegisterExistingCuratorMedia.php`.
- Enums: `ImageUploadPurpose.php`, `MediaAttachmentRole.php`,
  `MediaMutationOperationType.php`, and `MediaMutationStatus.php`.
- Models: `Media.php`, `MediaAttachment.php`, and
  `MediaMutationOperation.php`.
- Resource: `app/Filament/Resources/Media/MediaResource.php`, its
  `CreateMedia.php`, `EditMedia.php`, and `ListMedia.php` pages,
  `Schemas/MediaForm.php`, and `Tables/MediaTable.php`.
- Picker/file boundary: `app/Livewire/Admin/MediaPickerPanel.php`,
  `DisabledVendorCuratorSurface.php`,
  `app/Http/Controllers/AdminMediaFileController.php`,
  `resources/views/livewire/admin/media-picker-panel.blade.php`, and
  `resources/views/filament/forms/components/media-picker-modal.blade.php`.
- Media support: `CuratorImageUploadPolicy.php`, `ExternalImageDnsResolver.php`,
  `ImageUploadValidator.php`, `LegacyMediaReferenceSwitcher.php`,
  `LegacyMediaRegistrationPlan.php`, `LegacyMediaRegistrationPlanner.php`,
  `MediaAttachmentFormState.php`, `MediaAttachmentIdentityResolver.php`,
  `MediaAttachmentManager.php`, `MediaCacheInvalidator.php`,
  `MediaFilesystemMutationCoordinator.php`, `MediaIdentityResolver.php`,
  `MediaIntegrityReporter.php`, `MediaMutationFence.php`,
  `MediaMutationLease.php`, `MediaRecordProjector.php`, `MediaRecordScope.php`,
  `PinnedExternalImageTransport.php`, `SafeExternalImageFetcher.php`,
  `StoredMediaValidator.php`, `SvgUploadSanitizer.php`, and
  `ValidatedImage.php` under `app/Support/Media/`.
- Settings support:
  `app/Support/SettingsLifecycle/SettingsMediaIdentityProjector.php`.
- Factories: `MediaFactory.php`, `MediaAttachmentFactory.php`, and
  `MediaMutationOperationFactory.php`.
- Migrations: the four files listed in the schema section.

### Updated application integration

- Existing media support: `ContentImagesExportManager.php`,
  `CuratorPathGenerator.php`, `ImageFileNamer.php`, `ImageUploadRules.php`, and
  `MediaReferenceFinder.php`.
- Filament actions/forms/import/export:
  `app/Filament/Actions/ContentImageActions.php`,
  `ExportPublicSettingsAction.php`, `ContentGroupExporter.php`,
  `ContentItemExporter.php`, `ContentGroupImporter.php`,
  `ContentItemImporter.php`, `PathCuratorPicker.php`, `MediaPickerField.php`,
  `app/Filament/Pages/SpotifyLinksFetcher.php`, and
  `resources/views/filament/forms/components/path-curator-picker.blade.php`.
- Group/item admin writes and queries: the ContentGroup create/edit/form/table
  files, `ContentItemsRelationManager.php`, and the ContentItem
  create/edit/form/table files under `app/Filament/Resources/`.
- Settings and public UI:
  `BuildsPublicContentSettingsSubjectSchemas.php`, `AboutPage.php`,
  `ShowContentItem.php`, `PublicContentItemQueries.php`,
  `PublicAboutPageRenderer.php`, both public card presenters,
  `PublicContentGroupQueries.php`, `PublicMenuConfigReader.php`,
  `PublicDefaultImageResolver.php`, `PublicFrontConfigRegistry.php`,
  `PublicFrontConfigValidator.php`, and `CardTemplatePreviewer.php`.
- Settings lifecycle: `PublicSettingsPackage.php`, `SettingsBackupManager.php`,
  `SettingsPackageImportAnalyzer.php`, and
  `SettingsPackageUpgradePipeline.php`.
- Models/observers/policy/providers: `ContentGroup.php`, `ContentItem.php`,
  `ContentGroupObserver.php`, `ContentItemObserver.php`,
  `CuratorMediaObserver.php`, `CuratorMediaPolicy.php`, `AppServiceProvider.php`,
  `AdminPanelProvider.php`, and `AdminNavigationOrder.php`.
- External image job: `app/Jobs/DownloadExternalContentItemImage.php`.
- Configuration/routing/locales: `config/curator.php`, `routes/web.php`,
  `lang/en/admin.php`, and `lang/he/admin.php`.
- Public Blade integration: `profile-card.blade.php`,
  `content-group-badge.blade.php`, `content-item-card-part.blade.php`, and
  `filament/public/pages/about-page.blade.php` under `resources/views/`.

### Tests and committed fixtures

- New: `tests/Unit/CuratorImageUploadPolicyTest.php`.
- New Feature tests: `AppOwnedMediaPickerTest.php`,
  `AppOwnedMediaResourceTest.php`, `ExternalImageSecurityTest.php`,
  `ImageUploadValidatorTest.php`, `LegacyMediaRegistrationTest.php`,
  `MediaAttachmentModelTest.php`, `MediaBackfillAndIntegrityReportTest.php`,
  `MediaMutationCoordinatorTest.php`, `MediaRecordScopeAndAuthorizationTest.php`,
  and `MediaRelationshipPerformanceTest.php`.
- Updated Feature tests: `AdminPhase02ResourcesTest.php`,
  `AdminResourcesTest.php`, `CardTemplatePreviewerTest.php`,
  `ContentImagesExportTest.php`,
  `EpisodeWorkspaceTest.php`, `ImageMediaCuratorTest.php`,
  `ImportExportTest.php`, `PublicAboutPageContentTeamTest.php`,
  `PublicDefaultImagesSettingsTest.php`,
  and `PublicStep9RMenuHeaderUxFixesTest.php`.
- Fixtures: `tests/Fixtures/media/clean.svg`, `malicious.svg`,
  `valid.jpg.base64`, `valid.png.base64`, and `valid.webp.base64`.

### Durable documentation

- `docs/research/curator-g1/image-library-o2-research.md`
- `docs/research/curator-g1/image-library-o2-implementation-plan.md`
- `docs/phase-02/curator-g1-image-library-o2-handoff.md`
- `docs/phase-02/curator-g1-image-library-production-cutover-runbook.md`
- `docs/phase-02/curator-g1-existing-svg-runbook.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/images-media-track-plan.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

No Composer/npm manifest or lockfile changed.

## Test matrix and traceability

- Upload policy/validator: accepted JPEG, PNG, WebP, clean SVG; excluded PHP,
  script, shell, JS, JSON, HTML/CSS/XML, documents/PDF/DOCX/Markdown/text,
  audio/video/archive/binary/Flash/font/GIF/BMP/TIFF/AVIF/ICO families;
  MIME/extension mismatch, renamed executable, polyglot, malformed raster,
  malicious SVG vectors, over-size and over-dimension input.
- Root/scope: traversal, backslash, encoded traversal, nested and sibling-prefix
  bypasses; wrong disk, visibility, root, type, extension, duplicate identity,
  missing key, and purpose mismatch.
- Filament/Livewire surfaces: real Resource and picker create/edit/actions;
  25-row Resource/browse, 50-result search, load-more, projected payload, 10
  files, two concurrent transfers; forged locked purpose, selected IDs, action
  IDs, metadata identity, download/view/delete/rename/swap, and mixed bulk.
- Authorization: guest/moderator denial; all named abilities; per-record bulk
  authorization; referenced/attached mutation denial.
- Relationships: shared Media across owners, singleton cover/primary roles,
  ordered relationships, duplicate/cardinality constraints, stable morph maps,
  active-mutation attachment denial.
- Identity/backfill/report: guarded creation, immutable key, idempotent
  checksum-proof key/attachment/settings backfills, invalid/noncanonical bytes,
  existing SVG refusal, orphan/duplicate/missing/disallowed reporting, and exact
  status output.
- Settings/import/export: key-first resolution, path fallback, mismatch failure,
  form writes, public resolvers, nested-object settings migration preservation
  and rollback, portable group/item import/export, formula safety retained, ZIP
  manifest identities and SHA-256, and no remote import.
- Mutation/recovery: upload/external import/registration/rename/swap/delete,
  private staging/quarantine, copy failure, invalid swap preservation, database
  rollback, missing source, destination collision/retry/exhaustion, stale
  curations, old/new Glide/cache invalidation, invalid journal enums, crash
  recovery, cleanup pending, recovered-commit failure, and stale/foreign/expired
  lease fencing.
- Registration: default report, exact-path/Admin requirement, shared-owner
  atomic apply/idempotency, settings switch with enabled cache, reused path,
  locked settings and failure compensation.
- External image security: HTTPS/scheme/userinfo/port, public DNS pinning,
  redirects, private/reserved IPv4/IPv6 and DNS rebinding, response-size/content
  validation, retry behavior, and `Http::preventStrayRequests()`.
- Performance: eager-loaded attachment identity for 1/10/50 owners; batched
  about/team settings identities for 1/10/50; bounded bulk reference queries for
  1/10/50 deletes; picker projection and SQLite index-plan assertions.

All HTTP-touching tests call `Http::preventStrayRequests()`. Storage fakes and
the isolated test database own all test state. No test accesses the local
development database.

## Performance evidence

- ContentGroup cover resolution stays at three queries for 1, 10, and 50
  owners.
- ContentItem primary image plus group cover resolution stays at six queries
  for 1, 10, and 50 owners.
- About/team settings identity resolution stays at four Curator queries for 1,
  10, and 50 records.
- Bulk delete reference discovery remains within six relevant queries for 1,
  10, and 50 records. An iteration failure of 24/204/1004 queries exposed that
  the batch cache was cleared too early; the final code retains it through the
  mutation loop and the three budgets pass.
- Picker state contains nine projected fields per row, not `Media::toArray()`.
- SQLite `EXPLAIN QUERY PLAN` proves the attachment owner/role,
  Media/role, Curator reference-key, and mutation status/media indexes are used.
- Resource/browse page size is 25, search is capped at 50, and upload
  configuration is 10 files/two transfers.

These are PHP/query/Livewire-state measurements. No browser DOM, heap,
listener, navigation, modal, or TTFB result is claimed.

## Laravel Boost and FilamentExamples

Laravel Boost was used for installed-version application information and
version-aware Laravel/Filament/Livewire/Pest guidance. Its database-schema and
query tools were deliberately not used because the local development database
was off-limits; migration/source inspection supplied schema evidence.
Installed source resolved exact Curator and Filament behavior when documentation
was general or silent.

The required FilamentExamples protocol used multiple short query batches,
followed by refined searches for Resource uploads, tables/actions, picker/modal
patterns, schema-restricted uploads, metadata, and pagination/state. Results
provided search names/snippets only; no source/read/detail endpoint was
available. No result concerning Spatie Media Library was treated as Curator
evidence. The research document records patterns adopted, rejected, and adapted.

## Commands and results

### Preflight and orientation

- `pwd` and `git rev-parse --show-toplevel`: both returned
  `/Users/studioycm/Herd/PodText`.
- `git status --short --branch`: clean `main...origin/main` at kickoff.
- `git rev-parse HEAD` and `git rev-parse origin/main`: both returned
  `7c55dca4012ce48779b32b2e3c4d2076d9198807`.
- `git rev-list --left-right --count origin/main...main`: `0 0`.
- Read-only Git history/file discovery identified and fully read the newest two
  handoffs; `AGENTS.md`, the full lessons file, state, ledger head, complete
  Simplifier package/references, relevant Laravel/Filament/Livewire/security/
  form/performance/Pest/PHP skills, installed package manifests, installed
  Curator/Filament source, nearby application code, tests, and Stage 1 evidence
  were read before edits.
- Read-only prompt search confirmed no kickoff prompt under
  `prompts/pre-13-prompts/` was active.
- Installed-source version/hash reads returned Laravel 13.19.0, Filament 5.6.7,
  Livewire 4.3.3, Curator 5.1.2, and source `2a79bf...` without running Composer
  or npm dependency commands.
- Laravel Boost and FilamentExamples searches completed with the limitations
  recorded above and in research.
- Repository-wide `rg`, `sed`, `git show`, `git log`, `find`, and source reads
  inventoried legacy paths/settings, policies, observers, resources, picker,
  public resolution, import/export, schema, tests, and vendor behavior. They
  were read-only and found no overlapping user-owned work.

### Focused implementation runs

- Validator/external group: first run had 49 of 50 tests pass because
  `2001:db8::/32` was not rejected; after adding the documentation range it
  passed, 50 tests / 185 assertions.
- Expanded external security group: first run had 14 of 15 pass because
  `4000::/3` was not rejected; after the explicit global-unicast bound it
  passed, 15 tests / 220 assertions.
- Backfill/report group: first new status-output assertion failed; after exact
  output reconciliation it passed, 8 tests / 74 assertions, and the later
  checksum-proof/noncanonical additions passed, 9 tests / 83 assertions.
- Registration group: first run had 2 of 3 pass because the operation enum did
  not yet include registration; after adding it the group passed, 3 tests / 41
  assertions.
- Attachment/coordinator group: one transaction-harness expectation failed;
  after using the real boundary the group passed, 18 tests / 117 assertions.
- Broad affected compatibility group: passed, 174 tests / 1,603 assertions.
- Selected hardening group: passed, 65 tests / 298 assertions.
- Security-focused group: passed first at 81 tests / 466 assertions and after
  recovery/creation additions at 82 tests / 476 assertions.
- Surface-limit/Glide/direct-factory regression group: first run had 54 of 55
  pass because `assertCountTableRecords()` measures total rows, not the page;
  the real paginator assertion replaced it and the group passed, 55 tests /
  617 assertions.
- Consolidated Curator/compatibility group: first final run had 269 of 272 pass
  and exposed bulk-reference query counts 24/204/1004 for 1/10/50 deletes.
  Keeping the prime through the deletion loop fixed the defect.
- Performance/coordinator/Resource/picker rerun: passed, 44 tests / 638
  assertions.
- Final consolidated Curator/compatibility rerun: passed, 272 tests / 2,589
  assertions.
- Preliminary Pint test: failed formatting-only checks across 35 changed PHP
  files. `vendor/bin/pint` applied the repository formatter; a subsequent
  formatter run passed without changes.
- Preliminary FilaCheck: failed with five actionable issues: the Media table
  lacked a bounded MIME filter, one test used a deprecated bulk-action helper,
  and three cast enums lacked translated `HasLabel` contracts. After the
  focused corrections, `vendor/bin/filacheck` passed with zero issues; no
  `--fix` command ran.
- Focused Resource/enum/mutation rerun after FilaCheck corrections: passed, 34
  tests / 386 assertions.
- Preliminary `npm run build`: passed with Vite 8.1.0.
- First preliminary full `php artisan test`: failed after 924 of 952 tests and
  11,160 assertions, with 27 failures, one error, and 20 risky browser cases.
  Five application causes were isolated: one options-only eager-load query,
  one obsolete out-of-root fixture, a missing virtual Spotify CSV key, an
  obsolete pre-G1 form-upload test, and nested `stdClass` loss in the settings
  migration. The same run also hit macOS Chromium
  `MachPortRendezvousServer ... Permission denied`, which cascaded through the
  browser files.
- Focused regression rerun after those five fixes: passed, 71 tests / 899
  assertions. The settings migration test proves nested logo/about/team/default
  values survive both `up()` and `down()`.
- Final focused Curator/compatibility rerun after all corrections: passed, 273
  tests / 2,637 assertions.
- The identical full command was retried with the permitted runner solely
  because Chromium could not acquire its macOS Mach port in the sandbox. It
  passed, 953 tests / 13,148 assertions.
- `git diff --check`: passed before handoff drafting.
- PHP syntax check over every changed/untracked PHP file: passed with no syntax
  errors.
- Locale key flattening after final enum labels:
  `en=1981 he=1981 missing_he=0 missing_en=0`.

### Canonical final gates

The following no-edit sequence was run after this command record was written
and immediately before the implementation commit:

- Requirements classification sweep: passed; required files, commands, routes,
  abilities, scope, limits, migrations, translations, traceability, runbooks,
  no-dependency diff, and production deferrals were present and reconciled.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed with zero issues.
- `npm run build`: passed with Vite 8.1.0.
- Full `php artisan test` last, serial and uninterrupted with the permitted
  Chromium runner: passed, 953 tests / 13,148 assertions.

No `filacheck --fix`, dependency update, `vendor:publish`, development-database
command, production export/import/sanitizer/migration/backfill/repair/deploy,
registration apply, or cache mutation command was run.

## Assumptions and scoped decisions

- The installed Curator numeric primary key remains the local FK; portable
  boundaries use ULID reference keys.
- A completed checksum-proof journal is the evidence that a keyed existing row
  passed stored-content validation. Existing SVG is deliberately excluded from
  automatic key backfill.
- The fixed current owner roles are singleton roles; `position` is retained for
  deterministic typed ordering and a future concrete consumer, not an invented
  owner gallery.
- The global Curator configuration is the maximum union. Each purpose may only
  narrow it.
- Installed Curator quality 60 and a square WebP candidate are retained, but
  dimensions remain unregistered until real consumer width/DPR measurement.
- Registration is the exceptional legacy conversion writer. Normal UI/import
  attachment writes go through `MediaAttachmentManager`.
- Missing source during delete can be journaled and resolved, but unknown or
  checksum-mismatched artifacts are never deleted automatically.

## Production and deployment gates

The following remain separately approved production operations:

1. deploy the canonical two commits and verify the target release/topology;
2. back up the database, public media, private staging/quarantine, and settings;
3. run the four migrations;
4. run dry reports for integrity, key/attachment/settings backfills, and legacy
   registration;
5. review and separately approve exact-path registration applies, including
   environment, path, SHA-256, purpose, references, and Admin actor;
6. execute idempotent backfills and registration during one maintenance window;
7. verify owners, settings identities, manifests, public/admin visuals, queues,
   Glide/curations/caches, reports, and cleanup journals;
8. separately approve any exact-operation repair, disallowed-media quarantine
   or deletion, or SVG IDs 6/7 sanitation;
9. retain compatibility paths until proof allows their retirement and a later
   non-null key migration; and
10. measure consumer widths/DPR before registering curation dimensions.

Do not deploy partial stages. The exact procedure and approval text are in
`docs/phase-02/curator-g1-image-library-production-cutover-runbook.md`.
Existing header SVG IDs 6 and 7 use
`docs/phase-02/curator-g1-existing-svg-runbook.md`; this implementation did not
sanitize, rewrite, or delete them.

## Rollback

- Before production apply, roll back the additive migrations in reverse order
  only after proving no new key/attachment/journal/settings identity is needed.
- After registration or mutation commit, preserve the journal. Restore the old
  source only from checksum-verified quarantine/backup, restore owner/settings
  compatibility identities transactionally, clear settings/public/Glide
  caches, and separately approve disposition of the generated Media row and
  destination.
- Do not delete a journal, quarantine artifact, generated destination, or
  disallowed row merely to make a report green.
- Application rollback may revert the implementation commit only while the
  compatibility fields and schema remain available. Do not drop schema before
  the rollback release no longer reads it.
- No rollback command was needed or executed locally.

## Local Front Check Report

1. Start the local application with its normal isolated development workflow.
2. Sign in as an Admin and open the Media navigation item.
3. Confirm the table shows at most 25 rows per page and offers no unbounded
   page-size option.
4. Open Create Media, select each finite purpose, and confirm the accepted-type
   help and file chooser narrow to that purpose.
5. Upload a committed JPEG, PNG, and WebP fixture; confirm generated filenames,
   the fixed purpose root, thumbnails, and metadata editing.
6. Upload the clean SVG fixture for Header/Logo and confirm it renders; attempt
   SVG for a raster-only purpose and expect rejection.
7. Attempt a GIF, renamed PHP file, MIME/extension mismatch, over-two-megabyte
   file, and over-3000-pixel image; expect validation errors and no Media row.
8. Open a ContentGroup cover picker, browse and search, load the next page, and
   confirm only compatible cover images appear.
9. Select one image, save the ContentGroup, reopen it, and confirm the same
   image resolves from its attachment/reference key while the legacy path stays
   compatible.
10. Attach the same Media to a second ContentGroup and confirm both owners show
    it without duplicating the Media row.
11. Open a ContentItem workspace, choose a primary image, save, and confirm its
    public card/item fallback resolves correctly.
12. Rename and swap an unreferenced raster image; confirm the Media reference
    key remains unchanged, the new image appears, and the old public URL/cache
    no longer serves stale bytes.
13. Attempt rename, swap, and delete on a referenced or attached image; expect a
    denial naming its references and no partial change.
14. Select a deletable and a referenced image together for bulk delete; expect
    the entire mixed action to fail without deleting either row.
15. Open public About, header/logo, default-image, group-card, item-card, and
    item pages in Hebrew RTL and English LTR; confirm images and fallbacks render
    without exposing an untrusted disk/path control.
16. Export ContentGroups and ContentItems and inspect the image ZIP manifest;
    confirm portable reference keys, owner reference key/role, archive filename,
    validated type, and SHA-256 are present instead of numeric IDs or mutable
    paths as portable identity.
17. Sign in as a Moderator and confirm the Media Resource, picker upload, file
    view/download, and mutation routes are inaccessible.
18. Do not run production migration, backfill, registration, repair, sanitizer,
    or cache commands during this local front check.

## Commit and final Git status

The canonical closeout is:

1. one implementation commit containing code, tests, research, plan, runbooks,
   state, ledger, and this handoff with the implementation hash pending;
2. one immediate docs-only commit stamping the full implementation hash in
   this handoff and the ledger; and
3. no push, with `main` clean afterward.

Final Git status after this docs-only stamp: `## main...origin/main [ahead 2]`
with no changed paths. No push was performed.
