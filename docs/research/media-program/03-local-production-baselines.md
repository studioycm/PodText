# Media Program Local and Production Baselines

## Purpose

This document corrects the stale active claim that the G1 migrations never ran
locally and gives the converter two explicit schema/data profiles. It records
read-only facts only. It is not an execution manifest and authorizes no action.

## Repository and installed-source baseline

- Checkout and Git root: `/Users/studioycm/Herd/PodText`
- Branch: `main`
- Stage 2 starting HEAD:
  `5ba687eff92878f18e9e19e807944a2d39b63372`
- Starting worktree: clean
- Tracking at start: 11 commits ahead of `origin/main`
- `origin/main`: `7c55dca4012ce48779b32b2e3c4d2076d9198807`
- Laravel: 13.21.1
- Filament: 5.7.1
- Livewire: 4.3.3
- Curator: 5.1.2
- PHP reported by Boost: 8.4
- Development database engine reported by Boost: MySQL

Installed application and vendor source are authoritative. No dependency was
installed or updated for this baseline.

## Local development: post-G1 incident profile

### Schema fact

The local incident ran these four G1 media migrations and the dormant
permission migration in migration batch 8:

1. `2026_07_20_000000_add_public_media_reference_keys`
2. `2026_07_20_000001_add_reference_key_to_curator_table`
3. `2026_07_20_000002_create_media_attachments_table`
4. `2026_07_20_000003_create_media_mutation_operations_table`
5. `2026_07_16_172210_create_permission_tables`

Fresh Laravel Boost schema inspection on 2026-07-22 confirms that local MySQL
currently has:

- nullable unique `curator.reference_key`;
- `media_attachments.media_id -> curator.id` with RESTRICT delete, singleton
  owner/type/role uniqueness, and `(media_id, role)` index;
- `media_mutation_operations.media_id -> curator.id` with SET NULL delete and
  the existing source/destination/staging/quarantine/journal columns;
- the dormant permission/role tables.

### Data and filesystem fact

The already-verified read-only incident snapshot contains:

- 15 Curator rows and 15 corresponding files;
- all 15 rows have `reference_key = NULL`;
- five ContentGroups have `cover_path` values matching Curator IDs 1-5;
- no ContentItem has `image_path` populated;
- IDs 6-7 are header SVGs;
- ID 8 is an unreferenced default image;
- ID 9 is referenced by global, ContentItem, and ContentGroup default-image
  settings;
- IDs 10-11 are unreferenced team JPEGs;
- IDs 12-15 are root-level legacy JPEGs with null directory metadata;
- `media_attachments` has zero rows;
- `media_mutation_operations` has zero rows;
- permission and role tables exist and are empty;
- `public/storage` points to `storage/app/public` and the recorded file facts
  matched Curator metadata.

The MediaAsset schema and conversion are not applied to this database in the
approved implementation task. Tests reproduce the profile with isolated test
databases and fake storage.

## Production: pre-G1 profile

This is a dated read-only design snapshot, not a current execution manifest.
Before any future real action it must be regenerated because editorial data and
files may change.

### Schema and release fact

- Deployed release was based on `7c55dca`.
- The four G1 media migrations were not applied.
- The dormant permission migration was applied and its permission/role tables
  were empty.
- Production therefore has the original Curator schema but not
  `curator.reference_key`, `media_attachments`, or
  `media_mutation_operations` from G1.

### Data and filesystem fact

- 403 Curator rows and corresponding Curator files.
- 400 raster rows: 399 JPEG and 1 PNG.
- 3 SVG rows.
- 108 ContentGroups with covers; all 108 paths match Curator rows.
- 106 of the active group-cover paths are root-level; 2 are already under
  fixed roots.
- 1 ContentItem exists and has no image.
- 2 active header/logo references.
- No configured default image.
- 110 distinct paths are actively referenced.
- 293 Curator rows are not referenced by the inspected owner/settings paths.
- 5 filesystem-only files exist and are explicitly excluded from Curator-row
  conversion.
- 1 unreferenced raster exceeds the current 3000-pixel working target.
- 2 duplicate-byte pairs exist; conversion keeps four separate MediaAssets.

## Dual schema profiles

### `local_post_g1`

- G1 schema already exists.
- Apply only the future exact MediaAsset schema allowlist in an isolated test
  or separately approved real action.
- Converter must preserve the 15 rows, null-key incident shape, existing legacy
  owner/settings references, and empty G1 attachment/journal tables.

### `production_pre_g1`

- G1 schema does not exist.
- A future runbook must apply the exact G1 relational/settings allowlist before
  the exact MediaAsset schema allowlist; never broad `migrate --force`.
- Converter must close all 403 Curator rows and exclude the 5 filesystem-only
  files.

Both profiles use one shared planner/executor. Separate entry commands may set
the expected schema profile and present environment-specific diagnostics, but
must not contain divergent conversion algorithms.

## Conversion closure invariants

For a reviewed manifest, closure requires:

- Curator row count equals Curator provider-binding count;
- every Curator binding points to one distinct MediaAsset;
- duplicate checksums do not collapse rows;
- every existing attachment has matching Curator and MediaAsset identities;
- every unambiguous owner/settings path has the same asset key or an explicit
  Needs Repair diagnostic;
- every trusted asset has complete canonical storage and checksum proof;
- every Needs Repair asset has exact provider/provenance evidence but no
  ordinary byte URL;
- no incomplete journal operation remains;
- filesystem-only discovery candidates are counted and remain unimported;
- the final digest covers the same rows, bytes, references, and dispositions as
  the approved preflight digest.

## Real-action gate

This Stage 2 task may create migrations, commands, services, tests, and
runbooks, but it must not apply them to local development or production.

Before any future real-data action:

1. re-read the current release/schema read-only;
2. regenerate row/file/reference inventory and source checksums;
3. compare counts and data shape with this design snapshot;
4. return to amended Stage 1 on material data-shape or production-action drift;
5. obtain separate exact approval naming environment, manifest digest,
   migration allowlist, maintenance action, and commands.
