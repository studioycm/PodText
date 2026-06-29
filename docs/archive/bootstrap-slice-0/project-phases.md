# Project Phases — Bootstrap Slice 0

This is a bootstrap-only phase plan. It does not replace the later full-project phase plan generated after product discovery, user stories, database design, and architecture guidelines are complete.

Implementation is sequential. Do not use worktrees or parallel code-writing agents.

## Phase 0 — Repository and framework foundation

### Goal

Create a clean Laravel 13 repository with Filament 5, Livewire 4, Laravel Boost, the Admin panel, the guest Public panel, and the minimum queue/database-notification foundation required by Filament imports and exports.

### Tasks

#### Phase 0.1 — Create and inspect the Laravel application

- Create a new Laravel 13 application.
- Confirm the installed PHP, Laravel, Node, and database environment.
- Configure the intended local database.
- Install frontend dependencies.
- Confirm the default test suite passes before adding project code.

#### Phase 0.2 — Install Filament 5 panel builder

- Install the Filament 5 panel builder using current official commands.
- Create the default authenticated Admin panel at `/admin`.
- Create a second Public panel intended for guest access at `/`.
- Keep Admin and Public discovery paths separate.
- Create an initial administrator account or a deterministic local seeder.

#### Phase 0.3 — Install Laravel Boost and project guidelines

- Install Laravel Boost as a development dependency.
- Place project-specific guidelines in `.ai/guidelines`.
- Run Boost installation after Filament is installed.
- Confirm Codex can access Boost tools/documentation for installed package versions.

#### Phase 0.4 — Add import/export infrastructure

- Set the intended queue connection to `database` for Slice 0.
- Add the jobs migration if absent.
- Add job-batch and database-notification migrations.
- Publish Filament Actions import/export migrations.
- Enable Admin-panel database notifications.
- Document the local queue-worker command.

#### Phase 0.5 — Localization and public theme foundation

- Publish/configure Laravel and Filament translations where required.
- Configure Hebrew as default locale and English as available.
- Add locale-aware document direction.
- Add a Public-panel theme entry point suitable for Blade and Livewire public components.
- Use an initial font that renders Hebrew and diacritics correctly.

### Acceptance criteria

- `/admin` exists and requires authentication.
- `/` is served by the Public panel and does not require login.
- The Public panel contains no Admin Resources.
- Hebrew locale renders with RTL direction.
- English locale renders with LTR direction.
- queue, batch, notification, and Filament import/export migrations run successfully.
- the project can run a database queue worker.
- Laravel Boost recognizes the installed Laravel and Filament packages.

### Automated tests

- Admin panel guest access is denied or redirected.
- Authenticated administrator can access the Admin panel.
- Public panel is guest-accessible.
- Locale/direction helper or rendered layout produces RTL for Hebrew and LTR for English.

### Completion gate

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

Commit before Phase 1.

---

## Phase 1 — Domain foundation

### Goal

Create the smallest durable domain needed to store, publish, import, and display grouped content.

### Tasks

#### Phase 1.1 — Publication Enum

Create a backed `PublicationStatus` Enum with:

```text
Draft
Published
```

Use Filament-friendly labels and presentation only through current interfaces supported by installed Filament 5.

#### Phase 1.2 — ContentGroup model

Create:

- migration;
- model;
- factory;
- published scope;
- slug behavior;
- reference-key generation;
- publication-status cast;
- relationship to content items.

Required fields are defined in `docs/project-description.md`.

Add sensible indexes for:

- reference key;
- slug;
- publication status/date;
- original language code where useful.

#### Phase 1.3 — ContentItem model

Create:

- migration;
- model;
- factory;
- published scope that also requires a published parent group;
- reference-key generation;
- publication-status cast;
- relationship to group;
- many-to-many relationship to authors;
- effective type-label accessor/method.

Make slug uniqueness scoped to the content group unless current project requirements or database behavior justify a global slug. Public route resolution must remain unambiguous.

#### Phase 1.4 — Author model

Create:

- migration;
- model;
- factory;
- reference-key generation;
- slug behavior;
- many-to-many relationship to content items.

#### Phase 1.5 — Pivot and relationship constraints

- Create the item-author pivot table.
- Add unique pair constraint.
- Add foreign keys and deletion behavior.
- Decide and document whether deleting an author detaches credits or is prevented.

#### Phase 1.6 — Safe Markdown renderer

Create a focused backend renderer that:

- accepts Markdown text;
- converts it to HTML;
- sanitizes the HTML;
- returns a safe renderable value or clearly documented string;
- is reusable by public Blade components.

#### Phase 1.7 — Seed representative data

Seed:

- at least two authors;
- one published podcast-labeled group;
- one draft group;
- at least one published item;
- one draft item;
- one future-dated item if future publication is supported;
- Hebrew Markdown with diacritics;
- one malicious Markdown/HTML sample in a test fixture, not production seed data.

### Acceptance criteria

- all migrations run on a clean database;
- model factories create valid records;
- reference keys are generated and immutable by ordinary editing;
- content item belongs to one group;
- content item supports multiple authors;
- effective item label inherits from the group and supports an override;
- public scopes exclude drafts;
- item public scope excludes items under draft groups;
- Markdown rendering removes executable content.

### Automated tests

- Enum cast round trip.
- ContentGroup relationships.
- ContentItem relationships.
- Author relationships.
- stable unique reference-key generation.
- group label defaults.
- item label inheritance and override.
- published group scope.
- published item scope.
- draft-parent exclusion.
- future publication behavior.
- safe Markdown rendering/XSS regression.

### Completion gate

```bash
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint --test
npm run build
```

Commit before Phase 2.

---

## Phase 2 — Admin content management

### Goal

Allow an authenticated administrator to create, edit, search, filter, and publish authors, content groups, and content items through standard Filament 5 Resources.

### Tasks

#### Phase 2.1 — Author Resource

Create a conventional Resource with:

- list page;
- create page;
- edit page;
- searchable name and slug;
- biography Markdown editor;
- reference key displayed read-only where useful;
- validation;
- no View page.

#### Phase 2.2 — ContentGroup Resource

Create a conventional Resource with:

- title and slug;
- group singular/plural labels with defaults;
- default item singular/plural labels with defaults;
- Markdown description;
- cover upload;
- original language code;
- publication status and timestamp;
- useful search;
- status/language filters;
- published/draft badges;
- reference key displayed read-only where useful;
- no View page.

#### Phase 2.3 — ContentItem Resource

Create a conventional Resource with:

- group selection;
- title and scoped slug;
- optional item type-label override;
- Markdown description;
- media URL;
- optional embed URL;
- duration in seconds with human-readable helper text;
- author multi-select;
- Markdown transcript editor;
- publication status and dates;
- useful search;
- group/status/author filters;
- reference key displayed read-only where useful;
- no View page;
- no custom transcription studio.

#### Phase 2.4 — Embed validation

- Accept HTTPS only for embed URLs.
- Validate the host against configuration or an equally strict approved strategy.
- Do not accept raw iframe HTML.
- Keep the original media URL available even when embed validation fails.

#### Phase 2.5 — Resource navigation and translations

- Use translated generic navigation labels.
- Display per-record configured type labels in tables/forms.
- Ensure RTL forms and tables are usable in Hebrew.

### Acceptance criteria

- administrator creates an author;
- administrator creates a content group with default labels;
- administrator changes group/item labels;
- administrator uploads a cover;
- administrator creates an item and attaches multiple authors;
- administrator writes/pastes Hebrew Markdown transcript content;
- administrator publishes records;
- invalid embed URLs are rejected;
- Resource forms use current Filament 5 APIs;
- no unauthorized public CRUD route exists.

### Automated tests

For each Resource:

- list page smoke test;
- create page smoke test;
- edit page smoke test;
- valid create submission;
- required-field validation;
- relevant unique validation;
- relevant relationship selection;
- table search/filter behavior.

Additional tests:

- item author attachment;
- label override persistence;
- invalid embed host rejection;
- draft and published status persistence;
- cover upload behavior using fake storage.

### Completion gate

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

Manually create and publish one full record set, then commit before Phase 3.

---

## Phase 3 — Public guest panel

### Goal

Let a logged-out visitor browse published groups, inspect one group's published items, and read a published item's sanitized transcript.

### Tasks

#### Phase 3.1 — Public panel configuration

- Ensure the Public panel is guest-accessible.
- Remove login/profile/account widgets.
- Register only public custom Pages and public theme assets.
- Keep Admin Resource discovery out of the Public panel.
- Use root path or the approved public path.

#### Phase 3.2 — BrowseContentGroups page

Create a custom public Filament Page hosting a focused Livewire browser component.

Include:

- responsive group-card grid;
- cover/title/type label;
- description excerpt;
- published item count;
- search by group title;
- sort by newest and title;
- pagination;
- URL-persisted search/sort state;
- empty state.

#### Phase 3.3 — ShowContentGroup page

Create a slug-bound public page that:

- resolves only published groups;
- shows cover, title, labels, and sanitized description;
- lists only published child items;
- supports simple item sorting through Livewire when needed;
- shows item authors and duration;
- returns not found for draft/unavailable groups.

#### Phase 3.4 — ShowContentItem page

Create a slug-bound public page that:

- resolves only publicly visible items;
- rejects items whose parent group is not public;
- displays parent group, item label, title, authors, dates, and duration;
- displays an approved controlled embed or source link;
- displays sanitized description and transcript;
- returns not found for drafts/unavailable records.

#### Phase 3.5 — Reusable Blade components

Create only the components that reduce duplication, such as:

```text
content-group-card
content-item-row
type-label
markdown-content
media-embed
```

#### Phase 3.6 — Alpine enhancements

Add only small local enhancements where they improve usability:

- description disclosure;
- copy-link feedback;
- embed loading feedback.

Do not introduce Alpine stores or duplicated server state.

#### Phase 3.7 — Public localization and RTL polish

- translate all UI labels;
- confirm RTL grid/card/detail layout;
- confirm Hebrew diacritics render correctly;
- ensure long titles wrap or truncate accessibly;
- confirm keyboard focus and semantic headings.

### Acceptance criteria

- guest opens the public browse page;
- guest searches, sorts, and paginates groups;
- query state survives refresh/share;
- guest opens a published group;
- guest sees only published items;
- guest opens a published item;
- guest reads sanitized Markdown transcript;
- draft group/item direct URLs return not found;
- item under draft group returns not found;
- unsafe embed is not rendered;
- source link remains available;
- public layout works in Hebrew RTL and English LTR.

### Automated tests

- public panel guest access;
- browse page published-only query;
- search and sorting component tests;
- pagination component test;
- group slug resolution;
- draft group not found;
- item slug resolution;
- draft item not found;
- item under draft group not found;
- sanitized description/transcript output;
- malicious HTML absent;
- approved embed rendered;
- unapproved embed not rendered;
- query-string state behavior where practical.

### Completion gate

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

Manually verify the full create-to-public loop while logged out, then commit before Phase 4.

---

## Phase 4 — Native import and export

### Goal

Allow an administrator to import and export all three core record types using Filament's native queued Actions.

Read `docs/import-export-spec.md` before implementation.

### Tasks

#### Phase 4.1 — Author importer/exporter

- Generate and configure `AuthorImporter`.
- Generate and configure `AuthorExporter`.
- Add import/export actions to the Author Resource.
- Support create/update by reference key.
- Add example CSV.

#### Phase 4.2 — ContentGroup importer/exporter

- Generate and configure group importer/exporter.
- Apply type-label defaults.
- Validate status/language/date fields.
- Exclude remote cover fetching.
- Add example CSV.

#### Phase 4.3 — ContentItem importer/exporter

- Generate and configure item importer/exporter.
- Resolve parent group by reference key.
- Resolve multiple authors by reference key.
- Preserve multiline Markdown.
- Validate media/embed URLs.
- Make transcript export optional and disabled by default.
- Add example CSV.

#### Phase 4.4 — Import options and failures

- Add create/update mode options only when they can be clearly tested.
- Define blank-update behavior.
- Configure row limits and chunk size suitable for transcript data.
- Confirm failed rows produce downloadable CSV output.
- Ensure valid rows continue when other rows fail.

#### Phase 4.5 — Export behavior

- Support normal table export.
- Support selected-row bulk export when useful.
- Set sensible default columns.
- Preserve UTF-8 Hebrew output.
- Confirm CSV/XLSX formula-injection protections from current Filament behavior.

#### Phase 4.6 — Notifications and queue operation

- Confirm database completion notifications in Admin panel.
- Confirm local queue worker processes imports/exports.
- Document operational command in project README.
- Do not add custom retry/operations UI.

### Acceptance criteria

- administrator downloads each example CSV;
- administrator imports authors;
- administrator imports groups;
- administrator imports items with relationships;
- administrator updates an existing record by reference key;
- invalid rows fail without discarding valid rows;
- failed-row file is available;
- administrator exports CSV;
- administrator exports XLSX where supported;
- transcript is not included in default item export selection;
- Hebrew and Markdown survive round trip;
- import/export completion notification appears;
- guest cannot access import/export actions or files.

### Automated tests

All tests listed in `docs/import-export-spec.md`, including:

- create/update imports;
- relationship resolution;
- defaults;
- invalid rows;
- multiline Markdown preservation;
- export columns;
- authorization;
- URL validation;
- transcript default-selection behavior where testable through current Filament API.

### Completion gate

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

Perform a manual round trip:

```text
export seeded records
→ modify safe fields
→ import
→ verify updates
→ open public page
```

Commit before Phase 5.

---

## Phase 5 — Independent hardening and handoff review

### Goal

Review the implementation against the specification without adding deferred product features.

### Tasks

#### Phase 5.1 — Scope review

- Compare code with all files in `docs/`.
- Remove speculative abstractions and unused generated code.
- Confirm no deferred feature was partially introduced.
- Confirm internal terminology is consistent.

#### Phase 5.2 — Security review

Check:

- public draft leakage;
- authorization around Admin and import/export;
- Markdown XSS;
- arbitrary iframe/embed HTML;
- unapproved embed hosts;
- unsafe file uploads;
- CSV formula injection;
- export ownership/download access;
- mass-assignment and importer field protection.

#### Phase 5.3 — Data integrity review

Check:

- foreign keys;
- unique constraints;
- reference-key immutability;
- slug uniqueness/resolution;
- publication scopes;
- item-parent publication rule;
- importer relationship sync behavior;
- N+1 queries on public lists.

#### Phase 5.4 — UI and accessibility review

Check:

- Hebrew RTL;
- English LTR;
- Hebrew diacritics;
- mobile grid/list layout;
- long titles;
- focus states;
- labels and semantic headings;
- loading and empty states;
- useful error messages.

#### Phase 5.5 — Clean rebuild and test

Run from a clean state:

```bash
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint --test
npm run build
```

Run a queue worker and manually verify import/export.

#### Phase 5.6 — Handoff notes

Create a concise handoff report containing:

- implemented scope;
- routes and panels;
- local setup commands;
- queue-worker requirement;
- seed credentials or safe account-creation command;
- import order and example files;
- known limitations;
- deferred features;
- test/build results.

### Acceptance criteria

- all tests pass from a fresh database;
- no public draft exposure exists;
- no unsafe Markdown/embed output exists;
- all three imports and exports work;
- the full manual content loop works;
- the repository contains no worktree-specific assumptions;
- documentation matches actual commands and paths;
- handoff report is honest about remaining limitations.

### Final completion gate

```bash
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint --test
npm run build
```

Bootstrap Slice 0 is complete only after the manual checklist in `docs/acceptance-checklist.md` is signed off.
