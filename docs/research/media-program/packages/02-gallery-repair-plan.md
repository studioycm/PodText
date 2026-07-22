# Package 2 Forecast Plan — Inventory and Repair

Status: not approved.

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

No normalization/checksum/trusted-status requirement may return.
