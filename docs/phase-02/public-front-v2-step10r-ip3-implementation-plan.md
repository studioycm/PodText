# Public Front v2 Step 10R-IP3 Implementation Plan

## 1. Selected mini-step and dependency verification

Selected mini-step: Step 10R-IP3 - Transcript section, actions menu, share, and reading UX.

Dependencies verified locally:

- M5 complete: `aa7568c` and follow-up fixes in local history.
- IP1 complete: `9d565d7`.
- IP2 complete: `0b067e9` plus review-fix `280b7ef`.
- HF1 complete: `2a5ff96`.
- Ledger marks IP3 as the first pending mini-step for this run.

Stop guardrail status:

- B4, 9F, Step 11, Prompt 13, Prompt 14, and Prompt 15 are not selected and remain out of scope.
- Step 11 and Prompt 13 do not have explicit Yoni approval in this run.

## 2. Current local repo evidence

- Current commit before IP3 edits: `280b7ef`.
- Branch: `main...origin/main`.
- Initial working tree was clean; only ledger in-progress marking exists before this plan.
- Preflight route checks passed for `items`, `podcasts`, `contributors`, `search`, `about`, and `admin`.
- `php artisan migrate:status` shows settings migrations through `2026_07_09_000002_extend_public_item_page_podcast_identity_settings` as `Ran`.
- `author_transcription` creation and `author_content_item` drop migrations are present and applied.

## 3. Files inspected

- `AGENTS.md`
- `.ai/guidelines/tooling-quality.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-transcription-display-decisions.md`
- `docs/phase-02/public-front-v2-performance-efficiency-audit.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/phase-02/ai-development-lessons.md`
- M1-M4, IP1, IP2, A1/A2, B1/B2/B3 handoffs and M4 research.
- `docs/phase-02/transcript-viewer-markdown-rendering-hotfix-plan.md`
- `docs/phase-02/blueprints/12-public-item-page-media-parser-blueprint.md`
- `docs/phase-02/transcript-viewer-and-studio-future-plan.md`
- `app/Filament/Public/Pages/ShowContentItem.php`
- `resources/views/filament/public/pages/show-content-item.blade.php`
- `app/Livewire/Public/ContentItemTranscriptViewer.php`
- `resources/views/livewire/public/content-item-transcript-viewer.blade.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicFront/PublicFrontRenderContext.php`
- `app/Support/PublicFront/ItemPage/PublicItemPageRegistry.php`
- `resources/views/components/public/card-part-shell.blade.php`
- `app/Support/PublicFront/Cards/PublicFrontCardIconResolver.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `database/settings/2026_07_09_000001_add_public_item_page_header_settings.php`
- `database/settings/2026_07_09_000002_extend_public_item_page_podcast_identity_settings.php`
- `tests/Feature/PublicItemPageMediaParserTest.php`
- `tests/Feature/PublicTranscriptRenderingTest.php`
- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`

## 4. Laravel Boost findings

Tools used:

- `application_info`
- `database_schema`
- `search_docs`

Findings:

- Installed stack: Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, Tailwind 4.3.2, SQLite locally.
- `settings.payload` is text-backed locally and stores Spatie settings payloads by `group` and `name`.
- IP3 can be settings-only; no table schema migration is needed.
- Livewire and Alpine interoperate through browser events and Alpine local state; Livewire bundles Alpine, so no new frontend package should be added.
- Blade/HTTP tests can assert safe data markers and rendered strings; existing PodText tests already follow this.

## 5. FilamentExamples MCP findings and access level

Access level: search/snippet access only. No detail/source fetch tool was available.

Query batches:

- `public detail page`; `media sidebar page`; `share actions page`; `SettingsPage tabs`
- `settings repeater`; `Alpine fullscreen`; `font size controls`; `dropdown action group`
- `custom page view data`; `public Blade actions menu`; `Filament action group`; `reader controls`
- `getViewData public page`; `Filament actions group blade dropdown`; `custom settings page toggle buttons`; `Alpine x data custom page`

Adapted patterns:

- Prepare server-derived detail data before Blade rendering and avoid querying from Blade.
- Keep settings controls finite and explicit.
- Use custom Blade/Alpine for public-page interactions when server-side Filament Actions would introduce unnecessary public Livewire action state.

## 6. Exact requirement IDs owned this run

IP3 owns and lands:

- R7: Share section moves above the player.
- R8: Remove the share/copy action above the transcript as a standalone row action.
- R9: Transcript actions consolidated into a Blade/Alpine menu.
- R10: Menu hidden behind an episode-page setting, default hidden.
- R19: Transcript details row above transcript: title, read time, word count, publish date, settings menu.
- R20: Font size increase/decrease/reset.
- R21: Full-screen reading mode for transcript section and player together.
- R22: Hide/show player/media column.

## 7. Schema/settings reality check

- No new database tables or columns are required.
- Existing `item_page` JSON settings group is the correct extension point.
- `PublicFrontConfigValidator::normalizeItemPage()` currently rejects unknown keys, so `show_transcript_actions_menu` must be added there.
- Existing settings migration pattern is a Spatie settings migration in `database/settings`, not a Laravel schema migration.

## 8. Data mapping check

- Transcription publish date for the details row maps to the selected public transcription `transcriptions.published_at`.
- Date rendering must stay `d/m/Y` in `Asia/Jerusalem`.
- Word count uses `transcriptions.word_count` when present, falling back to the current markdown word-count helper.
- Read time remains computed from word count at 200 words/minute.
- Transcriber links use selected transcription `authors`.

## 9. Current rendering/query/settings gaps

- Share block currently renders below the media player.
- Viewer currently has standalone timestamp/speaker/copy controls above the transcript; these need to move into the optional menu.
- Viewer details row exists partially but lacks transcription title and IP1 label/icon-driven date presentation.
- There is no `item_page.show_transcript_actions_menu` setting.
- There is no page-level Alpine shell for font-size/fullscreen/player visibility.

## 10. Exact JSON shape/defaults/validator changes

Add:

```json
{
  "item_page": {
    "show_transcript_actions_menu": false
  }
}
```

Existing `item_page` keys remain unchanged. Browser preferences are not stored in JSON settings.

Validator:

- `show_transcript_actions_menu` is normalized as a boolean.
- Invalid values fall back to `false` and produce an invalid-config path.

Settings migration:

- New `database/settings/2026_07_09_000003_add_public_item_page_transcript_actions_setting.php`.
- Adds/backfills `show_transcript_actions_menu` into `public_content.item_page`.
- Down migration removes only that key.

## 11. Exact files to change

Planned app/test files:

- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `app/Livewire/Public/ContentItemTranscriptViewer.php`
- `resources/views/filament/public/pages/show-content-item.blade.php`
- `resources/views/livewire/public/content-item-transcript-viewer.blade.php`
- `database/settings/2026_07_09_000003_add_public_item_page_transcript_actions_setting.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `lang/en/public.php`
- `lang/he/public.php`
- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
- `tests/Feature/PublicItemPageMediaParserTest.php`
- `tests/Feature/PublicTranscriptRenderingTest.php` if needed for changed control markers.

Planned docs:

- `docs/research/public-front-v2/18-step10r-ip3-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-ip3-implementation-plan.md`
- `docs/phase-02/public-front-v2-step10r-ip3-handoff.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`

## 12. Tests to add/update, including harness coverage

Focused updates:

- Settings defaults normalize with `show_transcript_actions_menu` false.
- Settings migration backfills the IP3 key.
- Invalid action-menu values normalize safely.
- Settings page saves the toggle.
- Share block renders before player.
- Standalone transcript copy/share action is absent above transcript.
- Actions menu hidden by default.
- Actions menu renders when enabled and contains timestamp/speaker/copy/font/fullscreen/player controls.
- Details row renders selected transcription title, read time, word count, publish date, and transcriber links.
- Font size/fullscreen/player controls render with finite `data-test` hooks.
- Draft/future/unpublished transcriptions remain hidden.
- Per-transcription tabs still work.
- Long transcript rendering and Markdown formatting stay green.
- Bounded query-count harness stays green.

Full gate still required after focused tests.

## 13. Translation keys to add/update

Add or update in `lang/en` and `lang/he`:

- `admin.sections.public_front_item_page_transcript_controls`
- `admin.descriptions.public_front_item_page_transcript_controls`
- `admin.fields.item_page_show_transcript_actions_menu`
- `admin.helpers.item_page_show_transcript_actions_menu`
- `public.viewer.actions`
- `public.viewer.decrease_font`
- `public.viewer.fullscreen`
- `public.viewer.hide_player`
- `public.viewer.increase_font`
- `public.viewer.reset_font`
- `public.viewer.show_player`

Reuse existing `public.actions.close`, `public.actions.copy_link`, `public.actions.copied`, timestamp/speaker labels, read-time and word-count translations where possible.

## 14. Performance implications

- No new public queries should be introduced.
- `ContentItemTranscriptViewer::publishedTranscriptions` remains `#[Computed]`.
- Details row uses the already-selected transcription and loaded authors.
- Browser-local UI state does not touch Livewire or the server.
- F3 remains scheduled for P3; this step does not cache or persist parsed transcript segments.

## 15. Out-of-scope list

- Player/transcript sync.
- Stored parsed segments and transcript render economy.
- Public-front config cache.
- B4 card-options convergence.
- M6 stabilization docs.
- 9F footer/rich sections.
- Step 11 seeders/demo assets.
- Prompt 13 dashboard metrics.
- New models or public Filament Tables.
- Raw classes, raw HTML, raw SVG, raw Blade/PHP in JSON settings.

## 16. Stop conditions

Stop before app-code changes if a contradiction appears in ledger/current state, prior commits, migrations, or working tree scope.

Stop before commit if any focused test, bounded query-count harness, full test suite, Pint, FilaCheck, build, or `git diff --check` fails.

Stop before broadening scope if implementing fullscreen or player toggle would require player sync, server state, or schema changes beyond `item_page.show_transcript_actions_menu`.
