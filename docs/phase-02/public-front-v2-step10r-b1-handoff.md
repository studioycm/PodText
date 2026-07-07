# Public Front v2 Step 10R-B1 Handoff

## Purpose

Step 10R-B1 fixes the admin card-template selection path after the render context adoption. Custom templates should be available in relevant settings selects by family, and the public content settings page should expose newly added templates in dependent podcast selects during the same editing session when the unsaved state is safe.

## What Was Implemented

- Added `PublicFrontCardTemplateResolver::optionsForFamily()` for context-backed `key => label` select options.
- Added `PublicFrontCardTemplateResolver::optionsFromTemplates()` for family-scoped options from an explicit normalized template array.
- Updated podcast settings template selects:
  - `podcasts_page.template_key` now reads `content_group` options from normalized current `card_templates` form state.
  - `podcasts_page.item_template_key` now reads `content_item` options from normalized current `card_templates` form state.
- Marked the custom template family select and card-template repeater as live so same-session option updates are available.
- Routed homepage section `display_config.template_key` options through the resolver option API.
- Added focused Pest coverage for saved template options, same-session unsaved options, wrong-family exclusion, unsafe label fallback, and contributor template setting deferral.

## Files Changed

- `app/Support/PublicFront/Cards/PublicFrontCardTemplateResolver.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `app/Filament/Resources/HomepageSections/Schemas/HomepageSectionForm.php`
- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `docs/research/public-front-v2/16-step10r-b1-card-template-select-options-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-b1-implementation-plan.md`
- `docs/phase-02/public-front-v2-step10r-b1-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

## Final Public API For Later Steps

- `PublicFrontCardTemplateResolver::optionsForFamily(string $family): array`
  - Returns default templates plus saved custom templates for the requested family.
  - Returns an empty array for unsupported families.
- `PublicFrontCardTemplateResolver::optionsFromTemplates(array $templates, string $family): array`
  - Returns default templates plus supplied custom templates for the requested family.
  - Intended for already-normalized transient settings state.

## Settings / Schema Changes

- No database schema changes.
- No settings keys were added.
- No contributor template key setting was added. Contributor template selection remains deferred until a later renderer/schema step intentionally adds a contributor template setting.

## Rendering Behavior

- Public rendering behavior is unchanged.
- Card template data attributes and resolver fallback behavior remain compatibility-only until Step 10R-B2/B3 implement visible part rendering.
- Homepage section and podcast settings admin selects now use the same family-scoped option logic as runtime template resolution.

## Tests Added / Updated

- Updated `tests/Feature/PublicFrontCardTemplateBuilderTest.php`.
- Added coverage that:
  - saved custom `content_item` templates appear in podcast item template options;
  - saved custom `content_group` templates appear in podcast index template options;
  - saved custom templates appear in homepage section template options for their selected family;
  - wrong-family templates do not appear in unrelated selects;
  - unsaved same-session settings page templates appear after normalization;
  - unsafe labels are not exposed as raw option labels;
  - contributor template settings are absent/deferred in the current schema.

## Security / Fallback Behavior

- Transient settings-page `card_templates` state is normalized through `PublicFrontConfigValidator` before it becomes select options.
- Invalid keys, invalid families, raw unsafe labels, and wrong-family templates do not leak into unrelated selects.
- Existing finite maps for template families, parts, sources, attributes, layout, density, image size, title size, icons, and URL targets remain authoritative.
- No raw Tailwind classes, CSS, Blade paths, PHP class names, raw HTML, scripts, SQL, iframe HTML, or unsafe URLs are stored or rendered by this mini-step.

## Blueprint / Audit Deviations

- Contributor template settings are documented as deferred because the current `contributors_page` schema does not include a contributor template key setting. This mini-step did not invent a new setting.
- Same-session custom template visibility was fixed for the public content settings page. Homepage section resource selects use saved settings, which matches the separate-resource lifecycle.

## Effect On Later Mini-Steps

- Step 10R-B2 can reuse the resolver option methods while implementing visible `content_item` card part rendering.
- Step 10R-B3 can add contributor/group renderer wiring without re-solving select option filtering.
- Step 10R-B4 can converge legacy scalar card options with template rendering using the resolver as the stable template option source.

## Open Questions

- Which later mini-step should add contributor template key settings for contributor directory/top-transcriber cards if the renderer needs admin-selectable contributor templates.
- Whether homepage section create/edit screens should ever consume unsaved `PublicContentSettings` state from another browser tab. Current behavior intentionally uses saved settings only.

## Quality Gate Summary

- `php artisan test tests/Feature/PublicFrontCardTemplateBuilderTest.php tests/Feature/PublicDisplaySectionsLoopersTest.php`: passed, 29 tests.
- `vendor/bin/pint --dirty --format agent`: fixed formatting/import ordering in `tests/Feature/PublicFrontCardTemplateBuilderTest.php`.
- `php artisan test`: passed, 231 tests.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed, 0 issues.
- `npm run build`: passed.
- `git diff --check`: passed.

## Commit Hash

Pending final commit in this run: `fix: expose custom public card templates in settings`.
