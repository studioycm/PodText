# Blueprint Result: About Page Content And Team Builder

Source blueprint: `docs/phase-02/blueprints/public-front-v2/05-about-page-content-team-builder-blueprint.md`

Generated with Laravel Boost context and Filament Blueprint planning docs.

## Commands

```bash
php artisan make:filament-page AboutPage --no-interaction
php artisan make:enum AboutBlockType --no-interaction
php artisan make:test AboutPageSettingsTest --pest --no-interaction
php artisan make:test PublicAboutPageTest --pest --no-interaction
```

Place the generated Filament page in the public panel namespace manually if the generator creates it under the admin namespace.

## Models

Update: `App\Settings\PublicContentSettings`

- Ensure `public array $about_page = [];`

Rejected models:

- `AboutPage`
- `AboutPageBlock`
- `TeamProfile`

## Resources And Pages

New public page:

- Page: `App\Filament\Public\Pages\AboutPage`
- Route: `/about`
- Register in `App\Providers\Filament\PublicPanelProvider::pages()`
- Docs: https://filamentphp.com/docs/5.x/navigation/custom-pages

Update settings page:

- Page: `App\Filament\Pages\PublicContentSettings`

Field: `Filament\Forms\Components\Toggle`

- Docs: https://filamentphp.com/docs/5.x/forms/toggle
- Validation: `boolean`
- Config: about page enabled, team section enabled, block/profile visibility.

Field: `Filament\Forms\Components\TextInput`

- Docs: https://filamentphp.com/docs/5.x/forms/text-input
- Validation:
  - title: `required|string|max:120`
  - team member name: `required|string|max:120`
  - team member title: `nullable|string|max:120`
- Config: translated labels/helper text.

Field: `Filament\Forms\Components\Builder`

- Docs: https://filamentphp.com/docs/5.x/forms/builder
- Validation: `nullable|array`
- Config:
  - blocks: markdown, callout, image, team_section.
  - rich_text_json block only if user explicitly approves RichEditor JSON.

Field: `Filament\Forms\Components\MarkdownEditor`

- Docs: https://filamentphp.com/docs/5.x/forms/markdown-editor
- Validation: `nullable|string|max:20000`
- Config: content blocks rendered through existing safe Markdown renderer.

Field: `Filament\Forms\Components\RichEditor`

- Docs: https://filamentphp.com/docs/5.x/forms/rich-editor
- Validation: `nullable|array` if JSON mode approved.
- Config: `->json()` and render only with Filament rich content renderer/sanitizer.

Field: `Filament\Forms\Components\FileUpload`

- Docs: https://filamentphp.com/docs/5.x/forms/file-upload
- Validation: image file, max size, accepted MIME types.
- Config:
  - `->image()`
  - `->directory('team')`
  - `->disk('public')`
  - `->visibility('public')`
  - `->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])`
  - `->maxSize(2048)`

Field: `Filament\Forms\Components\Repeater`

- Docs: https://filamentphp.com/docs/5.x/forms/repeater
- Validation: `nullable|array`
- Config:
  - `Repeater::make('about_page.team.profiles')`
  - `->reorderable()`
  - `->cloneable()`
  - `->grid(['md' => 2])` if the form stays readable.

## Support Classes

Create:

- `App\Support\PublicFront\About\AboutPageConfigReader`
- `App\Support\PublicFront\About\AboutBlockRenderer`
- `App\Support\PublicFront\About\TeamProfileConfig`

Enum:

- `App\Enums\AboutBlockType`

## Authorization

- Admin settings editing: authenticated admin only.
- Public About page: guest-accessible only when enabled; disabled page returns 404 or redirects according to user decision.

## Widgets

None.

## Public Livewire And Blade

Create:

- `resources/views/filament/public/pages/about-page.blade.php`
- optional block partials under `resources/views/components/public/about/`

Rendering:

- Normalize config through reader.
- Render only visible blocks.
- Render team profiles ordered by sort/index.
- Missing image path gets safe placeholder or no image.

## Tests

- guest can view enabled page.
- disabled page does not render public content.
- hidden block/profile does not render.
- Markdown XSS is sanitized.
- RichEditor JSON is sanitized if enabled.
- FileUpload field has image, disk, directory, visibility, accepted types, max size.
- no About/team models exist.

## Security

- No raw HTML block.
- Rich content only through sanitizer.
- Uploads are image-only.
- JSON cannot contain Blade paths or classes.

## Quality Gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Out Of Scope

- General CMS page builder.
- Laravel teams/multi-tenancy.
- Linking team profiles to `Author`.

## Final Report Checklist

- State block types implemented.
- State Markdown/RichEditor decision.
- State upload config.
- Confirm no About/team models.
