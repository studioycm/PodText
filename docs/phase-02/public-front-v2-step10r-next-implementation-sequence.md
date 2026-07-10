# Public Front v2 Step 10R / 9F Next Implementation Sequence

This file records the active continuation-runner order after Step 10R-M6 plus the post-M6 admin/settings enhancement planning addendum. The central ledger remains the source of truth for per-run status:

`docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

Importer Workbench WB1-WB7 are tracked in that ledger's `Importer Workbench Track`
section; WB runs interleave only by Yoni's per-run selection and do not change the
main Public Front v2 queue order below.

## Active Order

| # | Step | Depends on | One-line scope |
|---|---|---|---|
| 1 | Step 10R-UX1 | M6 | Complete: admin navigation order, relation-manager tab placement, record-action column placement, and wide/full-width modal standards. |
| 2 | Step 10R-UX2 | UX1 | Complete: effective/featured/main transcription edit action on episode lists in the Episodes resource and podcast episode relation manager. |
| 3 | Step 10R-V1a | M6, UX1 | Complete: default/no-image fallback settings with per-family inherit/custom/none modes and public fallback rendering. |
| 4 | Step 10R-V1b | V1a | Complete: Heroicon-enum registry, permanent legacy aliases, and shared lazy searchable icon-picker helper. |
| 5 | Step 10R-V1c | V1a | Complete: custom hex color controls and theme-safe persistent podcast-palette cache. |
| 6 | Step 10R-P1 | UX1, UX2, V1a-V1c | Complete: cached validated public-front config with versioned key `public_front.config.v1`, settings-migration watermark, save invalidation, and corrupted-cache fallback. |
| 7 | Step 10R-S2 | P1 | Complete: settings backup versions, shared package serializer, compare/download, retention, and restore flow. |
| 8 | Step 10R-S2V | S2 | Complete: backup visual snapshots with Playwright thumbnails/full captures, private storage, queued jobs, and gallery UI. S1a/S1b do not depend on S2V. |
| 9 | Step 10R-S1a | P1, S2 | Complete: settings export and import wizard core with schema boundary, export, source/validate/dry-run/selection/apply replace flow, before-import backup, and S2V audit corrections. |
| 10 | Step 10R-S1b | S1a | Import locks and add-only mode: persistent locks, lock manager, hard wizard enforcement, merge mode, and outcome chips. Importer Workbench opens after S1b. |
| 11 | Step 10R-P2 | S1b | Listing fetch-window, lazy filter options/form definitions, and opt-in aggregate subselects. |
| 12 | Step 10R-P3 | P2 | Derived transcript segments and viewer render economy. |
| 13 | Step 10R-AX1 | P2, P1 | GSAP motion foundation with approved AX1-only dependency install, public-panel JS wiring, finite `PodTextMotion` preset/data-attribute contract, motion tokens, reduced-motion, and FOUC/SEO guard. |
| 14 | Step 10R-SL1 | AX1, M5 | Result display-template builder foundation, finite vocabularies, admin builder, surface selectors, grid default template, and per-template motion config. |
| 15 | Step 10R-SL2 | SL1 | Flip-slider rendering engine with scroll-snap, bounded lazy page fetch, front-face cards, RTL-aware controls, and AX1 presets. |
| 16 | Step 10R-SL3 | SL2 | Flip animation and smart side-open back face rendered from existing card templates using GSAP Flip from AX1. |
| 17 | Step 10R-SL4 | SL2 | Quick-view modal with lazy mounted content, density/label controls, full-page deep link, and AX1 open/close choreography. |
| 18 | Step 10R-AX2 | AX1 | Loading/update/page-transition concealment and motion retrofit for existing grids, sections, load-more, and page transitions. |
| 19 | Step 10R-AX3 | AX2 | Scroll-linked effects for public headers, transcript reading progress, and cover emphasis. |
| 20 | Step 10R-B4 | M1-M6 complete, IP1-IP3 complete, P1-P3 complete, SL1-SL4 complete, AX1-AX3 complete | Converge legacy card options with card presentation services, now covering slider/modal/motion surfaces. |
| 21 | Step 10R-C2 | B4 | Normalize card layout consistency and semantic layout tokens, now covering slider/modal/motion surfaces. |
| 22 | Step 9F-A | all 10R above | Rich homepage columns foundation. |
| 23 | Step 9F-B | 9F-A | Footer config and footer renderer. |
| 24 | Step 9F-C | 9F-B | Footer/rich section admin UX and integration polish. |
| 25 | Step 11 | all above and explicit Yoni approval | Seeders/demo/assets/cleanup. |
| 26 | Prompt 13 | explicit Yoni approval | Dashboard metrics. |

## Guardrails

- Each run implements exactly the first pending mini-step unless Yoni explicitly selects a different approved step.
- Step 10R-C1 is superseded by the completed M1-M6 path and must not run as written.
- Step 10R-P1 must wait for UX1, UX2, and V1a-V1c so the new settings shape is known before validated config caching lands.
- Step 10R-S2 must run before S1a so import can reuse backups and the shared package serializer.
- Step 10R-S2V is complete. S1a/S1b have no dependency on S2V beyond the now-shipped snapshot follow-up.
- Step 10R-S1a must run after P1 and S2 so import flows can use the single versioned public-front config cache invalidation path and backup package flow.
- Step 10R-S1b must run after S1a. The Importer Workbench track opens after S1b.
- Step 10R-AX1 must run after P2/P3 and before SL1 so display templates, sliders, flip interactions, and quick-view modals consume one shared motion boundary from the start.
- Step 10R-SL1-SL4 must run after AX1 and P2/P3 unless Yoni explicitly resequences them; bounded fetching remains mandatory either way.
- Step 10R-AX2 and Step 10R-AX3 run after SL4 by default; AX2 may be pulled earlier by editing the ledger if loading/transition concealment becomes more urgent than the slider.
- Motion goes only through the `PodTextMotion` boundary with finite preset tokens. Reduced-motion always wins and is not a setting. ScrollSmoother/scroll-jacking are banned. GSAP never takes over slider scroll transport.
- Motion must not add artificial loading latency, must animate transforms/opacity only, and must leave content visible without JS.
- Page transitions use the cross-document View Transitions API as progressive enhancement while public SPA mode stays OFF.
- Step 10R-B4 must not start until M1-M6, IP1-IP3, P1-P3, SL1-SL4, and AX1-AX3 are complete.
- Step 9F must not start until all prior 10R steps, including SL1-SL4, AX1-AX3, B4, and C2, are complete.
- Step 11 and Prompt 13 require explicit Yoni approval.
- Prompt 14 and Prompt 15 remain future work and must not start from this runner.

## Current Recommendation

Continue with Step 10R-P2 when Yoni returns to the main queue. Step 10R-UX1 standardized admin navigation, table action
placement, action modal defaults, section width defaults, and relation-manager tabs.
Step 10R-UX2 added the shared effective transcription edit action on both episode list
surfaces. Step 10R-V1a added finite default/no-image fallback settings and shared
public image fallback rendering. Step 10R-V1b added enum-backed icon settings and a
shared lazy searchable icon picker. Step 10R-V1c added strict custom hex color controls
and a theme-safe cached podcast palette. Step 10R-P1 added the validated public-front
config cache boundary. Step 10R-S2 added settings backup versions and restore.
Step 10R-S2V added backup visual snapshots plus the S2 system-only prune/file-cleanup
correction. Step 10R-S1a added the settings export/import wizard core and S2V audit
corrections. Step 10R-S1b, HF2, UX3, S1c, MP1, and S1d are complete; the Importer
Workbench side track is recorded separately in the central ledger. The v4 continuation
order is reflected here and in the central ledger.
