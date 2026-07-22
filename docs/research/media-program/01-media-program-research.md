# PodText Media Asset Program Master Research

## Decision record

- Audit: `LS-20260722-PODTEXT-MEDIA-ASSET-PROGRAM-06`
- Approved option:
  `MEDIA-CUTOVER-O1-DIRECT-ASSET-HYBRID-ROOT-MAINTENANCE`
- Current status: Stage 2 documentation Gate 0.
- Requirements authority:
  `00-media-program-requirements-decisions-and-method.md`.

## Preflight and current installed source

Stage 2 began in `/Users/studioycm/Herd/PodText` on clean `main` at
`5ba687eff92878f18e9e19e807944a2d39b63372`, 11 commits ahead of
`origin/main`. G1, LMTC, the dependency refresh, and the two settings-metrics
retirement tasks are preserved. No overlapping media work was present. No
prompt under `prompts/pre-13-prompts/` is active.

Laravel Boost reported:

- PHP 8.4;
- Laravel 13.21.1;
- Filament 5.7.1;
- Livewire 4.3.3;
- Pest 4.7.5;
- MySQL development database.

Composer-installed source confirms Curator 5.1.2. No dependency was installed
or updated during research.

## Evidence method and tool limitations

Research reconciled:

- current application source, migrations, tests, configuration and routes;
- installed Laravel, Filament, Livewire and Curator source;
- Laravel Boost installed-version documentation and schema inspection;
- the G1 and LMTC research, plans, handoffs and runbooks;
- historical images/import and Spotify research;
- the verified local incident and production snapshots;
- the operator's settled product and UX decisions.

FilamentExamples was searched in an initial multi-query pass and a refined pass
for gallery Resources, image columns, detail actions, modal table selection,
ListRecords tabs, and custom columns. It returned broad Filament v4 snippets,
not installed-version Filament 5 source, and exposed no source/detail reader.
Those results are neighboring ideas only. All API choices must be proven by
Filament 5.7.1 documentation or installed source.

## Correct baseline finding

The active state document was stale: the local incident did apply the four G1
media migrations and dormant permission migration in batch 8. Fresh Boost
schema inspection confirms the local G1 columns/tables/FKs. Production remains
pre-G1 in the dated snapshot. The exact profiles and counts are in
`03-local-production-baselines.md`.

This matters because one migration/converter path must handle:

- local `post_g1`: G1 schema already present and legacy data unconverted;
- production `pre_g1`: original Curator schema, then exact G1 allowlist, then
  MediaAsset schema and conversion.

## Current Curator-owned seams

### Provider model and vendor containment

`app/Models/Media.php` extends Curator's Media model. It:

- disables the inherited observer list;
- permits creation only under `MediaMutationLease` or a test-only bypass;
- creates a ULID reference key;
- prevents changing an issued key.

This is a good provider containment seam. Curator's vendor observer directly
moves/renames/deletes files, while PodText already routes mutations through
`MediaFilesystemMutationCoordinator`. The provider adapter must keep the vendor
observer disabled.

### Current trust boundary

`MediaRecordScope` currently requires:

- public disk and public visibility;
- non-null syntactically valid Curator key;
- current size/dimension limits;
- exact MIME/canonical extension metadata;
- one of six purpose roots;
- path/directory/name equality;
- root/purpose equality.

It does not prove current bytes for each ordinary read; it trusts the Curator
row after the prior proof boundary. A Needs Repair Curator row could become
browseable if it received a non-null key and its metadata looked valid. The
asset cutover therefore cannot merely generate keys or wrap this scope. It must
make `MediaAsset.validation_status` and `lifecycle_status` authoritative before
unsafe Curator keys are mirrored.

### Owner relationships and compatibility paths

Current shared ownership is:

- `ContentGroup::coverMediaAttachment()` plus `cover_path`;
- `ContentItem::primaryImageMediaAttachment()` plus `image_path`;
- `media_attachments.media_id -> curator.id`;
- singleton uniqueness on `(attachable_type, attachable_id, role)`.

`MediaAttachmentManager` locks the owner, old/new Curator rows and attachment,
checks the mutation fence, authorizes attach/detach, and writes attachment plus
legacy path in one transaction. That concurrency behavior should be retained
while dual-writing a nullable `media_asset_id` bridge.

### Unsafe owner behavior

LMTC already established the correct failure UX foundation:

- `MediaAttachmentIdentityResolver` throws a typed
  `UnsafeLegacyOwnerMediaException` for missing, disallowed, mismatched, or
  duplicate legacy identity;
- `MediaAttachmentFormState` catches only that typed state for safe mount and
  preserves unrelated form saves;
- `LegacyOwnerMediaRepairer` performs fingerprinted replace or detach;
- public/default resolvers treat unsafe media as absent and use fallback;
- list projections avoid filesystem work per row.

`UnsafeLegacyOwnerMediaException` extends `InvalidArgumentException`, matching
the existing Menu/About catch contract. Preserve this behavior when the asset
resolver replaces the Curator resolver.

### Settings and portable identity

`SettingsMediaIdentityProjector`, public default-image resolution, Menu logos,
About/team rendering, and settings lifecycle paths keep paired path and media
reference-key values. ContentGroup/ContentItem importers/exporters use Curator
keys through `MediaRecordScope` and `MediaAttachmentIdentityResolver`.

The new key semantics can remain compatible if the MediaAsset key is the one
portable key and Curator mirrors it. No second settings schema is needed.

### Mutation state machine

Reusable implementation evidence includes:

- `LegacyMediaTransitionManifest` deterministic serialization/digest;
- `LegacyMediaTransitionPlanner` schema/reference/file classifications;
- `LegacyMediaTransitionExecutor` exact candidate execution;
- `MediaFilesystemMutationCoordinator` copy/verify/commit/cleanup;
- `MediaMutationFence` and `MediaMutationLease`;
- `LegacyMediaReferenceSwitcher` atomic owner/settings switching;
- `StoredMediaValidator`, `ImageUploadValidator`, `SvgUploadSanitizer`;
- `SafeExternalImageFetcher` and `PinnedExternalImageTransport`;
- `MediaReferenceFinder` and `MediaIntegrityReporter`.

These algorithms are proven but Curator-typed. Authority should move to
MediaAsset rather than adding another Curator-only wrapper.

## Why the MediaAsset kernel is the bounded choice

### Benefit over remaining Curator-only

An app-owned asset separates:

- portable identity from Curator numeric identity;
- trust/lifecycle from provider metadata;
- logical organization from physical path;
- owner relationships from provider FKs;
- acquisition policy from Curator's uploader;
- future provider choice from current model inheritance.

Curator remains useful for its installed provider row/model and compatibility,
but it no longer decides which asset is trusted, selectable, attached, or
portable.

### Spatie Media Library assessment

Spatie Media Library and Filament's Spatie plugin offer mature model-attached
collections, conversions, responsive images, custom properties, and Filament
fields/columns. They would require a second media table/identity and a
model-centric attachment architecture that conflicts with the settled shared
asset library, Curator-row preservation, immutable portable key, existing
mutation journal, settings identities, and provider abstraction.

Fitting both would require:

- publishing/running Spatie's schema;
- adding `InteractsWithMedia` and collection semantics to owners;
- reconciling Spatie numeric media IDs with Curator IDs and asset keys;
- replacing or duplicating `media_attachments` and journal relationships;
- deciding which package owns file deletion/conversions;
- a new dependency/security/data migration audit.

The package benefits do not justify that duplication. A minimal MediaAsset
kernel reuses PodText's already-proven validator/journal and keeps future
providers possible without adopting Spatie now.

## Target schema

No dependency is required. New schema is genuinely required.

### `media_folders`

- `id`: unsigned big integer primary key.
- `system_key`: nullable string(64), unique; a non-null value marks a protected
  system folder.
- `name`: nullable string(255); required for custom folders, ignored for the
  translated system label.
- `position`: unsigned integer, default 0.
- `is_visible`: boolean, default true; affects normal gallery organization,
  never integrity/security visibility.
- timestamps.

Six flat protected keys:

- `podcast_covers`
- `episode_images`
- `headers_logos`
- `defaults`
- `about_team`
- `legacy_library`

No `parent_id` is approved. Deleting a custom folder moves its assets to
Legacy library in one transaction.

### `media_assets`

- `id`: unsigned big integer primary key.
- `reference_key`: char(26), non-null, immutable, unique.
- `media_folder_id`: non-null FK to `media_folders`, RESTRICT on delete. Every
  asset, including Needs Repair, has exactly one logical folder; the migration
  creates the six system folders before the asset table can receive rows.
- `validation_status`: string(32): `trusted` or `needs_repair`.
- `lifecycle_status`: string(32): `active` or `trashed`.
- `disk`: nullable string(255).
- `path`: nullable text.
- `storage_identity_hash`: nullable char(64), unique hash of exact
  `disk + NUL + path`; code compares exact values after a hash match.
- `visibility`: nullable string(32).
- `mime_type`: nullable string(255).
- `extension`: nullable string(16).
- `width`, `height`: nullable unsigned integers.
- `size`: nullable unsigned big integer.
- `sha256`: nullable char(64), deliberately not unique.
- `original_filename`, `cleaned_filename`: nullable string(255).
- `source_type`: string(32), e.g. `curator_conversion`, `upload`, `url`, or
  `storage_import`. Spotify is recorded as origin/provenance on a `url`
  acquisition, never as a separate acquisition mechanism.
- `source_provenance`: nullable JSON.
- `normalization_version`: nullable string(64).
- `repair_reason`: nullable string(64).
- `repair_context`: nullable JSON.
- `trusted_at`, `trashed_at`, `purge_after`: nullable timestamps.
- timestamps.

Indexes:

- `(validation_status, lifecycle_status, id)`;
- `(media_folder_id, validation_status, lifecycle_status, id)`;
- `(sha256, id)`;
- `(purge_after, id)`.

A trusted asset requires complete canonical storage/checksum fields. A Needs
Repair asset keeps recorded provider path/metadata in provider snapshot and
provenance, while canonical `disk`, `path`, and storage hash remain null unless
bytes have passed proof. This makes accidental raw URL projection harder.

### `media_provider_bindings`

- `id`: unsigned big integer primary key.
- `media_asset_id`: non-null FK to `media_assets`, RESTRICT on delete.
- `provider`: string(32), initially `curator`.
- `provider_record_key`: string(191), the exact Curator numeric ID rendered as
  a string.
- `provider_snapshot`: nullable JSON with the reviewed provider metadata.
- timestamps.
- unique `(provider, provider_record_key)`.
- unique `(media_asset_id, provider)`.

Do not unique the asset checksum. One Curator row always means one asset.

### `media_attachments` bridge

Add nullable `media_asset_id` with FK to `media_assets`, RESTRICT on delete, and
index `(media_asset_id, role)`. Retain `media_id -> curator.id` and the existing
owner-role unique constraint through all five packages. Managers dual-write
both identities. Closure, not an unsafe immediate NOT NULL change, proves the
bridge.

### `media_mutation_operations` bridge

Add:

- nullable `media_asset_id` FK to assets, SET NULL on delete;
- nullable `media_asset_reference_key` char(26);
- indexes `(media_asset_id, status)` and
  `(media_asset_reference_key, status)`.

Keep every Curator ID/reference/path/checksum snapshot and existing journal
column. Add operation enum values only as packages introduce conversion,
repair, move, trash, restore, and purge.

### No new owner/settings columns

`media_attachments` remains the shared relationship. `cover_path`,
`image_path`, settings paths, settings reference keys, Curator keys, and
journal snapshots remain compatibility fields. Their removal is a later audit.

## Schema and deployment sequence

1. Exact G1 relational/settings migrations must exist first. Local already has
   them; future production does not.
2. Kernel migration A creates folders/assets/bindings and deterministically
   upserts the six system folders before assets can exist. Compatibility
   migration B adds nullable bridge/journal fields. Both are independently
   reversible and neither converts a Curator row.
3. Dual-schema code can read legacy state before conversion and writes new
   state only through the controlled asset/provider service.
4. A deterministic preflight creates the environment-specific manifest and
   digest.
5. A separately approved full-maintenance apply performs all row conversion.
6. Asset-first authority and bridge closure are proven before ordinary writes
   resume.

The exact production allowlist belongs in
`docs/phase-02/media-asset-production-cutover-runbook.md` after implementation.
Reusable SVG operation belongs in
`docs/phase-02/media-asset-svg-sanitation-runbook.md`. Broad `migrate --force`
and broad rollback remain forbidden.

## Full Curator-row conversion

### Manifest

Every Curator row produces one stable entry containing:

- schema profile and schema fingerprint;
- Curator ID, reference key and all provider metadata;
- exact source disk/path, existence, file type, dimensions, size and SHA-256;
- all owner paths, current attachments, settings path/key references and
  portable identity references;
- duplicate path/checksum group information without dedupe;
- open/incomplete journal state;
- selected logical folder and hybrid physical root;
- exact disposition and executable action;
- filesystem-only count as excluded evidence.

Canonical serialization is sorted and binds all decision fields into one
digest. Apply requires expected profile and digest and replans under locks.

### Placement

- One active purpose only -> that purpose's physical root and logical purpose
  folder.
- Multiple active purposes -> physical `media-library`; logical Legacy library
  unless a deterministic preferred organization rule is explicitly recorded.
- Unassigned -> physical `media-library`; logical Legacy library.

Later reuse never changes path or folder automatically.

### Trusted raster

1. authorize and acquire exact journal/lease/fence;
2. verify the locked manifest entry and source checksum;
3. copy original bytes to private quarantine;
4. normalize in private staging when necessary;
5. validate MIME/extension/decode/dimensions/size and staged SHA-256;
6. copy to a deterministic collision-safe public destination;
7. independently verify destination size/checksum;
8. in a short transaction create/update the MediaAsset and Curator binding,
   preserve the Curator numeric row, mirror the one key, switch owner paths,
   dual attachments, settings pairs and journal identity;
9. mark DB commit before cleanup;
10. invalidate provider/Glide/placeholder/palette identities;
11. remove the old public source only after zero-reference proof; retain private
    original for 90 days; mark cleanup complete.

### Needs Repair

Missing, corrupt, malicious, ambiguous, or unsanitized data still creates one
asset and Curator binding. It:

- keeps canonical storage fields null;
- records typed reason, provider snapshot and exact provenance;
- preserves active owner/settings intent diagnostically;
- copies any recoverable raw source to private quarantine before public cleanup;
- never emits a normal preview/download/selection URL;
- resolves configured/system fallback on use.

SVG rows can initially become Needs Repair without violating one-row closure.
The reusable sanitizer in Package 2 later supplies a tested repair transition.
No real SVG is acted on in this implementation run.

### Crash and retry

- Before DB commit: legacy DB identity/source remains authoritative; journal-
  owned stage/destination/quarantine is resumable or safely removable.
- After DB commit: asset identity remains authoritative; journal is
  `cleanup_pending` and repair resumes cleanup.
- Existing matching destination is accepted only after checksum proof.
- Conflicting destination, changed/missing source, stale references/digest,
  duplicate path ambiguity, foreign fence or open incompatible operation
  blocks without guessing.

## Trust, slot compatibility, folders and physical paths

The old `ImageUploadPurpose` currently combines four concerns. The program
must split them:

- validation/trust: MediaAsset status and canonical byte proof;
- slot compatibility: image type and explicit exceptional rules;
- logical folder: organization/default filter;
- physical root: controlled output placement.

Sanitized SVG is compatible with every approved image slot. A trusted image in
one physical root remains selectable for another slot. `MediaAttachmentRole`
may retain owner-role mapping, but its `purpose()` must not filter selection by
physical root.

## Filament and Livewire implementation evidence

The current app-owned Resource/schema/table separation is reusable:

- `MediaResource`
- `MediaForm`
- `MediaTable`
- `PathCuratorPicker`
- `MediaPickerPanel`
- `MediaPickerField`
- `ContentImageActions`

Installed Filament 5.7.1 proves:

- a table column can call a mounted `Filament\Actions\Action`;
- actions support `slideOver()`, `modalContent()`,
  `extraModalFooterActions()`, `modalWidth()`, `fillForm()` and `schema()`;
- `ImageColumn` uses `imageSize()`, `imageHeight()`, `imageWidth()`, `disk()`,
  `visibility()`, `alt()` and `defaultImageUrl()`.

The requested 300px hover image is not a native rich tooltip contract. Use an
app-owned column/view wrapper with one trusted thumbnail URL, keyboard focus,
and a capped hover/focus overlay. Detail data loads only when the action mounts.

Livewire `#[Locked]` prevents direct client property mutation but does not make
an ID/path/key trusted. Preserve the current pattern: re-query the asset by
opaque key, recheck trust/compatibility, authorize, and compare expected owner
identity inside the action. Do not serialize Eloquent models, provider
snapshots, owner collections or journals in gallery cards.

## Gallery, Needs Repair and logical folders

The trusted Resource query should require trusted + active and load only
minimal folder/provider data. Default page size is 25 within finite choices.
Search is bounded to 50 and constrained to indexed or reviewed display fields.

Needs Repair is a separate tab/page/query. It may show escaped metadata,
provider identity, reasons, owner/settings reference counts and fallback state.
It may offer authorized revalidate/normalize/sanitize/disposition actions. It
must not render unsafe bytes or offer ordinary media mutation actions.

Folder counts use one grouped query. Folder visibility never hides a row from
integrity, authorization or repair services.

## Unified acquisition and MediaAssetPicker

The current custom form component plus embedded Livewire modal is a sound
shell, but identity changes from Curator path/ID to MediaAsset key.

Four sources converge on one result contract:

1. Gallery: select an existing trusted compatible asset.
2. Upload: temporary upload retained with `storeFiles(false)`, then server
   validation/normalization/provider creation.
3. URL: queue or controlled fetch outside owner transaction, SSRF-safe download,
   normalize/create, then expected-identity attach.
4. Storage: server-issued discovery candidate identity + digest, never raw
   client path; exact import through the same pipeline.

Spotify's podcast and episode lookup already yields image URLs. Both surfaces
feed the URL source. They do not implement another downloader.

`SafeExternalImageFetcher` already enforces HTTPS/port/credentials/fragment,
public IP/DNS stability, manual redirect revalidation, pinned transport, proxy
disablement, encoded-body refusal and size limits. Preserve it and committed
HTTP fixtures with `Http::preventStrayRequests()`.

## Image-column/detail/change UX

The shared action must distinguish the displayed source:

- directly attached asset;
- podcast/group fallback;
- configured default;
- static system fallback.

Clicking a fallback must not imply the owner owns or may edit that fallback.
Change always targets the owner slot. Edit metadata appears only for an actual
authorized MediaAsset. Safe download uses an app-owned authorized route.
Copy filename uses the server-projected cleaned filename, never a raw path.

Current target surfaces include ContentGroup table, ContentItem table, and the
ContentGroup ContentItems relation manager. Their existing effective-image
eager loading should be adapted, not replaced by query-per-row resolution.

## Files Discovery and physical lifecycle

Files Discovery enumerates only configured app-owned candidate roots and
explicitly excludes canonical files already bound to assets, cache, Curator
curations, staging, quarantine, trash internals, symlinks/traversal and other
disks/roots.

It reports metadata first. Import or Import-and-Use requires a fresh opaque
candidate/digest and the same validation/acquisition coordinator. Physical
move, rename, trash, restore and purge are separate journal operation types.

Logical folder changes do not move bytes. Physical lifecycle keeps copy ->
verify -> DB switch -> cleanup, leases/fences, collision refusal, cache
invalidation, and retry compensation. Purge requires elapsed retention,
zero-reference proof, completed journal and explicit authorization.

## Security invariants

- Admin-or-higher legacy boundary; no Shield architecture.
- Curator IDs, asset keys, provider keys, Livewire state, paths, filenames,
  URLs and discovery candidates remain untrusted.
- Positive image MIME/extension allowlist and MIME sniffing.
- Raster decode/resource limits and server normalization.
- Staged SVG sanitation and malicious fixture coverage.
- No ordinary unsafe-byte preview/download.
- App-owned public disk/visibility only for trusted canonical output.
- Private quarantine/trash, never public quarantine.
- SSRF, redirects, DNS rebinding, response size/time/content controls.
- Short DB transactions; network/file work outside importer/owner transactions.
- Every Resource/action/controller/job/service authorizes server-side.
- Admin-adjustable limits remain inside absolute hard ceilings.

## Performance invariants

- Browse 25, bounded search 50, upload batch 10 by default.
- Minimal gallery projection and lazy detail load.
- No per-card filesystem `exists`, hash, image decode, settings lookup, owner
  query or provider-history load.
- One grouped folder-count query, not one per tab.
- Preserve eager-loaded effective image relationships on owner tables.
- Reuse one hover thumbnail URL; do not preload full-resolution images.
- Measure query count and Livewire state separately; do not call either a DOM,
  heap, navigation or TTFB measurement.
- Cover 1/10/50 owners and production-shaped aggregate cases.

## Documentation and runbook finding

`current-project-state.md` contains the wrong local migration statement.
`images-media-track-plan.md` still routes forward architecture through
Curator. The old production and SVG runbooks are unsafe as current execution
routes because they describe the G1/LMTC-only cutover. The precise update map
is in `04-active-document-supersession-map.md`.

## Material-drift conclusion

Source reconciliation confirms the approved option still requires the planned
MediaAsset/folder/provider schema, five packages, current Admin security
boundary, and no new dependency. The exact schema above is the schema already
approved in Stage 1, refined into fields/indexes/FKs without changing its
boundary. No material drift requires another audit at Gate 0.

No application file, dependency, local/production database, storage, cache,
migration, conversion, repair, sanitizer, process, deployment, or production
state was changed by this research.
