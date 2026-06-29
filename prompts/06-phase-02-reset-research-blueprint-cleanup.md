# Prompt 06R — Reset Phase 02 Research, Blueprints, Guidelines, and Prompts

You are working inside the current PhpStorm project repository for PodText.

This task **restarts and repairs Phase 02 planning** using the current project state and the newly installed tooling:

- Laravel Boost
- Filament Blueprint
- FilaCheck
- FilaCheck Pro
- explicit `livewire/livewire` dependency
- configured `filament-examples` MCP server

This is a **planning, research, documentation, guideline, and prompt-generation task only**.

Do **not** implement Phase 02 application features.

---

## 0. Current user-confirmed state

The user reports:

- Phase 0 is committed.
- Phase 01 / prompts `00` through `05` were completed before this Phase 02 reset.
- FilaCheck and FilaCheck Pro are already installed.
- `livewire/livewire` is explicitly present in `composer.json`.
- Filament Blueprint is installed.
- Laravel Boost is installed.
- FilamentExamples MCP is configured in the IDE/Codex environment with a paid subscription token.
- Existing Phase 02 docs/guidelines/prompts were generated once, but they need a proper reset/rebuild using Boost + Blueprint + FilaCheck + FilaCheck Pro + proper MCP research.

If the repository state contradicts these assumptions, stop and report the mismatch.

---

## 1. Core purpose

Create a corrected Phase 02 planning pack that is implementation-ready.

You may clean, rewrite, replace, or rebuild Phase 02 files from scratch if necessary, but only within documentation/guideline/prompt scope.

The final result must include:

1. proper current-state inspection through Laravel Boost;
2. proper FilamentExamples MCP deeper research proof;
3. Blueprint-style implementation specification files;
4. corrected Phase 02 docs/specs;
5. corrected Phase 02 AI guidelines;
6. corrected implementation prompts;
7. FilaCheck and FilaCheck Pro quality gates added to every implementation prompt;
8. no implementation code changes.

---

## 2. Non-negotiable rules

- Work sequentially in the current checkout.
- Do not create worktrees.
- Do not launch parallel agents.
- Do not create or push a remote.
- Do not implement application features.
- Do not install application packages in this task.
- Do not run migrations that change the database state.
- Do not edit models, migrations, Resources, Pages, Livewire components, Blade views, services, tests, or production config except where explicitly needed for documentation/guideline quality gates.
- Do not commit secrets.
- Do not write MCP tokens, FilaCheck Pro license data, Filament package credentials, Composer auth secrets, or machine-specific paths into tracked files.
- Do not edit `.env`.
- Do not edit `.codex/config.toml`, `.mcp.json`, IDE MCP settings, Composer auth files, or any token-bearing local configuration.
- If a tool requires login/license interaction, stop and ask the user to complete it locally.
- Leave the final changes uncommitted for human review unless the user explicitly asks for a commit.

---

## 3. Required source files to read

Read these first:

- `AGENTS.md`
- `composer.json`
- `composer.lock`
- all files under `docs/`
- all files under `.ai/guidelines/`
- all prompts under `prompts/`
- current models, migrations, factories, seeders
- current Filament panel providers
- current Filament Resources
- current Public panel Pages
- current Livewire components
- current Blade components/views
- current tests

Pay special attention to existing Phase 02 files and decide whether each should be patched or regenerated.

---

## 4. Tooling verification gate

Before editing files, verify the installed tooling.

Run/inspect:

```bash
git status --short --branch
git log --oneline --decorate -10
php artisan about
php artisan route:list
composer show laravel/boost filament/filament filament/blueprint livewire/livewire laraveldaily/filacheck
vendor/bin/filacheck --detailed
```

Also inspect `composer.json` and confirm:

- `livewire/livewire` is explicitly required;
- `filament/blueprint` is installed as a dev dependency or otherwise available;
- `laraveldaily/filacheck` is installed as a dev dependency;
- FilaCheck Pro rules appear to be available or activated.

If `vendor/bin/filacheck --detailed` does not appear to include Pro rules, report that FilaCheck is installed but Pro activation is not verified. Do not write license data or try to guess secrets.

If `filament/blueprint` is installed but Boost/agent files do not appear to include Blueprint guidance or skills, inspect whether Boost resources need updating. If safe, run:

```bash
php artisan boost:update --discover
```

If that command is interactive or may overwrite custom files without review, stop and report the required manual step.

---

## 5. Laravel Boost usage requirements

Use Laravel Boost MCP tools when available.

At minimum, use Boost or equivalent project inspection to collect:

- application package versions;
- database connection type;
- database schema;
- Eloquent models;
- routes;
- relevant docs via `search-docs` for installed package versions.

Use Boost documentation search for current docs on:

- Laravel 13;
- Filament 5;
- Livewire 4;
- Pest;
- Filament import/export;
- Filament tables and filters;
- Filament custom pages;
- Filament widgets;
- Filament Spatie Tags plugin;
- Filament Spatie Settings plugin;
- Filament Blueprint;
- FilaCheck if available through docs.

Record whether Boost MCP was actually available. Do not claim Boost was used if it was not.

---

## 6. Filament Blueprint usage requirements

Use Filament Blueprint planning guidance for this task.

This means:

- create explicit, structured implementation specifications before prompts;
- map each feature to concrete Filament primitives;
- include model attributes, casts, relationships, enums, indexes, and constraints;
- include Resource/Page/Relation Manager/Widget/Action plans;
- include form components, validation, layout structure, table columns, filters, actions, bulk actions, imports/exports, and widgets;
- include authorization assumptions and future Shield ability names;
- include tests and verification commands;
- include exact namespaces and likely file paths where appropriate;
- include edge cases and security checks;
- include implementation order and dependencies;
- copy all important plan details into the blueprint/spec files so later implementation agents do not need to load Blueprint guidance.

Create a dedicated blueprint directory:

```text
docs/phase-02/blueprints/
```

Create these Blueprint-style files:

```text
docs/phase-02/blueprints/07-transcriptions-model-revision-blueprint.md
docs/phase-02/blueprints/08-taxonomy-tags-pinning-settings-media-foundation-blueprint.md
docs/phase-02/blueprints/09-admin-content-management-blueprint.md
docs/phase-02/blueprints/10-import-export-blueprint.md
docs/phase-02/blueprints/11-public-homepage-search-blueprint.md
docs/phase-02/blueprints/12-public-item-page-media-parser-blueprint.md
docs/phase-02/blueprints/13-dashboard-metrics-blueprint.md
docs/phase-02/blueprints/14-viewer-studio-future-plan-blueprint.md
docs/phase-02/blueprints/15-filament-security-audit-blueprint.md
```

The security audit blueprint is a future prompt/plan only. Do not run a full security audit unless explicitly asked.

---

## 7. FilamentExamples MCP deeper research gate

The previous research was insufficient because it said the MCP server was used only through search.

This time, before editing the research file, prove that the configured `filament-examples` MCP server can provide deeper implementation details.

### Required MCP proof

Use the configured `filament-examples` MCP server.

Do not display or write the token.

1. Use the `search-examples` MCP tool, or the exact available equivalent.
2. Search for at least one known relevant example, for example:

```text
Filament Table on a Public Livewire Page
```

3. After search, use the deepest available MCP result/tool/path to read example implementation details.
4. Record whether you accessed source-level details by identifying:
   - MCP search tool name;
   - MCP fetch/read/source/detail tool name, if present;
   - example name;
   - files/classes/readme/source details inspected;
   - whether the access was source-level or search-summary-only.

### Stop condition

If the MCP server only exposes search results and no source/read/detail/fetch content, stop and report:

```text
The filament-examples MCP server is connected, but deeper source/detail fetch is not available through the exposed tools.
```

Do not generate a new research file by pretending search-only results are source-level research.

### Required research subjects

Use FilamentExamples MCP for all of these subjects:

- public Filament tables outside admin panels;
- Livewire components rendering Filament tables in Blade/custom public pages;
- complex filters;
- filters above table;
- custom filters with TextInput;
- SelectFilter;
- dependent filters;
- dynamic filters;
- filter layouts;
- clear/apply actions;
- deferred filters;
- URL/query-string state;
- full-text search approaches;
- AI/natural language search as future idea only;
- table columns as grid;
- ViewColumn;
- custom Blade result cards;
- responsive table/card layouts;
- custom homepage dynamic sections;
- homepage section Resources/Pages;
- Spatie Tags with Filament plugin;
- custom/hierarchical category Resources;
- Spatie Settings / SettingsPage patterns;
- safe media embeds / external data source actions;
- native Filament import/export;
- import options and relationship mapping;
- dashboard widgets and editorial warning lists;
- repeaters/nested repeaters;
- custom Livewire components in Filament pages;
- Alpine usage with Livewire/Blade;
- future transcription studio patterns.

### Required research entry template

Regenerate or patch:

```text
docs/research/filament-examples-phase-02.md
```

Every example must use this template:

```md
## Example: <name>

- Source:
- MCP search tool used:
- MCP fetch/read/detail/source tool used:
- MCP fetched: yes/no
- Access level: source / README / summary / search only
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

Also add a summary table:

```md
| Feature | Best example(s) | Use now/later | Notes |
```

---

## 8. FilaCheck and FilaCheck Pro requirements

FilaCheck and FilaCheck Pro are now installed and must become part of Phase 02 planning.

Create or patch:

```text
docs/phase-02/tooling-and-quality-gates.md
```

It must include:

- Laravel Boost usage;
- Filament Blueprint usage;
- FilamentExamples MCP usage;
- FilaCheck/FilaCheck Pro usage;
- Pest;
- Pint;
- npm build;
- exact quality gates per prompt.

Every implementation prompt that changes Filament code must run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

For local iteration, prompts may allow:

```bash
vendor/bin/filacheck --dirty
```

but final verification must include full:

```bash
vendor/bin/filacheck
```

Guidelines must mention FilaCheck Pro-relevant pitfalls:

- deprecated Filament API usage;
- wrong namespaces;
- deprecated Filament testing methods;
- Actions vs BulkActions;
- relationship selects without `searchable()`;
- tables without searchable text columns;
- missing table filters;
- custom filters without active indicators;
- table/card closures that perform queries/N+1 work;
- default polling in widgets where not needed;
- FileUpload fields without accepted file types and max size;
- string icons instead of enum icons;
- enum casts without Filament label/color/icon interfaces;
- Tailwind classes in Blade without theme coverage;
- bulk actions missing record deselection.

Do not run `vendor/bin/filacheck --fix` unless the repository has a clean commit and the user explicitly approves auto-fixes.

---

## 9. Phase 02 domain decisions that must remain final

Use these final semantics everywhere.

### Public result unit

Public homepage/search/category/tag listings return `ContentItem` records.

They do **not** return `Transcription` records as public cards.

### Transcriptions

`Transcription` is a child model of `ContentItem`.

`ContentItem` has many `Transcription` records.

`Transcription` belongs to one `ContentItem`.

`Transcription` has an author.

`Transcription` contains canonical Markdown transcript content.

Speakers and timestamp parsing belong to `Transcription`, not directly to `ContentItem`.

### Effective/main transcription

A `ContentItem`’s effective/main transcription is:

1. the explicitly selected featured transcription, if it is published and belongs to the same item;
2. otherwise the latest published transcription for that item;
3. otherwise `null`.

Public item pages default to the effective/main transcription.

Other published transcriptions appear as tabs/selector.

Draft/unpublished transcriptions are hidden publicly.

A `ContentItem` without an effective/main published transcription must not appear in public latest/homepage/search/category/tag listings.

Recommended model direction:

```text
content_items.featured_transcription_id nullable foreign key to transcriptions.id
```

Add validation and tests:

- featured transcription must belong to the same item;
- featured transcription must be published to be effective;
- if featured transcription is unpublished/deleted, safely clear the featured field or reject the operation;
- public sorting by effective/main transcription date must be queryable and tested.

### Latest transcriptions

“Latest transcriptions” is a user-facing label.

Internal meaning:

```text
ContentItem records ordered by effective/main transcription published_at.
```

### Pinning

Pinning belongs only to `ContentItem`.

Do not add pinning fields to `Transcription`, `ContentGroup`, `Category`, or `Tag`.

Recommended fields:

```text
is_pinned
pinned_at
pinned_until
pin_order
```

Homepage order:

1. valid pinned items first;
2. `pin_order` ascending;
3. `pinned_at` descending;
4. effective/main transcription `published_at` descending;
5. item `published_at` fallback.

Search results may let explicit user sort override pinned-first behavior.

### Categories

Categories are custom hierarchical records.

- `ContentGroup` has categories.
- `ContentItem` can optionally have categories.
- Public filtering includes item categories plus inherited group categories.
- Parent category filters include descendants.

Suggested tables:

```text
categories
category_content_group
category_content_item
```

### Tags

Use `spatie/laravel-tags` with the Filament Spatie Tags plugin in the implementation prompt.

Do not use a duplicate custom tag pivot if using Spatie taggables.

Rules:

- tags are flat;
- content tags are scoped to type `content`;
- only enabled tags appear publicly;
- volunteer-created tags are disabled by default later;
- admin manages tags for now;
- plan custom Spatie Tag model/extra fields for:
  - `is_enabled`
  - `enabled_at`
  - `enabled_by_id`
  - `created_by_id`
  - future moderation state.

### Media fields

Media field foundation must exist before import/export.

Move media field foundation into Prompt 08 or a separate prompt before Prompt 10.

Plan fields:

```text
media_url
embed_url
embed_provider
media_duration_seconds
external_id
external_title
external_description
external_thumbnail_url
external_published_at
media_metadata
direct_media_url nullable
```

Media security:

- no raw iframe HTML;
- HTTPS only;
- allowlisted providers;
- generic iframe/oEmbed is admin-only;
- render through application-owned Blade component;
- fallback to original source link.

### Public item page

Prompt 12 must implement:

- item page for one `ContentItem`;
- media player component;
- effective/main transcription default tab;
- other published transcriptions as tabs/selector;
- safe Markdown rendering;
- timestamp/speaker parser when present;
- show/hide timestamps option;
- show/hide speakers option;
- timestamp anchors;
- no player sync yet;
- reading time;
- audio duration;
- transcript length;
- categories/tags;
- author links;
- copy/share actions;
- desktop and mobile layout defaults.

### Transcript parser

Support both:

```text
[00:01:23] Speaker: Transcript text
```

and:

```text
[00:01:23] Speaker:
Transcript text...
```

Parser output is derived from `Transcription::transcript_markdown`.

Markdown remains canonical.

Parser failure must fall back to safe Markdown rendering.

### Future viewer/studio

Prompt 14 must be future planning only.

Prompt 14 must not be the first place parser implementation appears.

Prompt 12 implements parse-only viewer.

Prompt 14 plans:

- future synced public viewer;
- future transcription studio;
- embedded external player limitations;
- direct audio URL benefits;
- speed control;
- shortcuts;
- speaker quick insert;
- timestamp injection;
- future autosave/failure prerequisites;
- future permissions.

---

## 10. Required docs to regenerate or patch

Create/rebuild these docs.

### Research

```text
docs/research/filament-examples-phase-02.md
```

### Current state and tooling

```text
docs/phase-02/current-project-state.md
docs/phase-02/tooling-and-quality-gates.md
```

### Planning and specs

```text
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
```

### Blueprints

```text
docs/phase-02/blueprints/*.md
```

The blueprints must be concrete enough for implementation prompts to reference directly.

---

## 11. Required guidelines to regenerate or patch

Rewrite these guidelines with full structure:

```text
.ai/guidelines/public-panel.md
.ai/guidelines/search-filters.md
.ai/guidelines/taxonomy-tags.md
.ai/guidelines/media-embeds.md
.ai/guidelines/transcriptions.md
.ai/guidelines/import-export.md
.ai/guidelines/settings-dashboard.md
.ai/guidelines/viewer-studio.md
.ai/guidelines/tooling-quality.md
```

Each guideline must include:

```md
## Preferred architecture
## Do
## Do not
## Testing rules
## Security rules
## FilaCheck / FilaCheck Pro rules
## Relevant specs
## Relevant examples / research
```

---

## 12. Required prompts to regenerate or patch

Regenerate or patch prompts so they match the corrected order and include Boost, Blueprint, FilaCheck, and FilaCheck Pro.

Use these prompt files:

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

Prompt 06 is this reset/research/cleanup task.

Prompt 15 is future audit-only and should run after implementation phases, not now.

Every prompt must include:

- goal;
- current state assumptions;
- docs to read;
- relevant blueprint file to read;
- relevant guidelines to read;
- exact scope;
- out-of-scope;
- package/tool assumptions;
- implementation plan;
- acceptance criteria;
- required tests;
- required quality gate;
- final report format;
- commit behavior.

Every implementation prompt must run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

Implementation prompts that change Filament UI must also explicitly check relevant FilaCheck Pro issues.

Prompt 06 must **not** run feature implementation.

---

## 13. Corrected build order

The corrected Phase 02 order is:

1. Prompt 06 — reset research/specs/guidelines/prompts/blueprints only.
2. Prompt 07 — transcriptions model revision.
3. Prompt 08 — categories, Spatie tags, item pinning, settings, and media field foundation.
4. Prompt 09 — admin management for transcriptions/categories/tags/pinning/settings/media fields.
5. Prompt 10 — import/export for finalized Phase 02 schema.
6. Prompt 11 — public homepage/search/category/tag landing pages.
7. Prompt 12 — public item page, safe media player rendering, transcription tabs, timestamp parser, viewer hide/show preferences.
8. Prompt 13 — editorial dashboard metrics.
9. Prompt 14 — future sync viewer and transcription studio plan only.
10. Prompt 15 — Filament Blueprint security audit after implementation.

---

## 14. Coverage matrix requirements

Regenerate `docs/phase-02/answers-coverage-matrix.md`.

It must validate, in detail, all final user requirements, including:

- public listings are `ContentItem`;
- no public `Transcription` result cards;
- effective/main transcription rules;
- same-item featured transcription validation;
- featured transcription unpublish/delete behavior;
- latest transcription sorting;
- item-only pinning;
- manual pin order;
- pin expiration;
- homepage combined pinned/latest list;
- content group badge with image/initials fallback;
- group homepage order field;
- homepage settings;
- custom homepage sections;
- immediate search results;
- deferred transcript full-text action;
- default search fields;
- advanced future search fields;
- desktop filter UX;
- mobile filter UX;
- Apply/Clear filters;
- URL state;
- all sort options;
- custom categories;
- descendant filtering;
- Spatie tags;
- enabled-only public tags;
- no duplicate tag pivot;
- media field foundation before import/export;
- safe media embeds;
- item page layout;
- timestamp/speaker parser now;
- no player sync now;
- viewer show/hide options;
- future sync viewer;
- future studio;
- import/export `.md`/`.txt` transcript files;
- dashboard metrics;
- no search logging;
- Boost usage;
- Blueprint usage;
- FilaCheck usage;
- FilamentExamples MCP deep research proof.

---

## 15. Verification for this reset task

After creating/patching docs, guidelines, and prompts, run:

```bash
git diff --check
git status --short
vendor/bin/filacheck --detailed
```

Do not run migrations.

Do not run `npm run build` unless no implementation files changed and it is cheap/safe. This is a docs/planning task.

Do not fix app code found by FilaCheck in this prompt. Record baseline FilaCheck issues in:

```text
docs/phase-02/tooling-and-quality-gates.md
```

---

## 16. Commit behavior

Leave the cleanup changes uncommitted for human review.

Do not commit unless the user explicitly asks.

---

## 17. Final report

Return:

1. Current project state detected.
2. Whether Laravel Boost MCP was available and which tools were used.
3. Whether Filament Blueprint guidance/skills were available.
4. Whether FilaCheck and FilaCheck Pro were verified.
5. Whether FilamentExamples MCP deeper source research was proven.
6. Examples researched through MCP and access level.
7. Docs created/rewritten.
8. Blueprint files created/rewritten.
9. Guidelines created/rewritten.
10. Prompts created/rewritten.
11. Baseline FilaCheck findings, if any.
12. Any unresolved blockers.
13. Current `git status`.

End with exactly:

```text
Phase 02 reset research, blueprints, guidelines, and prompts are ready for human review. No implementation features were built.
```
