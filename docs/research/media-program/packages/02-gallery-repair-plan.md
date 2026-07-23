# Package 2 Forecast Plan — Inventory and Repair

Status: implemented locally under audit
`LS-20260723-PODTEXT-MEDIA-P2-INVENTORY-PICKER-REPLACE-01`, option
`MEDIA-P2-O1-REUSE-PICKER-SAME-PAGE-REPLACE`.

After a fresh audit:

1. Make All Media query every Curator row across configured disks.
2. Add computed/batched Needs Repair diagnostics without per-row filesystem
   byte work or N+1 settings queries.
3. Add logical folders only with their actual UI and keep them non-authoritative.
4. Rewrite attachment resolution so `media_id` wins and legacy paths are
   mirrors.
5. Implement D01's four fallback reasons exactly.
6. Make picker All Media clear the initial logical filter and keep disabled
   repair rows visible with an exact reason/action.
7. Rewrite tests that encode superseded strict visibility rules.
8. Reuse the Gallery/Upload picker for always-visible same-page Add/Replace
   Image actions on podcast and episode list/edit surfaces, showing the current
   image and preserving cancellation of staged Gallery selection as a no-op.
   The reused picker's explicit Upload command remains an immediate library
   write; staging that command until owner save belongs to Package 3.

No normalization/checksum/trusted-status requirement may return.

Result: all eight steps are implemented. Existing-media selection changes only
the attachment and compatibility path; it does not copy, move, rename,
normalize or journal the selected media. Packages 3-5 remain gated.
