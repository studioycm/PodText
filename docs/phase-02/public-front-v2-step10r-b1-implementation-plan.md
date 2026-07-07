# Public Front v2 Step 10R-B1 Implementation Plan

## Purpose

Fix public card template selection UX so custom templates saved in JSON settings appear in the relevant admin selects, and so new unsaved templates on the public content settings page can be selected in the same session where safe.

## Scope

- Keep card templates in `PublicContentSettings` JSON settings only.
- Do not add card-template models, migrations, CMS structures, or public Filament Tables.
- Do not implement visual card-part rendering. That remains Step 10R-B2/B3.
- Do not add contributor template settings unless a schema already exists. Current schema does not include a contributor template key, so contributor template setting selection is documented as deferred.

## Implementation Steps

1. Add resolver support for family-scoped option arrays.
   - Add methods on `PublicFrontCardTemplateResolver` to return `key => label` options for a family from persisted context templates.
   - Add a companion method that accepts an explicit template array, used by settings-page transient form state.
   - Keep default templates included and filter options strictly by family.

2. Update admin settings select option sources.
   - Change `PublicContentSettings` podcast template selects to use `Get` and the current `card_templates` form state.
   - Normalize transient templates through existing public-front validation before generating options.
   - Keep saved/reloaded behavior based on context-backed resolver defaults.
   - Mark the card-template repeater and template family selects as live where needed for same-session option refresh.

3. Update homepage section template select helper.
   - Route `HomepageSectionForm::cardTemplateOptions()` through the resolver option API.
   - Preserve current source-type/default-family behavior and only improve option filtering/composition.

4. Tighten tests.
   - Assert saved custom `content_item` templates appear in podcast detail item template options.
   - Assert saved custom `content_group` templates appear in podcast index template options.
   - Assert custom templates appear in homepage section template options by selected family.
   - Assert wrong-family templates do not appear in unrelated selects.
   - Assert unsafe template values still normalize out or fall back safely.
   - Assert contributor template settings are absent/deferred in the current schema rather than introduced.

## Files Expected To Change

- `app/Support/PublicFront/Cards/PublicFrontCardTemplateResolver.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `app/Filament/Resources/HomepageSections/Schemas/HomepageSectionForm.php`
- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `docs/research/public-front-v2/16-step10r-b1-card-template-select-options-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-b1-implementation-plan.md`
- `docs/phase-02/public-front-v2-step10r-b1-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

## Quality Gate

Focused tests first:

```bash
php artisan test tests/Feature/PublicFrontCardTemplateBuilderTest.php tests/Feature/PublicDisplaySectionsLoopersTest.php
```

Final gate:

```bash
vendor/bin/pint --dirty --format agent
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
git diff --check
```
