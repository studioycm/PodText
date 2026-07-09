# Public Front v2 10R-M6 Handoff

## Purpose

Step 10R-M6 closes the M1-M5 plus IP1-IP3 arc, verifies the multi-transcriber/card-template/episode-page regressions, records the final Step 10R-C1 status, and prepares the sequence for P1.

## What was implemented

- Audited the M1-M5, HF1, and IP1-IP3 runtime paths.
- Verified the old item-author pivot and relationships remain removed.
- Verified public transcriber rendering still comes from transcription transcribers.
- Verified R1-R23 are landed across IP1-IP3.
- Marked Step 10R-C1 superseded; no narrow C1 cleanup remains.
- Fixed a stabilization mismatch: `transcription_display` defaults/fallbacks and existing settings rows now align with Yoni's `effective_only` default decision.
- Added a Spatie settings migration to update existing `effective_plus_count` default-surface settings rows to `effective_only`.
- Kept `effective_plus_count` as an explicit finite opt-in for count-badge surfaces.
- Corrected the transcription display decisions doc so optional count badges are tied to `effective_plus_count`.

## Requirement IDs landed

M6 did not add new R behavior; it verified the arc.

Landed and verified:

| R# | Owner | M6 status |
|---|---|---|
| R1 | IP1 + IP2 | Verified: site/original dates are available to cards and episode page. |
| R2 | IP1 | Verified: date display mode setting exists. |
| R3 | IP1 | Verified: per-date long/short/hidden label settings exist. |
| R4 | IP1 | Verified: per-date icon and icon-position settings exist. |
| R5 | IP1 | Verified: transcription date label/icon settings exist. |
| R6 | IP1 | Verified: Episode page settings tab exists. |
| R7 | IP3 | Verified: share block is above the player. |
| R8 | IP3 | Verified: standalone share action above transcript was removed. |
| R9 | IP3 | Verified: transcript actions menu exists when enabled. |
| R10 | IP3 | Verified: actions menu setting defaults hidden. |
| R11 | IP2 | Verified: categories/tags render above description in the info line. |
| R12 | IP2 | Verified: category/tag/podcast badge text is linked on audited card/page surfaces. |
| R13 | IP1 + IP2 | Verified: finite badge size/color/icon tokens and render behavior exist. |
| R14 | IP2 | Verified: standalone episode/transcription type labels are removed from the page header. |
| R15 | IP2 | Verified: breadcrumbs show/hide setting exists. |
| R16 | IP2 | Verified: page image falls back from item image to podcast cover. |
| R17 | IP2 | Verified: linked podcast identity supports badge/text/title/hidden modes and position/color/icon settings. |
| R18 | IP2 | Verified: ordered info fields render under/near the title. |
| R19 | IP3 | Verified: transcript details row renders selected transcription metadata. |
| R20 | IP3 | Verified: font controls render as Alpine/localStorage preferences. |
| R21 | IP3 | Verified: fullscreen reading layer controls render. |
| R22 | IP3 | Verified: player/media hide-show controls render. |
| R23 | IP1 | Verified: `item_page` is extensible nested JSON with validator/default/migration patterns. |

Remaining R requirements: none.

## Finding IDs resolved

M6 resolved no F finding. It is a stabilization and closeout step.

Remaining scheduled findings:

- F1: Step 10R-P1.
- F2, F7, F12, F15: Step 10R-P2.
- F3 remainder: Step 10R-P3.
- F11 remainder: Step 10R-B4.
- F13: Step 10R-C2.

Already resolved findings remain unchanged: F4, F5, F6, F8, F9, F10, F14, and F16.

## Files changed

- `app/Filament/Pages/PublicContentSettings.php`
- `app/Livewire/Public/ContributorContentItems.php`
- `app/Livewire/Public/ContributorDirectory.php`
- `app/Livewire/Public/TopTranscribersSection.php`
- `app/Support/PublicContent/PublicContentCardOptions.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `database/settings/2026_07_09_000004_align_public_transcription_display_defaults.php`
- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
- `tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php`
- `docs/research/public-front-v2/18-step10r-m6-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-m6-implementation-plan.md`
- `docs/phase-02/public-front-v2-step10r-multi-transcriber-card-template-ip-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`
- `docs/phase-02/public-front-v2-transcription-display-decisions.md`

## Migrations/settings/schema changes

- Added settings migration `2026_07_09_000004_align_public_transcription_display_defaults`.
- No database table schema changed.
- Local `php artisan migrate` ran the new settings migration successfully.
- Runtime audit confirmed `author_content_item` remains absent.
- Runtime audit confirmed `author_transcription` remains present.
- Runtime audit confirmed `ContentItem::authors()` and `Author::contentItems()` remain absent.

## Settings keys and defaults

No new settings key was added.

Existing finite `transcription_display` defaults now align to `effective_only` for:

- `display_defaults.transcription_display`
- `podcasts_page.group_page.transcription_display`
- `contributors_page.directory.transcription_display`
- `contributors_page.top_transcribers.transcription_display`
- `contributors_page.page.transcription_display`

Allowed values remain:

- `effective_only`
- `effective_plus_count`

The settings migration changes existing rows on those default surfaces from `effective_plus_count` to `effective_only`. Explicit tests still set `effective_plus_count` where optional count-badge rendering is required.

## Registry/validator/render-context changes

- `PublicFrontConfigRegistry` defaults now use `effective_only` on the five `transcription_display` surfaces.
- `PublicFrontConfigValidator` fallback defaults now use `effective_only`.
- `PublicContentCardOptions` constructor and `fromValues()` fallback now use `effective_only`.
- Contributor public Livewire fallback values now use `effective_only`.
- `PublicFrontRenderContext` did not need a code change; it returns the normalized settings groups.

## Public rendering behavior

- Default public item cards and contributor cards now omit optional multi-transcription count badges unless a surface explicitly chooses `effective_plus_count`.
- Explicit `effective_plus_count` behavior remains supported and covered.
- Episode page settings/date/header/transcript behavior from IP1-IP3 is unchanged.
- Card-template label/icon/group rendering from M5 is unchanged.
- Public transcriber names and links remain based on `Transcription::authors()`.

## Admin behavior

- The public content settings page now defaults all `transcription_display` selects to `effective_only`.
- Existing labels/helper text were reused; no new admin labels were added.
- Admin users can still opt any supported surface into `effective_plus_count`.

## Query/performance behavior

- The default alignment adds no public rendering queries.
- The settings migration is a one-time settings-row update.
- The bounded public rendering query-count harness remains green.
- P1 still owns config caching.
- P2 still owns listing fetch windows, lazy options/form definitions, and opt-in aggregate subselects.
- P3 still owns derived transcript segments and word-count render economy.
- B4/C2 still own card option/layout convergence.

## Translation keys added/updated

No translation keys were added or changed in M6.

Existing relevant keys remain in `lang/en` and `lang/he`:

- `admin.transcription_display.effective_only`
- `admin.transcription_display.effective_plus_count`

## Tests added/updated/renamed

Updated:

- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
  - verifies `effective_only` defaults;
  - verifies the M6 settings migration aligns existing `effective_plus_count` rows to `effective_only`;
  - verifies saved settings/card options use the aligned default.
- `tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php`
  - explicit count-badge fixtures now opt in through `podcasts_page.group_page.transcription_display`.

No tests were renamed or deleted.

## Tests changed because old assertions intentionally moved/changed

- One bounded-harness fixture no longer relies on the old implicit count-badge default.
- The count-badge regression remains covered by explicitly setting `effective_plus_count` on the podcast group-page card surface.

## Security/fallback behavior

- No raw HTML, CSS classes, Blade/PHP strings, SQL, SVG, or component names were added to JSON settings.
- `transcription_display` remains a finite validator token.
- Invalid `transcription_display` values still normalize safely.
- M6 did not change public visibility, draft hiding, Markdown rendering, media allowlists, or browser preference storage.

## Bounded query-count harness result

Focused run passed:

```bash
php artisan test tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php
```

Result: 7 passed, 64 assertions.

## Impact on later mini-steps

- Step 10R-P1 is next and should cache validated public-front config with the versioned key `public_front.config.v1`.
- Step 10R-C1 must not run as written; it is superseded by M1-M6.
- Step 10R-B4 should preserve M5 grouped parts plus IP1 date attributes and the `effective_only` default.
- Step 9F remains blocked until P1-P3, B4, and C2 complete.
- Step 11 and Prompt 13 still require explicit Yoni approval.

## Open issues / follow-up decisions

- Full visual review should still inspect Hebrew RTL, light mode, and dark mode across episode pages, cards, podcast pages, contributor pages, homepage/search, and category/tag pages.
- No remaining M/C1 runtime cleanup was found.

## Quality gate summary

Focused tests passed before the final gate:

```bash
php artisan test tests/Feature/PublicFrontJsonSettingsArchitectureTest.php
php artisan test tests/Feature/TranscriptionsModelTest.php tests/Feature/PublicTranscriptionPolicyTest.php
php artisan test tests/Feature/ImportExportTest.php tests/Feature/Phase02ImportExportTest.php
php artisan test tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php
php artisan test tests/Feature/PublicFrontCardTemplateBuilderTest.php
php artisan test tests/Feature/PublicItemPageMediaParserTest.php
php artisan test tests/Feature/PublicTranscriptRenderingTest.php
php artisan test tests/Feature/PublicPodcastsGroupsUxTest.php tests/Feature/PublicContributorsTopTranscribersUxTest.php tests/Feature/PublicHomepageSearchTest.php tests/Feature/PublicLatestSearchUxTest.php tests/Feature/PublicContributorDiscoveryTest.php
php artisan test tests/Feature/PublicFrontRenderContextTest.php tests/Feature/PublicFrontJsonSettingsArchitectureTest.php
```

Final gate passed:

```bash
vendor/bin/pint --dirty --format agent
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
git diff --check
```

- `vendor/bin/pint --dirty --format agent`: passed.
- `php artisan test`: 285 passed, 2622 assertions.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed, 0 issues.
- `npm run build`: passed.
- `git diff --check`: passed.

## Commit hash

This commit: `docs: summarize public front multi-transcriber card template arc`. The concrete hash is recorded in the final run report because amending the commit changes the self-reference.
