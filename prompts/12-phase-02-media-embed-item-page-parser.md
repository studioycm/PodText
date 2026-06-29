# Prompt 12: Phase 02 Media Embed, Item Page, and Parser

## Goal

Implement the public item page, safe media rendering, transcription tabs, and parse-only transcript viewer.

## Current state assumptions

- Prompts 07 through 11 are complete and committed.

## Docs to read

- `AGENTS.md`
- `docs/phase-02/public-panel-ux-spec.md`
- `docs/phase-02/media-embed-spec.md`
- `docs/phase-02/transcript-viewer-and-studio-future-plan.md`
- `docs/phase-02/tooling-and-quality-gates.md`

## Blueprint and guidelines

- `docs/phase-02/blueprints/12-public-item-page-media-parser-blueprint.md`
- `.ai/guidelines/public-panel.md`
- `.ai/guidelines/media-embeds.md`
- `.ai/guidelines/viewer-studio.md`
- `.ai/guidelines/tooling-quality.md`

## Scope

- One-item public page.
- Media player/source component.
- Effective/main transcription default.
- Other published transcriptions as tabs/selector.
- Timestamp/speaker parser.
- Show/hide timestamp/speaker preferences.
- Reading time, duration, transcript length, categories/tags, author links, copy/share.

## Out of scope

No player sync, no studio, no autosave, no analytics, no metadata extraction automation.

## Package/tool assumptions

Use Boost docs when available and FilamentExamples custom page/Alpine examples.

## Implementation plan

1. Write failing public item page and parser tests.
2. Implement parser class.
3. Update page resolution rules.
4. Update media component.
5. Add viewer Blade/Alpine local controls.
6. Add translations and RTL checks.

## Acceptance criteria

Public item pages render only public items with effective/main transcripts and safe media/transcript output.

## Required tests

Embed allow/reject/fallback, draft hiding, effective transcript default, published tabs, parser formats, XSS safety, metadata display, RTL markers.

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
