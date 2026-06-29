# Media Embeds Guideline

## Purpose

Keep media storage URL-only, safe to render, and available before import/export revisions.

## Preferred architecture

Store URLs/metadata on `ContentItem`; render through the app-owned Blade media component.

## Do

- Add media metadata foundation before import/export revision.
- Accept HTTPS URLs only.
- Use provider/host allowlists.
- Render original source link fallback.
- Keep metadata extraction explicit and admin-triggered.

## Do not

- Do not store raw iframe HTML.
- Do not fetch remote media during import.
- Do not render unapproved embed URLs.

## Testing rules

- Approved embed accepted/rendered.
- Unknown host rejected/fallback.
- HTTP rejected.
- Raw iframe HTML rejected.

## Security rules

- URL-only storage.
- Owned component controls iframe attributes.
- Sanitize displayed metadata.

## FilaCheck / FilaCheck Pro notes

- FileUpload fields, if later added, require accepted file types and max size.
- Avoid Blade Tailwind classes outside theme coverage.

## Related active docs

- `docs/phase-02/media-embed-spec.md`
- `docs/phase-02/blueprints/08-taxonomy-tags-pinning-settings-media-foundation-blueprint.md`
- `docs/phase-02/blueprints/12-public-item-page-media-parser-blueprint.md`
- `docs/research/filament-examples-phase-02.md`
