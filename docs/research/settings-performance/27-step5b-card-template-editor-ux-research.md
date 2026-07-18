# Step 5B card-template editor UX research

Date: 2026-07-18
Audit: `LS-20260718-STEP5B-CARD-TEMPLATE-UX-01`
Approved option: `STEP5B-CARD-UX-O1`
Verified baseline: `e31118f1c9f0fa5b2494d72fa0dc2097f6dc9d07` on `main`, clean except for the branch being ahead of `origin/main`

## Scope and boundaries

This note records the Stage 2 research for the approved smallest coherent closure. It covers the existing Card Template editor preview layout, transient zoom, inline public-safe sample selection, Builder edit presentation, presentation-field refresh boundaries, and Builder summary localization. It does not authorize a settings migration, per-user server preference, custom mounted-action synchronization bridge, permission redesign, generalized preview platform, public-card redesign, dependency change, or another roadmap step.

The completed restricted-selector and Builder-parts-refresh closures remain binding. Preview interactions must stay read-only with respect to settings storage, settings lifecycle hooks, backup, reference scans, cache invalidation, and public persistence.

## Installed-version evidence

Laravel Boost reported the installed stack as Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, and Tailwind CSS 4.3.2.

Installed documentation and package source establish these relevant behaviors:

- A custom searchable Filament `Select` uses `getSearchResultsUsing()` for server search and `getOptionLabelUsing()` for resolving an already-selected value. `preload()` loads its initial options and `optionsLimit()` caps search results.
- Filament Builder supports conditional `blockPreviews(bool|Closure)` and customization of its native edit action through `editAction()`.
- The Builder edit action fills a cloned action schema and only writes that action state back to the authoritative Builder item when the action is submitted. Therefore native slide-over editing is Apply-time preview refresh, not live preview synchronization.
- Filament actions support `slideOver()`, `slideOverPosition()`, sticky modal chrome, and responsive modal widths. `SlideOverPosition::Start` uses logical start placement in the installed package CSS, including RTL-aware transforms.
- Filament schemas accept responsive column counts. This supports one column on narrow surfaces and two columns at a sufficiently wide breakpoint.
- Filament pages accept an `Htmlable` subheading. The standard header renders that surface below the title, making a compact header metadata partial smaller than a dedicated form section or a fake action.
- Livewire public component state can be changed from an Alpine-local control through `$wire`, while browser `localStorage` remains outside settings/model lifecycle.

## FilamentExamples research

The required two passes were performed before implementation planning.

### Pass 1: direct topics

Queries covered Builder edit slide-overs, logical start placement, searchable/preloaded custom selects, page header metadata, responsive schemas, and RTL. Results supplied neighboring custom-page, header-action, action-modal, and searchable-select patterns, but no exact Card Template Builder composition.

### Pass 2: refined topics

Queries were refined around `editAction()` plus `slideOver()`, `SlideOverPosition::Start`, modal widths, `getSubheading()` with `HtmlString`/views, custom Select search and selected-label callbacks, and browser-local preferences. The useful adaptation is to customize the native Builder action and use an app-owned header partial and preview-control schema. No source/read/detail operation was exposed in the available integration, so this was search/snippet research rather than deep source retrieval. Installed package source is the decisive API evidence.

## Existing seams and findings

### Preview layout and header

`resources/views/filament/pages/card-template-editor.blade.php` already uses a two-column responsive grid with the preview as the sticky logical-end column on wide screens and a narrow-screen preview action. The preview must remain in that column, with its rendered canvas continuously visible. The dedicated import-lock section currently consumes the top of the editor column; its short status belongs in the page subheading, while the existing long explanation belongs in a localized `?` tooltip.

The grid relies on logical document direction for placement. Browser evidence must assert geometry in English and Hebrew: preview right/editor left in LTR, preview left/editor right in RTL. This is more reliable than introducing physical left/right utility classes.

### Preview controls and zoom

The existing preview header contains the sample-modal and refresh actions. The approved compact toolbar can be collapsed independently from the canvas. Zoom is a page-local Alpine value with 10% steps, a 50%–150% clamp, and a reset to 100%; it is not settings state and is not remembered. CSS `zoom` participates in the browser scroll/layout plane in the supported browser target, avoiding the unmeasured/clipped scroll geometry that a bare transform would introduce. Browser acceptance must exercise 50%, 100%, and 150% and verify no overlap or clipping in the scroll plane.

### Inline sample selector

The existing `CardTemplatePreviewer` already centralizes public-safe family queries, eligibility, selected-label resolution, and the 50-result search cap. The smallest closure adds a separate 10-result preload path and reuses the same query constraints.

Image preference is ordering only:

- content items count only their own non-empty `image_path` or `external_thumbnail_url`;
- content groups count only their own non-empty `cover_path`;
- an inherited group cover does not count as a content item's own image;
- authors retain their current deterministic ordering and no image field is invented.

The 10-result initial preload and 50-result searched response are distinct contracts and require distinct tests with more than each threshold. Restricted state must not render or construct the selector, query preload/search/label paths, or accept direct/forged interactions.

For preview rendering only, a content item without an own image should not inherit its podcast cover. The existing card fallback remains the missing-image treatment, so no new asset or public default-image setting is needed. Public rendering outside preview keeps its current inheritance behavior.

### Builder display mode

Inline mode uses authoritative Builder state and can keep the existing 500 ms Builder-part refresh behavior. Slide-over mode uses Builder previews plus the native cloned edit action; preview refresh occurs after Apply. A custom bridge from mounted action state into authoritative preview state would be materially larger, easier to desynchronize, and is excluded by the approved option.

The display-mode preference is remembered in browser `localStorage` only. The server accepts only `inline` and `slide_over` into page-local Livewire state. This has no migration, no `AdminUxSettings` ownership, no settings writer, no backup/import lifecycle, and no claim of true per-user cross-browser persistence.

The native slide-over is configured at logical start and at a bounded large width. Its panel must not geometrically cover the preview column at the acceptance viewport. Filament's overlay and focus trap mean the preview remains visible but is intentionally not interactive until Apply or cancel.

Nested child Builders must use the same display-mode rule and slide-over action customization. Builder schemas should be one column on narrow widths and two columns on sufficiently wide slide-over/inline surfaces.

### Automatic presentation refresh

`family` and Builder-part paths already refresh automatically. The rendered draft also depends on `layout`, `density`, `image_size`, and `title_size`; these finite Select fields should use Livewire updates and refresh once per discrete change. `key` and `label` are identity/editor metadata and do not affect the rendered card, so they should not trigger preview work. No text field requires per-keystroke preview querying.

### Builder summaries

The no-label fallback should be the localized value `Unlabelled` / `ללא תווית`, without a Part prefix. Source and attribute summary values should resolve through the same localized registry option maps used by their Selects. Unknown legacy/corrupt values must remain visible in an escaped localized diagnostic fallback that includes the raw value.

## Security and performance implications

- The selector schema must return no components when preview is restricted or the family is protected/invalid. Blade also must not access the schema in restricted state.
- Every preload, search, label-resolution, state-update, direct method, and forged schema interaction path must recheck server-side eligibility.
- Only scalar sample identity and preview metadata may enter Livewire state; no protected records or part payloads may be serialized.
- Initial preload adds one bounded public-safe query only when an eligible selector is rendered. Searches remain capped at 50; selected-label resolution remains a single constrained lookup.
- Discrete presentation Select changes add one preview refresh request/query set per selection. Identity-only fields stay outside that boundary.
- Browser-local Builder preference may cause one initialization request when a stored non-default mode is restored; it causes no database or settings lifecycle work.
- Existing Step 5B/SP3C component HTML, query, serialized-state, and browser canaries remain the measurement planes. Any necessary budget movement must be evidence-backed rather than inferred from DOM or query counts alone.

## Decision

Implement `STEP5B-CARD-UX-O1` with native Apply-time slide-over behavior and browser-local display-mode memory. Keep live preview synchronization exclusive to inline authoritative Builder state. Reuse the existing previewer, registry, resolver, card presenter, and Filament/Livewire schema boundaries; do not add a custom action-state bridge or server persistence.
