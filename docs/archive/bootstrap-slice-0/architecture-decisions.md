# Architecture Decisions — Bootstrap Slice 0

These decisions are intentionally scoped to the bootstrap version. They should be revisited during the later full-project architecture phase.

## ADR-001: Use generic internal content names

### Decision

Use:

```text
ContentGroup
ContentItem
Author
```

Do not use `Podcast` or `Episode` as PHP model names.

### Reason

The application may later contain grouped media that is not literally a podcast episode. Stable generic names avoid a costly rename and make the group/item relationship explicit.

### Consequence

Generated Laravel and Filament classes should use names such as:

```text
ContentGroupResource
ContentItemResource
ContentGroupImporter
ContentItemExporter
```

Admin navigation should initially use generic translated labels such as “Content Groups” and “Content Items”. Each record displays its administrator-configured type label.

## ADR-002: Keep type vocabulary in content data

### Decision

A content group stores:

```text
group_type_label_singular
group_type_label_plural
default_item_type_label_singular
default_item_type_label_plural
```

Defaults:

```text
Podcast
Podcasts
Episode
Episodes
```

A content item stores:

```text
type_label_singular_override (nullable)
```

### Effective item label

```text
item override
→ otherwise parent group's default item singular label
→ otherwise “Episode”
```

### Reason

This meets the need to rename the displayed concept without dynamically renaming models, tables, Resources, routes, or permissions.

### Deferred refinement

The final project may replace these fields with translatable JSON or a first-class content-type entity. Slice 0 must not introduce that extra abstraction.

## ADR-003: Use stable reference keys for import/export

### Decision

Each core model receives a unique, immutable `reference_key` using a ULID-compatible value.

Database primary keys remain conventional numeric IDs unless the generated project already establishes another standard.

### Usage

- public routes use slugs;
- database relationships use foreign keys;
- import/export identity and relationship mapping use `reference_key`.

### Reason

Titles and slugs may change. A stable reference key permits safe export/import round trips and updates without exposing database IDs.

### Behavior

- manually created records receive a generated reference key;
- imported rows with no reference key receive one;
- imported rows with a matching reference key update the existing record;
- importers must not change an existing record's reference key.

## ADR-004: Use a simple publication Enum

### Decision

Create a backed PHP Enum:

```text
PublicationStatus::Draft
PublicationStatus::Published
```

Use ordinary string database columns and Eloquent Enum casts.

### Reason

Slice 0 needs only public visibility. The later approval workflow will be designed after product discovery.

### Public scopes

`ContentGroup::published()` should require:

- status is Published;
- `published_at` is null or not in the future, according to the final field rule chosen during implementation.

`ContentItem::published()` should require:

- item is published;
- item publication date is active;
- parent group is published and active.

The tests must define the exact future-date behavior.

## ADR-005: Use Filament Resources only for admin CRUD

### Decision

Create authenticated Admin-panel Resources for:

- ContentGroup
- ContentItem
- Author

Use the Filament 5 generated separation:

```text
Resource class
Schemas/*Form.php
Tables/*Table.php
Pages/List*.php
Pages/Create*.php
Pages/Edit*.php
```

### Reason

Filament Resources provide the fastest conventional CRUD implementation and are optimized for Eloquent-backed administration.

### Limits

- no admin View pages;
- no Infolists;
- no custom studio;
- no nested Resource architecture;
- no Relation Managers unless they materially improve the first content loop and do not delay delivery.

The Content Item Resource owns item editing and selects its parent group.

## ADR-006: Use a separate guest Filament Public panel

### Decision

Create two Filament panels:

```text
Admin panel   /admin   authenticated
Public panel  /        guest-accessible
```

The Public panel is read-only and uses custom Filament Pages rather than duplicating admin Resources.

### Expected pages

```text
BrowseContentGroups
ShowContentGroup
ShowContentItem
```

### Reason

This gives the first version a consistent Filament shell while keeping public presentation separate from CRUD. The custom public pages can later be replaced by a custom Livewire frontend without changing the domain model.

### Security

Guest access must be intentional. Remove authentication requirements only from the Public panel. Never expose admin Resources in that panel.

## ADR-007: Split frontend responsibilities deliberately

### Blade

Use Blade for stable presentation:

```text
resources/views/filament/public/pages/*
resources/views/components/public/*
```

Potential components:

```text
public.content-group-card
public.content-item-row
public.type-label
public.markdown-content
public.media-embed
```

### Livewire 4

Use class-based Livewire components only for dynamic server-backed behavior:

```text
ContentGroupBrowser
ContentItemBrowser
```

Their responsibilities include:

- search;
- sort;
- pagination;
- URL query-state persistence.

The detail page itself should remain a Filament Page/Blade template unless dynamic behavior is required.

### Alpine.js

Use Alpine for local-only interactions:

- expand/collapse;
- copy feedback;
- loading indicators;
- non-persistent disclosure state.

### Rule

Do not implement the same state in both Livewire and Alpine.

## ADR-008: Centralize safe Markdown rendering

### Decision

Store original Markdown in the database and create a focused backend class such as:

```text
App\Support\Markdown\SafeMarkdownRenderer
```

It converts Markdown to sanitized HTML.

### Consumers

- group description;
- item description;
- transcript;
- author biography when displayed later.

### Reason

Public Blade templates are responsible for sanitization when they render Markdown as HTML. Centralizing the behavior reduces the risk of one unsafe output path.

### Security rule

No public template may directly render stored Markdown using `{!! !!}` unless the value is the result of the centralized sanitizer.

### Testing

Include malicious Markdown/HTML payloads and assert that scriptable content is removed.

## ADR-009: Store media URLs, not embed code

### Decision

`ContentItem` stores:

```text
media_url       required HTTPS or supported URL
embed_url       nullable HTTPS URL
```

Never store arbitrary iframe/embed HTML.

### Rendering

A dedicated Blade component:

- checks the embed URL against a configured approved-host policy;
- renders a controlled iframe with restrictive attributes;
- otherwise renders a normal link to `media_url`.

### Deferred work

Provider detection, ID extraction, metadata APIs, player adapters, and synchronized playback are not part of Slice 0.

## ADR-010: Use native Filament import/export

### Decision

Use:

- `ImportAction` with generated Importer classes;
- `ExportAction` and/or `ExportBulkAction` with generated Exporter classes.

### Reason

Filament already provides:

- CSV column mapping;
- per-row validation;
- failed-row CSV output;
- relationship import support;
- queued chunking;
- CSV and XLSX export;
- completion notifications;
- authorization hooks.

Building custom import screens would slow the first release and duplicate framework behavior.

## ADR-011: Accept the minimum queue foundation required by import/export

### Decision

Use a database queue connection for Slice 0 imports and exports.

Include:

- jobs table when not already present;
- job batches table;
- notifications table;
- Filament import/export support tables;
- Admin-panel database notifications;
- documented queue worker command.

### Reason

Native Filament imports/exports are queued batch operations. This is the only intentional asynchronous subsystem in Slice 0.

### Limits

Do not add Horizon, Pulse, Telescope, custom failed-process pages, custom retries, or operation logs yet.

## ADR-012: Keep backend orchestration small

### Decision

Do not establish a broad Actions/Services/DTO architecture during Slice 0.

Allowed focused classes:

```text
SafeMarkdownRenderer
an embed-host validation rule/helper
Importer classes
Exporter classes
```

Use:

- model scopes for publication queries;
- model relationships for navigation;
- Resource schemas for UI validation;
- Importer validation for CSV rows.

### Reason

The full project has not yet completed its architecture-guideline phase. Premature abstractions would make later decisions harder.

### Prohibition

Do not create vague classes such as:

```text
ContentService
ContentManager
PodcastService
ItemRepository
```

## ADR-013: Use translation files and RTL from the first screen

### Decision

- Hebrew is the default locale.
- English is available.
- every UI label uses a translation key;
- the HTML/panel direction follows the active locale;
- database content labels remain user-entered content.

### Reason

Retrofitting RTL and translations after building custom public templates is slower and riskier.

### Font

Use a Filament-compatible font that correctly renders Hebrew and diacritics. Varela Round may be used initially, but typography refinement is deferred.

## ADR-014: Keep import media handling manual

### Decision

CSV import does not:

- download covers;
- upload files;
- fetch remote media;
- resolve provider metadata.

Exports may include the stored cover path for reference, but cover files are not bundled.

### Reason

Remote file handling would introduce external requests, storage failure modes, provider rules, and queues beyond the minimum needed for the bootstrap release.

## ADR-015: Implement sequentially without worktrees

### Decision

Use one checkout and one active implementation task at a time.

### Process

1. Run one numbered prompt.
2. Review the diff.
3. Run tests, Pint, and build.
4. Manually verify.
5. Commit.
6. Start the next prompt.

### Reason

The initial domain, migrations, panels, and generated Filament files overlap heavily. Parallel work would create merge overhead without improving delivery speed.
