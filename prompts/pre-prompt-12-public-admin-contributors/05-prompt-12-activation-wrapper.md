# Prompt 12 Activation Wrapper

## Purpose

Use this wrapper after 11R, 11A, 11B, and the Prompt 12 readiness sync are complete and committed.

It runs the existing Prompt 12 while preserving the pre-Prompt-12 work.

## Copy/paste prompt

Work in the current PhpStorm project repository only.

Run Prompt 12:

`prompts/12-phase-02-media-embed-item-page-parser.md`

Read first:
- `AGENTS.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/ai-development-lessons.md`
- `prompts/12-phase-02-media-embed-item-page-parser.md`
- `docs/phase-02/blueprints/12-public-item-page-media-parser-blueprint.md`
- `docs/phase-02/public-panel-ux-spec.md`
- `docs/phase-02/media-embed-spec.md`
- `docs/phase-02/transcript-viewer-and-studio-future-plan.md`
- `.ai/guidelines/public-panel.md`
- `.ai/guidelines/media-embeds.md`
- `.ai/guidelines/viewer-studio.md`
- `.ai/guidelines/tooling-quality.md`

Preflight:
- `git status --short --branch`
- `git log --oneline --decorate -12`
- confirm Prompt 12 is next and not started in `current-project-state.md`;
- confirm the working tree is clean;
- confirm completed pre-12 work is committed or intentionally deferred.

Use Laravel Boost:
- `application_info`;
- `database_schema`;
- `search_docs` for media rendering, Livewire, Alpine, Filament public pages, and tests before code changes.

Use FilamentExamples MCP if useful for custom public pages, Alpine local controls, or media/viewer patterns.

Rules:
- Preserve custom public homepage/search/components from 11R.
- Preserve admin relationship UX from 11A.
- Preserve contributor/transcriber discovery from 11B if implemented.
- Do not reintroduce Filament Table as the public homepage/search renderer.
- Do not modify import/export behavior.
- Do not implement player sync, transcription studio, autosave, dashboard widgets, analytics, or metadata extraction automation.

Prompt 12 scope:
- one public `ContentItem` page;
- safe media player/source component;
- effective/main transcription default;
- other published transcriptions as tabs/selector;
- timestamp/speaker parser;
- show/hide timestamps and speakers;
- timestamp anchors;
- reading time, duration, transcript length;
- categories/tags;
- author/contributor links;
- copy/share actions;
- safe Markdown rendering;
- RTL/Hebrew behavior.

Tests:
- write failing public item page and parser tests first;
- preserve existing public homepage/search/contributor tests;
- test safe embed allow/reject/fallback;
- test draft hiding;
- test effective transcript default;
- test published tabs only;
- test parser formats;
- test fallback safe Markdown rendering;
- test timestamp anchors;
- test show/hide timestamp/speaker preferences;
- test no player sync is implemented;
- test RTL marker/direction-safe timestamps.

Quality gate:
- focused Prompt 12 tests first;
- `vendor/bin/pint --dirty --format agent` if PHP changed;
- full gate:
  - `php artisan test`
  - `vendor/bin/pint --test`
  - `vendor/bin/filacheck`
  - `npm run build`

Do not run `vendor/bin/filacheck --fix`.

Post-success docs:
- update `docs/phase-02/current-project-state.md`;
- mark Prompt 12 complete;
- mark Prompt 13 next/not started;
- patch other docs only if stable requirements changed.

Commit if full gate passes:

`feat: add public item page media and transcript parser`

Final report:
- files changed;
- media component behavior;
- parser behavior;
- transcription tab behavior;
- public visibility behavior;
- tests added/updated;
- commands/results;
- FilaCheck summary;
- Blueprint completion checklist;
- current git status;
- commit hash if committed;
- confirm Prompt 13 is next and Prompt 13 was not started.

End with exactly:

“Prompt 12 public item page/media/parser implementation is complete. Prompt 13 has not been started.”
