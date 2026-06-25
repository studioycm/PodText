# Project Description — Bootstrap Slice 0

## Purpose

Bootstrap Slice 0 is the smallest useful version of a larger transcription-content platform.

Its purpose is to let an administrator create or import structured content and immediately verify how that content appears to visitors. It is not the final application architecture or the complete product plan.

## Product outcome

The slice is successful when an administrator can:

1. sign in to the admin panel;
2. create or import authors;
3. create or import content groups;
4. create or import content items and Markdown transcripts;
5. publish a content group and content item;
6. export the same data;
7. open the public panel as a logged-out visitor and view the published content.

## Technology baseline

- Laravel 13
- Filament 5 panel builder
- Livewire 4
- Alpine.js bundled through Livewire/Filament
- Blade templates and Blade components
- Tailwind CSS 4
- Pest
- Laravel Boost

## Internal domain language

### Content group

`ContentGroup` is the internal model for a container of related content items.

The default display type is:

```text
Podcast / Podcasts
```

An administrator may replace those labels when creating or editing a group, for example:

```text
Series / Series
Course / Courses
Channel / Channels
Audiobook / Audiobooks
```

### Content item

`ContentItem` is the internal model for one piece of content inside a content group.

The default item display type inherited from the group is:

```text
Episode / Episodes
```

An administrator may configure the default item labels on the group and may override the singular type label on an individual item, for example:

```text
Chapter
Lecture
Interview
Video
Special
```

### Author

`Author` is a credited contributor attached to one or more content items.

A content item may have multiple authors.

## Bootstrap actors

### Administrator

The administrator:

- signs in to the Filament Admin panel;
- manages content groups, content items, and authors;
- uploads group covers manually;
- creates and edits Markdown content;
- sets publication state and dates;
- imports and exports CSV/XLSX data using native Filament actions.

Slice 0 has one trusted administrator class of user. The full role and permission matrix is deferred.

### Visitor

A visitor:

- does not need an account;
- opens the guest Public panel;
- browses published content groups;
- searches and sorts groups;
- opens a group and sorts its published items;
- opens a published item;
- follows or plays its external media source when an approved embed is available;
- reads the safely rendered Markdown transcript.

## Core records

### ContentGroup

Minimum data:

- stable `reference_key` for import/export identity;
- title;
- slug;
- singular and plural group type labels;
- default singular and plural item type labels;
- Markdown description;
- manually uploaded cover path;
- original-content language code;
- publication status;
- publication timestamp;
- standard timestamps.

Default labels:

```text
group singular: Podcast
group plural: Podcasts
item singular: Episode
item plural: Episodes
```

### ContentItem

Minimum data:

- stable `reference_key`;
- parent content group;
- title;
- slug unique within its group;
- optional singular item-type override;
- Markdown description;
- original media URL;
- optional approved HTTPS embed URL;
- optional duration in seconds;
- Markdown transcript;
- publication status;
- publication timestamp;
- optional original publication timestamp;
- standard timestamps.

### Author

Minimum data:

- stable `reference_key`;
- name;
- slug;
- optional Markdown biography;
- standard timestamps.

Authors and content items have a many-to-many relationship.

## Publication model

Slice 0 has only two publication states:

```text
Draft
Published
```

Public visibility rules:

- a content group must be published;
- a content item's parent group must be published;
- the content item must be published;
- any configured publication timestamp must not be in the future;
- drafts must not be discoverable through direct public URLs.

The status representation must be extensible enough to replace this simple model with the later moderation workflow.

## Admin panel

The authenticated Filament Admin panel lives at `/admin`.

It contains standard Resources for:

- Content Groups
- Content Items
- Authors

Each Resource includes:

- list page;
- create page;
- edit page;
- validation;
- useful search and filters;
- import action;
- export action;
- automated Resource smoke tests.

The Content Item form includes a Filament Markdown editor for the transcript.

Slice 0 does not include a custom transcription studio.

## Public panel

A separate Filament Public panel temporarily acts as the frontend.

It is guest-accessible and read-only.

Required public routes:

```text
/                          content-group browser
/groups/{contentGroup}     group details and published items
/items/{contentItem}       item details and transcript
```

Exact slugs and route bindings may be adjusted to current Filament 5 capabilities, but public URLs must use slugs rather than database IDs.

### Public browse page

Show published groups in a responsive card grid.

Include:

- cover;
- title;
- display type label;
- short description excerpt;
- published item count;
- text search;
- simple sorting;
- pagination;
- query-string persistence for search and sort.

### Public group page

Show:

- group cover;
- title;
- group type label;
- Markdown description;
- a list of published items;
- item type labels;
- author names;
- duration where available;
- simple item sorting.

### Public item page

Show:

- parent group information;
- item title and effective type label;
- author names;
- description;
- duration;
- approved external player embed when available;
- otherwise, a link to the original media URL;
- safely rendered Markdown transcript.

## Blade, Livewire, and Alpine responsibilities

### Blade

Blade handles reusable presentation and static detail layouts.

Expected reusable components include:

- group card;
- item list row/card;
- type-label badge;
- sanitized Markdown block;
- controlled media embed.

### Livewire 4

Livewire handles only server-backed public interactivity:

- group search;
- group sorting;
- pagination;
- item sorting.

The active search and sort state should be shareable through query parameters.

### Alpine.js

Alpine handles immediate browser-only interactions such as:

- expanding or collapsing long descriptions;
- copy-link feedback;
- iframe loading state.

Alpine does not own persisted business state.

## Backend boundaries

Slice 0 should remain intentionally small.

Required focused backend behavior:

- Eloquent published scopes;
- Enum casts for publication status;
- a centralized safe Markdown renderer;
- strict embed URL validation and rendering.

Do not create broad generic service layers.

A focused `SafeMarkdownRenderer` is justified because the same security rule applies to group descriptions, item descriptions, author biographies, and transcripts.

## Import and export

Use Filament 5 native Import and Export Actions for all three Resource types.

Required:

- import authors from CSV;
- export authors to CSV/XLSX;
- import content groups from CSV;
- export content groups to CSV/XLSX;
- import content items from CSV;
- export content items to CSV/XLSX;
- create new records;
- update existing records by stable `reference_key`;
- resolve item-group and item-author relationships by reference key;
- provide example CSV downloads;
- preserve Hebrew text and Markdown;
- return failed rows through Filament's standard failed-row output.

Remote files are not downloaded during Slice 0 imports. Covers are uploaded manually.

## Minimal queue requirement

Native Filament import/export features use queued job batches and database notifications.

Slice 0 therefore includes:

- database queue configuration;
- queue jobs table when absent;
- job batches table;
- notifications table;
- Filament Actions import/export migrations;
- database notifications enabled in the Admin panel;
- documented local `queue:work` command.

It does not include custom queue observability, retry controls, or failure-management UI.

## Localization and direction

- Hebrew is the default UI locale.
- English is available.
- all interface strings use translation files;
- Hebrew pages use RTL direction;
- content remains Unicode-safe, including Hebrew diacritics;
- the initial content-language metadata defaults to Hebrew;
- type labels entered by administrators are ordinary content values.

Typography is not a Slice 0 blocker. Use a Filament-compatible font that renders Hebrew and Hebrew diacritics correctly; Varela Round is an acceptable initial choice.

## Security requirements

- admin routes require authentication;
- the public panel is read-only;
- draft data is excluded at query and record-resolution level;
- stored Markdown is sanitized before raw HTML output;
- arbitrary embed HTML is never accepted;
- embed URLs must use HTTPS and pass an approved-host policy;
- imports are administrator-only;
- import validation applies per row;
- export access is administrator-only;
- large transcript fields are not enabled in exports by default;
- no secrets or credentials appear in imported/exported data.

## Deliberately deferred scope

- Filament Shield
- volunteer, auditor, and moderator panels
- custom permissions
- approval stages
- workflow history and transcript revisions
- categories and tags
- request forms
- provider drivers and automatic URL analysis
- metadata APIs
- local media upload
- timestamp and speaker syntax
- synchronized player
- transcript studio
- activity/action/error logs
- retry and replay management
- analytics
- notifications unrelated to import/export completion
- comments
- static CMS pages
- advanced/full-text search
- legacy cover download/import

## Definition of done

Slice 0 is done when:

1. a fresh database can be migrated and seeded;
2. an administrator can create all three record types manually;
3. an administrator can import all three record types;
4. an administrator can export all three record types;
5. imported relationships resolve correctly;
6. an administrator can publish a group and item;
7. a guest can browse and read the published item;
8. drafts remain inaccessible by direct URL;
9. Markdown is rendered safely;
10. Hebrew RTL presentation is usable;
11. automated tests pass;
12. Pint passes;
13. the frontend production build passes.
