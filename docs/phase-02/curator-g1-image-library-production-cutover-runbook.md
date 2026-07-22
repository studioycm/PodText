# CURATOR-G1 Image Library and Legacy Transition Production Cutover Runbook

> **Superseded for execution on 2026-07-22. Do not run this G1/LMTC-only
> cutover for the approved MediaAsset program.** Preserve this body as
> historical, unexecuted evidence. The active route is
> `docs/phase-02/media-program-context.md`; Package 5 will reconcile a new
> exact MediaAsset migration/conversion runbook. Every real production action
> still requires separate exact approval.

Date: 2026-07-21

Controlling audits and options:

- `LS-20260720-CURATOR-G1-IMAGE-LIBRARY-01` /
  `CURATOR-G1-O2-FULL-APP-OWNED-CURATOR-SURFACE`
- `LS-20260721-CURATOR-G1-LEGACY-MEDIA-TRANSITION-CORRECTION-03` /
  `CURATOR-G1-LMTC-O1-IN-PLACE-JOURNALED-TRANSITION-DEFAULT-FALLBACK`

Status: implementation-reconciled future procedure. This document does not
authorize a deployment, migration, transition, backfill, repair, sanitation,
cache/process action, export/import, or traffic change. None of those actions
ran against the local development database, staging, or production while this
correction was implemented.

## Authority and release boundary

Use one complete release containing both controlling implementations. Never
deploy only the strict gallery without the legacy-transition correction. Every
production mutation requires fresh environment-specific approval naming the
exact action. Stage 2 approval is code authority only.

Do not:

- use broad `php artisan migrate --force`;
- run a broad migration rollback or mixed-batch rollback;
- bulk-apply transitions, repairs, registrations, or SVG sanitation;
- run `curator:sanitize-svgs` without `--dry-run`;
- weaken fixed roots, MIME/extension allowlists, raster normalization, SVG
  sanitation, visibility, reference-key proof, or normal gallery scope;
- delete or expose a disallowed row/file to make a report green;
- sanitize real Media IDs 6 or 7 without their separate exact approval;
- auto-disposition root-level IDs 12-15;
- remove dormant permission tables during this media cutover; or
- affect another tenant on the host.

Keep an operator-owned private evidence bundle outside the release and public
storage. Record environment, UTC and Asia/Jerusalem timestamps, release hash,
database backup ID, storage snapshot ID, migration status, manifest JSON and
digest, every command/exit code, journal operation keys, before/after
checksums, integrity reports, smoke checks, and the final go/rollback decision.

## Phase 0 - Read-only release and tenant preflight

1. Resolve the `current` symlink and record the immutable release directory.
2. Confirm the shell is in this application's release, not a shared host root
   or another tenant.
3. Record `git rev-parse HEAD`; require the canonical LMTC implementation hash
   from its committed handoff and ledger.
4. Require `git status --short --branch` to show no unexplained release edits.
5. Verify this application's shared public storage and `public/storage`
   symlink without following a path into another tenant.
6. Before any process action, prove ownership with
   `ls -l /proc/<pid>/cwd` and the matching application environment. Request
   separate approval for the exact reload/restart.
7. Confirm a rollback release exists. Never edit an active/old release as a
   production fix.

Stop on uncertain release, tenant, storage, process, or hash identity.

## Phase 1 - Backups and pre-schema transition manifest

After separate approval for each backup and maintenance action:

1. Enter the approved maintenance boundary before activating the new normal
   media write surface.
2. Create and verify a database backup.
3. Snapshot this application's shared public storage and the private
   `media-staging` and `media-quarantine` areas.
4. Inventory SHA-256, size, and path without following symlinks for the fixed
   roots `content-groups/covers`, `content-items/images`, `header`, `team`,
   `about`, and `default-images`.
5. Record counts for Curator, ContentGroup, ContentItem, settings, attachment,
   and mutation rows where those tables exist.
6. Run the new release's schema-independent preflight read-only:

```text
php artisan migrate:status
php artisan media:preflight-legacy-transition --json
```

Retain the complete JSON and digest. This first manifest is evidence of the
pre-migration state; it is not the digest to reuse after migrations. Stop if
Curator schema is incomplete, an active reference has no known disposition, a
blocked row has no reason, or the observed inventory differs materially from
the reviewed cutover scope.

## Phase 2 - Exact additive migration allowlist

Request approval that names these exact four files. Apply them as independent
steps while normal traffic remains closed:

```text
php artisan migrate --path=database/settings/2026_07_20_000000_add_public_media_reference_keys.php --path=database/migrations/2026_07_20_000001_add_reference_key_to_curator_table.php --path=database/migrations/2026_07_20_000002_create_media_attachments_table.php --path=database/migrations/2026_07_20_000003_create_media_mutation_operations_table.php --step --force
```

The three relational migrations are additive and data-free. The Spatie
settings migration is reversible but not data-free: it rewrites
`menu_config`, `about_page`, and `default_images` payload shapes with nullable
adjacent reference-key fields.

Never replace the allowlist with broad `migrate --force`; that can also run the
dormant permission migration. Rerun `migrate:status` and prove the four named
files ran. On failure, preserve maintenance mode, logs, backups, and current
state. Do not improvise SQL or a broad rollback.

If empty permission tables already exist, leave them. A targeted future
cleanup needs its own audit and is safer than rolling back the observed mixed
batch.

## Phase 3 - Post-schema read-only closure gate

Run only commands without `--apply`:

```text
php artisan media:preflight-legacy-transition --json
php artisan media:backfill-reference-keys
php artisan media:backfill-attachments
php artisan media:backfill-settings-reference-keys
php artisan media:register-existing-curator-assets
php artisan media:report-integrity --json
php artisan media:repair-mutations
```

Save the new canonical manifest and digest. The manifest has exactly these
dispositions:

- `key_only`: current raster bytes already equal canonical output;
- `normalize_existing`: valid existing raster needs same-ID normalization;
- `sanitize_svg`: valid existing SVG needs the reusable staged sanitizer;
- `import_exact_path`: one referenced fixed-root file has no Curator row;
- `detach_to_default`: an owner reference is unusable and needs explicit
  authorized detach/replacement; and
- `blocked`: no safe automatic action; the reason is evidence, not permission
  to guess.

Stop for duplicate identities, unexpected roots/purposes, missing or changed
sources, conflicting attachments, locked/drifted settings, incomplete
journals, or unreviewed active references. Do not turn a block into an allow by
editing metadata or relaxing policy.

The preflight is deterministic only while decision-bearing database and file
state is unchanged. Any transition, repair, owner edit, attachment write,
settings write, migration, or source-byte change invalidates the retained
digest. Rerun preflight and review a fresh digest after every such change.

## Phase 4 - One exact transition at a time

### Existing row: `key_only`, `normalize_existing`, or `sanitize_svg`

For each exact row, retain its manifest entry including ID, path, purpose,
source SHA-256, normalized/sanitized SHA-256, active references, disposition,
fingerprint, and current digest. Obtain this approval:

> APPROVE CURATOR-G1 LMTC ON `<environment>` FOR MEDIA ID `<id>` WITH
> DISPOSITION `<disposition>`, PATH `<path>`, SOURCE SHA-256 `<sha256>`,
> MANIFEST DIGEST `<digest>`, AND ADMIN ACTOR ID `<actor-id>`. Apply only this
> reviewed row through the journaled transition; preserve its numeric ID;
> reject any drift; and stop on cleanup-pending.

Apply exactly one ID:

```text
php artisan media:transition-legacy <id> --actor=<admin-user-id> --digest=<reviewed-digest> --apply
```

For `key_only`, the compatibility wrapper is also available, but it remains
one-row and exact-digest only:

```text
php artisan media:backfill-reference-keys --media=<id> --actor=<admin-user-id> --digest=<reviewed-digest> --apply
```

Successful outcomes are `key_only_completed`,
`normalize_existing_completed`, or `sanitize_svg_completed`. O1 preserves the
numeric Curator ID. It copies the original to checksum-verified private
quarantine, writes verified normalized/sanitized bytes to a generated public
destination, switches the row and reviewed references in one fenced database
commit, issues the immutable key only with proof, invalidates known old/new
cache identities, removes the old public source only after zero references,
and retains the quarantine evidence.

Do not use `sanitize_svg` on actual IDs 6 or 7 in this general phase; follow the
separate SVG runbook and exact approval contract.

### Rowless fixed-root file: `import_exact_path`

Retain the exact path entry and obtain this approval:

> APPROVE CURATOR-G1 LMTC ON `<environment>` FOR EXACT ROWLESS PATH `<path>`,
> PURPOSE `<purpose>`, SOURCE SHA-256 `<sha256>`, MANIFEST DIGEST `<digest>`,
> AND ADMIN ACTOR ID `<actor-id>`. Import only this reviewed path through the
> journaled proof boundary; reject a row, symlink, root, byte, reference, or
> digest change; and stop on cleanup-pending.

Then run:

```text
php artisan media:transition-legacy --path=<exact-normalized-path> --actor=<admin-user-id> --digest=<reviewed-digest> --apply
```

`media:register-existing-curator-assets` exposes the same bounded executor:

```text
php artisan media:register-existing-curator-assets --apply --actor=<admin-user-id> --path=<exact-normalized-path> --digest=<reviewed-digest>
```

Use one interface for a reviewed operation, not both. A successful first apply
returns `import_exact_path_completed:<media-id>`. An exact retry with the
original digest returns `already_registered` only after actor authorization,
journal/digest match, current row identity, destination existence, and
checksum proof.

### After every apply

Immediately run:

```text
php artisan media:preflight-legacy-transition --json
php artisan media:report-integrity --json
php artisan media:repair-mutations
```

Review and retain the fresh digest before the next row. Never reuse the prior
digest for another candidate.

If apply reports `cleanup_pending`, the database commit is authoritative and
the journal owns cleanup. Do not reapply, restore paths manually, or delete an
artifact. Review the exact operation:

```text
php artisan media:repair-mutations --operation-key=<operation-ulid>
```

Only after separate exact-operation approval run:

```text
php artisan media:repair-mutations --apply --operation-key=<operation-ulid>
```

Before commit, a failed operation leaves the old row/path/source active and
repair can remove only checksum-owned artifacts. After commit, repair verifies
the committed row/destination and resumes cleanup; it does not roll back a
trusted committed identity.

## Phase 5 - Owner repair and default fallback

An unsafe legacy owner row remains invisible to ordinary browse, search,
selection, view, download, rename, swap, and delete. It must not make the
ContentGroup or ContentItem page fail.

For an affected record, an Admin may:

1. leave the typed warning unchanged while a reviewed transition is pending;
2. choose an allowed keyed gallery image through the record repair action; or
3. explicitly confirm detach-to-default.

The repair action carries an opaque server-derived fingerprint, locks and
rechecks the exact owner/role/attachment/path evidence, rejects forged or stale
state, and writes a completed database-only `legacy_owner_repair` journal. It
does not view, trust, mutate, or delete the unsafe old file/row. Detach clears
the owner path/attachment so the normal configured family/global/system
default image chain applies. Public reads also treat the typed unsafe state as
absent without mutating it.

Owner repair is not a bulk transition tool. Review each warning and record its
result. Settings-owned valid rows use the transition engine; do not clear a
settings identity merely to bypass sanitation or proof.

## Phase 6 - Ordered attachment and settings reconciliation

After all executable media transitions/imports are complete and the current
manifest is reviewed:

1. Run `media:backfill-attachments` dry. It may create only unambiguous
   singleton owner-role relationships for already keyed/allowed rows.
2. Obtain approval for attachment backfill with the current digest.
3. Apply and rerun dry:

```text
php artisan media:backfill-attachments --digest=<current-digest> --apply
php artisan media:backfill-attachments
```

4. Rerun preflight; attachment writes changed the digest.
5. Run `media:backfill-settings-reference-keys` dry. It must report no
   transition-pending, detach-to-default, or blocked settings identity.
6. Obtain approval with the fresh digest, apply, and rerun dry:

```text
php artisan media:backfill-settings-reference-keys --digest=<fresh-digest> --apply
php artisan media:backfill-settings-reference-keys
```

7. Rerun preflight, integrity JSON, and mutation-repair dry run.

Settings are last because they resolve immutable key first while retaining the
matching path only as compatibility fallback. A locked setting, path/key
disagreement, stale digest, or changed payload stops the transaction.

## Phase 7 - Explicit blocks and dormant schema

- Root-level IDs 12-15 stay outside fixed roots. Keep them blocked/reportable
  until a later disposition names each row/path and proves move, owner, and
  rollback behavior. Never widen the root policy.
- Missing/corrupt owner media may use explicit record repair/detach; do not
  relabel it as an ordinary orphan candidate.
- Unreferenced disallowed rows remain excluded evidence. Deletion/quarantine
  is a separate approved operation.
- Existing empty permission tables may remain. Their presence does not activate
  Shield or replace legacy authorization. Cleanup is unrelated and must not use
  a broad rollback.

## Phase 8 - Final closure and UI verification

Require:

- no active reference lacking an executable or explicit blocked disposition;
- no unexpected `detach_to_default` item;
- no incomplete mutation;
- every normal gallery row keyed, allowed, public, fixed-root, and backed by
  current destination checksum proof;
- exactly one compatible attachment for each trusted owner role;
- settings key/path pairs reconciled;
- disallowed rows still absent from every ordinary media operation; and
- pre/post row counts, checksums, journal keys, and integrity reports retained.

While still in maintenance, use an Admin account and perform the numbered Local
Front Check from the LMTC handoff. Verify ContentGroup and ContentItem list,
relation, edit, replacement, detach/default, gallery exclusion, settings
defaults, header logos, and public rendering. Do not use IDs 6/7 as a generic
smoke mutation.

Any export/import, broader cache clear, PHP-FPM reload, Horizon/queue restart,
or traffic activation needs separate exact approval. Before reopening traffic,
verify the active release and storage symlink again and obtain the operator go
decision.

## Rollback and recovery rules

1. Keep maintenance active and stop new media writes.
2. Record the exact phase, command, exit code, operation key, manifest digest,
   current row/reference state, and integrity report.
3. For an uncommitted journal, run exact-operation repair dry and request exact
   approval. The old database identity/public source remains authoritative.
4. For committed/cleanup-pending state, finish journal cleanup. Do not manually
   restore the old path over a trusted destination.
5. A business rollback after commit requires separate approval and the verified
   database/storage backup plus quarantine checksum. Preserve the journal.
6. Reactivating the previous release does not reverse database or filesystem
   effects. First prove compatibility with the current additive schema,
   settings shape, paths, and storage.
7. Do not roll down migrations with the new release active. Never use a broad
   batch rollback; the batch may include the dormant permission migration.
8. Never assume a database restore alone reverses files, or a file restore
   alone reverses owner/settings identity.

The cutover is complete only after the evidence bundle, closure gate, Local
Front Check, operator go decision, and final traffic state are all recorded.
Legacy path retirement, a non-null schema constraint, IDs 6/7 sanitation,
root-level disposition, recovery gallery, dependency upgrades, and permission
schema cleanup remain outside this cutover unless separately approved.
