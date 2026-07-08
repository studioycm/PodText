# Step 10R-IP1 MCP Research - Episode Page Dates And Card Date Attributes

## Scope

Selected mini-step: Step 10R-IP1 - Episode page settings foundation, dates, and info-badge tokens.

This research covers the new `item_page` settings group, site/original/transcription date settings, finite info-badge tokens, and the content-item card-template date attribute gap called out by Yoni.

## Laravel Boost Access

Access level: installed-version application/package guidance, database inspection, and local database query.

Tools used:

- `application_info`
- `database_schema`
- `database_query`
- `search_docs`

Findings:

- Installed stack: Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, Tailwind CSS 4.3.2, PHP 8.4, local SQLite.
- `content_items.published_at` exists and is the "published on site" date.
- `content_items.original_published_at` exists and is the "originally published" date.
- `transcriptions.published_at` exists and remains the policy/effective transcription publish date source.
- The old `author_content_item` table is absent from SQLite schema.
- The `settings` table stores Spatie settings rows by group/name. A new `public_content.item_page` row is the correct migration shape for existing installations.
- Filament tabs, sections, fieldsets, selects, toggles, and text inputs can edit nested settings paths such as `item_page.dates.site_published.icon`.
- Eloquent date values should be formatted at presentation time. Existing card presenter date formatting already uses `timezone('Asia/Jerusalem')->format('d/m/Y')`.
- Boost search did not surface Spatie Settings migration package docs directly, so existing project settings migrations were inspected as the source of truth for `SettingsMigration` and `$this->migrator` usage.

## Boost Search Docs Queries

- `Spatie Laravel Settings settings migration add property array JSON settings migration`
- `Filament SettingsPage tabs sections form schema state path array settings`
- `Filament Builder Repeater nested array settings page options helper text`
- `Laravel Eloquent date casting timezone format Asia Jerusalem Carbon tests`
- `Pest Laravel rendered output assert see html translations`

Usable guidance:

- Continue the existing Spatie Settings class and settings migration pattern.
- Keep nested settings edited through safe finite Select options and plain TextInput overrides.
- Keep formatted dates in presenters/rendering, not persisted strings.
- Use behavior tests around normalized settings and rendered cards rather than class-existence checks.

## FilamentExamples MCP Access

Access level: search/snippet access only. No separate source/read/fetch/details tool was exposed.

First-pass query batches:

- `SettingsPage tabs`
- `settings repeater`
- `nested settings fields`
- `date display settings`

Second-pass query batches:

- `badge icon label`
- `metadata row renderer`
- `public detail page`
- `safe icon map settings`

Refined query batches:

- `public card layout presenter`
- `custom public page view data`
- `date badge settings`
- `SettingsPage date options`

Relevant examples and patterns:

- `v4/forms/repeater-five-advanced-use-cases/.../ProductForm.php`
  - Pattern to copy: nested configuration fields stay manageable when grouped into collapsed/sectioned areas with finite controls.
  - PodText adaptation: IP1 uses fieldsets for each date type instead of unstructured JSON editing.
- `v4/forms/select-with-custom-html-values-and-search-results/.../CategoryForm.php`
  - Pattern to copy: enum-backed icon choices can be finite and searchable.
  - Pattern to avoid: `allowHtml()` option labels and any raw HTML persisted into settings.
  - PodText adaptation: icon settings use M5's app-owned finite icon registry.
- GitHub-style profile/custom page snippets
  - Pattern to copy: public views should receive prepared view data and fixed class maps.
  - PodText adaptation: IP1 adds `PublicFrontRenderContext::itemPage()` and a badge token helper; IP2 will consume these in the page header renderer.
- Settings page state-path snippets
  - Pattern to copy: nested array settings can be edited directly through SettingsPage fields.
  - PodText adaptation: `item_page.dates.*` and `item_page.badges.info` are normalized by `PublicFrontConfigValidator` before save.

## Code Inspection Summary

Inspected:

- `app/Settings/PublicContentSettings.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicFront/PublicFrontRenderContext.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRegistry.php`
- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `database/settings/2026_07_08_000008_add_public_transcription_display_settings.php`
- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
- `tests/Feature/PublicFrontRenderContextTest.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `lang/en/public.php`
- `lang/he/public.php`

Key findings:

- `PublicContentSettings` has scalar `item_page_layout` but no extensible `item_page` JSON group.
- `PublicFrontConfigRegistry::settingsKeys()`, `defaults()`, and `schema()` do not include `item_page`.
- `PublicFrontConfigValidator` has no `item_page` normalizer.
- `PublicFrontRenderContext` has no `itemPage()` accessor.
- `PublicFrontCardTemplateRegistry::attributes()['content_item']` includes `original_published_at` but does not include `site_published_date`.
- `PublicContentItemCardPresenter` formats `original_published_at` and effective transcription `published_at`, but does not format `content_items.published_at` as its own card-template value.
- The settings page has a display tab and an `item_page_layout` scalar field; IP1 should add a dedicated Episode page tab and keep the legacy scalar compatible.

## Resulting Implementation Direction

- Add `public_content.item_page` with this normalized default shape:

```json
{
  "dates": {
    "display": "both",
    "site_published": {
      "label_mode": "long",
      "label_override": null,
      "icon": "calendar",
      "icon_position": "inline_before"
    },
    "original_published": {
      "label_mode": "short",
      "label_override": null,
      "icon": "calendar",
      "icon_position": "inline_before"
    },
    "transcription_date": {
      "enabled": true,
      "label_mode": "short",
      "label_override": null,
      "icon": "document",
      "icon_position": "inline_before"
    }
  },
  "badges": {
    "info": {
      "size": "sm",
      "color": "gray"
    }
  }
}
```

- Add finite item-page token helper methods/classes for date display, label modes, badge sizes, and badge colors.
- Keep icons and icon positions delegated to the M5 card-template finite registry.
- Add `PublicFrontRenderContext::itemPage()`.
- Add a Spatie settings migration that backfills the `item_page` row for existing settings.
- Add the Episode page settings tab with IP1 fields and keep `item_page_layout` as a compatibility scalar inside that tab.
- Add `content_item.site_published_date` to the card-template registry and card presenter, formatted as `d/m/Y` in `Asia/Jerusalem`.
- Keep `content_item.original_published_at` and add a compatibility alias `content_item.original_published_date` for the IP2 field-key vocabulary.
- Do not rebuild the public episode page header/info line in IP1. Yoni's requested page display depends on this foundation, but the actual page placement/rendering is the first task of IP2.
