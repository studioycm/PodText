# Public Front v2 Step 10R-M4 Implementation Plan

## 1. Selected Mini-Step And Dependencies

Selected mini-step: Step 10R-M4 - Public rendering, card templates, Livewire, Blade, and aggregate attributes.

Dependencies verified:

- Step 10R-M1 complete: multi-transcriber relationship foundation.
- Step 10R-M2 complete: episode authors removed in favor of transcription transcribers.
- Step 10R-M3 complete: public transcription policy, selector, aggregate services, and pivot-backed counts.

B4 remains paused until M1-M6 are complete. Step 9F/10F, Step 11, and Prompt 13 have not started.

## 2. Current Local Repo Evidence

Preflight confirmed HEAD at `825004c feat: add public transcription policy and aggregates` after `e813513` and `800218a`.

Routes for podcasts, contributors, and search are registered. Migrations for `author_transcription`, dropping `author_content_item`, and public transcription policy settings are applied locally.

Local-only evaluation data contains multiple transcriptions and transcribers, but implementation tests will create their own fixtures.

## 3. Files Inspected

- `app/Livewire/Public/ContentItemSearch.php`
- `app/Livewire/Public/ContentItemBrowser.php`
- `app/Livewire/Public/ContributorContentItems.php`
- `app/Livewire/Public/ContributorDirectory.php`
- `app/Livewire/Public/TopTranscribersSection.php`
- `app/Livewire/Public/ContentItemTranscriptViewer.php`
- `app/Filament/Public/Pages/ShowContentItem.php`
- `app/Filament/Public/Pages/ShowContentGroup.php`
- `app/Filament/Public/Pages/ShowContributor.php`
- `resources/views/components/public/content-item-card.blade.php`
- `resources/views/components/public/content-item-grid.blade.php`
- `resources/views/components/public/contributor-item-grid.blade.php`
- `resources/views/components/public/content-group-card.blade.php`
- `resources/views/components/public/contributor-card.blade.php`
- `resources/views/filament/public/pages/show-content-item.blade.php`
- `resources/views/filament/public/pages/show-content-group.blade.php`
- `resources/views/livewire/public/content-item-transcript-viewer.blade.php`
- `app/Support/PublicFront/Cards/*Presenter.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicContent/PublicContentCardOptions.php`
- `app/Support/PublicContent/PublicTranscriptionPolicy.php`
- `app/Support/PublicContent/PublicTranscriptionSelector.php`
- `app/Support/PublicContent/PublicTranscriptionAggregates.php`
- `app/Support/PublicFront/Sections/PublicDisplaySectionResolver.php`
- `app/Models/Transcription.php`
- `app/Providers/AppServiceProvider.php`

## 4. Laravel Boost Findings

Boost was available and returned installed-version guidance.

Relevant findings:

- Use `#[Computed]` for request-local Livewire memoization.
- Use `Model::preventLazyLoading(! $this->app->isProduction())` in app boot code.
- Use Builder-composed eager loading and aggregate subqueries for SQLite/MySQL compatibility.
- A query-count harness can use Laravel/Pest database query counting; this plan will use a `DB::listen` bounded helper to avoid brittle exact counts.

## 5. FilamentExamples MCP Findings

FilamentExamples access level: search/snippet only.

Query batches used:

- `Livewire public cards`, `URL state filters`, `public page grid`, `custom page layout`
- `settings page nested arrays`, `builder repeater settings`, `custom page view data`, `livewire computed page`, `card grid presenter`
- `getViewData aggregate stats`, `relationship eager load view page`, `modifyQueryUsing eager load`, `select multiple relationship form`, `repeater json settings`
- `public page custom blade`, `Livewire custom Filament page`, `URL query state page`, `getViewData public page`

Adapted patterns:

- Prepare derived view data outside Blade where practical.
- Load relationships in page/component setup before custom Blade rendering.
- Keep nested settings fields under explicit state paths and normalize before render use.

## 6. Old Front-Card Leftovers Found

- Content-item presenter still uses an internal `authors` key for transcription-backed transcribers.
- Content-item cards still use `data-test="item-author"`.
- Per-card Blade presenter resolution remains in item, group, and contributor cards.
- `PublicContentCardOptions` still has `showAuthors`; M4 treats it as a compatibility name for transcriber display. Full options convergence remains B4.

## 7. Current Model/Relationship Reality

- `ContentItem::authors()` and `Author::contentItems()` are gone.
- `author_content_item` is dropped.
- `Transcription::authors()` is the public transcriber source.
- `Transcription::author()` / `author_id` remain compatibility storage.
- `ContentItem::effectiveTranscription()` still selects featured/latest published transcription.

## 8. Settings/Render-Context Impact

M4 adds one finite `transcription_display` token to card-rendering surfaces:

- `display_defaults.transcription_display`
- `podcasts_page.group_page.transcription_display`
- `contributors_page.directory.transcription_display`
- `contributors_page.top_transcribers.transcription_display`
- `contributors_page.page.transcription_display`

Allowed values:

- `effective_only`
- `effective_plus_count`

Default: `effective_plus_count`.

## 9. Card-Template/Rendering Impact

M4 expands finite card-template attributes for content items, transcriptions, content groups, and contributors.

M4 does not implement label/icon/group-row rendering; D8 keeps that for M5.

## 10. Livewire/Blade Impact

- Item grids prepare card view-models once per grid and pass arrays into cards.
- Contributor-context grids pass the selected contributor to the item-card presenter.
- Transcript viewer memoizes the public transcription list and respects `show_multiple_transcriptions_on_item_page`.
- Top transcribers memoizes the ranked contributor list for each request.
- Item page header renders effective transcribers and transcription-count metadata.
- Podcast detail header renders aggregate stats from M3 subselects.

## 11. Admin/Import/Export Impact

Admin impact is limited to public settings fields and translations for `transcription_display`.

No import/export behavior changes in M4.

## 12. Query/Scopes/Aggregation Impact

- `ShowContentItem` uses `PublicContentItemQueries::base()` so item pages have the same effective transcription relations and aggregate selects as listing surfaces.
- Group aggregate values added by M3 are consumed by group presenters and podcast detail headers.
- Any aggregate subselects that remain always-on but unconsumed are documented for P2 opt-in work.

## 13. Episode-Author Removal Impact

M4 does not remove schema or relationships; M2 already did.

M4 removes remaining public wording/data paths that imply item authors where those values are actually transcription transcribers.

## 14. Decisions Applied D1-D8

M4 applies D1-D8 exactly:

- Cards show effective transcription data, not full multi-transcription lists.
- Item page groups by transcription only through viewer tabs when enabled.
- Contributor-context cards use contributor-specific transcriptions.
- Podcast/group aggregate stats are rendered and template-addressable.
- `all_published` affects counts, item-page tabs, and optional count badges.
- Per-surface settings remain a single finite `transcription_display` token.
- Template data remains finite registered attributes.
- Labels/icons/group rows are deferred to M5.

## 15. Backlog Items Owned This Run

- F3: memoize transcript viewer transcription list.
- F4: memoize top transcribers ranked list.
- F5: guard no-op compatibility pivot sync.
- F6: avoid repeated section target finds.
- F8: enable lazy-loading prevention outside production and fix exposed eager-load gaps.
- F10: render group aggregate attributes.
- F11: hoist item-card presentation per grid and rename internal transcriber key.
- F14: fix ledger 9F-A note.
- F15: consume aggregate values where M4 surfaces need them and document P2 opt-in remainder.

## 16. Tests To Add/Update

- Multi-transcriber public rendering tests under `featured_only` and `all_published`.
- Contributor-context card test where contributor transcription differs from effective transcription.
- Item page header/viewer tab tests for counts and per-transcription transcriber names.
- Group aggregate card/header tests.
- Card-template attribute expansion tests.
- No-op transcription save test proving no pivot sync query.
- Public rendering query-count harness for homepage, search, podcast detail, contributor directory, and item page.
- Existing tests updated only where old item-author wording or all-tabs-default behavior conflicts with M4 decisions.

## 17. Exact Files To Change

Planned app files:

- `app/Providers/AppServiceProvider.php`
- `app/Models/Transcription.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicContent/PublicContentCardOptions.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `app/Filament/Public/Pages/ShowContentItem.php`
- `app/Livewire/Public/ContentItemTranscriptViewer.php`
- `app/Livewire/Public/TopTranscribersSection.php`
- `app/Livewire/Public/ContentItemBrowser.php`
- `app/Livewire/Public/ContributorContentItems.php`
- `app/Livewire/Public/ContributorDirectory.php`
- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContentGroupCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContributorCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRegistry.php`
- `app/Support/PublicFront/Sections/PublicDisplaySectionResolver.php`
- public Blade card/grid/page/viewer files
- `lang/en/admin.php`, `lang/he/admin.php`, and `lang/*/public.php` if labels are missing
- focused Pest tests

Planned docs:

- current state
- ledger
- M4 handoff
- display decisions
- performance audit
- research note

## 18. Risks, Conflicts, And Stop Conditions

Risks:

- Enabling lazy-loading prevention can surface unrelated test gaps. Fix only public-front eager-load issues needed for M4.
- Query-count tests can become brittle. Use upper bounds with fixture-owned data.
- Card-template compatibility must keep legacy `transcription.author_name` working while internal naming changes.
- `all_published` local settings must not leak into defaults or tests.

Out of scope:

- labels/icons/group rows (M5);
- validated config caching (P1);
- fetch-window/filter-option performance (P2);
- derived transcript segments (P3);
- card-options convergence (B4);
- layout token normalization (C2);
- Step 11 seeding;
- Prompt 13.

Stop conditions:

- ledger/current state contradict M4 selection;
- unexpected app-code dirt appears outside M4 scope;
- lazy-loading prevention exposes a broad non-M4 app issue that cannot be fixed narrowly;
- SQL needed for M4 cannot be made SQLite/MySQL compatible.
