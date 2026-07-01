# Prompt 15 Blueprint: Filament Security Audit

## Scope

Future audit-only prompt after Phase 02 implementation. Verify prompt state in `docs/phase-02/current-project-state.md` before starting.

## Audit Inputs

- All Phase 02 implementation commits.
- Filament Resources, Pages, Widgets, Imports, Exports, and public Livewire components.
- FilaCheck and FilaCheck Pro output.
- Pest test coverage.

## Audit Checklist

- Admin-only access for admin Resources.
- Public pages query only published and effective content.
- No raw Markdown output without `SafeMarkdownRenderer`.
- No raw iframe/embed HTML.
- HTTPS allowlist on media embeds.
- No N+1 in table/card closures.
- Relationship selects searchable/preloaded where needed.
- File uploads have accepted file types and max sizes.
- Import/export validates every relationship and formula-injection handling.
- Actions use `Filament\Actions` namespaces.
- Widgets do not poll unnecessarily.
- Resource links use Resource URL helpers.
- Translation keys for UI text.
- RTL layout markers preserved.

## Commands

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

Prompt 15 may add focused tests or documentation findings only after audit review.

## Prompt 06S Section Alignment

This alignment block preserves the implementation scope above while exposing the exact headings required by the active AI-context prompt.

## Goal

Implement only the prompt-specific objective described in this blueprint title and body.

## Dependencies

Complete prior prompts in sequence and read `AGENTS.md`, relevant specs, durable guidelines, and this blueprint before implementation.

## Models and migrations

Use the model and schema notes above. If this prompt is documentation-only, do not create migrations.

## Relationships and casts

Use the relationship, cast, and enum notes above; keep public visibility rules queryable and tested.

## Indexes and constraints

Add indexes, unique constraints, and foreign keys only for fields created in this prompt and queries described above.

## Filament Resources / Pages / Relation Managers / Actions

Use Filament 5 Resources, Pages, Actions, Importers, Exporters, or Widgets only where this prompt scope requires them.

## Public UI / Livewire / Blade where relevant

Use public Filament Pages, class-based Livewire, Blade components, and local Alpine only where this prompt scope requires public UI.

## Forms / tables / filters / actions

Use full Filament component namespaces, searchable relationship selects, useful filters, indicators, and Resource URL helpers.

## Import/export where relevant

Use native Filament import/export only for schema fields created by earlier prompts; never build custom CSV controllers.

## Settings/widgets where relevant

Use approved Spatie Settings for global options and simple editorial widgets only where this prompt scope requires them.

## Security

Preserve admin-only access, public draft hiding, safe Markdown rendering, HTTPS allowlisted embeds, and import formula protection.

## Tests

Add or update focused Pest tests for the behavior this prompt implements; documentation-only prompts run documentation checks only.

## Quality gate

Implementation prompts run `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/filacheck`, and `npm run build`; documentation-only prompts run their requested checks.

## Out of scope

Do not implement work assigned to later prompts, install unrelated packages, run migrations in planning tasks, or add speculative infrastructure.
