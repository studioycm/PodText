# Repository Instructions for Codex

## Purpose

This file contains **evergreen repository rules** for Codex and other AI agents.

Do not put one-time phase scope, temporary exclusions, or sprint-specific tasks in this file. Put those in:

- the active prompt under `prompts/`;
- feature/spec files under `docs/`;
- blueprint files under `docs/**/blueprints/`;
- temporary planning notes under `docs/archive/` when no longer active.

`AGENTS.md` should remain stable across future phases.

## Project context

This is a Hebrew-first transcription platform built with Laravel, Filament, and Livewire.

Current core stack:

- Laravel 13
- Filament 5
- Livewire 4
- Alpine.js as provided by Livewire/Filament
- Tailwind CSS 4
- Pest
- Laravel Boost
- Filament Blueprint
- FilaCheck and FilaCheck Pro

Use installed package versions as the source of truth.

## Instruction priority

When instructions conflict, follow this order:

1. The active user request.
2. The active prompt under `prompts/`.
3. Blueprint/spec files explicitly referenced by the active prompt.
4. This `AGENTS.md`.
5. Active evergreen files under `.ai/guidelines/`.
6. Laravel Boost-generated package guidance.
7. Existing code conventions.

Do not follow archived or historical instructions unless the active prompt explicitly asks you to inspect history.

## Documentation lifecycle

Keep active context clean.

- `AGENTS.md` is evergreen.
- `.ai/guidelines/` is for durable project rules and package conventions.
- Feature/phase-specific research, specs, and blueprints belong under `docs/`.
- Completed, superseded, or obsolete docs/prompts belong under `docs/archive/`.
- Do not leave stale one-time rules active in `.ai/guidelines/`.
- Do not keep old “do not implement X” exclusions active after X becomes part of the project roadmap.
- If a file is archived, add a short archive note explaining why it is no longer active.

## Read before changing code

Before modifying application code, read:

1. the active prompt;
2. the relevant blueprint/spec files named by that prompt;
3. relevant evergreen `.ai/guidelines/` files;
4. existing code near the files you will change;
5. existing tests for the affected area.

Before planning or rewriting docs, inspect the existing docs/prompts/guidelines and remove stale active instructions by archiving or replacing them.

## Sequential execution

- Work sequentially in the current checkout.
- Do not create worktrees unless the user explicitly asks.
- Do not launch parallel agents that modify this repository.
- Do not create a Git remote, push, publish, or create a GitHub repository unless explicitly asked.
- Finish the active prompt before starting another.
- Follow the active prompt’s commit behavior.
- If the active prompt does not explicitly request a commit, leave changes uncommitted for human review.

## Secret and local configuration safety

Never commit or print:

- `.env` secrets;
- MCP bearer tokens;
- Composer auth credentials;
- FilaCheck Pro license data;
- FilamentExamples membership tokens;
- private API keys;
- account passwords;
- local machine paths when avoidable;
- IDE-local MCP configuration.

If a task requires login, 2FA, license activation, private token entry, or account access, stop and ask the user to complete that step locally.

## Tooling expectations

### Laravel Boost

Use Laravel Boost MCP tools when available.

- Use `search-docs` before code changes when syntax/package behavior is uncertain.
- Use version-aware docs for Laravel, Filament, Livewire, Pest, Spatie plugins, import/export, tables, filters, widgets, and custom pages.
- Use `database-schema` before migrations or relationship changes.
- Use `get-absolute-url` before sharing a project URL.
- Do not claim Boost was used if it was unavailable.

### Filament Blueprint

Use Filament Blueprint for planning tasks.

Blueprints/specs should be concrete enough that an implementation agent can code without inventing architecture. Include:

- models, fields, casts, relationships, indexes, and constraints;
- Filament Resources, Pages, Relation Managers, Actions, Importers, Exporters, and Widgets;
- form schemas, validation, table columns, filters, actions, and bulk actions;
- public Pages, Livewire components, and Blade component plans;
- authorization assumptions and future ability names;
- tests, quality gates, edge cases, and security checks.

### FilamentExamples MCP

Use the configured `filament-examples` MCP server when the active prompt requests FilamentExamples research.

- Do not write the token into files.
- Distinguish search-only results from source/detail access.
- Do not claim “deep MCP research” unless a fetch/read/detail/source tool was actually used and documented.

### FilaCheck and FilaCheck Pro

FilaCheck is part of the quality gate for Filament work.

For implementation prompts that modify Filament code, final verification must include:

```bash
vendor/bin/filacheck
```

For iteration, `vendor/bin/filacheck --dirty` is allowed.

Do not run `vendor/bin/filacheck --fix` unless the active prompt explicitly allows auto-fixes, the repository has a clean commit, and the diff will be reviewed afterward.

If FilaCheck reports a real issue, fix it. If it is a false positive, document why in the final report.

## Domain terminology

Use stable internal names:

- `ContentGroup`: container of content items. Default display concept: podcast.
- `ContentItem`: content item inside a group. Default display concept: episode.
- `Author`: credited contributor.
- `Transcription`: transcript record belonging to a content item.

Do not create `Podcast` or `Episode` models, migrations, Resources, services, namespaces, or tables unless the user explicitly changes the internal architecture.

User-facing labels can say podcast/episode. Internal class and table names remain stable.

## Core content rules

Public browse/search/listing pages show `ContentItem` records unless a specific prompt says otherwise.

Transcriptions are child records used for transcript content, credits, tabs, parser output, and item metadata. They are not public result cards by default.

When implemented, a public content item should be visible only when its publication rules are satisfied by the active specs.

## Security rules

- Admin areas require authentication.
- Public areas must never expose draft/unpublished records.
- Store Markdown; render only sanitized HTML through a centralized safe renderer.
- Never store or render arbitrary iframe HTML.
- Media embeds must be HTTPS and allowlisted.
- Generic embed/oEmbed support must be admin-only unless a future prompt changes that.
- CSV/import behavior must protect against formula injection.
- Use stable public identifiers such as slugs and reference keys; do not rely on numeric IDs in portable imports/exports.

## Architecture boundaries

- Prefer Eloquent relationships, scopes, casts, factories, and conventional Laravel validation.
- Use PHP backed Enums for finite states; store enum values in string columns and cast them in models.
- Avoid database-native enum columns.
- Keep migrations independently runnable and reversible.
- Add indexes and foreign keys required by real queries.
- Do not hide workflow behavior in casts, observers, or accessors.
- Do not create broad generic service classes such as `ContentService`.
- Focused classes are acceptable when they have a clear responsibility, such as Markdown rendering, transcript parsing, media URL validation, or import mapping.

## Filament conventions

- Use Filament 5 APIs only.
- Use Filament generators when appropriate.
- Do not use deprecated Filament v3/v4 syntax.
- Use Resource URLs instead of hard-coded admin route names.
- Do not generate View pages or Infolists unless explicitly required.
- Keep Resources focused on UI composition.
- Avoid N+1 queries in table/card closures.
- Relationship selects should be searchable when datasets can grow.
- FileUpload fields must define accepted file types, max size, disk/visibility, and validation.
- Custom filters should expose active indicators where appropriate.
- Widgets should not poll unless there is a clear requirement.
- Use `Filament\Support\Icons\Heroicon` enum values instead of string icons.

## Public UI conventions

The public panel may be used as a temporary public shell.

Use:

- custom Filament Pages for public routes;
- class-based Livewire components for server-driven search, filters, sorting, pagination, and tab selectors;
- Blade components for cards, rows, badges, chips, media embeds, and sanitized transcript output;
- Alpine.js only for local UI behavior such as drawers, copy-link feedback, local display preferences, and simple toggles.

Do not duplicate authoritative persistent state in Alpine and Livewire.

Keep public search/filter/sort state URL-backed where practical.

## Localization and RTL

- Hebrew is the primary locale and must render RTL.
- English is available.
- UI text should use translation keys.
- User-entered titles, descriptions, labels, and transcript content are content, not translation keys.
- Language metadata fields such as `original_language_code` are content metadata.

## Testing

Every implementation change must add or update Pest tests where meaningful.

Common coverage includes:

- model relationships, casts, scopes, and factories;
- public visibility and draft hiding;
- admin Resource smoke tests;
- create/edit validation;
- import/export create/update and failure behavior;
- safe Markdown and XSS regressions;
- media embed allowlist/rejection;
- public search/filter/sort behavior;
- Hebrew/RTL markers where feasible without brittle visual assertions.

Use current Livewire/Filament testing APIs for the installed versions.

## Quality gates

For documentation/planning-only prompts, run the checks specified by the active prompt. At minimum:

```bash
git diff --check
git status --short
```

For implementation prompts, final verification normally includes:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

If a command is skipped, unavailable, or fails, state that clearly.

## Final report

Every task report should include:

- files changed;
- tests added or updated;
- commands run and results;
- FilaCheck result when applicable;
- assumptions made;
- deferred issues;
- anything that could not be completed;
- current `git status`.
