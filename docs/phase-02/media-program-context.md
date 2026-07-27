# Media Program Context Helper

Read this file first after context compaction or when resuming media work. Then
read the requirements registry, current project state, active package plan and
current Git diff. Conversation summaries and historical handoffs are evidence,
not current authority.

## Media Operations UX3 forward route

- Media Operations UX3 Mini-tasks 1–3 are implemented locally and are the
  accepted UX baseline. The documentation-only **Program Reconciliation and
  Finding Coverage** contract at
  `prompts/pre-13-prompts/media-operations-ux3-program-reconciliation-finding-coverage-codex-prompt.md`
  v1 has been executed and was accepted by the operator on 2026-07-26.
- Its canonical result is
  `docs/research/media-operations-ux3/07-program-reconciliation-and-finding-coverage.md`
  and owns the detailed UX3 goal, 51-finding matrix, outcome sequence and
  gates. Rolling execution status remains in
  `docs/phase-02/current-project-state.md` and the mini-step ledger.
- Mini-task 3A — Owner Image Choice and Commit is implemented locally under
  its approved primary, cross-task, localization and event/bridge audits. Its
  handoff is
  `docs/phase-02/media-operations-ux3-mini3a-owner-image-choice-and-commit-handoff.md`.
  The remaining order after operator review and closure of 3A is:
  **Mini-task 3B — Media Intake and Acquisition Results** →
  **Mini-task 3C — Safe Existing-File Operations and Outcomes** →
  **replanned Mini-task 4 — provisional boundary: Reason-Specific Media Issue
  Resolution and Verified Results**. These are UX3 Mini-tasks, not original
  Media Program Packages.
- Mini-task 4 must not begin UX research, technical research, planning,
  Laravel Simplifier Stage 1 or implementation until Mini-task 3C has been
  reviewed and closed. After 3C closes, Mini-task 4 restarts at UX
  research/design and is replanned from the reconciled 3A–3C evidence. No
  earlier Mini-task 4 title, scope, repair reason, plan, audit or option is
  authoritative. The completed choice, acquisition-result and existing-file
  operation contracts determine which first reason-specific repair is
  truthful, valuable and safely bounded.
- Each remaining Mini-task follows its own Operations UX research and
  product-fidelity visual-design decision, followed by a bounded technical
  implementation plan and only then a fresh Laravel Simplifier Stage 1 and
  exact approval before implementation. Package 5 may inform the desired
  end-state but remains a separate future research and implementation seam.
- The later **PodText Documentation Architecture and Consolidation Audit**
  prompt-authoring trigger was satisfied by that acceptance, but the prompt
  has not been written and the task has not started.
- Mini-task 3A implementation is complete locally and is awaiting operator
  outcome review. Mini-task 3B has not started and is not authorized until
  that review closes 3A.

## Original Media Program visual-correction authority

- Audit:
  `LS-20260724-PODTEXT-MEDIA-P3-POSTP3-P4-VISUAL-UX-01`
- Approved option:
  `MEDIA-P3-POSTP3-P4-VUX-O2-NATIVE-CARD-GALLERY`
- Approved scope: correct the completed Package 3/post-P3/Package 4 visual UX
  with a native Media Resource card gallery, wider non-autofocusing owner
  workspace, compact contain-fit selected image, narrow-screen source-first
  picker order, server-backed 1500-millisecond Storage search, bounded
  recursive Laravel-public and `public/images` acquisition discovery, and
  measured query/filesystem-probe budgets.
- The operator amended only the topbar part: production remains sticky. Visual
  capture tooling temporarily applies a capture-only static-topbar class/style
  and removes it after capture, so no Card Template Editor runtime change is
  included.
- Package 4's original implementation and 43-action Resource-table rider
  remain authoritative except where the correction changes picker topology.
  The inline-picker correction research, plan and handoff are
  `docs/research/media-program/packages/04-owner-image-inline-picker-correction-research.md`,
  `docs/research/media-program/packages/04-owner-image-inline-picker-correction-plan.md`
  and
  `docs/phase-02/media-program-p4-inline-picker-correction-handoff.md`.
- The active visual correction research, plan and handoff are
  `docs/research/media-program/packages/04-postp3-visual-ux-research.md`,
  `docs/research/media-program/packages/04-postp3-visual-ux-plan.md` and
  `docs/phase-02/media-program-p3-postp3-p4-visual-ux-handoff.md`.
- Packages 1-4, the post-Package-3 correction and the Package 4 picker
  correction are complete locally. This visual correction is also complete
  locally. It is implementation evidence for the forward UX3 route above, not
  an approval for any remaining UX3 Mini-task. Package 5 remains separately
  gated.
- Starting baseline: clean `main` at
  `5b737e1e0f3d355a8dbb51a6887c2e0e785ed463`, 27 commits ahead of
  `origin/main`.
- The documentation-only reconciliation named above is accepted. Mini-task 3A
  has since completed locally under its separately approved research, design,
  audit and implementation cycle. It does not authorize Mini-task 3B or any
  later outcome.

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
The reused picker's explicit Upload command is an immediate library write.
Package 3 deliberately extends that permanence to URL and Storage: a successful
acquisition remains in the library if the picker or outer owner form is
cancelled. Gallery selection remains pending and mutation-free until owner save.
The corrected owner action contains one schema-owned picker directly in its
first/default tab; it opens no second picker modal. One uploaded image becomes
the pending owner choice. A multi-file upload permanently admits every
successful image but chooses no owner image automatically; the operator must
choose exactly one from the gallery.

## Boundary-specific validation

- Unique reference keys are required only for portable/key operations.
- Missing keys are generated during conversion or explicit repair.
- MIME, extension, upload limits and filename cleanup govern new input.
- Containment governs delivery and physical mutation.
- File existence governs preview/delivery/fallback, not row visibility.
- Audience visibility governs delivery, never admin inventory visibility.
- Unsanitized SVG cannot render inline.
- URL/Spotify acquisition retains SSRF, redirect and DNS controls.
- New acquisition creates its Curator row, MediaAsset and provider binding in
  one transaction after source bytes are ready.
- Raster admission preserves bytes; sanitized SVG is the only allowed source
  transformation.
- Mutation journals govern actual move, rename, replace, trash, restore and
  purge only, not new acquisition.
- Actively referenced media cannot be deleted.

## Package sequence

1. Minimal MediaAsset kernel and database-only Curator conversion — complete.
2. Inventory-first gallery, Needs Repair diagnostics, simplified resolution,
   attachment authority, settings/public fallback, picker All Media and
   same-page Add/Replace Image UX — complete locally.
3. Gallery/Upload/URL/Storage acquisition; Spotify feeds URL; validation applies
   to new inputs and SVG inline rendering — complete locally.
Post-Package-3 correction: immediate acquisition correctness and
source-workspace UX — complete locally.

4. Podcast/episode image hover, detail, download, copy, change and association
   repair/default UX — complete locally.
Package 4 picker correction: inline Replace Image first, Details and Effective
Image second, acquisition-only owner batch upload and explicit standalone Media
batch upload — complete locally.
Post-P3/Package 4 visual correction: native Media card gallery, wider
non-autofocusing owner workspace, compact contain-fit selection, source-first
mobile picker order, bounded recursive server-backed Storage search and
measured query/probe budgets — complete locally.
5. Files Discovery and physical lifecycle; journals apply only to real file
   mutations.

## Baselines

- Visual correction starting baseline: clean `main` at
  `5b737e1e0f3d355a8dbb51a6887c2e0e785ed463`, 27 commits ahead of
  `origin/main`.
- At Stage 1: 79 modified tracked files and 54 default-status untracked entries
  formed the unfinished overbuilt Package 1 draft. Exact cleanup enumeration
  resolved 55 files because `tests/Support/` had been collapsed to one entry.
- Local data: under separate exact backup-first approval, the committed
  Package 3 settings migration and two Media Asset relational migrations were
  applied to local MySQL database `podtext`. The report/apply/report conversion
  settled at 15 bound Media rows, zero unbound rows and zero diagnostics.
  Production was not touched.
- Production: the latest dated snapshot remains pre-G1 with 403 Curator rows,
  108 matched covers, three SVGs, five rowless managed files, one oversized
  raster and two duplicate-byte pairs.
- Visual correction tests use isolated databases and fake storage only.
  The separate local database activation performed database-only migration and
  conversion work; it did not mutate media files or authorize production.

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

- No dependency, manifest, lockfile, npm/toolchain or Boost-discovery change.
- No dedicated security audit or new security architecture.
- No further local database/storage/cache action. The separately approved
  local database activation is complete; production remains untouched.
- No production action without a separate exact approval.
- No raster normalization, checksum proof, file relocation, quarantine,
  conversion manifest/digest/schema hash or new conversion journal.
- Selecting existing media performs attachment-only database changes; it never
  copies, moves, renames or normalizes the selected row or file.
- No Package 5 Files Discovery/lifecycle work or unrelated media expansion.
- No runtime topbar/Card Template Editor change; the static topbar rule exists
  only inside the visual-capture session.
- No branch/worktree change, push, broad reset, stash, checkout or deletion.
