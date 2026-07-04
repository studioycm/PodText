# Public Front v2 JSON-First Research + Blueprint Planning Prompt

## New thoughts / corrections before running this prompt

This is **not** an implementation prompt. It is a research, planning, and blueprint-generation prompt for Codex App / PhpStorm AI Assistant.

The important changes from the earlier version are:

1. **JSON-first settings/configuration is now a hard requirement.**
   Any configuration that can reasonably be stored as JSON should be stored as JSON. This includes card templates, card parts, homepage section options, looper/source/query configuration, menu/header configuration, about-page blocks, team-profile blocks, public form definitions, and display/layout options.

2. **Do not create models just to hold settings.**
   Models such as `CardTemplate`, `PublicMenuItem`, `AboutPageBlock`, `TeamProfile`, `PublicFormDefinition`, `PublicDisplaySection`, or `PublicLooper` should **not** be the default blueprint direction. If the agent thinks a model/table is necessary, it must document why JSON settings/configuration is not enough and mark it as an explicit user decision point.

3. **Submissions are different from settings.**
   Public form definitions are settings/configuration and should be JSON-first. Actual submitted forms may require a `PublicFormSubmission` model/table because submissions are transactional records, not settings. Even then, the agent must justify the exception and propose the smallest safe model/table.

4. **Filament Blueprint usage is planning-style, not assumed CLI generation.**
   The agent must write blueprint documents using the “Using Filament Blueprint, produce an implementation plan…” style. Do not assume a Filament Blueprint CLI command exists unless the installed package exposes one. The required output is Markdown blueprint files.

5. **The task must use Laravel Boost and FilamentExamples MCP deeply.**
   The agent must repeatedly search each topic with multiple wording variants, fetch/read actual example source if the MCP exposes that capability, and create one research file per topic with annotated patterns to copy/avoid.

6. **This planning happens after Prompt 12.**
   Prompt 12 is complete. Prompt 13 dashboard metrics is formally next, but the public-front v2 architecture should be researched and blueprinted before deciding whether Prompt 13 should run immediately.

---

## BEGIN CODEX PROMPT

Work in the current PhpStorm / Codex App project repository only.

This prompt supersedes previous Public Front v2 research prompt versions.

This is a **research, blueprint, and planning task only**.

Do not implement application features.
Do not edit PHP, Blade, migrations, tests, Resources, Livewire components, config, package files, or app code except Markdown docs/blueprints/research files.
Do not install packages.
Do not run migrations.
Do not run Prompt 13.
Do not push to GitHub unless explicitly asked.
Do not run `vendor/bin/filacheck --fix`.

## Goal

Create a deep research and blueprint pack for the next public-front development stage after Prompt 12, with a strict JSON-first settings/configuration architecture.

The subjects are:

1. JSON settings/configuration architecture.
2. Card template builder.
3. Homepage sections and generalized looper/query-builder configuration.
4. Public menu/header manager.
5. About page content builder / content editor.
6. Team-profile builder settings, not Laravel team/multi-tenancy.
7. Configurable public forms and form submissions management/resources.
8. Seeders/demo-data strategy related to these features.
9. Main transcription publication policy setting.
10. Public contributor/transcriber UX refinements.
11. Latest/search UX refinements.
12. Podcasts/groups page and group-page refinements.
13. Updated future implementation sequence before Prompt 13.

## Current state assumptions

Verify these from `docs/phase-02/current-project-state.md` before doing anything else:

- Prompt 12 is complete.
- Prompt 13 dashboard metrics is formally next, but public-front UX/CMS planning must happen before continuing to Prompt 13 if approved.
- `docs/phase-02/current-project-state.md` is the single source of truth for current prompt progress.
- Prompt 11R already replaced public Filament Table listing with custom Livewire state and Blade card grids/rows.
- Prompt 11B already added contributor discovery using `Author` as the public-safe contributor/transcriber model.
- Prompt 12 already added public item page, safe media component behavior, and parse-only transcript viewer.
- Do not regress any of those.

If the current repository state contradicts this, stop and report.

## Core architecture decision: JSON-first configuration

Configuration and settings must be JSON-first.

### Do

- Prefer Spatie Settings arrays / JSON payloads and existing settings pages for site-level configuration.
- Prefer adding `settings`, `display_config`, `source_config`, `template_config`, `menu_config`, `form_config`, `content_blocks`, or similar JSON properties to existing settings/configuration structures.
- Prefer extending existing `HomepageSection` with JSON configuration if the configuration belongs to homepage/section rendering.
- Prefer storing menu/header configuration, card templates, about content blocks, team profile blocks, form definitions, and looper/section configuration as JSON settings/configuration rather than creating separate models for each of them.
- Use Filament Builder, Repeater, RichEditor, MarkdownEditor, FileUpload, and SettingsPage patterns to edit JSON configuration.
- Use PHP registries, support classes, enums, casts, and typed config readers to validate and render JSON safely.
- Map semantic JSON values to known PHP/Blade/Tailwind-safe output.
- Keep configuration portable and easy to evolve.

### Do not

- Do not propose a new model just because a setting has many fields.
- Do not create models like `CardTemplate`, `PublicMenu`, `PublicMenuItem`, `AboutPage`, `AboutPageBlock`, `TeamProfile`, `PublicFormDefinition`, `PublicDisplaySection`, or `PublicLooper` as the default approach.
- Do not store raw Tailwind classes, raw CSS, raw SQL, arbitrary PHP class names, arbitrary validation classes, arbitrary Blade template paths, or arbitrary unsanitized HTML in JSON configuration.
- Do not create new database tables for settings/configuration unless the research proves a strong reason and flags it as a user decision.
- Do not use JSON settings as a way to bypass validation or security.

### Allowed exception: submissions

Public form submissions are not settings. They are user-generated transactional records.

A `PublicFormSubmission` model/table/resource may be proposed only if submission persistence is part of the recommended plan.

If proposed, justify it explicitly:

- why settings JSON is insufficient for submissions;
- why submissions need queryable records/status/admin review;
- what the smallest safe schema would be;
- how payload JSON is validated and safely rendered;
- what is deferred.

## Blueprint creation instructions

For every blueprint file, write it in a Filament Blueprint planning style.

Start each blueprint file with a short section like:

```text
Using Filament Blueprint, produce an implementation plan for a Filament v5 application feature: [feature name].

The plan should:
- Describe the primary user flows end to end.
- Map each domain/configuration concept and flow to concrete Filament primitives such as Settings Pages, Resources, Pages, Relation Managers, Actions, Builder blocks, Repeaters, FileUpload, RichEditor, and Livewire components.
- Identify configuration/state transitions and the actions that trigger them.
- Identify public Livewire/Blade flows and admin Filament flows.
- Identify tests, security rules, and out-of-scope boundaries.
```

Do not assume a Filament Blueprint CLI command exists.
If installed Filament Blueprint exposes a relevant command, document it, but do not require it.
Create blueprint Markdown files manually in the project’s established blueprint style.

## Required tools and research behavior

### Laravel Boost

Use Laravel Boost:

- `application_info`
- `database_schema`
- `search_docs`

Use `search_docs` before writing blueprint recommendations that depend on:

- Laravel;
- Filament;
- Livewire;
- Pest;
- Spatie Settings;
- Filament Builder;
- Filament Repeater;
- FileUpload;
- RichEditor;
- MarkdownEditor;
- Filament Actions;
- public pages;
- SettingsPage patterns.

Do not claim Boost was used if the tool fails.

### FilamentExamples MCP

Use the configured `filament-examples` MCP server deeply.

Do not merely do one broad search.

For each topic:

- run several targeted searches with different wording;
- re-run searches with alternative terms until you have enough concrete source/example detail or can honestly state that MCP access is limited;
- if MCP exposes source/fetch/read/detail tools, use them;
- if only `search_examples` is available and it returns snippets, record that limitation precisely;
- document exact example names, source paths/classes/snippets when available;
- document pattern to copy, pattern to avoid, and PodText adaptation notes.

Do not write MCP tokens into files.

### External sources to inspect if available

Also research these sources where accessible:

- `filamentexamples.com`
- `laraveldaily.com`
- LaravelDaily / FilamentDaily YouTube channels when useful for concept discovery only
- `https://github.com/LaravelDaily`
- `https://github.com/LaravelDaily/Filament-Menu-Builder-Demo`
- `https://github.com/studioycm/FilamentExamples`

If GitHub/source access is unavailable from Codex, record the limitation and tell the user what source access is needed.

## Read first

Read:

- `AGENTS.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/ai-development-lessons.md`
- `docs/phase-02/public-panel-ux-spec.md`
- `docs/phase-02/search-and-filters-spec.md`
- `docs/phase-02/homepage-settings-spec.md`
- `docs/phase-02/media-embed-spec.md`
- `docs/phase-02/transcript-viewer-and-studio-future-plan.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/phase-02/blueprints/13-dashboard-metrics-blueprint.md`
- `prompts/13-phase-02-dashboard-metrics.md`
- `.ai/guidelines/public-panel.md`
- `.ai/guidelines/search-filters.md`
- `.ai/guidelines/settings-dashboard.md`
- `.ai/guidelines/tooling-quality.md`

Inspect current code for context:

- public pages under `app/Filament/Public/Pages`
- public Livewire components under `app/Livewire/Public`
- public Blade components under `resources/views/components/public`
- current homepage/search/card rendering
- current contributor discovery components
- current item page/media/parser components
- `HomepageSection`
- `HomepageSectionType`
- `PublicContentSettings`
- `PublicContentCardOptions`
- `DemoHebrewContentSeeder`
- existing settings pages/resources
- current public panel provider/layout/header structure
- current tests around public homepage/search/contributors/item page

## Preflight

Run:

```bash
git status --short --branch
git log --oneline --decorate -15
```

Confirm:

- working tree is clean before this docs task;
- Prompt 12 is complete;
- Prompt 13 has not started.

If there are unexpected app-code changes, stop and report.

---

# Research topic 1: JSON settings/configuration architecture

Research:

- Spatie Settings JSON/array properties.
- Filament SettingsPage with nested array configuration.
- Filament Builder/Repeater for editing JSON arrays.
- Safe typed readers and renderer classes around JSON configuration.
- Testing JSON settings and defaults.
- Risks of overusing JSON settings.

Blueprint requirements:

- Define a project-wide JSON settings convention.
- Define when to use:
  - Spatie Settings JSON/array properties;
  - JSON columns on existing configuration models such as `HomepageSection`;
  - new models/tables only as explicit exceptions.
- Define config registry/support classes:
  - allowed block types;
  - allowed field types;
  - allowed entity sources;
  - allowed attributes;
  - allowed layout variants;
  - defaults;
  - validation.
- Define security rules:
  - no raw CSS/classes;
  - no raw SQL;
  - no arbitrary PHP classes;
  - no arbitrary Blade paths;
  - no unsafe HTML.
- Define test rules for defaults, invalid config fallback, and public rendering safety.

---

# Research topic 2: Card template builder

Research:

- Filament Builder blocks.
- Builder block previews.
- Interactive previews.
- Repeater vs Builder.
- View preview side-by-side.
- Icon picker / icon display.
- Custom table/card design examples.
- FilamentExamples examples around custom table design, ViewColumn, form preview, textarea preview, CMS/blog frontend theme.

Blueprint requirements:

- JSON-first card template system.
- Store card template families and template definitions in JSON settings/configuration.
- Use support classes/registries to validate template structure and render parts.
- Do not create `CardTemplate` model/table by default.
- If a model/table is proposed, justify why JSON settings cannot handle it.

Card template needs:

- card style/type families;
- reusable card templates;
- configurable card parts;
- entity-based data sources;
- related entity attributes;
- custom labels/icons;
- layout and positioning;
- preview beside or below builder;
- selected template used inside page/section/looper settings.

Possible item card parts:

- image;
- transcriber name/icon;
- date;
- read time;
- podcast/group identity;
- item title;
- description;
- categories;
- tags;
- custom content;
- action/link.

Part sources:

- content item;
- content group;
- transcription;
- author/transcriber;
- categories;
- tags;
- custom values.

Part settings:

- label;
- label position;
- icon;
- icon position;
- layout:
  - all in one line;
  - label separate and value/icon on second line;
  - inline grid;
  - full row;
- source entity;
- source attribute;
- visibility;
- order;
- line clamp;
- font-size preset.

Preview:

- side-by-side if room;
- under form if preview mode emulates full screen;
- server-side rendering acceptable/preferred.

---

# Research topic 3: Homepage sections and generalized loopers/query displays

Research:

- FilamentExamples custom homepage dynamic sections.
- Dynamic table fields with database actions.
- Custom data in editable table.
- Table bulk select / bulk actions / select all matching.
- Reorderable table rows.
- Section settings and query configuration.
- Query builder patterns without unsafe raw query storage.

Blueprint requirements:

- JSON-first section/looper/query-display system.
- Preferred default: extend existing `HomepageSection` and settings with JSON config.
- Do not create a new generic `PublicDisplaySection` / `PublicLooper` model by default.
- Blueprint optional future generic model only if current `HomepageSection + JSON settings` becomes unmaintainable.

Sections/loopers should display:

- items;
- categories;
- authors/transcribers;
- groups/podcasts;
- manually selected entities;
- query-generated entities;
- latest/top viewed/future variants.

Each looper/query-display needs JSON settings for:

- source type;
- source config;
- manual include/exclude;
- filtered selection;
- select all / deselect filtered results;
- order/sort;
- card type/template;
- layout;
- pagination;
- load more;
- link to full page;
- hide/show link;
- amount per page;
- total limit;
- heading/body.

Homepage section examples:

- items in category/group/tag;
- categories list;
- items list;
- authors list;
- groups list;
- “new podcasts” with heading, six results, no pagination if limit is six, published desc, link to all podcasts page or hide.

Latest section:

- section/looper;
- no heavy filters;
- heading row;
- search;
- forward/back controls;
- load more at bottom;
- admin setting for total query size, minimum 50;
- page-size setting open number from 4 to 25;
- card template setting.

Load-more types:

- numbered pagination;
- next/previous;
- load more button;
- infinite scroll optional/future, not default.

Admin selection:

- multi-select by table if practical;
- select all visible/filtered;
- deselect all visible/filtered;
- filter and select/deselect all results with one action;
- no raw SQL or arbitrary query strings in DB.

---

# Research topic 4: Public menu/header manager

Research:

- LaravelDaily/Filament-Menu-Builder-Demo source.
- FilamentExamples menu-related examples.
- Dynamic public header patterns.
- Public action modal/slide-over patterns.

Blueprint requirements:

- JSON-first menu/header manager.
- Store menu/header items in JSON settings.
- Do not create `PublicMenu` or `PublicMenuItem` models by default.
- Use a validated registry of menu item types.

Top public menu:

- home;
- podcasts/groups page;
- about page “מי אנחנו”;
- request transcription action;
- register as volunteer transcriber action;
- light/dark/system theme selector at end/left side.

Menu item JSON block types:

- internal route/page;
- external URL;
- public form modal;
- public form slide-over;
- dropdown/group later if practical.

Menu item fields:

- label;
- icon;
- sort order;
- visibility;
- target;
- selected public form key/slug from JSON form definitions;
- display mode: modal or slide-over;
- open in new tab.

Default route labels:

- contributors/transcribers route should have a default in code but be controlled by admin settings with other authors/transcribers page settings.
- groups/podcasts default public label/path should default to “groups” in code, but admin settings may change display labels to “podcasts” or another label.
- Do not keep old public group/podcast routes for backward compatibility unless a deliberate redirect strategy is approved.

---

# Research topic 5: About page content builder and team profiles

Research:

- Filament Builder for page blocks.
- Filament RichEditor JSON vs HTML.
- MarkdownEditor.
- Safe rendering.
- FileUpload image editor.
- FilamentExamples CMS/blog front-end theme.
- Form with content preview.
- Repeater advanced use cases.

Blueprint requirements:

- JSON-first About page settings.
- Store about content blocks and team profiles as JSON settings.
- Do not create `AboutPage`, `AboutPageBlock`, or `TeamProfile` models by default.
- Use Filament Builder/Repeater/RichEditor/FileUpload for JSON editing.

Need:

- public About page “מי אנחנו”;
- content paragraphs/blocks;
- content editor;
- support Markdown or rich content with per-block type/toggle;
- content rich editor with smart options;
- team section;
- team member/profile fields:
  - image upload into `team/` folder;
  - title;
  - name;
  - description;
  - sort order;
  - visibility.

Team profiles are not Laravel teams / not multi-tenancy.

Admin UI:

- paragraphs/content blocks using Builder or suitable content block editor;
- team management as repeater/table layout if practical;
- if table repeater is too cramped for image + description, research grid repeater or Builder alternative.

Rendering:

- Markdown must use existing safe Markdown renderer if available.
- Rich content must use a safe renderer.
- Do not render arbitrary unsanitized HTML.

---

# Research topic 6: Configurable public forms and submissions

Research:

- Filament Builder for dynamic form fields.
- Filament Actions modal/slide-over schemas.
- FilamentExamples form with custom fields.
- Dynamic table fields with database actions.
- Create form and table on same page.
- Admin management for submissions.

Blueprint requirements:

- JSON-first public form definitions.
- Store form definitions as JSON settings/configuration.
- Do not create `PublicFormDefinition` model by default.
- Use a registry to validate field blocks and runtime rendering.
- Use a real submission model/table/resource only for actual submitted records if needed.

Need:

- form definition/configuration;
- form name;
- heading;
- fields configuration;
- submit label;
- other settings;
- menu item can choose a form by key/slug;
- menu item chooses modal or slide-over;
- public form submissions stored with JSON payload because fields vary.

Recommended blueprint direction:

- `PublicFormsSettings` or existing public site settings JSON for definitions.
- `PublicFormSubmission` model/table/resource for submitted records only if submission storage is confirmed and explicitly justified.

Form definition JSON may include:

- slug/key;
- name;
- heading;
- description;
- submit label;
- success message;
- display mode default;
- enabled;
- fields JSON;
- settings JSON.

Field block types:

- text;
- email;
- phone;
- textarea;
- select;
- checkbox;
- toggle;
- url;
- file later / deferred.

PublicFormSubmission may include:

- form key/slug snapshot;
- payload JSON;
- status: new / reviewed / archived;
- submitted_at;
- source_url;
- metadata JSON.

Security:

- Field definitions must be whitelisted.
- Validation rules must be generated by a safe registry, not arbitrary user-provided PHP/class names.
- Submission payload must be escaped/safely displayed in admin.
- Disabled form cannot submit.
- File uploads can be deferred unless blueprint recommends safe v1.

---

# Research topic 7: Transcription publication policy

Research/blueprint a main app transcription setting:

- enable/disable more than one published transcription per item.

Preferred starting point:

- Store this in JSON-backed app/settings configuration.
- Do not create a model.

If disabled:

- only one published transcription per item should be allowed;
- featured transcription is the chosen public one;
- first transcription created should be saved as featured;
- to change public one manually, choose a new featured transcription.

Research implications:

- `TranscriptionResource`;
- `ContentItem` transcriptions relation manager;
- imports;
- public item page tabs;
- validation;
- default setting value.

Suggested default to evaluate:

- `true` to preserve current flexibility;
- `false` to simplify production.

---

# Research topic 8: Public contributors/transcribers UX refinements

Public contributors/transcribers page:

- compact right-side list taking about 25% width on desktop;
- selected preview takes about 75%;
- compact list cards:
  - name only;
  - number badge only, no label text;
  - click whole card to select/open preview;
  - no “go to page” action on compact card.
- selected preview card:
  - name;
  - transcription count;
  - link/action to full transcriber page (“עמוד מתמלל”);
  - latest related content items by transcriptions.

Top transcribers homepage section:

- horizontal compact list of top transcribers;
- click one;
- preview opens underneath;
- preview shows:
  - full selected transcriber card;
  - link to full page;
  - name;
  - transcription count;
  - latest transcriptions/items;
  - default five;
  - page-size choices 5 / 10 / 15;
  - link to all transcriber transcriptions.

Counting rules:

- count any published transcription by that transcriber;
- only if parent item and parent group are public;
- if same author has two published transcriptions on the same item, count two transcriptions, but the card is for the item and should list the two transcription names without duplicating/cutting item names badly.

---

# Research topic 9: Latest/search UX refinements

Latest section should:

- be a section/looper;
- have settings, not only hard-coded presets;
- have heading;
- have search;
- have pagination controls on top row;
- have forward/back;
- have load more at bottom;
- have admin-controlled total query size, minimum 50;
- have admin-controlled page size, open integer 4–25;
- have no heavy filters.

Latest item card style requirements:

- full square cropped image;
- transcriber name + icon on one line;
- transcription publish date with icon and read-time with icon on same line, pushed to sides;
- heading at bottom;
- podcast name small image;
- next line episode name, max 3 lines by default, tooltip full name;
- description truncated to 2 lines small text;
- settings control font size, title lines, description visibility, description size/lines;
- settings choose podcast display:
  - image + name;
  - simple text;
  - concatenate before episode name;
  - separator configurable if combined with episode name.

Card layout issue to research:

- if image size is large, text should go underneath or layout should switch;
- avoid flex overflow bugs;
- use grid/fixed safe image columns with `min-w-0`, or stacked image full width for large images;
- cards need stronger deterministic layout rules.

Search page:

- filters opened by action/button;
- drawer or slide-over acceptable;
- custom drawer is acceptable;
- categories as multi-select toggle buttons;
- tags as multi-select toggle chips/buttons;
- search and sort visible;
- clear all;
- active filter count badge;
- URL state.

---

# Research topic 10: Podcasts/groups page and group page refinements

Podcasts/groups page:

- route/default naming setting to research;
- default in code can be “groups” but public/admin setting can label as podcasts or another term;
- categories as toggle buttons list;
- search by name or topic;
- cards with image;
- under image:
  - name;
  - episodes count;
- card links to podcast/group page.

Group/podcast page:

- episodes list should display description;
- settings and row-card settings:
  - visibility;
  - font sizes;
  - description length/lines;
  - image size;
  - image position;
  - layout;
- likely reuse the card template family.

---

# Research topic 11: Seeders/demo data

Research:

- Laravel seeders and factories.
- `DatabaseSeeder::call()`.
- `WithoutModelEvents`.
- Production-safe vs demo-only seeders.

Blueprint requirements:

- Split current demo seed data into smaller optional seeders if useful.
- Keep demo content clearly demo-prefixed.
- Do not run demo seeders automatically in production.
- Add production-safe seeders only for settings/default structures if needed.
- Add cleanup strategy if demo data is used.
- Document exact Forge-safe commands.

---

# Output files to create

Create folder:

```text
docs/research/public-front-v2/
```

Create research files:

```text
01-json-settings-architecture.md
02-card-template-builder.md
03-public-display-sections-loopers.md
04-public-menu-header-manager.md
05-about-page-content-team-builder.md
06-public-forms-submissions.md
07-transcription-publication-policy.md
08-contributors-transcribers-ux.md
09-latest-search-ux.md
10-podcasts-groups-ux.md
11-seeders-demo-data.md
12-povilas-filamentexamples-source-index.md
index-and-agent-usage-guide.md
```

Create blueprint folder:

```text
docs/phase-02/blueprints/public-front-v2/
```

Create blueprint files:

```text
01-json-settings-architecture-blueprint.md
02-card-template-builder-blueprint.md
03-public-display-sections-loopers-blueprint.md
04-public-menu-header-manager-blueprint.md
05-about-page-content-team-builder-blueprint.md
06-public-forms-submissions-blueprint.md
07-transcription-publication-policy-blueprint.md
08-contributors-transcribers-ux-blueprint.md
09-latest-search-ux-blueprint.md
10-podcasts-groups-ux-blueprint.md
11-seeders-demo-data-blueprint.md
12-implementation-sequence-blueprint.md
```

Create final summary files:

```text
docs/phase-02/public-front-v2-final-report.md
docs/phase-02/public-front-v2-open-questions.md
docs/phase-02/public-front-v2-agent-usage-index.md
```

Each research file must include:

- purpose;
- topic scope;
- exact search terms used;
- Boost docs used;
- FilamentExamples MCP examples found;
- actual files/classes/snippets observed;
- GitHub/source files inspected if available;
- pattern to copy;
- pattern to avoid;
- PodText adaptation notes;
- JSON-first settings/configuration recommendation;
- where a model/table was considered and why it was rejected or accepted;
- recommended model/schema options only when truly needed;
- recommended Filament Resource/page/form patterns;
- public Livewire/Blade implications;
- tests;
- security notes;
- open questions.

Each blueprint file must include:

- “Using Filament Blueprint” planning framing section;
- goal;
- dependencies;
- primary user/admin flows end to end;
- mapping to concrete Filament primitives;
- JSON settings/configuration shape;
- models/migrations only if truly needed and justified;
- casts/enums/support classes;
- relationships only if relevant;
- Filament Resources/Pages;
- form schemas;
- tables/actions;
- public pages/Livewire/Blade;
- settings;
- seeders;
- tests;
- security;
- state/configuration transitions and actions;
- out of scope;
- quality gate;
- final-report checklist.

Final report requirements:

- summarize all topics;
- explain the JSON-first decision;
- recommend implementation order;
- state which topics are implementation-ready and which require user answers;
- list all examples researched;
- state MCP/source access level honestly;
- list questions for Yoni;
- do not propose starting Prompt 13 until user approves the public-front plan.

Important implementation-order recommendation to evaluate:

1. JSON settings architecture and renderer/validator conventions.
2. Card template builder foundation.
3. Public display sections / loopers.
4. Latest/search UX repair.
5. Public menu/header manager.
6. Configurable public forms/submissions.
7. About page content/team builder.
8. Podcasts/group page refinements.
9. Transcriber/top-transcriber refinements.
10. Seeder cleanup.
11. Transcription publication policy setting.
12. Prompt 13 dashboard metrics only after user approves.

Do not implement these steps now.
This task is only research, blueprints, final report, and questions.

## Validation

Run:

```bash
git diff --check
git status --short
```

Do not run tests/build/FilaCheck unless the active docs require it.
Do not edit app code.

## Commit behavior

If only Markdown files changed and validation passes, commit with:

```text
docs: research public front v2 json settings blueprint plan
```

## Final report

Include:

- latest local HEAD before this docs commit;
- Boost tools used;
- FilamentExamples MCP examples used;
- LaravelDaily/GitHub sources inspected;
- files created/updated;
- research topics completed;
- blueprint files created;
- implementation-order recommendation;
- open questions;
- validation command results;
- commit hash if committed;
- current git status.

End with exactly:

```text
Public Front v2 JSON-settings research and blueprints are ready for review. No implementation was started.
```

## END CODEX PROMPT
