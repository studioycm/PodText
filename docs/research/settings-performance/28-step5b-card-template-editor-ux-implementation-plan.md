# Step 5B card-template editor UX implementation plan

Date: 2026-07-18
Audit: `LS-20260718-STEP5B-CARD-TEMPLATE-UX-01`
Approved option: `STEP5B-CARD-UX-O1`
Research basis: `docs/research/settings-performance/27-step5b-card-template-editor-ux-research.md`

## Objective

Deliver the approved coherent Card Template editor UX closure without changing settings ownership, persisted settings schema, permissions, public-safe sample semantics beyond image-first selector ordering, or the public-card contract outside isolated preview behavior.

## Implementation sequence

1. **Header and responsive page shell**
   - Replace the dedicated import-lock form section with a compact localized page-subheading partial containing the short lock label, current badges, and a `?` tooltip with the existing long explanation.
   - Keep the editor as the first section of its column and the preview as the first/top surface in its own sticky responsive column.
   - Add stable browser hooks for logical LTR/RTL geometry without introducing physical placement classes.

2. **Compact preview controls and transient zoom**
   - Rework the preview header into a compact collapsible controls toolbar while keeping status and the rendered canvas outside the collapse.
   - Add grouped minus, plus, and reset controls with 10% steps, 50%–150% bounds, and 100% reset.
   - Keep zoom and toolbar-open state local to the current rendered page; use layout-participating CSS zoom inside the existing overflow plane.

3. **Inline public-safe sample Select**
   - Add a separate `previewControls` Filament schema with scalar page-local state.
   - Render it only when the server says selection is allowed.
   - Configure exactly 10 initial options, server search capped at 50, selected-label resolution, and a discrete live update that refreshes preview without persistence.
   - Retire the Choose Sample modal/action while retaining the narrow-screen preview action.

4. **Sample ordering and preview-only missing-image behavior**
   - Add a distinct preload limit of 10 and retain the search limit of 50.
   - Put content items/groups with their own image fields first, then keep deterministic ordering; do not count inherited podcast cover for an episode and do not alter author image behavior.
   - Add an explicit presenter/resolver parameter so only Card Template preview disables group-cover inheritance. Continue using the existing card missing-image fallback.

5. **Builder display modes and native slide-over**
   - Add validated page-local `inline` / `slide_over` mode state and a small in-form custom view control backed by browser `localStorage`.
   - Keep the default as slide-over. Do not add an `AdminUxSettings` field or any server persistence.
   - Make Builder previews conditional: enabled in slide-over mode and disabled in inline mode, including nested child Builders.
   - Customize top-level and nested edit actions as native logical-start slide-overs with sticky chrome and a bounded large responsive width.
   - Use one schema column on narrow widths and two on sufficiently wide editor/slide-over surfaces.
   - Preserve native Apply-time synchronization for slide-over mode; do not implement live mounted-action bridging.

6. **Presentation refresh and summaries**
   - Make `layout`, `density`, `image_size`, and `title_size` refresh preview on discrete Livewire change.
   - Keep `key` and `label` outside preview refresh.
   - Remove the Part prefix from the unlabelled summary fallback.
   - Resolve source/attribute summary values from the existing localized registry options and show escaped localized diagnostics for unknown values.

7. **Automated verification**
   - Extend focused feature tests for automatic presentation refresh, identity non-refresh, display-mode validation, native slide-over configuration/state preservation, localized summaries, image-first ordering, 10 preload versus 50 search caps, all three families, selected-label resolution, preview-only missing-image fallback, restricted no-render/no-query/direct/forged paths, no writers/lifecycle/backup/cache/reference side effects, and serialized-state exclusions.
   - Extend canaries only where a measured boundary changes; keep claims within query/component/serialized/browser planes.
   - Add/update Pest browser acceptance for wide/narrow layouts, English/Hebrew logical placement, compact header metadata tooltip, selector search, zoom bounds/scroll plane, display-mode memory, slide-over visibility/non-overlap/focus trap, Apply-time refresh, inline live refresh, and all-family operation.

8. **Closeout and canonical gates**
   - Perform a requirement-by-requirement sweep against this plan and the approved audit.
   - Update current project state, the Step 10R-9F mini-step ledger, and the Step 5B handoff without selecting another roadmap step.
   - Run final gates sequentially on the final file state: `vendor/bin/pint --test`, `vendor/bin/filacheck`, `npm run build`, then full `php artisan test` last. After any later edit, restart at Pint.
   - Commit implementation plus docs/handoff with the handoff hash pending, then immediately make the docs-only implementation-hash backfill commit. Do not push.

## Stop and drift conditions

Return to Stage 1 rather than improvise if implementation requires a migration, a new persisted setting, true per-user server persistence, a custom live-in-slide-over state bridge, a dependency change, permission redesign, generalized preview infrastructure, broadened default-image ownership, a public-card redesign, or a material increase to an established query/serialized/browser budget.
