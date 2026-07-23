# Inventory-First Media Program Master Plan

## Controlling route

Package 1 closed under audit `LS-20260723-MEDIA-INVENTORY-FIRST-RESET-01`.
Package 2 is implemented locally under audit
`LS-20260723-PODTEXT-MEDIA-P2-INVENTORY-PICKER-REPLACE-01`, approved option
`MEDIA-P2-O1-REUSE-PICKER-SAME-PAGE-REPLACE`.

Only Package 2 is authorized in the current run. Packages 3-5 remain route
forecasts and require fresh Simplifier approval.

## Package 1 — minimal kernel and database conversion

Outcome: every Curator row has one portable MediaAsset and Curator binding;
numeric owner relationships remain authoritative and no file is mutated.

Tasks:

1. Rebase canonical documents and record the unfinished handoff invalid.
2. Remove only the attributable overbuilt draft, preserving rebased docs.
3. Test-first minimal schema/models, attachment bridge and one idempotent
   conversion command.
4. Close with production-shaped fixtures, ordered gates, local implementation
   commit and immediate docs hash stamp.

Schema:

- `media_assets`: ID, immutable unique ULID reference key, timestamps.
- `media_provider_bindings`: asset FK, provider, provider record key,
  timestamps, unique provider-record and asset-provider pairs.
- nullable `media_attachments.media_asset_id`, retaining `media_id`.

The converter preserves IDs, paths and metadata; reuses or generates keys;
creates assets/bindings; bridges authoritative attachments; resolves unique
legacy paths/settings; reports missing files, duplicate paths and unresolved
owners; and commits database changes transactionally. It has no byte,
normalization, hashing, relocation, quarantine, manifest or journal behavior.

## Package 2 — inventory, repair diagnostics and selection

Outcome: every Media row is visible in All Media; Needs Repair is a filter;
attachment authority and D01 are correct; picker All Media clears its initial
logical filter.

This package removes strict existing-row eligibility from Resource/picker and
public resolution while retaining delivery/access/SVG-inline boundaries.
It also reuses the Gallery/Upload picker for always-visible, same-page
podcast/episode Add/Replace Image actions. Package 2 is complete locally;
closeout hash pending.

## Package 3 — four-source acquisition

Outcome: Gallery, Upload, URL and Storage share one new-input admission path;
Spotify supplies URLs. MIME/extension/limits apply to new input, SVG sanitation
is reusable, and raster normalization/checksum is optional rather than
mandatory.

## Package 4 — owner image UX

Outcome: podcast/episode list and detail surfaces provide bounded preview,
metadata, safe download, copy filename, change image and broken-association
repair/default actions.

## Package 5 — files and physical lifecycle

Outcome: managed rowless files appear in Files Discovery; explicit import,
move, rename, replace, trash, restore and purge use containment, reference
protection and durable mutation journals.

## Verification and commits

Every package is serial and test-first. Final order is requirements/drift
sweep, Pint, FilaCheck, Vite build and full test suite last. Any subsequent
change restarts at Pint. Package closeout uses an implementation commit followed
immediately by a docs-only handoff/ledger hash stamp. No push occurs.

## Program exclusions

No dependency changes, one-shot five-package implementation, dedicated
security-audit phase, local/production live action, compatibility-field removal
or parallel repository writers.
