# Public Front v2 Step 10R Livewire/Blade/Support Audit

## Executive Summary

This audit inspected the actual post-Step-10 Livewire components, Blade views, support classes, models, migrations, tests, settings configuration, and public routes. It did not implement app-code fixes.

The public front is functional, but its rendering layer is not yet stable enough for Step 9F / 10F. Settings are read through several independent paths, card templates resolve but are only partly visual, transcription attribution is correct in contributor discovery and transcript tabs but not in generic item cards, and card layout decisions are split between Blade and two overlapping support classes.

Recommended next work:

1. Step 10R-A: add a request-scoped `PublicFrontRenderContext` / settings snapshot and explicit settings-save invalidation hook.
2. Step 10R-B: convert the card-template foundation into a controlled part renderer for content item, content group, and contributor card families.
3. Step 10R-C: correct transcriber attribution on public cards and centralize card layout presentation.
4. Step 9F / 10F: footer plus rich section builder, after the above renderer/context work.
5. Step 11: seeders/demo data/assets/cleanup.
6. Prompt 13: dashboard metrics readiness.

## Current State Verification

Preflight state:

- `git status --short --branch`: clean working tree, branch `main...origin/main [ahead 1]`.
- `git log --oneline --decorate -30`: Step 10 commit `37ce738 feat: refine contributors and top transcribers ux` is present. Post-Step-10 label/header polish commits are present locally, including `e8077ea`, `20970a3`, `802cf4a`, `2b1c6b3`, `cea4f60`, and `1d92bb6` on `origin/main`.
- `php artisan migrate:status`: public front migrations through `2026_07_06_000006_add_public_contributors_page_settings` have run. No Step 9F footer/rich-section, Step 11 cleanup, or Prompt 13 dashboard-metric migrations are present.
- `php artisan route:list --path=podcasts`: public podcast index and detail routes exist.
- `php artisan route:list --path=contributors`: public contributor index and detail routes exist.
- `php artisan route:list --path=search`: public search route exists.
- `rg` is not installed in this shell; equivalent `grep -RInE` searches were run.

Status confirmations from code:

- Step 9F / 10F has not been implemented: no `footer_config` setting, no `rich_columns` section type, and no `App\Support\PublicFront\Footer` namespace.
- Step 11 has not started as a dedicated final cleanup pass. A demo seeder exists, but no Step 11-specific cleanup/assets implementation is present.
- Prompt 13 has not started: `AdminPanelProvider` still registers stock Filament widgets, and no dashboard metric widgets or dashboard metric models/migrations were found.
- No public `Podcast`, `Episode`, `ContributorProfile`, `VolunteerProfile`, `PublicFooter`, `FooterSection`, `PublicMenu`, or `PublicMenuItem` models exist.
- Public Filament Tables were not reintroduced in the inspected public front paths.

## Livewire Component Map

`app/Livewire/Public/ContentItemSearch.php`

- Owns homepage/search/category/tag discovery state with many URL-backed filters using Livewire `#[Url]`.
- Reads `PublicContentSettings` directly through `settings()` and `PublicContentCardOptions::fromSettings()`.
- Resolves content item templates in `render()` and resolves homepage section templates through `PublicDisplaySectionResolver`.
- Uses `authorOptions()` from all `Author` records for filtering by item-level `authors`, not transcription authors.
- Risk: multiple settings reads per render through `settings()`, `cardOptions()`, homepage sections, template resolver, and support resolvers.

`app/Livewire/Public/ContentItemBrowser.php`

- Owns podcast/detail item grid controls and URL-backed search/sort/category/per-page state.
- Calls `PublicFrontConfigReader::read()->group('podcasts_page')` through `pageConfig()` and then calls `groupPageConfig()` repeatedly.
- Resolves `podcasts_page.item_template_key` for content item cards.
- Builds `PublicContentCardOptions` from global settings, then overrides group-page item display options.
- Still treats `show_episode_authors` as item-level authors. The label says episode authors, not transcribers, but public perception can still be wrong if authors are expected to mean transcribers.

`app/Livewire/Public/ContentGroupBrowser.php`

- Owns podcast index filters and pagination.
- Calls `PublicFrontConfigReader::read()->group('podcasts_page')` through `pageConfig()`.
- Resolves `podcasts_page.template_key` for content group cards.
- Public card output receives the resolved template, but the card component only exposes compatibility attributes and uses fixed markup.

`app/Livewire/Public/ContributorDirectory.php`

- Owns contributor index, selected preview contributor, preview search, sort, and pagination.
- Uses `PublicContributorDiscovery`, which correctly discovers contributors through `transcriptions.author_id`.
- Reads `PublicContentSettings` and `PublicFrontConfigReader` directly.
- Resolves contributor and content item templates without using contributor-page-specific template keys because none exist yet.
- Preview item cards show contributor-specific transcription titles below the item card, but the item card itself still uses item-level authors.

`app/Livewire/Public/ContributorContentItems.php`

- Owns full contributor item list.
- Uses `PublicContributorDiscovery::contentItemsForContributor()`, which filters by published transcriptions for the contributor and eager-loads the matching transcriptions.
- Resolves only the default content item card template; there is no contributor page item template setting.
- The nested `contributor-transcription-list` displays the contributor-specific transcription titles, but the main card author chip still comes from `ContentItem::authors`.

`app/Livewire/Public/TopTranscribersSection.php`

- Owns top-transcriber selector and preview pagination.
- Uses `PublicContributorDiscovery::topContributors()`, which counts published transcriptions.
- Reads `contributors_page` and global card settings directly.
- Resolves contributor and content item card templates without top-transcriber-specific template keys.
- The preview grid uses contributor-specific transcriptions under the card, but item-card attribution remains item-author based.

`app/Livewire/Public/PublicHeader.php`

- Delegates to `PublicMenuRenderer`, which delegates to `PublicMenuConfigReader`.
- `PublicMenuConfigReader` calls `PublicFrontConfigReader::read()` and reads `menu_config`, `route_labels`, and `public_forms`.
- Better than inline Blade logic, but still outside a shared request context.

`app/Livewire/Public/PublicFormModal.php`

- Calls `PublicFrontConfigReader::read()->group('public_forms')` in `definition()`.
- `definition()` is used in `mount()`, `submit()`, and `render()`, so the same settings group can be read multiple times for one interaction.
- This should use a form-definition resolver from the render context or a request-scoped form registry.

`app/Livewire/Public/ContentItemTranscriptViewer.php`

- Does not read public settings.
- Correctly loads published transcriptions with `author` and displays `$activeTranscription->author` as the transcriber.
- This is the clearest current public implementation of transcription-author attribution.

## Blade Component Map

`resources/views/components/public/content-item-card.blade.php`

- Calls `PublicFrontCardTemplateRenderer` from Blade.
- Computes item URLs, effective transcription/date, categories, tags, duration, image fallback, image source, and title text in Blade.
- Uses `contentItemPresentation()` for article/image/body/title/description classes.
- Displays `$item->authors` as `data-test="item-author"` when `$options->showAuthors` is true.
- Does not render configured template parts. It uses the template only for layout/density/image/title clamp plus compatibility metadata.

`resources/views/components/public/content-group-card.blade.php`

- Calls `PublicFrontCardTemplateRenderer` from Blade.
- Resolves cover URL, excerpt, categories, counts, initials, image fit/radius in Blade.
- Exposes `data-card-template-*`, but does not part-render the template.
- Uses fixed article/title/description/count layout.

`resources/views/components/public/contributor-card.blade.php`

- Calls `PublicFrontCardTemplateRenderer` from Blade.
- Exposes `data-card-template-*`, but does not part-render the template.
- Uses fixed compact/full layouts and fixed count/bio/link regions.

`resources/views/components/public/content-item-grid.blade.php` and `contributor-item-grid.blade.php`

- Contain semantic-to-class maps for columns/gaps/layout.
- Duplicate each other almost exactly.
- Should move to a shared card-grid presentation helper so future rich sections and footer sections reuse the same semantic grid behavior.

`resources/views/components/public/contributor-transcription-list.blade.php`

- Correctly uses loaded `transcriptions` to show contributor-specific transcription titles.
- It does not display author names, but it is scoped to a selected contributor and is currently the right supporting metadata for contributor contexts.

`resources/views/components/public/content-group-badge.blade.php`

- Contains group-cover fallback and duplicate-thumbnail suppression logic.
- The duplicate-thumbnail policy is useful and should remain, but should be prepared by a presentation object rather than repeated in cards.

`resources/views/livewire/public/content-item-search.blade.php`

- Contains significant homepage rendering logic.
- It resolves content-block button URLs from `PublicRouteRegistry` inside Blade.
- It handles content block style class maps, form modal mounting, latest-section pagination controls, contributor grids, content-group grids, category cards, and item grids.
- Step 9F should not add more rich rendering into this view until section renderers return prepared view data.

`resources/views/livewire/public/content-item-browser.blade.php`

- Contains control visibility booleans and category-chip active class logic in Blade.
- Acceptable for small UI state, but the config-derived booleans should come from the component or render context.

`resources/views/livewire/public/contributor-directory.blade.php`, `contributor-content-items.blade.php`, and `top-transcribers-section.blade.php`

- Render cards and controls from component-provided data.
- Still have fixed grid/card class maps and no contributor-specific template options.

`resources/views/filament/public/pages/browse-contributors.blade.php` and `show-contributor.blade.php`

- Read `PublicFrontConfigReader` directly in Blade.
- Should receive page config from page classes or a render context instead.

`resources/views/filament/public/pages/show-content-item.blade.php`

- Displays item-level `$contentItem->authors` in the header as authors.
- The transcript viewer below correctly displays the active transcription author as transcriber.
- This page should decide whether header credits are item participants, and if so label them distinctly from transcribers; otherwise it should show effective transcription author(s).

## Support-Class Map

`PublicFrontConfigReader`

- Reads raw settings with `$settings->getRepository()->getPropertiesInGroup(PublicContentSettings::group())`.
- Validates on every `read()` call.
- Has no request-scoped memoization and no persistent app cache.

`PublicFrontConfigValidator` and `PublicFrontConfigRegistry`

- Normalize the JSON groups `card_templates`, `menu_config`, `about_page`, `public_forms`, `route_labels`, `display_defaults`, `podcasts_page`, and `contributors_page`.
- No `footer_config` group exists.
- The validator is the right boundary for finite settings tokens, but the normalized output is not currently collected into a final request context.

`PublicFrontCardTemplateResolver`

- Merges built-in default templates with configured `card_templates`.
- `all($family)` returns defaults plus configured templates, so podcast setting selects are not default-only in the current code.
- Resolves `template_key` and falls back safely when missing.

`PublicFrontCardTemplateRenderer`

- Has `compatibilityAttributes()` for all families.
- Has `contentItemPresentation()` for content item layout/density/image/title/description clamps.
- Does not render parts into view models.
- Has no content group or contributor presentation equivalent.

`PublicContentCardOptions`

- Reads global public-content settings directly.
- Converts semantic settings into classes and flags.
- Overlaps with `PublicFrontCardTemplateRenderer` on image size, density, title size, description clamps, and radius.

`PublicContentItemQueries`

- Centralizes public item visibility and eager-loads `authors`, categories, group categories, enabled tags, featured transcription, and latest published transcription.
- Does not eager-load `featuredTranscription.author` or `latestPublishedTranscription.author`.
- Because cards need transcription authors, this base query should include the effective transcription author relationships or a presentation service should load them safely.

`PublicContributorDiscovery`

- Correctly treats contributors/top transcribers as `Author` records attached to `Transcription` records through `transcriptions.author_id`.
- Counts public transcriptions and distinct content items from `Transcription`.
- Contributor item queries eager-load contributor-specific `transcriptions`, but not `transcriptions.author`.

`PublicDisplaySectionResolver` and `PublicDisplaySectionQueryResolver`

- Resolve homepage section data and templates.
- Section templates are resolved, but rendering is still mostly handled by Blade components.
- `content_block` is a minimal safe Markdown/action section, not a rich section builder.

`PublicMenuConfigReader` and `PublicMenuRenderer`

- Prepare menu items, form mounts, logo, search, and theme-selector data.
- They read settings independently from other page components.

`PublicAboutPageRenderer`

- Centralizes Markdown/RichEditor rendering and image URL safety.
- Still reads enabled public forms directly through `PublicFrontConfigReader`.
- This is a good model for Step 9F renderers, but it should accept context instead of reaching into settings.

`SafeMarkdownRenderer`

- Sanitizes Markdown and provides fixed public content classes.
- This should remain the Markdown boundary for rich columns/footer.

## Settings Read/Write Map

Writes:

- `app/Settings/PublicContentSettings.php` defines typed scalar settings plus JSON array groups.
- `app/Filament/Pages/PublicContentSettings.php` extends Filament's Spatie `SettingsPage`.
- `mutateFormDataBeforeFill()` normalizes the settings data for the builder UI.
- `mutateFormDataBeforeSave()` normalizes uploads and validates the final config before save.
- The installed SettingsPage calls `beforeSave`, saves the settings object, then calls `afterSave`.

Reads:

- Direct `PublicContentSettings` reads: `ContentItemSearch`, `ContributorDirectory`, `ContributorContentItems`, `TopTranscribersSection`, and `PublicContentCardOptions`.
- Direct `PublicFrontConfigReader` reads: `ContentItemBrowser`, `ContentGroupBrowser`, `ContributorDirectory`, `ContributorContentItems`, `TopTranscribersSection`, `PublicFormModal`, public contributor Blade pages, public page classes, `PublicMenuConfigReader`, `PublicAboutPageRenderer`, `PublicFrontCardTemplateResolver`, and homepage-section admin option helpers.
- Blade-level reads: `content-item-card`, `content-group-card`, `contributor-card`, contributor public page views, and homepage content-block route resolving.

Caching reality:

- `config/settings.php` has settings cache disabled by default: `SETTINGS_CACHE_ENABLED=false`; memoization is also disabled by default.
- Boost docs confirmed Laravel 13 has `once()` and `Cache::memo()` for request/job-scoped memoization, and normal Laravel cache for persistent values.
- Because settings are small and frequently needed by several Livewire islands on the same page, request-scoped caching is the necessary first step.
- Persistent app cache may be useful later, but it should not be the first architectural dependency because the current design needs one normalized render context even when DB reads are cheap.
- Cache tags should be avoided unless the production cache driver supports them; the project currently uses SQLite/database locally.

Recommended settings object:

- Name: `PublicFrontRenderContext`.
- Factory/service: `PublicFrontRenderContextFactory`.
- Request-scoped binding or memoized method should collect:
  - full normalized config;
  - `cardTemplatesByFamily`;
  - display defaults;
  - menu data;
  - public forms;
  - route labels;
  - podcast page config;
  - contributor page config;
  - card layout defaults;
  - future footer config.
- Renderers/components should receive the context or context-derived DTOs instead of calling `PublicFrontConfigReader` from Blade or repeatedly from Livewire helpers.

Invalidation point:

- Add settings invalidation after `PublicContentSettings` save by overriding/using the Filament SettingsPage `afterSave` hook.
- If only request-scoped cache is used, `afterSave` should at least forget the bound settings/render context and Spatie settings instance for the current request.
- If persistent cache is added, `afterSave` must clear the public-front settings cache key and any derived template/menu/form cache keys.

Tests needed:

- Save `PublicContentSettings` through the Filament page, then assert a new request renders updated menu labels/card template/layout without stale values.
- In a single request/view render, assert multiple context consumers use one normalized settings snapshot.
- Assert custom card template changes are visible after save without manually calling helper cache-reset functions.
- Assert invalid config still falls back to defaults and records invalid paths.

## Card-Template Resolution And Rendering Map

Storage:

- `PublicContentSettings::$card_templates` stores configured templates.
- `PublicFrontConfigValidator::normalizeCardTemplates()` normalizes family/key/layout/density/image/title/parts.
- `PublicFrontCardTemplateRegistry` defines families, part types, sources, attributes, layouts, icons, and built-in default templates.

Admin select options:

- Podcast index template: `PublicContentSettings` field `podcasts_page.template_key` calls `$this->cardTemplateOptions('content_group')`.
- Podcast detail item template: `podcasts_page.item_template_key` calls `$this->cardTemplateOptions('content_item')`.
- Homepage section template: `HomepageSectionForm` field `display_config.template_key` calls `cardTemplateOptions($get)`.
- Both settings-page and homepage-section helpers use `PublicFrontCardTemplateResolver::all($family)`, so default and custom templates are included in options after the current settings data is saved/readable.
- Contributor pages do not currently have contributor-card or contributor-item template key settings.

Public render paths:

- Homepage latest/manual/category/tag/group-item sections: `ContentItemSearch` -> `content-item-grid` -> `content-item-card`.
- Search/category/tag result pages: `ContentItemSearch` -> `content-item-grid` -> `content-item-card`.
- Podcast index: `ContentGroupBrowser` -> `content-group-card`.
- Podcast detail items: `ContentItemBrowser` -> `content-item-grid` -> `content-item-card`.
- Contributor directory: `ContributorDirectory` -> `contributor-card` and preview `contributor-item-grid`.
- Contributor detail: `ContributorContentItems` -> `contributor-item-grid`.
- Top transcribers: `TopTranscribersSection` -> `contributor-card` and preview `contributor-item-grid`.

Where templates visibly affect output today:

- Content item templates can influence card/row layout, density, image size, title size, title line clamp, and description line clamp.
- `data-card-template-*` attributes show which template resolved.

Where templates stop at compatibility metadata:

- Content group cards expose template attributes only.
- Contributor cards expose template attributes only.
- Template part `source`, `attribute`, `label`, `layout`, `icon`, `custom_text`, and most part visibility/order are not rendered as actual card regions.

Why users may report templates are "not reflected":

- Custom templates can be resolved, but visible changes are limited.
- Tests mostly assert `data-card-template-key` and safe normalization, not actual part output.
- Group and contributor families have no family-specific presentation renderer.
- Contributor surfaces have no admin template-key settings.
- Existing cards still render fixed hard-coded title/description/count/author/taxonomy regions.

Admin preview:

- Do not make admin preview the next first task.
- First implement real public part rendering and tests.
- After Step 10R-B, add a small admin preview if it reuses the same renderer. Avoid preview-only rendering paths that drift from public output.

Tests needed:

- Homepage latest cards render a custom content item template part order/visibility.
- Podcast detail item cards render `podcasts_page.item_template_key` visibly.
- Podcast/group index cards render a custom content group template visibly, not only data attributes.
- Contributor cards render custom contributor template parts.
- Contributor item cards and top-transcriber preview cards use contributor-scoped transcription title/author data.
- Tests assert hidden parts are absent, custom text is sanitized/escaped, and raw Blade/CSS/classes are rejected.

## Transcriber Attribution Map

Schema/model reality:

- `Transcription` is single-author today: `transcriptions.author_id` nullable FK and `Transcription::author()` belongs to `Author`.
- `Author::transcriptions()` is a has-many.
- `ContentItem::authors()` is a many-to-many via `author_content_item`.
- No `author_transcription` pivot exists.

Current public attribution:

- Contributor discovery and counts use `transcriptions.author_id`; this is correct for top transcribers and contributor pages.
- Transcript viewer displays `$activeTranscription->author` as `public.labels.transcriber`; this is correct.
- Content item cards display `$item->authors` as `item-author`.
- Content item row wrapper displays `$item->authors`.
- Item detail page header displays `$contentItem->authors` with `public.labels.authors`.
- Contributor item grids display contributor-specific transcription titles below the item card, but the card itself still displays item authors if enabled.

Meaning of `ContentItem::authors`:

- Current admin UI labels it `authors`.
- Imports/export and demo seeders use it as item-level credits/participants.
- Backfill migration used the first item author as an initial transcription author for legacy data, but the relationships are separate after the transcription model was introduced.
- Therefore `ContentItem::authors` should not be treated as transcribers unless the product explicitly decides it is a legacy alias.

Immediate correction without schema changes:

- Use the effective/main published transcription author for generic item cards.
- In contributor contexts, prefer the loaded contributor-specific published transcriptions for the selected contributor; show their titles and, if needed, the known contributor name rather than item-level authors.
- Relabel item-level authors as item participants/credits if they remain visible.
- Do not call item-level authors transcribers.
- Add eager loading for `featuredTranscription.author`, `latestPublishedTranscription.author`, and contributor-specific `transcriptions.author` where cards need author names.

Future multi-transcriber schema decision:

- If one transcription can have several transcribers, introduce `author_transcription`.
- Keep `transcriptions.author_id` temporarily for backfill and migration compatibility, or migrate it to a primary/legacy author flag.
- Backfill: insert `(transcription_id, author_id)` for every non-null `transcriptions.author_id`; enforce unique pivot pairs.
- Admin: change transcription form author select to multi-select/relation manager; update content-item transcription relation manager.
- Import/export: add multi-author reference-key columns or a delimited transcription author field; preserve formula-injection protection.
- Public queries: change contributor discovery/counts from `transcriptions.author_id` to the pivot and update distinct item counts.
- Tests: cover backfill, admin save, import/export, contributor counts, item card attribution, and transcript viewer attribution.
- Risks: duplicate counts, compatibility with existing `author_id`, ambiguous "main transcriber" display, and larger N+1 risk if pivot authors are not eager-loaded.

## Card Layout Consistency Audit

Current issues:

- Content item cards use `flex h-full` but sections inside the card do not reserve consistent title/description/metadata heights.
- Content group and contributor cards use fixed but separate layouts and do not share card presentation logic.
- `content-item-grid` and `contributor-item-grid` duplicate semantic grid class maps.
- Image aspect ratio is usually square, but image wrapper and fallback handling differ across item/group/badge/detail contexts.
- Title clamps exist for content item cards, but content group/contributor title behavior is not controlled by the same renderer.
- Description line clamps and line heights are inconsistent across item/group/category/contributor cards.
- Group thumbnail duplication is handled in `content-group-badge`, but the main card still prepares this in Blade.

Semantic JSON-safe recommendations:

- `height_policy`: `content`, `balanced`, `equal_row`.
- `image_ratio`: `square`, `wide`, `portrait`, `none`.
- `image_fit`: existing `cover`, `contain`.
- `image_radius`: existing radius tokens.
- `title_lines`: integer token constrained to `1` through `3`.
- `description_lines`: integer token constrained to `0` through `5`.
- `metadata_policy`: `hide_empty`, `reserve_one_line`, `reserve_two_lines`.
- `group_badge_mode`: existing `name_only`, `thumbnail_name`, `combined_title`.
- `thumbnail_policy`: `prefer_item`, `fallback_to_group`, `hide_when_duplicate`.
- `grid_columns`: existing constrained integer.
- `grid_gap`: existing `compact`, `comfortable`, `spacious`.

Defaults that should be hard-coded for consistency:

- Cards in the same grid should use equal outer height by default.
- Image wrappers should use a stable aspect ratio per card family.
- Titles should clamp to two lines by default.
- Descriptions should clamp to three lines by default and reserve space only when `height_policy` requires it.
- Metadata chips should not cause row-height jumps in dense grids; use `metadata_policy`.
- If the group cover is the main image fallback, the group badge should not duplicate the same thumbnail unless explicitly enabled.

## Performance And Settings Caching Risks

- Multiple Livewire components can render on the homepage and each call settings/config readers independently.
- `PublicFrontConfigReader::read()` revalidates the full public-front config for every call.
- `PublicContentCardOptions::fromSettings()` reads the settings repository independently from `PublicFrontConfigReader`.
- `PublicFrontCardTemplateResolver::resolve()` calls the config reader each time it resolves a template.
- Blade components call app services directly, which makes request-level caching harder to reason about.
- Contributor previews and cards need additional eager loading for transcription authors; otherwise fixing attribution may create N+1 queries.

Recommended performance boundary:

- Request-scoped context first.
- Persistent cache only if needed after measuring or if Spatie settings cache is enabled for production.
- Do not introduce cache tags as a dependency until the production cache driver is confirmed.
- Keep raw settings small and normalized; cache arrays, not rendered HTML.

## Mismatches With Current State And Handoffs

- Step 3 card templates were a foundation and compatibility contract, not a full visual template engine. The code confirms this.
- Step 5 practical card rendering applies mainly to content item cards; group and contributor cards remain compatibility-only.
- Step 10 contributor counts/grouping are transcription-based and correct, but generic item card attribution remains item-author based.
- The current state docs suggesting Step 9F / 10F next are now outdated unless Step 10R-A/B/C runs first.
- No current implementation supports footer/rich columns; the Step 9F plan remains future work.

## Exact Fix Sequence

Step 10R-A: Public front settings snapshot/render context.

- Add `PublicFrontRenderContext` and `PublicFrontRenderContextFactory`.
- Memoize normalized public config per request.
- Expose typed/group accessors for menu, forms, route labels, display defaults, podcast page, contributor page, and card templates.
- Route Livewire components, page classes, and renderers through the context.
- Add `afterSave` invalidation on `PublicContentSettings`.
- Tests: no stale settings after save; one context snapshot per request; invalid config fallback.

Step 10R-B: Card-template rendering.

- Add a controlled card presentation/part renderer for content item, content group, and contributor families.
- Move card URL/image/metadata/taxonomy/transcription-author preparation out of Blade.
- Keep finite class maps; do not allow raw Tailwind, Blade paths, PHP classes, or HTML in template JSON.
- Add contributor-page/top-transcriber template key settings only if Yoni wants separate contributor controls.
- Tests: custom template output visible on homepage, podcast detail, podcast index, contributor cards, contributor item cards, and top-transcriber previews.

Step 10R-C: Transcriber attribution and layout consistency.

- Add effective transcription-author presentation for public item cards.
- Use contributor-specific transcription context where loaded.
- Relabel or hide item-level authors depending on product decision.
- Centralize equal-row/image/title/description/metadata layout policies.
- Tests: item card shows transcription author instead of item author where appropriate; contributor cards show contributor-specific transcription titles and no wrong item-level transcriber labels; cards stay stable with missing images/descriptions/authors.

Then run Step 9F / 10F.

- Add footer/rich section foundation only after context and card renderer are stable.
- Reuse context, card presentation, form CTA resolver, safe Markdown/RichEditor rendering, and semantic layout tokens.

Then Step 11 and Prompt 13.

## Open Questions For Yoni

- Should `ContentItem::authors` remain visible publicly as episode participants/credits, or should cards show only transcription author(s)?
- Should the public label be "transcriber" on generic item cards, or only on transcript/detail surfaces?
- Is one transcriber per transcription enough for the near-term product, or should a future `author_transcription` pivot be scheduled?
- Do contributor directory/top-transcriber cards need separate template key settings, or should they use global contributor defaults?
- Should admin card-template preview ship immediately after public part rendering, or wait until Step 9F shares the renderer?
- Should production enable Spatie settings persistent cache after request-scoped context lands?
- Can Step 9F wait until Step 10R-A/B/C are implemented, not merely planned? This audit recommends yes.

## Do Not Implement Yet

This audit intentionally does not implement app-code fixes. Do not run Step 9F / 10F, Step 11, Prompt 13, Prompt 14, Prompt 15, Step 2 transcription publication policy, schema changes, or generic CMS work until the Step 10R fix prompts are approved.
