# Public Front v2 Step 10R-B2 Handoff

## Purpose

Step 10R-B2 makes `content_item` card templates visibly control rendered public card parts. It moves content item card data preparation out of Blade into app-owned presenter/renderer code while keeping public output safe and broadly compatible with the existing default card.

## What Was Implemented

- Added `App\Support\PublicFront\Cards\PublicContentItemCardPresenter`.
- Extended `PublicFrontCardTemplateRenderer` with ordered, visible `content_item` part resolution.
- Updated the public content item card Blade component to render prepared parts instead of hard-coding every card section.
- Added visible rendering support for these content-item parts:
  - `image`
  - `title`
  - `description`
  - `group_identity`
  - `transcriber_line`
  - `date_read_time`
  - `metadata_row`
  - `taxonomy`
  - `action_link`
  - `custom_text`
  - `divider`
  - `spacer`
- Preserved existing template compatibility attributes and added per-part `data-card-part` markers for rendered parts.
- Added focused tests for homepage, search/category/tag, and podcast detail item-template rendering.

## Files Changed

- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRenderer.php`
- `resources/views/components/public/content-item-card.blade.php`
- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `docs/research/public-front-v2/16-step10r-b2-content-item-card-part-renderer-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-b2-implementation-plan.md`
- `docs/phase-02/public-front-v2-step10r-b2-handoff.md`
- `docs/phase-02/public-front-v2-step10r-b1-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

## Final Public API For Later Steps

- `PublicFrontCardTemplateRenderer::contentItemParts(PublicFrontCardTemplate $template): array`
  - Returns ordered, visible, supported `PublicFrontCardPart` objects for content item cards.
  - Excludes the `image` part when the resolved template image size is `hidden`.
- `PublicContentItemCardPresenter::present(ContentItem $item, PublicContentCardOptions $options, PublicFrontCardTemplate $template, string $layout = 'cards'): array`
  - Returns prepared card presentation data, template attributes, media parts, and body parts.
  - Later B4 convergence can use this as the compatibility bridge between scalar card settings and template-driven parts.

## Settings / Schema Changes

- No database schema changes.
- No new settings keys.
- Existing `card_templates` settings now visibly drive content item part rendering where `content_item` templates are resolved.

## Rendering Behavior

- Default content item card output remains compatible: image, group identity, title, description, item-author badges, effective date, duration, categories, and tags still render when enabled by existing scalar card options.
- Custom content item templates can now visibly reorder, hide, or add supported parts.
- Homepage latest/manual/category/tag/group-item sections render their selected content item template parts.
- Search/category/tag listing cards render the resolved default content item template parts.
- Podcast detail item cards render the template selected by `podcasts_page.item_template_key`.
- If a template has no supported visible parts after validation, the presenter renders a minimal title fallback so public cards do not become blank.

## Tests Added / Updated

- Updated `tests/Feature/PublicFrontCardTemplateBuilderTest.php`.
- Added coverage that:
  - homepage content item cards render custom part order;
  - hidden description parts are absent;
  - unsafe raw-HTML `custom_text` is rejected by settings validation and does not render;
  - search, category, and tag pages render custom content item parts;
  - podcast detail item cards visibly use `podcasts_page.item_template_key`.

## Security / Fallback Behavior

- Rendering remains constrained to static app-owned Blade branches.
- JSON settings cannot provide Blade paths, PHP class names, raw HTML, raw CSS, raw Tailwind classes, SQL, iframe HTML, scripts, or arbitrary unsafe URLs.
- `custom_text` is plain text only and renders through escaped Blade output.
- Category/tag/action URLs are generated from app route helpers.
- Image URLs continue to use the existing item thumbnail or public disk group-cover fallback behavior.
- Invalid or unsupported template parts are ignored; if no supported parts remain, the card title fallback prevents blank public cards.

## Blueprint / Audit Deviations

- `transcriber_line` keeps the existing item-author display source for this mini-step. Step 10R-C1 owns correcting transcriber attribution to `Transcription::author`.
- Content group and contributor template part rendering is not implemented here; Step 10R-B3 owns those families.
- Scalar card-option convergence is not implemented here; Step 10R-B4 owns deeper composition rules.
- Part icons and label-position rendering remain limited; the renderer stores labels safely but B2 focuses on visible content item part structure, not decorative icon rendering.

## Effect On Later Mini-Steps

- Step 10R-B3 can mirror the presenter pattern for `content_group` and `contributor` families.
- Step 10R-B4 can converge `PublicContentCardOptions` by routing scalar defaults through the presenter/template layer.
- Step 10R-C1 can replace the current item-author-backed transcriber line with transcription-author data without changing the Blade control flow.
- Step 10R-C2 can centralize semantic layout tokens around the presenter’s prepared part metadata.

## Open Questions

- Whether `action_link` should gain a more specific public translation key than the existing `public.actions.view_more`.
- Whether Step 10R-B4 should render part labels/icons or leave them as admin metadata until semantic layout tokens are complete.

## Quality Gate Summary

- `php artisan test tests/Feature/PublicFrontCardTemplateBuilderTest.php`: passed, 18 tests.
- `php artisan test tests/Feature/PublicFrontCardTemplateBuilderTest.php tests/Feature/PublicDisplaySectionsLoopersTest.php tests/Feature/PublicHomepageSearchTest.php tests/Feature/PublicPodcastsGroupsUxTest.php`: passed, 57 tests.
- `php artisan test tests/Feature/PublicLatestSearchUxTest.php tests/Feature/PublicContributorDiscoveryTest.php tests/Feature/PublicContributorsTopTranscribersUxTest.php`: passed, 19 tests.
- `vendor/bin/pint --dirty --format agent`: passed.
- `php artisan test`: passed, 234 tests.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed, 0 issues.
- `npm run build`: passed.
- `git diff --check`: passed.

## Commit Hash

`e3c81de feat: render content item card template parts`
