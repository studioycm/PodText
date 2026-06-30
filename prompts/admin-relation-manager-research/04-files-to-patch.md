# Files To Patch

Patch only Markdown files.

## 1. `docs/phase-02/blueprints/09-admin-content-management-blueprint.md`

Add an explicit section:

```md
## Relation Managers

### `ContentItemResource\RelationManagers\TranscriptionsRelationManager`

Purpose:
- Manage all `ContentItem::transcriptions()` records directly from the item edit page.
- Primary admin UX for adding/editing transcript bodies for one item.
- Do not use legacy `content_items.transcript_markdown` for new edits.

Combined tabs:
- Research decision required: whether `EditContentItem` should combine content/form tab with relation manager tabs.
- If yes, plan `hasCombinedRelationManagerTabsWithContent(): true`.
- Customize the content tab label to a Hebrew-friendly translation key.
- Customize the transcriptions tab label/icon/badge.
- Consider deferred badges if badge queries may be expensive.
- Choose content tab position and document it.

Table:
- title/fallback label;
- author;
- status badge;
- `published_at` formatted `dd/mm/yyyy HH:mm` in `Asia/Jerusalem`;
- language;
- word count;
- featured/main indicator;
- updated at.

Filters:
- status;
- author;
- language;
- published/draft;
- featured/not featured.

Actions:
- create transcription;
- edit transcription;
- set as featured/main;
- optional duplicate/copy as draft if simple and covered by tests;
- open full `TranscriptionResource` edit page if useful.

Form:
- `author_id` searchable relationship select;
- `title`;
- `language_code`;
- `status`;
- `published_at`;
- `transcript_markdown` Markdown editor;
- technical/derived fields read-only or hidden unless needed.

Rules:
- A transcription created through the relation manager is automatically attached to the current content item.
- The featured action must validate that the transcription belongs to the current item.
- Only a published transcription can become publicly effective.
- Draft transcriptions must never appear publicly.
- Date-time fields use `dd/mm/yyyy HH:mm` and `Asia/Jerusalem`.
- All labels, hints, helper text, and validation messages use translation keys.

Tests:
- Relation manager renders on item edit page.
- Admin can create a transcription for an item.
- Admin can edit a transcription.
- Admin can set a transcription as featured/main.
- Featured action rejects or hides invalid cross-item choices.
- Draft transcription is not public.
- Item form no longer writes to legacy transcript field.
```

Adapt wording after research. Do not add unresearched method names unless official docs confirm them.

## 2. `prompts/09-phase-02-admin-content-management.md`

Add a new section:

```md
## Relation manager and Resource UX research contract

Before implementing admin Resources, read:

- `docs/research/filament-examples-admin-resource-relation-managers.md`
- `docs/phase-02/blueprints/09-admin-content-management-blueprint.md`

Required:
- Implement the researched `TranscriptionsRelationManager` plan unless it is marked deferred.
- Use the researched decision for combined content/relation tabs.
- Use the researched redirect behavior for standalone Create/Edit pages.
- Use Resource URLs, not hard-coded route names.
- Include a Blueprint completion checklist section for relation managers and redirects.
```

Also update required tests to include relation-manager tests from the research.

## 3. `docs/phase-02/answers-coverage-matrix.md`

Add rows for:

- admin Resource relation manager research;
- `ContentItemResource` transcriptions relation manager;
- combined relation manager tabs with content form;
- content/form tab label customization;
- relation manager tab badge/icon;
- after-create redirect to index/list;
- after-edit redirect to index/list;
- relation manager create/edit staying on item edit page;
- relation page vs relation manager decision;
- no Repeater for full transcript Markdown;
- Prompt 09 tests for relation manager.

## 4. `.ai/guidelines/transcriptions.md`

Add:

- Transcription body management should be available from the owning item admin page through a relation manager when implemented.
- The legacy item transcript field must not be reintroduced in item forms.
- Standalone `TranscriptionResource` is useful for global search, but item-scoped editing should prefer the item relation manager.

## 5. `.ai/guidelines/tooling-quality.md`

Add:

- Relation manager work must use current Filament 5 relation-manager APIs.
- If a prompt uses combined relation tabs with content, it must use the official Filament method names for the installed version.
- Final reports for Prompt 09 must state whether combined tabs, relation manager badges, and redirect behavior were implemented.

## 6. `docs/phase-02/feature-map.md`

Add under Prompt 09:

- Admin Resource UX includes researched relation manager patterns.
- Especially `ContentItemResource` → `TranscriptionsRelationManager`.
- Include combined content/relation tabs and create/edit redirect behavior if research supports them.

## 7. `prompts/README.md`

Do not change the active prompt order.

Add a note:

- This research prompt is a pre-Prompt-08 docs-only refinement.
- Historical state: this was true when the research task ran. Current state is Prompts 08 and 09 complete, admin UX repair complete, and Prompt 10 next after review and a clean quality baseline.

## 8. Optional if useful: `docs/phase-02/tooling-and-quality-gates.md`

Add a short note that relation-manager implementation must satisfy FilaCheck Pro concerns:

- searchable relationship selects;
- action namespaces;
- no N+1 table closures;
- no deprecated Filament relation manager APIs;
- no string icons if Heroicon enum is expected.
