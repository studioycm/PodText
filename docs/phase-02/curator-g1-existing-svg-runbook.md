# CURATOR-G1 Existing Header SVG Sanitation Runbook

Date: 2026-07-21

Controlling audits and options:

- `LS-20260720-CURATOR-G1-IMAGE-LIBRARY-01` /
  `CURATOR-G1-O2-FULL-APP-OWNED-CURATOR-SURFACE`
- `LS-20260721-CURATOR-G1-LEGACY-MEDIA-TRANSITION-CORRECTION-03` /
  `CURATOR-G1-LMTC-O1-IN-PLACE-JOURNALED-TRANSITION-DEFAULT-FALLBACK`

Status: reusable app-owned sanitation is implemented and tested with isolated
fixtures. This is a future environment-specific procedure only. No command in
this document ran against actual Curator Media IDs 6 or 7, local development,
staging, or production during implementation.

## Scope and authority

Prior read-only evidence identified IDs 6 and 7 as header SVG rows with null
keys whose sanitized bytes change. That evidence is not apply authority. Each
real ID needs its own reviewed manifest entry, digest, source checksum, actor,
backup, maintenance window, and exact approval. Never approve both IDs with one
apply command.

The reusable executor has no ID-specific bypass. It accepts any single existing
SVG only when the normal planner proves:

- one unique Curator row and exact server-owned ID/path;
- public disk and visibility;
- exact `header/` fixed root and SVG purpose;
- coherent path, directory, name, MIME, extension, size, and dimensions;
- a canonical local public source that is not a symlink;
- bounded source bytes that parse and sanitize successfully;
- exact source and sanitized SHA-256 values;
- a closed, current manifest and exact reviewed digest;
- unchanged owner, attachment, settings, and journal state; and
- Admin-or-higher `transitionLegacy` authorization.

Normal gallery, picker, preview, view, download, rename, swap, delete, and
selection continue to exclude the row before proof. Do not use the vendor
sanitizer apply path: `curator:sanitize-svgs` without `--dry-run` is unbounded
and bypasses the app-owned journal/digest contract.

## Required private evidence

Before requesting any mutation:

1. Confirm environment, immutable release path, canonical LMTC implementation
   hash, and this application's shared-storage root.
2. Require the complete G1/LMTC release and four exact G1 migrations to be
   active while normal media writes remain in the approved maintenance
   boundary.
3. Capture database identity for the one requested ID: key, disk, visibility,
   directory, name, path, type, extension, size, dimensions, timestamps,
   attachments, owner paths, and settings locations.
4. Require `public`, `public`, `header`, `image/svg+xml`, and `svg` respectively.
5. Create and verify the separately approved database backup and shared public
   plus private staging/quarantine storage snapshot.
6. Save the original length and SHA-256 and a private copy of the original.
7. Save the preflight manifest entry, its source/sanitized SHA-256 values,
   fingerprint, and complete manifest digest.
8. Inspect the expected visual/semantic result. Sanitation removes unsafe or
   unsupported content; checksum success alone is not visual approval.
9. Confirm sufficient private staging/quarantine space and a tested rollback
   source.
10. Stop all other Curator writes for the reviewed window.

Optional comparison only:

```text
php artisan curator:sanitize-svgs --dry-run
```

Never run the vendor command without `--dry-run`.

## Read-only plan and stop conditions

Run and retain:

```text
php artisan media:preflight-legacy-transition --json
php artisan media:report-integrity --json
php artisan media:repair-mutations
```

The exact row must be `sanitize_svg` with reason
`svg_requires_staged_sanitation`. Stop if it is missing, already changed,
blocked, detach-to-default, outside `header/`, duplicated, missing/nonpublic,
metadata-incoherent, malicious/unparseable, settings-locked, attached in an
unexpected role, or covered by an unfinished journal.

The sanitizer rejects scripts, event handlers, `foreignObject`, DTD/entities,
unsafe links, external resources, executable content, and invalid XML/SVG.
Spoofed MIME/extension is not enough to enter the executor.

## Exact approval and one-ID apply

Use this exact form separately for each ID:

> APPROVE CURATOR-G1 LMTC SVG SANITATION ON `<environment>` FOR MEDIA ID
> `<id>` ONLY, PATH `<path>`, SOURCE SHA-256 `<source-sha256>`, SANITIZED
> SHA-256 `<sanitized-sha256>`, MANIFEST DIGEST `<digest>`, AND ADMIN ACTOR ID
> `<actor-id>`. Preserve the numeric Curator ID; use the app-owned journaled
> same-ID transition; switch only reviewed references; retain checksum-verified
> private quarantine; invalidate owned caches; reject any drift; and stop on
> cleanup-pending. This does not approve any other ID or the vendor sanitizer.

Apply exactly one positional ID:

```text
php artisan media:transition-legacy <id> --actor=<admin-user-id> --digest=<reviewed-digest> --apply
```

Never pass two IDs. Never use `--path` for an existing row. Never reuse a
digest after the first ID changes state: rerun and re-review preflight before
requesting approval for the second ID.

The executor:

1. replans and locks the current row/reference state;
2. opens the durable `legacy_transition` journal/fence;
3. copies original bytes to checksum-verified private quarantine;
4. sanitizes/reparses in private staging;
5. writes a generated `header/` destination and independently verifies it;
6. locks/rechecks row, references, settings, manifest entry, and actor;
7. preserves the numeric ID, updates coherent metadata/path, issues the first
   immutable key, and atomically switches reviewed settings/attachments/paths;
8. records the database commit before destructive cleanup; and
9. invalidates old/new cache identities, removes the old public source only
   after zero references, removes staging, retains quarantine, and completes
   the journal.

Success returns `sanitize_svg_completed`. `cleanup_pending` is a committed
state that needs exact-operation repair, not a second sanitation apply.

## Post-ID checks

Immediately run:

```text
php artisan media:preflight-legacy-transition --json
php artisan media:report-integrity --json
php artisan media:repair-mutations
php artisan media:backfill-settings-reference-keys
```

Require:

- the same numeric ID and one immutable reference key;
- one generated `header/` public path and matching coherent metadata;
- destination checksum equals the reviewed sanitized checksum;
- old public path has zero references and the quarantine checksum equals the
  original source checksum;
- header settings resolve key first with the matching compatibility path;
- no unexpected attachment, owner, settings, duplicate, or journal issue;
- no old-path application request; and
- the next manifest/digest is retained before any second operation.

If settings reconciliation is still legitimately pending, follow the ordered
cutover runbook with the newly generated digest. Do not write settings by hand.

## Visual verification matrix

For the exact light/dark logo slot affected, verify in Hebrew and English at
desktop and mobile widths:

1. Open the public header and confirm geometry, color, transparency, sharpness,
   aspect ratio, accessible name, and link.
2. Toggle light/dark mode twice and confirm the correct variant, no flash, and
   no stale/broken source.
3. Navigate homepage, search, ContentGroup, ContentItem, About, and contributor
   pages and confirm the logo remains stable.
4. Open the Admin public-settings surface and confirm immutable key/path
   agreement without exposing the original row bytes.
5. Inspect only the expected logo network request and confirm successful status,
   SVG content type, generated path, and no old-path request.

Record screenshots and request/response metadata without secrets. Do not claim
browser DOM, heap, listener, navigation, or TTFB measurements unless they were
actually measured.

## Cleanup-pending and rollback

For `cleanup_pending`, inspect one operation:

```text
php artisan media:repair-mutations --operation-key=<operation-ulid>
```

After separate approval for that operation key only:

```text
php artisan media:repair-mutations --apply --operation-key=<operation-ulid>
```

Crash before database commit leaves the old identity/source active; repair may
remove only checksum-owned uncommitted artifacts. Crash after commit leaves the
new keyed identity authoritative; repair verifies it and resumes cleanup.

On checksum, identity, authorization, cache, or visual mismatch:

1. Keep maintenance active and stop further Curator writes.
2. Record operation key, manifest digest, exact failure, database state, public
   checksums, quarantine checksums, and integrity report.
3. Do not overwrite either public path or edit the Curator/settings row.
4. Use exact-operation repair for uncommitted or cleanup-pending machinery.
5. A business rollback of a committed sanitation needs separate approval and
   the verified database/storage snapshot plus original quarantine checksum.
6. Prove database and storage consistency before reactivating an older release;
   release rollback alone does not restore files or settings.
7. Repeat integrity, key/path, network, and full visual checks before traffic.

Any broader cache clear, PHP-FPM reload, Horizon/queue restart, or traffic
activation needs separate approval and proven process ownership. Completion of
one ID never authorizes the other.
