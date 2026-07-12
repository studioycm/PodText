# Images Arc IMG-A Handoff

Date: 2026-07-12

## Scope

Retrospective handoff for IMG-A, committed as `988676e feat: add image naming foundation and curator media library`.

IMG-A implemented the approved IMG-1 + IMG-2 merge: app-owned image naming foundation, Curator media library integration, content group cover hardening, public cover alt text, public settings image picker conversion, and the existing-asset registration command.

## Dependency Record

- Root Composer dependency added in IMG-A: `awcodes/filament-curator` 5.1.2.
- Transitive image/media dependencies installed by that package: `enshrined/svg-sanitize` 0.22.0, `intervention/gif` 4.2.4, `intervention/image` 3.11.8, and `league/glide` 3.2.0.
- No additional image dependencies were approved or installed by IMG-A.

## Implemented

- `AdminUxSettings::media_naming_strategy` with `slug`, `reference_key`, and `slug_key` options.
- `ImageFileNamer`, `ImageUploadRules`, and the shared `MediaPickerField`.
- Curator plugin registration on the admin panel only.
- `content_groups.cover_alt_text`.
- Content group cover form hardening and app-owned cover cleanup baseline.
- Public settings image fields converted to the shared picker while continuing to store path strings.
- Public group/card/badge cover alt rendering.
- `media:register-existing-curator-assets` for legacy cover/settings assets.
- Targeted tests for naming, picker persistence, settings paths, cover cleanup, registration idempotence, cover alt rendering, and export-column default state.

## Gate Record

The IMG-A session ran the full suite once and it failed on three integration issues. Those fixes were verified with targeted tests only in that session, and the full suite was not rerun before the IMG-A handoff gap.

The first later full-suite green over the tree was proven by EP1: `php artisan test` passed with 411 tests and 3,740 assertions.

## Corrections Adopted By IMG-B

- IMG-A's record-aware storage naming limitation is moot under D-IMG-A v2: `admin_ux.media_naming_strategy` now controls egress/download names only, not storage paths.
- IMG-A's adopted-media delete-on-replace behavior was too destructive for library-managed media. IMG-B changes cleanup semantics so Curator-registered files stay until explicitly deleted from the media library.
- Referenced Curator media deletion is now blocked rather than warned and allowed.
- EXIF stripping remains deferred until an approved image re-encoding step exists.

## Local Front Check Report

1. Open the admin media library and confirm Curator loads in the admin panel.
2. Upload or select a podcast cover from the content group form and confirm the form stores a plain public-disk path.
3. Open the public podcasts page and confirm the cover image renders with the configured cover alt text.
4. Open Public Content Settings and choose a header/default/team/about image; save and confirm the selected path persists.
5. Run `php artisan media:register-existing-curator-assets` locally and confirm existing cover/settings image paths are registered without moving files.
6. Change `admin_ux.media_naming_strategy` and confirm future egress/download behavior is reviewed under IMG-B, not by renaming stored files.

## Commit hash

`988676e feat: add image naming foundation and curator media library`
