# Public Front v2 Step 10R-V1b Implementation Plan

## Selected Step

Step 10R-V1b - Heroicon registry and shared icon picker.

Dependencies: Step 10R-V1a is complete as `4c545eb feat: add default image fallback
settings`. Step 10R-V1c remains pending and is out of scope.

## Current Repo Evidence

- Preflight was clean.
- `php artisan migrate:status` shows all migrations/settings migrations through V1a
  applied.
- The ledger, current state, and next-sequence docs agree that V1b is the first pending
  mini-step.
- The v4 enhancement plan header is v4 and schedules V1b before V1c/P1.
- `PublicFrontCardTemplateRegistry::icons()` currently exposes the 17 legacy keys.
- `PublicFrontCardIconResolver` already renders through `Heroicon` enum cases, making it
  the correct rendering boundary to extend.
- `contributors_page.cards.compact_count_icon` uses a separate one-value icon registry;
  V1b should fold it into the same shared picker/registry.

## Files Inspected

- `prompts/pre-13-prompts/public-front-v2-post-m6-ux-settings-runner-codex-prompt.md`
- `docs/phase-02/public-front-v2-step10r-v1a-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-admin-settings-enhancement-plan.md`
- `docs/phase-02/public-front-v2-transcription-display-decisions.md`
- `docs/phase-02/public-front-v2-performance-efficiency-audit.md`
- `docs/research/public-front-v2/19-admin-settings-enhancement-mcp-research.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/phase-02/ai-development-lessons.md`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRegistry.php`
- `app/Support/PublicFront/Cards/PublicFrontCardIconResolver.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Filament/Pages/PublicContentSettings.php`
- public card and podcast-identity Blade icon consumers
- existing public-front settings/card-template/contributor tests

## Boost Findings

Boost was available.

- `application_info`: Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4,
  SQLite.
- `database_schema`: V1b needs only the Spatie `settings` table; no new data table is
  required.
- `search_docs`: Filament form `Select` supports `allowHtml()`, `searchable()`,
  `getSearchResultsUsing()`, and `getOptionLabelUsing()`. The lazy callbacks are the
  correct way to avoid preloading a large option set.

## FilamentExamples Findings

Research note:
`docs/research/public-front-v2/20-step10r-v1b-mcp-research.md`.

- Access level was `search_examples` snippet/source only.
- The selected reference, **Select With Custom HTML Options**, demonstrates a
  Heroicon-backed `Select` with `allowHtml()` and safe app-generated icon HTML.
- PodText will not copy the full `options(Heroicon::cases())` preload pattern. The
  shared helper will use lazy `getSearchResultsUsing()` and `getOptionLabelUsing()`.

## Settings / Render-Context Impact

- No new settings keys are introduced.
- Existing icon values will normalize to Heroicon enum case-name strings.
- The legacy aliases remain permanent accepted inputs and render through the resolver.
- A settings migration will normalize existing stored nested icon values in:
  `card_templates`, `item_page`, and `contributors_page.cards.compact_count_icon`.
- `PublicFrontRenderContext` shape does not need a new accessor.

## Admin Impact

- Add one shared `IconSelect` helper for all public-front icon settings.
- Replace icon selects in the settings page for:
  - card-template part icons;
  - item-page podcast identity;
  - item-page info fields;
  - item-page date fields;
  - contributor compact count icon.
- Picker labels use safe app-generated HTML from `Heroicon` enum cases and include a
  visible icon preview.
- Picker results are lazy search results and do not preload all enum options into the
  settings page payload.

## Public Impact

- Public rendering continues through `PublicFrontCardIconResolver` only.
- Existing legacy icon settings continue to render.
- Normalized enum-name settings render the same visual Heroicons as their legacy aliases.
- JSON settings still never store SVG, arbitrary component names, raw classes, or raw
  icon component names.

## Query / Cache Impact

- No public database query changes are expected.
- The registry uses static per-request caches for enum lookup and HTML labels.
- No persistent cache is added; P1 owns public-front config caching.

## Exact Files To Change

- Add `app/Support/PublicFront/Icons/PublicFrontIconRegistry.php`.
- Add `app/Filament/Forms/Components/IconSelect.php`.
- Update `PublicFrontCardIconResolver`.
- Update `PublicFrontCardTemplateRegistry`.
- Update `PublicFrontConfigRegistry`.
- Update `PublicFrontConfigValidator`.
- Update `PublicContentSettings`.
- Add a settings migration for icon-token normalization.
- Update translations for helper text/search prompts if needed.
- Add/update Pest tests for registry, validator, settings page save, and bounded payload.
- Update current state, ledger, sequence, and create the V1b handoff.

## Tests

Focused:

- New V1b icon registry/settings tests.
- Existing public-front settings/card-template tests touched by icon-token expectations.
- `tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php`.

Final gate:

```bash
vendor/bin/pint --dirty --format agent
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
git diff --check
```

## Risks

- Existing tests and seeded settings use legacy aliases. Mitigation: normalize aliases and
  keep resolver compatibility.
- Filament selected labels can be blank when a value is not present in lazy search
  results. Mitigation: implement `getOptionLabelUsing()`.
- HTML option labels could create XSS risk if generated from stored strings. Mitigation:
  generate labels only from trusted enum metadata and escaped text.
- Full enum preloading could bloat settings-page payload. Mitigation: no `options()` on
  `IconSelect`; use lazy search callbacks.

## Out Of Scope

- Step 10R-V1c custom colors and podcast palette cache.
- Public motion/GSAP work.
- Any new icon package or JS dependency.
- Changing public card layout or icon placement semantics beyond token normalization.

## Stop Conditions

- Stop if unexpected app-code dirt appears before implementation.
- Stop if icon enum values changed in a way that breaks existing stored icon aliases
  without a compatibility layer.
- Stop if implementation would require storing raw SVG, Blade component names, Tailwind
  classes, arbitrary icon component names, scripts, or PHP class names in JSON settings.
- Stop if Filament cannot render selected lazy-search labels through
  `getOptionLabelUsing()` for the installed version.
