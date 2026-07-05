# Public Front v2 Step 9 Public Menu/Header and UX Fixes Implementation Plan

## Current public settings page structure and planned tab layout

`App\Filament\Pages\PublicContentSettings` currently renders one long stacked schema with open top-level sections for homepage settings, public display, card display, public-front JSON configuration, podcasts, About, card templates, and forms. Several top-level sections use `->columns(3)`, which makes the main settings surface feel cramped.

Plan:

- Wrap major domains in `Tabs::make('public_content_settings_tabs')`.
- Use these tabs: Display / General, Homepage / Sections, Podcasts, Forms, About, Menu / Header, Advanced / Diagnostics.
- Move existing fields into full-width collapsible `Section` components inside tabs.
- Keep top-level tab content single-column. Use field-level columns only inside compact sections or repeaters/fieldsets.
- Preserve all existing state paths and validation behavior.
- Add Menu/Header controls inside the new Menu / Header tab.

## About/team profile rendering diagnosis

Team images are stored as JSON settings under `about_page.team_profiles.*.image_path`. The public profile card calls `PublicAboutPageRenderer::imageUrl()`, but the current card has no profile-card display settings and always renders the same row-style card when an image URL is resolved.

Plan:

- Keep team profiles in `about_page.team_profiles`; do not add models/tables.
- Extend `about_page.settings.team_card` with safe semantic keys:
  - `show_image`
  - `image_size`
  - `layout`
  - `density`
  - `show_title`
  - `show_description`
  - `description_lines`
- Add registry options and validator normalization for those keys.
- Pass normalized settings to `x-public.about.profile-card`.
- Render image URLs through `PublicAboutPageRenderer` and support both string paths and one-item FileUpload arrays during normalization.

## Heading typography diagnosis and fix plan

About page chrome has explicit H1 styling, but Markdown/RichEditor containers style only H2/H3 and rely on inherited browser/body reset behavior for H1, H4, H5, and H6.

Plan:

- Add a reusable public rich-content class string in `PublicAboutPageRenderer`.
- Apply explicit Tailwind descendant classes for H1-H6 in About Markdown, RichEditor, callout content, and `x-public.markdown-content`.
- Keep sanitized rendering unchanged.
- Do not allow raw classes from JSON.

## Contributor directory current layout and fix plan

`ContributorDirectory` currently renders search controls, then a two-column grid with contributor cards beside a sticky preview. `x-public.contributor-card` includes avatar initials, bio preview, preview action, and direct page link.

Plan:

- Add Livewire state for `perPage`, `sort`, and `previewSearch`.
- Keep selected contributor state URL-backed by `selectedContributorId`.
- Paginate contributors with 10, 15, and 20 page-size options.
- Add sort toggle buttons for A-Z, Z-A, count down, and count up.
- Change compact contributor cards to buttons with only:
  - contributor name;
  - one published-count badge with icon;
  - title/tooltip text for the semantic label.
- Remove compact-card direct actions and direct page link.
- Render preview as a separate full-width row under the list/pagination.
- Put the contributor page link inside the preview card.
- Add a preview related-items search that filters the selected contributor's public item list.
- Preserve `Author`, public contributor visibility, and public transcription/count rules.

## Homepage top chrome diagnosis and fix plan

The homepage currently renders Filament's page header from `BrowseContentGroups::getTitle()`, then a custom page intro in `browse-content-items.blade.php`, then a global search/filter card inside `ContentItemSearch` even when default homepage sections render.

Plan:

- Override the homepage Filament page header with an empty header view.
- Remove the custom homepage intro from `browse-content-items.blade.php`.
- Hide the global search/sort/filter panel when the homepage is in default section mode.
- Keep `/search`, `/podcasts`, `/about`, category/tag pages, contributor pages, and detail pages unchanged.

## Section header/search/show-all layout plan

Latest section controls currently render in a separate bordered control card below the section heading, while the show-all link is a small text link.

Plan:

- Add a compact section-header control row for Latest:
  - heading/target label on the start side;
  - lightweight search, next/previous controls, and show-all link on the end side.
- Stack controls cleanly on mobile.
- Style show-all as a first-class action link/button.
- Keep bottom load-more where configured.
- Do not reintroduce public Filament Tables.

## Minimal content/block section support plan

The existing Step 4 section architecture can support a JSON-only source without new models.

Plan:

- Add `content_block` as a source type.
- Store content in `display_config` using safe fields:
  - `heading`
  - `body`
  - `content_style`
  - `button_label`
  - `button_route_key`
  - `button_form_key`
  - `button_display_mode`
- Render body Markdown through the existing safe Markdown renderer.
- Resolve route buttons through the same public route registry planned for the header.
- Resolve form buttons through Step 6 enabled form definitions and `open-public-form`.
- Do not create page/CMS models or generic public page routing.

## Public menu/header config and rendering plan

Current `menu_config` is a placeholder with `enabled` and `items`. It lacks item types, ordering, display modes, and theme-selector config.

Plan:

- Extend `menu_config` safely:
  - `enabled`
  - `items`
  - `theme_selector`
- Add support classes under `App\Support\PublicFront\Menu`:
  - item type registry/enum;
  - route registry;
  - menu config reader/renderer;
  - HTTPS URL sanitizer where needed.
- Add a public `PublicHeader` Livewire component and Blade view.
- Render the header through `PublicPanelProvider` using a Filament panel render hook while preserving `->navigation(false)`.
- Use `public/images/podtext-logo.jpg` for the public header logo baseline.
- Default items:
  - Home
  - Podcasts
  - About
  - Request transcription form
  - Register/volunteer transcriber form
  - theme selector
- Skip invalid/missing route and disabled/missing form items server-side.
- Mount one hidden `PublicFormModal` per enabled form key used by the header.
- Header buttons dispatch `open-public-form` with the configured form key.
- Implement a small localStorage-backed light/dark/system selector in Alpine; no persistent user settings.

## Exact files to change

- `app/Enums/PublicMenuItemType.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `app/Filament/Public/Pages/BrowseContentGroups.php`
- `app/Livewire/Public/ContributorDirectory.php`
- `app/Livewire/Public/PublicHeader.php`
- `app/Providers/Filament/PublicPanelProvider.php`
- `app/Settings/PublicContentSettings.php`
- `app/Support/PublicFront/About/PublicAboutPageRegistry.php`
- `app/Support/PublicFront/About/PublicAboutPageRenderer.php`
- `app/Support/PublicFront/Menu/*`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicFront/Sections/*`
- `database/settings/*_normalize_public_menu_header_and_about_cards.php`
- `lang/en/admin.php`
- `lang/en/public.php`
- `lang/he/admin.php`
- `lang/he/public.php`
- `resources/views/components/public/about/profile-card.blade.php`
- `resources/views/components/public/about/team-section.blade.php`
- `resources/views/components/public/contributor-card.blade.php`
- `resources/views/components/public/markdown-content.blade.php`
- `resources/views/filament/public/pages/browse-content-items.blade.php`
- `resources/views/filament/public/pages/empty-page-header.blade.php`
- `resources/views/livewire/public/content-item-search.blade.php`
- `resources/views/livewire/public/contributor-directory.blade.php`
- `resources/views/livewire/public/public-header.blade.php`
- `tests/Feature/PublicMenuHeaderUxFixesTest.php`
- Existing public regression tests where needed.
- `docs/phase-02/public-front-v2-step9-public-menu-header-ux-fixes-handoff.md`
- `docs/phase-02/current-project-state.md`

## Settings/config keys to add or extend

- Extend `menu_config.items.*`:
  - `key`
  - `type`
  - `label`
  - `route_key`
  - `external_url`
  - `form_key`
  - `display_mode`
  - `visible`
  - `sort`
  - `open_in_new_tab`
- Add `menu_config.theme_selector`:
  - `enabled`
  - `mode`
- Extend `about_page.settings.team_card`:
  - `show_image`
  - `image_size`
  - `layout`
  - `density`
  - `show_title`
  - `show_description`
  - `description_lines`
- Add `content_block` section support using `source_config.source_type = content_block` plus safe `display_config` fields listed above.

## Tests to add/update

- Add `tests/Feature/PublicMenuHeaderUxFixesTest.php`.
- Cover settings tabs and collapsible full-width sections through Filament schema component keys.
- Cover About team image/card settings and H1-H6 class markers.
- Cover contributor compact card semantics, no compact actions, preview link, preview search, page sizes, and sort toggles.
- Cover homepage chrome suppression and section header controls.
- Cover default public header/menu rendering, form action mounting/dispatch attributes, disabled form skipping, non-HTTPS external URL rejection, theme selector rendering, and no menu models.
- Run all required existing public regression filters and the full quality gate.

## Out of scope

- Step 10 full contributors/top-transcribers redesign.
- Step 11 seeders/demo data/assets/cleanup.
- Prompt 13 dashboard metrics.
- Prompt 14/15.
- Step 2 transcription publication policy.
- Generic CMS/page management.
- `PublicMenu` / `PublicMenuItem` models or tables.
- `Podcast` / `Episode` models.
- Public Filament Tables.
- Route path changes or `/groups` redirects.
