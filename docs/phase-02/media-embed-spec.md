# Phase 02 Media Embed Spec

## Storage

Continue storing URLs and metadata, never arbitrary embed HTML.

Recommended `ContentItem` fields:

- `media_url`
- `embed_url`
- `embed_provider`
- `media_duration_seconds`
- `external_id`
- `external_title`
- `external_description`
- `external_thumbnail_url`
- `external_published_at`
- optional metadata JSON for provider-specific values

## Providers

Plan for:

- Spotify
- YouTube
- Apple Podcasts
- SoundCloud
- strict admin-only generic iframe/oEmbed URLs

All embedded URLs must be HTTPS and host-allowlisted. When no permitted embed exists, render the original media link.

## Rendering

Render through an application-owned Blade component. The component controls iframe attributes, sandbox/referrer policy decisions, fallback links, and loading states.

## Metadata Extraction

Metadata extraction is future work. When implemented, it should be an explicit admin action/service with validation and no automatic remote fetching during import.

## Tests Required Later

- Approved HTTPS embed accepted.
- Non-HTTPS embed rejected.
- Unknown host rejected.
- Raw iframe HTML rejected.
- Fallback source link shown when no embed is available.
