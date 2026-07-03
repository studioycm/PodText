# Prompt 11B: Public Contributors / Top Transcribers Discovery

## Goal

Add public contributor/transcriber discovery before Prompt 12.

Implement:
- `top_transcribers` homepage section type;
- public contributors/transcribers directory;
- live search/filter;
- clicking a contributor card loads a live preview of related content items by transcription;
- full contributor page listing all public related content items as cards;
- demo/test seeders if needed;
- tests.

## Key model decision

Use `Author` as the public-safe contributor/transcriber entity for now because `Transcription` belongs to `Author`.

Do not expose `User` records publicly.

If a future app needs separate public volunteer profiles, defer that to a later contributor-profile prompt.

## Scope

Allowed:
- `HomepageSectionType::TopTranscribers`;
- public pages/routes/components for contributors;
- custom Livewire + Blade public contributor cards;
- homepage renderer extension for top transcribers;
- seeders/factories/demo data for contributor discovery;
- tests and translations;
- current-state update.

Out of scope:
- no Prompt 12 parser/media item page work;
- no dashboard widgets;
- no Shield/roles/permissions install;
- no contributor profile table unless truly required and approved by current code constraints;
- no exposing admin `User` publicly;
- no broad rewrite of author model beyond what is necessary for public slugs/profile display if missing.

## Read first

- `AGENTS.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/ai-development-lessons.md`
- `docs/phase-02/public-panel-ux-spec.md`
- `docs/phase-02/homepage-settings-spec.md`
- `docs/phase-02/search-and-filters-spec.md`
- `docs/phase-02/dashboard-metrics-spec.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/phase-02/blueprints/11-public-homepage-search-blueprint.md`
- `.ai/guidelines/public-panel.md`
- `.ai/guidelines/search-filters.md`
- `.ai/guidelines/settings-dashboard.md`
- `.ai/guidelines/tooling-quality.md`

Inspect:
- `App\Models\Author`
- `App\Models\Transcription`
- `App\Models\ContentItem`
- `App\Models\HomepageSection`
- `App\Enums\HomepageSectionType`
- public panel provider/routes/pages
- current public content-item card components from 11R
- current demo seeder, especially `DemoHebrewContentSeeder`

## Research / docs verification

Use Laravel Boost `search_docs` or installed source for:
- Livewire `wire:model.live`;
- Livewire `#[Url]`;
- Livewire pagination;
- Filament custom public pages if needed;
- Laravel seeding/factories and `WithoutModelEvents`.

Use FilamentExamples MCP for:
- dynamic homepage sections;
- custom public pages;
- Livewire card/grid public pages;
- public table/card examples as design reference only.

## Preflight

Run:
- `git status --short --branch`
- `git log --oneline --decorate -12`

Confirm:
- Prompt 11R is complete.
- Prompt 11A is complete if it was run.
- Prompt 12 has not started.
- Working tree is clean.

Run baseline:
- `php artisan test`
- `vendor/bin/pint --test`
- `vendor/bin/filacheck`
- `npm run build`

Stop if baseline fails outside this prompt scope.

## Implementation requirements

### 1. Top transcribers section type

Add `top_transcribers` to `HomepageSectionType`.

Update:
- enum labels;
- HomepageSection admin form type options;
- homepage rendering/resolver to support contributor cards for this section type;
- translations;
- tests.

Rules:
- section stores semantic settings only, not raw queries;
- use existing `limit`;
- optional future period/sort settings can be deferred unless trivial and tested;
- count only public transcriptions/items/groups.

### 2. Public contributors directory

Add a public route/page, preferably:

`/contributors`

Use a custom public Filament Page shell and a Livewire component.

Suggested component:

`App\Livewire\Public\ContributorDirectory`

State:
- `search`;
- optional role/type filter if existing model supports it;
- `selectedContributorId`;
- pagination.

Use:
- `wire:model.live.debounce.300ms`;
- `#[Url(as: 'q', except: '')]`;
- `WithPagination`.

UI:
- contributor cards grid;
- public counts:
  - public transcriptions count;
  - public content items count;
- click card to select contributor and show preview panel;
- Alpine only for local expand/collapse/transition if needed.

Do not use Filament Table as the main contributor UI.

### 3. Contributor full page

Add route/page, preferably:

`/contributors/{authorSlug}`

If route naming should be “transcribers” instead, choose one and document. Default is `contributors`.

Full page shows:
- contributor name;
- bio if public-safe;
- counts;
- all public related `ContentItem` cards through published transcriptions;
- pagination;
- empty state.

Use the same public content-item card component from 11R.

### 4. Visibility and counting rules

A contributor/transcriber appears publicly only if they have at least one public transcription relationship where:
- transcription is published;
- content item is published;
- content group is published;
- content item has effective/main published transcription.

Do not leak draft content metadata through counts or previews.

### 5. Seeders

The user added a temporary demo seeder. Keep it if useful, but create structured, idempotent demo data for contributors if needed.

Options:
- extend `DemoHebrewContentSeeder` if that is the project convention;
- or create `DemoContributorDiscoverySeeder`;
- or split into:
  - `DemoContributorSeeder`;
  - `DemoContentCatalogSeeder`;
  - `DemoTranscriptionSeeder`;
  - `DemoHomepageSectionSeeder`.

Use `updateOrCreate` or stable reference keys for idempotence.
Use factories where appropriate.
Consider `WithoutModelEvents` only if model events create unwanted duplicate/side effects. Do not silence events by default if auto-feature behavior should be tested.

Do not make demo data destructive.
Do not seed in production-specific flows unless current project conventions already do so.

### 6. Tests

Add tests for:
- `top_transcribers` section type renders contributor cards;
- hidden/draft-only contributors do not appear;
- counts are based only on public transcriptions/items/groups;
- `/contributors` guest access;
- live search filters contributors;
- URL search state works;
- selecting/clicking a contributor card loads a related public content-item preview;
- full contributor page loads;
- full contributor page lists public content item cards;
- full contributor page excludes draft/no-effective-transcript items;
- contributor card links are correct;
- homepage top transcribers section respects section limit;
- demo seeder is idempotent if touched.

Browser tests:
- optional small test for clicking a contributor card and seeing preview if Pest browser is already stable.

## Documentation update

If successful, update `docs/phase-02/current-project-state.md` before commit:
- mark Prompt 11B complete;
- keep Prompt 12 next/not started;
- record top_transcribers section;
- record contributor pages/routes/components;
- record seeder changes;
- record deferred contributor-profile model decision if applicable.

Patch other docs only if stable requirements changed:
- public-panel spec;
- homepage-settings spec;
- answers matrix;
- prompt 12 readiness if needed.

## Quality gate

Focused:
- `php artisan test --filter=Contributor`
- `php artisan test --filter=PublicHomepageSearchTest`
- or the exact focused tests added.

If PHP changed:
- `vendor/bin/pint --dirty --format agent`

Final:
- `php artisan test`
- `vendor/bin/pint --test`
- `vendor/bin/filacheck`
- `npm run build`

Do not run `vendor/bin/filacheck --fix`.

## Commit

Commit only after full gate passes:

`feat: add public contributor discovery`

## Final report

Include:
- model decision for contributors/transcribers;
- routes/pages/components added;
- top transcribers section behavior;
- public visibility/counting rules;
- seeders added/updated;
- tests added/updated;
- commands/results;
- FilaCheck summary;
- commit hash if committed;
- current git status;
- confirm Prompt 12 was not started.

End with exactly:

“Prompt 11B public contributor discovery is complete. Prompt 12 has not been started.”
