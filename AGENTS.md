# Repository Instructions for Codex

## Project context

This repository is the first bootstrap slice of a larger Hebrew-first transcription platform.

Current stack:

- Laravel 13
- Filament 5
- Livewire 4
- Alpine.js as provided by Livewire/Filament
- Tailwind CSS 4
- Pest
- Laravel Boost

Read these files before changing code:

1. `docs/project-description.md`
2. `docs/architecture-decisions.md`
3. `docs/import-export-spec.md`
4. `docs/project-phases.md`
5. the active prompt in `prompts/`

## Current objective

Implement only Bootstrap Slice 0:

```text
admin creates/imports content
→ admin publishes content
→ visitor browses and reads content
```

This slice is intentionally smaller than the final application.

## Sequential execution only

- Do not create or use worktrees for Bootstrap Slice 0.
- Do not launch parallel implementation tasks that modify this repository.
- Finish, test, review, and commit one phase before starting the next.
- Do not rewrite earlier completed phases unless required by a failing test or an approved architecture correction.

## Domain terminology

Use these internal names consistently:

- `ContentGroup`: a container of content items. Its default display type is “Podcast”.
- `ContentItem`: an item inside a content group. Its default display type is “Episode”.
- `Author`: a credited contributor to one or more content items.

Do not create `Podcast` or `Episode` models, migrations, resources, services, or tables.

Administrators can customize display type labels. Internal class names and table names remain stable.

## Scope rules

Implement only functionality explicitly required by the active phase.

Do not add:

- Shield, roles, or permissions beyond secure admin-panel access;
- volunteers, auditors, approval workflows, or versioning;
- categories, tags, requests, comments, analytics, or CMS pages;
- provider drivers, URL metadata extraction, or remote APIs;
- synchronized players, timestamp parsing, speaker management, or a transcript studio;
- custom activity logs, operation logs, retry dashboards, or observability infrastructure;
- speculative repositories, managers, DTOs, actions, services, interfaces, traits, or event systems.

Record deferred ideas in the final task report instead of implementing them.

## Laravel conventions

- Use current Laravel 13 APIs.
- Use strict PHP types where consistent with the generated project.
- Type method parameters and return values.
- Use Eloquent relationships, scopes, casts, factories, and policies conventionally.
- Use PHP backed Enums for finite publication states.
- Store Enum values in normal string columns and cast them in models.
- Avoid database-native enum columns.
- Keep migrations independently runnable and reversible.
- Add database indexes and foreign-key constraints required by actual queries.
- Use route-model binding or Filament record resolution rather than manual unvalidated IDs.
- Do not hide business behavior in accessors, casts, or observers.
- Do not create a service class merely to move one short query out of a model or component.

## Minimal backend boundaries

The bootstrap slice may use these focused backend abstractions:

- `SafeMarkdownRenderer`: converts stored Markdown to sanitized HTML for public Blade output.
- a dedicated validation rule or focused helper for approved HTTPS embed URLs, only if the implementation needs it.

Use model scopes for published-content queries. Do not create a generic `ContentService`.

## Filament 5 conventions

- Use Filament 5 APIs only.
- Use an authenticated Admin panel for CRUD and import/export.
- Use a guest-accessible Public panel as the temporary frontend shell.
- Use Filament Resources for admin CRUD.
- Keep Resource classes small by using generated `Schemas` and `Tables` classes.
- Do not generate admin View pages or Infolists unless a requirement explicitly needs them.
- Use custom Filament Pages for public routes.
- Use Filament-native import/export Actions and generated Importer/Exporter classes.
- Use Filament resource URLs rather than hard-coded admin route names.
- Add Filament resource smoke tests and action tests.
- Do not use deprecated Filament v3/v4 syntax.
- When syntax is uncertain, use Laravel Boost documentation search for the installed Filament version.

## Public UI boundaries

Use the technologies as follows:

### Filament panel and pages

The Public panel supplies:

- panel routing;
- layout and theme;
- navigation shell;
- custom public pages.

### Blade

Use Blade templates/components for:

- content cards;
- content item rows;
- type-label badges;
- sanitized Markdown output;
- the controlled media iframe or source link;
- static detail-page layout.

### Livewire 4

Use Livewire only where server-driven interactivity is justified:

- group browsing search;
- group sorting;
- pagination;
- item sorting on a group page.

Keep search/sort state in URL query parameters where practical.

For this slice, prefer explicit class-based Livewire components because their PHP, Blade, and tests are easy for agents to inspect. Do not turn static fragments into Livewire components.

### Alpine.js

Use Alpine only for immediate browser-local behavior that does not require a server round trip, such as:

- expand/collapse controls;
- copy-link feedback;
- dismissible UI;
- optional iframe-loading state.

Do not duplicate persistent state in both Alpine and Livewire. Do not add a separate frontend framework.

## Markdown security

- Store transcript and description content as Markdown.
- Never render stored Markdown with an unescaped raw output call unless it has been sanitized.
- Centralize public Markdown rendering through `SafeMarkdownRenderer` or an equivalent single safe path.
- Add an XSS regression test.
- Disable Markdown-editor attachment uploads in Slice 0 unless explicitly implemented and tested.

## Media embeds

- Store the original `media_url`.
- Store an optional `embed_url`.
- Never store or render arbitrary embed HTML.
- Accept HTTPS URLs only.
- Render embeds through an application-owned Blade component.
- Use a configurable host allow-list or a comparably strict validation strategy.
- When an embed is not permitted or unavailable, show the original media link.

## Import and export

- Use native Filament Import and Export Actions.
- Import CSV.
- Export CSV and XLSX where supported by Filament.
- Use stable `reference_key` values for upsert and relationship resolution.
- Generate failed-row output through Filament’s importer behavior.
- Validate all imported values.
- Protect against CSV formula injection using Filament’s supported security behavior.
- Do not fetch remote covers or media during imports in Slice 0.
- Imports/exports may use the minimal database queue, job-batch tables, and database notifications required by Filament.
- Do not build a custom queue dashboard or retry manager in this slice.

## Localization and direction

- All UI text must use translation keys.
- Configure Hebrew as the primary locale and English as an available locale.
- Public and admin layouts must render RTL correctly when the active locale is Hebrew.
- User-entered titles and labels are content, not translation keys.
- `original_language_code` is content metadata and defaults to Hebrew for this slice.

## Testing requirements

Every phase must add or update Pest tests.

Required coverage includes:

- model relationships and casts;
- publication scopes;
- group/item visibility rules;
- admin Resource smoke tests;
- create/edit validation;
- public guest access;
- draft records inaccessible to visitors;
- Markdown XSS sanitization;
- import create and update behavior;
- relationship import resolution;
- failed import-row behavior;
- export columns and authorization;
- Hebrew/RTL layout markers where feasible without brittle visual assertions.

Use `Livewire::test()` for Livewire and Filament component tests unless installed documentation requires another current API.

## Quality gate

A phase is complete only when:

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

all succeed.

Also report:

- files changed;
- tests added;
- commands run and results;
- assumptions made;
- deferred issues;
- any requirement that could not be completed.

Do not claim success if a command was not run or failed.
