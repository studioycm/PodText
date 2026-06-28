# Prompt 11: Phase 02 Public Homepage and Search

Work sequentially after Prompt 10 is complete and committed.

Implement the public homepage and search/listing experience.

Required:

- public results are `ContentItem` records;
- item visibility requires published group, published item, and effective/main published transcription;
- "latest transcriptions" sorts items by effective/main transcription `published_at`;
- pinned item ordering applies where appropriate;
- category/tag landing pages list items;
- default search covers item title, group title, enabled tags, and categories;
- filters, sort, pagination, result count, empty states, and URL state;
- desktop and mobile responsive filter UI.

Do not implement item page media overhaul, dashboards, analytics/search logging, or studio features.

Tests must cover guest access, visibility rules, search fields, filters, sort order, pinned ordering, category/tag pages, disabled tags, URL state, RTL markers, and empty states.

Run the project quality gate required by `AGENTS.md` before committing.
