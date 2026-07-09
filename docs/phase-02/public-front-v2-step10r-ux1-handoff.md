# Public Front v2 10R-UX1 Handoff

## Purpose

Step 10R-UX1 standardizes admin navigation, table record-action placement, action modal
widths, admin section spans, and relation-manager tab placement before the remaining
post-M6 settings and performance mini-steps.

## What Was Implemented

- Added `AdminNavigationOrder` as the single admin navigation sort map.
- Added `UsesAdminNavigationOrder` and routed every registered app admin resource/page
  through `getNavigationSort()`.
- Added an app dashboard page wrapper so the dashboard also consumes the map.
- Updated the admin panel provider to register the app dashboard page.
- Added admin-scoped global Filament defaults in `AppServiceProvider`:
  - table record actions use `RecordActionsPosition::BeforeColumns`;
  - non-confirmation actions default to `Width::SevenExtraLarge`;
  - confirmation actions keep Filament's compact `Width::Medium`;
  - admin schema `Section` components default to `columnSpanFull()`.
- Combined relation-manager tabs with content on `EditContentItem` and
  `EditContentGroup`, with content tab explicitly pinned first.
- Added a translated group content-tab label.
- Added scoped admin theme CSS for larger combined relation-manager tab labels.
- Amended the central ledger and sequence docs from the stale v1 rows to the v3 order.

## Files Changed

- `app/Filament/Support/AdminNavigationOrder.php`
- `app/Filament/Support/Concerns/UsesAdminNavigationOrder.php`
- `app/Filament/Pages/Dashboard.php`
- Admin resource/page classes under `app/Filament/Resources`
- `app/Filament/Pages/PublicContentSettings.php`
- `app/Providers/AppServiceProvider.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `resources/css/filament/admin/theme.css`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `tests/Feature/AdminPhase02ResourcesTest.php`
- UX1 plan/research docs, this handoff, ledger, sequence doc, and current state

## Tests Added Or Updated

Updated `tests/Feature/AdminPhase02ResourcesTest.php` with:

- central admin navigation order and map completeness coverage;
- representative admin table/relationship-manager record-action position assertions;
- global action modal default and compact confirmation assertions;
- admin Section full-width default assertion;
- combined relation-manager tab assertions for item/group edit pages;
- scoped CSS marker assertion.

## Exceptions / Notes

- Dashboard is included at sort `0`; the requested content order starts after it:
  podcasts, episodes, transcriptions, transcribers, categories, tags, form submissions,
  homepage sections, settings.
- Confirmation modals intentionally stay `Width::Medium`.
- Existing relationship option create/edit modals keep their compact explicit
  `Width::ThreeExtraLarge` because they are small selector modals.
- The CSS selector uses Filament's current `relationManagerTabs` component key and
  `.fi-tabs-item-label` class, so it is intentionally narrow but upgrade-sensitive.
- Create pages are not applicable for relation-manager tabs because they do not have a
  persisted owner record.

## Quality Gate

Focused tests:

```bash
php artisan test tests/Feature/AdminPhase02ResourcesTest.php --filter="orders every registered admin navigation resource and page through the central map|applies admin table action modal and section defaults|combines relation manager tabs with content first on edit pages"
php artisan test tests/Feature/AdminPhase02ResourcesTest.php
php artisan test tests/Feature/AdminResourcesTest.php
php artisan test tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php
```

Results: passed.

Final gate:

```bash
vendor/bin/pint --dirty --format agent
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

Results:

- `vendor/bin/pint --dirty --format agent`: fixed imports in
  `app/Filament/Pages/PublicContentSettings.php`, then clean.
- `php artisan test`: 288 passed, 2653 assertions.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed, 0 issues.
- `npm run build`: passed.

## Next Step

Step 10R-UX2 is next: add the shared effective/featured/main transcription edit action
to the Episodes resource list and podcast episode relation manager.
