# Public Front v2 Step 10R-B3 Handoff

## Purpose

Step 10R-B3 makes `content_group` and `contributor` card templates visibly control rendered public card parts. It mirrors the Step 10R-B2 content item presenter pattern and moves group/contributor card data preparation out of Blade where practical.

## What Was Implemented

- Added `App\Support\PublicFront\Cards\PublicContentGroupCardPresenter`.
- Added `App\Support\PublicFront\Cards\PublicContributorCardPresenter`.
- Extended `PublicFrontCardTemplateRenderer` with supported part filtering and presentation maps for `content_group` and `contributor` card families.
- Updated `resources/views/components/public/content-group-card.blade.php` to render prepared group card parts.
- Updated `resources/views/components/public/contributor-card.blade.php` to render prepared contributor card parts in both full and compact selector modes.
- Added focused B3 tests for podcast index cards, homepage content group sections, contributor directory cards, homepage contributor cards, and top-transcriber selector cards.

## Files Changed

- `app/Support/PublicFront/Cards/PublicContentGroupCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContributorCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRenderer.php`
- `resources/views/components/public/content-group-card.blade.php`
- `resources/views/components/public/contributor-card.blade.php`
- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `docs/research/public-front-v2/16-step10r-b3-group-contributor-card-renderers-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-b3-implementation-plan.md`
- `docs/phase-02/public-front-v2-step10r-b3-handoff.md`
- `docs/phase-02/public-front-v2-step10r-b2-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

## Final Public API For Later Steps

- `PublicFrontCardTemplateRenderer::contentGroupPresentation(PublicFrontCardTemplate $template): array`
  - Returns safe presentation classes/metadata for group cards.
- `PublicFrontCardTemplateRenderer::contentGroupParts(PublicFrontCardTemplate $template): array`
  - Returns ordered, visible, supported `content_group` parts and excludes image parts when image size is hidden.
- `PublicFrontCardTemplateRenderer::contributorPresentation(PublicFrontCardTemplate $template, bool $compact = false): array`
  - Returns safe presentation classes/metadata for full or compact contributor cards.
- `PublicFrontCardTemplateRenderer::contributorParts(PublicFrontCardTemplate $template, bool $compact = false): array`
  - Returns ordered, visible, supported `contributor` parts and filters `action_link` / `description` in compact selector mode.
- `PublicContentGroupCardPresenter::present(ContentGroup $group, PublicFrontCardTemplate $template, array $displayConfig = []): array`
- `PublicContributorCardPresenter::present(Author $author, string $fullPageUrl, PublicFrontCardTemplate $template, bool $compact = false, bool $selected = false): array`

## Settings / Schema Changes

- No database schema changes.
- No new settings keys.
- Existing `card_templates`, `podcasts_page.template_key`, and homepage section `display_config.template_family/template_key` now visibly affect group/contributor card output where those templates are resolved.
- Contributor template selection settings remain deferred because no contributor template key setting exists yet; overriding `default_contributor` or using homepage section template selection works.

## Rendering Behavior

- `/podcasts` cards now render safe `content_group` template parts.
- Homepage content group sections now render safe `content_group` template parts.
- Contributor directory compact cards now render safe contributor template parts except action links and descriptions, which are intentionally skipped to avoid nested links/content-heavy button cards.
- Top-transcriber selector cards use the same compact contributor card behavior.
- Homepage contributor sections can render full contributor cards with custom text, title, metadata, description, and action links.
- If a supported group/contributor template has no visible renderable parts after validation/filtering, the presenter renders a minimal title fallback.

## Tests Added / Updated

- Updated `tests/Feature/PublicFrontCardTemplateBuilderTest.php`.
- Added coverage that:
  - custom `content_group` templates visibly affect `/podcasts`;
  - custom `content_group` templates visibly affect homepage content group sections;
  - custom `contributor` templates visibly affect contributor directory cards;
  - custom `contributor` templates visibly affect homepage contributor cards;
  - custom `contributor` templates visibly affect top-transcriber selector cards;
  - unsafe custom text remains absent;
  - hidden description parts remain absent;
  - public Filament Tables remain absent.

## Security / Fallback Behavior

- Rendering remains constrained to app-owned presenters and Blade branches.
- JSON settings still cannot provide raw Tailwind classes, raw CSS, Blade paths, PHP class names, raw HTML, iframe HTML, scripts, SQL, or unsafe URLs.
- `custom_text` renders only escaped plain text.
- Public URLs are generated through app route helpers.
- Group cover URLs continue to use the public disk.
- Invalid or unsupported parts are ignored; title fallback prevents blank cards.

## Blueprint / Audit Deviations

- Full card-options convergence is not implemented here; Step 10R-B4 owns composition with `PublicContentCardOptions`.
- Transcriber attribution correction is not implemented here; Step 10R-C1 owns transcription-author attribution.
- Full semantic layout-token normalization is not implemented here; Step 10R-C2 owns broader card layout consistency.
- Compact contributor cards intentionally skip `action_link` and `description` parts because the compact card remains a Livewire selector button.

## Effect On Later Mini-Steps

- Step 10R-B4 can now converge scalar card options against presenters for all three template families.
- Step 10R-C1 can focus on attribution data sources without changing group/contributor card Blade structure.
- Step 10R-C2 can centralize semantic layout tokens around presenter output across item, group, and contributor cards.
- Step 9F rich sections/footer work can reuse the presenter pattern after Step 10R-A/B/C finish.

## Open Questions

- Whether compact contributor cards should eventually get a dedicated template setting or compact-only template family.
- Whether B4 should expose labels/icons in public card parts or leave them as admin metadata until C2 layout tokens are complete.

## Quality Gate Summary

- `php artisan test tests/Feature/PublicFrontCardTemplateBuilderTest.php`: passed, 20 tests.
- `php artisan test tests/Feature/PublicPodcastsGroupsUxTest.php tests/Feature/PublicContributorsTopTranscribersUxTest.php tests/Feature/PublicDisplaySectionsLoopersTest.php`: passed, 33 tests.
- `php artisan test tests/Feature/PublicFrontCardTemplateBuilderTest.php tests/Feature/PublicPodcastsGroupsUxTest.php tests/Feature/PublicContributorsTopTranscribersUxTest.php tests/Feature/PublicDisplaySectionsLoopersTest.php`: passed, 53 tests.
- `vendor/bin/pint --dirty --format agent`: passed.
- `php artisan test`: passed, 236 tests.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed, 0 issues.
- `npm run build`: passed.
- `git diff --check`: passed.

## Commit Hash

Pending final commit in this run: `feat: render group and contributor card templates`.
