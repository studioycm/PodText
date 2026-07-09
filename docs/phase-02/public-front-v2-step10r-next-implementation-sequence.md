# Public Front v2 Step 10R / 9F Next Implementation Sequence

This file records the active continuation-runner order after Step 10R-M6 plus the post-M6 admin/settings enhancement planning addendum. The central ledger remains the source of truth for per-run status:

`docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

## Active Order

| # | Step | Depends on | One-line scope |
|---|---|---|---|
| 1 | Step 10R-UX1 | M6 | Complete: admin navigation order, relation-manager tab placement, record-action column placement, and wide/full-width modal standards. |
| 2 | Step 10R-UX2 | UX1 | Effective/featured/main transcription edit action on episode lists in the Episodes resource and podcast episode relation manager. |
| 3 | Step 10R-V1a | M6, UX1 | Default/no-image fallback settings with per-family inherit/custom/none modes and public fallback rendering. |
| 4 | Step 10R-V1b | V1a | Heroicon-enum registry and shared lazy searchable icon-picker helper. |
| 5 | Step 10R-V1c | V1a | Custom hex color controls and theme-safe persistent podcast-palette cache. |
| 6 | Step 10R-P1 | UX1, UX2, V1a-V1c | Cache validated public-front config with versioned key `public_front.config.v1` and settings-migration watermark. |
| 7 | Step 10R-S2 | P1 | Settings backup versions, shared package serializer, compare/download, retention, and restore flow. |
| 8 | Step 10R-S1 | P1, S2 | Settings import/export package with dry-run validation, backup-before-import, transaction, and cache invalidation. |
| 9 | Step 10R-P2 | S1 | Listing fetch-window, lazy filter options/form definitions, and opt-in aggregate subselects. |
| 10 | Step 10R-P3 | P2 | Derived transcript segments and viewer render economy. |
| 11 | Step 10R-SL1 | P2, M5 | Result display-template builder foundation, finite vocabularies, admin builder, surface selectors, and grid default template. |
| 12 | Step 10R-SL2 | SL1 | Flip-slider rendering engine with scroll-snap, bounded lazy page fetch, front-face cards, and RTL-aware controls. |
| 13 | Step 10R-SL3 | SL2 | Flip animation and smart side-open back face rendered from existing card templates. |
| 14 | Step 10R-SL4 | SL2 | Quick-view modal with lazy mounted content, density/label controls, and full-page deep link. |
| 15 | Step 10R-B4 | M1-M6 complete, IP1-IP3 complete, P1-P3 complete, SL1-SL4 complete | Converge legacy card options with card presentation services, now covering slider/modal surfaces. |
| 16 | Step 10R-C2 | B4 | Normalize card layout consistency and semantic layout tokens, now covering slider front/back faces. |
| 17 | Step 9F-A | all 10R above | Rich homepage columns foundation. |
| 18 | Step 9F-B | 9F-A | Footer config and footer renderer. |
| 19 | Step 9F-C | 9F-B | Footer/rich section admin UX and integration polish. |
| 20 | Step 11 | all above and explicit Yoni approval | Seeders/demo/assets/cleanup. |
| 21 | Prompt 13 | explicit Yoni approval | Dashboard metrics. |

## Guardrails

- Each run implements exactly the first pending mini-step unless Yoni explicitly selects a different approved step.
- Step 10R-C1 is superseded by the completed M1-M6 path and must not run as written.
- Step 10R-P1 must wait for UX1, UX2, and V1a-V1c so the new settings shape is known before validated config caching lands.
- Step 10R-S2 must run before S1 so import can reuse backups and the shared package serializer.
- Step 10R-S1 must run after P1 and S2 so import flows can use the single versioned public-front config cache invalidation path and backup package flow.
- Step 10R-SL1-SL4 must run after P2/P3 unless Yoni explicitly resequences them; bounded fetching remains mandatory either way.
- Step 10R-B4 must not start until M1-M6, IP1-IP3, P1-P3, and SL1-SL4 are complete.
- Step 9F must not start until all prior 10R steps, including SL1-SL4, B4, and C2, are complete.
- Step 11 and Prompt 13 require explicit Yoni approval.
- Prompt 14 and Prompt 15 remain future work and must not start from this runner.

## Current Recommendation

Continue with Step 10R-UX2. Step 10R-UX1 is complete and standardized admin navigation,
table action placement, action modal defaults, section width defaults, and relation-manager
tabs. The v3 continuation order is now reflected here and in the central ledger.
