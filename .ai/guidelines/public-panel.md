# Public Panel Guideline

## Purpose

Define public browsing/search behavior for guest-facing Filament panel pages.

## Preferred architecture

Guest Filament Public panel with custom Pages, class-based Livewire for server-driven state, and Blade components for reusable content presentation.

## Do

- Return `ContentItem` records for homepage/search/category/tag listings.
- Require published group, published item, and effective/main published transcription.
- Use Blade for cards, group badges, type labels, media embeds, and transcript output.
- Use Alpine only for local UI behavior.
- Keep search/sort/filter state in URL where practical.

## Do not

- Do not render public result cards as `Transcription` records.
- Do not expose admin Resource routes publicly.
- Do not duplicate persisted state in Alpine.

## Testing rules

- Guest access tests.
- Draft/no-effective-transcription exclusion tests.
- RTL marker tests where feasible.
- Livewire search/sort/filter tests.

## Security rules

- Public queries must include publication/effective transcription constraints.
- Public Markdown must use safe renderer.
- Media embeds must use the owned component.

## FilaCheck / FilaCheck Pro notes

- Avoid table/card closures that query relationships.
- Ensure searchable text columns exist.
- Avoid deprecated Filament methods/namespaces.

## Related active docs

- `docs/phase-02/public-panel-ux-spec.md`
- `docs/phase-02/search-and-filters-spec.md`
- `docs/phase-02/blueprints/11-public-homepage-search-blueprint.md`
- `docs/research/filament-examples-phase-02.md`
