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
- enabled content tag names;
- direct and inherited category names.

Deferred/advanced search:

- author name;
- item description;
- transcript body;
- speaker names;
- metadata;
- external provider;
- original source URL.

Transcript full-text search is an explicit action/filter mode, not default live search.

Author and provider may remain initial dropdown filters when they are cheap metadata filters. They are not part of the default full-text search surface.

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

Sort labels must be translation keys and Hebrew-first.

Date range filter inputs and result date displays should use Hebrew/Israel behavior:

- date format: `dd/mm/yyyy`;
- date-time format: `dd/mm/yyyy HH:mm` where a time is shown;
- UI timezone: `Asia/Jerusalem`;
- storage remains Laravel's normal date storage convention.

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
