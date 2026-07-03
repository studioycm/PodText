# Prompt 12 Readiness Sync After 11R / 11A / 11B

## Goal

Markdown-only sync so Prompt 12 starts from the correct post-11R/11A/11B state and does not regress public frontend or admin/contributor additions.

Do not implement application features.

## Scope

Patch only Markdown files.

Allowed:
- `docs/phase-02/current-project-state.md`
- `prompts/12-phase-02-media-embed-item-page-parser.md`
- `docs/phase-02/blueprints/12-public-item-page-media-parser-blueprint.md`
- public panel/media/viewer specs if stable requirements changed
- `.ai/guidelines/public-panel.md`
- `.ai/guidelines/media-embeds.md`
- `.ai/guidelines/viewer-studio.md`
- `.ai/guidelines/tooling-quality.md`

Out of scope:
- no PHP/Blade/tests/config/package edits;
- no Prompt 12 implementation;
- no migrations;
- no FilaCheck/build/test unless prompt specifically requires more.

## Read first

- `AGENTS.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/ai-development-lessons.md`
- `prompts/12-phase-02-media-embed-item-page-parser.md`
- `docs/phase-02/blueprints/12-public-item-page-media-parser-blueprint.md`
- docs/specs/guidelines related to public panel, media embeds, viewer/studio.

## Preflight

Run:
- `git status --short --branch`
- `git log --oneline --decorate -12`

Confirm:
- Prompt 11R is complete.
- Prompt 11A is complete or intentionally deferred.
- Prompt 11B is complete or intentionally deferred.
- Prompt 12 has not started.
- Working tree is clean.

If unexpected app-code changes exist, stop.

## Required updates

### 1. Current state

Update `docs/phase-02/current-project-state.md`:
- record completed 11R/11A/11B commits if present;
- record any intentionally deferred pre-12 prompt;
- keep Prompt 12 next/not started;
- record that custom public homepage/search now uses Livewire + Blade, not Filament Table, if 11R ran;
- record that Prompt 12 must preserve public card components/routes/contributor links.

### 2. Prompt 12

Patch `prompts/12-phase-02-media-embed-item-page-parser.md` so it states:
- Prompt 12 depends on the post-Prompt-11 public frontend state in `current-project-state.md`;
- Prompt 12 must preserve custom public content-item cards/search/homepage sections;
- Prompt 12 may link to contributor pages/authors where needed, but must not implement contributor discovery if it is not already done;
- Prompt 12 owns item page, media rendering, transcript tabs, parser, viewer controls;
- Prompt 12 does not own admin relationship UX or homepage/contributor directory.

### 3. Prompt 12 blueprint

Patch `docs/phase-02/blueprints/12-public-item-page-media-parser-blueprint.md`:
- keep item-page/media/parser implementation details;
- add guardrail that item page should reuse existing public card/link conventions where appropriate;
- preserve contributor/author links if available;
- do not modify public homepage/search implementation except for shared components required by item page.

### 4. Specs/guidelines

Patch only stable facts:
- if contributors were implemented, mention contributor/author links in public panel spec;
- if custom card components were created, mention reuse by item page where appropriate;
- if 11A admin relationship UX was implemented, no Prompt 12 spec change is usually needed.

## Validation

Run:
- `git diff --check`
- `git status --short`

Do not run implementation gates.

## Commit

If only Markdown files changed and validation passes, commit:

`docs: prepare prompt twelve after public discovery work`

## Final report

Include:
- files patched;
- state recorded;
- what Prompt 12 must preserve;
- validation results;
- commit hash if committed;
- current git status;
- confirm Prompt 12 was not started.

End with exactly:

“Prompt 12 readiness sync is complete. Prompt 12 has not been started.”
