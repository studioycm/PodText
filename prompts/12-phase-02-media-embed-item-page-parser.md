# Prompt 12: Phase 02 Media Embed, Item Page, and Parser

## Goal

Implement the public item page, safe media rendering, transcription tabs, and parse-only transcript viewer.

## Current state assumptions

- This prompt depends on the post-Prompt-11 public frontend state recorded in `docs/phase-02/current-project-state.md`, including Prompt 11R custom Livewire + Blade public listing UI and any completed Prompt 11B contributor discovery work.
- For current prompt progress, read `docs/phase-02/current-project-state.md`.
- Preserve custom public content-item cards, search/filter state, homepage sections, category/tag routes, content group routes, contributor links, and public card/link conventions that already exist before Prompt 12 starts.
- Prompt 12 may link to existing contributor/author pages where needed, but must not implement contributor discovery if it is not already present in the current state.

## Preflight and carry-forward rules

- Run git status/log preflight before implementation and stop on unexpected app-code dirt.
- Confirm the prerequisite public homepage/search capabilities are present before starting; do not backfill Prompt 11 scope in this prompt.
- Preserve Prompt 10 native import/export behavior, Prompt 11 public listing behavior, Prompt 11R custom public card/search/homepage-section rendering, Prompt 11B contributor routes/sections if present, and the `ContentItem` public result unit.
- Prompt 12 owns the public item page, media rendering, transcript tabs/selector, parser, and viewer controls.
- Do not implement admin relationship UX, homepage/search rewrites, contributor directory/discovery work, player sync, studio, dashboards, analytics, metadata extraction automation, or Prompt 13+ behavior.
- After success, update `docs/phase-02/current-project-state.md` before final commit. Patch other docs only when stable requirements changed.

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

## Blueprint contract

The blueprint file named above is the detailed implementation contract for this prompt.

Before changing code:

1. Read the entire blueprint.
2. Summarize the blueprint sections that apply to this prompt.
3. Compare the blueprint against the current repository state.
4. If the blueprint conflicts with the active prompt, Phase 02 specs, `AGENTS.md`, or current code, stop and report the conflict before implementing.
5. If the prompt body is shorter than the blueprint, follow the blueprint details.
6. Do not omit blueprint fields, relationships, constraints, Filament components, tests, or quality checks unless the blueprint marks them optional or the current code makes them impossible.
7. In the final report, include a "Blueprint completion checklist" with:
   - implemented;
   - already existed;
   - deferred by blueprint;
   - not applicable;
   - blocked.

The Prompt 12 blueprint is the authority for the parser class, public item page resolution rules, media component, viewer controls, and tests.

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

Embed allow/reject/fallback, draft hiding, effective transcript default, published tabs, XSS safety, metadata display, RTL markers, and these explicit parser/viewer cases:

- parse `[00:01:23] Speaker: Transcript text`;
- parse `[00:01:23] Speaker:\nTranscript text...`;
- fallback to safe Markdown if parsing fails;
- render timestamp anchors;
- show/hide timestamp preference;
- show/hide speaker preference;
- confirm no player sync is implemented;
- timestamp displays are direction-safe in Hebrew RTL layout.

Prompt 14 remains only future sync/studio planning.

## Required quality gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Final report format

Report files changed, tests added, commands/results, assumptions, deferred issues, FilaCheck output, and the Blueprint completion checklist.

## Commit behavior

Commit only after the full quality gate passes.
