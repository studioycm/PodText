# Phase 02 Media Embed Spec

## Field Foundation

Prompt 08 owns media field foundation before import/export.

Fields on `content_items`:

- `media_url`
- `embed_url`
- `embed_html`, nullable trusted admin-pasted embed code
- `embed_provider`
- `media_duration_seconds`
- `external_id`
- `external_title`
- `external_description`
- `external_thumbnail_url`
- `external_published_at`
- `media_metadata`
- `direct_media_url`, nullable

Media provider, external ID, external published date, trusted embed HTML, and metadata fields are technical fields. Admin forms must provide helper text/hints for these fields and should group `embed_html`, `embed_provider`, `external_id`, `media_metadata`, and similar provider metadata in an Advanced or Technical details section where practical.

`external_published_at` should display and accept Israel/Hebrew day-first date-time format in UI (`dd/mm/yyyy HH:mm`, `Asia/Jerusalem`) even though it is stored normally through Laravel.

## Security

- Store URLs and metadata by default.
- D-EMB1 exception: `content_items.embed_html` may store admin-pasted embed code verbatim in a nullable text column. It is trusted-admin content with the same trust model as D30 maintenance HTML.
- Do not sanitize, normalize, rewrite, or extract `embed_html` on save. `ContentItemMediaRules` may pass it through as nullable trusted text with only bounded string/length checks if needed.
- Public media rendering precedence is `embed_html` first, else the existing `embed_url` allowlist flow.
- `embed_html` renders only through the application-owned public media Blade component raw mode.
- Never render `embed_html` through public cards, admin tables, Markdown rendering, generic presenters, imports/exports, or any surface outside the owned media component.
- HTTPS only for public media/embed URLs.
- Embed host/provider allowlist.
- Generic iframe/oEmbed URL extraction remains admin-only and strictly validated when saved to `embed_url`.
- Public rendering goes through the existing application-owned media Blade component.
- Fallback to original source link when no embed is allowed.

## Admin Workspace Behavior

- The episode workspace provides two embed affordances:
  - keep pasted iframe/embed code as trusted `embed_html`;
  - run an explicit extract-src helper that fills `embed_url` for the simple allowlisted URL path.
- Helper text must state that `embed_html` takes precedence over `embed_url` on the public item page.

## Tests

- `embed_html` renders verbatim on the public item page through the media component.
- When both `embed_html` and `embed_url` are present, `embed_html` wins.
- `embed_html` renders nowhere else.
- The extract-src helper fills `embed_url`.
- The existing `embed_url` path still rejects HTTP, unknown, and unapproved hosts.

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
