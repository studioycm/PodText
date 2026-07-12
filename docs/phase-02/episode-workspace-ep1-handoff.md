# Episode Workspace EP1 Handoff

Date: 2026-07-12

## Scope

Implemented `prompts/pre-13-prompts/episode-workspace-ep1-implementation-codex-prompt.md` as the single session step, executing `docs/phase-02/episode-workspace-plan.md` with the prompt corrections.

No Composer changes were made.

## Root Decisions

1. `ContentItem::workspaceTranscription()` is a workspace-only `HasOne` lens. It is documented not to be eager-loaded from list/table/collection queries.
2. The workspace defaults to one selected transcription: featured first, latest published second, newest draft third, and creates a first empty transcription when saving a new workspace.
3. Replace transcription is the explicit target-changing action. It supports selecting another same-item transcription or starting a fresh one; both paths repin `featured_transcription_id`.
4. `title_prefix` is stored separately from `title`. Public combined display uses `title_prefix ?? contentGroup->title`.
5. `embed_html` is trusted admin HTML stored verbatim on `content_items` and rendered only through `x-public.media-embed` raw mode, with precedence over `embed_url`.
6. `AdminUxSettings` was extended from the existing IMG-A baseline instead of recreated. The IMG-A commit hash was backfilled as `988676e`.
7. Transcript paste cleanup remains deferred to the format-probe follow-up because there is no approved transcript-convention mapping to implement safely in EP1.

## Implemented

- Schema: `content_items.title_prefix`, `content_items.embed_html`, and EP1 Admin UX settings migration.
- Admin settings: workspace presentation mode, transcription mode, hint line, language-code toggle, and TB1 picker container.
- Model layer: workspace transcription relation and explicit adopt/replace/start-fresh helpers.
- Admin workspace: dedicated create/edit pages, shared workspace schema, default table/relation-manager workspace URLs, classic edit retained as secondary.
- Media: raw public media component mode, iframe-src extraction helper, and Spotify lookup/fetch support.
- Public display: central content item display title helper used by cards, item page, and row component.
- Tests: focused EP1 workspace tests and public raw-embed/title-prefix regression tests.

## Tooling Notes

- Laravel Boost was used before implementation. It confirmed Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, Horizon 5.47.2, and current schema constraints.
- Boost docs were used for Filament 5 singular relationship-bound layouts, SettingsPage behavior, resource pages, action testing, and table actions.
- FilamentExamples MCP was used in two focused search passes for SettingsPage, resource custom pages, singular relationship form sections, table row actions, and modal actions. It exposed search/snippet access only; no source/read/fetch/detail tool was available.

## Targeted Verification

- `php artisan test --compact tests/Feature/EpisodeWorkspaceTest.php --filter='workspace|spotify|defaults|resolves|creates|replaces|settings'` passed.
- `php artisan test --compact tests/Feature/PublicItemPageMediaParserTest.php --filter='embed html|title prefix|approved embeds'` passed.
- `php artisan test --compact tests/Feature/AdminPhase02ResourcesTest.php --filter='navigation|hebrew slugs|content item hebrew slugs|content items from the content group relation manager|content item table row action|effective transcription edit action'` passed.
- `php artisan test --compact tests/Feature/ImageMediaCuratorTest.php --filter='saves the admin ux media naming strategy setting'` passed.
- `php artisan test --compact tests/Feature/PublicFrontCardTemplateBuilderTest.php --filter='content item card|combined title|template parts'` passed.

## Final Verification

- `vendor/bin/pint --test` passed.
- `php artisan test` passed once with 411 tests, 411 passed, 3,740 assertions.
- `vendor/bin/filacheck` passed with 0 issues.
- `npm run build` passed.
- `git diff --check` passed.

## Local Front Check Report

1. Workspace create route: covered by Livewire create-page test; manual browser review still pending Yoni.
2. Workspace edit route: covered by Livewire edit-page tests for save and replace actions; manual browser review still pending Yoni.
3. Public raw embed rendering: covered by HTTP rendered-output test for `data-test="media-embed-html"` and precedence over `embed_url`.
4. Public title-prefix display: covered by HTTP rendered-output tests for explicit prefix and group-title fallback.
5. Admin table/relation-manager default workspace actions: covered by Livewire action visibility tests.

## Commit hash

Final EP1 commit hash is reported after this handoff is committed as part of the EP1 implementation commit.
