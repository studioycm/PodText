# Public Front v2 Step 10R-B3 Implementation Plan

## Purpose

Make `content_group` and `contributor` card templates visibly drive rendered card parts, mirroring the Step 10R-B2 content-item presenter pattern while preserving current public behavior.

## Selected Mini-Step

Step 10R-B3 - Content group and contributor card template renderers.

## Current Code Reality

- Step 10R-B2 added `PublicContentItemCardPresenter` and real content-item part rendering.
- `content-group-card.blade.php` still prepares group URLs, cover image fallback, excerpt, category chips, counts, and display classes in Blade.
- `contributor-card.blade.php` still prepares contributor initials, counts, bio excerpt, selection classes, and compact/full markup in Blade.
- `PublicFrontCardTemplateRenderer` only has content-item-specific part filtering/presentation.
- Homepage content group and contributor sections already pass resolved `cardTemplate` objects into the card components.
- `/podcasts` and contributor/top-transcriber surfaces already pass default or resolved card templates into the cards.

## Implementation Tasks

1. Extend `PublicFrontCardTemplateRenderer`.
   - Add controlled part lists for `content_group` and `contributor`.
   - Add `contentGroupPresentation()` and `contributorPresentation()` with safe class maps based on existing template layout/density/title/image tokens.
   - Add `contentGroupParts()` and `contributorParts()` with image hidden filtering.

2. Add `PublicContentGroupCardPresenter`.
   - Prepare group URL, cover/fallback image data, display label, title, excerpt, visible categories, public item count label, and action URL.
   - Map finite part types: `image`, `entity_attribute`, `title`, `description`, `metadata_row`, `taxonomy`, `action_link`, `custom_text`, `divider`, `spacer`.
   - Render a title fallback if no supported visible parts remain.

3. Add `PublicContributorCardPresenter`.
   - Prepare contributor URL, initial, compact/selectable state, selected classes, public transcription/content-item counts, bio preview, and action data.
   - Map finite part types: `title`, `description`, `metadata_row`, `action_link`, `custom_text`, `divider`, `spacer`.
   - Keep compact selector behavior and `wire:click="selectContributor(...)"` in Blade.
   - Render a title fallback if no supported visible parts remain.

4. Update public card Blade components.
   - Keep `x-public.content-group-card` and `x-public.contributor-card` as the public API.
   - Replace inline data preparation with presenter output.
   - Loop prepared media/body parts with `data-card-part` markers.
   - Keep escaped output and route-generated URLs.

5. Add focused tests.
   - Custom content group template visibly affects `/podcasts` cards.
   - Custom content group template visibly affects homepage content group sections.
   - Custom contributor template visibly affects contributor cards and top-transcriber selector cards.
   - Hidden/reordered parts affect actual HTML safely.
   - Unsafe values stay rejected/escaped through existing validator coverage.
   - Public Filament Tables remain absent.

6. Update docs after gates pass.
   - Current project state.
   - Mini-step ledger.
   - B3 handoff.
   - Reconcile B2 commit placeholders to `e3c81de`.

## Explicit Non-Goals

- Do not add new settings keys for contributor template selection.
- Do not implement Step 10R-B4 card-options convergence.
- Do not correct item-card transcriber attribution; Step 10R-C1 owns that.
- Do not normalize all semantic layout tokens; Step 10R-C2 owns that.
- Do not add schema/migrations.
- Do not create forbidden public CMS/footer/menu/profile models.
- Do not reintroduce public Filament Tables.

## Verification

Focused:

```bash
php artisan test tests/Feature/PublicFrontCardTemplateBuilderTest.php
php artisan test tests/Feature/PublicPodcastsGroupsUxTest.php tests/Feature/PublicContributorsTopTranscribersUxTest.php tests/Feature/PublicDisplaySectionsLoopersTest.php
```

Required final gate:

```bash
vendor/bin/pint --dirty --format agent
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
git diff --check
```
