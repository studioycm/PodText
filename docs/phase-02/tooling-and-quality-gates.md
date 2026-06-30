# Phase 02 Tooling and Quality Gates

This file is active Phase 02 planning context. Root repository instructions are evergreen in `AGENTS.md`; historical Bootstrap Slice 0 and superseded Phase 02 context lives under `docs/archive/` and `prompts/archive/`.

## Required Planning Tools

### Laravel Boost

- Use Boost MCP tools when available.
- During Prompt 06S verification, Boost MCP was exposed and `application_info`, `database_schema`, and `search_docs` succeeded.
- If Boost MCP later fails with `Transport closed`, record the failure and use equivalent Artisan/shell inspection as fallback.
- Future implementation prompts should use Boost `application_info`, `database_schema`, and `search_docs` before changing code.

### Filament Blueprint

- Blueprint guidance is available at `vendor/filament/blueprint/resources/markdown/planning/`.
- Implementation prompts must read the relevant blueprint file under `docs/phase-02/blueprints/`.
- Blueprints must specify models, attributes, casts, relationships, indexes, Resources, fields, columns, filters, actions, widgets, tests, and exact quality gates.

## Blueprint contract for implementation prompts

- Blueprints are not optional background reading.
- The active implementation prompt must use the referenced blueprint as the detailed implementation contract.
- Each final report must include a Blueprint completion checklist.
- If code differs from the blueprint, document whether the difference already existed, was intentionally deferred, was impossible, was blocked, or is a conflict needing human decision.

### FilamentExamples MCP

- Use `mcp__filament_examples.search_examples` before writing Filament forms, tables, Resources, Pages, widgets, actions, imports, or exports.
- Record source/example access level. The exposed tool returns source snippets directly; no separate fetch tool is exposed.
- Record this precisely as source snippets through `search_examples`, not a full repository/source fetch.

### FilaCheck and FilaCheck Pro

- Installed packages:
  - `laraveldaily/filacheck` 1.2.3
  - `laraveldaily/filacheck-pro` 1.2.7
- Baseline command run in this reset task:

```bash
vendor/bin/filacheck --detailed
```

- Baseline result: pass, 0 issues.
- During docs-only prompts, `vendor/bin/filacheck --detailed` may still rewrite Filament app/test files. If that happens, record it, revert app/test diffs immediately, and keep only documentation/guideline/prompt changes.
- Prompt 06S observed this side effect: `vendor/bin/filacheck --detailed` rewrote three Filament form schema files and one admin Resource test. Those app/test diffs were reverted immediately.
- Do not run `vendor/bin/filacheck --fix` in planning tasks.

## Prompt 07 Post-Migration Verification

- Boost MCP was available during the post-migration documentation sync.
- Boost tools used: `application_info`, `database_schema`, and `database_query`.
- Migration state checked with Boost and `php artisan migrate:status`; all three Prompt 07 migrations were `Ran`.
- Physical schema checked: `transcriptions` exists, `content_items.featured_transcription_id` exists, and legacy `content_items.transcript_markdown` still exists.
- Focused tests run and passed: `php artisan test --filter=TranscriptionsModelTest` and `php artisan test --filter=PublicTranscriptionVisibilityTest`.
- The exact multi-package `composer show laravel/boost filament/filament filament/blueprint livewire/livewire laraveldaily/filacheck laraveldaily/filacheck-pro` command failed because this Composer version accepts one package argument; individual `composer show` checks succeeded.
- No migrations, npm build, FilaCheck run, or application code changes were part of this docs-only sync.

## Final Quality Gate Per Implementation Prompt

Every Phase 02 implementation prompt must run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

Local iteration may use:

```bash
vendor/bin/filacheck --dirty
```

Final verification must use full `vendor/bin/filacheck`.

## FilaCheck / Pro Pitfalls To Plan Against

- Deprecated Filament APIs.
- Wrong namespaces such as action classes outside `Filament\Actions`.
- Deprecated Filament test methods.
- Deprecated or incorrect Filament relation manager APIs.
- Confusing record actions and bulk actions.
- Relationship selects missing `->searchable()`.
- Tables without searchable text columns.
- Missing table filters for status/category/tag/content group.
- Custom filters without active indicators.
- Query work inside table/card closures causing N+1 behavior.
- Relation manager table closures that query owner or child relationships repeatedly instead of eager-loading or using `modifyQueryUsing()`.
- Relation manager tab badges that run expensive counts synchronously without a documented reason.
- Relation manager actions that use hard-coded admin route names instead of Resource URL helpers.
- Widget polling enabled without a need.
- File uploads without accepted file types and max size.
- String icons instead of `Filament\Support\Icons\Heroicon` enum icons.
- Enum casts that do not implement Filament label/color/icon interfaces where displayed.
- Tailwind classes in Blade not covered by the theme.
- Bulk actions that do not deselect records after completion.

## Reset Task Verification

For this documentation/planning task run:

```bash
git diff --check
git status --short
vendor/bin/filacheck --detailed
```

Do not run migrations. Do not fix app code found by FilaCheck in this prompt.
