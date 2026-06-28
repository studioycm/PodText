# Phase 02 Search and Filters Spec

## Result Semantics

Search results are `ContentItem` records. A result is public only when:

- the item is published;
- the item group is published;
- the item has an effective/main published transcription.

## Default Search Fields

Default public search covers:

- item title
- group title
- enabled content tags
- categories, including inherited group categories

Transcript body search is deferred to an explicit advanced action because it may be expensive and semantically different from item metadata search.

## Filters

Initial filters:

- category
- enabled tag
- group
- author
- provider
- publication date range by effective/main transcription `published_at`
- original publication date range
- duration range
- has media/embed

Advanced/later fields:

- item description
- transcript body
- speaker names
- source URL
- provider metadata

## Sorting

Required sort options:

- latest transcription
- oldest transcription
- title A-Z
- title Z-A
- shortest duration
- longest duration
- newest original publication
- oldest original publication

Homepage views may apply pinned-first ordering. Search pages should let explicit user sort choices take precedence when appropriate.

## UI

Desktop:

- prominent search field
- active filter chips
- collapsed advanced filters
- Apply and Clear actions
- result count

Mobile:

- search field
- filter drawer
- accessible Apply and Clear actions

Persist search, sort, and important filters in the URL where practical.

## Tests Required Later

- Default search matches item title, group title, categories, and enabled tags.
- Disabled tags are excluded.
- Public search hides draft/no-transcription items.
- Sorts use effective/main transcription dates.
- URL state is preserved for key filters.
