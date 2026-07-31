# Media Operations UX3 Mini-task 3B Media Intake and Acquisition Results Handoff

## Status of this document

Written on 2026-07-31 by RECON2 R4, after the fact. Mini-task 3B shipped and
was operator-closed on 2026-07-28 but never produced the handoff the cycle
requires; without a handoff, the findings matrix was never amended, and its
five 3B rows stayed `pending` for shipped work. This document is a
**reconstruction from the repository** — commits, code, tests and the rolling
prose in `docs/phase-02/current-project-state.md` — not a contemporaneous
report. It follows the precedent of the Mini-task 4 reconstruction handoff.

## Scope and baseline

- Approved slice: Media Intake and Acquisition Results — the second of the
  three reconciled Mini-tasks (3A choice/commit → **3B intake/results** → 3C
  existing-file operations)
- Research gate opened by `9a68ee5` (3A outcome review closed, 3B gate open)
- Owned findings: `MUX3-F017`–`MUX3-F022` (source `S3`, the operator design
  PDF, reconciled by the accepted matrix)
- Repository: `/Users/studioycm/Herd/PodText`

## Delivered outcome

Acquisition through the picker now tells the truth about permanence and
per-item results across all four sources:

- **P1–P4** (`bc95e21`): named per-upload fates in the batch result (which
  files were admitted, failed validation, failed admission, or were never
  attempted), URL acquisition preview, an explicit import verb, and Storage
  candidate row truth. Batch admission returns a structured
  `MediaUploadBatchResult` with admitted indexes and not-attempted indexes so
  the UI can render per-file outcomes instead of one shared toast.
- **P5** (`7e4eaf2`): the upload queue accepts more files than one admission
  batch by accumulating chunks, with a bounded queue-limit Admin UX setting
  (`AdminUxSettings`, migration-backed default) instead of silently dropping
  overflow.
- **OR1** (`4457bf0`): the batch describe section became honest — per-file
  prefixes instead of one anonymous shared metadata block (`MUX3-F019`).
- **OR2** (`45078bc`, `8452108`, `8908b4a`, `a638fd4`): gallery card RTL truth
  with inline title rename; return-to-gallery after batch creation; revised
  library cards with a linked-references slide-over; a session-persisted
  cards-per-row selector (three to five per desktop row); and direct Issue
  Review links from the primary-issue row and the details slide-over.

## Finding coverage

| Finding | Outcome |
|---|---|
| `MUX3-F017` — Gallery non-mutating vs. admission permanent | Implemented: per-source before/after truth and receipts; cancel semantics unchanged (cancel affects only the pending attachment) |
| `MUX3-F018` — source-specific intake | Implemented: Upload/URL/Storage speak their own goals, inputs and exits in the picker |
| `MUX3-F019` — per-file identity in batch intake | Implemented: per-file prefixes and per-file fates |
| `MUX3-F020` — partial/failed/interrupted truth | Implemented: named fates with reconciling totals and a safe not-attempted set |
| `MUX3-F021` — Storage candidate identity/operability | Implemented: candidate row truth from the bounded browser |
| `MUX3-F022` — Spotify artwork as acquisition + owner choice | **Partial**: provider wording/continuity shipped, but show artwork still auto-admits (`app/Filament/Forms/SpotifyShowInput.php:84-91`) with no admission choice and no per-item permanent receipt |

## Files changed (principal)

`app/Livewire/Admin/MediaPickerPanel.php`,
`app/Support/Media/MediaAcquisitionManager.php`,
`app/Support/Media/MediaUploadBatchResult.php`,
`app/Support/Media/StorageImageCandidateBrowser.php`,
`app/Filament/Resources/Media/Pages/CreateMedia.php`,
`app/Filament/Resources/Media/Pages/ListMedia.php`,
`app/Filament/Resources/Media/Tables/MediaTable.php`,
`app/Support/Media/MediaDetailsViewModel.php`, `AdminUxSettings`
(page/settings/policy/migration), both locale files, the picker and
slide-over Blade views.

## Tests

`tests/Feature/AppOwnedMediaPickerTest.php` (fates, queue chunking, limits),
`tests/Feature/MediaAcquisitionTest.php` (batch result truth),
`tests/Feature/AppOwnedMediaResourceTest.php` and
`tests/Feature/ResourceTableIconActionsTest.php` (cards-per-row, Issue Review
reachability).

## Commits

| Commit | Role |
|---|---|
| `9a68ee5` | 3B research gate opened |
| `bc95e21` | Implementation P1–P4 |
| `f961a80` | Implementation hash backfill |
| `7e4eaf2` | Implementation P5 |
| `e7bff26` | P5 hash backfill |
| `4457bf0` | OR1 |
| `45078bc`, `8452108`, `8908b4a`, `a638fd4` | OR2 series |
| `7bafd8d` | Closure — 3B closed by the operator on 2026-07-28, 3C gate opened |

## Deferred and excluded

- `MUX3-F022` remains the genuine partial (Spotify artwork admission choice
  and per-item receipt).
- No Package 5 discovery/lifecycle behavior was added.

## Documentation debt closed by this handoff

The five `pending — Mini-task 3B` matrix rows (`MUX3-F017`–`F021`) and the
`F022` partial are corrected by the dated 2026-07-31 amendment section in
`docs/research/media-operations-ux3/07-program-reconciliation-and-finding-coverage.md`,
which links here.
