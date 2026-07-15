# Public Front v2 Execution Plan

> **Forward-architecture supersession — 2026-07-16:** This remains the record of
> the Public Front v2 sequence and shipped decisions. Its future storage
> assumptions are superseded where they conflict with ARCH1 in
> `docs/research/settings-performance/07-sp3d-pre-research.md`: Card Templates
> and Public Forms move to versioned Resources before SP3D; Menu and About/Team
> remain temporary settings as recorded there.

## Purpose

This is the approved execution plan for implementing Public Front v2 after Prompt 12 and before Prompt 13 dashboard metrics. It resolves the user decisions from `docs/phase-02/public-front-v2-open-questions.md` and sequences the research, blueprints, and blueprint-result plans under:

- `docs/research/public-front-v2/`
- `docs/phase-02/blueprints/public-front-v2/`
- `docs/phase-02/blueprints/public-front-v2/blueprint-results/`

This file is an implementation guide, not completed implementation.

The execution plan must not be pasted into Codex as one giant implementation task. Convert it into one implementation prompt per step. Each step must complete, run its quality gate, update current state, and commit before the next step starts.

## Approved Decisions

- Public form submissions are in scope for v1 with stored `PublicFormSubmission` records and admin review/status/history.
- Public forms must include honeypot and rate limiting before they are enabled for live public use.
- Email notifications are deferred until the storage and review flow is stable.
- Public form file uploads remain deferred.
- Public transcription publication policy is deferred/reserved, not an immediate implementation decision.
- Current production-safe transcription behavior remains: use the existing featured/effective transcription flow, feature the first transcription by current model behavior, keep public output simple around the effective/final transcription, and do not add a complex multiple-published-transcription policy until a later dedicated prompt.
- If transcription publication policy must be implemented earlier because an implementation conflict appears, it must run as a dedicated isolated prompt with full regression tests.
- The canonical public path for content groups becomes `/podcasts`; internal code remains `ContentGroup` and `ContentItem`.
- About page content supports both Markdown and RichEditor JSON, but both must render only through safe/sanitized renderers.
- Homepage section JSON columns should be decided in implementation planning; the default execution decision is to add them in the looper step, not the foundation step.
- First card families are `content_item`, `content_group`, and `contributor`.
- Card templates are globally reusable, sections choose a template key, and section-level semantic inline overrides are allowed through validated JSON.
- Public menu v1 is flat, with internal links, safe external links, public form action links, and a theme selector.
- Latest/search UX uses next/previous controls at the top, load-more at the bottom, and a custom Livewire filter drawer.
- Demo data includes production-safe settings seeding, optional demo seeders, and a demo cleanup Artisan command.
- Podcast cover images are imported from an operator-provided local asset source during demo seeding, with normalized public-storage names.
- The PodText logo already exists at `public/images/podtext-logo.jpg` from the latest branding commit. Future steps must preserve its admin/public panel use and may reuse it in public header/menu defaults where appropriate.

## Global Guardrails

- Use JSON-first documents and typed readers/registries inside their owning
  aggregate.
- ARCH1 now approves `CardTemplate`/revision and `PublicForm`/revision
  model/Resource aggregates. Menu and About/Team remain temporary settings;
  future Pages ownership is separately deferred. Do not create other
  settings-only models by analogy.
- `PublicFormSubmission` remains the transactional submission model and must be
  migrated to exact immutable form-revision binding under ARCH1.
- Do not create `Podcast` or `Episode` models.
- Do not store raw Tailwind classes, CSS, SQL, PHP class names, Blade paths, iframe HTML, or unsafe HTML in JSON.
- Keep Prompt 11R custom public Livewire/Blade listings; do not reintroduce public Filament Tables for listing pages.
- Keep Prompt 11B `Author` contributor discovery.
- Keep Prompt 12 safe media and parse-only transcript rendering.
- Every public query must preserve published group, published item, and public/effective transcription constraints.

## Implementation Prompt Rules

The execution plan must not be pasted into Codex as one giant implementation task. Convert it into one implementation prompt per step. Each step must complete, run its quality gate, update current state, and commit before the next step starts.

After every successful implementation step:

- update `docs/phase-02/current-project-state.md`;
- mark the completed step and commit hash;
- mark the next step;
- patch other docs only when stable requirements, ownership, or durable lessons changed;
- commit implementation + tests + required state docs together only after the full quality gate passes.

After Step 1 completes, Codex must create a handoff report for the external reviewer agent, ChatGPT/Yoni, before future implementation prompts are generated.

Required handoff file:
`docs/phase-02/public-front-v2-step1-json-settings-handoff.md`

The handoff must explain:

- what JSON Settings Architecture was actually implemented;
- final namespaces/classes/value objects/registries/readers/validators;
- final public API/method names future prompts should call;
- settings keys/config groups added or changed;
- fallback/default behavior;
- validation and sanitization behavior;
- how invalid config is reported or ignored;
- whether existing `PublicContentSettings` and `PublicContentCardOptions` were changed;
- sample JSON config payloads;
- sample PHP usage for future steps;
- any deviations from the blueprint;
- any small implementation details that may affect card templates, loopers, public forms, menu/header, about/team, podcasts/groups, contributors, seeders, or Prompt 13;
- exact recommendations for how the next prompts should adapt to the final implementation.

## Step 0: Required Agent Preflight

Every implementation prompt starts with:

```bash
git status --short --branch
git log --oneline --decorate -15
```

Then:

- Confirm Prompt 12 is complete.
- Confirm Prompt 13 has not started unless explicitly approved.
- Stop on unexpected PHP, Blade, migration, test, config, or app-code dirt.
- Read `docs/phase-02/current-project-state.md`.
- Use Laravel Boost `application_info`, `database_schema`, and `search_docs`.
- Use FilamentExamples MCP before Filament code and record whether only snippets or source access was available.

## Step 1: JSON Settings Architecture

Use:

- `docs/research/public-front-v2/01-json-settings-architecture.md`
- `docs/phase-02/blueprints/public-front-v2/01-json-settings-architecture-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/blueprint-results/01-json-settings-architecture-plan.md`

Implement:

- `PublicContentSettings` arrays for public-front configuration.
- `App\Support\PublicFront` registry, reader, validator, and invalid-config value object.
- Safe default merging and invalid-config fallback.
- Settings page fields only after typed defaults/readers exist.

Defer:

- `homepage_sections` JSON columns until Step 4 / Public Display Sections and Loopers, unless a narrower implementation prompt explicitly needs them earlier.

Tests:

- Settings defaults without rows.
- Invalid keys fall back safely.
- Raw class/CSS/SQL/PHP/Blade-looking values are rejected or ignored.

Required Step 1 handoff:

- Create `docs/phase-02/public-front-v2-step1-json-settings-handoff.md` for ChatGPT/Yoni before future implementation prompts are generated.
- Explain final implemented JSON settings classes, public APIs, settings keys, defaults, validation behavior, invalid-config reporting, sample payloads, sample PHP usage, deviations, and prompt-by-prompt adaptation notes.

## Step 2: Deferred / Reserved — Transcription Publication Policy

Do not implement this step in the normal Public Front v2 run.

Current production-safe behavior remains:

- use the existing featured/effective transcription flow;
- the first transcription is featured by current behavior;
- keep public output simple around the effective/final transcription;
- do not add a complex multiple-published-transcription policy until a later dedicated prompt.

If this policy must be implemented earlier because an implementation conflict appears, it must run as a dedicated isolated prompt with full regression tests across:

- TranscriptionResource;
- ContentItem transcriptions relation manager;
- importers;
- public item page/transcript viewer;
- effective transcription resolution.

## Step 3: Card Template Builder

Use:

- `docs/research/public-front-v2/02-card-template-builder.md`
- `docs/phase-02/blueprints/public-front-v2/02-card-template-builder-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/blueprint-results/02-card-template-builder-plan.md`

Implement:

- Card families: `content_item`, `content_group`, `contributor`.
- Global reusable templates in settings JSON.
- Section override merge support in the reader/renderer, using semantic keys only.
- Template parts for image, title, description, group identity, transcriber, metadata, taxonomy, and safe action links.
- Default template parity with current cards.

Tests:

- Missing template falls back to family default.
- Invalid part/source/attribute is skipped.
- Public cards still represent `ContentItem` records.
- Rendering a grid does not introduce N+1 queries.

## Step 4: Public Display Sections And Loopers

Use:

- `docs/research/public-front-v2/03-public-display-sections-loopers.md`
- `docs/phase-02/blueprints/public-front-v2/03-public-display-sections-loopers-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/blueprint-results/03-public-display-sections-loopers-plan.md`

Implement:

- Add nullable JSON config columns to `homepage_sections`: `source_config`, `selection_config`, `display_config`, `pagination_config`.
- Preserve existing typed fields for backward compatibility.
- Looper registry/query resolver/config reader.
- Source types for latest, category, tag, group, manual items, groups/podcasts, contributors, categories, and top transcribers where the current data supports them.
- Section-level template key and semantic inline overrides in `display_config`.

Tests:

- Existing homepage sections render with empty JSON config.
- Manual include/exclude cannot expose draft/unpublished records.
- Category/tag/group loopers keep public visibility constraints.
- Latest source enforces total-limit/page-size rules.

## Step 5: Latest And Search UX

Use:

- `docs/research/public-front-v2/09-latest-search-ux.md`
- `docs/phase-02/blueprints/public-front-v2/09-latest-search-ux-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/blueprint-results/09-latest-search-ux-plan.md`

Implement:

- Latest as a looper with heading, lightweight search, top next/previous controls, and bottom load-more.
- Total query size minimum 50 and page size range 4-25.
- Custom Livewire drawer for filters.
- Category toggles, tag chips, active filter count, and clear-all behavior.
- Deterministic card layout rules with `min-w-0`, fixed image tracks/aspect ratios, and semantic line clamps.

Tests:

- Next/previous and load-more update state.
- Filter count and clear-all are correct.
- URL-backed search/filter/sort state survives interactions.
- No public Filament Table regression.

## Step 6: Public Forms And Submissions

Use:

- `docs/research/public-front-v2/06-public-forms-submissions.md`
- `docs/phase-02/blueprints/public-front-v2/06-public-forms-submissions-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/blueprint-results/06-public-forms-submissions-plan.md`

Implement:

- Historical shipped step: Public form definitions in settings JSON. ARCH1 now
  migrates definitions into `PublicForm`/immutable revision Resources while
  bounded verification policy remains settings.
- Supported fields: text, email, phone, textarea, select, checkbox, toggle, URL.
- `PublicFormSubmission` model, migration, enum status, and admin Resource.
- Status transitions: `new`, `reviewed`, `archived`.
- Honeypot and rate limiting before forms can be publicly enabled.
- No notification emails and no file uploads in v1.

Tests:

- Enabled forms submit and store escaped payload.
- Disabled forms cannot submit.
- Honeypot/rate-limit behavior works.
- Required/email/url/select validation is generated from the registry.
- Admin resource escapes payload and supports status changes.

## Step 7: About Page Content And Team Builder

Use:

- `docs/research/public-front-v2/05-about-page-content-team-builder.md`
- `docs/phase-02/blueprints/public-front-v2/05-about-page-content-team-builder-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/blueprint-results/05-about-page-content-team-builder-plan.md`

Implement:

- Public `/about` page.
- About content blocks in settings JSON.
- Markdown blocks through the existing safe Markdown renderer.
- RichEditor JSON blocks through Filament rich content rendering and sanitizer.
- Team profiles in settings JSON with image upload constraints.
- No About/team models.

Tests:

- Disabled page does not render public content.
- Hidden blocks/profiles do not render.
- Markdown and RichEditor XSS payloads are sanitized.
- FileUpload schema has disk, directory, visibility, MIME, and max-size rules.

## Step 8: Podcasts And Groups UX

Use:

- `docs/research/public-front-v2/10-podcasts-groups-ux.md`
- `docs/phase-02/blueprints/public-front-v2/10-podcasts-groups-ux-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/blueprint-results/10-podcasts-groups-ux-plan.md`

Implement:

- Canonical public path `/podcasts`.
- User-facing labels: podcast/podcasts where configured.
- Internal names remain `ContentGroup` and `ContentItem`.
- Groups/podcasts page with category toggle buttons, search by group title/topic, image cards, and public episode count.
- Group/podcast show page with item rows/cards, descriptions, and template-controlled layout.

Route note:

- Do not keep `/groups` as a second public browse path unless a later prompt explicitly approves redirect/backward-compatibility behavior.

Tests:

- Unpublished groups and items are hidden.
- Episode counts include public items only.
- Category filters include descendants and inherited group categories.
- Search matches group name/topic safely.
- No `Podcast` or `Episode` model is introduced.

## Step 9: Public Menu And Header

Use:

- `docs/research/public-front-v2/04-public-menu-header-manager.md`
- `docs/phase-02/blueprints/public-front-v2/04-public-menu-header-manager-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/blueprint-results/04-public-menu-header-manager-plan.md`

Implement:

- Flat public header from settings JSON.
- Default items: home, podcasts, about, request transcription form, volunteer transcriber form, theme selector.
- Internal routes through a route registry.
- External URLs as HTTPS-only.
- Public form action links using enabled form definitions.
- Missing route/form targets are skipped or disabled server-side.
- Preserve existing Filament/admin and public branding that already uses `public/images/podtext-logo.jpg`, and reuse it in public header/menu defaults where appropriate.

Tests:

- Default menu renders.
- Missing/disabled form items are skipped or disabled server-side.
- Non-HTTPS and `javascript:` URLs are rejected.
- Public panel navigation remains disabled.

## Step 10: Contributors And Top Transcribers UX

Use:

- `docs/research/public-front-v2/08-contributors-transcribers-ux.md`
- `docs/phase-02/blueprints/public-front-v2/08-contributors-transcribers-ux-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/blueprint-results/08-contributors-transcribers-ux-plan.md`

Implement:

- Settings for contributor labels and counts.
- Desktop compact list/preview layout.
- Compact card with name and count badge only.
- Preview with full transcriber page link and latest public items.
- Homepage top transcribers with page-size choices 5, 10, 15.

Tests:

- Counts include each public transcription by author.
- Same item with multiple author transcriptions counts both but renders one grouped preview item.
- Selected contributor state is URL-backed.
- Unpublished parent records are excluded from counts and previews.

## Step 11: Seeders, Demo Data, Assets, And Cleanup

Use:

- `docs/research/public-front-v2/11-seeders-demo-data.md`
- `docs/phase-02/blueprints/public-front-v2/11-seeders-demo-data-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/blueprint-results/11-seeders-demo-data-plan.md`

Implement:

- `PublicFrontSettingsSeeder` for production-safe JSON defaults.
- Optional `DemoHebrewPublicFrontSeeder` for demo-only public-front content.
- `DemoContentCleanupCommand` to remove demo data by stable reference keys/prefixes.
- Do not run demo seeders automatically in production.
- Keep `DatabaseSeeder` guarded and explicit.

Logo:

- Preserve the existing `public/images/podtext-logo.jpg` admin/public panel branding baseline and reuse it for public-front defaults where appropriate.

Podcast cover image import:

- Do not commit the full local source image folder.
- Add a local-only seeder option or environment variable such as `PODTEXT_DEMO_PODCAST_IMAGE_SOURCE_DIR`.
- Seeder should scan the operator-provided directory, exclude the logo file, accept only JPEG/PNG/WebP, and choose a deterministic subset for demo podcast/group covers.
- Copy selected images to the public disk under `content-groups/covers/demo/`.
- Normalize names using target group slugs/reference keys, for example `content-groups/covers/demo/{content_group_reference_key}.jpg`; if a source cannot be mapped to a group, use `demo-podcast-cover-{sequence}-{hash}.{extension}`.
- Store the copied relative path in `ContentGroup.cover_path`.
- Keep the operation idempotent: reruns should not duplicate files or change existing demo group mappings unless a `--refresh-assets` option is used.

Tests:

- Settings seeder is idempotent.
- Demo seeder is idempotent.
- Cleanup command removes demo records and known demo cover files.
- Demo records are identifiable by stable keys.

## Step 12: Prompt 13 Dashboard Metrics

Prompt 13 remains blocked until Public Front v2 is implemented or the user explicitly chooses to resume dashboard metrics first.

When resumed, Prompt 13 should account for the new public-form submission status counts, demo/default warnings, and public-front editorial metrics only where those metrics are real schema-backed states. Transcription-policy conflict metrics should wait unless Step 2 / Reserved is explicitly promoted and implemented.

## Planned prompts after Step 1

The execution plan should require one implementation prompt per step. After Step 1 is finished and reviewed, future prompts should be generated in this order, with exact wording adapted to the final JSON Settings Architecture implementation:

1. Public Front v2 Step 3: Card Template Builder Foundation.
2. Public Front v2 Step 4: Public Display Sections and Loopers.
3. Public Front v2 Step 5: Latest and Search UX.
4. Public Front v2 Step 6: Public Forms and Submissions.
5. Public Front v2 Step 7: About Page Content and Team Builder.
6. Public Front v2 Step 8: Podcasts and Groups UX.
7. Public Front v2 Step 9: Public Menu and Header.
8. Public Front v2 Step 10: Contributors and Top Transcribers UX.
9. Public Front v2 Step 11: Seeders, Demo Data, Assets, and Cleanup.
10. Public Front v2 Step 2 / Reserved: Transcription Publication Policy, only if explicitly promoted from deferred status and always as an isolated prompt.
11. Public Front v2 Step 12: Prompt 13 Dashboard Metrics readiness / next decision.

Do not pre-generate all implementation prompts before Step 1 is reviewed. The final public JSON settings API may affect all following prompts.

## Required Final Gate For Each Implementation Prompt

For app-code prompts:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

For docs-only prompts:

```bash
git diff --check
git status --short
```

Each final report must include files changed, tests added/updated, commands run and results, FilaCheck result when applicable, user decisions applied, deferred issues, blocked items, and current git status.
