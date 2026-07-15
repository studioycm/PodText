# Public Front v2 Research: Card Template Builder

> **ARCH1 supersession — 2026-07-16:** This explains the shipped Builder
> vocabulary and renderer, but its settings-storage assumptions are historical.
> Future Card Templates are approved versioned Resources with parts/nested
> children remaining validated JSON inside immutable revisions. See
> `../settings-performance/07-sp3d-pre-research.md`.

## Purpose

Plan a JSON-first card template builder for public content cards, contributor cards, group cards, and section-specific cards.

## Topic Scope

Reusable templates, template families, card parts, entity data sources, layout variants, preview UX, and safe rendering.

## Exact Search Terms Used

- Boost: "Filament Builder field dynamic blocks JSON array block schema"
- Boost: "Filament Builder block labels icons columns preview"
- Boost: "Filament Repeater reorderable cloneable collapsible grid simple repeated data"
- Boost: "Tailwind CSS min-w-0 flex overflow line clamp grid card layout"
- FilamentExamples MCP: "Filament card template builder preview ViewColumn custom card"
- FilamentExamples MCP: "Filament Builder block preview side by side form preview"
- FilamentExamples MCP: "Filament custom table card design ViewColumn frontend theme"
- FilamentExamples MCP: "Filament table ViewColumn custom Blade card layout card grid"

## Boost Docs Used

- Filament Builder docs: block arrays, `Block::make()`, `blockPreviews()`, `preview('view')`, block picker columns/width, and icons.
- Filament Repeater docs: cloneable, reorderable, grid, table, min/max items, delete confirmation.
- Tailwind docs via Boost: `min-w-0`, line clamp, aspect ratio, object cover, dark mode, and RTL variants.

## FilamentExamples MCP Examples Found

- `v4/tables/table-as-grid-with-cards/app/Filament/Resources/Users/UserResource.php`: table rendered as cards with `Grid`, `Split`, `Stack`, `ImageColumn`, `TextColumn`, `contentGrid()`, and filters above content.
- `v4/full-projects/cms-blog-system-shield/app/Filament/Resources/Posts/Schemas/PostForm.php`: content form with `RichEditor`, media upload, grouped sections, and helper action.
- `v4/full-projects/hotel-management-bookings/app/Filament/Booking/Pages/FindHotel.php`: form plus preview/search results table on one page.

## Actual Files, Classes, and Snippets Observed

- Local: `resources/views/components/public/content-item-card.blade.php` renders current card and row variants.
- Local: `app/Support/PublicContent/PublicContentCardOptions.php` maps semantic config to safe classes.
- Local: `app/Livewire/Public/ContentItemSearch.php` passes card options to search/listing views.
- FilamentExamples: `contentGrid(['md' => 2, 'xl' => 3])` card-table pattern is useful as admin inspiration only; public should remain custom Blade/Livewire.

## GitHub/Source Files Inspected

- `https://github.com/LaravelDaily/Filament-Menu-Builder-Demo` for public layout/menu rendering inspiration.
- No full FilamentExamples source fetch was exposed beyond MCP snippets.

## Pattern To Copy

- Use Builder blocks for heterogeneous card parts.
- Use server-side previews that render the same Blade renderer used by public pages.
- Use semantic size/layout options and a resolver class.
- Use deterministic CSS layout rules: fixed image tracks, aspect ratio, `min-w-0`, controlled line clamps, and stacked mode for large images.

## Pattern To Avoid

- Do not create a `CardTemplate` model/table by default.
- Do not store raw class names or arbitrary Blade view paths in JSON.
- Do not use card templates to bypass public visibility rules.

## PodText Adaptation Notes

The current `PublicContentCardOptions` should become the seed for a larger `PublicCardTemplateRegistry` / `PublicCardTemplateRenderer` pair. Existing current settings become defaults for the v1 item card template.

## JSON-First Settings Recommendation

Store template families and definitions under a Spatie settings array, for example:

```json
{
  "families": {
    "content_item": {
      "default_template": "latest_square",
      "templates": [
        {
          "key": "latest_square",
          "label": "Latest square card",
          "parts": []
        }
      ]
    }
  }
}
```

Each part is a block with `type`, `source_entity`, `source_attribute`, `label`, `icon`, `label_position`, `icon_position`, `layout`, `visibility`, `order`, `line_clamp`, and `font_size_preset`.

## Model/Table Considered

Rejected: `CardTemplate` table. Templates are low-volume configuration, need portability, and do not require independent lifecycle, ownership, or queryability.

## Recommended Model/Schema Options

No new model. If future per-editor workflow, version history, or approval is needed, a model can be reconsidered as an explicit user decision.

## Recommended Filament Patterns

- SettingsPage section: `Filament\Forms\Components\Builder` for templates and parts.
- Nested `Repeater` or Builder blocks for parts, depending on preview/edit ergonomics.
- `Select` fields for family, source entity, source attribute, layout, icon, and font-size presets.
- `Toggle` for visibility and description visibility.
- Preview area beside the builder on wide admin viewports and below it on narrow/full-screen preview.

## Public Livewire/Blade Implications

The renderer should resolve entities already eager-loaded by `PublicContentItemQueries`. Missing relations or unavailable attributes render nothing. Public cards should continue to represent `ContentItem`, not `Transcription`, unless an explicit future page says otherwise.

## Tests

- Template defaults render equivalent current cards.
- Invalid part type/source/attribute is skipped.
- Same template renders in homepage, search, group page, and latest section.
- Large image settings do not create flex overflow; assert expected classes/markup.
- Multi-transcription author/date parts do not trigger N+1 queries.

## Security Notes

Custom content parts may allow plain text or safe Markdown only. Icons must come from a whitelist. URL/action parts must use known routes or sanitized HTTPS external URLs.

## Open Questions

- Should templates be global only, or can each `HomepageSection` override with inline JSON?
- Should admins be able to duplicate templates in v1?
- Which card families ship first: content item, group, contributor, category/tag?
