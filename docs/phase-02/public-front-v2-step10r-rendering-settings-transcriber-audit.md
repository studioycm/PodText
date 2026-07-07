# Public Front v2 Step 10R Rendering, Settings, Card Template, and Transcriber Audit

## Executive Summary

This is a planning and audit document only. No app-code implementation is included here.

Recommendation: run a Step 10R implementation pass before Step 9F / 10F and before Step 11. Step 9F / 10F depends on the same settings and renderer architecture that is currently inconsistent, so adding footer and rich-section rendering first would multiply the same issues.

Recommended next sequence:

1. Step 10R-A: create a request-scoped public-front settings snapshot / render context.
2. Step 10R-B: make card templates drive real card parts and custom template selection.
3. Step 10R-C: correct public transcriber attribution and normalize card layout behavior.
4. Step 9F / 10F: footer plus constrained rich section builder.
5. Step 11: seeders, demo data, assets, cleanup.
6. Prompt 13: dashboard metrics readiness.

The current public-front implementation is functional, but settings are consumed through several direct paths: Livewire components, Filament public pages, Blade views, menu renderers, section resolvers, and `PublicContentCardOptions`. Card templates exist and resolve, but only content item cards apply a partial practical renderer; group and contributor cards mostly expose compatibility metadata. Content item cards still hard-code most rendered sections and show `ContentItem::authors` where the domain now requires transcription author attribution.

## Current State Verification

Preflight found a clean working tree before this docs-only task:

- `git status --short --branch`: `## main...origin/main`
- Step 10 commit present: `37ce738 feat: refine contributors and top transcribers ux`
- Post-Step-10 public label/header polish commits present locally, with current HEAD at docs-only commit `1d92bb6 fix: refine theme selector and search UX in public header`
- `php artisan migrate:status`: successful status inspection only; no migrations were run
- Public routes exist for:
  - `/podcasts`
  - `/podcasts/{contentGroupSlug}`
  - `/contributors`
  - `/contributors/{authorSlug}`
  - `/search`
- Prompt 13 has not started.
- Step 9F / 10F footer and rich section builder has not been implemented.
- Step 11 seeders/demo data/assets/cleanup has not started.
- No forbidden `Podcast`, `Episode`, `ContributorProfile`, `VolunteerProfile`, `PublicFooter`, `FooterSection`, `PublicMenu`, or `PublicMenuItem` model implementation was found. There is an enum named `PublicMenuItemType`, which is an existing Step 9 value object, not a settings-only model.

## Settings Read/Write Map

### Storage

`App\Settings\PublicContentSettings` stores the scalar legacy public settings and JSON-first groups:

- `card_templates`
- `menu_config`
- `about_page`
- `public_forms`
- `route_labels`
- `display_defaults`
- `podcasts_page`
- `contributors_page`

`config/settings.php` uses the database settings repository. Spatie Settings persistent cache is disabled by default:

- `SETTINGS_CACHE_ENABLED=false` by default
- `SETTINGS_CACHE_MEMO=false` by default

Local vendor inspection showed Spatie settings classes are container-scoped and lazily loaded. If Spatie cache is enabled, `SettingsSaved` updates/clears the settings cache internally. With the current default config, there is no persistent settings cache and no request-level memoization inside `PublicFrontConfigReader`.

### Main Reader

`App\Support\PublicFront\PublicFrontConfigReader`:

- calls `$settings->getRepository()->getPropertiesInGroup(PublicContentSettings::group())`;
- fills missing keys from `PublicFrontConfigRegistry::defaults()`;
- validates every call through `PublicFrontConfigValidator`;
- has no internal memoized `PublicFrontConfigResult`.

Because `read()` validates every call, repeated calls in one request repeat normalization and invalid config collection even if Spatie returns the same scoped settings instance.

### Direct Public Reads

Direct public settings reads currently appear in:

- `ContentItemSearch`: app settings for default sort/result layout and `PublicContentCardOptions`.
- `ContentItemBrowser`: `podcasts_page` and `PublicContentCardOptions`.
- `ContentGroupBrowser`: `podcasts_page`.
- `ContributorDirectory`: `contributors_page` and `PublicContentCardOptions`.
- `ContributorContentItems`: `contributors_page` and `PublicContentCardOptions`.
- `TopTranscribersSection`: `contributors_page` and `PublicContentCardOptions`.
- `PublicFormModal`: `public_forms`.
- `BrowsePublicContentGroups`, `ShowContentGroup`, `BrowseContributors`, `ShowContributor`: page enablement/title config.
- `PublicMenuConfigReader`: reads `menu_config`, `route_labels`, and `public_forms`.
- `PublicAboutPageRenderer`: reads public form definitions for CTA resolution.
- `PublicFrontCardTemplateResolver`: reads `card_templates` for `resolve()` and `all()`.
- `HomepageSectionForm` and `PublicContentSettings` admin forms: read public forms and card templates for select options.
- Blade views:
  - `resources/views/components/public/content-item-card.blade.php`
  - `resources/views/filament/public/pages/browse-contributors.blade.php`
  - `resources/views/filament/public/pages/show-contributor.blade.php`
  - `resources/views/filament/tables/columns/public-content-item-card.blade.php`

### `PublicContentCardOptions` Bypass

`App\Support\PublicContent\PublicContentCardOptions::fromSettings()` reads directly from the Spatie settings repository. It maps legacy homepage scalar fields such as:

- `homepage_card_image_size`
- `homepage_card_density`
- `homepage_card_title_size`
- `homepage_show_authors`
- `homepage_show_categories`
- `homepage_show_tags`
- `homepage_show_duration`
- `homepage_show_effective_date`
- `homepage_show_description`
- `homepage_group_badge_mode`

This bypasses `PublicFrontConfigReader` and overlaps with the template renderer. The result is two sources of truth:

- templates decide layout/density/image/title/clamps;
- `PublicContentCardOptions` still decides most displayed sections.

### Blade Logic To Move Out

The Blade components currently prepare non-trivial render data:

- `content-item-card.blade.php` resolves URLs, effective transcription, categories, tags, duration, image source, group cover fallback, image classes, and title composition.
- `content-group-card.blade.php` resolves group URL, cover URL, categories, count labels, initials, image fit/radius.
- `contributor-card.blade.php` resolves counts, bio excerpt, selected classes, and compact/full branching.
- public page Blade files read settings directly for contributor labels and page copy.

This is workable but makes settings behavior hard to reason about and hard to test. The next pass should move card data preparation into PHP render presenters or view models.

## Settings Render Context Design

Add a request-scoped final object named `PublicFrontRenderContext`.

Recommended classes:

- `App\Support\PublicFront\PublicFrontRenderContext`
- `App\Support\PublicFront\PublicFrontRenderContextFactory`
- optional immutable grouped DTOs later if useful, but do not overbuild them in Step 10R-A.

`PublicFrontRenderContext` should expose:

- `config`: the single `PublicFrontConfigResult`.
- `settings`: normalized full config array.
- `displayDefaults()`.
- `cardTemplates()`.
- `menu()`.
- `aboutPage()`.
- `publicForms()`.
- `routeLabels()`.
- `podcastsPage()`.
- `contributorsPage()`.
- `cardTemplateResolver()` or template map methods that resolve from the already-loaded template list.
- future `footer()` group once Step 9F / 10F adds `footer_config`.

Binding:

- bind `PublicFrontRenderContext` as scoped in a service provider;
- factory reads `PublicContentSettings` once and validates once;
- all public renderers and Livewire components depend on the context instead of calling `PublicFrontConfigReader` repeatedly.

Caching:

- start with request-scoped caching only. This is the safest first pass because it removes repeated normalization without stale persistent data risk.
- do not use cache tags because Laravel docs note not every driver supports them, and the project currently uses SQLite/database-oriented local defaults.
- leave Spatie Settings persistent cache to Spatie if `SETTINGS_CACHE_ENABLED` is enabled.
- add derived app cache only if profiling later proves it is needed. If added, use one versioned cache key such as `public_front.render_context.v1`, not tags.

Invalidation:

- Step 10R-A should add an explicit invalidation point for any future derived cache.
- Best places:
  - an event listener for Spatie `SettingsSaved` when the settings class is `PublicContentSettings`;
  - or `afterSave`/save hook on the Filament settings page if the event hook is not reliable in the installed version.
- `PublicContentSettings` save tests should assert no stale context after saving settings and resolving a new scoped context.

Tests for Step 10R-A:

- resolving `PublicFrontRenderContext` twice in one request returns the same normalized config instance or same values without revalidating.
- changing `podcasts_page.title` through settings save is reflected on the next request/context.
- changing `card_templates` is reflected in template select options and public rendering on the next request/context.
- if derived cache is introduced, saving `PublicContentSettings` invalidates it.
- Livewire components still preserve URL-backed state after switching to the context.

## Card Template Storage/Resolution/Rendering Map

### Storage and Normalization

`card_templates` are stored in `PublicContentSettings::card_templates` as an array. `PublicFrontConfigValidator::normalizeCardTemplates()` accepts:

- key/slug;
- family: `content_item`, `content_group`, `contributor`;
- label;
- layout;
- density;
- image size;
- title size;
- parts.

The validator unwraps Filament Builder part payloads from `{type, data}` into flat part arrays before saving. Unknown fields, invalid part types, invalid sources, invalid attributes, and unsafe values are dropped/reported.

### Default Templates

`PublicFrontCardTemplateRegistry` provides one default per family:

- `default_content_item`
- `default_content_group`
- `default_contributor`

The default content item template includes parts such as `image`, `group_identity`, `title`, `description`, `transcriber_line`, `date_read_time`, `metadata_row`, and `taxonomy`.

### Resolver

`PublicFrontCardTemplateResolver` merges default templates with configured templates by family/key. Custom templates replace any template with the same family/key and are returned by `all($family)`.

This means custom templates should appear in select options after they are saved and the settings are re-read. The likely reason a custom template does not appear immediately in podcast settings during the same edit session is that `PublicContentSettings::cardTemplateOptions()` reads persisted settings through the resolver, not the current unsaved `data.card_templates` form state. It also will not show templates that failed validation or are saved under the wrong family.

### Select Options

Current select option sources:

- `PublicContentSettings`:
  - `podcasts_page.template_key` uses `cardTemplateOptions('content_group')`.
  - `podcasts_page.item_template_key` uses `cardTemplateOptions('content_item')`.
  - `cardTemplateOptions()` calls `PublicFrontCardTemplateResolver::all($family)`.
- `HomepageSectionForm`:
  - `display_config.template_family` uses `PublicFrontConfigRegistry::cardFamilyOptions()`.
  - `display_config.template_key` uses resolver `all($family)` based on selected/default family.

Required Step 10R-B behavior:

- custom templates appear after save/reload;
- newly added templates also appear in dependent selects in the same settings form session if technically feasible with Filament state callbacks;
- selects clearly filter by family;
- tests cover custom content group, content item, and contributor template options.

### Public Render Paths

Content item paths:

- homepage latest/default sections through `ContentItemSearch`;
- homepage configured content item sections through `PublicDisplaySectionResolver`;
- podcast detail item grid through `ContentItemBrowser`;
- contributor preview and contributor page item grids through `ContributorDirectory`, `TopTranscribersSection`, and `ContributorContentItems`.

Content group paths:

- `/podcasts` group browser;
- homepage content group sections.

Contributor paths:

- `/contributors` directory;
- homepage contributors section;
- top transcriber selector cards.

### Real Rendering vs Compatibility Metadata

Current rendering state:

- `content-item-card.blade.php` calls `PublicFrontCardTemplateRenderer::contentItemPresentation()`.
- That method maps template layout/density/image size/title size and line clamps into class/presentation arrays.
- It computes `controlled_parts`, but the Blade view does not render an ordered loop of template parts.
- The view still hard-codes image, group badge, title, description, item authors, effective date, duration, categories, and tags.
- `content-group-card.blade.php` and `contributor-card.blade.php` only expose template compatibility attributes and keep hard-coded markup.

This explains the user-visible report: a custom template can appear as `data-card-template-key`, and may affect limited layout/presentation details, but it does not reliably make custom template parts visibly render across podcast, episode, item, contributor, and group cards.

### Renderer Conflict

`PublicContentCardOptions` and `PublicFrontCardTemplateRenderer` overlap:

- `PublicContentCardOptions` controls most public item-card sections and class tokens from legacy scalar settings.
- `PublicFrontCardTemplateRenderer` controls some presentation details from templates.

Step 10R-B should converge these into one card rendering pipeline:

- `PublicFrontCardTemplateRenderer` resolves template parts and returns a safe `CardPresentation`.
- `PublicContentCardOptions` either becomes a legacy adapter that feeds template overrides/defaults or is replaced by family-specific presentation options from `PublicFrontRenderContext`.
- Blade should render a list of prepared part view models, not re-run source/attribute logic directly.

### Safe Part Rendering Design

Keep the renderer safe by:

- only allowing registered part types;
- only allowing registered sources and attributes;
- resolving each part through PHP maps;
- returning escaped strings, known URLs, known route URLs, known icons, and fixed semantic layout tokens;
- using fixed class maps in PHP/Blade;
- never storing raw Blade, raw CSS classes, raw Tailwind, arbitrary PHP class names, raw HTML, SQL, or script fragments in JSON.

Part renderer responsibilities:

- content item: title, description, image, group identity, transcription author line, effective date/read time, duration, taxonomy, action link, custom text, divider, spacer.
- content group: image, type label, title, description, category chips, item count, action link.
- contributor: name, public transcription count, public content item count, bio, action/select link, compact selector layout.

Admin preview:

- not required before Step 9F.
- recommended after Step 10R-B only if templates remain hard to understand.
- if added, preview should reuse the same safe renderer with fixture records, not generate arbitrary preview HTML.

Tests for Step 10R-B:

- custom template output on homepage latest cards;
- custom template output on podcast detail item cards;
- custom template output on podcast/group index cards;
- custom template output on contributor item cards;
- custom template output on top transcriber selector/preview cards;
- hidden template parts actually disappear from HTML;
- part order changes actual HTML order;
- invalid parts are rejected and do not render;
- raw classes/HTML/scripts are rejected.

## Card Component Rendering Map

Current components:

- `x-public.content-item-grid`: resolves layout/columns/gap and loops `x-public.content-item-card`.
- `x-public.content-item-card`: content item card, partial template presentation, most hard-coded body.
- `x-public.content-item-row`: legacy row-style item component that still shows item authors.
- `x-public.content-group-card`: group card with metadata-only template attributes.
- `x-public.contributor-card`: contributor card with metadata-only template attributes.
- `x-public.contributor-item-grid`: wraps `content-item-card` and appends `contributor-transcription-list`.
- `x-public.contributor-transcription-list`: shows the contributor-specific transcription titles loaded by `PublicContributorDiscovery`.
- `x-public.content-group-badge`: suppresses duplicate thumbnail when main image source is already group cover, unless explicitly allowed.

Immediate component goals:

- move card data preparation into PHP presenters;
- make all public card families use one controlled renderer interface;
- preserve existing Blade components as view shells if useful;
- keep contributor-specific transcription title list from Step 10;
- make item cards use transcription author display instead of item author display where the label is transcriber.

## Livewire Data-Preparation Map

Current Livewire data prep:

- `ContentItemSearch` prepares homepage/search card options, content item template, sections, filters, and paginated results.
- `ContentItemBrowser` prepares podcast detail item settings and item card options.
- `ContentGroupBrowser` prepares podcast index page config and group template.
- `ContributorDirectory` prepares contributor selector cards and selected contributor preview items.
- `ContributorContentItems` prepares full contributor item list.
- `TopTranscribersSection` prepares top contributor selector and paginated selected contributor preview items.

Gaps:

- several components call `contributorsConfig()` or `groupPageConfig()` multiple times in one render;
- repeated config methods call `PublicFrontConfigReader::read()` each time;
- card options are regenerated from settings separately from template resolution;
- contributor item cards receive context-specific transcriptions, but the card itself does not receive an explicit attribution context.

Step 10R-A/C should introduce explicit view data:

- `PublicFrontRenderContext $context`
- `CardRenderContext $cardContext`
- optional `AttributionContext`, with values like `default`, `contributor_preview`, `contributor_page`, `top_transcriber_preview`.

## Transcriber/Transcription/Item Relationship Map

### Current Schema Reality

`ContentItem`:

- `authors()` belongs to many `Author` through `author_content_item`;
- `transcriptions()` has many `Transcription`;
- `featuredTranscription()` belongs to `Transcription`;
- `latestPublishedTranscription()` has one published `Transcription`;
- `effectiveTranscription()` chooses the published featured transcription when valid, otherwise latest published transcription.

`Transcription`:

- has nullable `author_id`;
- `author()` belongs to `Author`;
- current schema supports one author/transcriber per transcription.

`Author`:

- `contentItems()` belongs to many `ContentItem`;
- `transcriptions()` has many `Transcription`.

`author_content_item`:

- item-level many-to-many attribution.
- legacy backfill used the first item author to seed `transcriptions.author_id` for old transcript rows.

Conclusion: today, public transcriber attribution should come from `Transcription::author`, not `ContentItem::authors`.

### Current Public Attribution Issues

Current public cards:

- `content-item-card.blade.php` shows `$item->authors` when `showAuthors` is enabled.
- podcast detail settings call this `show_episode_authors`, which is ambiguous and can display item-level credits as if they were transcribers.
- contributor item cards reuse `content-item-card`, so the card can still show item authors above the contributor-specific transcription titles.
- `content-item-row.blade.php` shows `$item->authors`.
- `show-content-item.blade.php` header links `$contentItem->authors`.

Current correct path:

- `content-item-transcript-viewer.blade.php` shows `$activeTranscription->author` as `public.labels.transcriber`.
- `PublicContributorDiscovery` counts and finds contributors through `transcriptions.author_id`.
- contributor preview/full-page item queries eager load only the contributor's published transcriptions on each item, and the Step 10 transcription title list uses that context.

### Immediate Display Correction Without Schema Changes

Step 10R-C should:

- rename display concepts in code/options from author to transcription author/transcriber where public labels mean transcriber;
- keep `ContentItem::authors` available for future item participant/guest/host display, but do not label or render it as transcriber by default;
- make public item cards render:
  - contributor-context transcription authors when the card is rendered in contributor contexts;
  - otherwise the effective/main transcription author;
  - no transcriber badge when no published/effective transcription author exists;
- keep contributor-specific transcription titles from Step 10 below contributor item cards;
- update item detail header to avoid displaying item authors as transcribers. Either remove that header credit for now or relabel it as item participants if the product wants item-level credits visible.

Query changes needed for immediate fix:

- update `PublicContentItemQueries::base()` to eager load `featuredTranscription.author` and `latestPublishedTranscription.author`;
- ensure contributor context item queries load `transcriptions.author`;
- expose a prepared `transcriberNames` collection in the card presenter instead of loading in Blade.

Tests for immediate fix:

- item card shows effective transcription author when item-level authors differ;
- item card does not show item-level author as transcriber;
- contributor preview item card shows the selected contributor's transcription title(s) and does not show unrelated item-level authors as transcribers;
- top transcriber preview behaves the same;
- item detail transcript viewer continues to show active transcription author;
- item detail header no longer mislabels item-level authors.

### Future Multi-Transcriber Schema Decision

If multi-transcriber-per-transcription is required, introduce an `author_transcription` pivot in a later implementation prompt, not Step 10R docs.

Proposed schema:

- `author_transcription`
  - `id`
  - `author_id` FK cascade
  - `transcription_id` FK cascade
  - optional `role` only if the product needs roles like transcriber/editor/reviewer
  - optional `sort`
  - timestamps only if auditing is useful
  - unique `author_id`, `transcription_id`

Migration/backfill:

- create pivot;
- backfill every non-null `transcriptions.author_id` into the pivot;
- keep `author_id` during a transition as the primary/legacy author field, or remove it in a later breaking prompt after import/export/admin are updated;
- add integrity tests that backfill is idempotent and preserves existing public counts.

Model changes:

- `Transcription::authors()` belongsToMany `Author`;
- `Author::transcriptions()` eventually becomes belongsToMany or a new `authoredTranscriptions()` relation;
- decide whether `Transcription::author()` remains as primary author compatibility.

Admin changes:

- change transcription forms from single `author_id` select to multi-select/relationship manager;
- update content item relation manager;
- update filters and tables to handle many authors;
- keep searchable/preloaded relationship selects.

Import/export changes:

- transcription import should accept multiple author reference keys, probably pipe-separated;
- existing `author_reference_key` can map to primary/backfill compatibility during transition;
- export should include multiple author reference keys and possibly display names;
- failed-row behavior must reject unresolved author references.

Public query/count changes:

- contributor discovery counts should count pivot rows or distinct transcriptions by author, based on product decision;
- public content item count should count distinct content items through pivoted published transcriptions;
- contributor item queries should filter through `whereHas('transcriptions.authors')`;
- avoid double-counting duplicate authors per transcription.

Risks:

- import/export compatibility;
- existing tests and factories assume single author;
- contributor count semantics may change;
- admin filters become more complex;
- transition period can produce dual-source attribution bugs if `author_id` and pivot diverge.

## Card Layout Consistency Audit

Current card layout strengths:

- content item grid uses semantic column and gap inputs;
- content item cards use `h-full` and square image wrappers;
- group badge already suppresses duplicate thumbnails when item card image source is group cover;
- cards without item image fall back to group cover.

Current consistency gaps:

- content item, content group, contributor, and contributor item wrapper cards use different internal spacing and section heights;
- content item title/description clamps exist, but no stable min-height reserves space across a row;
- metadata/taxonomy/footer/action regions can appear/disappear and shift neighboring card heights;
- group cards and contributor cards do not share card presentation maps with content item cards;
- image ratio is fixed square in most cards, with no semantic choice for compact/balanced/detail contexts;
- contributor item cards append transcription lists outside the card, so grid rows can become visually uneven;
- settings expose image fit/radius/size but not enough semantic layout policy for equal rows.

JSON-safe layout recommendations:

- add semantic card layout tokens, not raw classes:
  - `height_policy`: `content`, `balanced`, `equal_row`
  - `image_ratio`: `square`, `wide`, `portrait`, `group_cover`, `none`
  - `title_lines`: `1`, `2`, `3`
  - `description_lines`: `0`, `2`, `3`, `4`
  - `metadata_policy`: `hide_empty`, `reserve_one_line`, `reserve_two_lines`
  - `taxonomy_policy`: `hide`, `chips_one_line`, `chips_two_lines`
  - `footer_policy`: `none`, `reserve_action`, `action_when_available`
  - `image_source_policy`: `item_then_group`, `group_only`, `item_only`, `none`
  - `duplicate_group_thumbnail_policy`: `suppress_when_main_image_is_group`, `allow`
- keep class maps in renderer code.
- default without extra admin settings:
  - content item grids use square or configured semantic ratio consistently per grid;
  - title region reserves two lines;
  - description reserves the configured clamp only when descriptions are enabled;
  - metadata uses one reserved row when date/duration/transcriber are enabled;
  - cards without item image use group cover;
  - group badge thumbnail is suppressed when the main image is already the group cover.

Step 10R-C should implement only the semantic defaults and renderer class maps needed for consistency. It should not expose a large new CMS-like layout editor.

## Performance And Caching Risks

Current risks:

- repeated `PublicFrontConfigReader::read()` calls per request/render;
- repeated validation of the same settings payload;
- `PublicContentCardOptions::fromSettings()` independently reads the settings repository;
- templates are resolved through a reader call in multiple components;
- Blade components prepare model-derived data and can accidentally lazy-load if query eager loading changes;
- Livewire re-renders can multiply all of the above.

Mitigation:

- Step 10R-A request-scoped `PublicFrontRenderContext`;
- pass context or prepared arrays into renderers/components;
- eager load transcription authors where card attribution needs them;
- keep persistent cache optional until profiling indicates a need;
- if persistent derived cache is added later, use a single versioned key and explicit invalidation on `PublicContentSettings` save.

## Step 9F / 10F Dependency Decision

Step 9F / 10F should wait until Step 10R-A/B/C is implemented.

Reason:

- footer and rich-section rendering will need the same render context;
- rich sections will need safe renderer interfaces and fixed class maps;
- footer config will be another settings group and should not add more direct `PublicFrontConfigReader` calls;
- rich-column sections may reuse card templates or card layout tokens, so the template renderer should be coherent first.

Step 9F / 10F can remain JSON-first, constrained, and non-CMS after Step 10R:

- no `FooterSection` or `PublicFooter` model;
- no raw Tailwind or arbitrary Blade in JSON;
- `footer_config` lives in `PublicContentSettings`;
- `rich_columns` lives in `HomepageSection` JSON;
- renderers consume `PublicFrontRenderContext`;
- Markdown/RichEditor go through app-owned sanitizers.

## Implementation Sequence Recommendation

Implement these as separate prompts:

1. Step 10R-A: settings snapshot/render context.
2. Step 10R-B: real card-template part rendering and custom template selection fixes.
3. Step 10R-C: transcriber attribution and layout consistency.
4. Step 9F / 10F: footer and rich section builder foundation.
5. Step 11: seeders/demo data/assets/cleanup.
6. Prompt 13: dashboard metrics readiness.

Do not combine Step 10R-A/B/C into one giant implementation prompt. The risk areas are different enough that the tests and review surface should stay separated.

## Do Not Implement Yet Notes

Do not implement any fixes from this audit in this planning prompt.

Do not implement:

- app code changes;
- schema changes;
- Step 9F / 10F;
- Step 11;
- Prompt 13/14/15;
- Step 2 transcription publication policy;
- generic CMS functionality;
- new public/footer/menu/podcast/episode profile models.

## Open Questions For Yoni

1. Should item-level `ContentItem::authors` ever be visible publicly, and if yes, what label should distinguish them from transcribers?
2. Should an item card show only the effective/main transcription author, or all published transcription authors when not in contributor context?
3. In contributor context, should the card show the selected contributor only, all transcribers on the displayed transcription(s), or no transcriber badge because the surrounding page already provides context?
4. Is multi-transcriber-per-transcription required now, or can the current single `transcriptions.author_id` model carry the final public-front release?
5. Should custom templates appear in dependent selects before saving the settings page, or is save/reload acceptable?
6. Should card templates control group/contributor cards with the same part-builder vocabulary, or should those families have smaller fixed template schemas?
7. Which layout policy should be the public default: compact equal rows, content-driven cards, or balanced rows with reserved title/metadata space?
