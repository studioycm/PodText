# Public Front v2 Step 10R-V1c Implementation Plan

## Selected Step

Step 10R-V1c - Custom colors and theme-safe podcast palette.

## Dependencies

- Step 10R-V1a complete: default/no-image fallback settings and public fallback
  rendering exist.
- Step 10R-V1b complete: icon registry and shared icon picker exist.
- Current sequence: V1c is the first pending mini-step. P1 remains blocked until V1c is
  complete.

## Current Repo Evidence

- `PublicItemPageRegistry` owns finite item-page color tokens.
- `PublicContentSettings` exposes finite item-page color selects for podcast identity,
  info badge defaults, and each info field.
- `PublicFrontConfigValidator` normalizes item-page color tokens but currently rejects
  all custom hex values.
- `PublicItemPagePodcastPalette` samples raw cover colors on each call and returns only
  one color per `image_*` token.
- `ShowContentItem` already computes podcast identity classes/styles in PHP and emits
  style through the Blade component.

## Files Inspected

- `prompts/pre-13-prompts/public-front-v2-post-m6-ux-settings-runner-codex-prompt.md`
- `docs/phase-02/public-front-v2-step10r-v1b-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-admin-settings-enhancement-plan.md`
- `docs/phase-02/public-front-v2-transcription-display-decisions.md`
- `docs/phase-02/public-front-v2-performance-efficiency-audit.md`
- `docs/research/public-front-v2/19-admin-settings-enhancement-mcp-research.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/phase-02/ai-development-lessons.md`
- `app/Filament/Pages/PublicContentSettings.php`
- `app/Filament/Public/Pages/ShowContentItem.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicFront/ItemPage/PublicItemPageRegistry.php`
- `app/Support/PublicFront/ItemPage/PublicItemPagePodcastPalette.php`
- `resources/views/components/public/item-page-podcast-identity.blade.php`
- `resources/views/components/public/card-part-shell.blade.php`
- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
- `tests/Feature/PublicItemPageMediaParserTest.php`
- `tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php`

## Boost Findings

- Filament 5 `ColorPicker` defaults to HEX and supports `hex()`.
- Filament validation supports `hexColor()` and regex rules for strict 3/6 digit hex.
- Conditional form fields should be driven by live controls and `visible()` closures.
- Laravel 13 supports `Cache::rememberForever()` for persistent arrays.
- Cache tags are not available on the local database cache store.

## FilamentExamples Findings

- Search/snippet access only.
- Useful patterns were conditional form fields with state-aware `Select` controls and
  normal Settings/Page form structures.
- No exact ColorPicker settings example was exposed; V1c follows Boost docs plus
  existing PodText default-image reveal patterns.

## Settings / Render-Context Impact

- Add `custom_color` beside finite color tokens in:
  - `item_page.podcast_identity`
  - `item_page.badges.info`
  - each `item_page.info_fields[]` row
- Add a settings migration to backfill those keys.
- No new top-level settings group and no new render-context method are needed because
  this extends the existing `item_page` group and accessor.

## Admin Impact

- Add `custom` to the relevant color select options.
- Reveal a `ColorPicker` only when the sibling color select is `custom`.
- Validate as strict hex and normalize `#rgb` to `#rrggbb`.
- Update English and Hebrew labels/helper text.

## Public Impact

- Custom color values render only through CSS custom properties on controlled public
  components.
- Podcast image sampled colors produce light and dark variants and render through CSS
  custom properties.
- No public rendering path fetches remote images.

## Query / Cache Impact

- No new SQL query should be added to public rendering.
- Podcast palette cache uses a versioned key derived from public cover path and file
  mtime. No cache tags.
- Palette values are cached arrays; GD decoding happens only on a cache miss for a
  readable local public-disk cover.

## Exact Files To Change

- `app/Support/PublicFront/Colors/PublicFrontColor.php`
- `app/Support/PublicFront/ItemPage/PublicItemPagePodcastPalette.php`
- `app/Support/PublicFront/ItemPage/PublicItemPageRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `app/Filament/Public/Pages/ShowContentItem.php`
- `resources/views/filament/public/pages/show-content-item.blade.php`
- `database/settings/2026_07_09_000007_add_public_custom_color_settings.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `docs/phase-02/public-front-v2-transcription-display-decisions.md`
- Focused tests under `tests/Feature/`
- Ledger/current-state/sequence/handoff docs

## Tests

- Hex normalization: `#abc` becomes `#aabbcc`; invalid hex falls back and records an
  invalid-config path.
- Settings page custom-color save normalizes strict hex and clears stale custom colors
  when the token is semantic.
- ColorPicker reveal is present for custom color mode.
- Deterministic fixture cover yields light/dark palette variants with WCAG 4.5:1
  contrast target.
- Palette cache prevents repeated computation for the same cover path + mtime.
- Remote cover values never fetch and use semantic fallback colors.
- Public item page renders custom and sampled colors as CSS custom properties, not raw
  dynamic Tailwind classes.
- `tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php` remains green.

## Risks

- Filament form component visibility assertions can be brittle; the behavior test will
  focus on saved settings plus component presence/visibility where stable.
- GD may be unavailable in some environments; tests that need image sampling will skip
  only the sampling-specific assertions if GD is unavailable, while fallback/cache logic
  remains covered.
- `color-mix()` is used as progressive CSS for tint/border values, matching the existing
  implementation style.

## Out Of Scope

- P1 validated public-front config caching.
- Admin theme palette changes.
- Card-template color controls beyond existing item-page color fields.
- Any new JavaScript dependency.
- GSAP/AX motion work.

## Stop Conditions

- Stop if strict custom hex conflicts with the finite-token guardrail; D9 records the
  sanctioned exception.
- Stop if image color sampling would require remote fetching.
- Stop if cache tags or per-request GD decoding are required to satisfy the plan.
- Stop if the ledger/sequence no longer list V1c as the first pending step.
