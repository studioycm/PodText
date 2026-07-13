# Media Embeds Guideline

## Purpose

Keep media storage URL-first, safe to render, and available before import/export revisions, with the narrow D-EMB1 trusted-admin raw HTML exceptions.

## Preferred architecture

Store URLs/metadata on `ContentItem` by default. Trusted admin-pasted embed code may be stored verbatim only in `content_items.embed_html`; trusted maintenance-page override HTML may be stored verbatim only in `PublicContentSettings` `maintenance.raw_html_override`; render all media through the app-owned Blade media component.

## Do

- Add media metadata foundation before import/export revision.
- Accept HTTPS URLs only.
- Use provider/host allowlists.
- Store admin-pasted trusted embed code only in `content_items.embed_html` when D-EMB1 behavior is in scope.
- Store trusted full maintenance-page override HTML only in `maintenance.raw_html_override`.
- Edit trusted raw HTML fields with an LTR code editor, not an RTL prose textarea.
- Render `embed_html` only through the owned public media-embed component raw mode.
- Give `embed_html` precedence over `embed_url` in that owned component.
- Keep an explicit extract-src helper for admins who want to fill the allowlisted `embed_url` path instead.
- Render original source link fallback.
- Keep metadata extraction explicit and admin-triggered.

## Do not

- Do not store raw embed/iframe HTML anywhere except `content_items.embed_html` or the maintenance-only `maintenance.raw_html_override`.
- Do not sanitize, normalize, rewrite, extract, trim, escape, or app-limit trusted raw HTML fields on save.
- Do not render `embed_html` through Markdown, public cards, admin tables, imports/exports, generic presenters, or any surface outside the owned media component.
- Do not fetch remote media during import.
- Do not render unapproved embed URLs.

## Testing rules

- Approved embed accepted/rendered.
- Unknown host rejected/fallback.
- HTTP rejected.
- Trusted `embed_html` renders verbatim on the item page through the media component.
- `embed_html` takes precedence over `embed_url`.
- `embed_html` renders nowhere else.
- Extract-src helper fills `embed_url` without changing `embed_html`.

## Security rules

- URL-first storage, with D-EMB1 trusted `embed_html` and maintenance `raw_html_override` as the only raw HTML exceptions.
- Owned component controls iframe attributes for URL embeds and owns raw-mode rendering for trusted `embed_html`.
- Sanitize displayed metadata.

## FilaCheck / FilaCheck Pro notes

- FileUpload fields, if later added, require accepted file types and max size.
- Avoid Blade Tailwind classes outside theme coverage.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields, where present, should auto-generate from title/name fields but allow manual override.
- Technical fields such as provider, external ID, external metadata, thumbnail URL, source URL, and direct media URL must have helper text, hints, or descriptions.
- Date/date-time UI should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Admin dashboard widgets should include available media warning metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/media-embed-spec.md`
- `docs/phase-02/blueprints/08-taxonomy-tags-pinning-settings-media-foundation-blueprint.md`
- `docs/phase-02/blueprints/12-public-item-page-media-parser-blueprint.md`
- `docs/research/filament-examples-phase-02.md`
