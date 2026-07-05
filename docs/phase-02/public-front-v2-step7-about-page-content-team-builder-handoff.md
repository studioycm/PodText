# Public Front v2 Step 7 About Page Content and Team Builder Handoff

## Purpose

Step 7 adds a JSON-settings powered About page and team profile builder without introducing settings-only models. The public page is `/about` and is designed for Hebrew/RTL-safe rendering.

## What was implemented

- Added normalized `about_page` support to the public-front config registry and validator.
- Added a Spatie Settings migration that upgrades the existing placeholder `about_page` setting to the canonical Step 7 shape.
- Added a guest public `/about` Filament page.
- Added safe Markdown and RichEditor JSON rendering helpers.
- Added team profiles as JSON settings under `about_page.team_profiles`.
- Extended the admin public content settings page with About identity fields, content Builder blocks, team defaults, team profile Repeater rows, and safe image uploads.
- Added optional `form_cta` blocks that mount and open Step 6 `PublicFormModal` only for enabled public forms.
- Added focused Pest coverage in `tests/Feature/PublicAboutPageContentTeamTest.php`.

## Final namespaces and classes

- `App\Filament\Public\Pages\AboutPage`
- `App\Support\PublicFront\About\PublicAboutPageRegistry`
- `App\Support\PublicFront\About\PublicAboutPageRenderer`
- `App\Support\PublicFront\PublicFrontConfigRegistry`
- `App\Support\PublicFront\PublicFrontConfigValidator`
- `App\Filament\Pages\PublicContentSettings`

Views:

- `resources/views/filament/public/pages/about-page.blade.php`
- `resources/views/components/public/about/team-section.blade.php`
- `resources/views/components/public/about/profile-card.blade.php`

## Final public API for future prompts

Read normalized About settings through the Step 1 config reader:

```php
$result = app(PublicFrontConfigReader::class)->read();
$aboutPage = $result->group('about_page');
$invalidConfig = $result->invalidConfigArray();
```

Render a public form CTA by mounting Step 6:

```blade
<livewire:public.public-form-modal
    form-key="request_transcription"
    display-mode="modal"
/>
```

Future menu/header work can open a mounted form with:

```js
window.dispatchEvent(new CustomEvent('open-public-form', {
  detail: { formKey: 'request_transcription' },
}));
```

## About page JSON schema

Settings migration:

- `database/settings/2026_07_05_000002_normalize_about_page_setting.php`

Canonical shape under `public_content.about_page`:

```json
{
  "enabled": true,
  "title": "מי אנחנו",
  "kicker": "על PodText",
  "description": "Public summary",
  "blocks": [],
  "team_profiles": [],
  "settings": {
    "team_heading": "הצוות",
    "team_description": "Optional intro",
    "team_layout": "grid"
  }
}
```

Unknown top-level keys are reported and ignored. Disabled About pages return 404.

## Content block types

Supported v1 block types:

- `heading`
- `markdown`
- `rich_content`
- `image`
- `callout`
- `form_cta`
- `team_section`

Supported safe block fields:

- `key`
- `type`
- `visible`
- `sort`
- `heading`
- `body`
- `content`
- `rich_content`
- `image_path`
- `image_alt`
- `style`
- `form_key`
- `display_mode`
- `button_label`

Raw HTML fields, raw CSS/classes, arbitrary Blade paths, arbitrary PHP classes, iframe HTML, and JavaScript URLs are rejected or sanitized.

## Markdown rendering behavior

Markdown blocks and callout Markdown are rendered through `App\Support\Markdown\SafeMarkdownRenderer`. Unsafe HTML is stripped and unsafe links such as `javascript:` are removed. The public page stores only sanitized view-ready HTML for Markdown/RichEditor blocks in Livewire public state, so raw editor payloads are not serialized to guests.

## Rich content rendering behavior

RichEditor blocks store Filament RichEditor JSON. Rendering uses `Filament\Forms\Components\RichEditor\RichContentRenderer` and then passes the generated HTML through Symfony HTML Sanitizer. If rendering fails, the block safely renders empty content.

The validator allows only a limited semantic TipTap node/mark set and rejects unsafe `class`, `style`, `html`, `href`, and `src` values.

## Team profile JSON schema

Canonical shape under `about_page.team_profiles`:

```json
{
  "key": "editor",
  "visible": true,
  "sort": 10,
  "image_path": "team/editor.webp",
  "title": "Editor",
  "name": "Name",
  "description": "Short plain-text bio"
}
```

Team profiles are sorted by `sort`, hidden profiles are skipped publicly, duplicate keys are reported and skipped, and empty names are invalid.

## Team image upload behavior

Team profile images use Filament `FileUpload` with:

- disk: `public`
- directory: `team`
- visibility: `public`
- accepted MIME types: `image/jpeg`, `image/png`, `image/webp`
- max size: 2048 KB
- avatar-friendly display

About image blocks upload to the public `about/` directory with the same MIME and size constraints. Runtime rendering accepts only `about/` or `team/` image paths with safe image extensions.

## Public route/page behavior

The public route is `/about`. The page label/fallback title is `מי אנחנו`.

The page:

- is guest-accessible;
- uses RTL direction from `public.meta.dir`;
- returns 404 when `about_page.enabled` is false;
- renders visible blocks by normalized sort order;
- renders visible team profiles by normalized sort order;
- mounts `PublicFormModal` only for enabled form CTA blocks.

## Admin settings UI behavior

`App\Filament\Pages\PublicContentSettings` now includes an About page section with:

- enabled toggle;
- title, kicker, description;
- team heading/description/layout defaults;
- Filament Builder blocks for mixed About content;
- team profile Repeater rows;
- FileUpload constraints for `about/` and `team/` image paths;
- helper text for semantic fields and unsafe-value boundaries.

The admin form normalizes Filament upload-array state back to canonical string paths before validation and save.

## Public form CTA integration

`form_cta` blocks use Step 6 public form definitions. Missing or disabled forms are filtered out before public state serialization and do not render.

Enabled CTA blocks render a button that dispatches `open-public-form` with the configured `form_key`. The page mounts one hidden `PublicFormModal` per enabled form key.

## Fallback and invalid config behavior

Invalid blocks and team profiles are reported via `invalidConfigArray()` and skipped or normalized to safe defaults. Unknown semantic values use registry defaults where possible. Unsafe plain text values are rejected. Unsafe Markdown and rich content payloads are sanitized before output.

## Security rules

- No `AboutPage`, `AboutPageBlock`, or `TeamProfile` model/table was created.
- About/team content remains JSON settings.
- Public Blade/Livewire does not read raw settings directly.
- Public page state omits hidden profiles, disabled/missing form CTA blocks, raw Markdown source, and raw RichEditor JSON.
- Image paths must stay in `about/` or `team/`; traversal, absolute paths, double slashes, non-image extensions, and unknown directories are rejected.
- Raw iframe HTML, script HTML, JavaScript URLs, CSS strings, Tailwind class strings, PHP class references, and Blade-looking paths are rejected or sanitized.

## Sample JSON payloads

```json
{
  "about_page": {
    "enabled": true,
    "title": "מי אנחנו",
    "kicker": "על PodText",
    "description": "Transcription platform summary.",
    "blocks": [
      {
        "key": "intro",
        "type": "markdown",
        "visible": true,
        "sort": 10,
        "heading": "הסיפור שלנו",
        "content": "Markdown **safe** content"
      },
      {
        "key": "request",
        "type": "form_cta",
        "visible": true,
        "sort": 20,
        "form_key": "request_transcription",
        "display_mode": "modal",
        "button_label": "שלחו בקשה"
      }
    ],
    "team_profiles": [
      {
        "key": "editor",
        "visible": true,
        "sort": 10,
        "image_path": "team/editor.webp",
        "name": "Editor Name",
        "title": "Editor",
        "description": "Short bio"
      }
    ],
    "settings": {
      "team_heading": "הצוות",
      "team_layout": "grid"
    }
  }
}
```

## Sample PHP usage

```php
$result = app(PublicFrontConfigReader::class)->read();

if (! $result->hasInvalidConfig()) {
    $aboutPage = $result->group('about_page');
}
```

## Blueprint deviations

- Optional social links for team profiles were not implemented because the blueprint allowed them only if explicitly approved; v1 keeps profiles to plain text and safe image paths.
- RichEditor rendering was implemented, not deferred, after installed Filament source/docs confirmed JSON rendering through `RichContentRenderer`.

## Impact on later prompts

- Step 8 Podcasts and Groups UX can link to `/about` or reuse the safe image path approach, but should not change About schema.
- Step 9 Public Menu and Header can add a menu/header item for `/about` and can use `open-public-form` for About CTA forms without changing form state ownership.
- Step 10 Contributors and Top Transcribers UX should keep using contributor/author data and should not treat JSON team profiles as public contributors.
- Step 11 Seeders, Demo Data, Assets, and Cleanup can seed sample About JSON and place demo team images under `storage/app/public/team` or equivalent public-disk workflow.
- Step 2 / Reserved Transcription Publication Policy remains deferred/reserved; Step 7 did not change public item/transcription visibility rules.
- Prompt 13 Dashboard Metrics has not started; future widgets may count invalid About config warnings if explicitly scoped.

## Open issues / follow-up decisions

- Team social links remain deferred.
- Public menu/header integration remains Step 9.
- Public form file uploads and email notifications remain deferred.
- Rich content supports the current safe TipTap subset only; new RichEditor extensions must be added to the validator before public use.

## Tests and quality gate summary

Focused test added:

- `tests/Feature/PublicAboutPageContentTeamTest.php`

Focused test result during implementation:

- `php artisan test --filter=PublicAboutPageContentTeamTest` passed.

Full final gate results are recorded in the final implementation report.
