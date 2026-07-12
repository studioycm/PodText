# Images And Media Track Plan

Date: 2026-07-12

This plan follows IMG-R research in `docs/research/images-media/00-images-media-research.md`. IMG-A implemented the approved IMG-1 + IMG-2 merge on 2026-07-12; the remaining mini-steps stay gated as noted below.

## Guardrails

- No schema changes until Yoni decides D-IMG-C.
- No dependency changes or plugin installs until Yoni decides D-IMG-B.
- No content package / zip import-export work until Yoni decides D-IMG-D.
- Existing stored paths are never rewritten by naming work.
- Public image output must continue through `PublicDefaultImageResolver`.
- Bulk media ingestion remains a WB7 concern unless D-IMG-D explicitly promotes zip package work.
- Native CSV imports must not fetch remote media.

## Yoni Decisions

### D-IMG-A - On-Disk Naming Policy

Final IMG-A decision: on-disk naming is a site setting named `media_naming_strategy`.

Implemented values:

- `slug` is the default. Hebrew slugs are allowed.
- `reference_key` stores the portable key as the filename stem.
- `slug_key` stores `{slug}--{reference_key}`.
- Empty slugs always fall back to `reference_key`.
- Existing stored files and paths are forward-only and are never renamed by strategy changes.
- Export stems remain `{slug}--{reference_key}` independently of the storage strategy.

Options:

- A1: `content-groups/covers/{reference_key}.{ext}`.
- A2 implemented as default: raw slug filenames, with validated extensions and `reference_key` fallback.
- A3 implemented as an available setting: `{slug}--{reference_key}.{ext}`.

Implements R3.

### D-IMG-B - Media Plugin Direction

Final IMG-A decision: Curator is selected and installed as the approved Filament 5 media library.

Options:

- B1: native Filament `FileUpload` remains available behind the PodText field factory fallback.
- B2: official `filament/spatie-laravel-media-library-plugin` plus `spatie/laravel-medialibrary` remains unselected.
- B3 implemented: `awcodes/filament-curator` v5 for Filament 5, registered on the admin panel only.
- B4 rejected for this stack: `tomatophp/filament-media-manager`, because its current package requires Filament 4.

Implements R1, R2, and R9.

### D-IMG-C - New Image Schema

Final IMG-A decision: harden existing group covers and add content group cover alt text. Contributor avatars are dead.

Options:

- C1 partially implemented: `content_groups.cover_path` remains the canonical cover path and was hardened through the app-owned picker factory.
- C2 dead: do not build author/contributor avatars.
- C3 implemented: `content_groups.cover_alt_text` is shipped and public group images use it with group-title fallback.
- C4: add category images later if category landing pages need them.
- C5 defer: local content item images, because items already have `external_thumbnail_url`.

Implements R4, R5, and R6.

### IMG-A Implementation Outcomes

- The only root Composer dependency added is `awcodes/filament-curator`.
- `App\Settings\AdminUxSettings` currently contains only `media_naming_strategy`; EP1 may extend the settings group later.
- Settings-page image assets now use the app-owned media picker factory and continue storing plain path strings in JSON.
- SVG remains allowed only for menu logos; Curator mode relies on Curator's SVG sanitizer, while content/default/about/team images remain JPEG/PNG/WebP.
- Existing `cover_path`, `header`, `team`, `about`, and `default-images` files can be registered in Curator with `php artisan media:register-existing-curator-assets` without moving or renaming files.
- The command was run locally during IMG-A and reported 11 created, 0 existing, 0 missing, and 0 skipped.
- EXIF stripping is deferred until a future image re-encoding step; IMG-A records the validation cap only.

### D-IMG-D - Image Packages In Import/Export

Recommendation: defer zip image packages and prefer WB7 Drive-folder media fetch.

Options:

- D1 recommended: keep image zip packages deferred; use WB7 for bulk image ingestion.
- D2: design an export-only media package manifest first.
- D3: build full import/export zip package support with manifest, zip-slip protection, caps, image validation, private scratch storage, cleanup, and missing-file warnings.

Implements R7.

## Mini-Step IMG-1 - Native Image Baseline

Status: implemented as part of IMG-A, except the quick-upload table action which was superseded by Curator picker integration and remains out of this run.

Scope:

- Add an app-owned media naming concern and validation helper for content image uploads.
- Apply explicit photo-safe accepted MIME types to `ContentGroup` covers: JPEG, PNG, WebP only.
- Add cover helper text that explains size/type/fallback behavior.
- Add a fallback hint linking editorially to default image settings.
- Add delete-on-replace and delete-on-delete cleanup for app-owned group cover files.
- Add a quick-upload record action on the content groups table after naming/cleanup are centralized.
- Use D-IMG-A to choose the storage filename strategy.
- Do not add cover alt text unless D-IMG-C explicitly chooses it.

Out of scope:

- Contributor/category/item image schema.
- Media plugins.
- Zip packages.
- Rewriting existing hashed cover paths.

Tests:

- Cover accepts JPEG/PNG/WebP and rejects SVG.
- Cover max size and dimensions are enforced.
- Existing cover paths remain valid and are not rewritten by edit forms.
- Replacing a cover deletes only the old app-owned file.
- Deleting a group deletes its app-owned cover file.
- Quick-upload action stores the expected path and preserves table rendering.
- Public resolver still returns item remote thumbnail, then group cover, then default fallback in the documented order.

Research decisions: R1, R2, R3, R4, R5.

## Mini-Step IE-1 - Relation Import/Export Semantics

Scope:

- Add relation mode import option for categories, tags, and transcription transcribers: `replace`, `merge`, `add_only`.
- Keep existing `replace` behavior as the compatibility default unless Yoni changes the default.
- Add export option for content item tags: enabled-only vs all content tags.
- Make disabled-tag round trips explicit: row warning/failure or a documented all-tags admin mode, not silent loss.
- Preserve existing import/export chunk-size tuning.
- Align option names with the existing settings-import vocabulary from D25/S1b.

Out of scope:

- Image uploads.
- Content package zip support.
- Remote media fetches.

Tests:

- Group category relation modes.
- Item category relation modes.
- Content tag relation modes.
- Disabled tag export/import behavior.
- Transcriber relation modes with `author_id` compatibility.
- Blank relation cells preserve or clear exactly as the selected mode documents.

Research decisions: R8.

## Mini-Step IMG-2 - Optional Media Plugin Track

Status: implemented for Curator as part of IMG-A.

Gate: complete for Curator; Spatie Media Library remains unselected.

Scope if Spatie is selected:

- Add the exact `filament/spatie-laravel-media-library-plugin` version compatible with the installed Filament version.
- Publish and run Spatie Media Library migrations.
- Prepare selected models with media collections.
- Add resolver adapter beneath `PublicDefaultImageResolver`.
- Define conversion/responsive image policy and queue expectations.
- Define custom file naming/path generation and alt/custom-property storage.
- Plan migration/coexistence from existing path columns.

Scope if Curator is selected:

- Installed Curator only; Spatie Media Library was not selected.
- Registered Curator plugin and theme sources.
- Kept public output beneath `PublicDefaultImageResolver`; no Curator public renderer was added.
- Verified custom path/filename control, metadata, cleanup, authorization, and SVG sanitizer behavior from source before coding.

Out of scope:

- TomatoPHP Media Manager for the current Filament 5 stack.
- Any plugin that bypasses default image settings or public visibility rules.

Tests:

- Admin upload/select workflow.
- Public resolver output and fallback order.
- Old media cleanup/detach behavior.
- Conversion/thumbnail generation if enabled.
- Import/export non-regression.

Research decisions: R2, R3, R6, R9.

## Mini-Step IMG-3 - Content Packages And Zip Media

Gate: run only after D-IMG-D approves zip package work.

Likely status: deferred.

Scope if promoted:

- Define a package manifest with portable identifiers and media relative paths.
- Export bundled media files using naming concern export stems, not raw `cover_path` values.
- Import package media from private scratch storage only.
- Add zip-slip protections, archive caps, count caps, per-image validation, cleanup, and missing-file warnings.
- Reuse settings lifecycle package semantics where practical.

Out of scope:

- Remote media fetch during native CSV imports.
- Treating `cover_path` CSV values as portable identifiers.
- Transcript file package support unless separately approved.

Tests:

- Export manifest shape and bundled image names.
- Import with missing image warnings.
- Zip-slip rejection.
- Duplicate target rejection.
- Per-file and total size caps.
- Cleanup on success and failure.

Research decisions: R3 and R7.

## WB7 Touchpoints

- WB7 should use the same naming concern from IMG-1.
- WB7 matching should accept slug, `reference_key`, and content item `external_id` / Spotify ID.
- WB7 Drive downloads should validate image MIME/content before writing to public disk.
- WB7 should report missing/unmatched images as reviewable warnings, not silently attach to the wrong record.
- WB7 should route all public output through `PublicDefaultImageResolver` after storage.

## Explicit Out Of Scope Until Approved

- Composer/package changes.
- Plugin installation.
- New image migrations.
- Zip image import/export implementation.
- S3 or remote filesystem migration.
- Rewriting current `cover_path`, `header`, `team`, `about`, or `default-images` files.
- Prompt 13 dashboard metrics.
