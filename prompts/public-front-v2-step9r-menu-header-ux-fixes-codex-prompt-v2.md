# Codex Prompt — Public Front v2 Step 9R: MCP Research Discipline + Menu/Header UX Fixes + Scope Split

Work in the current PhpStorm / Codex App project repository only.

This is a repair/verification prompt after Public Front v2 Step 9.

Do not run Step 10 Contributors and Top Transcribers UX as a full step.
Do not run Step 11 Seeders/Demo Data/Cleanup.
Do not run Prompt 13, Prompt 14, or Prompt 15.
Do not implement Step 2 transcription publication policy; it remains deferred/reserved.
Do not convert the About page into a generic CMS.
Do not create `Podcast` or `Episode` models.
Do not create settings-only models such as `PublicMenu`, `PublicMenuItem`, `PublicFormDefinition`, `FooterSection`, or `PublicFooter`.
Do not create full footer-manager implementation in this prompt.
Do not reintroduce public Filament Tables.
Do not push to GitHub unless explicitly asked.
Do not use worktrees.
Do not launch parallel agents.
Do not run `vendor/bin/filacheck --fix` unless explicitly approved.

## Goal

Fix remaining public UI/menu/header/settings issues reported after Step 9, verify what Step 8 and Step 9 actually implemented against their plans, improve MCP research guidance for future agents, and produce a clear plan for future full section/footer-builder work without implementing the full footer manager now.

This prompt has four tracks:

1. **MCP research/process improvement**
   - Add project guidance for better FilamentExamples MCP usage.
   - Before each implementation subtask, create short focused search-term batches.
   - Run multiple `filament-examples` MCP searches, not one broad search.
   - Use higher `limit` values when supported.
   - Inspect results, then run a second pass with refined terms.
   - Record examples/patterns used.

2. **Verification against Step 8 / Step 9 plans**
   - Read the Step 8 implementation plan.
   - Read the Step 9 implementation plan.
   - Read the Step 9 handoff.
   - Compare planned work to actual repo state.
   - Produce a verification matrix before fixing code.

3. **Public UX/settings fixes**
   - Fix stale `discovery-chrome` on homepage root with query params.
   - Remove redundant fixed Filament page titles.
   - Finish/extend public menu/header settings and rendering.
   - Fix theme selector modes.
   - Restore heading hierarchy styling properly.
   - Add image styling settings.
   - Improve contributor preview grid/list behavior where Step 9 already touched it.
   - Improve item/group/podcast image fallback and badge/title composition.
   - Add global search to public header.
   - Add menu item alignment setting.

4. **Scope split for Step 10 and future footer/section-builder work**
   - Keep full Contributors and Top Transcribers UX as Step 10.
   - Only fix contributor directory issues that are regressions or direct Step 9 follow-ups.
   - Do not implement full top-transcribers homepage redesign here.
   - Create a future plan for richer homepage section types and footer manager/section builder, but do not implement the full footer manager now.

## Step 10 scope decision

Step 10 is still the next major feature step after this repair prompt.

Do not merge all of Step 10 into this Step 9R prompt.

This prompt may fix contributor-directory regressions because Step 9 already modified contributor cards and preview behavior. But the following belong to Step 10 and should remain for the next dedicated prompt unless they are tiny and explicitly required to repair current broken behavior:

- full top-transcribers homepage section redesign;
- horizontal top-transcriber selector;
- top-transcriber preview below the selector;
- contributor preview item pagination 5/10/15 inside top-transcriber sections;
- contributor-page UX refinements beyond the reported directory preview fixes;
- contributor section-level settings beyond direct repair settings.

Create/update the handoff so Step 10 knows exactly what remains.

## Future footer/section-builder scope decision

The user wants future homepage sections and footer manager features:

- section type with layout settings for one or several columns;
- smart responsive column template settings;
- admin can add columns;
- each column has Builder blocks;
- block types such as RichEditor HTML/JSON, Markdown, smart rich content, links/actions, form CTA, etc.;
- footer manager with section builder;
- footer form section that can display form inline or modal/CTA;
- bottom bar section with styling settings for height/background/content/alignment.

Do not implement the full footer manager or full columns/block-builder section system in this prompt.

Instead:
- implement only a minimal safe `content_block` homepage section if it already exists partially and can be completed safely;
- create a future planning file:
  `docs/phase-02/public-front-v2-step9f-section-footer-builder-plan.md`
- the plan must specify whether this should run before or after Step 10 and before Step 11 seeders;
- recommend the smallest safe next step.

Recommended future sequence unless repo reality suggests otherwise:
1. Step 9R: this repair prompt.
2. Step 10: Contributors and Top Transcribers UX.
3. Step 9F or Step 10F: Footer + Rich Section Builder foundation.
4. Step 11: Seeders/Demo Data/Assets/Cleanup.
5. Prompt 13: Dashboard Metrics.

## Current known issue summary

### 1. Homepage stale discovery chrome and root query params

Problem:
- The old/deprecated `discovery-chrome` section with old search can still appear on the homepage.
- It appears when navigating/rerouting to root with a query param such as:

```text
/?sort=latest_transcription
```

Required:
- The homepage root `/` must not show old global discovery/search/filter chrome.
- Root should not treat `sort` as a reason to switch into the old search/discovery layout.
- `/search` should remain the dedicated search page.
- If root receives search/sort/filter query params, either ignore them safely or canonicalize/redirect to the correct `/search` route only if that behavior is already approved and tested.
- Do not break category/tag/podcasts/about/contributor/item pages.

### 2. Redundant Filament page titles

Problem:
- Public pages show fixed Filament page titles while the page content already has an inner title.
- This creates duplicate titles.

Required:
- Suppress/remove Filament page header/title chrome where public custom Blade already renders the meaningful title.
- Do this consistently for homepage and relevant custom public pages.
- Preserve accessibility: each page must still have a meaningful H1 inside content.

### 3. Public menu/header logo settings

Problem/request:
- Menu/header needs configurable logo settings.
- Support light logo and dark logo.
- Uploads should support normal images and SVG where safe.
- Default should use public panel brand logo / dark-mode brand logo values if available, falling back to existing PodText logo.

Required:
- Extend `menu_config` or a safe header settings group with:
  - light logo path;
  - dark logo path;
  - logo alt text;
  - logo display mode/size if already compatible with existing config.
- Support SVG uploads if the project safety rules permit; otherwise document as deferred and explain why.
- Defaults should preserve current brand logo behavior.
- Do not duplicate logo rendering with multiple images unless the theme requires it.
- Keep all logo paths storage-managed/safe. No arbitrary external URLs unless explicitly allowed and validated as HTTPS.

### 4. Theme selector display modes

Problem/request:
- Theme selector needs setting options:
  - texts only;
  - texts + icons;
  - icons only;
  - trigger icon only that opens an icon menu to select theme type.
- Style should be rounded.

Required:
- Extend `menu_config.theme_selector` with safe semantic display options.
- Implement supported modes:
  - `text`
  - `text_icon`
  - `icon`
  - `trigger_icon_menu`
- Use rounded styling through fixed Blade classes, not JSON-provided classes.
- Keep theme state local browser UI only unless existing code already stores it elsewhere.
- Preserve light/dark/system behavior from Step 9.

### 5. Heading typography regression

Problem:
- The prior heading fix overcorrected and dropped H2+ typography.
- Global CSS has:

```css
h1, h2, h3, h4, h5, h6 {
    font-size: inherit;
    font-weight: inherit;
}
```

- H1 was missing explicit styling, but after the fix all headings are effectively base-sized.

Required:
- Restore explicit visual hierarchy for H1-H6 in public rich/Markdown content.
- Add the missing H1 styling instead of removing the H2+ sizes.
- H1 must be larger/stronger than body text.
- H2-H6 must retain proper sizes/weights.
- Implement in the central public safe content classes/renderer where possible.
- Do not rely on raw CSS/classes from JSON.
- Preserve Markdown/RichEditor sanitization.

### 6. Image styling settings everywhere relevant

Problem/request:
- Add richer image styling settings wherever image settings exist.

Required semantic options:
- image fit/crop:
  - `cover`
  - `contain`
- image radius:
  - `sharp`
  - `low_rounded`
  - `mid_rounded`
  - `high_rounded`
  - `round`
  - `circle`

Apply where relevant and safe:
- item cards;
- podcast/group cards;
- About team profiles;
- About image blocks;
- header/logo only if appropriate, but do not force circular logos.

Use fixed class maps in PHP/Blade.
Do not store Tailwind class strings in JSON.

### 7. Contributor preview result grid

Problem:
- Contributor preview related item cards default to one card per row.
- It should default to many cards per row.

Required:
- Add settings or normalized defaults for contributor preview card grid columns.
- Default should be multiple columns where viewport permits.
- Suggested semantic values:
  - 2
  - 3
  - 4
  - responsive default
- Add tests/markers that verify preview result grid does not force one column by default.
- Keep compact contributor list behavior from Step 9.

### 8. Item cards without image should use group/podcast image

Problem/request:
- If a content item has no own image, the item card should display the parent content group/podcast image by default.

Required:
- Update item card image resolution:
  1. item/external thumbnail if present;
  2. content group cover image if present;
  3. fallback visual/initials only if neither image exists.
- Do not show redundant group thumbnail again in the group badge if it is already used as the main card image.
- Add tests.

### 9. Podcast/group badge simplification and title composition

Problem:
- Current group/podcast badge behavior is too decorative.
- Initials profile image is pointless.
- Group thumbnail should only display if configured and not duplicative.
- Group/podcast text should be more like part of the title line.

Required:
- Group/podcast badge should be simple text by default.
- Font should be larger, similar to item title context.
- Settings should allow:
  - name only;
  - thumbnail + name;
  - combined title mode where group name and item title are joined.
- Default separator:

```text
" - "
```

- If group name is combined with item title, output should be predictable and line-clamped.
- Group thumbnail should display only if item has no own image or if settings explicitly allow duplicated thumbnail.
- Remove initials-based group badge visual for item cards unless explicitly used as fallback when no image exists.
- Add tests for:
  - item image fallback to group image;
  - no duplicate group thumbnail;
  - text-only group badge;
  - custom separator.

### 10. Global search in menu/header

Problem/request:
- Add a global search to the public menu/header.
- Rounded corners.

Required:
- Add safe header setting:
  - enabled/disabled;
  - placeholder label;
  - target route, likely `/search`;
  - query param, likely `q`;
  - display mode if needed.
- Search submits/navigates to `/search?q=...`.
- Do not duplicate homepage search state.
- Do not turn header search into a Livewire global complex search unless existing architecture makes it easy.
- Rounded styling must be fixed Blade classes.

### 11. Menu item alignment settings

Problem/request:
- Menu items should be alignable:
  - centered;
  - at the start, just after the logo;
  - toward the end.
- For Hebrew/RTL, start means right side after the logo.

Required:
- Add safe semantic setting in `menu_config`, e.g.:
  - `items_alignment`: `start`, `center`, `end`
- Apply RTL-aware flex layout.
- Do not use raw CSS/classes from JSON.
- Add tests/markers.

## MCP research/process requirements

Before implementation, improve the project AI docs/guidance so future agents use FilamentExamples MCP better.

Patch active guidance files, likely:
- `AGENTS.md`
- `.ai/guidelines/tooling-quality.md`
- `docs/phase-02/ai-development-lessons.md`
- `docs/phase-02/public-front-v2-agent-usage-index.md`
- maybe `docs/phase-02/tooling-and-quality-gates.md`

Add a section such as:

```md
## FilamentExamples MCP research protocol
```

Rules to add:
- Use `filament-examples` MCP before changing Filament Resources, Pages, Settings pages, forms, tables, actions, widgets, Livewire public page patterns, or panel layout/header behavior.
- Do not run one broad query only.
- First, decompose the feature into short topic phrases.
- Scatter terms across multiple query batches.
- Use multiple short queries rather than one long query.
- Prefer `limit` 8 to 10 if supported by the MCP tool.
- If the MCP rejects the limit, retry with the maximum accepted limit or with `limit: 3`.
- After first results, inspect names/snippets/source paths.
- Run a second pass with refined terms based on the result names/classes/patterns.
- Search not only direct goals but also surrounding implementation patterns.
- For each relevant example, record:
  - example name;
  - file/class/snippet found;
  - pattern to copy;
  - pattern to avoid;
  - PodText adaptation notes.
- If the MCP exposes a source/read/fetch/details tool, use it.
- If only `search_examples` exists, record that limitation honestly.
- Never write MCP token/header values to tracked docs.

Example command shape for agents:


mcp.filament-examples.search-examples {
  "queries": [
    "settings page tabs",
    "collapsible sections",
    "public header menu",
    "custom panel header",
    "Livewire public search",
    "Filament settings form tabs"
  ],
  "limit": 8
}


If `limit: 8` fails, retry:

mcp.filament-examples.search-examples {
  "queries": [
    "settings page tabs",
    "collapsible sections",
    "public header menu"
  ],
  "limit": 3
}

For this prompt specifically, run MCP searches in batches for:

Batch 1 — settings page organization:
- `settings page tabs`
- `collapsible sections`
- `full width settings form`
- `Filament SettingsPage tabs`
- `settings form schema sections`

Batch 2 — public header/menu:
- `public page header`
- `custom panel header`
- `Filament public menu`
- `menu builder`
- `render hook header`
- `theme switcher`

Batch 3 — contributor/cards:
- `Livewire card preview`
- `contributor directory`
- `card grid settings`
- `public card layout`
- `search inside preview`

Batch 4 — content/heading/images:
- `Markdown content styling`
- `RichEditor public rendering`
- `image crop settings`
- `FileUpload SVG logo`
- `card image fallback`

Batch 5 — surrounding best practice:
- `custom page layout`
- `Livewire public page`
- `SettingsPage Builder`
- `repeater menu items`
- `public form modal`

Record the research output in:

```text
docs/research/public-front-v2/13-step9r-menu-header-ux-fixes-mcp-research.md
```

## Read first

- `AGENTS.md`
- `.ai/guidelines/tooling-quality.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/ai-development-lessons.md`
- `docs/phase-02/public-front-v2-agent-usage-index.md`
- `docs/phase-02/public-front-v2-execution-plan.md`
- `docs/phase-02/public-front-v2-step6-public-forms-submissions-handoff.md`
- `docs/phase-02/public-front-v2-step7-about-page-content-team-builder-handoff.md`
- `docs/phase-02/public-front-v2-step8-podcasts-groups-ux-handoff.md`
- `docs/phase-02/public-front-v2-step9-public-menu-header-ux-fixes-handoff.md`
- `docs/phase-02/public-front-v2-step8-podcasts-groups-ux-implementation-plan.md`
- `docs/phase-02/public-front-v2-step9-public-menu-header-ux-fixes-implementation-plan.md`
- `docs/research/public-front-v2/04-public-menu-header-manager.md`
- `docs/phase-02/blueprints/public-front-v2/04-public-menu-header-manager-blueprint.md`
- current public panel provider and render hooks
- current `App\Livewire\Public\PublicHeader`
- current public header Blade view
- current contributor directory Livewire/view
- current homepage/search Livewire/view
- current About renderer and views
- current `PublicContentSettings` page
- current `PublicFrontConfigRegistry` and `PublicFrontConfigValidator`
- current public tests for menu/header, About, contributors, homepage/search, podcasts, forms, item page.

## Preflight

Run:

```bash
git status --short --branch
git log --oneline --decorate -15
php artisan migrate:status
php artisan route:list --path=podcasts
php artisan route:list --path=about
php artisan route:list --path=contributors
php artisan route:list --path=search
```

Confirm:
- working tree is clean;
- Step 9 commit is present;
- Step 9 migrations/settings migrations have run locally if any;
- Prompt 13 has not started.

If a pending migration from previous steps exists, run:

```bash
php artisan migrate
```

Use Laravel Boost:
- `application_info`
- `database_schema`
- `search_docs`

Use Boost `search_docs` before changing Filament SettingsPage tabs, section schemas, public panel render hooks/layout, Livewire URL state, Alpine interactions, FileUpload/SVG handling, public rich content rendering, or testing APIs.

## Verification plan before implementation

Before changing app code, create:

```text
docs/phase-02/public-front-v2-step9r-verification-and-fixes-plan.md
```

It must include:

1. Step 8 plan verification matrix:
   - planned item;
   - actual repo evidence;
   - status: implemented / partial / deferred / not applicable / regression / blocker;
   - follow-up needed.

2. Step 9 plan verification matrix:
   - planned item;
   - actual repo evidence;
   - status;
   - follow-up needed.

3. Step 10 overlap decision:
   - list contributor-related requested fixes;
   - classify each as Step 9R repair vs Step 10 full contributor UX;
   - do not implement full top-transcribers redesign in Step 9R;
   - record what remains for Step 10.

4. Footer/section-builder future plan:
   - record the requested rich section and footer-builder features;
   - explain why the full footer manager is deferred from Step 9R;
   - recommend whether it should run after Step 10 or before Step 11;
   - include proposed JSON schemas and implementation prompts at a high level only.

5. Issue diagnosis sections:
   - discovery-chrome and root query params;
   - duplicate public page titles;
   - menu/header logo settings;
   - theme selector display modes;
   - heading typography regression;
   - image styling settings;
   - contributor preview grid;
   - item image fallback;
   - group/podcast badge behavior;
   - header global search;
   - menu item alignment.

6. Exact files to change.

7. Tests to add/update.

After writing the plan, continue implementation only if no conflict requires a human decision.

If a major route/layout/panel conflict is discovered, stop before implementation and report.

## Implementation requirements

### 1. Implement MCP guidance updates first

Patch the AI/docs guidance files first, before app changes.

Add the MCP research protocol described above.

### 2. Create the MCP research output

Create:

```text
docs/research/public-front-v2/13-step9r-menu-header-ux-fixes-mcp-research.md
```

Include:
- all query batches used;
- result summaries;
- examples/snippets/classes found;
- what was copied/adapted;
- what was rejected;
- access limitation if only `search_examples` exists.

### 3. Create the section/footer future plan

Create:

```text
docs/phase-02/public-front-v2-step9f-section-footer-builder-plan.md
```

Include:
- homepage rich section type requirements;
- column/blocks layout requirements;
- footer manager requirements;
- footer form-section requirements;
- bottom bar requirements;
- JSON-first schema proposal;
- Filament Builder/Reapter/SettingsPage approach;
- public renderer approach;
- tests;
- security rules;
- recommendation whether to run after Step 10 or before Step 11.

Do not implement full footer manager in this prompt.

### 4. Fix public settings page organization

Re-check current implementation. If Step 9 already did tabs but not correctly, patch it.

Required:
- major tabs;
- full-width collapsible sections;
- no cramped top-level columns;
- no lost fields/settings.

### 5. Fix homepage discovery chrome and root query params

Required:
- no `discovery-chrome` or old global search/filter panel on `/`, including `/?sort=latest_transcription`;
- `/search` still renders search/filter drawer;
- no undesired root redirect caused by `sort`;
- tests for `/` with `sort` query.

### 6. Remove duplicate public page titles

Required:
- no redundant Filament page title when custom inner title exists;
- keep semantic H1 inside content;
- tests for homepage/About/podcasts/search where practical.

### 7. Extend menu/header logo settings

Required:
- light logo;
- dark logo;
- upload support for safe images and SVG if safe;
- fallback to public panel `brandLogo` / `darkModeBrandLogo` when available, otherwise existing PodText logo;
- tests for settings and rendering.

### 8. Extend theme selector modes

Required modes:
- `text`
- `text_icon`
- `icon`
- `trigger_icon_menu`

Required:
- rounded style;
- safe semantic settings;
- tests/markers.

### 9. Fix heading typography

Required:
- restore/define H1-H6 styling;
- H1 visibly styled;
- H2+ not flattened to base text;
- central public content class/helper preferred;
- tests/markers.

### 10. Add image styling semantic options

Required:
- `image_fit`: `cover`, `contain`
- `image_radius`: `sharp`, `low_rounded`, `mid_rounded`, `high_rounded`, `round`, `circle`

Apply to:
- item cards;
- podcast/group cards;
- About team profiles;
- About image blocks where practical;
- header/logo only if safe and appropriate.

### 11. Contributor preview/list fixes

Required:
- preview separate row under list;
- compact card name + count badge only;
- count badge has icon and title/tooltip;
- preview card has contributor page link;
- related items preview search;
- page sizes 10/15/20;
- sort toggles A-Z, Z-A, count down, count up;
- preview results default multiple cards per row;
- tests.

Do not implement the full top-transcribers homepage redesign in this prompt unless it is already partially implemented and trivial to preserve/fix.

### 12. Item card image fallback and group badge/title behavior

Required:
- item image fallback to group/podcast image;
- no duplicate group thumbnail when group image is already main image;
- group badge simple text by default;
- optional thumbnail + name mode;
- combined group-name + item-title mode with separator default `" - "`;
- remove initials-based group badge on item cards unless neither item nor group image exists and fallback is configured;
- tests.

### 13. Header global search

Required:
- rounded input;
- submits/navigates to `/search?q=...`;
- does not own full search state;
- tests.

### 14. Header menu alignment

Required:
- `items_alignment`: `start`, `center`, `end`;
- RTL-aware;
- tests/markers.

## Out of scope

- Step 10 full contributors/top-transcribers redesign beyond listed fixes.
- Step 11 seeders/demo data/assets/cleanup.
- Prompt 13 dashboard metrics.
- Prompt 14/15.
- Step 2 transcription publication policy.
- Generic CMS/page management.
- Full footer manager implementation.
- Full responsive column/blocks homepage section implementation beyond minimal existing `content_block` support.
- `PublicMenu` / `PublicMenuItem` models or tables.
- `Podcast` / `Episode` models.
- Public Filament Tables.
- Route path changes or `/groups` redirects.

## Tests

Create or update focused tests, likely:

```text
tests/Feature/PublicStep9RMenuHeaderUxFixesTest.php
```

Required coverage:
- MCP guidance docs contain required protocol wording.
- Step 10 overlap decision is documented.
- Section/footer future plan is created.
- `discovery-chrome` absent on `/` and `/?sort=latest_transcription`.
- `/search` still renders search/filter drawer.
- duplicate public page title is absent where custom inner title exists.
- menu logo settings normalize and render/fallback.
- dark logo setting normalizes and renders/fallbacks.
- SVG logo upload/config is supported or explicitly deferred with a test/doc note.
- theme selector modes render expected markers.
- heading H1-H6 markers/classes exist.
- image radius/fit settings normalize and render markers/classes.
- contributor compact card has name + count badge only.
- contributor compact card has no actions.
- contributor preview row appears under list.
- contributor preview related item search works.
- contributor page sizes 10/15/20 and sort toggles work.
- contributor preview grid defaults to multiple columns.
- item without image uses group image.
- group thumbnail is not duplicated when used as main image.
- group badge can render text-only.
- group/item title separator default is `" - "`.
- header global search navigates/submits to `/search?q=...`.
- menu item alignment setting renders RTL-safe markers/classes.
- no `PublicMenu` / `PublicMenuItem` models exist.
- no public Filament Table markup is reintroduced.

Also run existing regression tests:
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
docs/phase-02/public-front-v2-step9r-menu-header-ux-fixes-handoff.md
```

Required sections:

```md
# Public Front v2 Step 9R Menu/Header UX Fixes Handoff

## Purpose
## MCP research protocol added
## Step 8 verification summary
## Step 9 verification summary
## Step 10 overlap decision
## Section/footer future plan
## Discovery chrome/root query fix
## Page title/chrome fix
## Logo settings behavior
## Theme selector modes
## Heading typography fix
## Image styling settings
## Contributor directory fixes
## Item image fallback and group badge behavior
## Header global search
## Menu alignment behavior
## Final namespaces/classes changed
## Final public API for future prompts
## Settings/JSON schema changes
## Fallback and invalid config behavior
## Security rules
## Sample JSON payloads
## Blueprint deviations
## Impact on later prompts
## Open issues / follow-up decisions
## Tests and quality gate summary
```

Impact on later prompts must cover:
- Step 10 Contributors and Top Transcribers UX;
- Step 9F / Future Section and Footer Builder, if recommended;
- Step 11 Seeders, Demo Data, Assets, and Cleanup;
- Step 2 / Reserved Transcription Publication Policy;
- Prompt 13 Dashboard Metrics.

Update:

```text
docs/phase-02/current-project-state.md
```

Record:
- Step 9R menu/header UX fixes complete;
- MCP guidance protocol added;
- main UX fixes;
- section/footer future plan path;
- next implementation step is Step 10 Contributors and Top Transcribers UX unless the handoff recommends Step 9F first and the user approves;
- Step 2 transcription policy remains deferred/reserved;
- Prompt 13 has not started.

Patch other docs only if stable requirements changed.

## Quality gate

Run focused tests:

```bash
php artisan test --filter=PublicStep9RMenuHeaderUxFixesTest
```

Then run relevant existing tests:

```bash
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
fix: refine public menu header and homepage ux
```

Do not push unless explicitly asked.

## Final report

Include:
- preflight state;
- Boost tools used;
- FilamentExamples MCP usage and query batches;
- files changed;
- verification plan path;
- MCP research output path;
- section/footer future plan path;
- Step 8 verification result;
- Step 9 verification result;
- Step 10 overlap decision;
- each issue fixed/deferred;
- settings/schema changes;
- tests added/updated;
- commands run and results;
- FilaCheck summary;
- handoff report path;
- commit hash if committed;
- current git status;
- confirm no full CMS conversion was implemented;
- confirm no full footer manager was implemented;
- confirm no settings-only menu/footer models were created;
- confirm Step 2 transcription policy remains deferred/reserved;
- confirm next implementation step recommendation:
  - Step 10 Contributors and Top Transcribers UX, or
  - Step 9F Section/Footer Builder first, only if strongly justified;
- confirm Prompt 13 has not started.

End with exactly:

```text
Public Front v2 Step 9R menu/header UX fixes are complete. Prompt 13 has not been started.
```
