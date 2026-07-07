# Step 10R-B2 Content Item Card Part Renderer MCP Research

## Mini-Step

Step 10R-B2 - Real content-item card part renderer.

## Access Level

- Laravel Boost: available.
- FilamentExamples MCP: `search_examples` only; snippet/search access. No separate source/read/detail tool was exposed.

## Laravel Boost Research

### `application_info`

Boost reported:

- PHP 8.4
- Laravel 13.18.0
- Filament 5.6.7
- Livewire 4.3.3
- Pest 4.7.4
- Tailwind CSS 4.3.2
- SQLite database

### `database_schema`

Schema inspection confirmed that Step 10R-B2 needs no migration:

- `content_items` already has title, slug, description, duration, thumbnail/media metadata, publication fields, group relation, and featured transcription relation.
- `transcriptions` already has author relation fields for later Step 10R-C1 attribution work.
- `homepage_sections` already stores JSON source/selection/display/pagination config.
- `settings` already stores Spatie Settings payloads.

### `search_docs`

Boost docs were queried for:

- Laravel Blade escaping, attributes, and `@class`.
- Laravel Eloquent eager loading and `loadMissing`.
- Livewire rendered-output testing and URL/query-state test helpers.
- Pest string expectations and sequence/order assertions.
- Tailwind CSS line clamp and aspect-ratio utilities.
- Filament settings/form option context.

Relevant guidance applied:

- Keep user-entered card text rendered through escaped Blade output.
- Use static Blade components/partials and finite PHP maps rather than user-provided view paths or HTML.
- Use eager-loaded relations already provided by public item queries to avoid N+1 behavior.
- Use Livewire/Pest rendered-output assertions, including order assertions, for card part behavior.
- Keep Tailwind classes app-owned and finite.

One broad Laravel docs query returned an invalid request, then smaller targeted Laravel docs queries succeeded.

## FilamentExamples Research

### First Pass Query Batch

Queries:

- `custom card renderer`
- `Blade card component view model`
- `Livewire public card grid`
- `card template builder`

Returned patterns:

- `Table Rendered as a Card Grid`
  - Source path: `v4/tables/table-as-grid-with-cards/app/Filament/Resources/Users/UserResource.php`
  - Pattern: compose cards from static columns/layouts with finite configuration.
  - PodText adaptation: keep PodText public cards custom Blade/Livewire, but drive the card from prepared PHP data rather than dynamic JSON paths.
- `GitHub-Style Profile View Page with Heatmap`
  - Source path: `v4/full-projects/github-style-user-profile-with-activity-heatmap/app/Filament/Resources/Users/Pages/ViewUser.php`
  - Pattern: collect view data in PHP methods and render simple Blade.
  - PodText adaptation: add a presenter that prepares content item card data before Blade renders.
- `Live Content Preview For Editors`
  - Source path: `v4/forms/markdown-and-rich-editor-preview-forms/app/Filament/Resources/Posts/Schemas/PostForm.php`
  - Pattern to avoid for public cards: raw HTML preview is appropriate only after controlled conversion/sanitization, not from card template JSON.

### Second Pass Query Batch

Queries:

- `getViewData custom page cards`
- `custom Blade view component Filament`
- `contentGrid cards Table`
- `ViewEntry blade partial`

Returned patterns:

- `Custom-Designed Table with ViewColumn Cells`
  - Source path: `v4/tables/table-customized-design-viewcolumn/app/Filament/Resources/Accounts/Tables/AccountsTable.php`
  - Pattern: static view names and explicit eager loading for relationship-backed card-like cells.
  - PodText adaptation: use static `content-item-card` rendering only; do not store or resolve Blade view names from JSON.
- `Form and Table on One Custom Page`
  - Source path: `v4/full-projects/create-form-and-table-on-the-same-page/app/Filament/Pages/Category.php`
  - Pattern: keep page state explicit and view rendering stable.
  - PodText adaptation: preserve existing Livewire URL-backed state; B2 only changes rendered card internals.

### Refined Query Batch

Queries:

- `custom infolist component protected view`
- `view model data blade card`
- `custom component make app static view`
- `eager load card grid relationships`

Returned patterns:

- Reconfirmed `getViewData`/static view component patterns.
- Reconfirmed `ViewColumn` with static Blade partials and eager-loaded relations.
- Reconfirmed content-grid card examples with finite layouts.

## Local Research Summary

Relevant files inspected:

- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRegistry.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateResolver.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRenderer.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplate.php`
- `app/Support/PublicFront/Cards/PublicFrontCardPart.php`
- `app/Support/PublicContent/PublicContentCardOptions.php`
- `app/Support/PublicContent/PublicContentItemQueries.php`
- `app/Models/ContentItem.php`
- `resources/views/components/public/content-item-card.blade.php`
- `resources/views/components/public/content-item-grid.blade.php`
- `resources/views/livewire/public/content-item-search.blade.php`
- `resources/views/livewire/public/content-item-browser.blade.php`
- focused public card/search/podcast tests.

Findings:

- `PublicFrontCardTemplateRenderer` currently exposes compatibility attributes and presentation classes, but Blade still hard-codes the actual content item parts.
- Content item templates are already resolved and passed through homepage/search/category/tag and podcast detail item grids.
- `PublicContentItemQueries::base()` eager-loads the relations needed by the current public card surface: authors, direct/group categories, content group, enabled tags, featured transcription, latest published transcription.
- `content-item-row.blade.php` is not currently referenced by the public card paths.
- Current card author badges still use `ContentItem::authors`; Step 10R-C1 owns correcting transcriber attribution to transcription authors.

## Implementation Notes For B2

- Add a dedicated content-item card presenter that prepares URLs, labels, image state, metadata, taxonomy, and ordered template parts.
- Keep part rendering constrained to supported content-item parts:
  - `image`
  - `title`
  - `description`
  - `group_identity`
  - `transcriber_line`
  - `date_read_time`
  - `metadata_row`
  - `taxonomy`
  - `action_link`
  - `custom_text`
  - `divider`
  - `spacer`
- Render only static app-owned Blade branches for known part types.
- Escape custom text and labels; do not render raw HTML from JSON.
- Preserve current output defaults where possible.
- Defer content group/contributor part rendering to Step 10R-B3.
- Defer transcriber source correction to Step 10R-C1.
- Defer legacy scalar card option convergence to Step 10R-B4.
