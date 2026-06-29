# Taxonomy and Tags Guideline

## Purpose

Separate custom hierarchical categories from Spatie flat content tags.

## Preferred architecture

Custom hierarchical `Category` model plus Spatie Laravel Tags with the Filament Spatie Tags plugin, scoped to type `content`. Spatie tag package usage is approved for Phase 02 implementation; do not ask for package approval again when Prompt 08 reaches this work.

## Do

- Use categories for hierarchy.
- Use Spatie taggables for tags.
- Enable tags before public display.
- Include group category inheritance in public item filters.
- Include descendant categories when filtering by a parent.

## Do not

- Do not create a duplicate custom tag pivot when using Spatie tags.
- Do not use unscoped free-form tag inputs.
- Do not make tags hierarchical.

## Testing rules

- Category hierarchy.
- Group-to-item inheritance.
- Descendant filtering.
- Tag type scoping.
- Disabled tag hiding.

## Security rules

- Disabled tags are admin-only.
- Public category/tag pages return public `ContentItem` records only.

## FilaCheck / FilaCheck Pro notes

- Relationship selects should be searchable/preloaded.
- Category/tag tables need searchable name/slug columns and useful filters.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields should auto-generate from category/tag title/name fields but allow manual override.
- Technical fields must have helper text, hints, or descriptions.
- Date/date-time UI should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Admin dashboard widgets should include available category/tag metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/taxonomy-tags-spec.md`
- `docs/phase-02/blueprints/08-taxonomy-tags-pinning-settings-media-foundation-blueprint.md`
- `docs/research/filament-examples-phase-02.md`
