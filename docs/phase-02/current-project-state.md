# Phase 02 Current Project State

This is the single source of truth for rolling Phase 02 prompt progress. Other active docs may describe stable dependencies and ownership, but should link here for current completion/progress status.

Recorded after the Markdown-only post-Prompt-10 prompt-progress centralization cleanup. This document intentionally avoids local secrets and should be updated when later prompts change the active baseline.

## Git State

- Current branch at cleanup preflight: `main` tracking `origin/main`.
- Branch tracking state at cleanup preflight: no ahead/behind marker reported by `git status --short --branch`.
- Working tree at cleanup preflight: clean.
- Latest docs cleanup commit recorded here: `1cb158a docs: centralize prompt progress and ai development lessons`.
- Latest local `HEAD` before Prompt 10 implementation: `014c6b0 docs: update phase two prompt state, completion details for Prompts 08 and 09, and readiness notes for Prompt 10`.
- Admin management UX repair commit is present in history: `16ab33a fix: repair admin management ux after phase two resources`.
- Prompt 09 implementation commit is present in history: `22e11d0 feat: add phase two admin content management`.
- Prompt 08 implementation commit is present in history: `b15f5c1 feat: add taxonomy tags pinning settings and media foundation`.
- Prompt 07 implementation commit remains in history: `7edb82d feat: add transcription model revision`.
- Prompt 10 implementation commit is present in history: `fad6721 feat: extend phase two import export`.
- Prompt 11 public homepage/search is implemented and committed as `7ef2fa7 feat: add public content item homepage search`.
- Pre-Prompt-12 documentation pack is present in history; latest pushed pre-Prompt-12 docs state is `c1237eb docs: add pre-prompt 12 documentation and guidelines for public admin contributors`.
- Prompt 11R public frontend custom Livewire/Blade refactor is implemented and committed as `bb4b97c refactor: customize public content item discovery`.
- Prompt 11A admin relationship UX is implemented and committed as `1d81ec0 feat: improve admin relationship management ux`.
- Prompt 11B public contributors/transcribers discovery is implemented and committed as `8998f7e feat: add public contributor discovery`.
- Prompt 12 readiness sync is implemented and committed locally as `23242a1 docs: prepare prompt twelve after public discovery work`.
- Prompt 12 public item page/media/parser is implemented and committed as `ffba2b3 feat: add public item page media and transcript parser`.
- Public Front v2 research and blueprint planning commits are present: `f9a1f80 docs: research public front v2 json settings blueprint plan`, `adbed99 docs: add blueprint results for public front v2 plans`, and `40aeafc docs: add execution plan for Public Front v2 implementation`.
- PodText brand-logo customization is implemented and committed as `6962c82 feat: add customizable brand logo and height for admin and public panels`; the logo exists at `public/images/podtext-logo.jpg`.
- Public Front v2 correction/Step 1 prompt pack is present as `716ee5a docs: add corrections to Public Front v2 execution plan and initial Step 1 prompt files`.
- Public Front v2 docs correction before Step 1 is implemented and committed as `5586ec8 docs: correct public front v2 execution plan`.
- Public Front v2 Step 1 JSON Settings Architecture is implemented and committed as `fb759b5 feat: add public front json settings architecture`.
- Public Front v2 Step 3 Card Template Builder is implemented and committed as `a0146ce feat: add public front card template builder foundation`.
- Public Front v2 Step 4 Public Display Sections and Loopers is implemented and committed as `c0ce7d7 feat: add public display section loopers`.
- Public Front v2 Step 5 Latest and Search UX is implemented and committed as `eea9164 feat: refine public latest and search ux`.
- Public Front v2 Step 6 Public Forms and Submissions is implemented and committed as `49f6ab0 feat: add public forms and submissions`.
- Public Front v2 Step 7 About Page Content and Team Builder is implemented and committed as `b4fe4d5 feat: add public about page content and team builder`.
- Public Front v2 Step 8 Podcasts and Groups UX is implemented and committed as `f3d137e feat: add public podcasts and groups ux`.
- Public Front v2 Step 9 Public Menu/Header and UX Fixes is implemented and committed as `5cf3363 feat: add public menu header and ux fixes`.
- Public Front v2 Step 9R Menu/Header UX Fixes is implemented and committed as `bfcda46 fix: refine public menu header and homepage ux`.
- Public Front v2 Step 9R Podcast Episode Grid Settings follow-up is implemented and committed as `af23555 feat: add podcast episode grid settings`.
- Public Front v2 Step 10 Contributors and Top Transcribers UX is implemented and committed as `37ce738 feat: refine contributors and top transcribers ux`.
- Post-Step-10 public label/header polish is committed through `cea4f60 fix: refine theme selector and search UX in public header`.
- Post-Step-10 follow-up sequence after the Step 10 handoff commit: `e8077ea` simplified public-facing Hebrew labels, `20970a3` aligned Hebrew content/podcast terminology, `802cf4a` temporarily enabled public panel SPA mode, `2b1c6b3` removed SPA mode and externalized content-group type label defaults to translation keys, and `cea4f60` refined public header search/theme selector layout.
- Public Front v2 Step 10R-A1 PublicFrontRenderContext foundation is implemented and committed as `a230410 feat: add public front render context foundation`.
- Public Front v2 Step 10R-A2 render context adoption is implemented and committed as `d6d0bec refactor: route public front settings through render context`.
- Public Front v2 Step 10R-B1 card template select/options UX is implemented and committed as `34c6032 fix: expose custom public card templates in settings`.
- Public Front v2 Step 10R-B2 content item card part rendering is implemented and committed as `e3c81de feat: render content item card template parts`.
- Public Front v2 Step 10R-B3 content group and contributor card part rendering is implemented and committed as `f712791 feat: render group and contributor card templates`.
- Public Front v2 post-B3 contributor item card overflow follow-up is committed as `549b331 refactor: remove unused contributor transcription list component from grid layout`.
- Public Front v2 post-B3 multi-transcriber/card-template continuation is active. Step 10R-M1 is complete as `800218a feat: add multi-transcriber relationship foundation`; Step 10R-M2 is complete as `e813513 feat: replace episode authors with transcription transcribers`; Step 10R-M3 is complete as `825004c feat: add public transcription policy and aggregates`; Step 10R-M4 is complete as `af9f399 feat: render public transcribers and transcription aggregates`; urgent hotfix Step 10R-HF1 is complete as `2a5ff96 fix: preserve transcript markdown formatting`; Step 10R-M5 is complete as `aa7568c feat: add card template grouped parts labels and icons`; Step 10R-IP1 is complete as `9d565d7 feat: add episode page settings and publication dates`; Step 10R-IP2 is complete through `280b7ef feat: refine episode podcast identity settings`; Step 10R-IP3 is complete as `d83edf8 feat: add transcript reading controls and actions menu`; Step 10R-M6 is complete with the stabilization audit, C1 superseded status, and `transcription_display` default alignment to `effective_only`. Step 10R-UX1 is complete with admin navigation/table/modal standards. Step 10R-UX2 is complete with the shared effective transcription edit action on both episode list surfaces and the v4 ledger/sequence amendment that schedules AX1-AX3. Step 10R-V1a is complete with default/no-image fallback settings. Step 10R-V1b is complete with the Heroicon enum registry and shared lazy icon picker. Step 10R-V1c is complete with custom hex color controls and a theme-safe cached podcast palette. Step 10R-P1 is the next mini-step, followed by S2, S1, P2, P3, AX1, SL1-SL4, AX2, AX3, B4, C2, and 9F-A through 9F-C.

## Prompt Progress

| Prompt | Status | Commit / evidence | Notes |
|---|---|---|---|
| Prompt 07 transcriptions model revision | Complete | `7edb82d feat: add transcription model revision` | Prompt 07 migrations are applied locally. |
| Prompt 08 taxonomy/settings/media foundation | Complete | `b15f5c1 feat: add taxonomy tags pinning settings and media foundation` | Spatie tags/settings foundation and media metadata fields exist. |
| Prompt 09 admin content management | Complete | `22e11d0 feat: add phase two admin content management` | Admin Resources and relation-manager baseline exist. |
| Admin UX repair | Complete | `16ab33a fix: repair admin management ux after phase two resources` | Repaired ContentItem edit tab behavior and related admin workflows. |
| Prompt 10 import/export | Complete | `fad6721 feat: extend phase two import export` | Native Filament import/export baseline exists and should be preserved by later prompts. |
| Post-Prompt-10 guidance sync | Complete | `773f1c0 docs: sync prompt workflow lessons after prompt ten` | Markdown-only guidance sync; did not start Prompt 11. |
| Post-Prompt-10 prompt-progress centralization cleanup | Complete | `1cb158a docs: centralize prompt progress and ai development lessons` | Markdown-only cleanup; centralized rolling progress in this file; did not start Prompt 11. |
| Prompt 11 public homepage/search | Complete | `7ef2fa7 feat: add public content item homepage search` | Public homepage/search lists `ContentItem` cards using public visibility rules, settings, filters, routes, and homepage section foundations. |
| Pre-Prompt-12 documentation pack | Complete | `c1237eb docs: add pre-prompt 12 documentation and guidelines for public admin contributors` | Adds Prompt 11R/11A/11B sequencing before Prompt 12 and ignores local Herd remote-site config. |
| Prompt 11R public frontend custom Livewire/Blade refactor | Complete | `bb4b97c refactor: customize public content item discovery` | Public homepage/search/category/tag listing no longer uses Filament Table as the public UI; custom Livewire state and Blade components render cards, filters, pagination, and homepage sections. |
| Prompt 11A admin relationship UX | Complete | `1d81ec0 feat: improve admin relationship management ux` | Adds safe admin create/edit option modals and `ContentGroupResource` -> `ContentItemsRelationManager`; Prompt 12 not started. |
| Prompt 11B public contributors/transcribers discovery | Complete | `8998f7e feat: add public contributor discovery` | Adds `top_transcribers`, public contributor directory, previews, full contributor page, and demo seeder state; Prompt 12 not started. |
| Prompt 12 readiness sync | Complete | `23242a1 docs: prepare prompt twelve after public discovery work` | Prepared Prompt 12 activation without starting implementation. |
| Prompt 12 media embed/item page/parser | Complete | `ffba2b3 feat: add public item page media and transcript parser` | Adds the public item page, safe media component behavior, and parse-only transcript viewer. |
| Public Front v2 planning/research | Complete | `40aeafc docs: add execution plan for Public Front v2 implementation` plus prior research/blueprint commits | Public Front v2 should run before Prompt 13 unless the user explicitly chooses dashboard metrics first. |
| Public Front v2 docs correction before Step 1 | Complete | `5586ec8 docs: correct public front v2 execution plan` | Corrects execution order, reserves transcription publication policy, and requires Step 1 handoff. |
| Public Front v2 Step 1 JSON Settings Architecture | Complete | `fb759b5 feat: add public front json settings architecture` | Adds the JSON settings architecture foundation and creates `docs/phase-02/public-front-v2-step1-json-settings-handoff.md` for ChatGPT/Yoni review. |
| Public Front v2 Step 3 Card Template Builder | Complete | `a0146ce feat: add public front card template builder foundation` | Adds JSON-first card template registry/validator support, support classes, admin settings UI, compatibility rendering attributes, tests, and Step 3 handoff. |
| Public Front v2 Step 4 Public Display Sections and Loopers | Complete | `c0ce7d7 feat: add public display section loopers` | Adds homepage section JSON config columns, section/looper validation and query support, admin config fields, Step 3 template integration, tests, and Step 4 handoff. |
| Public Front v2 Step 5 Latest and Search UX | Complete | `eea9164 feat: refine public latest and search ux` | Adds looper-driven Latest UX, search filter drawer, multi-select category/tag filters, card layout repair, controlled content-item renderer, tests, and Step 5 handoff. |
| Public Front v2 Step 6 Public Forms and Submissions | Complete | `49f6ab0 feat: add public forms and submissions` | Adds JSON-first public form definitions, `PublicFormSubmission` schema/model/resource, Livewire public form modal/slide-over, honeypot/rate limiting, admin settings UI, tests, and Step 6 handoff. |
| Public Front v2 Step 7 About Page Content and Team Builder | Complete | `b4fe4d5 feat: add public about page content and team builder` | Adds JSON-first About page content, public `/about`, safe Markdown/RichEditor rendering, team profiles in JSON settings, team/about image upload constraints, optional Step 6 form CTA integration, tests, and Step 7 handoff. |
| Public Front v2 Step 8 Podcasts and Groups UX | Complete | `f3d137e feat: add public podcasts and groups ux` | Adds canonical `/podcasts`, public group detail pages at `/podcasts/{contentGroupSlug}`, JSON-first podcast settings, public group query support, group cards, category/search UX, tests, and Step 8 handoff. |
| Public Front v2 Step 9 Public Menu/Header and UX Fixes | Complete | `5cf3363 feat: add public menu header and ux fixes` | Adds tabbed public settings organization, About/team card fixes, contributor list/preview repairs, homepage chrome/header fixes, JSON-powered public menu/header, theme selector, content-block sections, tests, and Step 9 handoff. |
| Public Front v2 Step 9R Menu/Header UX Fixes | Complete | `bfcda46 fix: refine public menu header and homepage ux` | Verifies Step 8/9 plans, improves FilamentExamples MCP discipline, repairs root-query homepage chrome, extends header logo/search/alignment/theme behavior, adds image styling settings, repairs contributor preview grid, and documents future footer/section-builder scope. |
| Public Front v2 Step 9R Podcast Episode Grid Settings follow-up | Complete | `af23555 feat: add podcast episode grid settings` | Adds JSON-first podcast detail episode grid/settings controls under `podcasts_page.group_page`, keeps `ContentItemBrowser` as Livewire owner, and was followed by the now-complete Step 10 implementation. |
| Public Front v2 Step 10 Contributors and Top Transcribers UX | Complete | `37ce738 feat: refine contributors and top transcribers ux` | Adds `contributors_page` settings, settings UI, horizontal top-transcriber selector/preview, contributor directory/page controls, grouped contributor transcription titles, tests, and Step 10 handoff. |
| Post-Step-10 public label/header polish | Complete | `e8077ea`, `20970a3`, `802cf4a`, `2b1c6b3`, `cea4f60` | Simplifies Hebrew public/admin labels, aligns podcast/episode terminology, records that temporary public panel SPA mode was removed, externalizes content-group type-label defaults to translation keys, and refines public header search/theme selector layout. |
| Public Front v2 Step 10R-A1 render context foundation | Complete | `a230410 feat: add public front render context foundation` | Adds request-scoped `PublicFrontRenderContext`, `PublicFrontRenderContextFactory`, scoped app binding, group accessors including future-safe `footer()`, and focused tests. |
| Public Front v2 Step 10R-A2 render context adoption | Complete | `d6d0bec refactor: route public front settings through render context` | Routes public Livewire components, public page classes, menu/about/card-template support services, and Blade compatibility defaults through `PublicFrontRenderContext`; public output behavior is intended to remain unchanged. |
| Public Front v2 Step 10R-B1 card template select/options UX | Complete | `34c6032 fix: expose custom public card templates in settings` | Adds family-scoped resolver option helpers, makes podcast settings template selects read safely normalized same-session `card_templates` state, routes homepage section template options through the resolver, and documents contributor template setting selection as deferred because no contributor template key setting exists yet. |
| Public Front v2 Step 10R-B2 content item card part renderer | Complete | `e3c81de feat: render content item card template parts` | Adds a controlled content item card presenter, makes supported content item template parts visibly render on homepage/search/category/tag and podcast detail item cards, and keeps group/contributor renderers deferred to Step 10R-B3. |
| Public Front v2 Step 10R-B3 content group and contributor card renderers | Complete | `f712791 feat: render group and contributor card templates`; follow-up `549b331 refactor: remove unused contributor transcription list component from grid layout` | Adds controlled presenters for `content_group` and `contributor` cards so `/podcasts`, homepage group/contributor sections, contributor directory cards, and top-transcriber selector cards visibly honor safe card template parts. The follow-up removed the old contributor transcription list from contributor item grid cards to avoid overflow. |
| Public Front v2 Step 10R-M1 multi-transcriber schema and model foundation | Complete | `800218a feat: add multi-transcriber relationship foundation` | Adds the `author_transcription` pivot and backfills it from `transcriptions.author_id`, adds ordered multi-transcriber relationships/helpers, preserves `transcriptions.author_id` compatibility/primary storage, and keeps first-transcription-auto-featured behavior intact. `author_content_item` remained for M2 and is removed by Step 10R-M2. |
| Public Front v2 Step 10R-M2 episode-author removal and transcription transcriber conversion | Complete | `e813513 feat: replace episode authors with transcription transcribers` | Drops `author_content_item`, removes `ContentItem::authors()` and `Author::contentItems()`, converts admin transcription forms/actions plus import/export/public search/display paths to `Transcription::authors()`, and keeps `transcriptions.author_id` synchronized as compatibility primary storage. |
| Public Front v2 Step 10R-M3 public transcription policy and aggregates | Complete | `825004c feat: add public transcription policy and aggregates` | Adds normalized `transcription_policy`, scoped policy/selector/aggregate services, pivot-backed contributor counts, featured-only/all-published count behavior, public item/group aggregate subselects, and policy-aware public transcriber filters. |
| Public Front v2 Step 10R-M4 public rendering and aggregate attributes | Complete | `af9f399 feat: render public transcribers and transcription aggregates` | Renders transcription-backed transcribers, optional all-published count badges, per-transcription viewer tab transcribers, contributor-context transcription data, and group aggregate attributes. Adds `transcription_display` settings, a settings migration, non-production lazy-loading prevention, memoized viewer/top-transcriber lists, and a bounded query-count harness. Next mini-step is Step 10R-M5 grouped parts, labels, and icons. |
| Public Front v2 Step 10R-HF1 transcript viewer Markdown rendering hotfix | Complete | `2a5ff96 fix: preserve transcript markdown formatting` | Removes the capped Symfony sanitizer pass from the Markdown path, adds transcript-specific soft-break rendering and fixed transcript typography classes, guards the executable-block prefilter against PCRE null-wipe, and keeps generated Markdown images HTTPS-only. P3 transcript render economy remains scheduled; Step 10R-M5 remains the next mini-step. |
| Public Front v2 Step 10R-M5 card-template labels/icons/groups | Complete | `aa7568c feat: add card template grouped parts labels and icons` | Adds escaped label rendering, finite Heroicon-backed icon rendering, label alignment tokens, one-level `part_group` rendering, nested admin Builder support, validator normalization/rejection coverage, and rendered-output tests across content item, content group, and contributor card families. No schema or new settings keys were added. The next mini-step is Step 10R-IP1 for episode-page settings/date foundations. |
| Public Front v2 Step 10R-IP1 episode page settings/date foundation | Complete | `9d565d7 feat: add episode page settings and publication dates` | Adds the `item_page` settings group, Spatie settings migration, Episode page settings tab, site/original/transcription date settings, finite info-badge tokens, `PublicFrontRenderContext::itemPage()`, and content-item card attributes for `site_published_date` and `original_published_date`. R1 data/attributes, R2-R6, R13 token foundation, and R23 are landed. IP2 owns public episode page placement/rendering. |
| Public Front v2 Step 10R-IP2 episode page header/info layout | Complete | `280b7ef feat: refine episode podcast identity settings` | Extends `item_page` with `show_breadcrumbs`, `podcast_identity`, and ordered `info_fields`; adds settings migrations and admin controls; rebuilds the public episode header with item/podcast image fallback, linked podcast identity, configured date labels/icons, linked category/tag/transcriber info fields, and site-wide link-audit tests. Review-fix coverage adds podcast identity style (`badge`, `text`, `title`, `hidden`), size, semantic/image-sampled color tokens, and placement above/below/before/after the title. R1 page part and R11-R18 are landed. |
| Public Front v2 Step 10R-IP3 transcript actions and reading UX | Complete | `d83edf8 feat: add transcript reading controls and actions menu` | Adds `item_page.show_transcript_actions_menu` default false, settings migration/admin toggle, share block above the player, transcript details row, settings-gated Blade/Alpine actions menu, font-size controls, fullscreen reading mode, and player/media column toggle. R7-R10 and R19-R22 are landed. |
| Public Front v2 Step 10R-M6 stabilization closeout | Complete | `ebfa68e docs: summarize public front multi-transcriber card template arc` | Verifies M1-M5, HF1, and IP1-IP3 regressions; records R1-R23 as landed; confirms `author_content_item`, `ContentItem::authors()`, and `Author::contentItems()` remain absent; marks C1 superseded; aligns existing `transcription_display` defaults/fallbacks/settings rows to `effective_only`; leaves F1-F3, F7, F11-F13, and F15 for P1-P3/B4/C2. |
| Public Front v2 post-M6 admin/settings enhancement planning | Planned | `docs/phase-02/public-front-v2-admin-settings-enhancement-plan.md`; `docs/research/public-front-v2/19-admin-settings-enhancement-mcp-research.md` | Adds the v4 queue for admin navigation/table/modal standards, effective transcription edit action, split default-image/icon/color settings, caching, backups/imports, AX1-AX3 motion work, slider/modal display templates, and the remaining performance/card steps. |
| Public Front v2 Step 10R-UX1 admin navigation/table/modal standards | Complete | `a88115f feat: standardize admin navigation tables and modals` | Adds the central admin navigation order map, admin-scoped global table/action/Section defaults, combined relation-manager tabs with content first on item/group edit pages, scoped tab-label CSS, tests, and the earlier ledger/sequence amendment. |
| Public Front v2 Step 10R-UX2 effective transcription edit action | Complete | `e99f22a feat: add effective transcription edit action to episode lists` | Adds one shared action class mounted on the Episodes resource table and podcast Episodes relation manager, two-tier admin fallback after public-effective resolution, transcriber pivot/`author_id` synchronization through `Transcription::syncTranscribers()`, a zero-query context column, focused tests, and the v4 ledger/sequence alignment with AX1-AX3 scheduled. |
| Public Front v2 Step 10R-V1a default/no-image fallback settings | Complete | `4c545eb feat: add default image fallback settings` | Adds `default_images` settings for global/content item/content group/contributor families, finite inherit/custom/none modes, validator/migration/render-context support, constrained admin FileUploads, and shared fallback rendering on public cards and detail pages. |
| Public Front v2 Step 10R-V1b Heroicon registry and shared icon picker | Complete | `ba43145 feat: expand icon settings with searchable heroicon picker` | Adds `PublicFrontIconRegistry`, a shared lazy searchable `IconSelect`, enum-name icon token normalization, permanent legacy alias compatibility, settings migration for stored aliases, and focused V1b tests. Step 10R-V1c is complete. |
| Public Front v2 Step 10R-V1c custom colors and theme-safe podcast palette | Complete | this commit: `feat: add custom colors and theme safe podcast palette` | Adds strict custom hex settings beside finite color tokens, conditional admin ColorPickers, CSS-variable-only public rendering, D9 decision note, cached light/dark podcast cover palette variants, and focused V1c tests. Step 10R-P1 is next. |
| Prompt 13 dashboard metrics | Not started / blocked unless explicitly chosen by user | Active prompt/blueprint | Owns editorial dashboard widgets after Public Front v2 Step 12 readiness or an explicit dashboard-first decision. |
| Prompt 14 viewer/studio future plan | Future planning after Prompt 13 | Active prompt/blueprint | Documentation/planning only. |
| Prompt 15 Filament Blueprint security audit | Audit after Prompt 14 | Active prompt/blueprint | Audit-only unless fixes are explicitly approved. |

## Active Known Blockers

- Prompt 13 dashboard metrics has not started and is intentionally blocked until Public Front v2 reaches the approved post-B3 readiness point or the user explicitly chooses dashboard metrics first.
- The `model:show` baseline issue below remains unresolved and should be avoided until investigated.
- The active post-B3 implementation sequence is Step 10R-P1, S2, S1, P2, P3, AX1, SL1, SL2, SL3, SL4, AX2, AX3, B4, C2, and 9F-A through 9F-C. Urgent Step 10R-HF1, Step 10R-M1 through Step 10R-M6, Step 10R-IP1 through Step 10R-IP3, Step 10R-UX1 through Step 10R-UX2, and Step 10R-V1a through Step 10R-V1c are complete; IP2 includes the podcast identity style/position/image-color review fix, IP3 includes local-only transcript reading controls, M6 marks the original Step 10R-C1 single-author attribution task as superseded, UX2 records the v4 ledger/sequence amendment with AX1-AX3 scheduled, V1a adds default/no-image fallback settings, V1b adds enum-backed icon settings, and V1c adds strict custom hex color settings plus a cached theme-safe podcast palette. Step 9F/10F Footer + Rich Section Builder must wait until all prior 10R work, including AX1-AX3, SL1-SL4, B4, and C2, is complete. Step 11 Seeders, Demo Data, Assets, and Cleanup must wait for approved Step 10R and Step 9F/10F completion or explicit Yoni approval. Prompt 13 has not started. The full Step 2 transcription publication workflow remains deferred/reserved; M3/M4 only added the minimal public read/display/count policy.

## Deferred Items

- `transcript_file` import support is deferred until an approved import package structure for referenced `.md`/`.txt` files exists.
- Curated homepage query sections are deferred until a concrete query-builder spec exists.
- Homepage result previews in admin forms remain deferred.
- Step 5B Card Template Admin Preview UX remains deferred.
- Footer-builder v2 and nested/dropdown public menu editing remain deferred beyond Step 10. Step 9F/10F foundation should wait until Step 10R-M1 through Step 10R-M6, Step 10R-IP1 through Step 10R-IP3, Step 10R-P1 through Step 10R-P3, Step 10R-B4, and Step 10R-C2 are complete and should still run before Step 11 seeders if footer/rich-section demo content is required. The post-M6 UX/V/S settings enhancement mini-steps run before or around P1-P3 as recorded in the central ledger.
- Public form email notifications remain deferred.
- Public form file uploads remain deferred.
- Advanced homepage section manual-selection controls such as "select all filtered results" and "deselect all filtered results" are deferred; Step 4 ships explicit include/exclude ID selection with public visibility rechecks.
- Associate-existing transcription remains deferred because `Transcription` belongs to one `ContentItem`.
- A separate public volunteer/contributor profile table remains deferred; Prompt 11B uses `Author` as the public-safe contributor/transcriber entity.
- `ContentItemForm::featured_transcription_id` remains create-disabled; transcriptions are created through item-scoped relation manager/full Resource workflows.
- `TranscriptionForm::content_item_id` remains create-disabled; creating a content item inline from a transcript form is too large for a safe selector modal.
- `SpatieTagsInput` remains plugin-managed and was not replaced with custom pivot or modal behavior.
- The Add transcription table/relation-manager row action reuses the existing author selector and remains options-only because it is not a relationship-bound Resource form selector.
- Editorial dashboard widgets belong to Prompt 13.
- Viewer/studio sync planning belongs to Prompt 14; no sync/studio implementation is active yet.
- Public Front v2 Step 2 / Reserved transcription publication policy is deferred. Keep the current featured/effective transcription behavior unless a later isolated prompt explicitly promotes the policy work.

## Tooling State

- Laravel: 13.18.0.
- PHP: 8.4.22 from `php artisan about`; Laravel Boost reports PHP 8.4.
- Filament: 5.6.7.
- Livewire: 4.3.3.
- Laravel Boost: 2.4.11 installed and available through MCP.
- Pest: 4.7.4.
- FilaCheck: 1.2.3 installed.
- FilaCheck Pro: 1.2.7 installed.
- Spatie Laravel Tags: 4.12.0 installed.
- Filament Spatie Laravel Tags plugin: 5.6.7 installed.
- Spatie Laravel Settings: 3.9.0 installed.
- Filament Spatie Laravel Settings plugin: 5.6.7 installed.
- App locale from `php artisan about`: `he`.
- App timezone from `php artisan about`: `UTC`; Phase 02 UI requirements still require Israel/Hebrew date presentation in `Asia/Jerusalem` while storing dates with Laravel's normal conventions.

## Boost MCP Status

Laravel Boost MCP tools were exposed and usable during Prompt 10.

- Boost tools used: `application_info`, `database_schema`, and `search_docs`.
- Boost confirmed Laravel 13.17.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, and SQLite.
- Boost schema inspection confirmed the post-Prompt-08/09 tables and fields listed below.
- Boost `search_docs` was used for current Filament import/export APIs before code changes.
- FilamentExamples MCP `search_examples` returned snippet-level examples for `ImportAction`, `ExportAction`, `ExportBulkAction`, `Importer`, and `Exporter` patterns.
- Prompt 11 also used Boost `application_info`, `database_schema`, and `search_docs` before changing Livewire, Filament table/filter, URL-state, Spatie Settings, and settings-page behavior.
- Prompt 11 FilamentExamples research returned snippet/source examples for public Filament table, card, and filter patterns.
- Prompt 11R used Boost `application_info`, `database_schema`, and `search_docs` for Livewire URL state, pagination, Eloquent queries, settings, and Filament page context before changing code.
- Prompt 11R FilamentExamples research returned source snippets for public Filament table/filter examples; those snippets were used only to identify the prior table pattern to remove from the public listing.
- Prompt 11A used Boost `application_info`, detailed `database_schema`, and `search_docs` for Filament 5 `Select::relationship()`, option actions, relation managers, stable relation keys, shared forms, and `hiddenOn()` before changing code.
- Prompt 11A FilamentExamples research returned source snippets for relation-manager and selector/action patterns; access level was snippet/source through `search_examples`, not a full repository fetch.
- Prompt 11B used Boost `application_info`, `database_schema`, and `search_docs` for Livewire 4 URL attributes, `wire:model.live.debounce`, pagination, Laravel seeding, and public Filament page patterns before changing code.
- Prompt 11B FilamentExamples research returned snippet/source examples for custom multi-panel Filament Pages and Livewire-rendered page content; snippets were used as page-shell design reference, not copied wholesale.
- Prompt 12 used Boost `application_info`, `database_schema`, and `search_docs` for the installed Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, public page, Livewire URL state, Alpine, media rendering, and test behavior before changing code.
- Prompt 12 FilamentExamples research returned snippet/source examples for custom public page and Livewire-rendered page content; snippets were used as reference only.
- Public Front v2 Step 1 used Boost `application_info`, `database_schema`, and `search_docs` for installed Laravel 13.18.0, Filament 5.6.7, Pest 4.7.4, Spatie Settings storage, settings-page save/fill hooks, and array validation behavior before code changes.
- Public Front v2 Step 1 FilamentExamples research returned snippet-level settings/form examples only; no full source/detail fetch tool was exposed.
- Public Front v2 Step 4 used Boost `application_info`, `database_schema`, and `search_docs` before changing migrations, casts, Filament form fields, Livewire rendering, and tests.
- Public Front v2 Step 4 FilamentExamples research returned snippet/source-level examples for dynamic homepage sections, section manager patterns, looper/query display concepts, and admin selection/table-selection patterns; no parallel agents or worktrees were used.
- Public Front v2 Step 5 used Boost `application_info`, `database_schema`, and `search_docs` before changing Livewire URL state, pagination, Alpine drawer behavior, Blade rendering, card template rendering, and tests.
- Public Front v2 Step 5 FilamentExamples `search_examples` research returned snippet-level examples for public Livewire tables/cards/filters and grid/card patterns; no full source/detail fetch was exposed for the requested latest drawer/looper renderer patterns.
- Public Front v2 Step 6 used Boost `application_info`, `database_schema`, and `search_docs` before changing migrations, Eloquent models/enums/casts, validation, rate limiting, Livewire forms, Filament Resources, Filament form components, and Pest tests.
- Public Front v2 Step 6 FilamentExamples `search_examples` research returned snippet/source-level examples for dynamic forms, public/custom pages, Resource tables, and actions; no full source/detail fetch tool was exposed.
- Public Front v2 Step 7 used Boost `application_info`, `database_schema`, and `search_docs` before changing Spatie Settings JSON normalization, Filament Builder/Repeater/RichEditor/MarkdownEditor/FileUpload usage, public pages, Livewire-safe rendering, and Pest tests.
- Public Front v2 Step 7 FilamentExamples `search_examples` research returned snippet/source-level examples for custom settings pages, repeaters, FileUpload image handling, and custom public pages; no full source/detail fetch tool was exposed.
- Public Front v2 Step 8 used Boost `application_info`, `database_schema`, and `search_docs` before changing public routes, Filament public Pages, Livewire URL state and pagination, Eloquent query scopes/counts, Spatie Settings JSON normalization, Filament settings fields, and Pest tests.
- Public Front v2 Step 8 FilamentExamples `search_examples` research returned snippet/search-level examples for public cards/pages, dynamic sections, content group/list pages, and search/filter patterns; no full source/detail fetch tool was exposed.
- Public Front v2 Step 9 used Boost `application_info`, `database_schema`, and `search_docs` before changing Filament settings tabs/sections, Livewire URL state and pagination, public panel render hooks/layout behavior, Alpine interactions, Filament Builder/Repeater behavior, and Pest tests.
- Public Front v2 Step 9 FilamentExamples `search_examples` research returned snippet/search-level examples for settings tabs/page layouts/menu builder patterns; no full source/detail fetch tool was exposed.
- Public Front v2 Step 9R used Boost `application_info`, `database_schema`, and `search_docs` before changing Filament settings tabs/sections, public panel header rendering, Livewire URL state, Blade rendering, and Pest tests.
- Public Front v2 Step 9R FilamentExamples `search_examples` research was run in focused batches plus a refined second pass for settings tabs, public header/menu, card grids, file/logo upload patterns, and Markdown/RichEditor rendering. No source/read/fetch details tool was exposed beyond search snippets.
- Public Front v2 Step 10 used Boost `application_info`, `database_schema`, and `search_docs` before changing Livewire URL state, pagination, Filament settings fields, Eloquent aggregate/public visibility queries, card rendering, and Pest/Livewire tests.
- Public Front v2 Step 10 FilamentExamples `search_examples` research was run in focused batches plus a refined second pass for directory cards, selector/preview state, top/ranked sections, pagination/grid controls, settings field organization, and public Livewire pages. No source/read/fetch details tool was exposed beyond search snippets.
- Public Front v2 Step 10R-B3 used Boost `application_info`, `database_schema`, and `search_docs` before changing Blade components, presenters, Livewire-rendered card surfaces, and Pest tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for card grids, profile view data, custom view cards, eager-loaded cards, and Livewire card grids; access level was search/snippet only.
- Public Front v2 Step 10R-M1 used Boost `application_info`, `database_schema`, `database_query`, and `search_docs` before changing migrations, Eloquent many-to-many relationships, model events/helpers, and Pest tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for multiple relationship selects, pivot/repeater patterns, and relationship filters; access level was search/snippet only.
- Public Front v2 Step 10R-M2 used Boost `application_info`, `database_schema`, `database_query`, and `search_docs` before dropping the old pivot, changing Eloquent relationships, Filament forms/tables/relation managers, native import/export classes, Livewire URL-backed search state, public rendering, and Pest tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for multiple relationship selects, belongs-to-many relation state, searchable filters, and importer/exporter relationship patterns; access level was search/snippet only.
- Public Front v2 Step 10R-M3 used Boost `application_info`, `database_schema`, `database_query`, and `search_docs` before changing Spatie Settings JSON policy, Eloquent query helpers, aggregate subqueries, Livewire public filter behavior, and Pest tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for settings pages, public page query data, aggregate counts, and Livewire URL state; access level was search/snippet only.
- Public Front v2 Step 10R-M4 used Boost `application_info`, `database_schema`, `database_query`, and `search_docs` before changing public rendering, Livewire computed properties, settings tokens, lazy-loading prevention, card presenters/registries, and Pest query-count tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for public cards, URL state, nested settings, aggregate stats, eager-loaded view data, and custom public Blade/page patterns; access level was search/snippet only.
- Public Front v2 Step 10R-HF1 used Boost `application_info` and `search_docs` before changing Markdown rendering, transcript viewer Blade output, and Pest rendered-output assertions. Local vendor source was inspected for Symfony HtmlSanitizer `maxInputLength`/`withMaxInputLength(-1)` behavior and CommonMark `renderer.soft_break`, `html_input`, and `allow_unsafe_links` options.
- Public Front v2 Step 10R-M5 used Boost `application_info`, `database_schema`, and `search_docs` before changing card-template settings, validators, presenters, Blade rendering, and Pest rendered-output tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for card grids, nested Builder/repeater settings, safe icon maps, and metadata row rendering; access level was search/snippet only.
- Public Front v2 Step 10R-IP1 used Boost `application_info`, `database_schema`, `database_query`, and `search_docs` before changing Spatie Settings JSON, settings migrations, Filament SettingsPage tabs/fieldsets, card-template attributes, presenter date formatting, and Pest rendered-output tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for settings tabs, nested settings fields, date badge settings, safe icon maps, and public detail page patterns; access level was search/snippet only.
- Public Front v2 Step 10R-IP2 used Boost `application_info`, `database_schema`, and `search_docs` before changing the episode page header, Spatie Settings JSON, settings migrations, Filament SettingsPage repeaters/sections, eager-loaded public page rendering, and Pest rendered-output tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for public detail pages, settings repeaters, metadata badge links, custom page view data, and public Blade card links; access level was search/snippet only.
- Public Front v2 Step 10R-IP3 used Boost `application_info`, `database_schema`, and `search_docs` before changing the transcript viewer, Spatie Settings JSON, settings migration, Episode page settings tab, public item page media/share layout, Alpine/localStorage controls, and Pest rendered-output tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for public detail pages, media sidebar/share actions, settings tabs, Alpine fullscreen/font-size controls, dropdown/action-group patterns, and custom page view data; access level was search/snippet only.
- Public Front v2 Step 10R-M6 used Boost `application_info`, `database_schema`, `database_query`, and `search_docs` before the stabilization audit and default-alignment settings migration. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for settings pages, public detail pages, media sidebars, Livewire public page tests, settings repeaters, Alpine action groups, computed Livewire pages, and clipboard actions; access level was search/snippet only.
- Public Front v2 post-M6 admin/settings enhancement planning used Boost `application_info`, `database_schema`, and `search_docs` for Filament navigation sorting, record-action placement, relation-manager tabs, action modal width, FileUpload, ColorPicker, settings import/export, and transaction/cache behavior. FilamentExamples `search_examples` was run in focused batches plus refined passes for icon selects, relation tabs, table actions, wide action modals, settings pages, FileUpload settings, ColorPicker/custom color fields, and settings import/export examples; access level was search/snippet only.
- Public Front v2 Step 10R-UX1 used Boost `application_info`, `database_schema`, and `search_docs` before changing Filament navigation sorting, `configureUsing()` defaults, table record-action placement, action modal widths, Section spans, and combined relation-manager tabs. FilamentExamples `search_examples` was run in short batches plus refined passes for navigation sort, table action placement, modal/section width patterns, relation manager tabs, and admin theme CSS; access level was search/snippet only.
- Public Front v2 Step 10R-UX2 used Boost `application_info`, `database_schema`, and `search_docs` before changing Filament table actions, modal `fillForm()`/`action()` behavior, `extraModalFooterActions()`, relation-manager action mounting, and `TestAction` assertions. FilamentExamples `search_examples` was run in short batches plus refined passes for editing related records through table modal actions, custom action classes, and modal footer links; access level was search/snippet only.
- Public Front v2 Step 10R-V1a used Boost `application_info`, `database_schema`, and `search_docs` before changing Spatie Settings JSON, settings migrations, Filament SettingsPage FileUpload fields, public image fallback rendering, and Pest rendered-output tests. FilamentExamples `search_examples` was run in short batches plus a refined pass for SettingsPage FileUpload image settings and card fallback patterns; access level was search/snippet only.
- Public Front v2 Step 10R-V1b used Boost `application_info`, `database_schema`, and `search_docs` before changing Heroicon enum settings, Filament `Select` lazy search/HTML labels, Spatie settings migrations, and Pest/Livewire assertions. FilamentExamples `search_examples` was run in short batches plus a refined pass for Yoni's selected icon picker reference and lazy searchable Select patterns; access level was search/snippet only.
- Public Front v2 Step 10R-V1c used Boost `application_info`, `database_schema`, and `search_docs` before changing custom color settings, Filament `ColorPicker` fields, Spatie settings migration/validation, Laravel cache usage, storage-safe cover sampling, and Pest public rendering assertions. FilamentExamples `search_examples` was run in short batches plus a refined pass for ColorPicker/settings-page and conditional-field patterns; access level was search/snippet only.

## Application Shape

- Database driver: SQLite.
- Public panel root: `/`.
- Admin panel root: `/admin`.
- `php artisan route:list --path=contributors` reports the public contributor directory and contributor detail routes.
- Existing public pages remain:
  - `App\Filament\Public\Pages\AboutPage`
  - `App\Filament\Public\Pages\BrowseContentGroups`
  - `App\Filament\Public\Pages\BrowsePublicContentGroups`
  - `App\Filament\Public\Pages\SearchContentItems`
  - `App\Filament\Public\Pages\BrowseCategoryContentItems`
  - `App\Filament\Public\Pages\BrowseTagContentItems`
  - `App\Filament\Public\Pages\BrowseContributors`
  - `App\Filament\Public\Pages\ShowContributor`
  - `App\Filament\Public\Pages\ShowContentGroup`
  - `App\Filament\Public\Pages\ShowContentItem`
- Existing public Livewire components remain:
  - `App\Livewire\Public\ContentGroupBrowser`
  - `App\Livewire\Public\ContentItemBrowser`
- Prompt 11 public homepage/search component:
  - `App\Livewire\Public\ContentItemSearch`
- Prompt 11B public contributor components:
  - `App\Livewire\Public\ContributorDirectory`
  - `App\Livewire\Public\ContributorContentItems`
- Public Front v2 Step 10 public contributor/top-transcriber component:
  - `App\Livewire\Public\TopTranscribersSection`
- Prompt 12 public item transcript viewer component:
  - `App\Livewire\Public\ContentItemTranscriptViewer`
- PodText logo baseline:
  - `public/images/podtext-logo.jpg`
- Prompt 11R public Blade components:
  - `resources/views/components/public/contributor-card.blade.php`
  - `resources/views/components/public/content-item-card.blade.php`
  - `resources/views/components/public/content-group-badge.blade.php`
  - `resources/views/components/public/content-item-grid.blade.php`
  - `resources/views/components/public/public-filter-panel.blade.php`
- Public Front v2 Step 10 public Blade components:
  - `resources/views/components/public/contributor-item-grid.blade.php`
  - `resources/views/components/public/contributor-transcription-list.blade.php`
- Prompt 11 public card option mapper:
  - `App\Support\PublicContent\PublicContentCardOptions`
- Prompt 11B public query helpers:
  - `App\Support\PublicContent\PublicContentItemQueries`
  - `App\Support\PublicContent\PublicContributorDiscovery`
- Prompt 11A admin helper:
  - `App\Filament\Resources\Support\RelationshipOptionForms`
- Prompt 11A admin relation manager:
  - `App\Filament\Resources\ContentGroups\RelationManagers\ContentItemsRelationManager`
- Public Front v2 Step 10R-UX1 admin navigation support:
  - `App\Filament\Support\AdminNavigationOrder`
  - `App\Filament\Support\Concerns\UsesAdminNavigationOrder`
  - `App\Filament\Pages\Dashboard`
- Prompt 12 parser:
  - `App\Support\Transcripts\TranscriptSegmentParser`
- Public Front v2 Step 1 support classes:
  - `App\Support\PublicFront\PublicFrontConfigRegistry`
  - `App\Support\PublicFront\PublicFrontConfigReader`
  - `App\Support\PublicFront\PublicFrontConfigValidator`
  - `App\Support\PublicFront\PublicFrontConfigResult`
  - `App\Support\PublicFront\PublicFrontInvalidConfig`
- Public Front v2 Step 3 card template support classes:
  - `App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry`
  - `App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver`
  - `App\Support\PublicFront\Cards\PublicFrontCardTemplateRenderer`
  - `App\Support\PublicFront\Cards\PublicFrontCardTemplate`
  - `App\Support\PublicFront\Cards\PublicFrontCardPart`
- Public Front v2 Step 4 display section support classes:
  - `App\Support\PublicFront\Sections\PublicDisplaySectionRegistry`
  - `App\Support\PublicFront\Sections\PublicDisplaySectionConfigValidator`
  - `App\Support\PublicFront\Sections\PublicDisplaySectionConfigResult`
  - `App\Support\PublicFront\Sections\PublicDisplaySectionResolver`
  - `App\Support\PublicFront\Sections\PublicDisplaySectionQueryResolver`
  - `App\Support\PublicFront\Sections\PublicDisplaySectionResult`
- Public Front v2 Step 5 Latest/Search UX surfaces:
  - `App\Livewire\Public\ContentItemSearch` now owns latest section controls and multi-select search filter state.
  - `App\Support\PublicFront\Cards\PublicFrontCardTemplateRenderer::contentItemPresentation()` returns controlled content-item card presentation metadata.
  - `resources/views/components/public/public-filter-panel.blade.php` renders the public search filter drawer.
- Public Front v2 Step 6 Public Forms and Submissions surfaces:
  - `App\Livewire\Public\PublicFormModal`
  - `App\Models\PublicFormSubmission`
  - `App\Enums\PublicFormFieldType`
  - `App\Enums\PublicFormSubmissionStatus`
  - `App\Support\PublicFront\Forms\PublicFormDefinitionRegistry`
  - `App\Support\PublicFront\Forms\PublicFormPayloadValidator`
  - `App\Support\PublicFront\Forms\PublicFormSchemaFactory`
  - `App\Support\PublicFront\Forms\PublicFormSubmissionPresenter`
  - `App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource`
  - `resources/views/livewire/public/public-form-modal.blade.php`
  - `docs/phase-02/public-front-v2-step6-public-forms-submissions-handoff.md`
- Public Front v2 Step 7 About Page Content and Team Builder surfaces:
  - `App\Filament\Public\Pages\AboutPage`
  - `App\Support\PublicFront\About\PublicAboutPageRegistry`
  - `App\Support\PublicFront\About\PublicAboutPageRenderer`
  - `resources/views/filament/public/pages/about-page.blade.php`
  - `resources/views/components/public/about/team-section.blade.php`
  - `resources/views/components/public/about/profile-card.blade.php`
  - `docs/phase-02/public-front-v2-step7-about-page-content-team-builder-handoff.md`
- Public Front v2 Step 7 About page JSON schema lives under `public_content.about_page` with `enabled`, `title`, `kicker`, `description`, `blocks`, `team_profiles`, and `settings`.
- Public Front v2 Step 7 team profile JSON schema lives under `public_content.about_page.team_profiles`; no `AboutPage`, `AboutPageBlock`, or `TeamProfile` model/table exists.
- Public Front v2 Step 8 podcast/group JSON schema lives under `public_content.podcasts_page` with page labels, title/description, pagination, search/category toggles, template keys, card visibility toggles, and nested group-page display options.
- Public Front v2 Step 8 canonical public routes are `/podcasts` and `/podcasts/{contentGroupSlug}`. The old public `/groups/{contentGroupSlug}` route is absent; admin `admin/content-groups` routes remain unchanged.
- Post-Step-10 content group type-label defaults in `ContentGroupForm` are translation-backed through `public.labels.podcast`, `public.labels.podcasts`, `public.labels.item`, and `public.labels.items` instead of hard-coded English strings.
- Public Front v2 Step 8 public group query helper:
  - `App\Support\PublicFront\Groups\PublicContentGroupQueries`
- Public Front v2 Step 10 contributor JSON schema lives under `public_content.contributors_page` with contributor labels, directory page-size/sort options, preview grid/search options, top-transcriber selector/preview options, compact-card tokens, and full contributor item-list controls.
- Public Front v2 Step 10 contributor directory/page behavior:
  - `/contributors` uses compact contributor selector cards with URL-backed search/sort/page-size/selected state.
  - `/contributors/{authorSlug}` uses URL-backed related-item search/sort/page-size state.
  - Top-transcriber homepage sections render a horizontal selector and selected preview through `App\Livewire\Public\TopTranscribersSection`.
  - Same-author multiple transcriptions on one item count separately but render one related `ContentItem` card with grouped transcription titles.
- Public Front v2 Step 8 handoff:
  - `docs/phase-02/public-front-v2-step8-podcasts-groups-ux-handoff.md`
- Public Front v2 Step 9 Public Menu/Header and UX Fixes surfaces:
  - `App\Enums\PublicMenuItemType`
  - `App\Livewire\Public\PublicHeader`
  - `App\Support\PublicFront\Menu\PublicMenuConfigReader`
  - `App\Support\PublicFront\Menu\PublicMenuRenderer`
  - `App\Support\PublicFront\Menu\PublicRouteRegistry`
  - `App\Support\PublicFront\Menu\PublicUrlSanitizer`
  - `resources/views/livewire/public/public-header.blade.php`
  - `docs/phase-02/public-front-v2-step9-public-menu-header-ux-fixes-handoff.md`
- Public Front v2 Step 9 public settings admin organization uses tabs for Homepage/Sections, General/Display, Menu/Header, Podcasts, About, Forms, and Advanced/Diagnostics.
- Public Front v2 Step 9 menu/header JSON schema lives under `public_content.menu_config` with `enabled`, `items`, and `theme_selector`. No `PublicMenu` or `PublicMenuItem` model/table exists.
- Post-Step-10 public header state:
  - Desktop header search renders before an independent theme selector when `theme_selector.enabled` is true.
  - Desktop theme selector rendering no longer depends on a `theme_selector` menu item in the desktop menu loop.
  - Header search icon and theme menu positioning use RTL-safe logical inset utilities.
  - Mobile theme selector rendering remains menu-item driven.
  - Public panel SPA mode is not enabled; the temporary `->spa()` addition was removed by `2b1c6b3`.
- Public Front v2 Step 9 About/team card settings live under `public_content.about_page.settings.team_card`; Step 7 About/team JSON remains compatible and no `TeamProfile` model/table exists.
- Public Front v2 Step 9 contributor directory keeps `Author` as the public contributor/transcriber model and changes only the compact-card/preview UX.
- Public Front v2 Step 9 homepage chrome is suppressed for default homepage sections while `/search` keeps the discovery search/filter UI.
- Public Front v2 Step 9 adds a minimal JSON-only `content_block` homepage section source; this is not a CMS/page-management system.
- Public Front v2 Step 1 enums:
  - `App\Enums\PublicFrontConfigBlockType`
  - `App\Enums\PublicFrontLayoutVariant`

## Current Domain Schema

Current tables relevant to Phase 02 content after Prompt 08 and Prompt 09:

- `authors`
- `content_groups`
- `content_items`
- `transcriptions`
- `author_transcription`
- `categories`
- `category_content_group`
- `category_content_item`
- `tags`
- `taggables`
- `settings`
- `homepage_sections`
- `public_form_submissions`

Prompt 07 migration status from `php artisan migrate:status` and Boost database inspection:

- `2026_06_29_134855_create_transcriptions_table`: ran.
- `2026_07_08_000000_create_author_transcription_table`: ran.
- `2026_07_08_000001_drop_author_content_item_table`: ran.
- `2026_06_29_134914_add_featured_transcription_id_to_content_items_table`: ran.
- `2026_06_29_134914_backfill_transcriptions_from_content_items_table`: ran.

Prompt 08 migration status from `php artisan migrate:status` and Boost database inspection:

- `2026_06_30_012920_create_tag_tables`: ran.
- `2026_06_30_012921_create_settings_table`: ran.
- `2026_06_30_012923_create_categories_table`: ran.
- `2026_06_30_012931_create_homepage_sections_table`: ran.
- `2026_06_30_012932_add_prompt08_fields_to_content_items_table`: ran.
- `2026_06_30_012933_add_homepage_order_to_content_groups_table`: ran.
- `2026_06_30_012934_create_public_content_settings`: ran.
- `2026_07_02_000000_add_public_content_card_settings`: added by Prompt 11.

Public Front v2 Step 6 migration status from `php artisan migrate:status` and local migration run:

- `2026_07_05_000000_create_public_form_submissions_table`: ran.
- `2026_07_05_000001_normalize_public_forms_setting`: ran.

Public Front v2 Step 7 settings migration status from local migration run:

- `2026_07_05_000002_normalize_about_page_setting`: ran.

Public Front v2 Step 8 settings migration status from local migration run:

- `2026_07_05_000003_add_public_podcasts_page_setting`: ran.

Public Front v2 Step 9 settings migration status from local migration run:

- `2026_07_06_000000_normalize_public_menu_header_and_about_cards`: ran.
- `2026_07_06_000001_ensure_public_about_team_legacy_settings`: ran.

Local data reset note:

- Previous `migrate:status` output showed all migrations in batch 1, which strongly suggests the local database was rebuilt with `migrate:fresh --seed` or an equivalent reset path.
- The exact manual reset command was not observed.

Current physical schema verified through Boost `database_schema`:

- `transcriptions` table exists.
- `content_items.featured_transcription_id` exists.
- Legacy `content_items.transcript_markdown` still exists as a legacy/backfill source and later cleanup target.
- `tags` and `taggables` exist for Spatie tags.
- `tags` includes Phase 02 editorial metadata columns: `is_enabled`, `enabled_at`, `enabled_by_id`, `created_by_id`, and `moderation_state`.
- `settings` exists for Spatie Settings.
- `homepage_sections` exists with section target fields for category, tag, and content group plus Step 4 JSON config columns: `source_config`, `selection_config`, `display_config`, and `pagination_config`.
- `public_form_submissions` exists with Step 6 submission review fields: `form_key`, `form_name_snapshot`, `payload`, `status`, `submitted_at`, `source_url`, hashed submitter fingerprints, and `metadata`.

## Prompt 07 Implementation Notes

- `ContentItem::transcriptions()`, `ContentItem::featuredTranscription()`, `ContentItem::latestPublishedTranscription()`, and `ContentItem::effectiveTranscription()` exist.
- `Transcription::contentItem()` and `Transcription::author()` exist.
- `Author::transcriptions()` exists.
- `ContentItem::published()` requires a published parent group, a published item, and at least one published child transcription.
- Public item/group pages load and render effective/main transcription content instead of directly rendering legacy item transcript content.
- Featured transcription ownership is validated so a featured transcription must belong to the same `ContentItem`.
- Public effective transcription resolution ignores unpublished featured transcriptions and falls back to the latest published transcription.
- New writes to legacy `content_items.transcript_markdown` are deprecated/blocked in normal application paths.

## Prompt 08 Implementation Notes

- Prompt 08 is implemented and committed.
- Categories are implemented as custom hierarchical records.
- Spatie tags are implemented through the standard `tags` table and `taggables` pivot, scoped to type `content` in admin item forms.
- `App\Models\ContentTag` remains only as the configured Spatie custom tag model for enabled/moderation metadata on the normal Spatie `tags` table.
- Item pinning fields and content group homepage ordering fields exist.
- Prompt 08 media metadata foundation fields exist on `content_items`.
- `App\Settings\PublicContentSettings` works in the admin settings page and persists rows in the `settings` table.
- Public homepage/search pages now consume `PublicContentSettings`.

## Prompt 11 Public Homepage/Search Notes

- Prompt 11 is implemented.
- The public root `/` keeps the existing `BrowseContentGroups` root page class as a compatibility shell but renders `ContentItemSearch`; the homepage result unit is now `ContentItem`/episode cards, not `ContentGroup`/podcast cards.
- New public routes/pages exist for `/search`, `/categories/{categorySlug}`, and `/tags/{tagSlug}`.
- Public item listing visibility requires a published parent group, a published item, and at least one effective/main published transcription.
- Prompt 11R replaced the public Filament Table listing with custom Livewire state, `WithPagination`, URL-backed properties, and Blade-rendered card grids/rows.
- The reusable public item card view is now `resources/views/components/public/content-item-card.blade.php`; `resources/views/filament/tables/columns/public-content-item-card.blade.php` remains only as a compatibility wrapper.
- Public listing output no longer renders `{{ $this->table }}` or public Filament table markup as the primary UI.
- Public group badges are rendered through `resources/views/components/public/content-group-badge.blade.php`, including cover-image and title/initial fallback behavior.
- Card display is controlled by safe semantic Spatie settings, not raw CSS or Tailwind classes from the database.
- Prompt 11 card settings cover image size, density, title size, group badge visibility, authors/categories/tags/date/duration/description visibility, description line count, and cards per page.
- Semantic values are mapped in PHP through `PublicContentCardOptions`; Tailwind source scanning includes that support namespace.
- Public filters include custom Blade search, category with descendant and inherited group matching, enabled content tag, content group, transcriber, provider, effective/original date ranges, duration, and media-presence controls. The transcriber filter keeps the legacy `author` URL query alias for compatibility.
- Sort options include latest/oldest transcription, title A-Z/Z-A, duration shortest/longest, and original newest/oldest.
- Homepage default ordering keeps valid pinned items first unless an explicit sort is selected.
- Visible ordered `HomepageSection` records now render as separate homepage sections for `latest`, `category`, `tag`, and `content_group`, each using `ContentItem` records and the shared card component.
- Prompt 11B adds `top_transcribers` homepage sections, rendered as public `Author` contributor cards ranked by published transcriptions on public content items.
- Curated homepage query sections remain deferred by the blueprint/spec.
- Transcript body search remains deferred and is not part of default live search.
- Prompt 12 later implemented the public item page media/parser overhaul while preserving the Prompt 11R custom Livewire + Blade homepage/search renderer.

## Prompt 11B Public Contributor Discovery Notes

- Prompt 11B is implemented and committed as `8998f7e feat: add public contributor discovery`.
- Contributor/transcriber discovery uses `Author` as the public-safe contributor model. No `User` records are exposed publicly.
- New public routes exist:
  - `/contributors`
  - `/contributors/{authorSlug}`
- `ContributorDirectory` provides URL-backed live search with `#[Url(as: 'q', except: '')]`, paginates contributors, and stores selected preview contributor state in the URL as `contributor`.
- Contributor directory cards show public transcription counts and distinct public content item counts.
- Selecting a contributor card loads a live preview of related public `ContentItem` records through published transcriptions.
- Full contributor pages show the contributor name, safe-rendered public bio Markdown, counts, and paginated public `ContentItem` cards.
- Public contributor visibility/counting requires a published transcription by the author whose content item is public under existing public item rules: published group, published item, and effective/main published transcription.
- Contributor-related content item cards are still `ContentItem` records, never public `Transcription` cards.
- `DemoHebrewContentSeeder` remains idempotent and now creates a visible `top-transcribers` homepage section with stable slug `top-transcribers`.
- Public contributor profile records beyond `Author` remain deferred to a future contributor-profile prompt if needed.

## Prompt 09 and Admin Repair Notes

- Prompt 09 is implemented and committed.
- The post-Prompt-09 admin management UX repair is implemented and committed as `16ab33a`.
- `EditContentItem` uses `getContentTabLabel(): ?string` for the item details tab label.
- `EditContentItem` no longer overrides `getContentTabComponent()` only to change the label, preserving real form fields in the item details tab.
- ContentItem edit renders the item details tab, core item form fields, and the transcriptions tab.
- ContentItem create redirects to the edit page for the created item and notifies admins to add a transcription from the transcriptions tab.
- `ContentItemsTable` has an Add transcription row action.
- Associate-existing transcription was deferred because `Transcription` belongs to one `ContentItem`; associating an existing transcription would move it from another item rather than copy it.
- The first transcription created for an item is automatically set as `featured_transcription_id`.
- The set-featured action is exposed only when the item has more than one transcription.
- Draft transcriptions remain publicly ineffective even if selected as featured.
- `content_items.transcript_markdown` remains out of item forms and relation-manager writes.

## Prompt 11A Admin Relationship UX Notes

- Prompt 11A is implemented and committed locally as `1d81ec0 feat: improve admin relationship management ux`.
- Relationship selector policy:
  - Simple singular selectors get create and edit option modals.
  - Many-to-many selectors get create option modals only because installed Filament 5 does not expose edit-option actions for multiple selects.
  - Complex selectors stay create-disabled and use relation managers or full Resource pages.
- Shared modal schemas live in `App\Filament\Resources\Support\RelationshipOptionForms`.
- Create/edit option modals were added to these singular selectors:
  - `ContentItemForm::content_group_id`
  - `CategoryForm::parent_id`
  - `HomepageSectionForm::category_id`
  - `HomepageSectionForm::content_group_id`
- Create-only option modals were added to these medium/multiple selectors:
  - `TranscriptionForm::transcriber_ids`
  - `TranscriptionsRelationManager::transcriber_ids`
  - `ContentItemForm::categories`
  - `ContentGroupForm::categories`
  - `HomepageSectionForm::tag_id`
- `ContentItemForm::authors` was removed by Step 10R-M2; episode/item transcribers are now managed on transcription records.
- Intentionally unchanged complex selectors:
  - `ContentItemForm::featured_transcription_id`: create/edit transcriptions through the item transcriptions relation manager or full `TranscriptionResource`.
  - `TranscriptionForm::content_item_id`: creating content items inline from a transcript form is too large for a safe selector modal.
  - `SpatieTagsInput::make('tags')`: plugin-managed tag entry remains intact; no custom tag pivot or replacement selector was introduced.
  - Add transcription row action author selector: action data is not a relationship-bound Resource form selector, so it remains options-only while the action itself is reused.
- `ContentGroupResource` now registers `ContentItemsRelationManager` with stable relation key `contentItems`.
- `ContentItemsRelationManager` manages the owner group's `contentItems` relation, lists only current-group items, creates items through the owner context without submitting `content_group_id`, edits items in a modal, exposes delete actions consistently with existing admin conventions, links to the full `ContentItemResource` edit page, and reuses the existing Add transcription action.
- `ContentItemForm::content_group_id` is hidden on `ContentItemsRelationManager`; the owner relationship supplies the group.
- Prompt 11A did not start public contributors/transcribers discovery, public item pages, media embeds, parser work, import/export changes, or permissions work.
- Prompt 11B later implemented public contributors/transcribers discovery while leaving public item pages, media embeds, parser work, import/export changes, and permissions work untouched.

## Prompt 10 Import/Export Notes

- Prompt 10 is implemented.
- Native Filament importers/exporters now include:
  - `App\Filament\Imports\TranscriptionImporter`
  - `App\Filament\Exports\TranscriptionExporter`
  - `App\Filament\Imports\CategoryImporter`
  - `App\Filament\Exports\CategoryExporter`
- Existing importers/exporters were extended:
  - `ContentItemImporter` and `ContentItemExporter`
  - `ContentGroupImporter` and `ContentGroupExporter`
  - existing `AuthorExporter` date output was aligned to day-first date-time formatting.
- Transcription imports create/update `Transcription` child records and never write to legacy `content_items.transcript_markdown`.
- First imported transcription auto-feature behavior remains the existing model behavior and is covered by tests.
- `transcript_file` import support is deferred because the active blueprint/spec does not define an approved import package structure for locating referenced `.md`/`.txt` files. Inline `transcript_markdown` import is supported.
- Category import/export uses portable category paths such as `parent/child` and preserves hierarchy, visibility, sort order, and Markdown description.
- Content item and content group imports attach existing categories by path; missing category paths fail the row.
- Content item imports attach existing enabled Spatie content tags by slug/name using type `content`; missing tags, wrong-type tags, and disabled content tags fail the row.
- Prompt 10 preserves the Spatie tag decision: normal `tags` table, normal `taggables` pivot, `type = content`, and no custom `content_item_tag` pivot.
- Content item import/export now covers pin fields, media metadata fields, category paths, content tag slugs, and `featured_transcription_reference_key`.
- Content group import/export now covers category paths and `homepage_order`.
- Exporters use portable identifiers only: reference keys, category paths, and typed tag slugs. Numeric database IDs are not exported as portable identifiers.
- Exported date-times use `dd/mm/yyyy HH:mm` in `Asia/Jerusalem`; imported day-first date-times are normalized to Laravel storage.
- Exported user/content text is formula-escaped where exporter APIs expose formatting. Failed import rows continue through native Filament failed-row behavior.
- Native `ImportAction`, `ExportAction`, and `ExportBulkAction` are registered for content groups, content items, categories, and transcriptions. Existing author import/export compatibility remains.
- Prompt 10 did not implement public homepage/search, public item page/parser work, dashboard widgets, or studio/sync work.
- Prompt 11 later implemented public homepage/search while preserving Prompt 10 import/export behavior.

## Homepage and Settings Notes

- `HomepageSectionResource` is treated as homepage content configuration: records define which content slices appear on the homepage.
- `HomepageSectionForm` is type-driven:
  - `latest` does not require a category, tag, or content group target.
  - `category` requires `category_id`.
  - `tag` requires `tag_id`.
  - `content_group` requires `content_group_id`.
  - `top_transcribers` does not require a category, tag, or content group target.
  - `curated_query` remains deferred.
- Homepage settings and homepage sections have separate responsibilities:
  - `PublicContentSettings` stores global defaults, limits, and layout choices.
  - `HomepageSection` records configure ordered content slices.
  - Item pinning is separate and affects item ordering where public queries support it.
- Prompt 11 reads `PublicContentSettings` and visible ordered `HomepageSection` records when building the public homepage/search UI.

## Browser Regression Tests

- Pest browser testing is present.
- `tests/Browser/AdminContentItemBrowserTest.php` visits a ContentItem edit page in a real browser.
- The browser test asserts the item details tab label, title field, slug field, content group field, status field, media URL field, and transcriptions tab are visible.
- This test protects the `getContentTabLabel()` repair from regressing into an empty details tab.

## Prompt 12 Public Item Page/Media/Parser Notes

- Prompt 10 is complete.
- Prompt 11 is complete.
- Prompt 11R is complete and committed as `bb4b97c refactor: customize public content item discovery`.
- Prompt 11A is complete and committed as `1d81ec0 feat: improve admin relationship management ux`.
- Prompt 11B is complete and committed as `8998f7e feat: add public contributor discovery`.
- Prompt 12 readiness sync is complete and committed as `23242a1 docs: prepare prompt twelve after public discovery work`.
- Prompt 12 is implemented and committed as `ffba2b3 feat: add public item page media and transcript parser`.
- The public item page resolves only published groups, published items, and items with at least one published effective/main transcription.
- The item page preserves Prompt 11R custom Livewire + Blade homepage/search behavior and Prompt 11B contributor discovery routes, author/contributor links, and `top_transcribers` sections.
- The item page shows day-first dates, duration, categories, enabled content tags, author/contributor links, copy/share actions, safe description Markdown, and the transcript viewer in an RTL public layout.
- `resources/views/components/public/media-embed.blade.php` renders an iframe only for allowlisted HTTPS embed URLs, falls back to a valid HTTPS source link, never renders raw embed HTML, and shows provider/source metadata where available.
- `TranscriptSegmentParser` supports `[HH:MM:SS] Speaker: Transcript text` and `[HH:MM:SS] Speaker:\nTranscript text...`, returning seconds, timestamp, speaker, Markdown, and `t-{seconds}` anchors.
- `ContentItemTranscriptViewer` defaults to the effective transcription, exposes only published transcription tabs/selector choices, keeps selection URL-backed by transcription reference key, and falls back to safe Markdown when parsing finds no segments.
- Viewer controls are local Alpine preferences for show/hide timestamps and speakers; timestamp anchors are direction-safe with `dir="ltr"`.
- Prompt 12 did not add player sync, transcription studio, autosave, dashboard widgets, analytics, metadata extraction automation, import/export behavior changes, admin relationship UX changes, homepage/search rewrites, or contributor discovery changes.
- Prompt 13 dashboard metrics remains not started. Public Front v2 post-Step-10 mini-step sequencing is controlled by `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`; Step 10R-M6, Step 10R-UX1, Step 10R-UX2, and Step 10R-V1a through Step 10R-V1c are complete, Step 10R-C1 is superseded, AX1-AX3 are scheduled by the v4 plan, and the next mini-step is Step 10R-P1.

## Public Front v2 Planning Notes

- Public Front v2 research, blueprint, blueprint-result, and execution-plan docs exist.
- Public Front v2 Step 10 is complete. Prompt 13 remains not started unless the user explicitly chooses dashboard metrics first.
- The execution plan is an implementation guide, not a single prompt. Run one implementation prompt per step.
- Corrected step order:
  - Step 1: JSON Settings Architecture.
  - Step 2 / Reserved: Transcription Publication Policy, deferred unless explicitly promoted as an isolated prompt.
  - Step 3: Card Template Builder.
  - Step 4: Public Display Sections and Loopers.
  - Step 5: Latest and Search UX.
  - Step 6: Public Forms and Submissions.
  - Step 7: About Page Content and Team Builder.
  - Step 8: Podcasts and Groups UX.
  - Step 9: Public Menu and Header.
  - Step 10: Contributors and Top Transcribers UX.
  - Step 11: Seeders, Demo Data, Assets, and Cleanup.
  - Step 12: Prompt 13 Dashboard Metrics readiness / next decision.
- Step 1 created `docs/phase-02/public-front-v2-step1-json-settings-handoff.md` for ChatGPT/Yoni and established the final JSON settings API used by Step 3.
- Step 3 Card Template Builder is committed as `a0146ce`.
- Step 4 Public Display Sections and Loopers is committed as `c0ce7d7`.
- Step 5 Latest and Search UX is committed as `eea9164`.
- Step 6 Public Forms and Submissions is committed as `49f6ab0`.
- Step 7 About Page Content and Team Builder is committed as `b4fe4d5`.
- Step 8 Podcasts and Groups UX is committed as `f3d137e`.
- Step 9 Public Menu/Header and UX Fixes is committed as `5cf3363`.
- Step 9R Menu/Header UX Fixes is committed as `bfcda46`.
- Step 9R Podcast Episode Grid Settings follow-up is implemented and committed as `af23555`.
- Step 10 Contributors and Top Transcribers UX is implemented and committed as `37ce738`.
- Post-Step-10 public label/header polish is committed through `cea4f60`.
- Future Step 9F/10F Footer + Rich Section Builder foundation remains planned after all prior Step 10R work, including P1-P3, AX1-AX3, SL1-SL4, B4, and C2, is complete. The active next mini-step is Step 10R-P1.
- The PodText logo already exists at `public/images/podtext-logo.jpg` and must be preserved by future public-front work.

## Public Front v2 Step 9 Public Menu/Header and UX Fixes Notes

- Step 9 reorganizes the `PublicContentSettings` admin page into major tabs with full-width collapsible sections for homepage/sections, general/display, menu/header, podcasts, about, forms, and advanced/diagnostics.
- Step 9 extends `menu_config` as a JSON-settings-powered public header/menu source and renders it through `App\Livewire\Public\PublicHeader` in the public panel. Default items include Home, Podcasts, About, request-transcription form, volunteer/register-transcriber form, and a theme selector.
- Public form action menu items use Step 6 `PublicFormModal` and the `open-public-form` browser event. Disabled/missing form targets are skipped server-side.
- The header uses the existing `public/images/podtext-logo.jpg` baseline and does not create `PublicMenu`, `PublicMenuItem`, or settings-only menu models.
- About/team profile cards now render uploaded images reliably and support safe semantic settings under `about_page.settings.team_card` for image visibility/size, grid/list layout, density, title/description visibility, and description line clamp.
- About Markdown/RichEditor/content-block output now has explicit H1-H6 public typography classes and keeps the existing safe renderer path.
- Contributor directory compact cards now show only contributor name plus public count badge; they select a Livewire-owned preview row. The preview contains the contributor page link and searchable related public items. Page sizes are 10, 15, and 20, with A-Z/Z-A/count-down/count-up sort toggles.
- Homepage default section mode suppresses top discovery chrome, page intro clutter, and the global search/filter panel while preserving `/search` behavior.
- Latest section headers now place section title, lightweight search, next/previous controls, and show-all action in one responsive header row.
- Step 9 adds a minimal safe `content_block` homepage section source using safe Markdown body, semantic style, and optional route/form action fields. This is not a CMS conversion.
- Step 9 handoff:
  - `docs/phase-02/public-front-v2-step9-public-menu-header-ux-fixes-handoff.md`
- Step 2 transcription policy remains deferred/reserved.
- Prompt 13 has not started.

## Public Front v2 Step 9R Menu/Header UX Fixes Notes

- Step 9R verified Step 8 and Step 9 plans against the current repository and recorded the verification matrix in `docs/phase-02/public-front-v2-step9r-verification-and-fixes-plan.md`.
- Step 9R added durable FilamentExamples MCP research discipline to `AGENTS.md`, `.ai/guidelines/tooling-quality.md`, the agent usage index, tooling quality docs, and AI development lessons. Research was recorded in `docs/research/public-front-v2/13-step9r-menu-header-ux-fixes-mcp-research.md`.
- Homepage root now stays in homepage-section mode even with query parameters such as `/?sort=latest_transcription`; `/search` keeps the full discovery chrome and filters.
- Public page classes with their own public H1s use an empty public page header override to avoid redundant fixed Filament page titles.
- `menu_config` now supports safe logo settings, header global search, desktop item alignment, and separate theme selector display modes while keeping form actions wired to Step 6 `PublicFormModal` and `open-public-form`.
- Public item cards now support safe image fit/radius settings, fall back from item thumbnail to group cover image, and keep group badge/title composition semantic. The JPG logo baseline at `public/images/podtext-logo.jpg` remains the default header fallback.
- About cards and image blocks now support safe image fit/radius markers, and H1-H6 public Markdown heading classes are covered by tests.
- Contributor directory follow-up work remains limited to Step 9 repairs: preview related items render as cards/grid and preview state remains Livewire-owned. Full contributor/top-transcriber redesign remains Step 10.
- Podcast detail pages now have JSON-first episode grid settings under `public_content.podcasts_page.group_page`, including card/list layout, desktop columns, gap, page-size options, search/sort/category/per-page control toggles, allowed/default sorts, and episode card display tokens. `ContentItemBrowser` owns the URL-backed state and public rendering remains custom Livewire + Blade.
- The future footer/rich-section-builder scope split is documented in `docs/phase-02/public-front-v2-step9f-section-footer-builder-plan.md`; no `FooterSection`, `PublicFooter`, CMS page, `Podcast`, or `Episode` model was created.
- Step 9R settings migrations `2026_07_06_000002_add_public_step9r_card_settings`, `2026_07_06_000003_normalize_public_step9r_json_defaults`, and `2026_07_06_000004_ensure_public_step9r_about_team_card_defaults` were run locally.
- Step 9R follow-up settings migration `2026_07_06_000005_add_podcast_episode_grid_settings` was run locally.
- Step 2 transcription policy remains deferred/reserved.
- Prompt 13 has not started.

## Public Front v2 Step 1 JSON Settings Architecture Notes

- Step 1 adds public-front array settings to `App\Settings\PublicContentSettings`:
  - `card_templates`
  - `menu_config`
  - `about_page`
  - `public_forms`
  - `route_labels`
  - `display_defaults`
- Step 1 adds a Spatie settings migration: `database/settings/2026_07_04_000000_add_public_front_json_settings.php`.
- Step 1 intentionally does not add `transcription_policy`; Step 2 remains deferred/reserved.
- Step 1 intentionally did not add `homepage_sections` JSON columns; Step 4 now adds the deferred `source_config`, `selection_config`, `display_config`, and `pagination_config` columns.
- `PublicFrontConfigReader` is the runtime entry point for normalized config. Future public rendering should call `read()`, `all()`, or `group()` rather than reading raw settings arrays.
- `PublicFrontConfigValidator` normalizes arrays, merges defaults, reports invalid config, and rejects unknown keys plus unsafe HTML, iframe/script strings, JavaScript URLs, non-HTTPS external URLs, raw CSS/Tailwind-looking values, SQL-looking values, PHP class names, and Blade path-looking strings.
- `PublicFrontConfigResult` returns normalized config and safe invalid-config report objects.
- The existing `PublicContentSettings` admin page now fills missing public-front defaults and sanitizes public-front arrays before save.
- Existing `PublicContentCardOptions` behavior is unchanged and remains compatible with the older scalar card settings.
- No settings-only models were introduced.
- No Prompt 13 work started.

## Public Front v2 Step 3 Card Template Builder Notes

- Step 3 stores card templates in the existing `public_content.card_templates` array setting as a flat JSON-first list. It does not create `CardTemplate`, `CardTemplatePart`, `CardFamily`, `PublicDisplaySection`, or `PublicLooper` models.
- Card template runtime reads continue through Step 1 APIs: `PublicFrontConfigReader::read()`, `PublicFrontConfigResult::config()`, `PublicFrontConfigResult::group('card_templates')`, and `PublicFrontConfigResult::invalidConfigArray()`.
- `PublicFrontConfigRegistry` now defines safe families, part types, sources, attributes, and default templates for `content_item`, `content_group`, and `contributor`.
- Supported families are `content_item`, `content_group`, and `contributor`; supported layout variants remain semantic (`cards`, `rows`), with semantic density, image size, title size, part layout, icon, URL target, line clamp, and font-size options.
- Supported part types include `image`, `title`, `description`, `metadata_row`, `entity_attribute`, `group_identity`, `transcriber_line`, `date_read_time`, `taxonomy`, `custom_text`, `action_link`, `divider`, and `spacer`.
- The validator accepts normalized JSON and Filament Builder-shaped part payloads, then normalizes parts to plain JSON arrays. Unknown or unsafe families, part types, sources, attributes, icons, layout values, CSS/Tailwind-looking values, Blade/PHP-looking strings, JavaScript URLs, and HTML/script/iframe strings are reported through invalid config and skipped or defaulted safely.
- `App\Filament\Pages\PublicContentSettings` now includes a card template editing section with a Repeater and Builder-backed parts editor. Live side-by-side preview remains deferred to later public UX work.
- Public item, group, and contributor cards preserve existing Blade output and expose compatibility metadata through `data-card-template-*` attributes resolved from the card template support layer.
- Step 3 does not implement display-section/looper queries, latest/search redesign, public forms, about/team builder, podcasts/group UX changes, menu/header management, contributor UX refinements, seeders, dashboard metrics, or the deferred transcription publication policy.
- Step 4 Public Display Sections and Loopers is committed as `c0ce7d7`. Step 5 Latest and Search UX is committed as `eea9164`. Step 6 Public Forms and Submissions is committed as `49f6ab0`. Step 2 transcription policy remains deferred/reserved. Prompt 13 has not started.

## Public Front v2 Step 4 Display Sections and Loopers Notes

- Step 4 extends the existing `HomepageSection` model; it does not create `PublicDisplaySection`, `PublicLooper`, or other settings-only models.
- The new reversible migration `2026_07_04_221810_add_public_front_config_to_homepage_sections_table.php` adds nullable JSON columns to `homepage_sections`: `source_config`, `selection_config`, `display_config`, and `pagination_config`.
- `HomepageSection` now casts the new JSON columns to arrays, mirrors empty-array defaults in model attributes, and exposes safe helper methods: `sourceConfig()`, `selectionConfig()`, `displayConfig()`, and `paginationConfig()`.
- Step 4 support classes under `App\Support\PublicFront\Sections` normalize section JSON config, report invalid config, resolve public-safe source queries, and build view-ready section results.
- Supported source types are `latest_content_items`, `category_content_items`, `tag_content_items`, `content_group_items`, `manual_content_items`, `content_groups`, `categories`, `contributors`, and `top_transcribers`.
- `curated_query` remains deferred and invalid/unknown section source config is reported and skipped safely during public rendering.
- Public homepage rendering now delegates visible ordered homepage sections through the Step 4 resolver while preserving Prompt 11R custom Livewire + Blade output and legacy `data-section-type` markers for existing section types.
- Public content item sources continue to enforce published group, published item, and published effective/main transcription constraints through the shared public query path.
- Tag sources require enabled `content` tags. Category sources can include descendants and inherited group categories. Manual source include/exclude IDs are database IDs and still recheck public visibility before rendering.
- Content group/category/contributor sources render through existing public Blade components or simple safe category cards; top-transcribers counting behavior remains the existing public contributor discovery behavior.
- Display config composes with the Step 3 `PublicFrontCardTemplateResolver` and compatibility renderer attributes. Step 4 resolves templates and safe semantic overrides; Step 5 adds the practical controlled content-item renderer.
- `HomepageSectionForm` keeps legacy type-driven fields and adds semantic fields for source, selection, display/template, and pagination config. It does not expose raw JSON, raw CSS/classes, arbitrary Blade paths, raw SQL, or arbitrary PHP classes.
- Pagination config stores and normalizes `none`, `simple`, `load_more`, and `next_previous`; infinite scroll remains deferred.
- The handoff file for review is `docs/phase-02/public-front-v2-step4-display-sections-loopers-handoff.md`.
- Step 5 Latest and Search UX is committed as `eea9164`. Step 6 Public Forms and Submissions is committed as `49f6ab0`. Step 7 About Page Content and Team Builder is committed as `b4fe4d5`. Step 2 transcription policy remains deferred/reserved. Prompt 13 has not started.

## Public Front v2 Step 5 Latest and Search UX Notes

- Step 5 makes Latest a looper-driven public section using `PublicDisplaySectionResolver` output and normalized `source_config`, `display_config`, and `pagination_config`.
- Latest sections now support lightweight search, top previous/next controls for `simple` and `next_previous`, and bottom load-more for `load_more`.
- Latest page size normalizes to 4 through 25, and Latest total query size normalizes to at least 50.
- The public search page keeps search and sort visible while moving filters into a custom Blade/Alpine drawer controlled by `resources/views/components/public/public-filter-panel.blade.php`.
- Livewire owns search/filter/sort state; Alpine owns only drawer open/close behavior.
- Category and tag filters now support multi-select toggle buttons/chips with URL-backed CSV state. Disabled tags remain hidden publicly.
- Public content item cards now use the practical controlled renderer `PublicFrontCardTemplateRenderer::contentItemPresentation()` for deterministic card classes, safe line clamps, square image handling, large-image stacking, and `min-w-0` text columns.
- Step 5 does not implement full admin card-template live preview. That work remains deferred as Step 5B Card Template Admin Preview UX.
- The Step 5 handoff file for review is `docs/phase-02/public-front-v2-step5-latest-search-ux-handoff.md`.

## Public Front v2 Step 6 Public Forms and Submissions Notes

- Step 6 stores public form definitions in the existing `public_content.public_forms` JSON setting under the canonical `public_forms.definitions` shape.
- The Step 1 reader/validator remains the runtime API. Public code should use `PublicFrontConfigReader::read()->group('public_forms')`, not raw settings arrays.
- `PublicFrontConfigValidator` validates and normalizes form keys, names, headings, descriptions, submit/success labels, display modes, fields, options, validation semantics, and rate-limit settings.
- Supported v1 field types are `text`, `email`, `phone`, `textarea`, `select`, `checkbox`, `toggle`, and `url`.
- The transactional submission table/model/resource is `PublicFormSubmission`; no settings-only `PublicFormDefinition` model/table exists.
- `PublicFormSubmissionStatus` values are `new`, `reviewed`, and `archived`.
- `App\Livewire\Public\PublicFormModal` renders enabled forms and owns form state, validation, honeypot, rate limiting, submission, and success/error messages.
- Alpine owns only modal/slide-over open and close state. Public forms remain separate from Step 5 search/filter drawer state.
- The admin `PublicContentSettings` page now includes a safe JSON-first public form definition builder.
- The admin `PublicFormSubmissionResource` lists submissions, filters by status, searches form key/name snapshot, safely renders payload summaries, and supports mark reviewed/archive/reopen actions.
- V1 public forms include honeypot protection and Laravel-native rate limiting before live enablement.
- Email notifications, file uploads, and CAPTCHA package integration remain deferred. Public menu/header integration is implemented in Step 9.
- Step 7 About Page Content and Team Builder is committed as `b4fe4d5`; Step 8 is committed as `f3d137e`; Step 9 is committed as `5cf3363`; Step 9R is committed as `bfcda46`; the Step 9R Podcast Episode Grid Settings follow-up is committed as `af23555`; Step 10 is committed as `37ce738`; post-Step-10 public label/header polish is committed through `cea4f60`. Step 2 transcription policy remains deferred/reserved. Prompt 13 has not started.
- The Step 6 handoff file for review is `docs/phase-02/public-front-v2-step6-public-forms-submissions-handoff.md`.

## Post-Prompt-10 Guidance Sync Notes

- Active prompt workflow guidance now records the requirement to run preflight, read the blueprint/spec stack, stop on conflicts, and classify blueprint completion in final reports.
- Successful implementation prompts must update relevant active Markdown state files before the final commit, not only code and tests.
- Prompt 11 started from the Prompt 10 import/export baseline and did not modify import/export behavior.
- This guidance sync changed Markdown only and did not start Prompt 11; Prompt 11 was implemented later.

## Baseline Issue To Record

`php artisan model:show App\Models\ContentItem` and `php artisan model:show App\Models\ContentGroup` previously failed with a class redeclare fatal. This documentation sync did not retest or fix that application issue. Future implementation prompts should avoid relying on `model:show` until the cause is investigated.
