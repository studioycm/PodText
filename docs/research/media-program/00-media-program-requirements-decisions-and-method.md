# Media Program Requirements, Decisions, and Method

## Authority

- Audit: `LS-20260724-PODTEXT-MEDIA-P4-POSTP3-OWNER-UX-01`
- Approved option:
  `MEDIA-P4-POSTP3-O1-INTEGRATED-IMAGE-WORKSPACE`
- Approval date: 2026-07-24
- Scope: Package 4 owner-image UX and its separately estimated Resource-table
  record-action rider only.

This registry supersedes the 2026-07-22 trusted-canonical/hybrid-root program.
Historical committed handoffs remain evidence of what existed; they do not
authorize the rejected behavior below.

## Working method

For every checkpoint: read the context helper, this registry, the current
audit/approval, active package plan and Git diff. Separate committed behavior,
unfinished draft behavior, dated environment evidence and current test proof.
Use test-first implementation in isolated test databases and fake storage.
Keep writers serial and do not execute real migrations, conversion or file
mutation.

## Controlling requirements

| ID | Requirement | Package/proof |
|---|---|---|
| `MI-R001` | Every Curator Media row appears in All Media. | Package 2 query/UI tests. |
| `MI-R002` | Every managed file appears in Media or Files Discovery. | Package 5 discovery tests. |
| `MI-R003` | Needs Repair is a visible filter/view, not an exclusion universe. | Package 2 Resource tests. |
| `MI-R004` | `media_attachments.media_id` is the local owner authority. | Package 1 model/conversion tests. |
| `MI-R005` | `MediaAsset.reference_key` is portable identity only. It is not trust or display authorization. | Package 1 model and compatibility tests. |
| `MI-R006` | Curator numeric ID is preserved as provider-local identity in one binding. | Package 1 conversion tests. |
| `MI-R007` | `curator.path` is the current file location. Owner/settings paths and Curator key remain compatibility mirrors. | Package 1 preservation tests. |
| `MI-R008` | One MediaAsset and one Curator binding are created per Curator row; duplicate bytes remain separate. | 15/403 fixtures. |
| `MI-R009` | A valid unique Curator key is reused; a missing, malformed or duplicate key receives a new ULID. | Conversion key tests. |
| `MI-R010` | MediaAsset keys are immutable after creation. | Model guard test. |
| `MI-R011` | Package 1 schema contains only assets, bindings and the attachment bridge. | Schema contract test. |
| `MI-R012` | `media_attachments.media_asset_id` is a portable bridge and never replaces `media_id` as local authority. | Relationship tests. |
| `MI-R013` | Existing authoritative attachments win over stale owner paths; stale mirrors are repaired/reported, not vetoed. | Conversion fixtures. |
| `MI-R014` | Unattached legacy paths attach only on one unique Curator path match. Duplicate matches remain unresolved and reported. | Conversion fixtures. |
| `MI-R015` | Settings reference keys are filled only when their compatibility path resolves uniquely. | Settings conversion tests. |
| `MI-R016` | Conversion preserves Curator ID, path and metadata and preserves compatibility paths. | Exact-row assertions. |
| `MI-R017` | Conversion performs database mutations in one transaction and is idempotent. | rollback/rerun tests. |
| `MI-R018` | Package 1 conversion performs no filesystem writes, imports, moves, copies, deletes, sanitation, byte decoding, normalization or hashing. | filesystem-spy and source sweep. |
| `MI-R019` | Existence-only storage checks may report missing files; they never gate conversion or row visibility and never read bytes. | reporting tests with fake storage. |
| `MI-R020` | Missing files, duplicate paths and unresolved owners remain visible and are reported. | command summary and fixture tests. |
| `MI-R021` | Public fallback is limited to missing canonical row, missing physical file, audience denial or unsanitized inline SVG. | Package 2 public resolution tests. |
| `MI-R022` | Key shape, root, folder, filename, metadata, size, dimensions, provenance, checksum and legacy-path mismatch are not display fallback reasons. | Package 2 regression tests. |
| `MI-R023` | Admin inventory includes rows on both configured public and private disks. | Package 2 query tests. |
| `MI-R024` | Private/public placement affects delivery and repair diagnostics, not inventory membership. | Package 2 delivery tests. |
| `MI-R025` | Picker All Media clears the initial logical folder/slot filter. | Package 2 Livewire tests. |
| `MI-R026` | Selecting existing media never clones, copies, moves or normalizes it. | Package 2 attachment tests. |
| `MI-R027` | Nonselectable repair rows may remain visible with an exact reason/action. | Package 2 picker tests. |
| `MI-R028` | Upload limits, MIME/extension checks and filename cleanup apply to new Upload/URL/Storage admission only. | Package 3 validation tests. |
| `MI-R029` | Unsanitized SVG remains visible in inventory but cannot render inline until sanitized. | Packages 2-3 tests. |
| `MI-R030` | URL/Spotify acquisition retains SSRF, redirect, DNS and response limits. | Package 3 HTTP fixture tests. |
| `MI-R031` | Mutation journals are required only for real move, rename, replace, trash, restore and purge. | Package 5 lifecycle tests. |
| `MI-R032` | Actively referenced media cannot be deleted. | Package 5 reference tests. |
| `MI-R033` | Logical folders are added only with Package 2 UI; lifecycle/trash fields only with Package 5. | Schema diff guards. |
| `MI-R034` | Package 1 uses no validation/trust/lifecycle statuses, canonical storage duplication, checksum, normalization, provenance, quarantine or schema-capability state machine. | Architecture/source sweep. |
| `MI-R035` | Conversion has no manifest, digest, migration-hash allowlist or per-row mutation journal. | Command/API/source sweep. |
| `MI-R036` | Original null-key 15-row, current valid-key 15-row and production-shaped 403-row fixtures are required. | Package 1 tests. |
| `MI-R037` | Existing authorization, delivery containment, SVG inline protection and URL acquisition controls remain correct in their actual boundaries. | Focused regression tests; no separate audit. |
| `MI-R038` | No dedicated security-audit/review phase is part of this program. | Plans and handoffs. |
| `MI-R039` | No local-development or production action is implied by implementation. | Command log and handoff. |
| `MI-R040` | No dependency changes, worktree/branch changes or push occur. | lockfile/status checks. |
| `MI-R041` | Gallery is mutation-free selection; successful Upload, URL and Storage acquisitions become permanent library items immediately. Cancelling the picker/outer owner action cancels only the pending attachment. | Package 3 picker/cancel tests. |
| `MI-R042` | Every new admission creates the Curator row, immutable MediaAsset and Curator provider binding together in one database transaction without rerunning legacy conversion. | Package 3 admission rollback/model tests. |
| `MI-R043` | Upload, URL and Storage converge on one app-owned admission boundary after source-specific byte acquisition; owner attachment remains in `MediaAttachmentManager`. | Package 3 architecture/source sweep and integration tests. |
| `MI-R044` | Raster admission preserves source bytes. Positive MIME/extension, structural, size and dimension rejection remains; successful SVG sanitation is allowed for every image purpose. | Package 3 validator fixtures. |
| `MI-R045` | Original filename metadata is preserved and a bounded Admin UX setting chooses app-generated or cleaned-original collision-safe destination naming. | Package 3 settings/naming tests. |
| `MI-R046` | Admin UX owns bounded new-input size/dimension, upload batch, browse limit, search limit and acquisition filename strategy settings. Allowlists, URL protections, parallel upload and selection safety caps remain code/config boundaries. | Package 3 settings/page tests. |
| `MI-R047` | Picker URL acquisition is synchronous; existing external record/import acquisition remains queued after commit and returns through persisted Media/attachment state. All paths reuse the same admission boundary. | Package 3 HTTP/queue/Livewire tests. |
| `MI-R048` | Storage accepts only bounded candidates from configured disks/roots through opaque server-side identity. It reuses an existing row, registers a safe public raster in place, or copies when required without mutating the source. | Package 3 Storage tests. |
| `MI-R049` | Spotify is a URL producer for podcast create/edit, episode create/edit/workspace and direct-import paths. Network acquisition remains outside importer database transactions. | Package 3 form/import/queue tests. |
| `MI-R050` | Package 3 adds no relational schema or dependency and does not implement Package 4 owner-tool expansion or Package 5 discovery/lifecycle behavior. | migration/lockfile/source/diff sweep. |
| `MI-R051` | One reusable integrated owner-image workspace serves podcast list/edit, episode list/classic edit/workspace and the podcast ContentItems relation manager. | Package 4 action/surface tests. |
| `MI-R052` | Owner image detail identifies the real effective source: direct Media, compatibility Media, external URL, inherited podcast cover, configured family/global fallback, empty fallback, or fallback displayed because a direct association is broken. | Package 4 presenter source matrix. |
| `MI-R053` | Canonical Media detail distinguishes original filename from stored basename and exposes useful existing metadata, safe preview/download and Media review without exposing a raw storage path. | Package 4 presenter/controller tests. |
| `MI-R054` | List thumbnails stay compact; supplemental previews are lazy and bounded to 300 by 300 pixels. Thumbnail click/keyboard/touch opens detail without record navigation or per-row detail/storage queries. | Package 4 table/query/browser tests. |
| `MI-R055` | Change Image reuses the completed four-source picker and its single outer-owner/schema-child modal topology. Gallery remains mutation-free, completed acquisition remains permanent, and cancel remains an attachment no-op. | Package 4 action and browser regressions. |
| `MI-R056` | Normal replace and Remove Direct Image / Use Automatic Image recheck the mount-time owner identity under row locks. Stale actions fail closed; unrelated saves never silently clear broken evidence. | Package 4 stale-write and owner-save tests. |
| `MI-R057` | A broken direct association shows the effective fallback separately from a plain warning and offers bounded replace, detach and Media review choices while preserving D01 and diagnostic visibility. | Package 4 broken-state matrix. |
| `MI-R058` | Package 4 labels, metadata, warnings, actions and copy feedback are translated in Hebrew and English; Hebrew-first RTL, narrow screens and day-first Jerusalem timestamps are preserved. | translation and browser tests. |
| `MI-R059` | Package 4 adds no relational/settings migration, dependency, provider, storage scan, file mutation, byte fingerprint, filesystem journal or Package 5 lifecycle behavior. | source/diff/lockfile sweep. |
| `MI-R060` | Exactly 43 ungrouped record actions across 12 Filament Resource/RelationManager tables become icon-only through a bounded local configurator while retaining the translated accessible label as tooltip and accessible name. Every action has a semantic Heroicon. | Package 4 rider inventory tests. |
| `MI-R061` | The Settings Backups ActionGroup stays grouped with visible labels, and header/toolbar/bulk/empty-state/page-header/form/modal-footer actions plus custom Page tables remain outside the rider. | Package 4 rider exclusion tests. |

## Rejected requirements

The following are not active requirements for existing-media visibility,
selection or Package 1 conversion: trusted status, key-as-trust, fixed/hybrid
root relocation, direct-child rules, ASCII paths, MIME/extension metadata
agreement, size/dimension limits, canonical name/path reconstruction, raster
re-encoding, normalized-byte equality, SHA-256 proof, mutation-journal proof,
app-created provenance, required asset binding before inventory visibility,
key/path disagreement veto, conversion quarantine/copy/delete and
manifest/digest/schema-hash authorization.

## Package boundary

Packages 1-4 and the post-Package-3 picker correction are complete locally;
Packages 1-3 and the correction are hash-stamped, while Package 4 awaits its
canonical implementation hash stamp. This approval ended after Package 4 and
its bounded Resource-table record-action rider. Package 5,
dependency/toolchain changes, live data actions, production actions and push
remain separately gated.
