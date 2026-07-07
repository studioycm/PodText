# Public Front v2 Step 10R Next Fix Sequence

## Recommendation

Use this sequence:

1. Step 10R-A: Public front settings snapshot / render-context service.
2. Step 10R-B: Card-template rendering and custom template selection fixes.
3. Step 10R-C: Transcriber attribution and card layout consistency fixes.
4. Step 9F / 10F: Footer and rich section builder foundation.
5. Step 11: Seeders, demo data, assets, cleanup.
6. Prompt 13: Dashboard metrics readiness.

This is the recommended next path based on actual code inspection. Step 9F / 10F should wait until Step 10R-A/B/C are implemented, not only documented.

## Why This Order

The current code already has public pages, settings JSON, card template definitions, and contributor discovery. The remaining gaps are in the shared rendering layer:

- settings are read through several paths instead of one normalized render context;
- card templates resolve, but only content item cards get limited visual presentation changes;
- content group and contributor cards mostly expose compatibility metadata;
- public item cards still display item-level authors while contributor counts and transcript tabs use transcription authors;
- card grid and card-section layout decisions are duplicated in Blade;
- Step 9F rich sections/footer would need exactly the same context, renderer, form CTA, and layout-token boundaries.

Running footer/rich sections first would add another settings/rendering surface before the existing public-front cards are stable.

## Step 10R-A: Settings Snapshot / Render Context

Objective:

- Add a request-scoped `PublicFrontRenderContext` and factory.
- Normalize `PublicContentSettings` once per request.
- Expose typed/group accessors for:
  - card templates by family;
  - display defaults;
  - menu config;
  - route labels;
  - public form definitions;
  - podcast page config;
  - contributor page config;
  - future footer config.
- Move Livewire components, public page classes, menu/forms/about renderers, and card/template resolvers toward the context.
- Keep persistent cache optional unless performance measurements justify it.
- Add `PublicContentSettings` `afterSave` invalidation if any derived/persistent cache is introduced.

Tests:

- repeated consumers in one request use one normalized snapshot;
- settings saved through the Filament SettingsPage appear in the next public request;
- route labels/menu/form definitions do not stale after save;
- invalid settings still normalize to safe defaults.

Do not do in this prompt:

- do not implement card part rendering;
- do not add footer/rich sections;
- do not change attribution schema.

## Step 10R-B: Card Template Rendering

Objective:

- Convert the current card-template foundation into a real controlled part renderer.
- Support the three existing template families:
  - `content_item`;
  - `content_group`;
  - `contributor`.
- Keep JSON safe by allowing only finite families, part types, sources, attributes, layout tokens, image tokens, title/description clamps, and display flags.
- Remove or reduce overlap between `PublicContentCardOptions` and `PublicFrontCardTemplateRenderer`.
- Ensure custom templates visibly affect:
  - homepage latest cards;
  - search/category/tag item cards;
  - podcast detail item cards;
  - podcast/group index cards;
  - contributor cards;
  - contributor item cards;
  - top-transcriber preview cards.
- Improve template select behavior so saved custom templates are reliably available, and same-session settings-page UX is clarified or fixed.

Tests:

- custom content item template output changes actual HTML on homepage and podcast detail pages;
- custom content group template output changes actual podcast index cards;
- custom contributor template output changes contributor cards;
- hidden/reordered parts affect rendered output, not only data attributes;
- unsafe raw Blade/CSS/classes/HTML are rejected or normalized away;
- admin preview is deferred unless it reuses the same renderer.

Do not do in this prompt:

- do not introduce generic CMS fields;
- do not use raw Tailwind/class strings from JSON;
- do not add footer/rich sections.

## Step 10R-C: Transcriber Attribution And Card Layout

Objective:

- Correct public card attribution so transcribers come from transcription authors, not item-level authors.
- Use current schema first:
  - `Transcription::author()`;
  - `ContentItem::effectiveTranscription()`;
  - contributor-specific loaded `transcriptions` where relevant.
- Keep `ContentItem::authors` as item participants/credits only if product copy explicitly wants that public field.
- Add eager loading for effective transcription author relationships in public item queries and contributor item queries.
- Centralize card layout presentation:
  - equal-row policy;
  - image aspect ratios;
  - title and description line clamps;
  - metadata row reservation;
  - duplicate group thumbnail behavior;
  - shared grid column/gap maps.

Tests:

- when an item author and transcription author differ, item cards show the transcription author in transcriber context;
- item-level authors are not mislabeled as transcribers;
- contributor directory preview and contributor detail retain contributor-specific transcription titles;
- top-transcriber previews use selected contributor context;
- cards without item images use group/podcast covers consistently;
- duplicate group thumbnails stay suppressed when the group cover is already the item image fallback.

Do not do in this prompt:

- do not create an `author_transcription` pivot;
- do not change import/export schemas;
- do not run Step 11 cleanup.

## Future Multi-Transcriber Prompt

Only schedule this if Yoni confirms that one transcription can require multiple transcribers.

Likely scope:

- add `author_transcription` pivot with unique `(author_id, transcription_id)`;
- backfill from non-null `transcriptions.author_id`;
- decide whether `transcriptions.author_id` remains as primary/legacy author or is deprecated;
- update transcription admin form and relation manager;
- update imports/exports with portable author reference keys;
- change contributor discovery/counts from `transcriptions.author_id` to pivot joins;
- update transcript viewer and public card queries;
- add migration, admin, import/export, public query, and attribution tests.

## Step 9F / 10F: Footer And Rich Section Builder

Run only after Step 10R-A/B/C.

Scope should remain:

- JSON-first;
- no generic CMS;
- no footer/menu/page models;
- constrained `rich_columns` homepage section;
- constrained `footer_config` settings group;
- app-owned renderers;
- safe Markdown/RichEditor rendering;
- Step 6 public form CTA support;
- fixed semantic class maps.

The renderer should reuse:

- `PublicFrontRenderContext`;
- card/template part renderer where cards appear in rich sections;
- public form CTA resolver;
- route-label resolver;
- safe Markdown/RichEditor renderer;
- shared card/grid layout token maps.

## Step 11

Run after Step 9F / 10F if footer/rich sections are implemented before demo cleanup.

Scope:

- seed stable demo settings;
- seed stable demo footer/rich sections if Step 9F / 10F landed;
- seed demo card templates only after the renderer is real;
- normalize demo assets and fallback images;
- remove temporary inconsistencies.

## Prompt 13

Run after public-front rendering and seed/demo state are stable.

Prompt 13 dashboard metrics should count real implemented states, not planned footer/rich/card-template states.

## Future Prompt Drafts

Short implementation prompt drafts already exist:

- `docs/phase-02/prompts/public-front-v2-step10r-a-settings-render-context.md`
- `docs/phase-02/prompts/public-front-v2-step10r-b-card-template-rendering.md`
- `docs/phase-02/prompts/public-front-v2-step10r-c-transcriber-attribution-card-layout.md`

Do not combine these into one giant implementation prompt.
