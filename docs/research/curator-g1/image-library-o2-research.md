# CURATOR-G1 O2 Full App-Owned Image Library Research

Date: 2026-07-20

Audit ID: `LS-20260720-CURATOR-G1-IMAGE-LIBRARY-01`

Approved option: `CURATOR-G1-O2-FULL-APP-OWNED-CURATOR-SURFACE`

This is the durable Stage 2 preflight and source-reconciliation record for the
approved seven-mini-task implementation. It inherits the accepted findings from
`CURATOR-G1-R1` and the named Laravel Simplifier audit. It does not reopen a
broad Stage 1 audit because the current baseline, dependency set, authorization
boundary, schema direction, task count, and 46-68 hour forecast do not
materially drift from the approved option.

## Task classification and authority

- This is an ad-hoc Stage 2 task. No prompt under `prompts/pre-13-prompts/` is
  active.
- The operator's task message is the authoritative Stage 2 contract.
- All seven approved mini-tasks remain in scope and are executed sequentially.
- Step 5B FU04 is not approved and remains unrelated.
- RichEditor file attachments remain disabled and are not expanded by this
  task.

## Exact pre-implementation baseline

| Evidence | Observed value |
|---|---|
| Working directory | `/Users/studioycm/Herd/PodText` |
| Git root | `/Users/studioycm/Herd/PodText` |
| Branch | `main` |
| HEAD | `7c55dca4012ce48779b32b2e3c4d2076d9198807` |
| `main` | `7c55dca4012ce48779b32b2e3c4d2076d9198807` |
| `origin/main` | `7c55dca4012ce48779b32b2e3c4d2076d9198807` |
| Worktree | clean (`## main...origin/main`) |
| Competing Curator implementation task | none active |

The expected Stage 1 baseline is therefore still exact. No later unrelated
Step 5B commit has moved the checkout, and no overlapping uncommitted changes
exist.

## Installed source of truth

No dependency command was run. Versions and source references were read from
Laravel Boost application information, `composer.lock`, and installed Composer
metadata.

| Package | Installed version / source | Evidence |
|---|---|---|
| Laravel | 13.19.0 | `composer.lock:2845-2846` |
| Filament | 5.6.7 | `composer.lock:1326-1327` |
| Livewire | 4.3.3 | `composer.lock:4342-4343` |
| Curator | 5.1.2 | `composer.lock:10-15` |
| Curator source | `2a79bf031099d2d75351377eae15322fb590ab43` | `composer.lock:13-20`; `vendor/composer/installed.php:158-160` |
| Intervention Image | 3.11.8 | `composer.lock:2706-2707` |
| SVG Sanitizer | 0.22.0 | `composer.lock:1233-1234` |
| Pest | 4.7.4 | Laravel Boost application information |

Local PHP has GD, Imagick, fileinfo, DOM, and libxml loaded. The approved
implementation needs no Composer or npm dependency change and does not install
Spatie Media Library.

## Primary documentation and installed behavior

Official primary sources consulted:

- Laravel 13 validation: https://laravel.com/docs/13.x/validation
- Laravel 13 filesystem: https://laravel.com/docs/13.x/filesystem
- Laravel 13 authorization: https://laravel.com/docs/13.x/authorization
- Laravel 13 HTTP client: https://laravel.com/docs/13.x/http-client
- Laravel 13 Eloquent relationships: https://laravel.com/docs/13.x/eloquent-relationships
- Filament 5 file upload: https://filamentphp.com/docs/5.x/forms/file-upload
- Filament 5 table actions: https://filamentphp.com/docs/5.x/tables/actions
- Filament 5 resource authorization: https://filamentphp.com/docs/5.x/resources/overview#authorization
- Filament 5 security: https://filamentphp.com/docs/5.x/advanced/security
- Livewire 4 security: https://livewire.laravel.com/docs/4.x/security
- Curator plugin documentation: https://filamentphp.com/plugins/awcodes-curator
- IANA IPv4 Special-Purpose Address Registry:
  https://www.iana.org/assignments/iana-ipv4-special-registry/iana-ipv4-special-registry.xhtml
- IANA IPv6 Special-Purpose Address Registry:
  https://www.iana.org/assignments/iana-ipv6-special-registry/iana-ipv6-special-registry.xhtml

Official documentation describes framework integration points; installed
source is authoritative for exact behavior. The decisive differences are:

1. Filament supports exact `acceptedFileTypes()`, `maxFiles(10)`,
   `maxParallelUploads(2)`, `storeFiles(false)`, individual-record bulk
   authorization, bounded pagination, and schema upload restrictions. These
   are useful integration hooks, not a substitute for content validation.
2. Curator's installed uploader derives the extension from the client filename
   and stores directly to the destination before in-place SVG sanitation
   (`vendor/awcodes/filament-curator/src/Components/Forms/Uploader.php:35-105`).
3. Curator's installed picker accepts mutable public disk, directory,
   visibility, settings, selected arrays, and action arguments; browse and
   search serialize full models
   (`vendor/awcodes/filament-curator/src/Components/Modals/CuratorPanel.php:42-116,172-260`).
4. Picker edit/delete reload by argument ID without one shared app scope, while
   download trusts argument disk/path and view trusts argument URL
   (`CuratorPanel.php:318-425`).
5. Curator's vendor observer deletes, moves, and renames files inside model
   events and deletes files after row deletion
   (`vendor/awcodes/filament-curator/src/Observers/MediaObserver.php:38-95`).
6. Laravel 13 merges `ObservedBy` attributes from Eloquent parent models
   (`vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasEvents.php:39-63`).
   The app Media model must override observer resolution; subclassing alone is
   insufficient.
7. Curator's plugin registers the configured complete Resource
   (`vendor/awcodes/filament-curator/src/CuratorPlugin.php:52-57`), but the
   vendor Resource hardcodes its own form, table, and pages
   (`vendor/awcodes/filament-curator/src/Resources/Media/MediaResource.php:91-109`).
   The supported safe seam is one complete app-owned Resource.

## Laravel Boost and FilamentExamples research

### Laravel Boost

Boost was available and used before application edits.

- `application_info` supplied installed version-aware package information.
- `search_docs` was used in decomposed searches for file validation, dimensions,
  SVG handling, policies, individual bulk authorization, resource query
  scoping, upload-to-schema restriction, polymorphic relationships, eager
  loading, FileUpload limits, and pagination.
- Relevant installed-version patterns include
  `RestrictsFileUploadsToSchemaComponents`,
  `authorizeIndividualRecords()`, `chunkSelectedRecords()`,
  `deselectRecordsAfterCompletion()`, `maxFiles()`, `maxSize()`, dimension
  rules, and bounded pagination options.
- Boost database-schema and query tools were deliberately not used because the
  local development database is off-limits. Migrations and query plans are
  verified only through the isolated test database.

### FilamentExamples

The required multi-query/refinement protocol was followed.

- Initial short-query batches covered app-owned Resources, create/multi-upload,
  card grids, media pickers, bulk authorization, FileUpload constraints, and
  polymorphic attachment patterns.
- Refined searches used surfaced terms and classes including
  `ProductPickerTable`, `QuoteProductsField`, `WithoutUrlPagination`, locked
  state, event dispatch by ID, `maxFiles`, and card-grid pagination.
- Useful adaptation: the Custom Table Field With Product Picker Modal
  demonstrates an owned Field plus owned Livewire table and ID-based dispatch;
  the Card Grid example demonstrates bounded table payloads and pagination.
- Search access exposed snippets only. No source/read/fetch/details tool was
  available. Results were predominantly Filament 4/general patterns and are
  not proof of Curator 5 behavior.
- No Spatie Media Library result was treated as Curator evidence.

## Inherited findings

The following accepted findings from `CURATOR-G1-R1` and
`LS-20260720-CURATOR-G1-IMAGE-LIBRARY-01` remain valid:

- Curator currently operates as a public-disk, mutable-path library with a
  broad and partly client-controlled upload/picker boundary.
- `PathCuratorPicker` preserves path compatibility but serializes complete
  Media arrays and does not create a trusted authorization/query boundary
  (`app/Filament/Forms/Components/PathCuratorPicker.php:16-227`).
- The current policy allows almost everything and protects only a subset of
  path-referenced deletes
  (`app/Policies/CuratorMediaPolicy.php:10-48`).
- Existing ContentGroup, ContentItem, and settings identities are mutable paths.
- The selected relationship design is app-owned attachments, not direct
  `belongsTo` foreign keys and not Spatie Media Library.
- Stable local identity remains Curator's numeric primary key; portable media
  identity is a new immutable ULID `reference_key`; disk/path remain mutable.
- All filesystem mutations require an app-owned journal and
  copy-verify-commit-cleanup coordination.
- Gallery and picker queries need one shared scope, 25-record browse pages,
  at most 50 search results, projected payloads, at most 10 files per upload,
  and at most two concurrent transfers.
- Existing disallowed rows are excluded and reported, not silently deleted.
- Curations remain raster-only. Installed source supports quality 60; no new
  dimensions are registered without rendered-width and DPR measurement.
- Existing SVG IDs 6 and 7 remain production-gated and are not sanitized here.

## Newly confirmed Stage 2 pre-code findings

1. The checkout still exactly matches the audit baseline, so no amended Stage
   1 audit is required.
2. No app `Media`, `MediaAttachment`, operation journal, stable media morph
   aliases, or attachment relationship exists.
3. `curator.path` has a simple non-unique index; `reference_key` is absent
   (`database/migrations/2026_07_12_140228_create_curator_table.php:11-34`).
4. ContentGroup and ContentItem already demonstrate immutable owner ULIDs
   (`app/Models/ContentGroup.php:79-96` and
   `app/Models/ContentItem.php:363-375`).
5. A naive global primary morph alias would change the existing Spatie taggable
   discriminator. Compatibility requires FQCN mappings first, alias mappings
   second, and app-owned attachment writers that explicitly persist
   `content_group` / `content_item`; `enforceMorphMap()` is not used.
6. Existing settings assets include nested about blocks and team profiles in
   addition to menu logos and defaults. All need adjacent media keys.
7. Current native group export emits `cover_path`, while item import/export has
   no local-image identity. Both must move to media reference keys.
8. Current ZIP export has no manifest and guesses types from paths
   (`app/Support/Media/ContentImagesExportManager.php:20-205`).
9. The external-image job validates only literal HTTPS, final response size,
   finfo, decode, and dimensions. It lacks DNS/IP/redirect/rebinding controls,
   streaming size enforcement, normalization, journal compensation, and job
   reauthorization
   (`app/Jobs/DownloadExternalContentItemImage.php:34-174`).
10. Current public queries and tables do not eager load attachment media, and
    `content-group-badge.blade.php` bypasses the central resolver.
11. A non-null media key can serve as the bounded-query trust marker only when
    key issuance itself is fenced. The final design therefore rejects every
    ordinary `Media::save()` creation outside the coordinator creation lease;
    the only other key-issuance path is the separate exact-normalization
    backfill, which writes a completed SHA-256 proof journal in the same
    transaction.
12. The remote-image IP policy must cover the complete IANA special-purpose
    IPv4 and IPv6 registries, not only PHP's private/reserved filters. The
    final allow rule also limits IPv6 to global unicast `2000::/3`.

## Pre-code local application inventory

### Upload and write surfaces

| Surface | Current boundary | Required cutover |
|---|---|---|
| Top-level gallery | Vendor Resource configured at `config/curator.php:33-55` | Complete app Resource/pages/form/table |
| ContentGroup cover | `ContentGroupForm.php:90-101` | Reference-key picker and attachment write |
| ContentItem primary image | `EpisodeWorkspaceForm.php:126-130` | Reference-key picker and attachment write |
| Table actions | `ContentImageActions.php:92-123` writes paths directly | Trusted key reload and attachment manager |
| Settings logos/about/team/defaults | `BuildsPublicContentSettingsSubjectSchemas.php:542-550,1368-1402,1749-1761,2092-2096` | Adjacent key fields, key-first resolution, path fallback |
| External thumbnail job | `DownloadExternalContentItemImage.php:107-137` | Safe fetcher, shared validator/coordinator, attachment write |
| Legacy registration | `RegisterExistingCuratorMedia.php:22-64` | App model, validation/scope, generated key |
| Picker | `PathCuratorPicker.php:16-227` and vendor panel | App Field/modal/Livewire component with ID-only state |

### Read/query surfaces

- `PublicDefaultImageResolver.php:19-147` is the central public image resolver
  but currently trusts paths.
- Public item/group query services do not eager load `mediaAttachments.media`.
- Admin item/group tables and relation managers do not eager load attachments.
- `content-group-badge.blade.php:10` constructs a path URL directly.
- Card template sample ranking uses raw `image_path` / `cover_path` SQL.
- Settings readers and validators recognize path-only image shapes.
- Media reference checks scan owner/settings paths per record and do not batch
  attachments.

### Exact legacy path and settings reference inventory

| Owner/settings location | Legacy mutable path | Adjacent immutable identity |
|---|---|---|
| ContentGroup cover | `content_groups.cover_path` | `media_attachments` role `cover`; portable field `cover_media_reference_key` |
| ContentItem primary image | `content_items.image_path` | `media_attachments` role `primary_image`; portable field `primary_image_media_reference_key` |
| Light menu logo | `menu_config.logo.light_path` | `menu_config.logo.light_media_reference_key` |
| Dark menu logo | `menu_config.logo.dark_path` | `menu_config.logo.dark_media_reference_key` |
| Global default | `default_images.global.path` | `default_images.global.media_reference_key` |
| Content-item default | `default_images.content_item.path` | `default_images.content_item.media_reference_key` |
| Content-group default | `default_images.content_group.path` | `default_images.content_group.media_reference_key` |
| Contributor default | `default_images.contributor.path` | `default_images.contributor.media_reference_key` |
| About image block, outer shape | `about_page.blocks[*].image_path` | `about_page.blocks[*].image_media_reference_key` |
| About image block, Builder data shape | `about_page.blocks[*].data.image_path` | `about_page.blocks[*].data.image_media_reference_key` |
| Team profile | `about_page.team_profiles[*].image_path` | `about_page.team_profiles[*].image_media_reference_key` |

Compatibility readers resolve the immutable key first and the paired path only
as fallback. If both are present but resolve to different Media, they fail and
report instead of guessing. Portable exports omit numeric Media IDs and mutable
paths as identity.

## Central upload contract

The single app-owned positive policy accepts only:

| Content MIME | Client extensions | Canonical stored extension |
|---|---|---|
| `image/jpeg` | `jpg`, `jpeg` | `jpg` |
| `image/png` | `png` | `png` |
| `image/webp` | `webp` | `webp` |
| `image/svg+xml` | `svg` | `svg` |

Every other family is rejected. There is no wildcard MIME family and no
blacklist fallback. ContentGroup cover and ContentItem primary-image purposes
are raster-only. Header/logo may also accept strict sanitized SVG. Gallery
uploads must choose one finite app enum purpose and therefore one of these
fixed roots:

- `content-groups/covers`
- `content-items/images`
- `header`
- `team`
- `about`
- `default-images`

Validation is content-derived and requires client extension agreement, strict
format structure, raster decode and re-encode or staged SVG sanitation and
reparse, no more than 2 MB, no raster dimension over 3000x3000, a generated
ULID filename, canonical extension, fixed public disk/visibility, and a
normalized purpose root. SVG is never passed to raster curation logic.

## Threat model

| Boundary | Attacker-controlled input | Enforcement |
|---|---|---|
| Upload RPC | temporary path, filename, extension, bytes, MIME claim, count | schema upload restriction; 10/2 limits; private staging; shared content validator |
| Purpose/root | public Livewire values, hidden controls, breadcrumbs | backed enum is re-parsed each action; root is derived server-side |
| Browse/search/load-more | search, page, selected IDs | authenticated Admin; one scoped query; 25/50 bounds; projection only |
| Selection/attach/detach | selected IDs, state path, owner IDs, role | reload scoped Media and owner; policy; server-derived role compatibility |
| Metadata edit | record ID and text | scoped reload; policy; field allowlist only |
| View/download | ID, forged disk/path/URL | scoped reload; policy; derive disk/path/URL from record |
| Rename/swap/delete | ID, filename, replacement, action arguments | scoped lock; per-record policy; attachment/legacy denial; coordinator lease and journal |
| Bulk delete | selected array | batch scoped reload; authorize every record; all-or-nothing preflight |
| Remote image | URL, DNS, redirects, headers, body | HTTPS/port policy, public IP resolution, pinned address, redirect revalidation, streamed cap, shared image validator |
| Import/settings | reference keys and legacy paths | reference key first; missing/mismatch is a row failure or explicit report; no remote fetch |
| Crash/failure | partial bytes or row state | durable journal, checksums, copy/verify/transaction/cleanup, idempotent repair |

## Relationship alternatives

| Alternative | Result |
|---|---|
| Keep path columns only | Rejected: mutable identity, no sharing, unsafe moves |
| Add direct media foreign keys to both owners | Rejected: duplicates role/schema rules and does not provide ordered extensibility |
| Curator-native polymorphic relationship | Rejected: vendor state/actions and uncoordinated writes remain unsafe |
| Spatie Media Library | Rejected and prohibited: new dependency and different ownership model |
| App-owned `media_attachments` | Selected: shared Media, stable roles, ordered rows, database cardinality constraint |

Selected attachment columns are `media_id`, `attachable_type`,
`attachable_id`, `role`, `position`, and timestamps. The stable type tokens are
`content_group` and `content_item`; current singleton roles are `cover` and
`primary_image`. Unique `(attachable_type, attachable_id, role)` enforces one
current singleton attachment while allowing the same media row on many owners.

## Compatibility and identity

- Schema DDL adds nullable unique `curator.reference_key`; the migration does
  not backfill data.
- New app Media rows receive immutable ULIDs automatically only while the
  validated coordinator holds its scoped creation lease. Vendor/direct model
  creation is rejected before a browse-eligible key can be minted.
- A separate idempotent command fills legacy null keys only when the stored
  raster bytes already exactly equal the validator's normalized output. It
  creates a completed `reference_key_backfill` checksum-proof journal in the
  same transaction. Noncanonical legacy raster uses the journaled registration
  path instead of being silently keyed in place.
- Separate attachment backfill resolves legacy paths to allowed Media records,
  reports missing/conflicting cases, and never guesses.
- Owner path columns remain compatibility mirrors during this implementation.
  Readers prefer loaded attachment media and use a legacy path only when no
  attachment exists.
- Settings store adjacent immutable media keys. Readers resolve the key first;
  a key/path disagreement is invalid and reported.
- Native content import/export and portable settings export use media keys,
  not numeric IDs or paths.
- Legacy path retirement and a later non-null media-key migration remain
  proof-gated production follow-ups.

## Filesystem/database integrity design

The app model overrides inherited Curator observer resolution. The scoped
`MediaMutationLease` is an in-process observer permission for app-owned model
creation and storage-field update/delete. The durable `MediaMutationFence`
uses database row locks, identity snapshots, open-operation exclusion,
lease-token ownership/expiry, and copied-state commit gates; attachment writes
also refuse an active mutation. Upload, external import, registration, swap,
rename, and delete create durable operation rows containing operation key,
status, actor/media identity, source/staging/destination or quarantine
locations, checksums, attempts, timestamps, error, and server-owned context.
Reference-key backfill creates a completed checksum-proof operation without a
filesystem mutation.

The mutation sequence is:

1. persist the journal row;
2. stage privately and validate/sanitize;
3. copy normalized bytes to a generated destination;
4. verify size and SHA-256;
5. lock and update Media/attachment state in a short transaction;
6. mark database commit;
7. after commit, quarantine/remove old bytes and invalidate old/new curation,
   Glide, placeholder, and palette cache data;
8. mark cleanup complete.

Repair is idempotent. Its command is dry-run/report by default; `--apply` is a
mutating production-gated operation.

## Schema and index evidence

The approved DDL is additive, data-free, and reversible:

- nullable unique `curator.reference_key` (`char(26)`);
- `media_attachments` with a restrictive Media foreign key, singleton unique
  owner/type/role constraint, and reverse media/role index;
- `media_mutation_operations` with nullable Media/user foreign keys,
  unique operation key, status/update repair index, and media/status index.

No broad browse/search index is added speculatively. Attachment and journal
indexes correspond directly to singleton writes, reverse reference checks, and
incomplete-operation repair queries. Isolated SQLite `EXPLAIN QUERY PLAN` tests
verify those plans; they are not claimed as MySQL or browser measurements.

## Performance budgets

- Gallery table: only 25 rows per page; no `all` option.
- Picker browse: 25 records per page.
- Picker search: at most 50 records.
- Picker/component state: IDs and small projections only; never full Media
  `toArray()` output.
- Multi-upload: at most 10 files, two concurrent transfers.
- Owner collections eager load `mediaAttachments.media`.
- Aggregate-only views use `withCount` / `withExists`.
- Bulk reference checks are batched before mutation.
- Query-count tests for 1, 10, and 50 owners must have the same bounded query
  ceiling after fixture setup.
- PHP/query/serialized-state evidence is reported separately. No browser DOM,
  heap, listener, modal, navigation, or TTFB claim is made without a browser
  measurement.

## Production and deployment limitations

This local task does not:

- access the local development database;
- run production/staging migrations, exports, imports, sanitizers, backfills,
  repair, deployment, or cache commands;
- publish vendor files;
- install/update dependencies;
- sanitize Curator Media IDs 6 and 7;
- make `reference_key` non-null;
- retire legacy paths;
- deploy partial stages.

Production execution requires one maintenance window after the complete local
implementation is verified. Separate approval remains mandatory for schema
migration, backup/checksum, reports/backfills, cutover, repair, SVG IDs 6/7,
cache operations, and rollback. Runbooks are written in Mini-task 7 only.

## Accepted-requirement classification at pre-code review

| Requirement group | Classification before code | Planned owner |
|---|---|---|
| Durable research and plan before code | Implementing now | Mini-task 1 |
| Positive upload purpose/role policy | Accepted | Mini-task 1 |
| Exact type/extension/content validation and normalization | Accepted | Mini-task 1 |
| Private staged strict SVG handling | Accepted | Mini-task 1 |
| App-owned gallery Resource/create/multi-upload/edit | Accepted | Mini-task 2 |
| Bounded gallery pagination and upload concurrency | Accepted | Mini-task 2 |
| App-owned picker browse/search/load-more/upload/select/actions | Accepted | Mini-task 3 |
| ID-only trusted reload and projected state | Accepted | Mini-task 3 |
| Explicit Admin-or-higher abilities and per-record bulk auth | Accepted | Mini-tasks 2-3 |
| App Media model and immutable nullable key | Accepted | Mini-task 4 |
| Shared typed ordered attachments and singleton constraints | Accepted | Mini-task 4 |
| Durable mutation journal and lease | Accepted | Mini-task 4 |
| Separate idempotent key/attachment backfills | Accepted | Mini-task 5 |
| Disallowed/orphan/duplicate/reference reports | Accepted | Mini-task 5 |
| Settings key-first dual-read and mismatch failure | Accepted | Mini-task 5 |
| ContentGroup/ContentItem attachment UI writes | Accepted | Mini-task 5 |
| Portable native import/export keys and ZIP manifest | Accepted | Mini-task 5 |
| Public resolver/query compatibility and fallbacks | Accepted | Mini-task 5 |
| Journaled rename/swap/delete and repair | Accepted | Mini-task 6 |
| Registration and external-image job integration | Accepted | Mini-task 6 |
| SSRF/redirect/DNS/size/content controls | Accepted | Mini-task 6 |
| Cache/curation invalidation and raster-only curations | Accepted | Mini-task 6 |
| Query/payload/index measurements | Accepted | Mini-task 6 |
| Full threat/relationship/integrity/performance tests | Accepted | Mini-task 7 |
| English and Hebrew keys | Accepted; G2 prose ownership noted only if needed | Mini-task 7 |
| Current state, ledger, handoff, runbooks | Accepted | Mini-task 7 |
| RichEditor Curator integration | Already disabled; preserve | All minis |
| Existing disallowed row deletion/quarantine | Deferred to separately approved production operation | Production-gated |
| Existing SVG IDs 6 and 7 sanitation | Deferred with exact separate approval | Production-gated |
| Legacy path retirement / non-null media key | Deferred until proof | Production-gated |
| New dependency / Spatie Media Library | Not applicable and prohibited | None |

## Reconciliation decision

The current checkout and installed source support the exact approved option.
The observer-inheritance and morph-map compatibility details are newly explicit
implementation constraints, not changes to the schema or security boundary.
There is no material conflict, dependency need, production action, task-count
change, or forecast drift. Stage 2 therefore continues directly into the seven
mini-tasks after the companion implementation plan is reviewed against source.

## Post-implementation reconciliation and evidence

The final task-owned source was reviewed against this pre-code record before
closeout. The implementation confirms the selected architecture without a
material conflict:

- The centralized positive allowlist is implemented by
  `app/Support/Media/CuratorImageUploadPolicy.php` and enforced by
  `ImageUploadValidator`, `SvgUploadSanitizer`, and
  `MediaFilesystemMutationCoordinator`. Tests cover every accepted type, every
  excluded family named by the contract, mismatches, executable renames,
  polyglots, malicious SVG, limits, and path bypasses.
- The configured gallery now uses the complete app-owned Resource under
  `app/Filament/Resources/Media/`. The picker uses
  `App\Livewire\Admin\MediaPickerPanel`, bounded projections, and trusted ID
  reloads. Vendor picker/curation aliases fail closed through
  `DisabledVendorCuratorSurface`.
- `App\Models\Media` owns immutable ULID keys and suppresses inherited
  destructive observer behavior. `MediaAttachment` and
  `MediaMutationOperation` implement shared owner identity and journal history;
  stable aliases preserve the pre-existing Spatie FQCN morph behavior.
- The three relational migrations are additive, data-free, and reversible. The
  Spatie settings-shape migration adds nullable adjacent key fields only; actual
  key and attachment population remains in three separate dry-run-first
  commands.
- Content owners, settings readers, public resolvers, native import/export, and
  the image ZIP manifest use immutable keys first while preserving fail-closed
  legacy-path compatibility. Mismatches are errors, never guessed identity.
- Rename, swap, upload, external import, legacy registration, and delete use
  generated destinations, private staging/quarantine, SHA-256 verification,
  short database transactions, observer leases, durable mutation fencing, and
  idempotent repair. Repair terminal writes recheck the claimed token under a
  row lock, so an expired worker cannot overwrite a replacement lease. Direct
  creation, storage-field updates, and deletes fail closed.
- `StoredMediaValidator` verifies metadata scope, unique storage/curation
  identity, file existence/public visibility, bounded bytes, content-derived
  MIME/extension/dimensions, and record agreement. Null-key raster must already
  be exact normalized output; keyed rows must match a current destination
  SHA-256 in an app-owned completed/committed journal. Existing SVG remains
  unkeyed pending its separate runbook.
- The external-image job now requires a reauthorized Admin actor, HTTPS on port
  443, exclusively public DNS answers after explicit IANA special-use checks,
  global-unicast IPv6, a pinned connected address, stable DNS, bounded
  redirects and bytes, raster-only content validation, the shared coordinator,
  and attachment persistence. HTTP tests prevent stray requests.
- Curations remain deliberately disabled because rendered widths/DPR were not
  measured in this non-browser task. Formats remain raster-only and installed
  quality 60 is preserved. All RichEditor/MarkdownEditor file attachments
  remain disabled.
- English and Hebrew recursive key inventories are equal at 1,966 keys each.
  Existing Hebrew singular pluralization variants intentionally spell “one”
  instead of interpolating `:count`; no translation key is missing.
- Isolated query tests keep 1/10/50 owner costs constant: three queries for
  groups, six for combined items/groups, four for settings about/team
  identities, and no more than six for bulk reference discovery. Five schema
  indexes are exercised with SQLite `EXPLAIN QUERY PLAN`. These are query-plane
  measurements, not browser DOM, heap, listener, navigation, modal, or TTFB
  claims.
- `media:register-existing-curator-assets` is dry-run-only by default and has a
  bounded `--apply --actor=<admin-id> --path=<one-exact-path>` mode. Apply
  re-plans the path, purpose, SHA-256, owners, and raw settings references;
  retains the source in checksum-verified private quarantine; stages normalized
  bytes privately; promotes to a generated public destination; and atomically
  creates Media/attachments plus owner/settings compatibility identities.
  Settings caches are cleared inside journaled cleanup before the old public
  source is removed. Completed operations are idempotent, while a reused path
  with new references is treated as a new registration rather than silently
  skipped.

The latest security-focused verification on the reconciled source passed 82
tests and 476 assertions; the final consolidated and repository-wide totals
are recorded in the handoff after the binding gate sequence. No local
development database, production system, dependency command, vendor
publication, migration, backfill, registration apply, repair, export/import,
sanitizer, or cache mutation was run.

## Accepted-requirement classification after implementation

| Requirement group | Final classification |
|---|---|
| Durable research and plan before application code | Implemented |
| Positive finite policy, trusted validation, raster normalization, staged SVG | Implemented |
| App-owned gallery Resource and bounded upload surfaces | Implemented |
| App-owned picker and fail-closed vendor boundaries | Implemented |
| Admin-or-higher ability matrix and per-record bulk authorization | Implemented |
| Immutable nullable Media key, creation fence, shared attachments, stable aliases, journal | Implemented |
| Separate dry-run-first backfills, reports, and repair | Implemented |
| Settings key-first dual-read and mismatch failure | Implemented |
| Content owner UI attachment writes and public compatibility | Implemented |
| Portable content/settings import/export and ZIP manifest | Implemented |
| Rename/swap/delete/registration/external-fetch integrity, fencing, and compensation | Implemented |
| Bounded payload/query/index evidence | Implemented in the query/PHP plane |
| English and Hebrew key coverage | Implemented |
| RichEditor Curator attachments | Already disabled and preserved |
| Measured raster curation dimensions | Deferred: no browser width/DPR evidence; curations remain disabled |
| Existing eligible unregistered legacy-byte conversion | Implemented locally as one-path journaled registration; production apply is gated and unexecuted |
| Existing disallowed row quarantine/deletion | Production-gated and not executed |
| Existing SVG IDs 6 and 7 sanitation | Production-gated and not executed |
| Legacy-path retirement and non-null proof migration | Production-gated and not executed |
| New dependency, Spatie Media Library, owner gallery, RichEditor attachment feature | Not applicable / prohibited |

The final source still has no material drift from the approved seven-mini,
46-68 hour Option O2 forecast.
