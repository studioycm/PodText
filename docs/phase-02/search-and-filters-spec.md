# Phase 02 Search and Filters Spec

## Result Unit

Every public homepage/search/category/tag result is a `ContentItem`.

Public visibility requires:

- published group;
- published item;
- effective/main published transcription.

## Search Fields

Default immediate search:

- item title;
- content group title;
- enabled content tags;
- direct and inherited categories.

Deferred/advanced search:

- transcript body;
- item description;
- author name;
- speaker names;
- media metadata;
- provider;
- source URL.

Transcript full-text search is an explicit action/filter mode, not default live search.

## Filters

Required filters:

- category with descendant matching;
- enabled content tag;
- content group;
- author;
- provider;
- effective transcription date range;
- original publication date range;
- duration range;
- has embed/source media.

## Sorting

Required sort options:

- latest transcription;
- oldest transcription;
- title A-Z;
- title Z-A;
- duration shortest;
- duration longest;
- original newest;
- original oldest.

Homepage may apply pinned-first order. Explicit search sort may override pinned-first.

## UX

Desktop:

- full search input;
- visible key filters/chips;
- collapsed advanced filters;
- Apply and Clear actions;
- result count.

Mobile:

- search input;
- filter drawer;
- Apply and Clear actions;
- readable cards without overlap.

Persist search, sort, and significant filters in the URL where practical.

## Blueprint

See `docs/phase-02/blueprints/11-public-homepage-search-blueprint.md`.
