# CURATOR-G1 Full Image Library Production Cutover Runbook

Date: 2026-07-20

Audit: `LS-20260720-CURATOR-G1-IMAGE-LIBRARY-01`

Approved implementation option:
`CURATOR-G1-O2-FULL-APP-OWNED-CURATOR-SURFACE`

Status: procedure only. Nothing in this runbook was executed against the local
development database, staging, or production during Stage 2.

## Authority boundary

This is a one-maintenance-window cutover for the complete Curator G1 release.
Do not deploy only a subset of its code, migrations, settings shape, commands,
or UI surfaces.

Every production mutation requires explicit approval in the session that will
run it. Approval of Stage 2 implementation is not approval to:

- deploy or activate a release;
- enable maintenance mode;
- migrate a database or settings payload;
- create a database or storage backup;
- apply a backfill or repair;
- create a production export or run any production import;
- upload, move, replace, quarantine, or delete media;
- clear caches or reload/restart processes; or
- perform the separate SVG operation for Media IDs 6 and 7.

Read-only status, report, checksum, and dry-run commands may be used only after
the operator confirms the exact environment and release. Never print `.env`,
credentials, tokens, license data, Composer authentication, or secrets.

## Non-negotiable exclusions

This cutover does not authorize:

- deletion or quarantine of disallowed existing rows/files;
- in-place registration of unvalidated legacy bytes;
- `curator:sanitize-svgs` without `--dry-run`;
- sanitation of Media IDs 6 and 7;
- legacy-path column/key retirement;
- a non-null `curator.reference_key` migration;
- Media ZIP import or remote-media fetching during import;
- dependency changes, `vendor:publish`, or Spatie Media Library;
- a partial rollout; or
- changes to any other tenant on the multi-tenant host.

## Required inputs and evidence bundle

Before requesting a mutation approval, create an operator-controlled evidence
bundle outside the release and outside publicly served storage containing:

1. environment name and UTC/Asia-Jerusalem timestamps;
2. resolved `current` release path and deployed Git hash;
3. database backup identifier, completion state, size, and provider checksum;
4. shared `storage/app/public` snapshot identifier and completion state;
5. SHA-256 inventory for these exact roots:
   `content-groups/covers`, `content-items/images`, `header`, `team`, `about`,
   and `default-images`;
6. `migrate:status` before and after;
7. every report/dry-run/apply command and complete exit status;
8. pre/post media-integrity JSON;
9. pre/post portable settings and native content export evidence;
10. image ZIP `manifest.json` verification evidence;
11. smoke-check and Local Front Check results; and
12. rollback decision, if any.

Keep the bundle private. Reports can contain record IDs, storage paths, and
editorial references.

## Multi-tenant and release preflight

Perform these read-only checks before any process or write action:

1. Resolve the site's `current` symlink and record the immutable release path.
2. Confirm the shell working directory is that release, not the shared host
   root or another tenant.
3. Record `git rev-parse HEAD` and require the canonical CURATOR-G1
   implementation hash recorded in its handoff/ledger.
4. Run `git status --short --branch` and require the deployed release checkout
   to have no unexplained changes.
5. If any process action is proposed, identify every target with
   `ls -l /proc/<pid>/cwd` and verify both this release path and this
   application's environment before requesting approval.
6. Confirm the public disk points at this application's shared storage and that
   the storage symlink is valid.
7. Confirm a rollback release artifact exists. Never edit files inside an old
   or current release as a production fix.

Stop if release identity, tenant ownership, shared-storage ownership, or the
deployed hash is uncertain.

## Phase 1 - Backup and pre-cutover read-only evidence

After separate approval for each backup action:

1. Put the public site into the operator-approved maintenance boundary while
   retaining authorized Admin access needed for the cutover.
2. Create and verify a database backup using the hosting provider's supported
   mechanism.
3. Snapshot this application's shared public storage and the private local disk
   locations used by `media-staging` and `media-quarantine`.
4. Generate the six-root SHA-256 inventory without following symlinks outside
   this application's storage.
5. Record current row counts for `curator`, `content_groups`, `content_items`,
   `settings`, and, if already present, `media_attachments` and
   `media_mutation_operations`.

Then run only read-only framework checks:

```text
php artisan migrate:status
```

Do not run `media:register-existing-curator-assets` before the four G1
migrations. The command intentionally fails if the app-owned media schema is
incomplete.

## Phase 2 - Activate the complete release and apply additive schema

Request explicit approval for the exact deploy/activation and migration
actions. The new release must not serve normal traffic until the additive
schema and settings-shape migration are complete.

The approved migration command is the repository's normal production migration
entry point:

```text
php artisan migrate --force
```

It discovers both the three relational migrations and the Spatie settings
migration:

- `2026_07_20_000001_add_reference_key_to_curator_table`
- `2026_07_20_000002_create_media_attachments_table`
- `2026_07_20_000003_create_media_mutation_operations_table`
- `2026_07_20_000000_add_public_media_reference_keys`

After migration, rerun `php artisan migrate:status` and confirm the expected
four entries only. On failure, keep maintenance mode active, preserve logs and
backups, and follow the rollback section. Do not improvise a manual schema
change.

## Phase 3 - Dry-run inventory and stop/go decision

Run these commands without `--apply`:

```text
php artisan media:backfill-reference-keys
php artisan media:backfill-attachments
php artisan media:backfill-settings-reference-keys
php artisan media:report-integrity --json
php artisan media:repair-mutations
php artisan media:register-existing-curator-assets
```

Stop the cutover if any of the following is unresolved:

- duplicate disk/path locations;
- invalid attachment type, role, owner, media, position, or purpose;
- attachment/legacy-path disagreement;
- settings key/path disagreement or ambiguous nested identity;
- an unexpected disallowed row needed by a current public/admin consumer;
- missing source files required by a current reference;
- incomplete mutation operations;
- a backfill conflict or locked settings property;
- an unexpected number of eligible unregistered paths; or
- any candidate outside the six fixed roots or exact MIME/extension contract.

Disallowed and orphaned rows may remain reported and excluded; do not delete or
quarantine them in this run. Record their separately reviewed disposition.

### Eligible unregistered legacy paths

Never create a Curator row pointing at the existing public bytes and never
rename the source in place. For every reviewed eligible path, first retain its
dry-run row and obtain exact per-path mutation approval. Required approval text:

> Approve CURATOR-G1 legacy registration on `<environment>` for exact path
> `<normalized-path>`, source SHA-256 `<sha256>`, purpose `<purpose>`,
> `<reference-count>` reviewed app references, using Admin actor ID
> `<admin-user-id>`. This approves private quarantine/staging, generated public
> destination creation, atomic owner/settings switching, settings-cache
> invalidation, and removal of the old public source after zero old references.

Then run, for that one approved path only:

```text
php artisan media:register-existing-curator-assets --path=<exact-normalized-path>
php artisan media:register-existing-curator-assets --apply --actor=<admin-user-id> --path=<exact-normalized-path>
php artisan media:register-existing-curator-assets --path=<same-old-path>
```

The first dry run must exactly match the approved path, purpose, SHA-256, and
reference count. Apply privately quarantines the original, privately stages
normalized/sanitized bytes, writes a generated destination, creates one
immutable Media identity, and atomically switches every reviewed owner and raw
settings reference. No UI re-selection is required. Successful cleanup removes
the old public source only after references switch; the checksum-verified
private quarantine remains.

The final exact-path dry run must report `already_registered`. If it reports
`registration_cleanup_pending`, stop and request approval for the exact journal
operation before running `media:repair-mutations`; do not reapply registration.
If the source path reappeared with new references, the command must present a
new eligible plan instead of treating the prior operation as current.

If the number of assets cannot be reviewed and switched within the approved
window, roll back or extend the window through fresh operator direction. Do not
open normal traffic with a knowingly partial UI write cutover.

## Phase 4 - Register legacy paths, then apply identity backfills

Complete every approved exact-path registration from Phase 3 first. Then
request explicit approval for these exact mutations and run them in order:

```text
php artisan media:backfill-reference-keys --apply
php artisan media:backfill-attachments --apply
php artisan media:backfill-settings-reference-keys --apply
```

Rerun the three dry runs immediately. Require zero remaining eligible updates
and zero conflict/failure result. Then run:

```text
php artisan media:report-integrity --json
php artisan media:repair-mutations
```

Require:

- every normal gallery/picker row to have a valid immutable key;
- every keyed row to have a matching current destination SHA-256 journal proof;
- every current ContentGroup cover and ContentItem primary image with a trusted
  Media row to have exactly one typed singleton attachment;
- settings identities to resolve key first with matching compatibility path;
- no incomplete operation requiring repair; and
- all disallowed rows to remain excluded and unchanged.

`media:backfill-reference-keys` keys only content-proven raster whose current
bytes already equal the canonical normalized output; it writes a completed
`reference_key_backfill` checksum-proof operation atomically. Invalid,
missing, private, duplicate, metadata-mismatched, or noncanonical rows remain
unkeyed and reported. Every existing SVG row remains unkeyed pending the
separate SVG runbook. Explicitly verify Media IDs 6 and 7 are still unchanged,
unkeyed/excluded, and SHA-256-identical to the pre-cutover inventory.

Do not run `media:repair-mutations --apply` unless the dry-run identifies exact
operation keys and the operator separately approves those keys. Prefer:

```text
php artisan media:repair-mutations --apply --operation-key=<approved-ulid>
```

over a broad repair.

## Phase 5 - Dual-read and UI write verification

While maintenance mode remains active, use an Admin-or-higher account:

1. Open the Media Resource and confirm only public, allowed, keyed records from
   fixed roots appear, 25 per page.
2. Search for a known image and confirm no more than 50 projected results.
3. Open one ContentGroup and one ContentItem with existing images. Confirm the
   picker resolves the immutable key and shows the same image/path.
4. Save each unchanged. Confirm the attachment remains singleton and its
   compatibility path still matches.
5. Upload one temporary approved raster through a finite purpose, attach it to
   a disposable test owner, detach it, and use the app-owned delete action only
   after the report shows no references. Retain the journal evidence.
6. Confirm referenced records cannot be renamed, swapped, or deleted.
7. Verify light/dark menu logos, about images, team images, defaults, group
   covers, and item primary images through their real consumers.
8. Confirm RichEditor/MarkdownEditor attachments remain unavailable.

Do not use Media IDs 6 or 7 for a mutation check.

## Phase 6 - Portable export/import and image ZIP evidence

The portable identity order is Media rows first, then owner/settings data.
Imports never fetch remote media and fail when a referenced key is unavailable.
Before creating any production export or running any production import in this
phase, request fresh approval naming the environment, exact action, selected
record/package scope, output location, and rollback/retention plan. Stage 2
approval does not authorize these actions.

1. Export ContentGroups and ContentItems through the native Filament export
   actions. Confirm the files contain `cover_media_reference_key` and
   `primary_image_media_reference_key`, not mutable media paths or numeric Media
   IDs.
2. Export public settings through its portable package action. Confirm every
   configured image has a media reference key and no portable legacy path.
3. Run the content-images ZIP export action for a bounded reviewed selection.
   Confirm `manifest.json` maps media reference key, owner reference key, role,
   archive filename, validated MIME/extension, and SHA-256.
4. Verify each archive byte checksum against the manifest in private scratch
   storage. Do not serve or import the ZIP directly.
5. Rehearse any import on staging or the isolated test environment first. On
   production, import only after confirming every referenced Media key already
   exists and is allowed for its role. A missing, wrong-purpose, private, or
   disallowed key must fail the row.
6. Keep ZIP media import deferred; this release implements export manifest
   evidence, not a Media ZIP importer.

## Phase 7 - Cache, process, and traffic activation

The coordinator invalidates old/new mutation cache identities. If a broader
application cache clear, PHP-FPM reload, Horizon restart, or queue restart is
still needed, name the exact action and obtain separate approval first. Verify
the process belongs to this release/application before acting.

Do not register raster curation dimensions in this cutover. Curations remain
disabled because no rendered-width/DPR measurement was approved.

Before reopening traffic:

1. rerun the integrity JSON report and retain it;
2. require zero unresolved key/path, attachment, duplicate, or mutation issue;
3. run the numbered Local Front Check from the CURATOR-G1 handoff;
4. confirm queues have no failed image-download job from the cutover;
5. verify the active release and storage symlink again; and
6. obtain the operator's go decision.

## Rollback

If a stop condition occurs:

1. Keep maintenance mode active and stop further Curator writes.
2. Record the exact failed phase, command, exit code, journal operation keys,
   and current integrity report.
3. If only application activation failed, reactivate the preceding release.
   The additive tables/nullable column can remain because old code ignores them
   and continues reading compatibility paths.
4. Restore settings payloads from the verified pre-cutover backup if a settings
   backfill or registration switch must be reversed.
5. For a registration rollback, restore the exact old owner/settings
   identities from the reviewed fingerprint/backup and restore the old public
   source from its checksum-verified private quarantine or storage snapshot.
   Obtain separate approval before disposing of the generated Media row or
   destination, and preserve the registration journal permanently.
6. Restore or remove newly created attachment rows only from the verified
   backup/diff and only under a separately approved rollback action.
7. Restore any other file from private quarantine/snapshot only after matching its
   recorded SHA-256 and proving that the target path is not owned by another
   Media row.
8. Do not roll down migrations while the new release is active. Roll them down
   in reverse order only after old-code stability, export of attachment/journal
   evidence, and explicit approval for data removal.
9. Never assume a database rollback reverses filesystem effects. Use the
   mutation journal, storage snapshot, and checksum inventory.
10. Leave IDs 6 and 7 unchanged; their rollback belongs to the separate SVG
   runbook.

## Completion record

The cutover is complete only when the evidence bundle contains the approved
release hash, backups, pre/post reports, zero applicable dry-run work, portable
export/manifest verification, smoke checks, operator go decision, and final
traffic state. Record any disallowed/orphan disposition as a future separately
approved task. Do not mark legacy paths retired or `reference_key` proven
non-null in this release.
