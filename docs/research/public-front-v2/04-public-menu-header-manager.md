# Public Front v2 Research: Public Menu and Header Manager

## Purpose

Plan a JSON-first public header/menu manager for the Filament public panel shell.

## Topic Scope

Top public menu, route labels, internal/external links, form modal/slide-over entries, theme selector, public header rendering, and route naming.

## Exact Search Terms Used

- FilamentExamples MCP: "Filament menu builder public header NavigationItem renderHook"
- FilamentExamples MCP: "LaravelDaily menu builder Filament menu items"
- FilamentExamples MCP: "Filament public action modal slide over form"
- FilamentExamples MCP: "Filament render hook topbar custom header"
- External: "LaravelDaily Filament Menu Builder Demo GitHub"
- External: "github.com LaravelDaily Filament-Menu-Builder-Demo"
- External: "LaravelDaily Filament menu builder demo"

## Boost Docs Used

- Filament Actions modal/slide-over docs.
- Filament custom page and `Page::getUrl(panel: ...)` docs.
- Filament table URL docs for safe URL handling.

## FilamentExamples MCP Examples Found

- `v4/full-projects/hotel-management-bookings/app/Providers/Filament/HotelPanelProvider.php`: multi-panel provider pattern.
- `v4/forms/edit-profile-custom-forms/app/Filament/Pages/EditProfile.php`: custom page with multiple schemas and save actions.
- Search did not expose a first-party JSON menu/settings snippet.

## Actual Files, Classes, and Snippets Observed

- Local: `app/Providers/Filament/PublicPanelProvider.php` disables navigation and registers public pages.
- Local public routes/pages include home, search, groups, group show, item show, and contributors.
- LaravelDaily menu demo:
  - `config/filament-menu-builder.php` maps menuable models and excludes routes.
  - Migrations create `menus` and `menu_items`.
  - `menu_items` stores type, URL, route, route parameters, target, link/wrapper classes, parameters, and nested set columns.
  - `resources/views/components/layouts/app.blade.php` renders package menu components by slug.

## GitHub/Source Files Inspected

- `https://github.com/LaravelDaily/Filament-Menu-Builder-Demo`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/config/filament-menu-builder.php`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/database/migrations/2026_02_16_061744_create_menus_table.php`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/database/migrations/2026_02_16_061745_create_menu_items_table.php`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/resources/views/components/layouts/app.blade.php`

## Pattern To Copy

- Render a header menu from a stable slug/key.
- Support internal routes, external URLs, and nested/group options later.
- Keep menu rendering in a Blade component so public shell markup is centralized.

## Pattern To Avoid

- Do not use menu/menu-item tables for the PodText default plan.
- Do not store raw `link_class` or `wrapper_class` style fields.
- Do not expose arbitrary route names without a registry.

## PodText Adaptation Notes

The public panel currently works without Filament navigation. Keep it that way and add an app-owned public header component that reads normalized settings JSON.

## JSON-First Settings Recommendation

Store menu config in a settings array:

```json
{
  "header": {
    "items": [
      {"type": "internal_route", "label": "Home", "target": "home", "visible": true},
      {"type": "public_form", "label": "Request transcription", "form_key": "request-transcription", "display_mode": "modal"}
    ],
    "theme_selector": {"enabled": true, "position": "end"}
  },
  "route_labels": {
    "contributors": {"label": "Transcribers"},
    "groups": {"path": "groups", "label": "Podcasts"}
  }
}
```

## Model/Table Considered

Rejected: `PublicMenu` and `PublicMenuItem`. The header menu is small, site-level configuration and portable as JSON.

## Recommended Model/Schema Options

No model. Public forms may point to form definitions in settings. If submissions are stored, that is handled by `PublicFormSubmission`, not the menu.

## Recommended Filament Patterns

- SettingsPage Builder or Repeater for menu items.
- Select for `type`, internal target, external target behavior, and public form key.
- Toggle for visibility, open new tab, and theme selector.
- Action preview for modal/slide-over form launch if practical.

## Public Livewire/Blade Implications

Add a public Blade header component that renders normalized menu items. Public form menu items trigger Livewire/Filament Action modals or a custom Livewire modal/slide-over. Theme selector should store preference locally, not duplicate persistent settings.

## Tests

- Default menu renders home, groups, about, request transcription, volunteer transcriber, and theme selector when configured.
- Disabled/unknown menu item types are skipped.
- External URL is HTTPS-only or safely sanitized.
- Form menu item cannot open disabled/missing form.
- Route label settings affect display without breaking route generation.

## Security Notes

Internal routes must be registry keys. External URLs must reject `javascript:` and non-HTTP(S) schemes. Public forms must validate enabled state at submit time.

## Open Questions

- Should groups route path remain `/groups` permanently while label changes, or can path be admin-configured?
- Should old podcast/group routes redirect if labels/paths change?
- Should dropdown/group menu items be v1 or deferred?
