# Prompt 07: Phase 02 Transcriptions Model Revision

## Goal

Implement the child `Transcription` domain model and effective/main transcription rules.

## Current state assumptions

- Prompt 06R planning pack has been reviewed.
- Current transcripts still live on `content_items.transcript_markdown`.
- No taxonomy, tags, pinning, homepage search, dashboards, or studio work is implemented in this prompt.

## Docs to read

- `AGENTS.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/transcriptions-model-spec.md`
- `docs/phase-02/tooling-and-quality-gates.md`

## Blueprint and guidelines

- `docs/phase-02/blueprints/07-transcriptions-model-revision-blueprint.md`
- `.ai/guidelines/transcriptions.md`
- `.ai/guidelines/tooling-quality.md`

## Scope

- Create `Transcription` model/migration/factory.
- Add relationships and effective/main transcription resolution.
- Backfill existing item transcripts.
- Update public visibility rules to require effective/main published transcription.
- Update tests for the domain move.

## Out of scope

No categories, tags, pinning, settings, media metadata foundation, public search redesign, dashboards, or studio.

## Package/tool assumptions

Use Laravel Boost MCP when available. Use FilamentExamples MCP only if Filament code becomes necessary. Do not install packages.

## Implementation plan

1. Write failing tests for relationships, casts, backfill, effective/main rules, public visibility, and XSS rendering.
2. Create migration/model/factory.
3. Implement relationships and scopes.
4. Backfill legacy transcript data.
5. Update public item/group queries and rendering to use effective/main transcription.
6. Remove new writes to legacy item transcript field while leaving cleanup/drop for a later phase.

## Acceptance criteria

- `Transcription` records own canonical Markdown transcript content.
- Public listings exclude items without effective/main published transcription.
- Featured transcription is validated to the same item and published to be effective.

## Required tests

See the blueprint test list and add/adjust Pest tests accordingly.

## Required quality gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Final report format

Report files changed, tests added, commands/results, assumptions, deferred issues, and FilaCheck output.

## Commit behavior

Commit only after the full quality gate passes.
