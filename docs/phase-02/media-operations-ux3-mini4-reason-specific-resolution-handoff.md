# Media Operations UX3 Replanned Mini-task 4 Reason-Specific Media Issue Resolution Handoff

## Status of this document

Written on 2026-07-30, after the fact. Mini-task 4 shipped on 2026-07-29 but
never produced the handoff the cycle requires, so its only durable in-repo
record was prose in `docs/phase-02/current-project-state.md` plus commit
messages. This document is a **reconstruction from the repository** — commits,
code and tests — not a contemporaneous report. Its scope and decision records
are quoted from the state doc; anything not verifiable in the repository is
marked as such.

The external dossier `LS-20260728-PODTEXT-MEDIA-OPS-UX3-M4-01` is not present
in this repository and was not consulted.

## Scope and baseline

- Approved slice: the first reason-specific Media issue resolution, selected
  from the six diagnostic reasons
- Selected reason: `unsanitized_svg`, the operator's stated preference
- Predecessor gate: Mini-task 3C reviewed and closed (`bc0ce8f`, closure
  `9393fef`), which is what released Mini-task 4 to restart at UX research
- Repository: `/Users/studioycm/Herd/PodText`
- Operator decisions recorded in the state doc: D2 flipped to A (managed rows
  only), D6=a (fate chips, no Recheck control), P4 dropped

## Delivered outcome

On `/admin/media/{record}/review-issues`, each current diagnostic reason renders
a card with cause, consequence, evidence limit and technical facts. For
`unsanitized_svg` only, that card gains an action zone with a «ניקוי בטיחותי» /
"Safety cleanup" button, gated by `Gate::inspect('repair', $media)`; a denial
renders as a blocked block carrying the policy's own message.

Running the action opens a consequence dialog that discloses the address change
and states plainly that this is not a replace screen, then executes
`MediaFilesystemMutationCoordinator::sanitize()` — a full journal, fence,
quarantine and lease operation whose destination bytes are the
`SvgUploadSanitizer` output. On success the page re-runs diagnostics in the same
request and shows fate chips ("Closed" / "Remaining") plus a durable receipt.

When a file cannot be sanitized, nothing is journaled and a named refusal region
appears with three prefilled continuation routes: replace the file, delete it
permanently, or mark it as trusted. The trust mark writes `trusted_at` and
`trusted_by_user_id` and short-circuits the single choke point
`PublicMediaDelivery::canRenderInline()`, so the diagnostic reason, selection
blocking, previews and public delivery all settle from one flag. A trusted strip
showing who and when, with a revoke control, renders above the issue list.

## Recheck decision

Recheck and Retry were **declined again**, by decision D6=a. `MUX3-F038` demands
proof of an explicit read set, bounds, authorization, stale semantics, result
lifetime and a no-Recheck fallback; that gate was not met. Mini-task 4 sidestepped
it rather than meeting it: the fate chips are a same-request re-evaluation after a
known mutation, which is why they are truthful without freshness proof. There is
no operator-triggered re-read anywhere on the page.

This is the second decline. `MUX3-F038` has never been retired, so it remains
open and will be re-litigated until the operator either meets the proof gate or
closes the finding.

## Requirement classification

| Requirement | Status |
|---|---|
| Select one diagnostic reason and deliver its resolution | Implemented (`unsanitized_svg`) |
| Truthful before/after cause and structured outcome | Implemented for the selected reason |
| Failed / no-op state | Implemented as the refusal region with three routes |
| Verified result and continuity | Implemented as fate chips plus a durable receipt |
| Recheck / Retry controls | Declined; proof gate not met (D6=a) |
| The other five diagnostic reasons | Out of scope; remain review-only |

## Files changed

Reconstructed from the commit range:

- `app/Support/Media/MediaIssueReviewPresenter.php` — per-reason resolution states
- `app/Filament/Resources/Media/Pages/ReviewMediaIssues.php` — action, dialog,
  refusal routes, same-request fate chips
- `resources/views/filament/resources/media/pages/review-media-issues.blade.php`
- `app/Policies/CuratorMediaPolicy.php` — the `repair` ability
- `app/Support/Media/MediaFilesystemMutationCoordinator.php` — `sanitize()`
- `app/Support/Media/PublicMediaDelivery.php` — trust-mark short circuit
- `database/migrations/2026_07_28_120000_add_trusted_columns_to_curator_table.php`
- `lang/en/admin.php`, `lang/he/admin.php`

## Tests

`tests/Feature/MediaSanitizeRepairTest.php` — 7 tests covering the action, the
policy gate, the refusal routes, the trust mark and the resolution kinds.

## Commits

| Commit | Subject |
|---|---|
| `24b13fb` | P1+P2+P3 — per-reason resolution states, journaled sanitize, refusal routes |
| `33a659c` | OR1 — accountable admin trust mark |
| `8bbd5e9` | OR2 — fate chips lead with the issue badge |
| `1713cbb` | OR3 — picker modal scroll fix, reclassified by the operator as outside M4 |
| `3694919` | Closure |
| `72b96a9` | Implementation hash backfill |
| `3900645` | Post-closure operator steer — sanitize any gallery row, relocating root sources in the same operation |

## Deferred and excluded

Five diagnostic reasons remain review-only with no resolution action: missing
file, technical metadata, portable identity, storage disk and audience denied
(`MUX3-F032`, `F034`–`F037`). `MUX3-F031` and `F040` are therefore satisfied for
one reason of six.

`MUX3-F039` — which reason should come next — was answered once and reopens for
the second cycle. Decision D8 seeded "intrinsic metadata rows" as the natural
next candidate, but that predates Storage Truth; cohort sizes have changed and
the selection should be re-made against current data.

Two M4-era deferrals were closed afterwards by other work: root-level reach was
resolved by the Storage Truth relocation, and the zero-reference sanitize
restriction was lifted by the Storage Truth D7=a sanitize lift, so
attachment-referenced rows are now repairable and only path-based settings
references block.

## Known documentation debt closed by this handoff

The findings matrix
`docs/research/media-operations-ux3/07-program-reconciliation-and-finding-coverage.md`
still carries the pre-M4 dispositions for all ten M4-family rows. A dated
post-acceptance amendment section was added to that document on 2026-07-30
rather than rewriting the operator-accepted rows in place.

## Decision lapse recorded 2026-07-31 (RECON2 R4): D2=A no longer applies

Decision D2=A restricted the sanitize repair to **managed rows only**, and the
operator accepted that restriction as safe on an explicit factual basis:
"media 2 waits for Storage Truth relocation … zero live production targets
until then". Both halves of that basis have since changed:

- Commit `3900645` (post-closure operator steer, listed above) removed the
  managed-scope guard from the `repair` ability. Verification against current
  code: `app/Policies/CuratorMediaPolicy.php` `repair` (lines 124–150) carries
  no `MediaRecordScope::allows()` check, while `delete` and `swap` still do.
- The Storage Truth relocation (2026-07-29) moved every root-level row into the
  managed roots, so sanitize now has live targets everywhere it can run.

D2=A is therefore **lapsed, not violated**: the guard removal was itself an
operator steer, and on 2026-07-31 the operator accepted the resulting boundary
— sanitize (with its journaled relocation side-effect) may reach any gallery
row that passes the policy's remaining checks. This handoff's earlier D2=A
narrative above is preserved as the decision history; from 2026-07-31 the
operative boundary is the one described here.
