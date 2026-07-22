# Package 1 Research — Minimal Kernel and Curator Conversion

## Authority

Package 1 is approved under `LS-20260723-MEDIA-INVENTORY-FIRST-RESET-01`, option
`MEDIA-INV-O1-RESET-CLEANUP-P1-MINIMAL-KERNEL`.

## Current committed seams

- `Media` is the Curator provider model and owns the current path/metadata.
- `MediaAttachment.media_id` is the numeric local owner relation.
- ContentGroup cover and ContentItem image paths are compatibility mirrors.
- Settings retain path/key pairs that can be reconciled by unique path.
- Curator's `reference_key` column already exists after G1 and is intended as a
  portable mirror.

## Draft findings

The unfinished draft implements a materially different product: asset trust and
lifecycle states, logical folders, canonical storage duplication, provider
snapshots, schema capability states, conversion manifests/digests, raster
normalization, checksums, private quarantine, journaled file mutation, SVG
sanitation and public asset delivery.

Those classes and tests are not a useful incremental base. Package 1 keeps only
the model names `MediaAsset` and `MediaProviderBinding`, the concept of a
portable attachment bridge, and the command name. Everything else is rewritten
from the approved requirements.

## Exact schema decision

### `media_assets`

- `id`
- `reference_key` as a 26-character unique immutable ULID
- timestamps

### `media_provider_bindings`

- `id`
- restrictive `media_asset_id` foreign key
- `provider` string (`curator` initially)
- `provider_record_key` string containing the preserved numeric Curator ID
- timestamps
- unique `(provider, provider_record_key)`
- unique `(media_asset_id, provider)`

### attachment bridge

- nullable restrictive `media_attachments.media_asset_id`
- retain required `media_id`
- retain existing unique owner/type/role relationship

No folder, trust, validation, lifecycle, disk, path, hash, provenance,
normalization, quarantine, purge, manifest or journal fields are present.

## Model behavior

`MediaAsset.reference_key` is generated when absent and cannot change after the
model exists. `MediaAsset` relates to bindings and attachments.
`MediaProviderBinding` relates to its asset and exposes a Curator relation by
numeric provider record key without creating a second authority.

`Media` relates to one Curator binding and through it to one asset.
`MediaAttachment` keeps `media()` authoritative and adds `mediaAsset()` for
portable/key operations.

## Conversion algorithm

One command, `media-assets:convert-curator`, supports report-only mode and an
explicit `--apply`. There is no digest, actor token, schema profile, manifest,
maintenance-mode requirement or per-row selector.

On apply, one database transaction:

1. Loads every Curator row in stable numeric-ID order.
2. Computes duplicate-key and duplicate-path groups from database rows.
3. Reuses an existing binding/asset on rerun.
4. Reuses a syntactically valid unique Curator ULID; otherwise generates a new
   ULID and mirrors it onto the Curator row.
5. Creates exactly one MediaAsset and one Curator binding per row.
6. Bridges existing authoritative attachments to the asset.
7. Repairs an authoritative attachment's stale owner path to `Media.path` and
   reports the repair.
8. For an owner without an attachment, creates the numeric attachment only when
   its compatibility path matches exactly one Curator row.
9. Fills a settings reference key only when its path matches exactly one row.
10. Reports duplicate paths and unresolved owner/settings paths without
    guessing or clearing compatibility values.
11. Reports missing files using existence-only checks; the result never gates
    conversion or inventory.
12. Commits all database mutations together.

Report-only mode calculates database mappings and existence diagnostics but
does not mutate. Neither mode reads bytes. No mode writes storage.

## Settings scope

The converter reuses the current settings projection definitions instead of
inventing a generic recursive setting walker. It covers the existing logo,
header, default image, About/team and other registered media path/key pairs.
Settings remain stored through their existing settings classes/repository.

## Test shapes

- Original local incident: 15 rows with null keys.
- Current local shape: 15 rows with valid unique keys; rerun is idempotent.
- Production shape: 403 rows, 108 unique group cover matches, three SVG paths,
  one oversized raster metadata row, two duplicate-byte metadata pairs and five
  rowless files excluded from conversion.
- Duplicate database paths and unresolved legacy paths.
- Missing file diagnostics with rows/assets/bindings retained.
- Exception during conversion rolls back all database changes.
- A filesystem spy proves zero writes and no byte reads.

## Deliberate Package 1 non-goals

Package 1 does not alter Resource/picker inventory queries, public D01 behavior,
file delivery, upload validation or lifecycle. Those are Package 2+ subjects.
It also performs no real local or production conversion.
