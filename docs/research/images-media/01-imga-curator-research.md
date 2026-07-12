# IMG-A Curator Research

Date: 2026-07-12

This research supports the single IMG-A implementation run from `prompts/pre-13-prompts/images-arc-imga-curator-codex-prompt.md`.

## Preflight

- `git status --short --branch`: clean on `main...origin/main`.
- Recent history contains the expected EP1-R docs commit: `80577e4 docs: add episode workspace research and plan`.
- IMG-A has not started in app code. Existing references are the IMG-R research/plan docs and this prompt.
- `rg` was unavailable locally, so repository search used shell `grep`/`find`.

## Tool Access

- Laravel Boost used: `application_info`, `database_schema`, and `search_docs`.
- Boost confirmed PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, and Tailwind CSS 4.3.2.
- Boost schema confirmed `content_groups.cover_path` exists and `content_groups.cover_alt_text` does not.
- Boost docs used for Filament FileUpload validation, Laravel `File::image()`/dimensions rules, panel plugin registration, and storage testing.
- FilamentExamples used: `search_examples` only. No source/read/fetch/details tool was exposed, so examples below are snippet-level evidence.
- Official/read-only Curator research used Packagist, GitHub README, GitHub raw source, and Composer metadata before code changes.

## FilamentExamples Findings

| Example | Path / class surfaced | Pattern to copy | Pattern to avoid | PodText adaptation |
|---|---|---|---|---|
| E-Shop Admin With Bootstrap Storefront | `v4/full-projects/eshop-with-front-page/app/Filament/Pages/ManageSettings.php` and `app/Settings/GeneralSettings.php` | A `SettingsPage` can use `FileUpload` for persisted settings paths. | Its field lacks accepted types, max size, helper text, and path-string normalization. | Keep PodText's existing `PublicContentSettings` normalization, but route image fields through the new app-owned factory. |
| Multi-Panel Hotel Booking Application | `app/Providers/Filament/HotelPanelProvider.php`, `BookingPanelProvider.php`, `app/Filament/Hotel/Pages/MyHotel.php` | Panel plugins/resources are registered per panel; forms use `statePath('data')` and explicit save flows. | Do not register Curator on public panels. | Register `CuratorPlugin::make()` only in `AdminPanelProvider`. |
| Schedule For Doctors | `app/Filament/Resources/Doctors/Schemas/DoctorForm.php` and `resources/views/filament/pages/manage-doctor-schedule.blade.php` | Image fields store public disk paths and Blade renders them with `Storage::url()`. | Avoid broad `->image()` alone for public images. | Continue to render public output through `PublicDefaultImageResolver`/owned Blade, not Curator components. |
| Custom Table Field With Product Picker Modal | `app/Filament/Forms/Components/QuoteProductsField.php` | Custom fields can dehydrate/rehydrate domain-specific state. | Relationship sync patterns are not useful for PodText path strings. | The media field factory should convert Curator media selections into plain path strings during dehydration/hydration. |

## Curator Install And Compatibility

- Packagist currently exposes `awcodes/filament-curator` v5.1.2, requires PHP `^8.2`, `filament/filament:^5.0`, `league/glide:^3.0`, `spatie/laravel-package-tools:^1.15.0`, and `enshrined/svg-sanitize:^0.22`. The only root Composer addition remains `awcodes/filament-curator`.
- The README compatibility table maps Curator 5.x to Filament 5.x, matching PodText's Filament 5.6.7.
- Official install is `composer require awcodes/filament-curator`, then `php artisan curator:install`.
- The README says Filament Panels must add the plugin in the panel configuration with `CuratorPlugin::make()`.
- The admin theme needs Curator CSS and Tailwind source entries:
  - `@import '../../../../vendor/awcodes/filament-curator/resources/css/plugin.css';`
  - `@source '../../../../vendor/awcodes/filament-curator/resources/**/*.blade.php';`
- Curator explicitly does not work with Spatie Media Library; PodText has no Spatie Media Library dependency in this run.

Sources:

- https://github.com/awcodes/filament-curator
- https://packagist.org/packages/awcodes/filament-curator

## Curator Source Findings

| Topic | Finding | Evidence | Implementation consequence |
|---|---|---|---|
| Picker state | `CuratorPicker` hydrates selected media into arrays, then dehydrates a selected media ID by default for single selection. | `src/Components/Forms/CuratorPicker.php` source. | PodText needs an app-owned factory that overrides dehydration to store a path string, not the ID. |
| Upload naming | Curator's `Uploader` uses a UUID filename unless `preserveFilenames()` is enabled; preserved names are slugged from the client filename. Collision appends `-time()`. Extension is lowercased from the uploaded original extension. | `src/Components/Forms/Uploader.php`. | Deterministic PodText names can be guaranteed in plain FileUpload mode. In Curator mode, uploaded names can be guided by the factory and path generator, but exact per-record naming is limited because the picker upload panel is not bound to the record's slug/reference key. |
| Path generation | `PathGenerator` exposes only `getPath(?string $baseDir): string`; `DefaultPathGenerator` returns the configured directory. | `src/PathGenerators/Contracts/PathGenerator.php`, `DefaultPathGenerator.php`. | Curator path generators control directories, not filename stems. PodText will add a path generator for family directories and keep deterministic filename logic in `ImageFileNamer` for app-owned FileUpload paths and future download/import flows. |
| Media table | Curator creates a `curator` table with `disk`, `directory`, `visibility`, `name`, indexed `path`, `width`, `height`, `size`, `type`, `ext`, `alt`, `title`, `description`, `caption`, `exif`, and `curations`. | `stubs/migration.stub`, `src/Models/Media.php`. | Existing files can be adopted by creating Curator media rows that point to their current public disk paths. No moving/renaming is needed. |
| Metadata UI | Curator media form includes editable `name`, `alt`, `title`, `caption`, and `description`. | `src/Resources/Media/Schemas/MediaForm.php`. | Content group alt text stays on `content_groups.cover_alt_text`; Curator alt/caption remains media-library metadata. |
| Delete semantics | Deleting a Curator `Media` model deletes `$media->path`, curation directories, empty directory, and Glide cache. Replacing media deletes/moves the stored file path. | `src/Observers/MediaObserver.php`. | Registering existing app-owned files makes library delete destructive. PodText cleanup must only delete old app-owned cover files when a group path changes or record deletes; command output/handoff must warn that deleting Curator records removes adopted files. |
| SVG handling | Curator depends on `enshrined/svg-sanitize`; uploads sanitize SVG content and `curator:sanitize-svgs` can sanitize existing SVG rows. | `composer show`, `src/Config/Concerns/HasSanitizers.php`, `src/Components/Forms/Uploader.php`, `src/Commands/SanitizeSvgsCommand.php`. | SVG remains allowed only for trusted logo settings. Curator mode can accept SVG logos because Curator sanitizes stored SVG markup, but content covers/default photos stay JPEG/PNG/WebP only. |

## PodText Current Code Findings

- `ContentGroupForm` uses `FileUpload::make('cover_path')->disk('public')->directory('content-groups/covers')->visibility('public')->image()->maxSize(2048)`.
- `ContentGroupsTable` uses `ImageColumn::make('cover_path')` on the public disk. Exporters still export `cover_path` disabled by default as a path string.
- `PublicDefaultImageResolver` returns `{url, source, path}` only; it does not return alt text.
- Public group/card/detail image Blade currently uses group title or item title as alt fallback. `content-group-badge.blade.php` reads `cover_path` directly.
- `PublicContentSettings` stores menu logos, default images, team profile images, and about block images as plain path strings inside JSON settings and normalizes Livewire upload arrays back to strings with `singleFileUploadPath()`.
- Current settings image directories are `header`, `team`, `about`, and `default-images`.

## Naming And Validation Decisions For IMG-A

- `AdminUxSettings` is a new Spatie settings group with only `media_naming_strategy`; EP1 is expected to extend it later.
- Default strategy is `slug`, with `reference_key` fallback when the slug is empty.
- Existing files are forward-only and are never renamed.
- `ImageFileNamer` stays pure and unit-tested:
  - semantic stem: slug else reference key;
  - storage stem: strategy-specific `slug`, `reference_key`, or `slug--reference_key`;
  - export stem: `slug--reference_key`;
  - extension: normalized from validated MIME;
  - family directories: `content-groups/covers`, `header`, `team`, `about`, `default-images`;
  - collisions append a stable numeric suffix before extension.
- Content photos allow JPEG, PNG, and WebP only with 2048 KB and a 3000x3000 dimensions cap.
- EXIF stripping is deferred until image re-encoding lands; this run records the note only.

## Implementation Outcome

- Installed `awcodes/filament-curator` v5.1.2 and published `config/curator.php`.
- Ran `php artisan curator:install --no-interaction`; it created and ran `database/migrations/2026_07_12_140228_create_curator_table.php`.
- Registered Curator only on the admin panel and added the required Curator CSS import/source entries to the Filament admin theme.
- Added `App\Support\Media\CuratorPathGenerator` to keep Curator directories normalized on the public disk.
- Added `App\Filament\Forms\MediaPickerField` and `PathCuratorPicker`; Curator mode and plain FileUpload mode both dehydrate plain path strings.
- Deterministic record-aware filenames are guaranteed in the plain FileUpload fallback. Curator mode keeps the app-owned family directory and path-string storage, but Curator's upload panel does not expose the ContentGroup slug/reference key to its default uploader.
- SVG logos are allowed in Curator mode because Curator sanitizes SVG uploads; SVG remains disallowed for content photos/default images/team/about images.
- Existing files can be adopted in place by creating Curator media rows. The local registration command run created 11 rows and did not move or rename files.
- Deleting a Curator media row for an adopted app-owned file is destructive because Curator's `MediaObserver` deletes the underlying path. PodText's cover cleanup only targets unused files under `content-groups/covers`.

## Open Implementation Risks

- Curator picker upload filename generation is not record-aware by default. The factory can guarantee path-string storage and directory/validation, but deterministic record slug/reference filenames are reliable only in app-owned FileUpload paths unless a deeper Curator upload override is added later.
- Curator media row delete is destructive for adopted files. This is acceptable for app-owned covers/settings assets if documented, but users should not delete adopted media rows casually.
- Curator's installed migration name is generated by `curator:install`; after installing, inspect generated files before editing integration code.
