# Media Program Context Helper

Read this file first after context compaction or when resuming media work. Then
read the requirements registry, current project state, active package plan and
current Git diff. Conversation summaries and historical handoffs are evidence,
not current authority.

## Controlling approval

- Audit: `LS-20260723-MEDIA-INVENTORY-FIRST-RESET-01`
- Approved option: `MEDIA-INV-O1-RESET-CLEANUP-P1-MINIMAL-KERNEL`
- Approved scope: canonical-document reset, exact cleanup of the unfinished
  Package 1 overbuild, and Package 1 minimal kernel/database conversion only.
- Active package: `MEDIA-P1-KERNEL-CONVERSION`.
- Packages 2-5 remain separately gated. Package 2 requires a fresh Simplifier
  audit after Package 1 changes the baseline.
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

1. Minimal MediaAsset kernel and database-only Curator conversion.
2. Inventory-first gallery, Needs Repair diagnostics, simplified resolution,
   attachment authority, settings/public fallback and picker All Media.
3. Gallery/Upload/URL/Storage acquisition; Spotify feeds URL; validation applies
   to new inputs and SVG inline rendering.
4. Podcast/episode image hover, detail, download, copy, change and association
   repair/default UX.
5. Files Discovery and physical lifecycle; journals apply only to real file
   mutations.

## Baselines

- Approved implementation baseline: `main` at
  `b455d5d546c5902edebaade2ad31c34bbfef3d2f`, 13 commits ahead of
  `origin/main`.
- At Stage 1: 79 modified tracked files and 54 default-status untracked entries
  formed the unfinished overbuilt Package 1 draft. Exact cleanup enumeration
  resolved 55 files because `tests/Support/` had been collapsed to one entry.
- Local data: the latest dated operator evidence says all 15 Curator rows have
  valid unique keys. This task does not claim a fresh live check.
- Production: the latest dated snapshot remains pre-G1 with 403 Curator rows,
  108 matched covers, three SVGs, five rowless managed files, one oversized
  raster and two duplicate-byte pairs.
- Package 1 implementation and tests do not execute migrations or conversion
  against the local development or production environment.

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
- No raster byte reads, normalization, checksum proof, file relocation,
  quarantine, conversion manifest/digest/schema hash or conversion journal.
- No filesystem-only import during Package 1.
- No branch/worktree change, push, broad reset, stash, checkout or deletion.
