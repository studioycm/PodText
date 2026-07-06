# Public Front v2 Step 10 Contributors and Top Transcribers UX Handoff

## Purpose

Step 10 completes the contributor/transcriber public UX that Step 9R intentionally left for a dedicated prompt. It keeps `Author` as the public contributor model, keeps public item cards as `ContentItem` cards, and adds settings-driven contributor directory, contributor detail, and top-transcriber homepage behavior.

## What was implemented

- Added JSON-first `public_content.contributors_page` settings and a settings migration.
- Added a Contributors tab to `App\Filament\Pages\PublicContentSettings`.
- Added settings-driven contributor directory defaults, sort options, page-size options, preview search, preview counts/bio, and preview grid columns.
- Added URL-backed full contributor item search, sort, and page-size controls.
- Added `App\Livewire\Public\TopTranscribersSection` for horizontal top-transcriber selectors with a selected preview under the selector.
- Added grouped contributor transcription title display under related public `ContentItem` cards.
- Added disabled-state guards for `/contributors` and `/contributors/{authorSlug}`.
- Updated tests for Step 10 behavior and the existing contributor discovery regression.

## Final contributor settings schema

Settings group: `public_content.contributors_page`.

```json
{
  "enabled": true,
  "title": "Contributors",
  "description": "Browse public transcribers",
  "label_singular": "Contributor",
  "label_plural": "Contributors",
  "item_label_singular": "item",
  "item_label_plural": "items",
  "directory": {
    "per_page_options": [10, 15, 20],
    "default_per_page": 10,
    "default_sort": "count_desc",
    "sort_options": ["name_asc", "name_desc", "count_desc", "count_asc"],
    "preview_items_per_page": 6,
    "preview_grid_columns": 3,
    "preview_search_enabled": true
  },
  "top_transcribers": {
    "enabled": true,
    "limit": 8,
    "layout": "horizontal",
    "preview_default_page_size": 5,
    "preview_page_size_options": [5, 10, 15],
    "preview_grid_columns": 3,
    "show_full_page_link": true,
    "show_count_badge": true
  },
  "cards": {
    "compact_show_count": true,
    "compact_count_icon": "document-text",
    "preview_show_bio": true,
    "preview_show_counts": true
  },
  "page": {
    "items_per_page": 12,
    "page_size_options": [6, 12, 24],
    "default_sort": "latest_transcription",
    "sort_options": ["latest_transcription", "oldest_transcription", "title_asc", "title_desc"],
    "search_enabled": true,
    "grid_columns": 3,
    "grid_gap": "comfortable"
  }
}
```

## Contributor directory behavior

- `/contributors` uses `ContributorDirectory`.
- Directory search, selected contributor, preview search, sort, and page size remain Livewire-owned; important state remains URL-backed.
- Compact cards show only contributor name and public transcription count badge.
- Compact cards do not render direct page actions.
- Selecting a card opens the separate preview row below the list.
- Preview contains the full contributor page link, optional counts, optional bio excerpt, preview search, and related item grid.
- Directory page sizes are constrained to 10, 15, and 20.
- Directory sort options are A-Z, Z-A, count down, and count up.

## Top transcribers homepage section behavior

- Homepage `top_transcribers` sections now mount `App\Livewire\Public\TopTranscribersSection`.
- The section renders a horizontal compact contributor selector.
- The selected contributor preview renders under the selector.
- Preview supports 5, 10, and 15 page-size options.
- Preview shows count badges and full contributor page link when enabled.
- Disabled top-transcriber settings render the normal empty state.
- The generic `contributors` section marker is now `data-test="contributors-grid"` to avoid mixing it with top-transcribers behavior.

## Contributor page behavior

- `/contributors/{authorSlug}` uses `ShowContributor` and `ContributorContentItems`.
- Page kicker uses `contributors_page.label_singular`.
- Related public items support URL-backed search, sort, and page size.
- Related cards use the existing public `ContentItem` card renderer plus a contributor-specific transcription title list.
- Full contributor pages return 404 when `contributors_page.enabled` is false.

## Counting and grouping rules

- Contributors are `Author` records.
- Public counts include published `Transcription` records only when the parent `ContentItem` is public.
- Public parent item visibility still requires a published group, published item, and published/effective transcription availability under current rules.
- Two published transcriptions by the same author on one item count as two public transcriptions.
- The preview and contributor detail list render one item card per item and list the relevant transcription titles under that card.

## Query/visibility rules

- `PublicContributorDiscovery` remains the aggregate/query boundary.
- `ContentItem` public cards come from `PublicContentItemQueries::base()`.
- Draft items, draft transcriptions, unpublished groups, and contributors with no public transcriptions are excluded.
- Searches use public-safe item, group, and contributor-transcription title fields.

## Final namespaces/classes changed

- `App\Livewire\Public\ContributorDirectory`
- `App\Livewire\Public\ContributorContentItems`
- `App\Livewire\Public\TopTranscribersSection`
- `App\Support\PublicContent\PublicContributorDiscovery`
- `App\Support\PublicFront\PublicFrontConfigRegistry`
- `App\Support\PublicFront\PublicFrontConfigValidator`
- `App\Settings\PublicContentSettings`
- `App\Filament\Pages\PublicContentSettings`
- `App\Filament\Public\Pages\BrowseContributors`
- `App\Filament\Public\Pages\ShowContributor`

## Final public API for future prompts

Runtime config:

```php
$contributorsConfig = app(PublicFrontConfigReader::class)
    ->read()
    ->group('contributors_page');
```

Top contributors:

```php
$contributors = PublicContributorDiscovery::topContributors($limit, $optionalAuthorIds);
```

Contributor public items:

```php
$items = PublicContributorDiscovery::contentItemsForContributor(
    author: $author,
    search: $search,
    sort: $sort,
);
```

Homepage section:

```blade
<livewire:public.top-transcribers-section
    :section-key="$section->key"
    :contributor-ids="$section->contributors->pluck('id')->all()"
/>
```

## Card/template/grid integration

- Contributor selector cards reuse `x-public.contributor-card` in compact/selectable mode.
- Related items reuse the existing `x-public.content-item-card` renderer through `x-public.contributor-item-grid`.
- `x-public.contributor-transcription-list` adds grouped same-author transcription names under each item card.
- Grid columns and gaps are semantic tokens mapped in Blade, not raw JSON classes.

## Admin settings UI behavior

- Public settings now includes a Contributors tab.
- Main contributor settings section is full-width and collapsible.
- Fieldsets group identity labels, directory controls, top-transcriber controls, card flags, and full contributor-page item controls.
- English and Hebrew labels/helper text were added.

## Fallback and invalid config behavior

- Invalid labels fall back to defaults.
- Invalid sort/layout/icon/gap tokens fall back to allowed defaults.
- Directory page sizes are constrained to 10, 15, and 20.
- Top-transcriber preview page sizes are constrained to 5, 10, and 15.
- Full contributor item page-size options are integer lists and automatically include the default item page size.
- Missing/disabled contributor discovery returns 404 for contributor routes and skips top-transcriber rendering.

## Security rules

- No `User` records are exposed publicly.
- No contributor/profile model was added.
- No public Filament Tables were introduced.
- Public queries use public item visibility constraints.
- Bio Markdown continues through the app-owned safe Markdown renderer.
- JSON settings store semantic values only.

## Sample JSON payloads

Directory tuned for smaller pages:

```json
{
  "directory": {
    "default_per_page": 15,
    "per_page_options": [10, 15, 20],
    "default_sort": "name_asc",
    "preview_items_per_page": 4,
    "preview_grid_columns": 2
  }
}
```

Top-transcriber preview tuned for compact homepage sections:

```json
{
  "top_transcribers": {
    "enabled": true,
    "limit": 6,
    "layout": "horizontal",
    "preview_default_page_size": 5,
    "preview_page_size_options": [5, 10, 15],
    "preview_grid_columns": 3
  }
}
```

## Sample PHP usage

```php
$result = app(PublicFrontConfigReader::class)->read();
$contributors = $result->group('contributors_page');

if ($contributors['top_transcribers']['enabled'] ?? true) {
    $authors = PublicContributorDiscovery::topContributors(
        (int) ($contributors['top_transcribers']['limit'] ?? 8),
    );
}
```

## Blueprint deviations

- No full footer/rich section builder was implemented. That remains Step 9F/10F.
- Homepage top-transcriber preview state is not URL-backed because it is section-local and not a canonical browse page.
- No new public contributor profile table was added; `Author` remains the public contributor entity.

## Impact on Step 9F / Footer + Rich Section Builder

- Step 10 confirms future rich sections need reusable semantic grid controls for selector and preview regions.
- Future builder work should support "selector plus preview" layouts as a semantic pattern, but should not make top-transcribers generic before there is a concrete second use case.
- The footer/rich section builder should still run after Step 10 and before Step 11 if approved.

## Impact on Step 11 Seeders/Demo Data/Assets/Cleanup

- Step 11 can seed `contributors_page` settings once this schema is stable.
- Demo content should include at least one author with multiple public transcriptions on one item to demonstrate grouped transcription names.
- Demo top-transcriber homepage sections should use the existing `HomepageSection` record type.

## Open issues / follow-up decisions

- Step 9F/10F should decide whether selector/preview layouts become a shared section-builder pattern.
- Step 11 should decide final demo copy and seeded contributor counts.
- Prompt 13 dashboard metrics has not started.
- Step 2 transcription publication policy remains deferred/reserved.

## Tests and quality gate summary

- Added `tests/Feature/PublicContributorsTopTranscribersUxTest.php`.
- Updated `tests/Feature/PublicContributorDiscoveryTest.php` for the new top-transcriber section marker.
- Updated `tests/Feature/PublicDisplaySectionsLoopersTest.php` for the renamed generic contributors grid marker and new top-transcriber section marker.
- Updated `tests/Feature/TaxonomyTagsPinningSettingsTest.php` so public settings defaults include `contributors_page`.
- Focused and regression tests passed:
  - `PublicContributorsTopTranscribersUxTest`
  - `PublicStep9RMenuHeaderUxFixesTest`
  - `PublicMenuHeaderUxFixesTest`
  - `PublicPodcastsGroupsUxTest`
  - `PublicAboutPageContentTeamTest`
  - `PublicFormsSubmissionsTest`
  - `PublicLatestSearchUxTest`
  - `PublicHomepageSearchTest`
  - `PublicDisplaySectionsLoopersTest`
  - `PublicFrontCardTemplateBuilderTest`
  - `PublicContributorDiscoveryTest`
  - `PublicItemPageMediaParserTest`
- Full quality gate passed:
  - `vendor/bin/pint --dirty --format agent`
  - `php artisan test`
  - `vendor/bin/pint --test`
  - `vendor/bin/filacheck`
  - `npm run build`
  - `git diff --check`
