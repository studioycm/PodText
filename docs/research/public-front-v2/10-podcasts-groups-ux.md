# Public Front v2 Research: Podcasts/Groups UX

## Purpose

Plan the public groups/podcasts listing and group page refinements without changing internal model names.

## Topic Scope

Route/default naming, labels, group cards, category toggles, search by name/topic, group page episode rows, and card-template reuse.

## Exact Search Terms Used

- Boost: "Filament custom page panel page route slug getUrl Page class public panel"
- Boost: "Livewire URL attribute query string search filters"
- FilamentExamples MCP: "Filament public cards group page content items"
- FilamentExamples MCP: "Filament categories toggle buttons search cards"
- FilamentExamples MCP: "Filament row card image description settings"

## Boost Docs Used

- Filament custom page route docs.
- Livewire URL state docs.
- Tailwind responsive grid/fixed image sizing docs.

## FilamentExamples MCP Examples Found

- `v4/tables/table-as-grid-with-cards/app/Filament/Resources/Users/UserResource.php`: image card grid and filters.
- `v4/full-projects/cms-blog-system-shield/app/Filament/Resources/Posts/Schemas/PostForm.php`: content/media form patterns.
- No dedicated podcast/group public page example found.

## Actual Files, Classes, and Snippets Observed

- Local: `app/Filament/Public/Pages/BrowseContentGroups.php` is root/home browse shell, not a separate full groups directory UX yet.
- Local: `app/Filament/Public/Pages/ShowContentGroup.php` uses `/groups/{contentGroupSlug}`.
- Local: `resources/views/filament/public/pages/show-content-group.blade.php` renders group header and `public.content-item-browser`.
- Local: `ContentGroup` remains the internal model; user-facing label can be podcast.

## GitHub/Source Files Inspected

- LaravelDaily menu demo route file showed public route naming by content type, but PodText should keep its own Filament public page routing.

## Pattern To Copy

- Use route labels and display labels as settings.
- Keep internal `ContentGroup` naming in code.
- Reuse card template families for group cards and group-page rows.

## Pattern To Avoid

- Do not create `Podcast` or `Episode` models.
- Do not keep old group/podcast routes for compatibility unless a redirect strategy is approved.
- Do not let group pages show draft/unpublished items.

## PodText Adaptation Notes

Default route path can remain `/groups`; admin-facing display labels can say podcasts. Any path change should be deliberate because it affects URLs and redirects.

## JSON-First Settings Recommendation

Store:

- `groups_page.route_label`
- `groups_page.public_label_singular/plural`
- `groups_page.search_placeholder`
- `groups_page.category_filter_mode`
- `groups_page.card_template`
- `group_page.item_row_template`
- `group_page.description_lines`
- `group_page.image_size`
- `group_page.image_position`

## Model/Table Considered

Rejected: no model required. Existing `ContentGroup` is the domain model; settings control display.

## Recommended Model/Schema Options

No schema. If route path becomes configurable, use settings and route registration carefully; do not store route definitions in a table.

## Recommended Filament Patterns

SettingsPage controls for labels, card template selection, and row/card display options. No Resource changes except possible helper text for group labels if requested.

## Public Livewire/Blade Implications

Groups page:

- category toggle buttons list
- search by group name/topic
- cards with image, name, episode count
- links to group page

Group page:

- item list includes description
- row/card settings control visibility, font sizes, description length, image size/position, and layout
- reuse content item card template family where practical

## Tests

- Groups page hides unpublished groups.
- Category toggles include descendants/inheritance according to taxonomy rules.
- Search matches group name/topic safely.
- Group card episode count counts public items only.
- Group page item rows include descriptions and respect settings.

## Security Notes

Group/category filters must use public item/group scopes. Descriptions use safe Markdown rendering or escaped text.

## Open Questions

- Should `/groups` be the permanent URL even when the label says podcasts?
- Should category filter state be URL-backed on the groups page?
- Which image fallback should group cards use if no cover exists?
