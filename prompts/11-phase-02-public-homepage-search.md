# Prompt 11: Phase 02 Public Homepage and Search

## Goal

Implement item-based public homepage/search/category/tag landing pages.

## Current state assumptions

- Prompts 07 through 10 are complete and committed.
- Effective/main transcription, categories, tags, pinning, settings, media fields, and import/export are ready.

## Docs to read

- `AGENTS.md`
- `docs/phase-02/public-panel-ux-spec.md`
- `docs/phase-02/search-and-filters-spec.md`
- `docs/phase-02/tooling-and-quality-gates.md`

## Blueprint and guidelines

- `docs/phase-02/blueprints/11-public-homepage-search-blueprint.md`
- `.ai/guidelines/public-panel.md`
- `.ai/guidelines/search-filters.md`
- `.ai/guidelines/taxonomy-tags.md`
- `.ai/guidelines/tooling-quality.md`

## Scope

- Public homepage item feed.
- Search page/listing component.
- Category landing pages.
- Tag landing pages.
- Filters, sort, pagination, URL state, empty states, result count, responsive filter UI.

## Out of scope

No item page parser/media overhaul, dashboards, analytics/search logging, or studio.

## Package/tool assumptions

Use Boost docs when available. Use FilamentExamples MCP public-table/filter/card examples before coding.

## Implementation plan

1. Write failing public Livewire/feature tests.
2. Build base public item query.
3. Implement Filament table/card result component.
4. Add homepage/category/tag pages.
5. Add translations and RTL-safe Blade components.

## Acceptance criteria

Public listings show `ContentItem` records only and obey effective/main transcription rules.

## Required tests

Guest access, draft/no-transcription hidden, search fields, filters, sort, pinned ordering, category/tag pages, disabled tags, URL state, RTL markers, empty states.

## Required quality gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

Check FilaCheck Pro table/filter/card concerns.

## Final report format

Report files changed, tests added, commands/results, assumptions, deferred issues, and FilaCheck output.

## Commit behavior

Commit only after the full quality gate passes.
