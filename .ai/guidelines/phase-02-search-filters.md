# Phase 02 Search and Filters Guideline

- Search results are `ContentItem` records.
- Default search covers item title, group title, enabled content tags, and categories.
- Transcript body search is explicit/deferred, not live default search.
- "Latest transcriptions" means item sorting by effective/main transcription `published_at`.
- Important search, filter, and sort state should be URL-backed where practical.
- Desktop filters should use a clear search bar, chips, Apply, and Clear.
- Mobile filters should use a drawer with accessible Apply and Clear actions.
- User-selected sort can override pinned-first ordering outside homepage contexts.
