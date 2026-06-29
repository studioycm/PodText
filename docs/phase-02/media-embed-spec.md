# Phase 02 Media Embed Spec

## Field Foundation

Prompt 08 owns media field foundation before import/export.

Fields on `content_items`:

- `media_url`
- `embed_url`
- `embed_provider`
- `media_duration_seconds`
- `external_id`
- `external_title`
- `external_description`
- `external_thumbnail_url`
- `external_published_at`
- `media_metadata`
- `direct_media_url`, nullable

## Security

- Store URLs and metadata only.
- Never store or render raw iframe HTML.
- HTTPS only for public media/embed URLs.
- Embed host/provider allowlist.
- Generic iframe/oEmbed is admin-only and strictly validated.
- Public rendering goes through the existing application-owned media Blade component.
- Fallback to original source link when no embed is allowed.

## Providers

Plan allowlisted provider handling for:

- Spotify
- YouTube
- Apple Podcasts
- SoundCloud
- strict generic admin-only iframe/oEmbed URL

## Metadata Extraction

Metadata extraction is not automatic. It should be an explicit admin action after field foundation exists. Imports must not fetch remote covers/media.

## Blueprint

See `docs/phase-02/blueprints/08-taxonomy-tags-pinning-settings-media-foundation-blueprint.md` and `docs/phase-02/blueprints/12-public-item-page-media-parser-blueprint.md`.
