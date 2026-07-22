# MEDIA-P3 Acquisition and Picker Research Route

## Status

- Package ID: `MEDIA-P3-ACQUISITION-PICKER`
- Stage: approved route; detailed source reconciliation waits for Package 2.

## Settled requirements

- One reusable MediaAssetPicker on every image field.
- Four sources: Gallery, Upload, URL, Storage.
- Spotify is a podcast/episode contextual URL producer, not a fifth source.
- Gallery selection attaches existing asset with no copy/move/new asset.
- All compatible trusted assets are visible through All Media; default logical
  folder is only the initial filter.
- All new bytes use one validation/normalization/SVG/provider/asset pipeline.
- Upload preserves a cleaned original name by default with deterministic
  collision behavior and batch override.
- URL retains complete SSRF/redirect/DNS/size/time/content defenses.
- Storage uses an opaque server candidate/digest, never an arbitrary client
  path.
- Network/filesystem acquisition finishes before short owner/import
  transactions.

## Current evidence to recheck

- `PathCuratorPicker`, `MediaPickerPanel`, `MediaPickerField` and their Blade;
- Package 2 gallery/folder/settings/repair APIs;
- `DownloadExternalContentItemImage`, `SafeExternalImageFetcher`,
  `PinnedExternalImageTransport` and fixtures;
- `EpisodeSpotifyLookup`, Spotify fetcher page and podcast/episode forms;
- every current image field in settings, ContentGroup, ContentItem and About;
- Livewire 4 locked property/action hydration and testing APIs;
- Filament 5 modal field/custom component APIs and installed source.

## Required research output before code

- exact picker field/component/action and state contract;
- complete surface inventory and migration order;
- one acquisition service/result/idempotency contract;
- source-specific threat/failure matrix;
- owner expected-identity/queue transaction design;
- query/state/upload/network budgets;
- HE/EN/RTL and real browser workflow plan.

Do not implement from this route-only document without reconciling it after
Package 2.
