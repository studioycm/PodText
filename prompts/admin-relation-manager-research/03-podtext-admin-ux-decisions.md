# PodText Admin UX Decisions To Evaluate

The research must evaluate and make a recommendation for each of these.

## 1. `TranscriptionResource`

Evaluate and likely recommend:

- Keep a standalone global `TranscriptionResource`.
- Use it for searching and managing all transcriptions.
- Useful filters:
  - content item;
  - content group;
  - author;
  - status;
  - language;
  - published date;
  - featured/main state.

## 2. `ContentItemResource` relation management

Evaluate and likely recommend:

- Add `ContentItemResource\RelationManagers\TranscriptionsRelationManager`.
- Use it for `ContentItem::transcriptions()`.
- Make it the primary admin UX for adding/editing transcript bodies for one item.
- Do not use legacy `content_items.transcript_markdown` for new transcript edits.

## 3. Combined tabs

Research and recommend whether `EditContentItem` should use combined content/form + relation manager tabs.

Evaluate:

- `hasCombinedRelationManagerTabsWithContent(): true`;
- custom form/content tab label, e.g. Hebrew translation key for “Item details”;
- transcriptions tab label, e.g. “תמלולים” via translation key;
- transcriptions tab icon;
- tab badge count;
- deferred badge if the count query may be expensive;
- whether content tab appears before or after relation tabs.

## 4. `TranscriptionsRelationManager` table

Required recommendation:

- title/fallback label;
- author;
- status badge;
- `published_at` formatted `dd/mm/yyyy HH:mm`;
- language;
- word count;
- featured/main indicator;
- updated at.

## 5. `TranscriptionsRelationManager` filters

Required recommendation:

- status;
- author;
- language;
- published/draft;
- featured/not featured.

## 6. `TranscriptionsRelationManager` actions

Required recommendation:

- header create action;
- row edit action;
- set as featured/main action;
- optional duplicate/copy as draft if examples support it cleanly;
- open full `TranscriptionResource` edit page if useful;
- keep bulk actions minimal unless a safe use case is clear.

## 7. `TranscriptionsRelationManager` form

Required recommendation:

- author searchable relationship select;
- title;
- language code;
- status;
- `published_at` with `dd/mm/yyyy HH:mm` and `Asia/Jerusalem`;
- Markdown editor for `transcript_markdown`;
- technical/derived fields hidden, read-only, or under advanced section.

## 8. Relation-manager validation

Required recommendation:

- relation manager automatically attaches to the current item;
- create action should not expose `content_item_id` when owner context supplies it;
- set-as-featured validates same item;
- only a published transcription can become publicly effective, or the UI must explain unpublished featured fallback;
- draft transcriptions never appear publicly.

## 9. Redirect behavior

Research and recommend:

- Standalone Create pages redirect to index/list after successful create unless there is a strong reason to stay on edit.
- Standalone Edit pages redirect to index/list after save if this matches the intended admin flow.
- Relation manager create/edit actions stay on the owner item edit page.
- Decide whether to disable “create another”.
- Use Resource `getUrl('index')`, not hard-coded routes.

## 10. Relation page alternatives

Research and recommend when to use:

- relation manager on edit page;
- separate `ManageRelatedRecords` page;
- standalone Resource;
- repeater inside form.

Expected PodText recommendation:

- Prompt 09 uses a relation manager for item-scoped transcription editing.
- Standalone `TranscriptionResource` remains for global search/filtering.
- Dedicated relation pages are future optional if transcript management becomes too large.
- Do not use a Repeater for full transcript Markdown because the child form is large.
