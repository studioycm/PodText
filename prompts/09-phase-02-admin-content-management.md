# Prompt 09: Phase 02 Admin Content Management

## Goal

Implement admin management for Phase 02 models and fields created by Prompts 07 and 08.

## Current state assumptions

- Prompts 07 and 08 are complete and committed.
- Packages approved in Prompt 08 are installed and configured.

## Docs to read

- `AGENTS.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- all Phase 02 specs relevant to transcriptions, taxonomy, tags, settings, media, and homepage.

## Blueprint and guidelines

- `docs/phase-02/blueprints/09-admin-content-management-blueprint.md`
- `.ai/guidelines/transcriptions.md`
- `.ai/guidelines/taxonomy-tags.md`
- `.ai/guidelines/settings-dashboard.md`
- `.ai/guidelines/media-embeds.md`
- `.ai/guidelines/tooling-quality.md`

## Scope

- Transcription Resource/management.
- Category Resource.
- HomepageSection management.
- Settings page.
- ContentItem/ContentGroup admin field updates for categories, tags, pinning, featured transcription, and media metadata.

## Out of scope

No public homepage/search, item page rebuild, import/export, dashboards, or studio.

## Package/tool assumptions

Use Boost docs, Filament Blueprint, FilamentExamples MCP, FilaCheck, and FilaCheck Pro. Do not install new packages unless already approved by Prompt 08.

## Implementation plan

1. Write Resource smoke and validation tests.
2. Scaffold Resources/pages with Filament commands.
3. Implement schemas/tables/actions using split classes.
4. Add admin translations.
5. Run focused tests and FilaCheck dirty scans during iteration.

## Acceptance criteria

Admins can manage transcriptions, categories, tags, homepage sections/settings, item pins, featured transcriptions, and media metadata without exposing public-only routes.

## Required tests

Resource smoke, create/edit validation, feature transcription action, pin controls, category/tag assignment, settings management, and admin access protection.

## Required quality gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

Check FilaCheck Pro pitfalls listed in `tooling-and-quality-gates.md`.

## Final report format

Report files changed, tests added, commands/results, assumptions, deferred issues, and FilaCheck output.

## Commit behavior

Commit only after the full quality gate passes.
