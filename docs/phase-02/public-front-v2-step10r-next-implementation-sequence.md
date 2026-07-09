# Public Front v2 Step 10R / 9F Next Implementation Sequence

This file records the active continuation-runner order after Step 10R-HF1. The central ledger remains the source of truth for per-run status:

`docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

## Active Order

| # | Step | Depends on | One-line scope |
|---|---|---|---|
| 1 | Step 10R-M5 | M4, HF1 | Card-template labels/icons rendering, label alignment, and `part_group` nested parts. |
| 2 | Step 10R-IP1 | M5 | Episode page settings group, date settings, info-badge tokens, and card-side date attributes. |
| 3 | Step 10R-IP2 | IP1 | Episode page header and info-line rebuild. |
| 4 | Step 10R-IP3 | IP1, IP2 | Transcript details row, actions menu, share move, font size, fullscreen, and player toggle. |
| 5 | Step 10R-M6 | M5, IP1-IP3 | Stabilization docs, close M1-M5 gaps, verify IP regressions, and decide C1 fate. |
| 6 | Step 10R-P1 | M6 | Cache validated public-front config with versioned key `public_front.config.v1`. |
| 7 | Step 10R-P2 | P1 | Listing fetch-window, lazy filter options/form definitions, and opt-in aggregate subselects. |
| 8 | Step 10R-P3 | P2 | Derived transcript segments and viewer render economy. |
| 9 | Step 10R-B4 | M1-M6 complete, IP1-IP3 complete | Converge legacy card options with card presentation services. |
| 10 | Step 10R-C2 | B4 | Normalize card layout consistency and semantic layout tokens. |
| 11 | Step 9F-A | M1-M6, IP1-IP3, P1-P3, B4, C2 | Rich homepage columns foundation. |
| 12 | Step 9F-B | 9F-A | Footer config and footer renderer. |
| 13 | Step 9F-C | 9F-B | Footer/rich section admin UX and integration polish. |
| 14 | Step 11 | all above and explicit Yoni approval | Seeders/demo/assets/cleanup. |
| 15 | Prompt 13 | explicit Yoni approval | Dashboard metrics. |

## Guardrails

- Each run implements exactly the first pending mini-step unless Yoni explicitly selects a different approved step.
- Step 10R-C1 stays paused/superseded until M6 records its final status.
- Step 10R-B4 must not start until M1-M6 and IP1-IP3 are complete.
- Step 9F must not start until M1-M6, IP1-IP3, P1-P3, B4, and C2 are complete.
- Step 11 and Prompt 13 require explicit Yoni approval.
- Prompt 14 and Prompt 15 remain future work and must not start from this runner.

## Current Recommendation

Continue with Step 10R-M6 after IP3 review. Step 10R-IP1 through Step 10R-IP3 are complete, so M6 should run the stabilization audit, verify R1-R23, verify IP1-IP3 regressions, and record the final Step 10R-C1 status before P1 starts.
