# Public Front v2 Step 9 Public Menu/Header and UX Fixes Handoff

## Purpose

Step 9 adds the JSON-settings-powered public header/menu and repairs the public UX/settings issues reported after Step 8 without starting Step 10, Step 11, Step 2 transcription policy, Prompt 13, or a CMS/page-management system.

## What was implemented

- Public settings admin organization was changed from one long stacked schema to domain tabs.
- About/team cards now support safe semantic card settings and reliable image rendering.
- Public rich content now applies explicit H1-H6 classes instead of relying on global heading inheritance.
- Contributor directory compact cards now act as preview selectors, with the detailed page link inside the preview.
- Homepage chrome/search clutter is suppressed in default homepage-section mode.
- Latest section search, pagination controls, and show-all link now live in the section header row.
- A minimal `content_block` homepage section source was added using safe Markdown and safe route/form actions.
- Public header/menu renders from `menu_config`, uses the existing PodText JPEG logo, opens Step 6 public forms, and includes a light/dark/system theme selector.

## Final public settings admin organization

`App\Filament\Pages\PublicContentSettings` now groups settings into tabs:

- Homepage / Sections
- General / Display
- Menu / Header
- Podcasts
- About
- Forms
- Advanced / Diagnostics

Top-level tab content uses full-width collapsible sections. Compact columns remain only inside field groups where they improve readability.

## About page/team fixes

Team profile images are normalized from string paths or FileUpload-style arrays and rendered through `PublicAboutPageRenderer::imageUrl()`.

The existing `about_page.settings` JSON now supports:

```json
{
  "team_card": {
    "show_image": true,
    "image_size": "medium",
    "layout": "grid",
    "density": "comfortable",
    "show_title": true,
    "show_description": true,
    "description_lines": 3
  }
}
```

Allowed values are registry-controlled. No `TeamProfile`, `AboutPage`, or About block model/table was created.

## Contributor directory fixes

`App\Livewire\Public\ContributorDirectory` now supports URL-backed search, selected contributor, preview search, sort, and page-size state. Page sizes are 10, 15, and 20. Sort modes are A-Z, Z-A, count descending, and count ascending.

Compact contributor cards render only the contributor name and a count badge with an icon and tooltip/title label. They do not render direct action links. Clicking a compact card selects it and opens the preview row below the list. The preview contains the contributor page link and a searchable list of related public items.

`Author` remains the contributor/transcriber model. Counts still use only public content/transcriptions.

## Homepage chrome and section header fixes

The homepage no longer renders the custom intro block or global discovery search/filter chrome when default homepage sections are shown. The `/search` context keeps the full search/sort/filter drawer behavior.

Homepage section headers now expose a responsive action row containing the section title, lightweight Latest search, previous/next controls where relevant, and a first-class show-all link.

## Public menu/header behavior

The public header renders through a Filament public panel render hook and keeps panel navigation disabled. `App\Livewire\Public\PublicHeader` reads normalized menu configuration through `PublicMenuConfigReader` and renders with `resources/views/livewire/public/public-header.blade.php`.

Default public header items are:

- Home
- Podcasts
- About
- Request transcription form
- Volunteer/register transcriber form
- Theme selector

The header uses `public/images/podtext-logo.jpg`.

## Menu config JSON schema

`public_content.menu_config` now supports:

```json
{
  "enabled": true,
  "items": [
    {
      "key": "home",
      "type": "route",
      "label": "Home",
      "route_key": "home",
      "visible": true,
      "sort": 10
    },
    {
      "key": "request_transcription",
      "type": "public_form",
      "label": "Request transcription",
      "form_key": "request_transcription",
      "display_mode": "slide_over",
      "visible": true,
      "sort": 40
    }
  ],
  "theme_selector": {
    "enabled": true,
    "mode": "light_dark_system"
  }
}
```

Supported item types are `route`, `external_url`, `public_form`, and `theme_selector`. External URLs must be HTTPS. Route keys and form keys are validated against server-side registries/config.

## Theme selector behavior

The theme selector is local browser UI only. Alpine owns `light`, `dark`, and `system` preference switching with `localStorage` key `podtext-theme`. No user setting, table, or package was added.

## Public form action integration

Header public-form items mount Step 6 `PublicFormModal` once per enabled form key used by the menu. Buttons dispatch:

```js
window.dispatchEvent(new CustomEvent('open-public-form', {
  detail: { formKey: 'request_transcription' },
}));
```

Livewire continues to own form state, validation, submission, honeypot, and rate limiting.

## Final namespaces and classes

- `App\Enums\PublicMenuItemType`
- `App\Livewire\Public\PublicHeader`
- `App\Support\PublicFront\Menu\PublicMenuConfigReader`
- `App\Support\PublicFront\Menu\PublicMenuRenderer`
- `App\Support\PublicFront\Menu\PublicRouteRegistry`
- `App\Support\PublicFront\Menu\PublicUrlSanitizer`
- `App\Support\Markdown\SafeMarkdownRenderer::publicContentClasses()`

No `PublicMenu`, `PublicMenuItem`, `PublicFormDefinition`, `Podcast`, `Episode`, or CMS page model was created.

## Final public API for future prompts

Runtime config reads should continue to use Step 1:

```php
$result = app(PublicFrontConfigReader::class)->read();
$menuConfig = $result->group('menu_config');
$publicForms = $result->group('public_forms');
$invalidConfig = $result->invalidConfigArray();
```

Menu rendering should use:

```php
$menu = app(PublicMenuConfigReader::class)->read();
```

Route action URLs should use:

```php
$url = app(PublicRouteRegistry::class)->url('podcasts');
```

## Fallback and invalid config behavior

The validator merges missing keys with defaults. Unknown menu item types, unknown route keys, disabled/missing public forms, unsafe labels, JavaScript URLs, non-HTTPS external URLs, raw HTML/iframe/script strings, raw CSS/classes, Blade paths, PHP class strings, and SQL-looking strings are rejected, skipped, or reported through invalid config.

The public header skips invalid item targets server-side.

## Security rules

- Menu configuration is JSON settings only; no dynamic Blade, PHP class, or raw CSS path is accepted.
- External menu URLs must be HTTPS.
- Public form menu items only open configured/enabled Step 6 forms.
- Rich content and content-block bodies use the existing safe Markdown renderer.
- Homepage and contributor queries preserve existing public visibility constraints.

## Sample JSON payloads

External URL item:

```json
{
  "key": "docs",
  "type": "external_url",
  "label": "Docs",
  "external_url": "https://example.com/docs",
  "open_in_new_tab": true,
  "visible": true,
  "sort": 60
}
```

Content block section display config:

```json
{
  "heading": "Support new transcriptions",
  "body": "Use the form to request a podcast or episode transcription.",
  "content_style": "callout",
  "button_label": "Request transcription",
  "button_form_key": "request_transcription",
  "button_display_mode": "slide_over"
}
```

## Sample PHP usage

```php
$result = app(PublicFrontConfigValidator::class)->validate($rawConfig);
$safeConfig = $result->config();

$menuItems = app(PublicMenuConfigReader::class)->read()->items;
```

## Blueprint deviations

No material deviations. Nested/dropdown menus and footer-builder v2 remain out of scope. The implemented content-block source is intentionally minimal and JSON-only.

## Impact on later prompts

Step 10 Contributors and Top Transcribers UX should build on the compact-card/preview separation introduced here and may extend contributor ranking/profile UX without changing `Author` as the public contributor model.

Step 11 Seeders, Demo Data, Assets, and Cleanup should seed or normalize `menu_config`, `about_page.settings.team_card`, and optional `content_block` homepage sections if demo coverage is needed.

Step 2 / Reserved Transcription Publication Policy remains deferred. Public visibility continues to use the existing published group, published item, and published effective/main transcription behavior.

Prompt 13 Dashboard Metrics has not started. It can later surface editorial metrics for menu/forms/about configuration only if those are real editorial metrics.

## Open issues / follow-up decisions

- Footer-builder v2 remains deferred.
- Nested/dropdown menu editing remains deferred.
- Full contributor/top-transcriber redesign remains Step 10.
- Full seed/demo cleanup remains Step 11.

## Tests and quality gate summary

Focused Step 9 coverage lives in `tests/Feature/PublicMenuHeaderUxFixesTest.php`.

Updated regression coverage includes public settings architecture, About/team rendering, forms settings, latest/search, homepage/search, display sections, card templates, contributors, podcasts/groups, and item page/media/parser tests.

Final command results are recorded in the Step 9 final report.
