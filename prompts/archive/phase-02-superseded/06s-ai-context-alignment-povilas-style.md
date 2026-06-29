# Prompt 06S — AI Context Alignment After Repository Cleanup

You are working inside the current PhpStorm project repository for PodText.

This is a **documentation / AI-context / planning cleanup task only**.

Do **not** implement application features.
Do **not** run Phase 02 implementation prompts yet.
Do **not** install packages unless an active prompt explicitly says to and the user has approved it.
Do **not** run migrations.
Do **not** edit application code except to revert accidental tool side effects.

## Why this prompt exists

A previous cleanup run archived old Bootstrap Slice 0 / Phase 1 materials, replaced `AGENTS.md` with an evergreen style, renamed active `.ai/guidelines` files to durable names, and kept only Phase 02 prompts `06` through `15` active.

Before running Phase 02 implementation, verify and fix the AI instruction hierarchy according to the Laravel Daily / Filament Examples / AI Coding Daily style:

- evergreen rules belong in `AGENTS.md`;
- durable code/package rules belong in `.ai/guidelines`;
- phase/product specifics belong in `docs/phase-02`, blueprints, and prompts;
- completed or superseded plans belong in archive;
- implementation prompts should be narrow and testable;
- agents must not act from stale historical files.

## External guidance to apply

Use these principles:

1. Povilas Korop / Laravel Daily style:
   - keep reusable AI coding rules as project guidelines;
   - create custom guidelines under `.ai/guidelines`;
   - run Boost so custom guidelines are recognized when safe;
   - keep project description, user stories/specs, database plans, and phase/task plans in `docs/`, not in a global agent file;
   - review planning docs before implementation.

2. Filament Examples style:
   - keep Filament-specific AI rules in a guideline file such as `.ai/guidelines/filament.md` or durable equivalent;
   - enforce Filament smoke tests;
   - do not generate View pages/Infolists unless explicitly asked;
   - use Resource `getUrl()` instead of hard-coded panel routes;
   - use `Livewire::test(...)`;
   - use Enum interfaces for labels/colors/icons where applicable;
   - use `Heroicon` enums;
   - avoid deprecated Filament v3/v4 APIs;
   - use proper non-static `$view` property on Filament v4/v5 Pages.

3. OpenAI Codex / AGENTS.md style:
   - `AGENTS.md` is a concise README for agents;
   - include setup, commands, architecture, testing, constraints, and safety;
   - do not put one-time phase scope or huge generated specs in it;
   - reference task-specific docs instead of duplicating them.

## Current known state from the previous cleanup report

The previous cleanup reported:

- old Bootstrap Slice 0 root docs, Bootstrap/Phase 1 prompts, superseded Phase 02 prompts, and old `bootstrap-slice-0` guideline were archived;
- `AGENTS.md` was replaced with an evergreen style;
- root `docs/` now only has `README.md`;
- active planning is under `docs/phase-02/`, `docs/research/`, and `docs/planning/`;
- active prompts are `06` through `15` plus `prompts/README.md`;
- active guidelines are durable names: `transcriptions`, `public-panel`, `search-filters`, `taxonomy-tags`, `media-embeds`, `import-export`, `settings-dashboard`, `viewer-studio`, and `tooling-quality`;
- Laravel Boost is installed but MCP calls failed with `Transport closed`;
- Filament Blueprint guidance was available;
- FilamentExamples MCP exposed `search_examples`, returning source-level snippets and file paths, but no separate fetch/read/detail tool;
- FilaCheck and FilaCheck Pro were verified and `vendor/bin/filacheck --detailed` reported `issues: 0`;
- FilaCheck caused app/test diff side effects during that run, which were reverted;
- pre-existing tooling/dependency changes such as `CLAUDE.md`, `boost.json`, `composer.json`, `composer.lock`, and skill directories were intentionally not touched.

Verify all of this from the actual repository. Do not rely only on the report.

## Non-negotiable rules

- Work sequentially.
- Do not use worktrees.
- Do not create or push a remote.
- Do not modify app implementation files.
- Do not delete history; archive superseded files instead.
- Do not write secrets, tokens, license keys, Composer auth, MCP headers, `.env` values, or local machine paths into tracked files.
- Do not run `vendor/bin/filacheck --fix`.
- If FilaCheck or another command modifies app files during this docs-only task, revert those app changes immediately and report it.
- Leave changes uncommitted for human review unless the user explicitly asks for a commit.

## Step 1 — Inspect actual repository state

Run/inspect:

```bash
git status --short --branch
git log --oneline --decorate -10
composer show laravel/boost filament/filament filament/blueprint livewire/livewire laraveldaily/filacheck
php artisan about
php artisan route:list
```

Inspect:

- `AGENTS.md`;
- `.ai/guidelines/`;
- `docs/README.md`;
- `docs/archive/`;
- `docs/phase-02/`;
- `docs/phase-02/blueprints/`;
- `docs/research/filament-examples-phase-02.md`;
- `docs/planning/`;
- `prompts/README.md`;
- active prompts in `prompts/`;
- archived prompts.

Record findings in a short audit section inside:

```text
docs/phase-02/current-project-state.md
```

or create it if missing.

## Step 2 — Validate `AGENTS.md`

`AGENTS.md` must be evergreen.

It must contain:

- project stack;
- instruction priority;
- docs/guidelines/prompts lifecycle;
- secret safety;
- tooling expectations for Boost, Blueprint, FilaCheck, and FilamentExamples MCP;
- stable domain terminology;
- high-level architecture boundaries;
- security rules;
- Laravel/Filament conventions;
- testing and quality gates;
- final report expectations.

It must **not** contain:

- “Current objective”;
- “Bootstrap Slice 0” as an active instruction;
- “Phase 02” as active implementation scope;
- one-time feature exclusions;
- old “do not add categories/tags/timestamp parsing/provider metadata” rules;
- detailed phase blueprints;
- stale root docs as required reading;
- instructions to run `vendor/bin/filacheck --fix` automatically.

If `AGENTS.md` still has stale one-time instructions, replace it with a concise evergreen version.

If `AGENTS.md` contains a generated `<laravel-boost-guidelines>` block, ensure it no longer includes archived `.ai/bootstrap-slice-0` rules or stale phase-named rules. If Boost will re-inject those rules, fix the active `.ai/guidelines` set first and document the need to regenerate Boost context.

## Step 3 — Validate `.ai/guidelines`

Active `.ai/guidelines` files should be durable and not one-time phase prompts.

Required active guideline files or equivalents:

```text
.ai/guidelines/transcriptions.md
.ai/guidelines/public-panel.md
.ai/guidelines/search-filters.md
.ai/guidelines/taxonomy-tags.md
.ai/guidelines/media-embeds.md
.ai/guidelines/import-export.md
.ai/guidelines/settings-dashboard.md
.ai/guidelines/viewer-studio.md
.ai/guidelines/tooling-quality.md
```

Guideline names may differ if clearly documented in `docs/README.md`, but they must be durable and active.

Each active guideline should use this structure:

```md
# <Guideline Name>

## Purpose
## Preferred architecture
## Do
## Do not
## Testing rules
## Security rules
## FilaCheck / FilaCheck Pro notes
## Related active docs
```

Patch any too-thin guideline.

Specifically ensure:

- `taxonomy-tags.md` says categories are custom hierarchical records, while tags use Spatie Laravel Tags + Filament Spatie Tags plugin when the implementation prompt reaches that work.
- `taxonomy-tags.md` says Spatie tag package usage is approved for Phase 02 implementation and should not ask for package approval again.
- `taxonomy-tags.md` forbids duplicate custom tag pivots when using Spatie taggables.
- `media-embeds.md` says media field foundation occurs before import/export.
- `viewer-studio.md` says parse-only viewer belongs to public item page implementation; future sync/studio remains future planning.
- `tooling-quality.md` says FilaCheck is required but `--fix` is not allowed without explicit approval.

Archive obsolete `.ai/guidelines/bootstrap-slice-0.md`, old `.ai/phase-02-*` names, and any phase-only guideline that is no longer active.

## Step 4 — Validate active docs

Active docs must live under:

```text
docs/phase-02/
docs/phase-02/blueprints/
docs/research/
docs/planning/
```

Archived docs must live under:

```text
docs/archive/
```

Patch `docs/README.md` and `docs/archive/README.md` so future agents understand which docs are active.

Required active docs:

```text
docs/phase-02/current-project-state.md
docs/phase-02/tooling-and-quality-gates.md
docs/phase-02/feature-map.md
docs/phase-02/answers-coverage-matrix.md
docs/phase-02/transcriptions-model-spec.md
docs/phase-02/taxonomy-tags-spec.md
docs/phase-02/search-and-filters-spec.md
docs/phase-02/media-embed-spec.md
docs/phase-02/public-panel-ux-spec.md
docs/phase-02/homepage-settings-spec.md
docs/phase-02/import-export-revision-spec.md
docs/phase-02/dashboard-metrics-spec.md
docs/phase-02/transcript-viewer-and-studio-future-plan.md
docs/research/filament-examples-phase-02.md
```

If any are missing, create or patch them from existing active Phase 02 material.

Patch known issues:

1. `feature-map.md` and blueprints must use corrected order:
   - Prompt 07: transcriptions model revision;
   - Prompt 08: categories, Spatie tags, item pinning, settings, and media field foundation;
   - Prompt 09: admin management;
   - Prompt 10: import/export for finalized schema;
   - Prompt 11: public homepage/search/category/tag landing pages;
   - Prompt 12: public item page, safe media player, transcription tabs, timestamp parser, viewer preferences;
   - Prompt 13: dashboard metrics;
   - Prompt 14: future sync viewer/studio plan only;
   - Prompt 15: security audit after implementation.
2. `transcriptions-model-spec.md` must require same-item validation for `featured_transcription_id`, safe delete/unpublish behavior, and queryable effective transcription sorting.
3. `taxonomy-tags-spec.md` must say Spatie Tags implementation is approved for Phase 02 implementation and should not ask for package approval again.
4. `media-embed-spec.md` must include media field foundation before import/export.
5. `public-panel-ux-spec.md` must include group image/initials badge, item page desktop/mobile layout, clear filters, result count, sort dropdown, category/tag landing pages, copy/share actions, request/report later.
6. `search-and-filters-spec.md` must distinguish default text search from advanced/deferred transcript search.
7. `import-export-revision-spec.md` must define `.md` / `.txt` transcript import package behavior and missing category/tag behavior.
8. `transcript-viewer-and-studio-future-plan.md` must state Prompt 12 implements parse-only viewer; Prompt 14 plans future sync/studio.

## Step 5 — Validate research file

Patch `docs/research/filament-examples-phase-02.md`.

It must precisely state what the FilamentExamples MCP can access.

If only `search_examples` exists but returns source snippets and file paths, document that as:

```text
Access level: source snippets through search_examples
Separate fetch/read/detail tool: not exposed
```

Do not call it full repository/source fetch if only snippets are available.

Each example entry must include:

```md
- Source:
- MCP search tool used:
- MCP fetch/read/detail/source tool used:
- MCP fetched: yes/no
- Access level:
- Filament version:
- Files/classes inspected:
- Dependencies:
- Why relevant:
- Filament concepts used:
- Pattern to copy:
- Pattern to avoid:
- Testing ideas:
- Implementation risk:
- Use now/later:
- Adaptation notes for PodText:
- Implementation prompt references:
- Confidence:
```

Add a summary table:

```md
| Feature | Best example(s) | Use now/later | Notes |
```

## Step 6 — Validate blueprints

Blueprints under `docs/phase-02/blueprints/` must be specific enough for implementation agents.

Each blueprint should include:

```md
## Goal
## Dependencies
## Models and migrations
## Relationships and casts
## Indexes and constraints
## Filament Resources / Pages / Relation Managers / Actions
## Public UI / Livewire / Blade where relevant
## Forms / tables / filters / actions
## Import/export where relevant
## Settings/widgets where relevant
## Security
## Tests
## Quality gate
## Out of scope
```

Patch or create missing blueprints for prompts 07–15.

## Step 7 — Validate prompts

Active prompts should be the current sequence only:

```text
prompts/06-phase-02-reset-research-blueprint-cleanup.md
prompts/07-phase-02-transcriptions-model-revision.md
prompts/08-phase-02-taxonomy-tags-pinning-settings-media-foundation.md
prompts/09-phase-02-admin-content-management.md
prompts/10-phase-02-import-export.md
prompts/11-phase-02-public-homepage-search.md
prompts/12-phase-02-media-embed-item-page-parser.md
prompts/13-phase-02-dashboard-metrics.md
prompts/14-phase-02-viewer-studio-future-plan.md
prompts/15-phase-02-filament-blueprint-security-audit.md
```

Patch active prompts so each reads:

- `AGENTS.md`;
- relevant docs;
- relevant blueprints;
- relevant durable `.ai/guidelines`;
- Boost docs when available;
- FilaCheck quality gate.

Patch known prompt issues:

- Prompt 08 must include media field foundation and must not ask again for Spatie package approval.
- Prompt 09 must include admin forms/tables for media fields created by Prompt 08.
- Prompt 10 must import/export media fields after Prompt 08 and must not depend on fields created later.
- Prompt 12 must implement parse-only timestamp/speaker viewer behavior and local show/hide options.
- Prompt 14 must be future sync/studio planning only and must not be the first place parser implementation appears.
- Every implementation prompt must run:
  ```bash
  php artisan test
  vendor/bin/pint --test
  vendor/bin/filacheck
  npm run build
  ```
- No implementation prompt should run `vendor/bin/filacheck --fix` automatically.

## Step 8 — Validate quality/tooling docs

Patch `docs/phase-02/tooling-and-quality-gates.md`.

It must include:

- Boost status and fallback if MCP transport is closed;
- Blueprint status;
- FilamentExamples MCP access level;
- FilaCheck/FilaCheck Pro status;
- FilaCheck caveat: if it modifies app/test files during a docs-only prompt, revert and report;
- final quality gates for docs-only and implementation prompts;
- no `--fix` without approval.

## Step 9 — Verification

Run:

```bash
git diff --check
git status --short
```

Run FilaCheck carefully:

```bash
vendor/bin/filacheck --detailed
```

If FilaCheck creates app/test diffs during this docs-only prompt:

1. record that behavior in `docs/phase-02/tooling-and-quality-gates.md`;
2. revert those app/test diffs;
3. keep only docs/guidelines/prompts changes.

Do not run migrations.
Do not run `npm run build` unless the active task changed frontend assets, which it should not.

## Step 10 — Final report

Return:

1. Whether `AGENTS.md` is now evergreen.
2. Active guideline list.
3. Active docs list.
4. Active blueprint list.
5. Active prompt list.
6. Archived/superseded file summary.
7. Whether stale Bootstrap/Slice 0 active instructions remain.
8. Whether Spatie tags/settings approval ambiguity is removed.
9. Whether media foundation before import/export is fixed.
10. Whether Prompt 12 includes parser/viewer options.
11. Whether Prompt 14 is future-only.
12. Boost MCP status.
13. FilamentExamples MCP access level.
14. FilaCheck/FilaCheck Pro result.
15. Any files intentionally left unchanged.
16. Current `git status`.

End with exactly:

```text
AI context alignment is ready for human review. No application features were implemented.
```
