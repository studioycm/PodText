# Codex Prompt — Public Front v2 Step 10: Contributors and Top Transcribers UX + Step 9R/9F State Review

Work in the current PhpStorm / Codex App project repository only.

This prompt has three responsibilities:

1. Verify and stabilize the current Step 9R / podcast episode grid settings state.
2. Plan and implement Public Front v2 Step 10: Contributors and Top Transcribers UX.
3. Update the future Step 9F / 10F Section and Footer Builder plan based on what Step 10 actually needs, without implementing Step 9F.

Do not run Step 11 Seeders/Demo Data/Cleanup.
Do not implement Step 9F / 10F Footer + Rich Section Builder in this prompt.
Do not run Prompt 13, Prompt 14, or Prompt 15.
Do not implement Step 2 transcription publication policy; it remains deferred/reserved.
Do not convert the About page into a generic CMS.
Do not create `Podcast` or `Episode` models.
Do not create `ContributorProfile`, `VolunteerProfile`, `PublicFooter`, `FooterSection`, `PublicMenu`, `PublicMenuItem`, or other settings-only models.
Do not expose `User` records publicly.
Do not reintroduce public Filament Tables.
Do not push to GitHub unless explicitly asked.
Do not use worktrees.
Do not launch parallel agents.
Do not run `vendor/bin/filacheck --fix` unless explicitly approved.

## Goal

Implement the full Step 10 contributor/transcriber and top-transcribers public UX that was intentionally deferred from Step 9R.

Step 9R only repaired contributor-directory behavior already touched by Step 9. This prompt owns the fuller contributor/top-transcriber work:

- horizontal top-transcribers homepage section selector;
- preview panel under the selector;
- top-transcriber preview item pagination with 5 / 10 / 15 options;
- contributor page UX refinements;
- contributor section/page settings;
- contributor count and item grouping rules;
- contributor public item grids using existing card/grid settings patterns;
- updated tests and handoff.

## Current state assumptions

Verify from `docs/phase-02/current-project-state.md` and git history:

- Public Front v2 Step 9R Menu/Header UX Fixes is complete.
- Public Front v2 Step 9R Podcast Episode Grid Settings follow-up is either already committed or present as an intended local follow-up.
- Public Front v2 Step 10 Contributors and Top Transcribers UX is the next major implementation step.
- Future Step 9F / 10F Footer + Rich Section Builder foundation is planned after Step 10 and before Step 11 if approved.
- Prompt 13 has not started.

If current state contradicts this, stop and report before implementation.

## Phase 0 — State audit and stabilization

Before Step 10 implementation, audit the local state.

Run:

```bash
git status --short --branch
git log --oneline --decorate -20
git diff --stat
git diff --name-only
git diff --check
php artisan migrate:status
```

Classify the state:

### Case A — Clean working tree

Proceed to Step 10 preflight.

### Case B — Dirty working tree only contains the intended podcast episode grid settings follow-up

If the dirty files are exactly the intended Step 9R podcast episode grid settings follow-up, then:

1. Read:
   - `docs/phase-02/public-front-v2-step9r-podcast-episode-grid-settings-plan.md`
   - `docs/phase-02/public-front-v2-step9r-menu-header-ux-fixes-handoff.md`
   - `docs/phase-02/current-project-state.md`

2. Run the relevant verification:

```bash
php artisan test --filter=PublicPodcastsGroupsUxTest
php artisan test --filter=PublicStep9RMenuHeaderUxFixesTest
php artisan test --filter=PublicMenuHeaderUxFixesTest
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
git diff --check
```

3. If all gates pass, commit only the intended follow-up with:

```text
feat: add podcast episode grid settings
```

4. Update `docs/phase-02/current-project-state.md` if it still says the follow-up is uncommitted.

Then continue to Step 10.

### Case C — Dirty working tree contains unexpected changes

Stop and report. Do not start Step 10.

## Required research behavior

Use Laravel Boost and FilamentExamples MCP deeply and repeatedly.

### Laravel Boost

Use:

- `application_info`
- `database_schema`
- `search_docs`

Use Boost `search_docs` before changing:

- Livewire URL state;
- pagination;
- public pages;
- Filament settings forms;
- Filament Builder/Repeater fields;
- Eloquent query scopes;
- card rendering;
- test APIs.

### FilamentExamples MCP

Use the `filament-examples` MCP server before implementation.

Do not run one broad query only.

Follow the project MCP research protocol:

- Decompose the Step 10 work into short topic phrases.
- Use multiple batches of short queries.
- Use `limit: 8` to `10` when supported.
- If the MCP rejects that limit, retry with the maximum accepted limit or `limit: 3`.
- Inspect result names/snippets/source paths/classes.
- Run a second refined pass based on returned terms.
- Search surrounding implementation patterns, not only the direct feature.

Create this research file before code changes:

```text
docs/research/public-front-v2/14-step10-contributors-top-transcribers-mcp-research.md
```

Suggested MCP query batches:

Batch 1 — contributor/directory UX:

```text
Livewire directory cards
card preview list
selected preview state
search inside preview
public profile cards
```

Batch 2 — top/ranked sections:

```text
top users section
ranking cards
homepage dynamic sections
horizontal cards selector
section preview cards
```

Batch 3 — pagination/grid patterns:

```text
Livewire pagination cards
page size selector
responsive card grid
grid controls
public card layout
```

Batch 4 — settings/admin controls:

```text
settings page tabs
repeater settings cards
ToggleButtons settings
section form schema
Builder settings preview
```

Batch 5 — surrounding best practice:

```text
public Livewire page
custom page layout
card grid contentGrid
recordUrl false
Filament public page
```

For each useful example, record:

- example name;
- file/class/snippet found;
- pattern to copy;
- pattern to avoid;
- PodText adaptation notes;
- whether access was snippet-only or source/detail.

Never write MCP token/header values to tracked docs.

## Read first

- `AGENTS.md`
- `.ai/guidelines/tooling-quality.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/ai-development-lessons.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/phase-02/public-front-v2-agent-usage-index.md`
- `docs/phase-02/public-front-v2-execution-plan.md`
- `docs/phase-02/public-front-v2-step9r-menu-header-ux-fixes-handoff.md`
- `docs/phase-02/public-front-v2-step9r-verification-and-fixes-plan.md`
- `docs/phase-02/public-front-v2-step9r-podcast-episode-grid-settings-plan.md`
- `docs/phase-02/public-front-v2-step9f-section-footer-builder-plan.md`
- `docs/research/public-front-v2/08-contributors-transcribers-ux.md`
- `docs/phase-02/blueprints/public-front-v2/08-contributors-transcribers-ux-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/blueprint-results/08-contributors-transcribers-ux-plan.md`
- current `App\Livewire\Public\ContributorDirectory`
- current `App\Livewire\Public\ContributorContentItems`
- current contributor public pages under `App\Filament\Public\Pages`
- current contributor Blade views under `resources/views/livewire/public` and `resources/views/components/public`
- current `App\Support\PublicContent\PublicContributorDiscovery`
- current `App\Support\PublicContent\PublicContentItemQueries`
- current `App\Support\PublicFront\Sections`
- current `App\Support\PublicFront\Cards`
- current `App\Support\PublicFront\PublicFrontConfigRegistry`
- current `App\Support\PublicFront\PublicFrontConfigValidator`
- current `App\Filament\Pages\PublicContentSettings`
- current public tests for contributor discovery, Step 9R, podcasts, latest/search, homepage/search, display sections, card templates, public forms, About, and item page.

## Step 10 implementation plan

Before app-code changes, create:

```text
docs/phase-02/public-front-v2-step10-contributors-top-transcribers-ux-implementation-plan.md
```

The plan must include:

1. Current contributor route/component inventory.
2. Current homepage `top_transcribers` behavior.
3. Current contributor directory behavior after Step 9R.
4. Current full contributor page behavior.
5. Query/counting rules.
6. Settings/config keys to add or extend.
7. Top-transcriber section design.
8. Contributor directory/page design.
9. Card/template/grid strategy.
10. Integration with Step 9F future section/footer plan.
11. Exact files to change.
12. Tests to add/update.
13. Out-of-scope list.

After writing the plan, continue implementation only if no conflict requires a human decision.

If a major query/counting/route/settings conflict is discovered, stop before implementation and report.

## Blueprint contract

Treat this file as the detailed implementation contract:

```text
docs/phase-02/blueprints/public-front-v2/08-contributors-transcribers-ux-blueprint.md
```

The prompt defines scope and boundaries.

The blueprint defines implementation details, tests, security rules, and final checklist.

If the prompt, blueprint, Step 9R handoff, Step 9F plan, execution plan, current state, installed docs, or current code conflict, stop and report before changing code.

## Domain model and public-counting rules

Use `Author` as the public contributor/transcriber model.

Do not expose `User` records publicly.

Do not create a new contributor/profile model.

Counting rule:

- Count published transcriptions by the author.
- Only count a transcription if its parent content item is public:
  - published parent `ContentGroup`;
  - published `ContentItem`;
  - effective/main published transcription availability under current public visibility rules.
- If the same author has two published transcriptions for the same item:
  - count two transcriptions;
  - in item previews, show one item card and list/indicate the relevant transcription names without duplicating the item card unnecessarily.

Contributor-related public item cards remain `ContentItem` cards, never public `Transcription` cards.

## Settings/config requirements

Use JSON-first settings/configuration.

Do not create settings-only models.

Add or extend a contributor config group, for example:

```text
contributors_page
```

or use an existing equivalent if already present.

Potential safe config shape:

```json
{
  "contributors_page": {
    "enabled": true,
    "title": "Contributors",
    "description": "Browse public transcribers",
    "label_singular": "Contributor",
    "label_plural": "Contributors",
    "item_label_singular": "item",
    "item_label_plural": "items",
    "directory": {
      "per_page_options": [10, 15, 20],
      "default_per_page": 10,
      "default_sort": "count_desc",
      "sort_options": ["name_asc", "name_desc", "count_desc", "count_asc"],
      "preview_items_per_page": 6,
      "preview_grid_columns": 3,
      "preview_search_enabled": true
    },
    "top_transcribers": {
      "enabled": true,
      "limit": 8,
      "layout": "horizontal",
      "preview_default_page_size": 5,
      "preview_page_size_options": [5, 10, 15],
      "preview_grid_columns": 3,
      "show_full_page_link": true,
      "show_count_badge": true
    },
    "cards": {
      "compact_show_count": true,
      "compact_count_icon": "document-text",
      "preview_show_bio": true,
      "preview_show_counts": true
    }
  }
}
```

If adding this config:

- extend `PublicContentSettings`;
- add a Spatie settings migration/defaults;
- extend `PublicFrontConfigRegistry`;
- extend `PublicFrontConfigValidator`;
- add admin settings UI in the existing public settings page, preferably in a Contributors tab or a suitable existing tab;
- add English/Hebrew translations;
- add tests.

All settings must use semantic values only. No raw Tailwind classes, raw CSS, raw SQL, PHP classes, Blade paths, unsafe HTML, or JavaScript URLs in JSON.

## Required Step 10 behavior

### 1. Contributor directory finalization

Build on Step 9R, not around it.

Requirements:

- compact contributor cards remain name + count badge only;
- card click selects contributor preview;
- no direct action links on compact cards;
- preview section is a separate row under the contributor list;
- preview card/details contains the link to the contributor page;
- preview related item search remains Livewire-owned;
- page sizes: 10, 15, 20;
- sort toggles:
  - A-Z;
  - Z-A;
  - count down;
  - count up;
- preview item grid defaults to multiple columns;
- URL-backed state where practical;
- no Alpine ownership of contributor selection/query state.

### 2. Top transcribers homepage section redesign

Implement the full top-transcribers section UX that Step 9R intentionally deferred.

Requirements:

- horizontal compact selector/list of top transcribers;
- click one contributor to open/select preview underneath;
- preview shows:
  - contributor name;
  - public transcription count;
  - link to full contributor page;
  - latest public items/transcriptions related to that contributor;
  - preview page-size options 5 / 10 / 15;
  - link to all contributor transcriptions/items on the contributor page;
- top-transcribers section settings should control:
  - limit;
  - layout;
  - preview default page size;
  - preview page-size options;
  - preview grid columns;
  - show/hide count badge;
  - show/hide full-page link;
- maintain public visibility constraints.
- Use `HomepageSection` / Step 4 section/looper infrastructure where practical.
- Do not build a separate homepage mini-framework.

### 3. Full contributor page refinements

Improve the contributor detail page only within contributor/top-transcriber scope.

Requirements:

- page uses labels from contributor settings where practical;
- show contributor name and public counts;
- show public item cards for items/transcriptions related to the contributor;
- include search/filter/sort controls if the blueprint/plan supports them and they fit current architecture;
- preserve safe bio/Markdown rendering;
- keep item cards as `ContentItem` cards;
- group multiple transcriptions by same contributor/item when needed and show transcription names in a safe compact way.

### 4. Top-transcriber query support

Ensure top-transcriber queries:

- count only public content/transcriptions;
- support sorting by count descending;
- exclude authors with zero public contributions;
- avoid N+1 queries;
- keep counting logic centralized.

Use/extend:

```text
App\Support\PublicContent\PublicContributorDiscovery
```

or create a focused support class if the blueprint requires it.

### 5. Card/template/grid strategy

Use existing public card infrastructure:

- contributor family templates from Step 3 if useful;
- content-item cards from Step 5 renderer;
- section resolver from Step 4 where useful;
- `ContentItemBrowser` grid/control patterns from podcast episode follow-up if contributor item grids need similar controls.

Do not render raw classes from JSON.

### 6. Admin settings UI

Extend existing `PublicContentSettings` page.

Requirements:

- add contributor/top-transcriber settings in a tabbed, full-width, collapsible section layout;
- preserve existing settings;
- no raw JSON text area unless explicitly an advanced fallback;
- helper text in English/Hebrew translation files.

### 7. Step 9F plan update

After implementing Step 10, update:

```text
docs/phase-02/public-front-v2-step9f-section-footer-builder-plan.md
```

Update it based on actual Step 10 needs:

- whether top-transcriber sections need reusable rich-section/footer builder support;
- whether contributor preview grids suggest shared section-builder grid controls;
- whether Step 9F should still run before Step 11;
- any schema adjustments needed for future rich columns/footer.

Do not implement Step 9F.

## Out of scope

- Step 9F / 10F Footer + Rich Section Builder implementation.
- Step 11 Seeders/Demo Data/Assets/Cleanup.
- Prompt 13 dashboard metrics.
- Prompt 14/15.
- Step 2 transcription publication policy.
- Generic CMS/page management.
- ContributorProfile/VolunteerProfile/User public exposure.
- Public form uploads/notifications.
- Nested/dropdown menu builder.
- New Podcast/Episode models.
- Public Filament Tables.

## Tests

Create or update focused tests, likely:

```text
tests/Feature/PublicContributorsTopTranscribersUxTest.php
```

Required coverage:

- contributor directory compact cards show name + count badge only;
- compact cards do not include direct page actions;
- clicking/selecting contributor shows preview;
- preview contains contributor page link;
- preview search filters related public items;
- page-size options 10/15/20 work;
- sort toggles A-Z/Z-A/count down/count up work;
- preview item grid is multi-column by default;
- top-transcribers homepage section renders horizontal selector/list;
- clicking/selecting top transcriber renders preview under selector;
- top-transcriber preview supports 5/10/15 item page-size options;
- top-transcriber counts include published transcriptions only when parent item/group are public;
- authors with no public transcriptions are hidden;
- same author with two public transcriptions on one item counts two transcriptions but preview groups by item;
- full contributor page shows public item cards only;
- full contributor page excludes draft/unpublished/no-effective-transcription content;
- contributor settings normalize and affect labels/layout/page sizes;
- no `ContributorProfile`, `VolunteerProfile`, or public `User` model exposure exists;
- no public Filament Table markup is introduced.

Also run existing regression tests:

- `PublicStep9RMenuHeaderUxFixesTest`
- `PublicMenuHeaderUxFixesTest`
- `PublicPodcastsGroupsUxTest`
- `PublicAboutPageContentTeamTest`
- `PublicFormsSubmissionsTest`
- `PublicLatestSearchUxTest`
- `PublicHomepageSearchTest`
- `PublicDisplaySectionsLoopersTest`
- `PublicFrontCardTemplateBuilderTest`
- `PublicContributorDiscoveryTest`
- `PublicItemPageMediaParserTest`

Use exact available test class names.

## Documentation and handoff

Create:

```text
docs/phase-02/public-front-v2-step10-contributors-top-transcribers-ux-handoff.md
```

Required sections:

```md
# Public Front v2 Step 10 Contributors and Top Transcribers UX Handoff

## Purpose
## What was implemented
## Final contributor settings schema
## Contributor directory behavior
## Top transcribers homepage section behavior
## Contributor page behavior
## Counting and grouping rules
## Query/visibility rules
## Final namespaces/classes changed
## Final public API for future prompts
## Card/template/grid integration
## Admin settings UI behavior
## Fallback and invalid config behavior
## Security rules
## Sample JSON payloads
## Sample PHP usage
## Blueprint deviations
## Impact on Step 9F / Footer + Rich Section Builder
## Impact on Step 11 Seeders/Demo Data/Assets/Cleanup
## Open issues / follow-up decisions
## Tests and quality gate summary
```

Update:

```text
docs/phase-02/current-project-state.md
```

Record:

- Step 10 Contributors and Top Transcribers UX complete;
- contributor settings schema;
- top-transcribers homepage behavior;
- contributor page/directory behavior;
- next recommended step:
  - Step 9F / 10F Footer + Rich Section Builder if approved; otherwise
  - Step 11 Seeders/Demo Data/Assets/Cleanup;
- Step 2 transcription policy remains deferred/reserved;
- Prompt 13 has not started.

Patch other docs only if stable requirements changed.

## Quality gate

Run focused tests:

```bash
php artisan test --filter=PublicContributorsTopTranscribersUxTest
```

Then run relevant existing tests:

```bash
php artisan test --filter=PublicStep9RMenuHeaderUxFixesTest
php artisan test --filter=PublicMenuHeaderUxFixesTest
php artisan test --filter=PublicPodcastsGroupsUxTest
php artisan test --filter=PublicAboutPageContentTeamTest
php artisan test --filter=PublicFormsSubmissionsTest
php artisan test --filter=PublicLatestSearchUxTest
php artisan test --filter=PublicHomepageSearchTest
php artisan test --filter=PublicDisplaySectionsLoopersTest
php artisan test --filter=PublicFrontCardTemplateBuilderTest
php artisan test --filter=PublicContributorDiscoveryTest
php artisan test --filter=PublicItemPageMediaParserTest
```

If PHP files were modified:

```bash
vendor/bin/pint --dirty --format agent
```

Then run full gate:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

Do not run `vendor/bin/filacheck --fix`.

## Commit behavior

If and only if all gates pass, commit with:

```text
feat: refine contributors and top transcribers ux
```

Do not push unless explicitly asked.

## Final report

Include:

- preflight state;
- whether any Step 9R follow-up state was finalized before Step 10;
- Boost tools used;
- FilamentExamples MCP usage and query batches;
- MCP research output path;
- Step 10 implementation plan path;
- files changed;
- contributor settings schema;
- contributor directory behavior;
- top-transcribers behavior;
- contributor page behavior;
- counting/grouping rules;
- Step 9F plan updates;
- tests added/updated;
- commands run and results;
- FilaCheck summary;
- handoff report path;
- commit hash if committed;
- current git status;
- confirm no full footer builder was implemented;
- confirm no public user exposure or ContributorProfile model was created;
- confirm no full CMS conversion was implemented;
- confirm Step 2 transcription policy remains deferred/reserved;
- confirm next implementation step recommendation;
- confirm Prompt 13 has not started.

End with exactly:

```text
Public Front v2 Step 10 contributors and top transcribers UX is complete. Prompt 13 has not been started.
```
