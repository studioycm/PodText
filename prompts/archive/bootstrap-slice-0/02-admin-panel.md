# Codex Prompt 02 — Admin Content Management

## Goal

Create standard Filament 5 Admin Resources that let an administrator manually create, edit, search, filter, and publish Authors, ContentGroups, and ContentItems.

## Required context

Read:

- `AGENTS.md`
- `docs/project-description.md`
- `docs/architecture-decisions.md`
- Phase 2 in `docs/project-phases.md`

Inspect the actual Phase 1 models, migrations, casts, relationships, and test conventions before generating Resources.

## Constraints

- Current checkout only; no worktrees.
- Use Filament 5 APIs discovered from the installed version.
- Keep generated Resource, Schema, Table, and Page responsibilities separated.
- Do not generate admin View pages or Infolists.
- Do not implement import/export actions yet.
- Do not add Shield or roles.
- Do not build the transcription studio.
- Do not put domain orchestration into Resource classes.

## Implement

### 1. AuthorResource

Provide:

- list/create/edit pages;
- name and slug fields;
- read-only reference key display where useful;
- biography Markdown editor;
- searchable/sortable table;
- validation;
- translated labels.

Disable Markdown attachment uploads for this slice unless the current component requires an explicit configuration to do so.

### 2. ContentGroupResource

Provide:

- title and slug;
- group singular/plural type labels;
- default item singular/plural type labels;
- clear defaults and helper text;
- Markdown description;
- cover FileUpload on a defined disk/directory;
- original language code;
- publication status using Enum cases;
- publication timestamp;
- read-only reference key display where useful;
- table search;
- status and language filters;
- useful badges/columns;
- translated labels.

Use fake storage in tests.

### 3. ContentItemResource

Provide:

- parent ContentGroup selection;
- title and slug;
- nullable item singular-label override with inherited-label helper text;
- Markdown description;
- media URL;
- optional embed URL;
- duration seconds with useful presentation;
- multi-select Authors;
- Markdown transcript editor suitable for long content;
- original publication timestamp;
- publication status/timestamp;
- read-only reference key display where useful;
- table search;
- group/status/author filters;
- translated labels.

Do not create speaker, timestamp, or synchronized-player fields.

### 4. Embed validation

Implement a small explicit policy:

- HTTPS only;
- approved host list in configuration;
- no raw HTML;
- helpful validation message.

Do not implement provider detection or metadata extraction.

### 5. Admin navigation and RTL

- Use generic translated Resource labels: Content Groups, Content Items, Authors.
- Show configured record type labels in rows/forms.
- Verify forms and tables remain usable in Hebrew RTL.

## Tests

For each Resource, add current Filament 5/Pest tests covering:

- list page renders;
- create page renders;
- edit page renders;
- valid record creation;
- valid editing;
- required validation;
- unique validation;
- table search;
- relevant filters.

Additional tests:

- group type-label defaults persist;
- item effective label/override persists;
- item attaches multiple authors;
- cover upload stores on expected disk/path;
- invalid/non-HTTPS embed rejected;
- approved embed accepted;
- publication Enum submitted using Enum cases.

## Manual verification

Using the Admin panel:

1. create two authors;
2. create one group with Podcast/Episode defaults;
3. upload a cover;
4. create one item with both authors;
5. add media/embed URLs;
6. paste a Hebrew Markdown transcript;
7. publish both group and item.

Do not create the public display yet.

## Completion checks

Run and report:

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

## Final report

Report Resources created, validation rules, storage path, embed policy, tests, commands/results, and deferred observations.

Do not start Prompt 03.
