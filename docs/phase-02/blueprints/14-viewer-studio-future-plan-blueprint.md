# Prompt 14 Blueprint: Viewer and Studio Future Plan

## Scope

Documentation/planning only unless a later prompt explicitly changes scope. Do not implement studio code.

## Plan Sections To Produce

- Future synced public viewer.
- Player capability matrix: external iframe vs direct audio URL.
- Transcript timing data requirements.
- Studio editor layout.
- Keyboard shortcuts.
- Speaker quick insert.
- Timestamp injection.
- Autosave prerequisites.
- Failure/recovery states.
- Future permission/ability names.
- Testing strategy for a later implementation phase.

## Current Boundary

Prompt 12 already owns parse-only viewer behavior. Prompt 14 must not be the first place parser implementation appears.

## Verification

Run documentation checks and `git diff --check`. Run tests only if code changed, which should not happen in this prompt.

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
