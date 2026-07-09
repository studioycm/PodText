# Public Front v2 Step 10R-M6 Implementation Plan

## 1. Selected Mini-Step And Dependency Verification

Selected mini-step: Step 10R-M6 - final multi-transcriber, card-template, and IP stabilization.

Dependencies verified:

- M1 through M5 are present in local history.
- HF1 is present in local history.
- IP1 through IP3 are complete in the ledger.
- Step 10R-M6 is the first pending mini-step.
- Step 10R-C1 is paused/superseded until this run records final status.
- P1, P2, P3, B4, C2, 9F, Step 11, and Prompt 13 have not started in this run.

## 2. Current Local Repo Evidence

Preflight:

- Branch: `main...origin/main`.
- Working tree was clean before M6 ledger bookkeeping.
- HEAD: `d83edf8 feat: add transcript reading controls and actions menu`.
- Required prior commits are present: `800218a`, `e813513`, `825004c`, `af9f399`, `aaddd95`, `2a5ff96`, `aa7568c`, `9d565d7`, `0b067e9`, `280b7ef`, and `d83edf8`.
- Migrations are run through `2026_07_09_000003_add_public_item_page_transcript_actions_setting`.
- Public routes for `items`, `podcasts`, `contributors`, `search`, and `about` are registered.

Runtime schema checks:

- `author_content_item`: absent.
- `author_transcription`: present.
- `ContentItem::authors()`: absent.
- `Author::contentItems()`: absent.

## 3. Files Inspected

- `AGENTS.md`
- `.ai/guidelines/tooling-quality.md`
- required Phase-02 current state, ledger, performance audit, decisions, quality, lessons, M1-M5, B/A handoff, M4 plan, and M4 research docs.
- IP1, IP2, and IP3 handoffs.
- transcript hotfix plan, public item/media/parser blueprint, viewer/studio future plan, and active sequence doc.
- `app/Models/Transcription.php`
- `app/Models/Author.php`
- `app/Models/ContentItem.php`
- `app/Support/PublicContent/PublicContentItemQueries.php`
- `app/Support/PublicContent/PublicTranscriptionSelector.php`
- `app/Support/PublicContent/PublicContentCardOptions.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `app/Filament/Public/Pages/ShowContentItem.php`
- `app/Livewire/Public/ContentItemTranscriptViewer.php`
- public item/card/viewer Blade components.
- focused public-front, card-template, item-page, transcript, policy, model, and import/export tests.

## 4. Laravel Boost Findings

Boost access level: installed-version app/package info, schema inspection, read-only database queries, and docs search.

Findings:

- Installed stack is Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, PHP 8.4, SQLite locally.
- `author_transcription` exists and the old item-author pivot is absent.
- Laravel docs support the non-production `Model::preventLazyLoading()` guard already added in M4.
- Laravel docs support ordered many-to-many pivot relationships and sync payloads.
- Pest/Laravel database assertions and DB query listeners remain appropriate for the existing bounded harness.
- Boost did not return useful Spatie Settings migration docs; existing project settings migrations are the source pattern.

## 5. FilamentExamples MCP Findings And Access Level

FilamentExamples access level: search/snippet only.

Query batches:

- First pass: `settings page tabs`, `public detail page`, `media sidebar page`, `Livewire public page tests`, `settings repeater`, `Alpine dropdown action group`.
- Refined pass: `getViewData public page`, `computed Livewire page`, `clipboard alpine action`.

Relevant patterns:

- Prepare custom page view data in the page class before Blade rendering.
- Use `#[Computed]` for Livewire request-local derived data.
- Keep Alpine-only clipboard/local UI behavior out of server state.
- Use explicit nested state paths for multi-section settings forms.

## 6. Requirement IDs Owned This Run

M6 does not add new R requirements. It verifies closure of R1-R23:

- IP1 landed R1 data/card attributes, R2-R6, R13 token foundation, and R23.
- IP2 landed R1 page rendering, R11-R18, and R13 render behavior.
- IP3 landed R7-R10 and R19-R22.

M6 handoff will record all R1-R23 as verified and remaining R-count as none if tests/gate pass.

## 7. Schema/Settings Reality Check

Schema:

- `author_transcription` remains the multi-transcriber pivot.
- `transcriptions.author_id` remains compatibility primary-transcriber storage.
- `author_content_item` is gone.
- No new app table is planned.

Settings:

- `transcription_policy` exists.
- `transcription_display` exists on card surfaces.
- `item_page` exists and includes dates, badges, breadcrumbs, podcast identity, info fields, and transcript action-menu setting.

Stabilization gap:

- `docs/phase-02/public-front-v2-transcription-display-decisions.md` says `transcription_display` defaults to `effective_only`.
- Runtime defaults, admin field defaults, validator fallbacks, and local settings rows still use `effective_plus_count`.
- M6 will align code and existing settings rows with the existing Yoni decision.

## 8. Data Mapping Check

IP1/IP2 mapping remains valid:

- Published on site: `content_items.published_at`.
- Originally published: `content_items.original_published_at`.
- Transcription date: selected/effective transcription `published_at`.
- Presentation format: `dd/mm/yyyy` in `Asia/Jerusalem`.

## 9. Current Rendering/Query/Settings Gaps

- No remaining C1 runtime gap was found: public transcriber displays inspected route through transcription authors, not content-item authors.
- The original C1 prompt is superseded by M1-M6 and should not run as written.
- `transcription_display` default mismatch remains and will be fixed in M6.
- F1, F2, F3, F7, F11, F12, F13, and F15 remain scheduled for P/B/C steps.

## 10. Exact JSON Shape/Defaults/Validator Changes

No new JSON key is added.

Change the default value for these existing finite `transcription_display` tokens from `effective_plus_count` to `effective_only`:

- `display_defaults.transcription_display`
- `podcasts_page.group_page.transcription_display`
- `contributors_page.directory.transcription_display`
- `contributors_page.top_transcribers.transcription_display`
- `contributors_page.page.transcription_display`

Allowed values remain:

- `effective_only`
- `effective_plus_count`

Add a Spatie settings migration that updates existing rows whose current value is `effective_plus_count` to `effective_only` for those default surfaces. Explicit tests will still set `effective_plus_count` where count-badge behavior is being exercised.

## 11. Exact Files To Change

Planned app/settings/test files:

- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicContent/PublicContentCardOptions.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `app/Livewire/Public/ContributorContentItems.php`
- `app/Livewire/Public/ContributorDirectory.php`
- `app/Livewire/Public/TopTranscribersSection.php`
- new settings migration under `database/settings/`
- focused default/settings tests as needed.

Planned docs:

- `docs/research/public-front-v2/18-step10r-m6-mcp-research.md`
- this plan
- `docs/phase-02/public-front-v2-step10r-multi-transcriber-card-template-ip-handoff.md`
- central ledger
- current project state
- next implementation sequence
- transcription display decisions if implementation-status wording is needed.

## 12. Tests To Add/Update

Focused tests:

- Public settings/default tests for `effective_only`.
- Settings migration/backfill test for the M6 default alignment.
- Existing explicit `effective_plus_count` rendering tests should remain explicit and keep passing.
- Multi-transcriber model tests.
- Public transcription policy tests.
- Public card-template tests.
- Public item page/media/parser tests.
- Public transcript rendering tests.
- Public rendering bounded query-count harness.
- Admin import/export regression tests touched by M1/M2, using actual available test files.

Full gate:

- `vendor/bin/pint --dirty --format agent`
- `php artisan test`
- `vendor/bin/pint --test`
- `vendor/bin/filacheck`
- `npm run build`
- `git diff --check`

## 13. Translation Keys To Add/Update

No new labels are planned.

Existing `admin.transcription_display.*` and public/admin item-page keys remain unchanged.

## 14. Performance Implications

- Changing defaults does not add queries.
- The settings migration updates existing settings rows once.
- P1 still owns validated config caching.
- P2 still owns fetch-window/filter/form-definition/subselect optimization.
- P3 still owns stored transcript segments and render economy.
- B4/C2 still own card options/layout convergence.

## 15. Out Of Scope

- P1 cache implementation.
- P2 listing query optimization.
- P3 derived transcript segments/backfill.
- B4 card-options convergence.
- C2 semantic layout tokens.
- 9F rich sections/footer.
- Step 11 seeders/demo/assets.
- Prompt 13 dashboard metrics.
- Prompt 14/15 future work.
- Removing `transcriptions.author_id`.
- Reintroducing content-item authors.

## 16. Stop Conditions

Stop before commit if:

- the default-alignment change requires raw settings classes, raw HTML/classes, or schema rework;
- any focused test or full gate command fails and cannot be fixed within M6 scope;
- C1 audit finds public runtime code still using removed content-item authors as transcribers;
- Step 11 or Prompt 13 work becomes necessary;
- unexpected non-M6 app-code dirt appears.
