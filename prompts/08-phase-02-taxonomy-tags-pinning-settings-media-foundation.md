# Prompt 08: Phase 02 Taxonomy, Tags, Pinning, Settings, and Media Foundation

## Goal

Implement categories, Spatie content tags, item pinning, settings, homepage sections, and media metadata foundation.

## Current state assumptions

- Prompt 07 is complete and committed.
- Spatie Tags, the Filament Spatie Tags plugin, Spatie Settings, and the Filament Spatie Settings plugin are approved for this implementation prompt. If absent, this prompt owns adding them; do not ask for package approval again.

## Docs to read

- `AGENTS.md`
- `docs/phase-02/taxonomy-tags-spec.md`
- `docs/phase-02/homepage-settings-spec.md`
- `docs/phase-02/media-embed-spec.md`
- `docs/phase-02/tooling-and-quality-gates.md`

## Blueprint and guidelines

- `docs/phase-02/blueprints/08-taxonomy-tags-pinning-settings-media-foundation-blueprint.md`
- `.ai/guidelines/taxonomy-tags.md`
- `.ai/guidelines/settings-dashboard.md`
- `.ai/guidelines/media-embeds.md`
- `.ai/guidelines/tooling-quality.md`

## Scope

- Custom hierarchical categories.
- Spatie typed content tags.
- `ContentItem` pin fields only.
- Spatie Settings foundation.
- Homepage section model.
- Media metadata fields on `ContentItem`.

## Out of scope

No admin Resource polish, import/export, public homepage/search UI, item page parser/media rendering, dashboards, or studio.

## Package/tool assumptions

Use Boost docs when available. Install only the approved Spatie Tags/Settings packages required by this prompt if they are absent; do not install unrelated packages.

## Implementation plan

1. Write failing domain tests.
2. Add migrations/models/factories.
3. Add relationships/scopes.
4. Add settings classes/migrations using the approved settings packages.
5. Add media field validation helpers where needed.

## Acceptance criteria

Schema foundation exists for Prompt 09 admin management and Prompt 10 import/export.

## Required tests

Category hierarchy, inheritance, descendant filtering, item pin scopes/order/expiration, settings defaults, tag scoping/enabled visibility, media field validation.

## Required quality gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Final report format

Report files changed, tests added, commands/results, assumptions, deferred issues, package decisions, and FilaCheck output.

## Commit behavior

Commit only after the full quality gate passes.
