# CURATOR-G1 Existing Header SVG Runbook

Date: 2026-07-20

Audit: `LS-20260720-CURATOR-G1-IMAGE-LIBRARY-01`

Approved implementation option:
`CURATOR-G1-O2-FULL-APP-OWNED-CURATOR-SURFACE`

Status: future procedure only. Stage 2 did not sanitize, rewrite, move, key,
attach, quarantine, or delete Curator Media IDs 6 or 7 in any environment.

## Exact scope and authority

Prior operator dry-run evidence identifies exactly Media IDs 6 and 7 beneath
`header/` as SVG rows whose sanitized bytes would change. That evidence is not
authority to run the operation.

This operation is separate from the full image-library deployment and requires
new environment-specific approval. Never broaden it to another ID, path, disk,
purpose, settings field, or file. Stop if either row is absent or if any observed
identity differs from the reviewed evidence.

The Stage 2 reference-key backfill deliberately reports existing null-key SVG
rows as `pending_svg_sanitation`; it does not pass them through the sanitizer or
make them selectable. The existing package command may be used only in dry-run
mode for comparison:

```text
php artisan curator:sanitize-svgs --dry-run
```

Never run that command without `--dry-run`. Its apply path is not ID-bounded and
does not use the app-owned journaled coordinator.

## Required preflight evidence

Before requesting mutation approval:

1. Confirm the environment, resolved immutable release path, deployed Git hash,
   and this application's shared-storage path.
2. Require the complete CURATOR-G1 release, migrations, and full production
   cutover to be green; do not combine this operation with a partial deploy.
3. Record read-only database evidence for IDs 6 and 7: immutable ID, current
   `reference_key`, disk, visibility, directory, name, path, type, extension,
   size, width/height, and timestamps.
4. Require both rows to use disk `public`, visibility `public`, root `header`,
   type `image/svg+xml`, and extension `svg`.
5. Record every legacy path, attachment, and settings key reference. Expect only
   reviewed header-logo settings identities; stop on any unknown reference.
6. Create and verify an operator-controlled database backup and this
   application's shared/public plus private staging/quarantine storage snapshot.
7. Capture the original byte length and SHA-256 for both public files. Copy the
   two original SVGs into the private evidence bundle without changing them.
8. Save the sanitizer dry-run output, semantic SVG inspection, and proposed
   sanitized byte length/SHA-256 for each ID.
9. Confirm enough private storage exists for staging, quarantine, and rollback.
10. Put the site in the approved maintenance boundary and stop all Curator writes
    before any settings or media mutation.

If process control is required on the multi-tenant host, identify each process
with `ls -l /proc/<pid>/cwd` and verify this release/application before asking
for approval. Never affect another tenant.

## Required app-owned executor

Stage 2 intentionally provides no broad or automatically runnable SVG apply
command. Before the approved environment operation, the exact executor must be
reviewed as a narrowly scoped extension of the app-owned coordinator and must:

1. accept only IDs 6 and 7 and reject every other row;
2. reload and lock each row through a transitional server-owned scope;
3. require the exact reviewed disk, visibility, root, MIME, extension, path, and
   original SHA-256;
4. preserve the numeric Media ID and any existing immutable reference key, or
   assign a first reference key only inside the same proven transaction;
5. sanitize in private staging, reparse the sanitized SVG, and reject scripts,
   event attributes, `foreignObject`, DTD/entities, unsafe links, external
   references, executable content, and invalid SVG;
6. journal source/staging/destination/quarantine paths and SHA-256 values;
7. copy sanitized bytes to a generated `header/` destination and verify them;
8. lock and update the Media row and the exact reviewed settings identity in one
   short transaction;
9. quarantine the original only after commit, invalidate old/new Glide and
   application cache identities, and leave repairable journal state on failure;
10. support idempotent repair and refuse cleanup while any old-path reference
    remains.

Do not approximate this with a direct database update, in-place file overwrite,
manual storage rename, vendor observer, or unbounded sanitizer command.

## Staging rehearsal

After explicit staging approval:

1. Restore a verified production-equivalent database and storage snapshot into
   the isolated staging environment.
2. Reconfirm IDs 6 and 7 and compare their original SHA-256 values with the
   approved evidence.
3. Clear only the reviewed header-logo settings selections needed to release the
   mutation policy, preserving an exact before payload in the evidence bundle.
4. Execute the reviewed app-owned operation for ID 6 only; verify its completed
   journal before proceeding to ID 7.
5. Execute the reviewed app-owned operation for ID 7 only.
6. Re-select the same immutable Media identities in the exact light/dark logo
   slots and require key/path agreement.
7. Verify the numeric IDs and reference keys did not change, each generated path
   is beneath `header/`, each old file is privately quarantined, and each new
   public checksum equals the reviewed sanitized checksum.
8. Rerun the integrity report, reference-key/settings dry runs, and mutation
   repair dry run. Require no unexpected issue.
9. Complete the visual matrix below and record screenshots.
10. Rehearse rollback from the verified database/storage snapshot; do not treat
    an untested rollback description as sufficient production evidence.

## Visual-verification matrix

For both Hebrew and English, verify all of the following in desktop and mobile
widths:

1. Open the public header in light mode and confirm the light logo's geometry,
   color, transparency, sharpness, aspect ratio, accessible name, and link.
2. Open the public header in dark mode and confirm the dark logo with the same
   checks.
3. Toggle light/dark mode twice and confirm no stale source, flash, broken image,
   or wrong variant remains.
4. Navigate between the homepage, search, ContentGroup, ContentItem, About, and
   contributor pages and confirm the logo remains stable.
5. Open the Admin panel and relevant public settings surface and confirm both
   records resolve by immutable key with matching compatibility paths.
6. Inspect browser network responses only for the two logo assets and confirm
   the expected SVG content type, successful status, and no old-path request.

Do not claim browser DOM, heap, listener, navigation, or TTFB performance from
server-side tests; record those only if a browser measurement was actually run.

## Cache and completion checks

The app-owned executor must invalidate both old and new cache identities. Any
broader application cache clear, PHP-FPM reload, or queue restart needs separate
approval and verified process ownership.

Before reopening traffic, require:

- completed journal entries for IDs 6 and 7;
- no old-path application reference;
- exact key/path agreement in header settings;
- green integrity and repair dry runs;
- verified original and sanitized checksums;
- completed Hebrew/English, light/dark, desktop/mobile visual checks; and
- an explicit operator go decision.

## Rollback

On any checksum, identity, policy, cache, or visual mismatch:

1. Keep maintenance mode active and stop further Curator writes.
2. Record the operation keys, exact failure, current database state, public file
   checksums, quarantine checksums, and integrity report.
3. Do not overwrite a public file or edit a Media path manually.
4. If the journal is uncommitted, run only the exact reviewed repair dry run and
   request approval for that operation key before apply.
5. If database state committed, clear only the affected header selections and
   restore the coordinated database/settings and storage snapshot verified in
   rehearsal. Confirm the restored files match the original SHA-256 values.
6. Reactivate the previous release only after its database compatibility and
   storage view are proven. A release rollback alone does not reverse files.
7. Rerun integrity/reference reports and the complete visual matrix before
   requesting traffic activation.

## Exact future approval text

Staging:

> APPROVE CURATOR-G1 SVG SANITATION IN STAGING: during the approved maintenance window, operate only on Curator Media IDs 6 and 7 after verified database/storage backups and SHA-256 capture; clear only their header settings references, sanitize by app-owned journaled swap, reattach the same immutable media reference keys, invalidate verified caches, perform Hebrew/English light/dark desktop/mobile visual checks, and roll back on any checksum, scope, or visual mismatch.

Production:

> APPROVE CURATOR-G1 SVG SANITATION IN PRODUCTION: during the approved maintenance window, operate only on Curator Media IDs 6 and 7 after verified database/storage backups and SHA-256 capture; clear only their header settings references, sanitize by app-owned journaled swap, reattach the same immutable media reference keys, invalidate verified caches, perform Hebrew/English light/dark desktop/mobile visual checks, and roll back on any checksum, scope, or visual mismatch.

These sentences authorize only the described environment operation after its
executor and evidence are reviewed. They do not authorize deployment, backup,
maintenance mode, cache/process action, or another Media ID unless those actions
are separately named and approved.
