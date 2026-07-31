# Media Operations UX3 Mini-task 3C Safe Existing-File Operations and Outcomes Handoff

## Status of this document

Written on 2026-07-31 by RECON2 R4, after the fact. Mini-task 3C shipped and
was operator-closed on 2026-07-28 («3c is good») but never produced the
handoff the cycle requires; without a handoff, the findings matrix was never
amended, and its seven 3C rows stayed `pending` for shipped work. This
document is a **reconstruction from the repository** — commits, code, tests
and the rolling prose in `docs/phase-02/current-project-state.md` — not a
contemporaneous report. It follows the precedent of the Mini-task 4
reconstruction handoff.

The external dossier `LS-20260728-PODTEXT-MEDIA-OPS-UX3-3C-01` (published
artifact) is referenced by the state doc but is not present in this
repository.

## Scope and baseline

- Approved slice: Safe Existing-File Operations and Outcomes, approved
  «3C all» with decisions D1–D8 (D4 flipped to role priority
  podcast → episode → settings; the rest as recommended)
- Mid-research operator seeds absorbed as P5–P8 before approval: bulk
  name-by-owner, title-as-export-filename with preselected default, search
  scopes, selection-time retitle checkbox, zero-cost public alt chain
- The operator's «rename is for a title» ruling is the model: filenames stay
  anonymous engine mints; «יצירת שם קובץ חדש» is maintenance-only
- Research gate opened by `7bafd8d`; owned findings `MUX3-F024`–`F030`
  (source `S4`) plus, later, the `S5` evidence gap `MUX3-F045`
- Repository: `/Users/studioycm/Herd/PodText`

## Delivered outcome

Implementation `bc0ce8f` (P1–P8), quoted from its commit record and verified
against code:

- **P1**: policy `Response` reasons rendered as disabled-with-tooltip on both
  surfaces; managed-scope truth ends the confirm-then-404 legacy-row crash;
  missing-file View/Download disable; delete label unified.
- **P2**: consequence dialogs with identity, zero-usages truth, and per-op
  impact sentences (delete states the journal safety copy per D1).
- **P3**: before→after success receipts to toast and bell; engine-reason
  failures surface as named failures (`MediaOperationReceipts`).
- **P4**: bulk delete census — blocked rows skipped per D2 with a three-count
  receipt (`MediaBulkDeleteCensus`).
- **P5**: export dialogs state population/count/destination; Title naming
  strategy with settings default, slug-key fallback and `-2` suffixes per D6.
- **P6**: title-by-owner (bulk census + affixes, single-card preview, D8
  smart-default retitle checkbox at owner selection —
  `MediaOwnerTitleApplier`).
- **P7**: search scopes הכל/כותרת/בעלים/שם קובץ including owner-title search
  (`MediaLibraryTaskQuery`).
- **P8**: public alt chain `media.title ?: owner` on already-loaded rows only.
- Also fixed latent single-alias morph comparisons (`content_group` vs FQCN).

Outcome-review corrections: **OR1** `e529f48` (gallery card references
stacked — label above humanized names or the no-usages text; readiness badge
on its own row) and **OR2** `88c6880` (primed reference cache and linked
references build one canonical owner-title label and dedupe an attachment
against its converter-bridged legacy path column; repair-facing legacy list
untouched).

## Finding coverage

| Finding | Outcome |
|---|---|
| `MUX3-F024` — Rename identity/blast radius | Implemented (P1/P2 identity + impact dialogs; «rename is for a title» model) |
| `MUX3-F025` — Replace vs. owner-image distinction | Implemented (P2 consequence dialogs) |
| `MUX3-F026` — delete identity/references/irreversibility | Implemented (P2 delete dialog with journal safety copy per D1) |
| `MUX3-F027` — bulk scope/eligibility truth | Implemented (P4 census, skip-blocked, three-count receipt) |
| `MUX3-F028` — operation result/recovery truth | Implemented (P3 receipts + engine-reason failures) |
| `MUX3-F029` — export dialogs scoped and truthful | Implemented (P5) |
| `MUX3-F030` — Preview/View/Download contract | Implemented (P1 missing-file disable + existing safe routes) |
| `MUX3-F045` — action discoverability evidence gap | Closed later by `def171b` (2026-07-30), which proved per state, permission, locale and viewport |

## Files changed (principal)

`app/Filament/Resources/Media/Tables/MediaTable.php`,
`app/Livewire/Admin/MediaPickerPanel.php`, `app/Policies/CuratorMediaPolicy.php`,
`app/Support/Media/MediaBulkDeleteCensus.php`,
`app/Support/Media/MediaOperationReceipts.php`,
`app/Support/Media/MediaOwnerTitleApplier.php`,
`app/Support/Media/MediaLibraryTaskQuery.php`,
`app/Support/Media/MediaReferenceFinder.php`,
`app/Support/Media/ContentImagesExportManager.php`,
`app/Support/Media/ImageFileNamer.php`, `app/Enums/MediaNamingStrategy.php`,
`app/Filament/Actions/ContentImageActions.php`,
`app/Support/PublicFront/PublicDefaultImageResolver.php`, both locale files,
census Blade view.

## Tests

The 3C behaviors are covered across `tests/Feature/AppOwnedMediaResourceTest.php`,
`tests/Feature/AppOwnedMediaPickerTest.php`,
`tests/Feature/MediaRecordScopeAndAuthorizationTest.php` and the browser
suites (`tests/Browser/MediaResourceGalleryBrowserTest.php`,
`tests/Browser/GalleryKeyboardNavBrowserTest.php`).

## Commits

| Commit | Role |
|---|---|
| `7bafd8d` | 3C research gate opened |
| `bc0ce8f` | Implementation P1–P8 |
| `31be67f` | Implementation hash backfill |
| `e529f48` | OR1 |
| `88c6880` | OR2 |
| `9393fef` | Closure — 3C closed by the operator on 2026-07-28; M4 and the next research gate recorded |

## Deferred and excluded

- No new batch authority: bulk work reuses already-authorized operations only.
- No Package 5 Trash/lifecycle behavior; delete remains permanent with the
  journal quarantine copy as its safety.
- Recheck/Retry (`MUX3-F038`) remained omitted; its proof gate was untouched
  by 3C.

## Documentation debt closed by this handoff

The seven `pending — Mini-task 3C` matrix rows (`MUX3-F024`–`F030`) and the
`F045` evidence-gap row are corrected by the dated 2026-07-31 amendment
section in
`docs/research/media-operations-ux3/07-program-reconciliation-and-finding-coverage.md`,
which links here.
