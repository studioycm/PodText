# Step 10R-IP3 MCP Research - Transcript Actions Menu and Reading UX

## Scope

Selected mini-step: Step 10R-IP3 - Transcript section, actions menu, share, and reading UX.

This research covers settings-backed episode-page transcript controls, Livewire/Alpine public viewer interaction, safe transcript details rendering, localStorage preferences, and focused Pest coverage for the transcript viewer.

## Laravel Boost Access

Access level: installed-version application/package guidance plus SQLite schema inspection.

Tools used:

- `application_info`
- `database_schema`
- `search_docs`

Application evidence:

- PHP 8.4, Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, Tailwind 4.3.2.
- Local database engine is SQLite. Production guardrail remains MySQL on Forge, so IP3 must avoid dialect-specific SQL.
- `settings` stores Spatie settings rows as `group`, `name`, `payload`; nested `item_page` stays a JSON settings payload.
- `content_items.published_at` and `content_items.original_published_at` exist.
- `transcriptions.published_at`, `transcriptions.word_count`, `transcriptions.parsed_segments`, and `transcriptions.transcript_markdown` exist.
- Existing settings migrations through `2026_07_09_000002_extend_public_item_page_podcast_identity_settings.php` are applied locally.

Search docs queries:

- `Spatie Laravel Settings migrations nested array settings`
- `Filament 5 SettingsPage tabs sections toggle select`
- `Livewire 4 Alpine interop browser localStorage public component`
- `Pest Laravel assert rendered Blade Livewire output`
- `Filament 5 form Toggle helperText Select options Section Tabs schema`
- `Livewire 4 events Alpine dispatch localStorage Blade x-data`
- `Laravel 13 migration update JSON settings row Spatie settings`

Relevant findings:

- Livewire includes Alpine; do not add a second Alpine instance.
- Alpine state can live inside Livewire-rendered Blade and can use browser events with `.window` listeners for parent/child coordination.
- Livewire/Alpine event interop uses browser events; this fits page-level reading controls triggered from the viewer without duplicating persistent state on the server.
- Blade data rendered into Alpine should use `@js`/safe escaping where dynamic strings are needed.
- Laravel HTTP/Blade tests can assert rendered output markers. Existing PodText tests already assert `data-test` hooks and translated output.
- Filament form controls should remain finite schema inputs. IP3 only needs a boolean Toggle in the existing Episode page tab.

## FilamentExamples MCP Access

Access level: search/snippet access only. No source/fetch/detail tool was exposed, so snippets and paths are the only inspected material.

First-pass query batches:

- `public detail page`
- `media sidebar page`
- `share actions page`
- `SettingsPage tabs`
- `settings repeater`
- `Alpine fullscreen`
- `font size controls`
- `dropdown action group`

Refined query batches:

- `custom page view data`
- `public Blade actions menu`
- `Filament action group`
- `reader controls`
- `getViewData public page`
- `Filament actions group blade dropdown`
- `custom settings page toggle buttons`
- `Alpine x data custom page`

Relevant snippets and adaptation:

- GitHub-style profile view page: prepares view data on the page class and renders a custom Blade layout. PodText already follows this with `ShowContentItem`; IP3 should keep derived page layout state in Blade/Alpine and server-derived transcript details in the Livewire component.
- Profile multi-record page: uses a Filament action group in Blade, but IP3 will use an app-owned public Alpine dropdown because the public panel should not hold server-side action state.
- Google Maps custom page: keeps Alpine setup local to the page view. IP3 should do the same for fullscreen, font-size, and player visibility preferences.
- Repeater/settings snippets: finite options, helper text, defaults, and normalized state are the pattern to preserve. IP3 adds only a boolean toggle and leaves browser preferences out of JSON settings.

## Code Inspection Summary

Inspected files:

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

Findings:

- The episode page already consumes `item_page` through `PublicFrontRenderContext::itemPage()`.
- The existing share block is below the media player in the media aside; IP3 must move it above the player.
- The transcript viewer currently renders standalone timestamp, speaker, and copy-link buttons above the transcript. IP3 must remove that standalone action row and render those actions inside an optional menu.
- `ContentItemTranscriptViewer` already memoizes public transcriptions with `#[Computed]`; IP3 should not add per-render queries.
- Existing transcript rendering uses the HF1 `SafeMarkdownRenderer::toTranscriptHtml()` path. IP3 must preserve long transcript and soft-break behavior.
- `card-part-shell` can safely render a transcription-date label/icon using finite keys from the M5 icon resolver.
- `PublicFrontConfigValidator::normalizeItemPage()` rejects unknown `item_page` keys, so `show_transcript_actions_menu` needs a registry default, validator normalization, settings migration, and admin field.
- Existing direct-DB test helpers clear Spatie settings cache and `PublicFrontRenderContext`.

## Implementation Direction

- Add `item_page.show_transcript_actions_menu` with default `false`.
- Add a settings migration to backfill the key in existing `public_content.item_page` payloads.
- Add one Episode page admin toggle with translated label/helper text.
- Move the item share block above the media player.
- Add an Alpine reading shell around the item page layout:
  - `fontStep` bounded from `-2` to `3`;
  - fullscreen boolean;
  - player visibility boolean;
  - ESC closes fullscreen;
  - localStorage keys only, no Livewire state.
- Pass no browser preferences through Livewire. The viewer will dispatch Alpine/browser events for font/fullscreen/player actions.
- Add a transcript details row above the transcript body:
  - transcription title only when present;
  - read time;
  - word count;
  - publish date rendered with IP1 transcription-date label/icon settings;
  - transcriber links;
  - optional actions menu trigger.
- Keep transcript tabs and visibility policy behavior unchanged.
- Do not implement player sync, derived parsed segments, cache work, or Step 14/15 future viewer work.
