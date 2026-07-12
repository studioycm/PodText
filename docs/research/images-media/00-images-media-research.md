# IMG-R Images and Media Research

Date: 2026-07-12

This is a docs-only research run. No app code, migrations, Composer changes, plugin installs, test suite, Pint, FilaCheck, or build commands were run. Validation for this run is limited to `git diff --check` and `git status --short`.

## Scope And Tool Access

- Installed stack confirmed by Laravel Boost `application_info`: PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, Laravel Boost 2.4.11.
- Laravel Boost used: `application_info`, `database_schema`, and `search_docs`.
- FilamentExamples used: `search_examples` only. No source/read/fetch/details tool was exposed, so findings from FilamentExamples are search/snippet-level evidence only.
- Local source audit used read-only shell probes against app, vendor, config, and docs files.
- Allowed local executable probes run: PHP ini reads, PHP extension checks, and one `php artisan tinker --execute` Storage/ZipArchive Hebrew-filename probe in `storage/app/public/__codex-img-r`, cleaned up afterward.

## Source Links

- Filament 5 FileUpload docs: https://filamentphp.com/docs/5.x/forms/file-upload
- Filament 5 Import action docs: https://filamentphp.com/docs/5.x/actions/import
- Filament 5 Export action docs: https://filamentphp.com/docs/5.x/actions/export
- Filament Spatie Media Library plugin docs: https://filamentphp.com/plugins/filament-spatie-media-library
- Packagist `filament/spatie-laravel-media-library-plugin`: https://packagist.org/packages/filament/spatie-laravel-media-library-plugin
- Packagist `spatie/laravel-medialibrary`: https://packagist.org/packages/spatie/laravel-medialibrary
- Spatie Media Library installation: https://spatie.be/docs/laravel-medialibrary/v11/installation-setup
- Spatie Media Library file naming: https://spatie.be/docs/laravel-medialibrary/v11/advanced-usage/naming-files
- Packagist `awcodes/filament-curator`: https://packagist.org/packages/awcodes/filament-curator
- Awcodes Curator GitHub README: https://github.com/awcodes/filament-curator
- Packagist `tomatophp/filament-media-manager`: https://packagist.org/packages/tomatophp/filament-media-manager
- Laravel 13 filesystem docs: https://laravel.com/docs/13.x/filesystem

## Job 1 - Inventory, Schema Gaps, And UX Candidates

### Current Image Surfaces

| Area | Current image storage | Public use | Gap / option | Effort |
|---|---|---|---|---|
| `ContentGroup` | `content_groups.cover_path` path on `public` disk. `ContentGroupForm` uses `FileUpload::make('cover_path')->disk('public')->directory('content-groups/covers')->visibility('public')->image()->maxSize(2048)` at `app/Filament/Resources/ContentGroups/Schemas/ContentGroupForm.php:80`. | `PublicDefaultImageResolver::contentGroupImage()` uses the cover, and `contentItemImage()` can fall back to the item's group cover. `ContentGroupsTable` already has an `ImageColumn` for the cover. | Strong immediate hardening target: explicit JPEG/PNG/WebP accepted types, helper text, central naming hook, old-file cleanup, and optional quick-upload record action. | Low for hardening; medium if adding `cover_alt_text`. |
| `ContentItem` | No local image path. `external_thumbnail_url` is URL-only and HTTPS-validated by `ContentItemMediaRules`; items also have `external_id`. | Item cards prefer `external_thumbnail_url`, then group cover if mode permits, then `default_images`. | Weakest local-image candidate. A local item image would duplicate the existing remote-thumbnail-first media model and add schema/resolver/import/export work. | Medium/high; not recommended for the first images step. |
| `Author` / contributor | No image/avatar column. | `PublicDefaultImageResolver::contributorImage()` only returns settings-driven contributor defaults. Contributor cards/pages exist, so there is no per-record visual identity. | Strongest new schema candidate if Yoni wants richer contributor UX: `authors.avatar_path` plus optional `avatar_alt_text`, admin form/table, resolver, cards/pages. | Medium; migration plus public/admin tests. |
| `Category` | No image column. | Public category/tag listings currently surface item cards, not category hero imagery. | Optional future UX candidate only. No active public spec requires category images. | Medium; migration plus optional category landing design. |
| `Transcription` | No image column. | Transcriptions are transcript records, not public cards. | Stays image-free by domain rule. | Not applicable. |
| Public settings JSON images | Menu logos, team images, about block images, and default image paths live in settings JSON and public disk paths. | Menu/header/about/default-image fallback rendering. | Good prior art: explicit accepted types, max size, helper text, companion alt fields for logos/about blocks. Settings lifecycle currently treats menu-logo/default-image paths as `asset_path`; team/about image paths are not fully covered by that semantic today. | Existing. |
| Settings backup thumbnails | Snapshot thumbnails shown through `SettingsBackupsTable`. | Admin-only backup previews. | Not content media. | Existing. |

### UX Candidates

| Candidate | Value | Risk / note | Suggested track |
|---|---:|---|---|
| Harden group cover upload field | High | Current bare `->image()` allows all image MIME types, and there is no helper text. Filament 5 docs say FileUpload should call `acceptedFileTypes()` or `image()`, but `image()` is broad. | IMG-1. |
| Central image naming concern | High | Current uploads use Livewire hashed names, with no predictable matching for WB7 or package export. Must not rewrite existing paths. | IMG-1. |
| Delete old files on replace and delete | High | Filament docs state developers are responsible for deleting files removed from disk; current model/app has no cleanup path. | IMG-1. |
| Quick-upload table record action | Medium | Useful once validation/naming/cleanup are centralized. Should be a record action on resources that have an image path. | IMG-1 after hardening. |
| Cover alt text | Medium/high | Accessibility and SEO benefit; mirrors menu logo/about block prior art. Requires schema if stored on `ContentGroup`. | Decision D-IMG-C before implementation. |
| Contributor avatar column | High | Contributor public UI has no per-record image source; resolver already has a contributor family. | Decision D-IMG-C; likely IMG-2 or later if approved. |
| Local item image | Low/medium | Items already prefer `external_thumbnail_url`; local image adds collision with remote provider thumbnail semantics. | Defer unless Yoni explicitly selects it. |
| Category image | Low | Optional public landing polish, not required by active specs. | Defer. |
| Form hint linking fallback settings | Medium | Low-risk editorial UX: "empty cover falls back to default image settings." | IMG-1. |

## Job 2 - Media Plugin Compatibility Matrix

Local extension probe:

```text
gd=loaded
imagick=loaded
zip=loaded
fileinfo=loaded
intl=loaded
mbstring=loaded
exif=loaded
```

| Option | Compatibility evidence | Storage model | Picker / upload | Naming and conversions | Alt/caption | Cleanup | RTL/i18n | Score |
|---|---|---|---|---|---|---|---|---:|
| No plugin: Filament `FileUpload` + app-owned naming/cleanup | Already installed. Filament 5 docs support `acceptedFileTypes()`, `maxSize()`, `Rule::dimensions()`, `getUploadedFileNameForStorageUsing()`, and image editor/crop helpers. | Existing path columns and settings JSON paths. | Upload only unless we build a later path/media picker. | Naming hook is direct. No conversions unless we add an app image processor later; GD/Imagick are locally available. | App-owned companion fields. | App-owned observer/service cleanup needed. | Native app translations. | 1 |
| `filament/spatie-laravel-media-library-plugin` + `spatie/laravel-medialibrary` | Official Filament plugin supports v5.x. Packagist currently lists `v5.7.0-beta3` requiring `filament/support` `v5.7.0-beta3`, but stable `v5.6.7` exists and aligns with current Filament 5.6.7. It requires `spatie/laravel-medialibrary:^11.0`; Spatie 11.23.2 requires PHP `^8.2` and Laravel components `^10.2|^11|^12|^13`. | Spatie `media` table with polymorphic model collections. Requires published migration and model changes. | `SpatieMediaLibraryFileUpload` and `SpatieMediaLibraryImageColumn`; field supports collections and custom properties. | Spatie supports custom `FileNamer`, conversions, responsive images, and custom path generation. | Custom properties can store metadata; Filament docs show `customProperties()` but not a full per-image alt UI. | Media Library owns file deletion for media records, but migration path and resolver adapter are nontrivial. | Official plugin page reports multilingual support. | 2 |
| `awcodes/filament-curator` | Packagist `v5.1.2` requires PHP `^8.2` and `filament/filament:^5.0`. README compatibility table maps 5.x to Filament 5.x. | Central Curator media model/table; README notes it does not work with Spatie Media Library. | `CuratorPicker` can select existing media or upload new media; `CuratorColumn` exists. | Curator has Path Generators and Glide/curation support; custom path generator is available, but exact filename-stem control would need source review during implementation. | Central media model likely carries metadata, but exact alt workflow needs source review. | Central library likely improves orphan handling, but record-level detach/delete semantics require tests. | Community plugin, theme CSS source integration required. | 3 |
| `tomatophp/filament-media-manager` | Packagist `4.0.3` requires `filament/filament:^v4.0` and `filament/spatie-laravel-media-library-plugin:^v4.0`. That is not compatible with current Filament 5.6.7. | Spatie Media Library based manager with folders. | Picker and direct upload features according to README. | Responsive image generation via Spatie. | Custom fields advertised. | Spatie-backed. | README advertises RTL/multilanguage support. | Reject |
| Other manager surfaced by FilamentExamples | None surfaced. FilamentExamples search batches returned plain `FileUpload`, `ImageColumn`, `SpatieMediaLibraryFileUpload`, and `SpatieMediaLibraryImageColumn` snippets; no additional maintained manager source surfaced. | Not applicable. | Not applicable. | Not applicable. | Not applicable. | Not applicable. | Not applicable. | Not scored |

Ranked recommendation:

1. Start with no plugin for IMG-1. It fixes real defects without dependencies or migrations and keeps `PublicDefaultImageResolver` as the only public image source boundary.
2. If Yoni wants a full media library, prefer the official Filament Spatie plugin over Curator because it is first-party and aligns with Spatie Media Library's cleanup/conversion model. This is a schema/dependency decision, not a hardening step.
3. Consider Curator only if the desired UX is a central media picker independent of Spatie. It cannot coexist cleanly with Spatie Media Library according to its README warning.
4. Reject TomatoPHP Media Manager for this stack until it has Filament 5 support.

## Job 3 - Central File-Naming Rule Design

### Evidence

- Filament 5 FileUpload defaults to random filenames, supports `getUploadedFileNameForStorageUsing()`, and warns that preserving user filenames on local/public disks is unsafe because MIME validation does not prove file extension safety.
- Laravel 13 filesystem docs state local driver `Storage::url()` values are not URL encoded and recommend storing filenames that create valid URLs.
- Local Hebrew filename probe:

```text
url=https://PodText.test/storage/__codex-img-r/שלום עולם.txt
exists=yes
read=hebrew-name-ok
zip_entry=תיקייה/שלום-עולם.txt
zip_read=zip-hebrew-ok
cleanup_exists=no
```

Storage and ZipArchive handled Hebrew paths and ZIP entries. The generated local public URL kept raw Hebrew characters and a raw space, so browser-safe serving depends on proper URL encoding by the consumer. That is acceptable for existing paths, but it is not a good default for new generated storage names.

### Proposed Naming Concern

Design target: `App\Support\Media\ImageFileNamer`.

- `semanticStem(Model $record): string`: slug when present and nonblank, fallback `reference_key`.
- `storageStem(Model $record): string`: recommended default `reference_key` for ASCII URL safety; optional strategy `slug` if D-IMG-A chooses Hebrew slug storage.
- `exportStem(Model $record): string`: slug when present plus `reference_key` disambiguator, so exported bundles stay readable and collision-safe.
- `extension(TemporaryUploadedFile|string $source): string`: lower-case extension normalized from trusted MIME/validated upload, not raw client extension.
- `pathFor(string $family, Model $record, string $extension): string`: family-specific directories, for example `content-groups/covers/{reference_key}.jpg`, `authors/avatars/{reference_key}.webp`, or if slug storage is approved `content-groups/covers/{slug}--{reference_key}.jpg`.
- Collision rule: always include `reference_key` in storage or export names unless the storage scope is guaranteed unique. Content item slugs are unique per group, so item images would require either `content-items/{group_reference_key}/{item_slug}.jpg` or `content-items/{group_reference_key}/{item_reference_key}.jpg`.

Integration points:

- Plain Filament FileUpload: `getUploadedFileNameForStorageUsing()` plus `directory()` from the naming concern.
- Quick-upload record action: same concern and the same validation rules.
- Observer cleanup: compare old/new path values and delete only old app-owned files in expected directories.
- WB7 queued downloads: use the same matcher and path generator after image validation.
- Spatie Media Library option: map concern into a custom `FileNamer`/path generator if D-IMG-B chooses Spatie.
- Curator option: map concern into a custom PathGenerator and verify filename control during implementation source review.
- Future export images: default export filenames use `exportStem()`, with an alternative external-id based naming mode for Spotify item images.

Matcher design for imports/WB7 should accept `reference_key`, slug, and where applicable `external_id` / Spotify episode ID. Editorial matching must not depend on the on-disk filename choice.

## Job 4 - Upload Limits

Local PHP ini probe:

```text
php_version=8.4.23
upload_max_filesize=256M
post_max_size=256M
max_file_uploads=20
```

Visible local chain:

- PHP permits much larger uploads than current image fields.
- `config/livewire.php` is not published, so Livewire's default temporary upload rule is still the practical pre-field ceiling; Filament docs describe the default as 12 MB unless the Livewire temp-upload rule is changed.
- Current app image field max sizes are 2048 KB on group covers, menu logos, default images, and about/team image registries.
- No nginx `client_max_body_size` value is visible in the repository. On Forge, read it from the site nginx config and the PHP tab, do not guess production values.
- `config/filesystems.php` uses `public` as a local disk rooted at `storage/app/public` with `APP_URL`-derived `/storage` URLs. Forge must have `php artisan storage:link` or equivalent symlink in place. Stored paths are relative, so a domain cutover is safe if `APP_URL` is correct.

Recommended limits:

- Covers: keep 2048 KB for IMG-1; allow only JPEG, PNG, WebP; add dimensions cap such as max 3000x3000 or max long edge 3000. Consider 4096 KB only if conversions/resizing land later.
- Contributor avatars/team images: 1024-2048 KB; JPEG, PNG, WebP; square crop guidance, no SVG.
- Logos: keep current 2048 KB and SVG allowance only for trusted logo settings.
- About/default images: keep 2048 KB and photo-safe MIME list.

## Job 5 - Content Packages: Images In Import And Export

Installed Filament source audit:

- `vendor/filament/actions/src/ImportAction.php` merges the CSV `FileUpload`, column mapping UI, and `Importer::getOptionsFormComponents()` into the import modal.
- The same action stores `options = array_merge($action->getOptions(), Arr::except($data, ['file', 'columnMap']))` and passes that options array to queued import jobs.
- `vendor/filament/actions/src/Imports/Importer.php` receives options in the constructor and exposes them through `getOptions()`.
- `vendor/filament/actions/src/Imports/Jobs/ImportCsv.php` serializes rows/options into jobs. It does not provide a native content-package abstraction or special lifecycle for extra uploaded files.

Therefore an import options form can technically include a zip `FileUpload`, but the queued jobs would only receive a stored path/string in `options`. A safe implementation would have to deliberately store the zip in a private/scratch disk path, authorize access, make every chunk job able to read it, and clean it up after the batch. That is much more than "add a column".

Zip safety requirements if ever built:

- Reject zip-slip paths: no absolute paths, no drive prefixes, no `..`, no backslash traversal, normalize every entry before extraction.
- Cap archive bytes, uncompressed total bytes, file count, and per-file bytes.
- Validate each image by MIME/content after extraction, not by extension only.
- Permit only JPEG, PNG, WebP for content images.
- Use a private scratch directory and delete it on success, failure, cancellation, and stale-batch cleanup.
- Reject duplicate manifest targets unless explicitly defined.
- Never fetch remote media during native CSV import.

Portability semantics:

- Current `ContentGroupExporter` exports `cover_path` disabled by default as a plain path string. `ContentGroupImporter` has no `cover_path` column. The exported value is a dead local path on another site.
- Future package export should not mean "export public disk path". It should mean "export a package manifest with portable identifiers and bundled media files", for example `images/content-groups/{reference_key}.jpg`.
- Future package import should reuse the settings lifecycle precedent: semantic `asset_path` plus missing-file warnings, selected-unit apply, and no silent file creation from unknown sources.

Comparison with WB7:

- WB7 already owns "Drive images-folder media fetch". It avoids HTTP upload limits, can be queued/resumable, and fits the editorial workflow of a Drive folder with images matched by resolver/matcher keys.
- Zip package support is the same class of deferred work as `transcript_file`: useful only after a safe package structure is approved and tested.
- Recommendation: defer zip image packages to IMG-3 and prefer WB7 for bulk media ingestion.

## Job 6 - Native Import/Export Relation Handling Audit

Current update-mode behavior:

- `ContentGroupImporter` imports `category_paths` and calls `$record->categories()->sync($categories->pluck('id')->all())`; provided categories replace the current relation set.
- `ContentItemImporter` imports `category_paths` and calls `$record->categories()->sync(...)`; provided categories replace the current relation set.
- `ContentItemImporter` imports `content_tag_slugs`, resolves only enabled `content` tags, and calls `$record->tags()->sync(...)`; provided tags replace the current tag set and can drop disabled tags.
- `ContentItemExporter` exports `content_tag_slugs` from `$record->contentTags`, not `$record->enabledContentTags`, so disabled tags are exported today.
- `TranscriptionImporter` collects `transcriber_reference_keys` and `transcriber_names`; when the resolved set is non-empty, `afterSave()` calls `Transcription::syncTranscribers()`.
- `Transcription::syncTranscribers()` calls `$this->authors()->sync($syncPayload)` and then synchronizes legacy `author_id` to the first author. Provided transcriber values replace the set; blank/omitted values currently preserve existing authors because the importer only syncs when the resolved set is non-empty.

Verified IE-1 issue:

- Disabled tag round-trip loss is real: exporter writes all content tags, importer accepts only enabled content tags. Re-importing an export can silently drop disabled tags from a content item.

Recommended IE-1 scope:

- Add explicit relation import mode option aligned with settings import vocabulary: `replace`, `merge`, and `add_only`.
- Keep current behavior as the named `replace` default for compatibility unless Yoni chooses a different default.
- Add tag export option: enabled-only vs all tags. Recommended default for public-portable exports is enabled-only; all-tags remains available for admin audit/export.
- Treat disabled tag inputs as row warnings or failures, not silent drops. A spec decision is needed if imports should be allowed to attach disabled tags.
- Preserve existing chunk-size tuning on import/export actions.

IE-1 tests must cover:

- Category replace, merge, and add-only update rows for groups and items.
- Tag replace, merge, and add-only update rows.
- Disabled tag exported all-tags then imported with enabled-only validation produces explicit warning/failure behavior.
- Transcriber replace, merge, and add-only rows, while preserving `author_id` compatibility.
- Blank relation cells obey documented behavior and do not accidentally clear relations unless a clear mode is explicitly selected.

## R-Decisions

1. R1 - IMG-1 should start with native `FileUpload` hardening, not a media plugin. Evidence: current defect is on `ContentGroupForm` and Filament 5 provides the needed accepted type, size, naming, and validation hooks. Rejected alternative: install Spatie/Curator first; that would add dependencies/migrations before fixing the known unsafe cover field and orphan files.
2. R2 - Keep `PublicDefaultImageResolver` as the single public image boundary. Evidence: item, group, and contributor public image resolution already flows through it. Rejected alternative: let plugin-specific Blade/components bypass the resolver; that would fragment default-image and publication behavior.
3. R3 - Default new on-disk generated names to ASCII `reference_key` stems, while retaining slug-based display/export stems and matcher support. Evidence: Hebrew filenames work in Storage and ZipArchive, but Laravel local `Storage::url()` is not URL encoded and the probe returned a raw Hebrew URL with a space. Rejected alternative: raw Hebrew slug filenames as the default; too fragile for public local URLs.
4. R4 - Implement delete-on-replace and delete-on-record-delete for app-owned path columns before adding more content image fields. Evidence: Filament says developers must delete removed files; current app has no cleanup. Rejected alternative: periodic orphan sweeps only; that hides deterministic cleanup behind maintenance work.
5. R5 - For content photos, allow JPEG, PNG, and WebP only; keep SVG logo-only in trusted settings. Evidence: current settings already deliberately allow SVG for logos, while covers currently use broad `image/*`. Rejected alternative: allow SVG covers/avatars; public direct URLs make SVG a needless XSS surface.
6. R6 - If schema expansion is approved, prioritize contributor avatars, then optional cover alt text, then category images; keep local item images deferred. Evidence: contributors have public cards/pages but no per-record image source, while items already have `external_thumbnail_url`. Rejected alternative: item local images first; they duplicate existing remote thumbnail semantics.
7. R7 - Defer image zip import/export packages and prefer WB7 Drive-folder ingestion for bulk media. Evidence: Filament import options can carry extra form data but not a full package lifecycle; zip safety and cleanup would be substantial. Rejected alternative: add a `cover_path` importer column or upload a zip in native import now; it would violate the portable-identifier and transcript_file deferral precedents.
8. R8 - IE-1 should make relation update semantics explicit with `replace`, `merge`, and `add_only`, and add an export tag-scope toggle. Evidence: importers currently use `sync()` replacement and disabled tags silently drop on round-trip. Rejected alternative: merely document the current loss; that preserves a data-loss trap.
9. R9 - Plugin adoption, if approved, should prefer the official Filament Spatie Media Library plugin over Curator, and reject TomatoPHP Media Manager for the current stack. Evidence: Spatie plugin is official and has 5.6.x versions; Curator supports Filament 5 but does not work with Spatie; Tomato currently requires Filament 4. Rejected alternative: choose a community manager before deciding whether PodText needs a central media library at all.
