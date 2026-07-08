# Codex Prompt — Public Front v2 Step 10R-M4: Public Rendering, Surface Modes, Card Attributes, Livewire/Blade

Work in the current local clone of `studioycm/PodText`.

This prompt runs the next post-M3 mini-step.

Current expected state:
- Step 10R-B3 is complete and committed.
- Step 10R-M1 is complete: `author_transcription` exists and `Transcription::authors()` is the multi-transcriber relationship.
- Step 10R-M2 is complete: old episode/item authors were removed, `author_content_item` was dropped, `ContentItem::authors()` and `Author::contentItems()` are gone, and admin/import/export/public search paths moved to transcription transcribers.
- Step 10R-M3 is complete: `transcription_policy` settings exist, `PublicTranscriptionPolicy`, `PublicTranscriptionSelector`, and `PublicTranscriptionAggregates` exist, public contributor counts are pivot-backed, public transcriber filters are policy-aware, and content item/group aggregate subselects exist.
- Next mini-step is Step 10R-M4.
- Step 10R-B4 remains paused until M4, M5, and M6 are complete.
- Original Step 10R-C1 remains superseded/paused.
- Step 9F, Step 11, and Prompt 13 have not started.

If current state contradicts this, stop and report before implementation.

## Purpose

Implement public rendering and card-template data consumption on top of the M1-M3 domain/policy foundation.

This step must make public pages/cards consistently use transcription-backed transcribers and policy-backed transcription aggregates, without implementing the full card-template row/group/label/icon renderer that belongs to Step 10R-M5.

## Mandatory first action

Read and verify:

- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-step10r-m3-handoff.md`
- `docs/phase-02/public-front-v2-step10r-m3-implementation-plan.md`
- `docs/phase-02/public-front-v2-step10r-b2-handoff.md`
- `docs/phase-02/public-front-v2-step10r-b3-handoff.md`
- `docs/phase-02/public-front-v2-step10-contributors-top-transcribers-ux-handoff.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/phase-02/ai-development-lessons.md`

Also inspect the actual code before planning:

- `app/Models/Author.php`
- `app/Models/ContentGroup.php`
- `app/Models/ContentItem.php`
- `app/Models/Transcription.php`
- `app/Support/PublicContent/PublicTranscriptionPolicy.php`
- `app/Support/PublicContent/PublicTranscriptionSelector.php`
- `app/Support/PublicContent/PublicTranscriptionAggregates.php`
- `app/Support/PublicContent/PublicContentItemQueries.php`
- `app/Support/PublicContent/PublicContributorDiscovery.php`
- `app/Support/PublicFront/Groups/PublicContentGroupQueries.php`
- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContentGroupCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContributorCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRegistry.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRenderer.php`
- `app/Livewire/Public/ContentItemSearch.php`
- `app/Livewire/Public/ContentItemBrowser.php`
- `app/Livewire/Public/ContentGroupBrowser.php`
- `app/Livewire/Public/ContributorDirectory.php`
- `app/Livewire/Public/ContributorContentItems.php`
- `app/Livewire/Public/TopTranscribersSection.php`
- `app/Livewire/Public/ContentItemTranscriptViewer.php`
- `resources/views/components/public/content-item-card.blade.php`
- `resources/views/components/public/content-group-card.blade.php`
- `resources/views/components/public/contributor-card.blade.php`
- `resources/views/components/public/content-item-grid.blade.php`
- `resources/views/components/public/contributor-item-grid.blade.php`
- `resources/views/livewire/public/contributor-content-items.blade.php`
- public item/detail views under `resources/views/filament/public/pages`

## Preflight

Run:

```bash
git status --short --branch
git log --oneline --decorate -40
php artisan migrate:status
php artisan route:list --path=podcasts
php artisan route:list --path=contributors
php artisan route:list --path=search
```

Confirm:
- working tree is clean;
- M1, M2, and M3 commits are present;
- `author_transcription` migration has run;
- `author_content_item` is gone;
- `transcription_policy` settings migration has run;
- Step 9F, Step 11, and Prompt 13 have not started.

If unexpected app-code dirt exists, stop and report.

Do not use `php artisan model:show`; current project state records a class-redeclare issue with that command.

## Required tools

Use Laravel Boost:
- `application_info`
- `database_schema`
- `search_docs`

Use Boost `search_docs` before changing:
- Eloquent relationship loading;
- aggregate subqueries;
- Livewire URL state;
- public page rendering;
- Spatie Settings config;
- card-template registries/presenters/renderers;
- Pest/Livewire tests.

Use FilamentExamples MCP:
- Use `mcp__filament_examples.search_examples`.
- Do not run one broad query.
- Decompose this mini-step into short search terms.
- Use multiple batches and a refined second pass.
- Prefer `limit: 8` to `10` when supported; fall back if rejected.
- Record access level honestly.

Create or update:

```text
docs/research/public-front-v2/17-step10r-m4-public-rendering-card-attributes-mcp-research.md
```

Suggested query batches:

Batch 1 — public Livewire/cards:
```text
Livewire public cards
card presenter data
public card grid
URL state filters
custom page layout
```

Batch 2 — relationship/aggregates:
```text
Eloquent aggregate counts
belongsToMany eager loading
withCount pivot
group aggregate query
filtered relation count
```

Batch 3 — card template parts:
```text
card template renderer
custom card renderer
safe card attributes
ViewColumn card rendering
Builder preview cards
```

Batch 4 — transcript/item pages:
```text
tabs transcript viewer
Livewire tabs URL
public detail page
related records cards
nested metadata cards
```

## Implementation plan first

Before changing app code, create:

```text
docs/phase-02/public-front-v2-step10r-m4-implementation-plan.md
```

The plan must include:

1. M3 API reality: exact methods available on `PublicTranscriptionPolicy`, `PublicTranscriptionSelector`, and `PublicTranscriptionAggregates`.
2. Current card presenter gaps.
3. Current Livewire query/loading gaps.
4. Current item page/transcript viewer gaps.
5. Current card-template registry attribute gaps.
6. Surface-mode decision:
   - whether M4 should extend `transcription_policy` with `surface_modes`;
   - if yes, exact normalized shape and migration/default handling;
   - if no, explain how M5 will own it and how M4 stays coherent.
7. Exact files to change.
8. Tests to add/update.
9. Out-of-scope list.
10. Stop conditions.

Stop before coding if the plan finds that M3 did not actually provide selector/aggregate APIs required by M4.

## Core product rules for M4

### General

- Public transcriber display comes from `Transcription::authors()`.
- Never recreate or use `ContentItem::authors()`.
- Never use `Author::contentItems()`.
- Never recreate `author_content_item`.
- Keep `transcriptions.author_id` only as compatibility/primary storage, not as the public source of truth.
- Keep `featured_only` as default behavior.
- Do not dump all public transcriptions onto default cards just because global mode is `all_published`.

### Card defaults

Default episode/item cards should show:
- effective/main transcription title where template asks for transcription title;
- effective/main transcription transcribers;
- effective/main transcription read time;
- effective/main transcription word count;
- effective/main transcription published date;
- optional public transcription count / distinct transcriber count only when template/surface requests it.

### Contributor contexts

Contributor context means:
- contributor directory preview;
- contributor detail page;
- top-transcriber selected preview.

Rules:
- related item grids still render one `ContentItem` card per item;
- supporting metadata must show only transcriptions involving the selected contributor;
- if selected contributor worked on multiple transcriptions for the same item, show multiple transcription titles/details under one item card;
- unrelated transcription transcribers must not appear as if they are the selected contributor's work.

### Podcast/group contexts

Podcast/group cards and detail stats must be able to show separate aggregates:
- public episode count;
- public transcription count according to policy;
- distinct public transcriber count according to policy;
- total reading time according to policy;
- latest public transcription date according to policy.

## Surface display modes

The product wants explicit surface behavior. M3 currently added the base `transcription_policy`. M4 should extend it only if this can be done safely and tested.

Preferred shape:

```json
{
  "transcription_policy": {
    "public_mode": "featured_only",
    "count_mode": "featured_only",
    "show_multiple_transcriptions_on_item_page": false,
    "surface_modes": {
      "homepage_cards": "effective_only",
      "search_cards": "effective_only",
      "podcast_episode_cards": "effective_only",
      "contributor_preview_cards": "effective_with_counts",
      "item_page_header": "effective_with_counts",
      "item_page_transcript_viewer": "grouped_transcriptions",
      "podcast_cards": "counts_only",
      "top_transcribers_preview": "effective_with_counts"
    }
  }
}
```

Allowed surface modes:

```text
effective_only
effective_with_counts
merged_transcribers
grouped_transcriptions
counts_only
```

M4 must at least prepare and use these modes in PHP presentation data for:
- homepage/search cards;
- podcast episode cards;
- contributor preview/detail item cards;
- item page header;
- transcript viewer;
- podcast/group cards.

Full nested card-template group rendering can remain M5.

## Required card-template source/attribute expansion

Extend registry/validator/presenters so card templates can safely reference these attributes.

Content item:

```text
content_item.transcribers
content_item.transcription_count
content_item.public_transcription_count
content_item.distinct_transcriber_count
content_item.reading_time
content_item.effective_transcription_title
content_item.effective_transcription_date
content_item.transcription_summary
```

Transcription:

```text
transcription.title
transcription.transcribers
transcription.published_at
transcription.read_time
transcription.word_count
```

Transcription group / prepared grouped rows:

```text
transcription_group.title
transcription_group.transcribers
transcription_group.metadata
transcription_group.summary
```

Content group:

```text
content_group.public_episode_count
content_group.public_transcription_count
content_group.total_reading_time
content_group.latest_transcription_date
content_group.transcriber_count
```

Contributor:

```text
contributor.transcription_count
contributor.public_item_count
contributor.related_transcription_titles
```

All values must be computed through presenters/query helpers and finite maps. No raw Blade, raw HTML, raw CSS, or raw classes from JSON.

## Required implementation behavior

### 1. PublicContentItemCardPresenter

Update so:
- `transcriber_line` uses effective/selected transcription transcribers, not item authors.
- `transcription.author_name` becomes a compatibility alias for transcription transcribers, or is replaced by `transcription.transcribers` while keeping existing templates safe.
- data contains:
  - effective transcription metadata;
  - policy-selected transcription count;
  - distinct transcriber count;
  - public transcription summary;
  - contributor-context grouped titles if supplied by query/component.
- no `authors` item-level data is used.

### 2. PublicContentGroupCardPresenter

Update so:
- group card data can render aggregate values added by M3:
  - public episode count;
  - public transcription count;
  - total read time;
  - latest transcription date;
  - distinct transcriber count.
- existing default cards stay visually compatible unless template requests new attributes.

### 3. PublicContributorCardPresenter

Update so:
- contributor count labels remain policy-aware and pivot-backed.
- contributor templates can access related transcription title summary if supplied by the surface.
- no item-author relation assumptions.

### 4. Livewire/public query surfaces

Update only where needed:
- `ContentItemSearch`
- `ContentItemBrowser`
- `ContentGroupBrowser`
- `ContributorDirectory`
- `ContributorContentItems`
- `TopTranscribersSection`
- `ContentItemTranscriptViewer`

Goals:
- eager-load selected/effective transcription authors/transcribers to avoid N+1;
- pass surface context/mode to presenters if needed;
- keep existing URL aliases stable;
- contributor filters use transcription transcribers;
- do not move Livewire state to Alpine.

### 5. Item page / transcript viewer

Update:
- item page header must show transcribers from the effective/main transcription.
- if policy/display mode permits multiple public transcriptions, transcript viewer exposes all public transcriptions.
- each transcription option/tab/list item must show:
  - transcription title;
  - transcribers;
  - published date;
  - read time;
  - word count when available.
- draft/future/unpublished transcriptions remain hidden.

### 6. Blade cleanup

Move logic into presenters/services where feasible.

Blade components should render prepared data:
- no item author fallback;
- no heavy relation traversal;
- no direct settings reads except already accepted compatibility defaults;
- no raw JSON interpretation.

Do not attempt full shared part-group renderer in M4. That is M5.

## Explicit out of scope

Do not implement:
- Step 10R-M5 grouped/nested card part renderer, except data structure preparation.
- label/icon rendering.
- `part_group` / `part_row` nested admin UI.
- Step 10R-B4 card-options convergence.
- Step 10R-C2 layout token unification.
- Step 9F rich sections/footer.
- Step 11 seeders.
- Prompt 13 dashboard.
- full old Step 2 publication workflow.
- removal of `transcriptions.author_id`.
- any settings-only models.

## Tests

Create or update focused tests, likely:

```text
tests/Feature/PublicMultiTranscriptionRenderingTest.php
```

or extend existing public/card tests if more appropriate.

Required coverage:

- item card shows effective transcription transcribers, not old item authors;
- content item with multiple transcribers on effective transcription renders all transcribers;
- `transcription.author_name` compatibility path renders multi-transcriber names or maps to `transcription.transcribers`;
- homepage/latest/search item cards use transcription transcribers;
- podcast detail item cards use transcription transcribers;
- contributor-context item cards do not show unrelated transcription transcribers as the selected contributor's work;
- contributor-context related item list groups selected contributor transcription titles under one item card;
- item page header shows effective transcription transcribers;
- transcript viewer tabs/list show transcriber names per transcription;
- draft/future transcriptions are hidden from public viewer;
- `featured_only` mode does not show all transcriptions on default cards;
- `all_published` mode changes counts/aggregates but does not dump all transcription details onto default cards;
- podcast/group aggregate values render or are exposed safely when template requests them:
  - public episode count;
  - public transcription count;
  - total read time;
  - latest transcription date;
  - distinct transcriber count;
- new card-template attributes are normalized and unsafe values rejected;
- no `ContentItem::authors()` usage remains in public rendering paths;
- no public Filament Tables are introduced.

Also run relevant existing regression tests:
- `PublicTranscriptionPolicyTest`
- `PublicFrontCardTemplateBuilderTest`
- `PublicPodcastsGroupsUxTest`
- `PublicContributorsTopTranscribersUxTest`
- `PublicContributorDiscoveryTest`
- `PublicLatestSearchUxTest`
- `PublicHomepageSearchTest`
- `PublicDisplaySectionsLoopersTest`
- `PublicItemPageMediaParserTest`

Use actual existing class/file names.

## Documentation and handoff

Create:

```text
docs/phase-02/public-front-v2-step10r-m4-handoff.md
```

Required sections:

```md
# Public Front v2 Step 10R-M4 Handoff

## Purpose
## What was implemented
## M3 policy APIs consumed
## Surface-mode behavior
## Public item/card rendering behavior
## Podcast/group aggregate rendering behavior
## Contributor-context rendering behavior
## Item page/transcript viewer behavior
## Card-template attributes added
## Livewire/query/eager-loading changes
## Blade/presenter changes
## Settings/schema changes
## Security/fallback behavior
## Tests and quality gate summary
## Impact on Step 10R-M5
## Impact on Step 10R-B4
## Open issues / follow-up decisions
```

Update:

```text
docs/phase-02/current-project-state.md
docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md
```

Record:
- Step 10R-M4 complete;
- whether `surface_modes` were added in M4;
- next mini-step:
  - Step 10R-M5 if ledger remains current, or
  - a renamed grouped-rendering/card-label step if the plan updates the ledger;
- B4 remains paused until M5/M6 complete;
- Prompt 13 has not started.

Patch other docs only if stable requirements changed.

## Quality gate

Run focused tests first:

```bash
php artisan test tests/Feature/PublicTranscriptionPolicyTest.php
php artisan test --filter=PublicMultiTranscriptionRenderingTest
php artisan test tests/Feature/PublicFrontCardTemplateBuilderTest.php
php artisan test tests/Feature/PublicPodcastsGroupsUxTest.php tests/Feature/PublicContributorsTopTranscribersUxTest.php tests/Feature/PublicItemPageMediaParserTest.php
```

Use actual available test file/class names. If a filter does not exist, record and run the closest focused tests.

Then:

```bash
vendor/bin/pint --dirty --format agent
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
git diff --check
```

Do not run `vendor/bin/filacheck --fix`.

## Commit behavior

If and only if all gates pass, commit with:

```text
feat: render public transcribers and transcription aggregates
```

Do not push unless explicitly asked.

## Final report

Include:
- selected mini-step;
- preflight state;
- Laravel Boost tools used;
- FilamentExamples MCP query batches and access level;
- research note path;
- implementation plan path;
- handoff path;
- files changed;
- M3 services consumed;
- settings/schema changes;
- card-template attributes added;
- Livewire/query/eager-loading changes;
- item page/transcript viewer changes;
- tests/commands run;
- FilaCheck summary;
- current ledger status;
- commit hash if committed;
- current git status;
- next mini-step;
- manual review checklist:
  - homepage/latest/search card transcribers;
  - podcast episode cards;
  - contributor page/preview grouped titles;
  - item page transcript tabs;
  - all_published vs featured_only setting behavior;
  - template attributes for aggregate values;
- confirmation that B4 remains paused until M5/M6;
- confirmation that Prompt 13 has not started.

End with exactly:

```text
Public Front v2 Step 10R-M4 public rendering and aggregate attributes are complete. Waiting for Yoni/ChatGPT review before continuing.
```
