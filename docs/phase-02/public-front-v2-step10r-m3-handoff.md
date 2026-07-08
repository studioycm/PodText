# Public Front v2 Step 10R-M3 Handoff

## Purpose

Centralize public transcription selection, counts, and aggregate behavior after the M1/M2 multi-transcriber model changes.

## What Was Implemented

- Added normalized `transcription_policy` settings with `featured_only` and `all_published` modes.
- Added `PublicTranscriptionPolicy`, `PublicTranscriptionSelector`, and `PublicTranscriptionAggregates`.
- Routed public contributor counts through `author_transcription` instead of compatibility `transcriptions.author_id`.
- Added policy-aware public transcriber filtering for search.
- Added public content item and content group aggregate subselects for transcription count, total words, latest transcription date, and transcriber count.
- Added settings-page controls and translations for the policy.

## Files Changed

- `app/Support/PublicContent/PublicTranscriptionPolicy.php`
- `app/Support/PublicContent/PublicTranscriptionSelector.php`
- `app/Support/PublicContent/PublicTranscriptionAggregates.php`
- `app/Support/PublicContent/PublicContentItemQueries.php`
- `app/Support/PublicContent/PublicContributorDiscovery.php`
- `app/Support/PublicFront/Groups/PublicContentGroupQueries.php`
- `app/Settings/PublicContentSettings.php`
- `app/Providers/AppServiceProvider.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicFront/PublicFrontRenderContext.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `database/settings/2026_07_08_000007_add_public_transcription_policy_setting.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `tests/Feature/PublicTranscriptionPolicyTest.php`
- focused existing public-front tests and docs

## Migrations/Schema

Added a Spatie Settings migration for `public_content.transcription_policy`.

No database tables or columns were added in M3. `author_transcription` remains the multi-transcriber source, and `transcriptions.author_id` remains compatibility/primary storage.

## Final Model Relationships

No model relationship signatures changed in M3.

- `Transcription::authors()` remains the public transcriber source.
- `Author::authoredTranscriptions()` remains available.
- `ContentItem::authors()` and `Author::contentItems()` remain absent.

## Removed Relationships/Tables

None in M3. M2 already removed `author_content_item`.

## Admin Behavior

The public content settings page now includes a public transcription policy section with finite selects for public display mode and count mode plus the reserved item-page multiple-transcriptions toggle.

## Import/Export Behavior

No import/export changes in M3.

## Public Query/Policy Behavior

Default policy is `featured_only`:

- one effective/main transcription counts per public item;
- contributor and top-transcriber counts use selected/effective transcription transcribers;
- non-featured alternate transcriptions do not affect default public counts.

`all_published` mode:

- public selector/count helpers include all published transcriptions;
- contributor counts and item matches use all published transcription transcribers.

Draft/unpublished transcriptions remain excluded.

## Card/Template/Rendering Behavior

Existing rendering remains largely unchanged. M3 supplies aggregate attributes and policy-aware query data for M4. Full public rendering expansion for transcribers, aggregate attributes, transcript viewer behavior, and card template sources remains Step 10R-M4.

## Settings/Schema Changes

New setting shape:

```json
{
  "transcription_policy": {
    "public_mode": "featured_only",
    "count_mode": "featured_only",
    "show_multiple_transcriptions_on_item_page": false
  }
}
```

Allowed modes: `featured_only`, `all_published`.

## Tests Added/Updated

- Added `tests/Feature/PublicTranscriptionPolicyTest.php`.
- Updated existing contributor defaults/count expectations for featured-only behavior.
- Updated public settings defaults coverage for the new policy group.

## Security/Fallback Behavior

- Policy values are finite and normalized by `PublicFrontConfigValidator`.
- No raw SQL, class names, Blade paths, CSS, HTML, or unsafe URL settings are accepted.
- Public counts use published item/group/transcription constraints.
- Public contributor counts use `author_transcription`, not `User` records or item authors.

## Blueprint/Audit Deviations

No M3 requirement was intentionally skipped. Broad public rendering updates were kept out of M3 because the continuation prompt assigns them to M4.

## Effect on Later Mini-Steps

- Step 10R-M4 can now render public transcribers and aggregate attributes from centralized policy/aggregate services.
- Step 10R-M5 can build grouped/label/icon card parts on top of policy-backed data.
- Step 10R-B4 remains paused until M1-M6 are complete.

## Open Questions

- M4 should decide exactly where `all_published` mode shows multiple transcription groups versus effective/main fallback.
- M6 should decide whether any narrow C1 cleanup remains after M4/M5.

## Quality Gate Summary

- `php artisan migrate`
- `php artisan test tests/Feature/PublicTranscriptionPolicyTest.php`
- `php artisan test tests/Feature/PublicContributorDiscoveryTest.php tests/Feature/PublicContributorsTopTranscribersUxTest.php tests/Feature/PublicPodcastsGroupsUxTest.php tests/Feature/PublicDisplaySectionsLoopersTest.php tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
- `php artisan test tests/Feature/PublicHomepageSearchTest.php tests/Feature/PublicLatestSearchUxTest.php tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `vendor/bin/pint --dirty --format agent`
- `php artisan test`
- `vendor/bin/pint --test`
- `vendor/bin/filacheck`
- `npm run build`
- `git diff --check`

All passed.

## Commit Hash

This commit: `feat: add public transcription policy and aggregates`. The immutable hash is reported in the final run report after commit creation.
