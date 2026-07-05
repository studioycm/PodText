# Public Front v2 Step 8 Podcasts and Groups UX Implementation Plan

## Current Route/Page Inventory

- `/` is currently `App\Filament\Public\Pages\BrowseContentGroups`, despite the class name. It renders `resources/views/filament/public/pages/browse-content-items.blade.php` and the custom `App\Livewire\Public\ContentItemSearch` homepage/search UI.
- `/search` is `App\Filament\Public\Pages\SearchContentItems`.
- `/groups/{contentGroupSlug}` is currently `App\Filament\Public\Pages\ShowContentGroup` with route name `filament.public.pages.groups.show`.
- `/podcasts` has no current route.
- `/groups` has no public browse/index route. `php artisan route:list --path=groups` also lists admin `admin/content-groups` routes because of the substring match.
- Existing public group card links and section view-more route-key mapping currently point to the root browse page for `podcasts` through `BrowseContentGroups::getUrl(panel: 'public')`, which is now incorrect for Step 8.

## Current Public Group/Podcast Behavior

- `App\Livewire\Public\ContentGroupBrowser` exists but is not currently mounted by a dedicated public page.
- `ContentGroupBrowser` lists published `ContentGroup` records, searches only group titles, sorts by newest/title, and counts `contentItems()->published()` as `published_content_items_count`.
- `ContentItem::published()` currently enforces published item, published parent group, and at least one published transcription.
- The current group detail page resolves only published groups but does not require the group to have at least one public item before resolving.
- The current group item browser lists `contentItems()->published()` with no pagination and no search.
- Existing group cards expose Step 3 template compatibility attributes but do not yet use Step 8 config or public episode-count labeling.

## Conflicts Or Route Risks

- No `/podcasts` route conflict exists.
- The older `/groups/{contentGroupSlug}` public detail route exists. The active Step 8 prompt explicitly makes `/podcasts` canonical and does not approve `/groups` backward compatibility. The implementation will therefore change `ShowContentGroup` to `/podcasts/{contentGroupSlug}` instead of adding a redirect.
- Existing tests and links that refer to `/groups/{slug}` must move to `/podcasts/{slug}`.
- The homepage root must remain unchanged; `BrowseContentGroups` is currently the homepage shell and should not be repurposed as the podcasts index.

## Exact Files To Change

- `app/Providers/Filament/PublicPanelProvider.php`
- `app/Filament/Public/Pages/BrowsePublicContentGroups.php` or equivalent new public page class
- `app/Filament/Public/Pages/ShowContentGroup.php`
- `app/Livewire/Public/ContentGroupBrowser.php`
- `app/Livewire/Public/ContentItemBrowser.php`
- `app/Support/PublicFront/Groups/PublicContentGroupQueries.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Settings/PublicContentSettings.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `app/Support/PublicFront/Sections/PublicDisplaySectionQueryResolver.php`
- `app/Support/PublicFront/Sections/PublicDisplaySectionResolver.php`
- `resources/views/filament/public/pages/browse-content-groups.blade.php`
- `resources/views/filament/public/pages/show-content-group.blade.php`
- `resources/views/livewire/public/content-group-browser.blade.php`
- `resources/views/livewire/public/content-item-browser.blade.php`
- `resources/views/components/public/content-group-card.blade.php`
- `lang/en/public.php`, `lang/he/public.php`
- `lang/en/admin.php`, `lang/he/admin.php`
- `database/settings/2026_07_05_000003_add_public_podcasts_page_setting.php`
- `tests/Feature/PublicPodcastsGroupsUxTest.php`
- Existing public route tests that assert `/groups/{slug}`.
- `docs/phase-02/public-front-v2-step8-podcasts-groups-ux-handoff.md`
- `docs/phase-02/current-project-state.md`

## `/groups` Decision

- `/groups` currently does not exist as a public browse path.
- `/groups/{contentGroupSlug}` currently exists as a public detail path.
- Final Step 8 behavior: no public `/groups` route and no compatibility redirect. The old detail route is removed by changing `ShowContentGroup` to `/podcasts/{contentGroupSlug}`.
- Admin `admin/content-groups` routes remain unchanged.

## Final `/podcasts` Route Plan

- Add a new public Filament Page class for the canonical index at `/podcasts`.
- Register it in the public panel.
- Keep the root homepage on `BrowseContentGroups`.
- Change `ShowContentGroup` to `/podcasts/{contentGroupSlug}` with relative route name `podcasts.show`.
- Update all public group links and `route_key = podcasts` mapping to point to `/podcasts`, not `/`.

## Settings/Config Keys

Add `public_content.podcasts_page` as a JSON-first settings group:

```json
{
  "enabled": true,
  "title": "Podcasts",
  "description": "Browse podcasts with published episodes.",
  "group_label_singular": "Podcast",
  "group_label_plural": "Podcasts",
  "cards_per_page": 12,
  "category_filter_enabled": true,
  "search_enabled": true,
  "template_key": null,
  "item_template_key": null,
  "show_description": true,
  "show_categories": true,
  "show_episode_count": true,
  "group_page": {
    "show_description": true,
    "show_categories": true,
    "show_episode_descriptions": true,
    "items_per_page": 12
  }
}
```

Validation will be added to `PublicFrontConfigValidator`. Admin controls will be added to the existing `PublicContentSettings` page. No settings-only model will be created.

## Query/Visibility Strategy

- Centralize group visibility in `App\Support\PublicFront\Groups\PublicContentGroupQueries`.
- A public group query requires:
  - `ContentGroup::published()`;
  - at least one `ContentItem::published()` child;
  - count alias `public_content_items_count` with the same public item constraints.
- Group index search matches:
  - group title;
  - group Markdown description as plain database text;
  - visible group category names;
  - public item titles.
- Category filters use visible categories only, include descendants, and match either direct group categories or direct public item categories inside the group.
- Group detail uses the same public group query by slug, so a published group with no public items returns 404.
- Group detail item list uses `PublicContentItemQueries::base()` constrained to the selected group.
- The Step 4 `content_groups` section source will be moved to the same public group query so homepage group cards do not count or expose groups without public items.

## Card/Template Strategy

- Resolve group cards with `PublicFrontCardTemplateResolver::resolve(family: 'content_group', key: podcasts_page.template_key)`.
- Resolve group-page item cards with `PublicFrontCardTemplateResolver::resolve(family: 'content_item', key: podcasts_page.item_template_key)`.
- Continue rendering through existing Blade card components and Step 5 controlled content-item presentation metadata.
- Extend group card Blade props to consume normalized config flags for description, categories, and episode count.
- Keep deterministic layout conventions: `min-w-0`, safe image wrappers, square fallbacks, no raw JSON classes, and semantic line clamps.

## Tests To Add/Update

- Add `tests/Feature/PublicPodcastsGroupsUxTest.php`.
- Update existing group route assertions from `/groups/{slug}` to `/podcasts/{slug}`.
- Cover guest access, public group visibility, public item count, search, category toggles, descendant filtering, configured labels, card fallback/cover, template metadata, detail page visibility, item descriptions, and old `/groups/{slug}` absence.
- Preserve no-`Podcast`/`Episode` model assertions.
- Preserve no public Filament Table markup assertions.

## Out Of Scope

- Public menu/header implementation.
- Contributors/top-transcribers UX refinements.
- Seeders/demo assets/cleanup.
- Step 2 transcription publication policy.
- Prompt 13 dashboard metrics.
- About schema changes.
- Generic CMS pages.
- `Podcast` or `Episode` models, tables, Resources, or namespaces.
- Public Filament Tables.
- `/groups` backward compatibility redirects.
