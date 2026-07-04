# Codex Prompt B v2 — Public Front v2 Step 1: JSON Settings Architecture

Work in the current PhpStorm / Codex App project repository only.

This is the first Public Front v2 implementation prompt.

Do not run the Card Template Builder step.
Do not run the Public Display Sections / Loopers step.
Do not run Public Forms, About, Podcasts, Menu/Header, Contributors, Seeders, Prompt 13, Prompt 14, or Prompt 15.
Do not implement the transcription publication policy; it is deferred/reserved unless explicitly approved later.
Do not push to GitHub unless explicitly asked.
Do not use worktrees.
Do not launch parallel agents.
Do not run `vendor/bin/filacheck --fix` unless explicitly approved.

## Goal

Implement the Public Front v2 Step 1 JSON Settings Architecture foundation.

This step creates the safe JSON settings/configuration infrastructure that later steps will use for:

- card templates;
- public display sections / loopers;
- latest/search UX;
- public forms;
- about/team content;
- menu/header config;
- podcasts/group page config;
- contributor/top-transcriber config;
- seed/default settings.

This step must not implement those features yet.

## Critical output required for external reviewer agent

At the end of Step 1, create:

```text
docs/phase-02/public-front-v2-step1-json-settings-handoff.md
```

This file is for the external reviewer agent, ChatGPT/Yoni, before future implementation prompts are generated.

The handoff must explain what was actually implemented and how future prompts should depend on it.

Required sections:

```md
# Public Front v2 Step 1 JSON Settings Handoff

## Purpose

## What was implemented

## Final namespaces and classes

## Final public API for future prompts

## Settings groups and keys

## JSON structure conventions

## Defaults and fallback behavior

## Validation and sanitization behavior

## Invalid config reporting behavior

## Existing settings/components changed

## Sample JSON payloads

## Sample PHP usage

## Blueprint deviations

## Impact on later prompts

## Prompt-by-prompt adaptation notes

## Open issues / follow-up decisions

## Tests and quality gate summary
```

The `Impact on later prompts` section must cover:

- Step 3 Card Template Builder;
- Step 4 Public Display Sections and Loopers;
- Step 5 Latest and Search UX;
- Step 6 Public Forms and Submissions;
- Step 7 About Page Content and Team Builder;
- Step 8 Podcasts and Groups UX;
- Step 9 Public Menu and Header;
- Step 10 Contributors and Top Transcribers UX;
- Step 11 Seeders, Demo Data, Assets, and Cleanup;
- Step 2 / Reserved Transcription Publication Policy;
- Prompt 13 Dashboard Metrics.

For each future step, state:

- whether the prompt can use the JSON settings architecture as planned;
- exact class/method/config key it should use;
- any implementation nuance that changes the original blueprint;
- any likely prompt wording changes needed.

Do not skip this handoff. If implementation succeeds but this file is missing, the task is incomplete.

## Planned future prompts

After Step 1 is complete and reviewed, future implementation prompts should be generated in this order, adapted to the final Step 1 implementation:

1. Public Front v2 Step 3: Card Template Builder Foundation.
2. Public Front v2 Step 4: Public Display Sections and Loopers.
3. Public Front v2 Step 5: Latest and Search UX.
4. Public Front v2 Step 6: Public Forms and Submissions.
5. Public Front v2 Step 7: About Page Content and Team Builder.
6. Public Front v2 Step 8: Podcasts and Groups UX.
7. Public Front v2 Step 9: Public Menu and Header.
8. Public Front v2 Step 10: Contributors and Top Transcribers UX.
9. Public Front v2 Step 11: Seeders, Demo Data, Assets, and Cleanup.
10. Public Front v2 Step 2 / Reserved: Transcription Publication Policy, only if explicitly promoted from deferred status and always as an isolated prompt.
11. Public Front v2 Step 12: Prompt 13 Dashboard Metrics readiness / next decision.

Do not generate all of those prompts during this task.
Only create the handoff report so the external reviewer can decide whether small adjustments are needed before generating Step 3+ prompts.

## Current state assumptions

Verify from `docs/phase-02/current-project-state.md`:

- Prompt 12 is complete.
- Prompt 13 has not started.
- Public Front v2 research/blueprints exist.
- Public Front v2 execution plan corrections are committed.
- Public transcription policy is deferred/reserved.
- The PodText logo already exists at `public/images/podtext-logo.jpg`; preserve it.

If current state contradicts this, stop and report.

## Read first

- `AGENTS.md`
- `.ai/guidelines/tooling-quality.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/ai-development-lessons.md`
- `docs/phase-02/public-front-v2-final-report.md`
- `docs/phase-02/public-front-v2-agent-usage-index.md`
- `docs/phase-02/public-front-v2-execution-plan.md`
- `docs/research/public-front-v2/01-json-settings-architecture.md`
- `docs/phase-02/blueprints/public-front-v2/01-json-settings-architecture-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/blueprint-results/01-json-settings-architecture-plan.md`
- `docs/phase-02/blueprints/public-front-v2/12-implementation-sequence-blueprint.md`
- current `App\Settings\PublicContentSettings`
- current admin settings page for public content
- existing public front support classes, especially `App\Support\PublicContent\PublicContentCardOptions`
- tests around public settings and public homepage/search

## Preflight

Run:

```bash
git status --short --branch
git log --oneline --decorate -15
```

Confirm:

- working tree is clean;
- Prompt 12 is complete;
- Prompt 13 has not started;
- Public Front v2 execution plan corrections are committed;
- no unexpected app-code changes exist.

Use Laravel Boost:

- `application_info`
- `database_schema`
- `search_docs`

Use Boost `search_docs` before changing Spatie Settings, casts, validation, Filament SettingsPage behavior, or testing APIs.

Use FilamentExamples MCP only if helpful for current Step 1 patterns. Record whether it returned snippets or source.

If baseline quality gate is required by the active docs, run it before implementation. Otherwise, at least run the final implementation gate after changes.

## Blueprint contract

Treat:

```text
docs/phase-02/blueprints/public-front-v2/01-json-settings-architecture-blueprint.md
```

as the detailed implementation contract.

The prompt defines scope and boundaries.
The blueprint defines implementation details, tests, security rules, and final checklist.

If the prompt, blueprint, execution plan, current state, installed docs, or code conflict, stop and report before changing code.

## Scope

Implement only the JSON settings architecture foundation.

### Required implementation themes

Implement a reusable support namespace, likely:

```text
App\Support\PublicFront
```

or an equivalent naming already recommended by the blueprint.

It should provide typed, testable helpers for:

- reading JSON settings with defaults;
- merging defaults with stored arrays;
- validating allowed keys;
- rejecting/ignoring unsafe values;
- reporting invalid config in a safe value object or result object;
- normalizing config for rendering;
- making future feature registries easy to add.

### Core requirements

Implement a safe foundation for JSON-first configuration.

The system must support future config categories, but this step should only implement generic infrastructure and minimal integration.

Expected pieces may include, subject to blueprint/current code:

- `PublicFrontConfigReader`
- `PublicFrontConfigValidator`
- `PublicFrontRegistry`
- `InvalidPublicFrontConfig` or similar value object
- safe default arrays for public-front settings
- typed accessors for known config groups
- tests for invalid/fallback behavior

Use clear naming and keep the API small.

### PublicContentSettings integration

Extend or prepare `PublicContentSettings` for array/JSON public-front config if the blueprint requires it.

Do not move all existing flat settings immediately unless the blueprint says to.

Prefer backward-compatible behavior:

- existing flat settings still work;
- new JSON config values have defaults;
- missing rows do not crash public pages;
- invalid values are ignored/fallbacked.

### Security rules

Reject or ignore unsafe values in JSON config:

- raw Tailwind class strings;
- raw CSS;
- raw SQL;
- arbitrary PHP class names;
- arbitrary Blade view paths;
- iframe HTML;
- unsafe HTML;
- JavaScript URLs;
- unknown component/renderer names.

Semantic config values only.

Examples of allowed semantic values:

- `compact`
- `comfortable`
- `cards`
- `rows`
- `small`
- `medium`
- `large`
- known route keys
- known card family keys
- known field/block type keys

### Out of scope

Do not implement:

- card template builder UI;
- card template rendering changes beyond minimal compatibility;
- `homepage_sections` JSON columns;
- public display loopers;
- Latest/search UX rewrite;
- public forms/submissions;
- about/team builder;
- podcasts/groups page changes;
- menu/header manager;
- contributor/top-transcriber refinements;
- seeders cleanup;
- transcription publication policy;
- dashboard metrics;
- public item page/media/parser changes;
- new settings-only models.

Do not create these models:

- `CardTemplate`
- `PublicMenu`
- `PublicMenuItem`
- `AboutPage`
- `AboutPageBlock`
- `TeamProfile`
- `PublicFormDefinition`
- `PublicDisplaySection`
- `PublicLooper`

The only model exception approved by the plan is `PublicFormSubmission`, but that belongs to a later public forms/submissions step, not this step.

## Tests

Create or update focused tests, likely:

```text
tests/Feature/PublicFrontJsonSettingsArchitectureTest.php
```

Required coverage:

- defaults load when settings rows are missing;
- nested config arrays merge with defaults;
- unknown top-level keys are ignored or reported safely;
- unknown nested keys are ignored or reported safely;
- unsafe class-like values are rejected/ignored;
- unsafe CSS/Tailwind-looking values are rejected/ignored where applicable;
- unsafe Blade path-like values are rejected/ignored;
- unsafe URL values such as `javascript:` are rejected/ignored when URL config validation exists;
- validator returns a safe invalid-config report/value object;
- existing public card settings still work after introducing the new architecture;
- existing public homepage/search tests still pass;
- no settings-only models are introduced.

Prefer behavior tests over class-existence tests.

## Quality gate

Run focused tests first:

```bash
php artisan test --filter=PublicFrontJsonSettingsArchitectureTest
```

Then, if PHP files were modified:

```bash
vendor/bin/pint --dirty --format agent
```

Then run the full gate:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

Do not run `vendor/bin/filacheck --fix`.

## Documentation update after success

Before final commit, update:

- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step1-json-settings-handoff.md`

Record in current state:

- Public Front v2 Step 1 JSON Settings Architecture complete;
- commit hash placeholder if committing after update;
- next step is Step 3 Card Template Builder, because Step 2 Transcription Publication Policy is deferred/reserved;
- no Prompt 13 work started;
- no transcription publication policy implemented;
- handoff report exists for ChatGPT/Yoni review.

Patch other docs only if stable scope/requirements changed.

## Commit behavior

If and only if all quality gates pass, commit with:

```text
feat: add public front json settings architecture
```

Do not push unless explicitly asked.

## Final report

Include:

- preflight state;
- Boost tools used;
- FilamentExamples MCP usage, if any;
- files changed;
- support classes/value objects added;
- settings integration details;
- backward compatibility notes;
- security/validation behavior;
- tests added/updated;
- commands run and results;
- FilaCheck summary;
- blueprint completion checklist:
  - implemented;
  - already existed;
  - deferred by blueprint/spec;
  - not applicable;
  - blocked;
- handoff report path;
- short summary for external reviewer agent:
  - final JSON settings architecture API;
  - major deviations from blueprint;
  - prompt wording implications for Step 3+;
- commit hash if committed;
- current git status;
- confirm Step 2 transcription policy is deferred/reserved;
- confirm next implementation step is Card Template Builder after ChatGPT/Yoni review;
- confirm Prompt 13 has not started.

End with exactly:

```text
Public Front v2 Step 1 JSON settings architecture is complete. Prompt 13 has not been started.
```
