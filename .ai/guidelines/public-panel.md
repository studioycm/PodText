# Public Panel Guideline

## Purpose

Define public browsing/search behavior for guest-facing Filament panel pages.

## Preferred architecture

Guest Filament Public panel with custom Pages, class-based Livewire for server-driven state, and Blade components for reusable content presentation.

## Do

- Return `ContentItem` records for homepage/search/category/tag listings.
- Require published group, published item, and effective/main published transcription.
- Use Blade for cards, group badges, type labels, media embeds, and transcript output.
- Reuse existing public card, group-badge, and contributor-card Blade components where item pages or landing pages surface related content.
- Use Alpine only for local UI behavior.
- Keep search/sort/filter state in URL where practical.
- Read `PublicContentSettings` and visible ordered `HomepageSection` records where homepage/search specs require public defaults and slices.
- Use the Prompt 10 category, tag, pinning, media metadata, and transcription model state as the public listing source of truth.

## Do not

- Do not render public result cards as `Transcription` records.
- Do not expose admin Resource routes publicly.
- Do not duplicate persisted state in Alpine.
- Do not implement public item page/parser, dashboard widgets, or studio behavior in the homepage/search prompt.
- Do not rewrite homepage/search or contributor discovery while implementing the Prompt 12 item page unless a shared component change is required.

## Testing rules

- Guest access tests.
- Draft/no-effective-transcription exclusion tests.
- RTL marker tests where feasible.
- Livewire search/sort/filter tests.
- Settings/section consumption tests where public homepage behavior depends on them.
- Regression tests that public cards still represent `ContentItem` records when multiple transcriptions exist.

## Security rules

- Public queries must include publication/effective transcription constraints.
- Public Markdown must use safe renderer.
- Media embeds must use the owned component.

## FilaCheck / FilaCheck Pro notes

- Avoid table/card closures that query relationships.
- Ensure searchable text columns exist.
- Avoid deprecated Filament methods/namespaces.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields, where present in admin surfaces feeding public pages, should auto-generate from title/name fields but allow manual override.
- Technical fields must have helper text, hints, or descriptions in admin forms.
- Date/date-time UI should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Admin dashboard widgets should include available public-content editorial metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/public-panel-ux-spec.md`
- `docs/phase-02/search-and-filters-spec.md`
- `docs/phase-02/blueprints/11-public-homepage-search-blueprint.md`
- `docs/phase-02/blueprints/12-public-item-page-media-parser-blueprint.md`
- `docs/research/filament-examples-phase-02.md`
