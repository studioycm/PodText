# Media Program Context Helper

Read this file first after context compaction or when resuming media work. Then
read the requirements registry, current project state, active package plan and
current Git diff. Conversation summaries and historical handoffs are evidence,
not current authority.

## Controlling approval

- Audit: `LS-20260723-PODTEXT-MEDIA-P2-INVENTORY-PICKER-REPLACE-01`
- Approved option: `MEDIA-P2-O1-REUSE-PICKER-SAME-PAGE-REPLACE`
- Approved scope: Package 2 inventory-first gallery/diagnostics, authoritative
  resolution and D01 delivery, picker All Media, and same-page podcast/episode
  Add/Replace Image UX only.
- Active package: `MEDIA-P2-INVENTORY-PICKER-REPLACE` (complete locally;
  closeout hash pending).
- Package 1 is complete and hash-stamped. Packages 3-5 remain separately gated.
- No prompt under `prompts/pre-13-prompts/` is active.

## Controlling product rule

If a Curator Media row exists, it appears in All Media. If a managed media file
exists, it appears in Media or Files Discovery. A failed identity, folder,
metadata, filename, size, dimension, normalization, provenance or relationship
rule never makes inventory silently disappear.

## One authority per job

- Local owner relationship: `media_attachments.media_id`.
- Portable identity: `media_assets.reference_key`.
- Provider identity: `media_provider_bindings` maps the asset to the preserved
  Curator numeric ID.
- Current file location: `curator.path`.
- Compatibility mirrors: owner paths, settings paths and Curator's mirrored
  `reference_key`.

After conversion, the attachment's `media_id` chooses the Media row and that
row's path chooses the file. Legacy paths are repaired or reported; they do not
veto an attachment. Reference keys support portable/key lookup operations and
are not file trust, gallery eligibility or display authorization.

## Display and inventory rules

Public fallback occurs only when the canonical attached Media row is missing,
the physical file is missing, the current audience cannot access it, or an
unsanitized SVG cannot be rendered inline.

Null or malformed keys, unusual roots, Hebrew/Unicode names, nested folders,
size, dimensions, stale metadata, legacy-path mismatch, absent checksum and
non-PodText origin are not fallback or inventory-exclusion reasons.

Admin inventory consists of:

- All Media: every Curator Media row;
- Needs Repair: a filter/view for actionable problems, never an exclusion
  universe;
- Files Discovery: managed files without Media rows; and
- Trash: intentionally removed restorable media.

The picker may start with a logical folder/slot filter, but All Media clears it.
Selecting an existing image never clones, copies, moves or normalizes it.
Cancelling a staged Gallery selection changes no owner, row, file or journal.
The reused picker's explicit Upload command remains an immediate library write;
staging new uploads until owner save belongs to Package 3.

## Boundary-specific validation

- Unique reference keys are required only for portable/key operations.
- Missing keys are generated during conversion or explicit repair.
- MIME, extension, upload limits and filename cleanup govern new input.
- Containment governs delivery and physical mutation.
- File existence governs preview/delivery/fallback, not row visibility.
- Audience visibility governs delivery, never admin inventory visibility.
- Unsanitized SVG cannot render inline.
- URL/Spotify acquisition retains SSRF, redirect and DNS controls.
- Mutation journals govern actual move, rename, replace, trash, restore and
  purge only.
- Actively referenced media cannot be deleted.

## Package sequence

1. Minimal MediaAsset kernel and database-only Curator conversion — complete.
2. Inventory-first gallery, Needs Repair diagnostics, simplified resolution,
   attachment authority, settings/public fallback, picker All Media and
   same-page Add/Replace Image UX — complete locally.
3. Gallery/Upload/URL/Storage acquisition; Spotify feeds URL; validation applies
   to new inputs and SVG inline rendering.
4. Podcast/episode image hover, detail, download, copy, change and association
   repair/default UX.
5. Files Discovery and physical lifecycle; journals apply only to real file
   mutations.

## Baselines

- Package 2 implementation baseline: `main` at
  `39420d1f21fbe43e193913fc59d6d9efea5ced66`, 15 commits ahead of
  `origin/main`.
- At Stage 1: 79 modified tracked files and 54 default-status untracked entries
  formed the unfinished overbuilt Package 1 draft. Exact cleanup enumeration
  resolved 55 files because `tests/Support/` had been collapsed to one entry.
- Local data: the latest dated operator evidence says all 15 Curator rows have
  valid unique keys. This task does not claim a fresh live check.
- Production: the latest dated snapshot remains pre-G1 with 403 Curator rows,
  108 matched covers, three SVGs, five rowless managed files, one oversized
  raster and two duplicate-byte pairs.
- Package 2 implementation and tests use test databases and fake storage only;
  they do not execute migrations, conversion or media operations against local
  development or production.

## Drift checkpoint

At the start, after docs, before/after cleanup, before/after every package and
before gates/commits, reread this file, the registry, approved audit, active
plan and current diff. Record whether inventory is complete, key/path rules
became visibility vetoes, rejected byte machinery returned, `media_id` remains
owner authority, D01 remains narrow, Needs Repair remains visible, picker All
Media clears its default filter, validators remain boundary-specific, a
dedicated security audit appeared, or a live action was assumed. Correct drift
before proceeding.

## Hard exclusions

- No dependency changes.
- No dedicated security audit or new security architecture.
- No real local or production DB/storage/cache action.
- No production action without a separate exact approval.
- No raster normalization, checksum proof, file relocation, quarantine,
  conversion manifest/digest/schema hash or new conversion journal.
- Selecting existing media performs attachment-only database changes; it never
  copies, moves, renames or normalizes the selected row or file.
- No Package 3 URL/Storage acquisition, Package 4 owner-tools expansion, or
  Package 5 Files Discovery/lifecycle work.
- No branch/worktree change, push, broad reset, stash, checkout or deletion.
