# Prompt 06 — Phase 02 Full Research, Specs, Guidelines, and Prompt Pack

You are working inside the current PhpStorm project repository for PodText.

## Purpose

This is a **research, specification, guideline, and future-prompt generation task only**.

Use the configured `filament-examples` MCP server for deeper research across **every subject and feature** listed in this prompt. Then produce project-specific research files, specs, AI guidelines, and the next implementation prompts that fit the actual current project state.

Do **not** implement application features in this task.

This prompt supersedes any earlier Phase 02 planning prompt. It clarifies that public listings are `ContentItem`-based, but it must also preserve and plan all other requested subjects: homepage UX, search/filter UX, categories, Spatie tags, pinning, media embeds, item page, parser/viewer, future transcription studio, import/export, settings, dashboards, and Filament Examples research.

---

## Current known project state

The user reports:

- Phase 0 is committed.
- Phase 01 / Prompt 01 has already run through prompts `00` to `04`.
- Prompt `05` is currently finalizing or has just finalized.
- Continue only after Phase 01 and prompts `00` through `05` are complete.
- The project currently has Laravel, Filament 5, Livewire 4, Laravel Boost, an Admin panel, a Public panel, and Phase 01 domain work.
- The project does **not** yet have categories, tags, pinning, media embed/provider fields, homepage/search implementation, public item-page player, or the revised multi-transcription model.
- The language switcher is deferred.

If the repository state contradicts this, adapt all planning to the actual repository state and document the difference.

---

## Required preflight

Before changing any file:

1. Read:
   - `AGENTS.md`
   - `.ai/guidelines/bootstrap-slice-0.md`
   - every file under `docs/`
   - every existing prompt under `prompts/`
   - all existing Phase 01 tests
   - all existing panel providers
   - current models, migrations, factories, seeders, Resources, Pages, Livewire components, Blade views, services, casts, and enums

2. Run or inspect:
   - `git status --short --branch`
   - `git log --oneline --decorate -10`
   - `php artisan about`
   - `php artisan route:list`
   - `composer show` and identify installed Laravel, Filament, Livewire, Spatie, and Boost packages
   - existing database schema from migrations and models

3. Confirm:
   - Prompt `05` has finished.
   - There are no unfinished implementation changes from Prompt `05`.
   - The local branch is in a safe state for documentation/planning changes.

If Prompt `05` has not finished, or if there are unresolved implementation changes, stop and report the blocker.

---

## Non-negotiable rules

- Do not create or use worktrees.
- Do not launch parallel agents.
- Do not push or create a remote.
- Do not implement migrations, models, Resources, Pages, services, UI, tests, or package installation in this task.
- Do not modify production code except documentation-only references if absolutely necessary.
- Do not edit existing implementation prompts destructively. Create new Phase 02 prompts instead.
- Do not include any MCP bearer token, API key, absolute secret, user credential, password, private path, or machine-specific private configuration in any committed file.
- Use the configured `filament-examples` MCP server only through the IDE/MCP integration.
- If the `filament-examples` MCP server is unavailable, stop and report that MCP research cannot be completed. Public web research alone is not enough for this task.
- Use Laravel Boost MCP tools if available, but do not pretend Boost tools were used if they are unavailable.
- Prefer official Laravel, Filament, Livewire, Spatie, and FilamentExamples sources over blog posts.
- Follow installed package versions. Do not use Filament 3 syntax or deprecated APIs.
- This task should create planning/specification files and a future prompt pack. It should not change application behavior.

---

# Comprehensive coverage mandate

Do not narrow this task to only the ambiguity around item-based listings, item-only pinning, or effective/main transcription sorting.

Those semantics are important, but this task must also cover **all other Phase 02 subjects**:

- homepage behavior and layout;
- homepage admin-managed sections;
- content group order;
- homepage/public settings through Spatie Settings / Filament Settings;
- public search behavior;
- search fields;
- advanced deferred transcript search;
- desktop/mobile filter UX;
- filter Apply / Clear behavior;
- URL/query-string persistence;
- sort options;
- custom hierarchical categories;
- Spatie tags and Filament Spatie Tags plugin;
- enabled-only public tags;
- public panel architecture;
- Filament table + Livewire public search/listing;
- ViewColumn/custom Blade cards;
- embedded media players;
- provider metadata;
- safe iframe rendering;
- public item page layout;
- timestamp/speaker parser;
- viewer options;
- future synced viewer;
- future transcription studio;
- import/export;
- optional `.md` / `.txt` transcript files during import;
- dashboard metrics;
- no search logging now;
- local research file from Filament Examples MCP;
- future implementation prompt pack.

Create an explicit coverage matrix so omissions are visible.

---

# Core semantic rules for Phase 02

These definitions must be used consistently in every generated spec, guideline, and future prompt.

## Public listing unit

Public homepage and public search results list **ContentItem** records.

They do **not** list `Transcription` records as standalone public cards.

User-facing labels may say “latest transcriptions,” but the internal meaning is:

> Published `ContentItem` records ordered by the item’s effective/main transcription publication date.

## Transcription model

`Transcription` is a child model of `ContentItem`.

A `ContentItem` has many `Transcription` records.

A `Transcription` belongs to one `ContentItem`.

A `Transcription` has an author.

A `Transcription` contains Markdown transcript content.

Speakers and timestamp parsing belong to `Transcription`, not directly to `ContentItem`.

If multiple published transcriptions exist for the same item, the public item page displays the published transcriptions as tabs.

## Effective/main transcription

Every public item page and public item card should use the item’s **effective/main transcription** where transcript-derived data is needed.

A `ContentItem`’s effective/main transcription is:

1. the explicitly selected featured transcription, if it is published;
2. otherwise the latest published transcription for that item;
3. otherwise `null`.

A `ContentItem` without an effective/main published transcription must not appear in public “latest transcriptions” lists.

Recommended database direction to evaluate:

```text
content_items.featured_transcription_id nullable foreign key to transcriptions.id
```

Prefer this over a `transcriptions.is_featured` boolean, because one item should have at most one featured transcription.

## Effective transcription date

Public “latest transcription” sorting must use:

```text
effective_transcription.published_at
```

not raw `content_items.created_at`, and not a standalone public `Transcription` listing.

If no explicit featured transcription exists, the effective transcription fallback is the latest published child transcription.

If an admin explicitly selects an older published transcription as featured, that featured transcription becomes the effective transcription and its `published_at` controls item-level public transcript metadata and sorting.

## Pinning

Pinning belongs to `ContentItem` only.

Do not add pinning fields to `Transcription`.

Do not plan transcription-level public pinning.

`Transcription` records affect item detail tabs, item effective/main transcript, parser behavior, author attribution, import/export, and transcript-derived metadata. They are not the public pin target.

Recommended item pinning fields to evaluate:

```text
content_items.is_pinned
content_items.pinned_at
content_items.pinned_until
content_items.pin_order
```

## Homepage ordering

Homepage returns published `ContentItem` records only.

Homepage visibility:

- `ContentItem` is published.
- `ContentItem` has an effective/main published transcription.
- Expired pinned state is ignored publicly.

Homepage ordering:

1. valid pinned `ContentItem` records first;
2. `pin_order` ascending;
3. `pinned_at` descending;
4. `effective_transcription.published_at` descending;
5. `content_items.published_at` descending as fallback.

Pinned items and latest items appear in one combined list, not separate sections.

## Search result ordering

Public search returns `ContentItem` records.

Default search may sort by effective/main transcription date.

Search must allow users to choose another sort and not force pinned-first behavior.

Sort options to plan:

- latest transcription;
- oldest transcription;
- title A-Z;
- title Z-A;
- duration shortest;
- duration longest;
- original episode date newest;
- original episode date oldest.

“Latest transcription” means `ContentItem` ordered by effective/main transcription publication date.

---

# Phase 02 decisions to encode

## 1. Revised transcription model

Plan the move from any current item-level transcript storage to child `Transcription` records.

Required direction:

- `ContentItem` has many `Transcription` records.
- `Transcription` is its own model.
- `Transcription` belongs to `ContentItem`.
- `Transcription` has an author.
- `Transcription` has Markdown content.
- `Transcription` may have parsed speakers/timestamps.
- Public item page displays multiple published transcriptions as tabs.
- Admin can select the item’s featured transcription.
- Default effective/main transcription is the latest published transcription when no explicit featured transcription is set.
- Speakers are per transcription.
- Timestamp syntax:

```md
[00:01:23] Speaker:
Transcript text...
```

Planning question to resolve in the spec:

- Should `Transcription` support a single `author_id` now and a future many-to-many contributor relation later, or should it already support multiple authors? Current requested direction is one author per transcription.

## 2. Homepage behavior

- Homepage shows only published `ContentItem` records.
- Homepage lists pinned items first, then latest published items by effective/main transcription date, in the same list.
- No separate pinned section for now.
- Each result card should show the `ContentGroup` as a badge or Blade component with:
  - group image when available;
  - blank profile/initials fallback when no image is available.
- `ContentGroup` needs an order control/field for where groups are displayed on homepage-managed sections.
- Homepage sections should be controlled by an Admin panel custom Page or Resource managing homepage layout sections and content.
- Default latest layout: mixed pinned cards and latest rows.
- Site settings using Spatie Settings / Filament Spatie Settings should control:
  - max pinned/latest count;
  - homepage layout options;
  - future item-page layout options.

## 3. Pinning

- Pinning applies to `ContentItem` only.
- Do not pin `Transcription` records.
- Pinning must support:
  - manual order;
  - optional expiration;
  - filter-aware display, so category/tag filters only show pinned results that match the active filter;
  - site setting to control number of pinned records displayed publicly.
- Suggested fields:
  - `is_pinned`
  - `pinned_at`
  - `pinned_until`
  - `pin_order`

## 4. Search page behavior

- Public search page shows results immediately.
- Search results are `ContentItem` records.
- Default search fields:
  - item title;
  - content group title;
  - enabled tag names;
  - category names.
- Advanced search fields are planned later:
  - author name;
  - item description;
  - transcript body;
  - speaker names;
  - metadata;
  - external provider;
  - original source URL.
- Transcript full-text search must be deferred and triggered by an explicit action/button, not run automatically on every filter change.
- Search logging is not required now.

## 5. Search UI and filters

Desktop:

- full-width search bar;
- important filters as chips/toggle buttons;
- advanced filters collapsed.

Mobile:

- search bar;
- filter drawer.

Filter behavior:

- deferred by default;
- Apply button;
- Clear filters button;
- persist filters in URL/query string if practical.

Sort options:

- latest transcription;
- oldest transcription;
- title A-Z;
- title Z-A;
- duration shortest;
- duration longest;
- original episode date newest;
- original episode date oldest.

Homepage applies pinned-first by default. Search results should allow the user to choose a sort that can turn off pinned-first behavior.

## 6. Categories

- Use custom hierarchical category tables, not Spatie tags for categories.
- `ContentGroup` has categories.
- `ContentItem` can optionally have categories.
- Public filtering includes item categories plus inherited group categories.
- Parent category filters include descendants.
- Store `parent_id` and plan for more than two levels, while keeping UI optimized for one or two levels initially.
- Suggested category tables:
  - `categories`
  - `category_content_group`
  - `category_content_item`

## 7. Tags

Use `spatie/laravel-tags` together with the official Filament Spatie Tags plugin.

This supersedes any earlier “custom tags table only” idea. Categories remain custom; tags use Spatie.

Plan these requirements:

- Tags are flat.
- Categories are hierarchical.
- Only enabled tags appear publicly.
- Volunteer-created tags are disabled by default later.
- For now, only admins manage tags.
- Use a safe scoped tag type for content tags, for example `content`, or justify an alternative.
- Plan a custom Spatie Tag model or migration extension if needed for:
  - `is_enabled`
  - `enabled_at`
  - `enabled_by_id`
  - `created_by_id`
  - future moderation/approval state.
- Public filtering must only use enabled tags.
- Admin resources should allow filtering tags by enabled/disabled later.
- Be careful with Spatie tag type security. Do not allow unscoped user-facing `SpatieTagsInput`.
- Do not use tags for roles, abilities, permissions, workflow states, or categories.

## 8. Public page architecture

- Public panel shell remains Filament.
- Public pages should be custom Filament Pages where appropriate.
- Search/listing should use a Livewire component with a Filament Table.
- Cards should use ViewColumn or custom Blade components.
- Item page should be custom Blade/Livewire inside the public panel.

## 9. Search results design

- First version uses a consistent card grid.
- Use Filament table mechanics for search/filter/sort/pagination where useful.
- Use custom Blade/ViewColumn for public card design.
- Do not make public results look like an admin CRUD table unless the researched examples justify a specific table/card hybrid.

## 10. Embedded media player

- Admins can provide both original media URL and embed URL for now.
- Later, provider drivers can derive the embed URL.
- Supported providers to plan:
  - Spotify;
  - YouTube;
  - Apple Podcasts;
  - SoundCloud;
  - generic iframe/oEmbed, admin-only.
- Do not store raw iframe HTML unless a researched package/pattern strongly justifies it and the spec defines sanitization.
- Prefer storing:
  - `media_url`
  - `embed_url`
  - `embed_provider`
  - `media_duration_seconds`
  - `external_id`
  - `external_title`
  - `external_description`
  - `external_thumbnail_url`
  - `external_published_at`
  - metadata JSON column if needed.
- Plan automatic metadata extraction as a later action that fills manual form fields, but prepare clean contracts now.

## 11. Public item page

Default layout should be setting-controlled later, but initial default:

Desktop:

- header/meta at top;
- player sticky in side/top area;
- transcript in readable main column.

Mobile:

- player sticky at top;
- transcript below.

Public item page should:

- represent one `ContentItem`;
- show the effective/main transcription by default;
- show other published transcriptions as tabs;
- hide draft/unpublished transcriptions;
- show the embedded player when allowed and available;
- show “Open original source”;
- show reading time;
- show audio duration;
- show transcript length;
- show author profile link;
- show category/tag links;
- show copy link to item;
- plan copy link to timestamp later;
- show share buttons;
- show empty-state suggestions;
- plan “Request this episode” when transcript is not published later;
- plan “Report correction” later.

## 12. Transcript display now

- Public item page should parse timestamps when present.
- Parser service should parse speaker names and timestamps from the effective/main transcription and other published tabbed transcriptions.
- Viewer options should allow users to hide/show speaker names and timestamps.
- No player sync yet.
- Parsed timestamp anchors should support future “copy link to timestamp.”
- Parser output should not replace the canonical Markdown; it should be derived from `Transcription` content.

## 13. Future transcript viewer

Plan but do not implement now:

- highlight current line if timestamp data exists;
- auto-scroll to current segment;
- auto-advance text at reading speed when no timestamps exist;
- viewer settings stored in localStorage now, user preferences later.
- Sync is primarily valuable for the transcription studio and later public viewer improvements, but current public item page should remain parse-only.

## 14. Future transcription studio

Plan but do not implement now:

- Use embedded external player when only that is available.
- Use direct audio URL when available.
- Focus on the author/admin writing workflow:
  - play/pause;
  - scrub;
  - playback speed;
  - insert timestamp;
  - select speaker quickly;
  - create speakers per transcription;
  - inject speaker/timestamp into Markdown;
  - future autosave only after failure handling and draft states exist.
- The goal is author/admin editing, not the first public viewing version.
- Plan Alpine.js usage for keyboard shortcuts and lightweight player/editor UI state, but do not make it mandatory before the stable item page exists.

## 15. Import/export

- Use native Filament import/export actions and importer/exporter classes.
- Export all fields for now with selectable columns where practical.
- Import matching rules:
  - `ContentGroup`: `reference_key` first, then slug;
  - `ContentItem`: `reference_key` first, then provider/external_id, then group + slug;
  - `Author`: `reference_key` first, then slug/name;
  - `Category`: slug/path;
  - `Tag`: slug or Spatie tag slug with content type;
  - `Transcription`: `reference_key` first, then item + author + published_at/content hash fallback.
- Item import must support an optional import option field to upload `.txt` or `.md` files for transcript content.
- Because transcriptions are separate child records, transcript file imports must create or update `Transcription` records. They must not write transcript Markdown directly onto `ContentItem`.
- Do not depend on numeric IDs for portable CSVs.
- Plan update-existing-record behavior by `reference_key`.
- Plan dry-run/validation behavior for later if not implemented immediately.
- Plan import/export after the model revision, category/tag model, and media fields are settled.

## 16. Public frontend improvements

Plan these public UX improvements:

- Clear all filters.
- Search result count.
- Sort dropdown.
- Copy link to item.
- Copy link to timestamp later.
- Share buttons.
- Reading-time estimate.
- Audio duration.
- Transcript length.
- Open original source.
- Author profile links.
- Category/tag landing pages.
- Empty state suggestions.
- Request this episode button when transcript is not published later.
- Report correction button later.

For each improvement, document whether it belongs in the next implementation phase, a later public-panel phase, or a future workflow/moderation phase.

## 17. Metrics and dashboards

Plan admin dashboard widgets for:

- published items;
- draft items;
- pinned items;
- items with multiple published transcriptions;
- items missing effective/main published transcription;
- content groups;
- authors;
- categories;
- tags;
- recently published;
- missing embed URL;
- missing transcript;
- items without category;
- transcriptions by author.

Do not implement analytics/logging now.

## 18. Search logging

No public search logging now.

If researching examples finds useful search-query logging patterns, document them as future/optional only.

## 19. Permissions

- For now, pinning/category/tag/media management is admin-only because only the Admin panel exists.
- Do not install or configure Shield in this phase.
- Keep Shield for a later moderation phase.
- Still design abilities/names so Shield can later gate:
  - manage categories;
  - manage tags;
  - pin content items;
  - manage media;
  - publish;
  - select featured transcription;
  - manage settings;
  - manage imports;
  - manage homepage sections.

---

# Required MCP research subjects

Use the configured `filament-examples` MCP server to search and fetch deeper details for every subject below.

For each subject, search by both feature keywords and likely example names. Do not rely only on known titles.

## Public tables and frontend search

Research:

- public Filament tables outside admin panels;
- Livewire components rendering Filament tables in Blade/custom public pages;
- search/listing pages using Filament table builder outside Resources.

Likely examples to fetch:

- Filament Table in Public: Outside the Panel
- any public-page table / public search examples

Extract:

- component class structure;
- traits/interfaces used;
- Blade structure;
- whether table state is in URL/query string;
- how public panel auth is avoided;
- how pagination and search work.

## Advanced filters/search

Research:

- complex filters;
- filters above table;
- custom filters with TextInput;
- SelectFilter;
- dependent filters;
- dynamic filters;
- filter layouts;
- clear/apply actions;
- deferred filters;
- query-string persistence if any;
- full-text search approaches;
- future natural-language search ideas.

Likely examples:

- Table with Complex Filters and Comma Separated Filter
- Dynamic Real Estate Filters
- AI-Powered Free-Form Text Search in Filament Table
- Common Filters as Buttons Above Table
- Show Empty Table Until Filtered/Searched if available

Extract:

- how filter classes are organized;
- whether filters live in Resource table method or separate Table class;
- how query logic is tested;
- how to avoid duplicate search pipelines;
- what belongs now vs later.

## Grid/card table layouts

Research:

- table columns as grid;
- ViewColumn;
- custom Blade card components;
- responsive table/card layouts;
- card grid for public search results.

Likely examples:

- Filament Table with Columns as Grid
- Custom Table Design with ViewColumn

Extract:

- how card Blade views receive records;
- how sorting/search still works;
- how image/avatar fallbacks are handled;
- how actions/links are rendered safely.

## Homepage layout and sections

Research:

- custom homepage dynamic sections;
- admin-managed homepage Resource/Page;
- ordering and visibility controls;
- configurable item counts;
- latest/pinned/section-based queries.

Likely examples:

- Filament Custom Homepage with Dynamic Sections

Extract:

- model fields;
- Resource/Page fields;
- section query strategy;
- frontend rendering strategy;
- how to adapt to PodText with latest ContentItems, pinned ContentItems, content group order, and effective/main transcription date sorting.

## Tags and categories

Research:

- Spatie Tags with Filament plugin;
- tag fields/columns/entries;
- custom tag model;
- tag type scoping;
- tag moderation/enabled state;
- custom category Resource with hierarchy;
- tree or nested category UI if examples exist.

Likely examples:

- CMS/blog with categories/tags/authors
- Spatie Tags examples
- hierarchical category examples if any
- Shield CMS Blog only for later role/permission ideas

Extract:

- whether examples use custom tags, Spatie tags, or simple many-to-many tags;
- how to display tags in tables/cards;
- how to filter by tags;
- how to moderate tags;
- pitfalls.

## Settings

Research:

- Spatie Settings plugin;
- Filament SettingsPage patterns;
- dashboard/public-display settings;
- settings authorization;
- settings forms.

Extract:

- which settings belong in a settings class;
- which settings belong in regular models;
- how settings pages are tested;
- how to avoid exposing sensitive settings.

## Media embeds and provider metadata

Research:

- embedded media/player examples;
- iframe rendering and safe allowlist patterns;
- oEmbed/provider resolver patterns;
- custom actions to extract metadata from URLs;
- custom page or form action calling third-party APIs.

Likely examples:

- Custom Page with Search in 3rd-Party Service
- any media/embed examples

Extract:

- where service classes are placed;
- how actions call services;
- how failures are displayed;
- validation/security for external URLs.

## Import/export

Research:

- native Filament import/export examples;
- import relationships;
- import options;
- update existing records;
- bulk export;
- selectable columns;
- queued import/export notifications;
- file upload option for additional transcript `.md`/`.txt`.

Extract:

- importer/exporter class structure;
- relationship resolution patterns;
- how examples handle validation failures;
- how to test import/export.

## Dashboards and metrics

Research:

- stats widgets;
- dashboard layout;
- cards/widgets;
- missing data widgets;
- recently published widgets.

Likely examples:

- Custom Dashboard Widgets
- dashboard charts examples

Extract:

- widget class structure;
- query strategies;
- lazy vs non-lazy;
- polling or no polling.

## Transcript viewer and transcription studio planning

Research:

- repeaters / nested repeaters;
- custom Livewire components inside Filament pages;
- custom page with rich editor/textarea;
- keyboard shortcut patterns;
- Alpine.js integration in Blade/Livewire;
- tabs for multiple transcriptions;
- custom schema/table/action organization.

Likely examples:

- Filament Repeater advanced use-cases
- Livewire component in edit form sidebar
- custom page examples
- any table tabs / custom tabs examples

Extract:

- what patterns are useful for the future studio;
- what should not be implemented before the public item page is stable.

---

# Official documentation research

In addition to FilamentExamples MCP, use official current docs for:

- Filament 5 tables filters and layouts.
- Filament 5 custom pages.
- Filament 5 import/export actions.
- Filament 5 widgets.
- Filament Spatie Tags plugin.
- Filament Spatie Settings plugin.
- Spatie Laravel Tags.
- Spatie Laravel Settings.
- Livewire 4 components and Alpine integration if needed.
- Laravel 13 validation, jobs, queues, storage, and testing when relevant.

Document official docs separately from example-source findings.

---

# Required output files

Create or update these files.

## 1. Research file

`docs/research/filament-examples-phase-02.md`

For each example found/fetched, include:

```md
## Example: <name>

- Source:
- MCP fetched: yes/no
- Filament version:
- Why relevant:
- Files/classes found:
- Filament concepts used:
- Pattern to copy:
- Pattern to avoid:
- Dependencies:
- Testing ideas:
- Adaptation notes for PodText:
- Implementation prompt references:
- Confidence:
```

Also include a summary table:

```md
| Feature | Best example(s) | Use now/later | Notes |
```

## 2. Phase 02 feature map

`docs/phase-02/feature-map.md`

Map every requested feature to:

- current status;
- required models/tables;
- admin UI;
- public UI;
- services/actions;
- tests;
- implementation phase/prompt;
- risk;
- dependencies.

## 3. Phase 02 answer coverage matrix

`docs/phase-02/answers-coverage-matrix.md`

Create a coverage matrix that maps every user answer and decision to a generated spec and future prompt.

Required columns:

```md
| Topic | Decision | Covered in spec | Covered in prompt | Implementation phase | Notes |
```

The matrix must include, at minimum:

- homepage published-only behavior;
- pinned-first same-list behavior;
- content group badge/avatar;
- latest by effective transcription published date;
- item-only pinning;
- manual pin order;
- pin expiration;
- filter-aware pinning;
- settings-controlled counts;
- immediate search results;
- deferred transcript full-text search;
- default search fields;
- future advanced search fields;
- desktop filter UX;
- mobile filter UX;
- Apply/Clear filters;
- URL/query persistence;
- all sort options;
- categories on groups and items;
- descendant category filters;
- Spatie flat tags;
- enabled-only public tags;
- volunteer tags disabled later;
- public panel architecture;
- card grid results;
- admin-managed homepage sections;
- media URL + embed URL;
- provider list;
- metadata fields;
- metadata extraction later;
- item page layout;
- timestamp parser now;
- future sync viewer;
- future transcription studio;
- timestamp format;
- speakers per transcription;
- admin-only management now;
- Shield later;
- import/export rules;
- optional transcript files;
- export all/selectable fields;
- public frontend improvements;
- dashboard metrics;
- no search logging;
- MCP research output format.

If a requested subject is not covered, update the relevant spec/prompt before committing.

## 4. Transcriptions model revision spec

`docs/phase-02/transcriptions-model-spec.md`

Must cover:

- migration path from current Phase 01 structure;
- `ContentItem hasMany Transcription`;
- `ContentItem.featured_transcription_id` recommendation or justified alternative;
- effective/main transcription resolution;
- why pinning belongs to `ContentItem`, not `Transcription`;
- whether existing item-author pivot becomes deprecated or remains for item-level metadata;
- `Transcription` fields;
- author relationship;
- featured/main transcription logic;
- published-tab display;
- parser relationship to transcription, not item;
- import/export impact;
- public route impact;
- test plan.

## 5. Taxonomy and tags spec

`docs/phase-02/taxonomy-tags-spec.md`

Must cover:

- custom categories;
- Spatie Tags integration;
- custom Tag model/extra columns if needed;
- enabled-only public tags;
- future volunteer-created disabled tags;
- category descendant filtering;
- group/item category inheritance;
- admin Resources/pages;
- public filters;
- tests.

## 6. Public panel UX spec

`docs/phase-02/public-panel-ux-spec.md`

Must cover:

- homepage;
- item-based “latest transcriptions” wording;
- search page;
- result cards;
- content group badge/avatar component;
- item page;
- effective/main transcription;
- transcription tabs;
- viewer settings;
- copy/share links;
- responsive layouts;
- RTL/Hebrew-first design;
- accessibility expectations.

## 7. Search and filters spec

`docs/phase-02/search-and-filters-spec.md`

Must cover:

- public search returns `ContentItem` records;
- default search fields;
- advanced deferred transcript search;
- filter UI;
- sort options;
- URL/query string persistence;
- category/tag filters;
- public table component;
- effective/main transcription sorting;
- cache/performance considerations;
- tests.

## 8. Media embed spec

`docs/phase-02/media-embed-spec.md`

Must cover:

- media fields;
- allowed providers;
- manual embed URL;
- provider resolver contract for later;
- metadata extraction action plan;
- iframe allowlist/security;
- player layout;
- fallback link behavior;
- tests.

## 9. Homepage settings spec

`docs/phase-02/homepage-settings-spec.md`

Must cover:

- Spatie Settings;
- Filament SettingsPage;
- homepage section model/page if used;
- max pinned count;
- max latest count;
- default item-page layout setting;
- group order;
- tests.

## 10. Import/export revision spec

`docs/phase-02/import-export-revision-spec.md`

Must cover:

- groups;
- items;
- authors;
- categories;
- tags;
- transcriptions;
- optional `.md` / `.txt` transcript file upload during import;
- transcript file import creates/updates `Transcription` records, not `ContentItem` transcript fields;
- matching rules;
- update existing;
- export all fields with selectable columns;
- queue/database notification needs;
- tests.

## 11. Dashboard metrics spec

`docs/phase-02/dashboard-metrics-spec.md`

Must cover:

- published items;
- draft items;
- pinned items;
- items with multiple published transcriptions;
- items missing effective/main published transcription;
- content groups;
- authors;
- categories;
- tags;
- recently published;
- missing embed URL;
- missing transcript;
- items without category;
- transcriptions by author.

## 12. Future viewer/studio plan

`docs/phase-02/transcript-viewer-and-studio-future-plan.md`

Must cover:

- current parse-only viewer;
- future sync viewer;
- future transcription studio;
- player limitations by provider;
- direct audio URL benefits;
- speakers per transcription;
- timestamp format;
- keyboard shortcuts;
- autosave/failure prerequisites;
- phased implementation order.

## 13. AI guidelines

Create or update these files:

```text
.ai/guidelines/phase-02-public-panel.md
.ai/guidelines/phase-02-search-filters.md
.ai/guidelines/phase-02-taxonomy-tags.md
.ai/guidelines/phase-02-media-embeds.md
.ai/guidelines/phase-02-transcriptions.md
.ai/guidelines/phase-02-import-export.md
.ai/guidelines/phase-02-viewer-studio.md
.ai/guidelines/phase-02-settings-dashboard.md
```

Each guideline file should include:

- preferred architecture;
- what to do;
- what not to do;
- testing rules;
- security rules;
- relevant examples from the research file;
- exact package/version warnings where found.

## 14. Future implementation prompts

Create new prompts after the existing prompt sequence. If prompts `00` through `05` exist, start at `06` or the next free number. If this active prompt is saved as `06`, create the implementation prompts after it.

Create these prompt files or equivalent next-numbered names:

```text
prompts/06-phase-02-research-and-specs.md
prompts/07-phase-02-transcriptions-model-revision.md
prompts/08-phase-02-taxonomy-tags-pinning-settings.md
prompts/09-phase-02-admin-content-management.md
prompts/10-phase-02-import-export.md
prompts/11-phase-02-public-homepage-search.md
prompts/12-phase-02-media-embed-item-page.md
prompts/13-phase-02-dashboard-metrics.md
prompts/14-phase-02-viewer-studio-future-plan.md
```

Important:

- `prompts/06-phase-02-research-and-specs.md` should represent this research/spec task itself.
- Later prompts should be implementation-ready, but must tell the implementation agent to re-check current code before changing files.
- Each implementation prompt must include:
  - goal;
  - current assumptions;
  - files/docs to read;
  - exact features;
  - out-of-scope features;
  - acceptance criteria;
  - required tests;
  - verification commands;
  - whether to leave changes uncommitted or commit.

---

# Special planning constraints

## Token and MCP security

- Never write the `filament-examples` MCP authorization token into docs, prompts, logs, comments, screenshots, or test output.
- Refer only to “the configured `filament-examples` MCP server.”

## Tag security

- Explicitly plan scoped Spatie tags using `type('content')` or a justified equivalent.
- Do not use tags for roles/permissions.
- Do not allow unscoped user-facing tag inputs.

## Media security

- Do not store arbitrary iframe HTML.
- Store URLs and render controlled iframe markup in Blade.
- Require HTTPS embed URLs.
- Plan allowlisted provider domains.
- Generic iframe/oEmbed should be admin-only.

## Search performance

- Do not implement automatic live transcript full-text search.
- Transcript-body search should be explicit/deferred/action-triggered.
- Plan indexes for searchable columns and relationship filters.

## Public data visibility

- Public pages show only published records.
- Public search returns only `ContentItem` records with an effective/main published transcription.
- Public search only uses enabled tags.
- Draft/transient records must not appear publicly.
- Multiple transcription tabs show only published transcriptions.

## Testing

Every implementation prompt must require Pest tests for:

- model relationships and scopes;
- public visibility;
- category inheritance;
- descendant category filters;
- enabled-only public tags;
- effective/main transcription selection;
- `ContentItem` without published transcription is hidden publicly;
- item with one published transcription uses it as effective transcription;
- item with many published transcriptions defaults to latest published transcription;
- item with explicit featured transcription uses it as effective transcription;
- public latest list orders items by effective transcription `published_at`;
- multiple published transcription tabs on the public item page;
- draft/unpublished transcriptions hidden from public tabs;
- pinned ContentItems appear before unpinned ContentItems;
- pin expiration;
- manual pin order;
- public search returns ContentItems, not Transcriptions;
- search filters;
- sort options;
- safe embed rendering;
- Markdown/timestamp parser behavior;
- import/export mapping where applicable.

---

# Verification for this planning task

After creating the docs/prompts/guidelines, run:

```bash
git diff --check
git status --short
```

Do not run migrations or modify database state.

Review all generated files and ensure:

- no token or secret appears;
- no application implementation files were changed;
- research clearly separates MCP findings from public docs;
- each prompt is actionable and phase-scoped;
- specs reflect the current repository state, not stale assumptions;
- all public listing semantics are item-based, not transcription-record-based;
- all non-listing subjects are covered in the coverage matrix.

---

# Commit behavior

If and only if:

- the task changed only docs, prompts, and `.ai/guidelines`;
- no secrets were written;
- no implementation code was changed;
- the research file was created;
- the answer coverage matrix was created;
- `git diff --check` passes;

then create one local commit:

```text
docs: plan phase two public panel and transcription architecture
```

Do not push.

If implementation files were changed accidentally, revert only those accidental implementation changes before committing the documentation.

---

# Final report

Return:

1. Whether `filament-examples` MCP was available.
2. Which examples were fetched through MCP.
3. Which official docs were consulted.
4. Current project state detected after prompts `00` through `05`.
5. Key architecture changes recommended.
6. Confirmation that public listing semantics are `ContentItem`-based.
7. Confirmation that pinning is `ContentItem`-only.
8. Confirmation that latest transcription sorting uses the item’s effective/main transcription publication date.
9. Confirmation that all other Phase 02 subjects are covered in the answer coverage matrix.
10. Created/updated docs.
11. Created/updated guidelines.
12. Created future prompts.
13. Any unresolved questions.
14. Commit hash if committed.
15. Current `git status`.

End with exactly:

```text
Phase 02 research and planning is complete. No implementation features were built.
```
