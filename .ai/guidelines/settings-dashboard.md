# Settings and Dashboard Guideline

## Purpose

Define durable rules for global public settings, homepage sections, and editorial dashboard widgets.

## Preferred architecture

Spatie Settings for global options, normal database records for ordered homepage sections, simple editorial Filament widgets. Spatie Settings package usage is approved for Phase 02 implementation; do not ask for package approval again when Prompt 08 reaches this work.

## Do

- Use typed settings classes.
- Use homepage section records for visible ordered sections.
- Keep dashboard widgets editorial.
- Link widgets to Filament Resources through Resource URL helpers.
- Include available editorial metrics as dashboard widgets and extend them as later schema becomes available.

## Do not

- Do not add analytics/search logging.
- Do not add observability dashboards or retry managers.
- Do not use item pinning as settings storage.

## Testing rules

- Settings defaults and save behavior.
- Homepage section visibility/order.
- Widget render/count tests.
- Admin-only access.

## Security rules

- Settings/admin widgets require authenticated admin panel access.
- Public section queries must use public item visibility rules.

## FilaCheck / FilaCheck Pro notes

- Avoid default polling in widgets unless needed.
- Use searchable table columns and useful warning filters.
- Use enum icons instead of string icons.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields should auto-generate from title/name fields but allow manual override.
- Technical settings, homepage section targets, pin fields, and metric filters must have helper text, hints, or descriptions.
- Date/date-time UI should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Dashboard widgets should include available editorial metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/homepage-settings-spec.md`
- `docs/phase-02/dashboard-metrics-spec.md`
- `docs/phase-02/blueprints/08-taxonomy-tags-pinning-settings-media-foundation-blueprint.md`
- `docs/phase-02/blueprints/13-dashboard-metrics-blueprint.md`
- `docs/research/filament-examples-phase-02.md`
