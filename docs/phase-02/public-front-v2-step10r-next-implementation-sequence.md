# Public Front v2 Step 10R / 9F Next Implementation Sequence

This file records the active continuation-runner order after Step 10R-M6 plus the post-M6 admin/settings enhancement planning addendum. The central ledger remains the source of truth for per-run status:

`docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

## Active Order

| # | Step | Depends on | One-line scope |
|---|---|---|---|
| 1 | Step 10R-UX1 | M6 | Admin navigation order, relation-manager tab placement, record-action column placement, and wide/full-width modal standards. |
| 2 | Step 10R-UX2 | UX1 | Effective/featured/main transcription edit action on episode lists in the Episodes resource and podcast episode relation manager. |
| 3 | Step 10R-V1 | M6, UX1 | Default/no-image uploads, expanded safe icon picker, custom-color controls, and light/dark-safe podcast-image color sampling. |
| 4 | Step 10R-P1 | UX1, UX2, V1 | Cache validated public-front config with versioned key `public_front.config.v1`. |
| 5 | Step 10R-S1 | P1, V1 | Settings import/export plan plus versioned JSON package, dry-run validation, backup-before-import, and cache invalidation. |
| 6 | Step 10R-S2 | P1, S1 | Settings backup versions plan plus backup, compare/download, retention, and restore flow. |
| 7 | Step 10R-P2 | S2 | Listing fetch-window, lazy filter options/form definitions, and opt-in aggregate subselects. |
| 8 | Step 10R-P3 | P2 | Derived transcript segments and viewer render economy. |
| 9 | Step 10R-B4 | M1-M6 complete, IP1-IP3 complete, P1-P3 complete | Converge legacy card options with card presentation services. |
| 10 | Step 10R-C2 | B4 | Normalize card layout consistency and semantic layout tokens. |
| 11 | Step 9F-A | M1-M6, IP1-IP3, P1-P3, B4, C2 | Rich homepage columns foundation. |
| 12 | Step 9F-B | 9F-A | Footer config and footer renderer. |
| 13 | Step 9F-C | 9F-B | Footer/rich section admin UX and integration polish. |
| 14 | Step 11 | all above and explicit Yoni approval | Seeders/demo/assets/cleanup. |
| 15 | Prompt 13 | explicit Yoni approval | Dashboard metrics. |

## Guardrails

- Each run implements exactly the first pending mini-step unless Yoni explicitly selects a different approved step.
- Step 10R-C1 is superseded by the completed M1-M6 path and must not run as written.
- Step 10R-P1 must wait for UX1, UX2, and V1 so the new settings shape is known before validated config caching lands.
- Step 10R-S1/S2 must run after P1 so import/restore flows can use the single versioned public-front config cache invalidation path.
- Step 10R-B4 must not start until M1-M6, IP1-IP3, and P1-P3 are complete.
- Step 9F must not start until M1-M6, IP1-IP3, P1-P3, B4, and C2 are complete.
- Step 11 and Prompt 13 require explicit Yoni approval.
- Prompt 14 and Prompt 15 remain future work and must not start from this runner.

## Current Recommendation

Continue with Step 10R-UX1 after this planning addendum review. Step 10R-M6 completed the stabilization audit, verified R1-R23, verified IP1-IP3 regressions, aligned `transcription_display` defaults to `effective_only`, and marked Step 10R-C1 superseded. The post-M6 admin/settings enhancement plan is recorded in `docs/phase-02/public-front-v2-admin-settings-enhancement-plan.md`.
