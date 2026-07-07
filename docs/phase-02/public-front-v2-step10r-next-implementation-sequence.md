# Public Front v2 Step 10R Next Implementation Sequence

## Recommendation

Use this sequence:

1. Step 10R-A: public-front settings snapshot / render-context service.
2. Step 10R-B: card-template rendering and custom template selection fixes.
3. Step 10R-C: transcriber attribution and card layout consistency fixes.
4. Step 9F / 10F: footer and rich section builder foundation.
5. Step 11: seeders, demo data, assets, cleanup.
6. Prompt 13: dashboard metrics readiness.

This order should replace the previous "Step 9F / 10F next unless skipped" recommendation until Step 10R-A/B/C are complete.

## Why Step 10R Comes First

The current public-front layer has a working JSON settings foundation, but the rendering layer consumes it inconsistently:

- some paths read `PublicFrontConfigReader`;
- some paths read `PublicContentSettings` directly;
- `PublicContentCardOptions` reads scalar settings separately;
- Blade views contain non-trivial config and model preparation logic;
- card templates resolve but do not drive real card parts across all families.

Footer and rich-section builders will need the same settings snapshot, card/layout token maps, safe renderer boundary, and public form CTA resolution. Running Step 9F / 10F first would add another settings group and another renderer surface before the current public-front architecture is stable.

## Step 10R-A Objective

Create a request-scoped public-front render context.

Scope:

- add `PublicFrontRenderContext`;
- add a factory/binding that validates `PublicContentSettings` once per request;
- move public settings consumers toward the context;
- keep persistent derived cache optional unless a clear performance need appears;
- add explicit invalidation hook if any derived cache is introduced;
- keep all output behavior unchanged except stale/repeated settings reads.

Tests:

- context resolves normalized groups;
- repeated reads in one request reuse the same normalized config;
- saved settings are visible on the next request/context;
- template selects and public page labels are not stale after save.

## Step 10R-B Objective

Make card templates visibly affect public cards.

Scope:

- make custom templates available in podcast/homepage/contributor template selects after save and, if feasible, during the same settings form session;
- implement safe finite part rendering for content item cards;
- add equivalent controlled renderer support for content group and contributor cards;
- fold or adapt `PublicContentCardOptions` so it no longer conflicts with templates;
- keep raw Blade/CSS/classes/PHP/HTML out of JSON;
- add focused tests for real output, not only `data-card-template-*` metadata.

Tests:

- homepage latest cards render custom parts;
- podcast detail item cards render custom parts;
- podcast/group index cards render custom parts;
- contributor item cards render custom parts;
- top transcriber selector/preview cards render custom parts;
- hidden/reordered parts affect actual HTML.

## Step 10R-C Objective

Correct transcriber attribution and normalize card layout behavior.

Scope:

- public item cards use effective/main transcription author by default;
- contributor-context cards use contributor-specific transcription authors/titles where relevant;
- item-level `ContentItem::authors` is not mislabeled as transcribers;
- item detail header stops showing item authors as transcribers;
- eager load transcription authors for card paths;
- introduce semantic layout defaults/class maps for consistent rows and card sections;
- do not change schema in this step.

Tests:

- effective transcription author appears when item authors differ;
- item authors do not appear as transcribers;
- contributor preview/full page keeps grouped transcription titles;
- top transcriber preview uses the selected contributor context;
- duplicate group thumbnails stay suppressed when item image falls back to group cover;
- card title/description/metadata layout tokens are reflected in safe data attributes or output structure.

## Step 9F / 10F Objective

After Step 10R, implement footer and constrained rich sections.

Scope:

- add `footer_config` to public settings;
- add constrained `rich_columns` homepage section support;
- reuse `PublicFrontRenderContext`;
- reuse app-owned safe rich/Markdown renderer;
- use fixed semantic class maps;
- support Step 6 public form CTA;
- do not create footer/menu/public page models.

## Step 11 Objective

After final public-front schema/rendering is stable:

- seed demo footer/rich section content if Step 9F / 10F lands;
- seed stable card templates if wanted;
- normalize demo images/assets;
- remove temporary/demo inconsistencies.

## Prompt 13 Objective

Only after public-front state is stable:

- dashboard metrics can count real public/front editorial states;
- metrics can include card/template/settings warnings only if they reflect implemented behavior.

## Future Prompt Filenames

Short implementation prompt drafts were created:

- `docs/phase-02/prompts/public-front-v2-step10r-a-settings-render-context.md`
- `docs/phase-02/prompts/public-front-v2-step10r-b-card-template-rendering.md`
- `docs/phase-02/prompts/public-front-v2-step10r-c-transcriber-attribution-card-layout.md`

Do not combine them into one implementation prompt.
