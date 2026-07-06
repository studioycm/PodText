# Public Front v2 Step 9R Verification and Fixes Plan

## Purpose

Step 9R verifies Step 8 and Step 9 against their implementation plans, repairs the remaining public menu/header and UX issues, improves FilamentExamples MCP process guidance, and documents future footer/section-builder scope without implementing the full footer manager.

## Preflight Snapshot

- Branch: `main` tracking `origin/main`.
- Working tree before Step 9R work: one pre-existing Markdown rename from `prompts/public-front-v2-step9r-menu-header-ux-fixes-codex-prompt-v2.md` to `docs/phase-02/public-front-v2-step9r-menu-header-ux-fixes-codex-prompt-v2.md`.
- No unexpected PHP, Blade, migration, test, config, or app-code dirt was present before implementation.
- Recent commits include Step 8 `f3d137e feat: add public podcasts and groups ux`, Step 9 `5cf3363 feat: add public menu header and ux fixes`, and later Step 9R-adjacent commits through `fcaff1a fix: finalize public menu/header UX fixes, homepage enhancements, and future scope refinements`.
- Step 9 settings migrations are run locally, including `2026_07_06_000000_normalize_public_menu_header_and_about_cards` and `2026_07_06_000001_ensure_public_about_team_legacy_settings`.
- Prompt 13 has not started.

## Step 8 Plan Verification Matrix

| Planned item | Actual repo evidence | Status | Follow-up needed |
|---|---|---|---|
| Add canonical `/podcasts` index route | `BrowsePublicContentGroups::getSlug()` returns `podcasts`; `php artisan route:list --path=podcasts` shows `/podcasts`. | Implemented | None. |
| Move group detail to `/podcasts/{contentGroupSlug}` | `ShowContentGroup` is registered under the public panel; Step 8 handoff confirms `/groups` routes are absent. | Implemented | None. |
| Keep homepage root unchanged | `BrowseContentGroups` remains root route and renders `ContentItemSearch` with `context="home"`. | Implemented | Step 9R must repair root query-param chrome. |
| Add public group query support | `App\Support\PublicFront\Groups\PublicContentGroupQueries` exists and Step 8 tests cover published group/public item visibility. | Implemented | None. |
| Add `podcasts_page` JSON settings | `PublicFrontConfigRegistry::defaults()` and `PublicFrontConfigValidator` include `podcasts_page`; settings migration ran. | Implemented | None. |
| Add podcast/group cards and detail item browser | `x-public.content-group-card`, `ContentGroupBrowser`, and `ContentItemBrowser` exist. | Implemented | Step 9R should add image fit/radius settings where safe. |
| No `Podcast` or `Episode` models | `find app -path '*Podcast.php' -o -path '*Episode.php'` returned no model files. | Implemented | Keep this invariant in tests. |
| Do not reintroduce public Filament Tables | Public group/search views use Livewire/Blade card components; tests assert no `fi-ta-table`. | Implemented | Keep tests. |

## Step 9 Plan Verification Matrix

| Planned item | Actual repo evidence | Status | Follow-up needed |
|---|---|---|---|
| Public settings page organized into tabs | `PublicContentSettings` uses `Tabs::make(...)->vertical()` with Homepage, Display, Menu/Header, Podcasts, About, Forms, Advanced. | Implemented | Add Step 9R fields inside existing tabs only. |
| About/team profile image and card settings | `about_page.settings.team_card` exists; About profile cards render images through `PublicAboutPageRenderer`. | Implemented | Add image fit/radius settings for About cards and image blocks. |
| Explicit public heading classes | `SafeMarkdownRenderer::publicContentClasses()` contains H1-H6 classes. | Implemented | Add Step 9R test coverage for H1-H6 hierarchy. |
| Contributor compact cards and preview row | `ContributorDirectory` has selected contributor, preview search, sort, page size, and preview row. | Partial | Preview related item grid is forced to `layout="rows"` and needs multi-column default. |
| Homepage chrome suppressed | Default homepage sections suppress discovery chrome. | Partial/regression | `/?sort=latest_transcription` still triggers `discovery-chrome`; root query state must be ignored or canonicalized. |
| Section header Latest controls/show-all | `content-item-search.blade.php` renders section header action row with latest search, next/previous, view-more. | Implemented | Preserve. |
| Minimal `content_block` section support | `PublicDisplaySectionRegistry::CONTENT_BLOCK` and renderer branches exist. | Implemented | Full section builder remains deferred. |
| Public menu/header JSON rendering | `PublicHeader` and `PublicMenuConfigReader` exist; header opens Step 6 forms and mounts `PublicFormModal`. | Partial | Step 9R must add logo config, header search, item alignment, and theme display modes. |
| Theme selector light/dark/system | Header stores theme locally and renders system/light/dark buttons. | Partial | Step 9R must support `text`, `text_icon`, `icon`, and `trigger_icon_menu` display modes. |
| No menu/header settings-only models | No `PublicMenu`/`PublicMenuItem` classes under `app/Models`. | Implemented | Keep test. |

## Step 10 Overlap Decision

Step 9R repairs contributor behavior already touched by Step 9:

- Keep compact cards limited to name and count badge.
- Keep preview row below the list.
- Keep preview page link inside the preview.
- Keep preview search, page sizes 10/15/20, and sort toggles.
- Repair preview related item grid so it defaults to multiple columns.

Step 10 remains responsible for:

- Full top-transcribers homepage redesign.
- Horizontal top-transcriber selector.
- Top-transcriber preview below the selector.
- Contributor preview item pagination 5/10/15 inside top-transcriber sections.
- Contributor page UX refinements beyond direct directory-preview repairs.
- Contributor section-level settings beyond direct repair defaults.

## Footer/Section-Builder Future Plan Summary

The user wants richer homepage section and footer-builder features, including multi-column layouts, Builder blocks per column, rich/Markdown/smart content, links/actions, form CTA blocks, a footer manager, and a bottom bar. This is intentionally deferred from Step 9R because it would create a new rendering subsystem and broad settings schema. The separate planning file is `docs/phase-02/public-front-v2-step9f-section-footer-builder-plan.md`.

Recommended sequence:

1. Step 9R repair.
2. Step 10 Contributors and Top Transcribers UX.
3. Step 9F/10F Footer + Rich Section Builder foundation before Step 11 seeders.
4. Step 11 Seeders, Demo Data, Assets, and Cleanup.
5. Prompt 13 Dashboard Metrics.

## Issue Diagnoses And Fix Plan

### Discovery Chrome And Root Query Params

Diagnosis: `ContentItemSearch::mount()` sets `$sortWasSelected` from any root `sort` query, and `hasActiveDiscoveryState()` makes the homepage leave section mode. Fix root `/` to always stay in homepage-section mode; `/search` remains the dedicated discovery UI.

### Duplicate Public Page Titles

Diagnosis: public page Blade views render their own H1s, while Filament page headers can still render page titles. Add an empty public page header override to custom public pages with their own H1.

### Menu/Header Logo Settings

Diagnosis: the header directly renders `asset('images/podtext-logo.jpg')`. Add normalized `menu_config.logo` fields for light path, dark path, alt text, display mode, and size. Resolve paths through safe storage/assets fallback. SVG is supported only as a storage-managed file path or controlled bundled asset, not raw inline SVG.

### Theme Selector Display Modes

Diagnosis: `theme_selector.mode` currently means theme options (`light_dark_system`) rather than visual display mode. Add `theme_selector.display_mode` with `text`, `text_icon`, `icon`, and `trigger_icon_menu`. Preserve `mode` for theme availability.

### Heading Typography Regression

Diagnosis: central Markdown classes already include H1-H6. Add focused Step 9R tests so future changes do not flatten headings again.

### Image Styling Settings

Diagnosis: card components hard-code `object-cover` and rounded classes. Add semantic `image_fit` and `image_radius` to card options and relevant JSON config, then map to fixed classes in Blade/PHP. Apply to item cards, group cards, About team cards, and About image blocks where practical.

### Contributor Preview Grid

Diagnosis: contributor preview uses `x-public.content-item-grid layout="rows"`, forcing one item per row. Change to `layout="cards"` or a semantic grid default.

### Item Image Fallback

Diagnosis: item cards render only `external_thumbnail_url`; if missing, they show text fallback even when the parent group has a cover. Resolve images in order: item thumbnail, group cover, fallback.

### Group/Podcast Badge Behavior

Diagnosis: `content-group-badge` always renders cover or initials. Add text-only default, optional thumbnail mode, combined title mode with default separator `" - "`, and suppress duplicate group thumbnail when group cover is already the main card image.

### Header Global Search

Diagnosis: header has no search form. Add normalized `menu_config.search` settings and render a rounded form that submits to `/search?q=...` without owning Livewire search state.

### Menu Item Alignment

Diagnosis: menu items are hard-coded between logo and mobile toggle. Add `menu_config.items_alignment` values `start`, `center`, and `end`, mapped to RTL-aware fixed flex classes.

## Exact Files To Change

- `AGENTS.md`
- `.ai/guidelines/tooling-quality.md`
- `docs/phase-02/ai-development-lessons.md`
- `docs/phase-02/public-front-v2-agent-usage-index.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/research/public-front-v2/13-step9r-menu-header-ux-fixes-mcp-research.md`
- `docs/phase-02/public-front-v2-step9f-section-footer-builder-plan.md`
- `docs/phase-02/public-front-v2-step9r-menu-header-ux-fixes-handoff.md`
- `docs/phase-02/current-project-state.md`
- `app/Filament/Pages/PublicContentSettings.php`
- relevant `app/Filament/Public/Pages/*` public page classes
- `app/Livewire/Public/ContentItemSearch.php`
- `app/Livewire/Public/PublicHeader.php`
- `app/Support/PublicContent/PublicContentCardOptions.php`
- `app/Support/PublicFront/Menu/PublicMenuConfigReader.php`
- `app/Support/PublicFront/Menu/PublicMenuRenderer.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicFront/About/PublicAboutPageRegistry.php`
- `resources/views/livewire/public/public-header.blade.php`
- `resources/views/livewire/public/contributor-directory.blade.php`
- `resources/views/components/public/content-item-card.blade.php`
- `resources/views/components/public/content-group-badge.blade.php`
- `resources/views/components/public/content-group-card.blade.php`
- `resources/views/components/public/about/profile-card.blade.php`
- `resources/views/filament/public/pages/about-page.blade.php`
- `lang/en/admin.php`, `lang/he/admin.php`
- `lang/en/public.php`, `lang/he/public.php`
- `tests/Feature/PublicStep9RMenuHeaderUxFixesTest.php`
- existing public tests only if behavior-specific assertions must be updated.

## Tests To Add Or Update

Add `tests/Feature/PublicStep9RMenuHeaderUxFixesTest.php` covering:

- MCP guidance docs contain the protocol.
- Step 10 overlap and future section/footer plan docs exist.
- `/` and `/?sort=latest_transcription` do not render `discovery-chrome`.
- `/search` still renders discovery/filter UI.
- public page titles are not duplicated where custom H1s exist.
- menu logo settings normalize and render fallback/light/dark markers.
- unsafe logo paths and non-HTTPS external menu URLs are rejected/ignored.
- theme selector display modes render markers.
- H1-H6 public content classes exist.
- image fit/radius settings normalize and render classes/markers.
- contributor preview related items use a multi-column grid.
- item cards fall back to group cover image.
- group badge is text-only by default and supports combined title with default `" - "` separator.
- header global search submits to `/search?q=...`.
- menu item alignment renders RTL-safe markers/classes.
- no `PublicMenu`, `PublicMenuItem`, `PublicFormDefinition`, `Podcast`, or `Episode` model exists.

Run the existing public regression filters listed in the Step 9R prompt after the focused test.
