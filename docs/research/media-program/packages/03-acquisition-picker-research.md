# Package 3 Research — Four-Source Acquisition Picker

Status: approved for Stage 2 implementation on 2026-07-23.

- Audit: `LS-20260723-PODTEXT-MEDIA-P3-ACQUISITION-PICKER-01`
- Option: `MEDIA-P3-O1-IMMEDIATE-SHARED-ADMISSION`
- Baseline: `main` at
  `a65de4c3c4c22b89cd2934dc01faa602877d924c`, clean and 17 commits ahead
  of `origin/main`.
- Package 1 implementation/hash stamp:
  `ca483c0c0072e0791fe6c26755aadae341ece0a5` /
  `39420d1f21fbe43e193913fc59d6d9efea5ced66`.
- Package 2 implementation/hash stamp:
  `2a6de67816b9a7c8e53bcd29795a5b306a36dbaf` /
  `a65de4c3c4c22b89cd2934dc01faa602877d924c`.

## Installed baseline

Installed source remains authoritative: Laravel 13.21.1, Filament 5.7.1,
Livewire 4.3.3 and Curator 5.1.2. No dependency update is required.

## Current behavior and reuse

- `MediaPickerPanel` and `PathCuratorPicker` already provide Gallery, current
  image, context filtering, All Media, search, pagination, visible repair
  reasons and immediate Upload. They are the shared UX shell.
- Gallery selection is attachment-only. It never copies, moves, renames,
  normalizes or edits an existing file.
- Current Upload is a permanent Media-library write before the owner form is
  saved. Package 3 deliberately preserves that immediate-library behavior for
  all new acquisition sources.
- `ImageUploadValidator` has useful new-input rejection for type, structure,
  size and dimensions, but it also re-encodes rasters and produces normalized
  hashes. Admission must retain rejection and remove raster transformation.
- `MediaFilesystemMutationCoordinator` combines acquisition with staging,
  hashes and mutation journals. New acquisition must leave that path; the
  coordinator remains isolated for existing lifecycle operations.
- `SafeExternalImageFetcher` already owns HTTPS, DNS, redirects, response
  limits and timeout controls. It returns bytes and is reused unchanged in
  purpose.
- `SvgUploadSanitizer` is the only SVG sanitizer. A successfully sanitized SVG
  is valid for every image purpose; an existing unsanitized SVG stays visible
  but nonselectable/non-inline.
- `MediaAttachmentManager` remains the only owner-attachment writer.
- `EpisodeSpotifyLookup`, `SpotifyLinksFetcher` and direct-import paths already
  produce image URLs. Spotify must feed URL acquisition rather than gain a
  downloader or fifth picker source.

## Research decisions

### Shared admission boundary

`MediaAcquisitionManager` accepts source-specific bytes and metadata, validates
new input, chooses a destination name, and delegates one final admission to a
small Curator registrar. The registrar creates the Curator Media row,
`MediaAsset` and Curator `MediaProviderBinding` in one database transaction
using one immutable ULID. The legacy converter is never part of new
acquisition.

Filesystem work precedes the database transaction. If database admission
fails, only a newly written destination is deleted. A Storage source registered
in place is never deleted. Owner attachment runs afterward through
`MediaAttachmentManager`; an attachment failure leaves the valid new library
item available for later use.

### Permanence and cancellation

Upload, URL and Storage become permanent library items when their acquisition
action reports success. Gallery remains a pending owner-form selection.
Cancelling the picker or outer owner action discards only the pending
attachment change. It does not delete a successfully acquired library item.
An acquisition that fails before database admission creates no rows.

### Source behavior

- **Gallery:** select an existing row; no file mutation.
- **Upload:** validate temporary upload bytes and admit synchronously.
- **URL:** fetch through `SafeExternalImageFetcher`, validate and admit
  synchronously in the picker so the new Media ID can be selected immediately.
  Existing importer/record background paths remain queued after commit, but
  call the same admission boundary.
- **Storage:** list only bounded, non-recursive candidates from configured
  disk/root pairs. The browser sends an encrypted opaque candidate identity,
  never a client-supplied path. Reuse an existing Media row for an identical
  disk/path. Register a safe public managed raster in place; copy when the
  source is private/copy-only or SVG sanitation changes bytes. Never scan the
  filesystem or mutate the source.

### New-input policy and filenames

Raster bytes are preserved exactly. Retain positive MIME/extension allowlists,
nonempty input, structural decoding, configurable size/dimension limits and
SVG sanitation. URL input without a usable filename extension may derive its
canonical extension from an allowed detected MIME; Upload and Storage retain
extension/MIME agreement.

The full cleaned original filename is stored as Media metadata. A new setting
chooses either an app-generated collision-resistant destination name or a
cleaned-original stem plus collision-resistant suffix. Raw
`preserveFilenames()` is not used.

### Admin UX settings

Package 3 adds bounded settings for:

- new-input maximum size, default 2048 KB;
- new-input maximum dimension, default 3000 pixels;
- Upload batch count, default 10;
- picker browse page size, default 25;
- picker search result limit, default 50; and
- acquisition filename strategy, default app-generated.

The positive allowlist, URL transport protections, selection safety cap,
parallel upload count and configured Storage roots remain code/config
boundaries. Logical folder schema and additional admin-managed destination
roots are deferred; folders never gate visibility.

## Filament and framework research

Laravel Boost version-aware documentation confirmed temporary upload handling,
filesystem writes, queued after-commit work, HTTP fakes and Livewire event
patterns for the installed versions. Filament documentation supports
`storeFiles(false)` for an app-owned immediate upload command and warns against
raw original filenames on public/local disks.

FilamentExamples was searched in two query/refinement passes. Relevant
search-only snippets were:

- `Custom Table Field With Product Picker Modal`:
  `QuoteProductsField` / `ProductPickerTable` for an embedded Livewire picker
  dispatching a selection while retaining the host modal;
- `AI-Powered CMS With Laravel AI SDK` for keeping an action modal open or
  halting on an operation failure; and
- numeric settings-field examples for bounded admin values.

The configured server exposed search/snippet results only, not a source/detail
fetch endpoint. No claim of full-example source review is made.

## Package boundaries

Package 3 may extend the existing picker, add one settings migration, admission
classes, Storage candidate browsing, Spotify URL integration, translations and
tests. It does not add Package 4 hover/detail/copy/download-column work or
Package 5 Files Discovery, directory management, move, rename, trash, restore,
purge or lifecycle journals. It adds no relational schema and no dependency.
