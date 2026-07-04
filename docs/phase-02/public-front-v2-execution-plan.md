# Public Front v2 Execution Plan

## Purpose

This is the approved execution plan for implementing Public Front v2 after Prompt 12 and before Prompt 13 dashboard metrics. It resolves the user decisions from `docs/phase-02/public-front-v2-open-questions.md` and sequences the research, blueprints, and blueprint-result plans under:

- `docs/research/public-front-v2/`
- `docs/phase-02/blueprints/public-front-v2/`
- `docs/phase-02/blueprints/public-front-v2/blueprint-results/`

This file is an implementation guide, not completed implementation.

## Approved Decisions

- Public form submissions are in scope for v1 with stored `PublicFormSubmission` records and admin review/status/history.
- Public forms must include honeypot and rate limiting before they are enabled for live public use.
- Email notifications are deferred until the storage and review flow is stable.
- Public form file uploads remain deferred.
- Public transcription policy defaults to one public transcription per content item.
- Publishing a second public transcription must use an explicit "publish and replace" style action, not automatic replacement.
- The canonical public path for content groups becomes `/podcasts`; internal code remains `ContentGroup` and `ContentItem`.
- About page content supports both Markdown and RichEditor JSON, but both must render only through safe/sanitized renderers.
- Homepage section JSON columns should be decided in implementation planning; the default execution decision is to add them in the looper step, not the foundation step.
- First card families are `content_item`, `content_group`, and `contributor`.
- Card templates are globally reusable, sections choose a template key, and section-level semantic inline overrides are allowed through validated JSON.
- Public menu v1 is flat, with internal links, safe external links, public form action links, and a theme selector.
- Latest/search UX uses next/previous controls at the top, load-more at the bottom, and a custom Livewire filter drawer.
- Demo data includes production-safe settings seeding, optional demo seeders, and a demo cleanup Artisan command.
- Podcast cover images are imported from an operator-provided local asset source during demo seeding, with normalized public-storage names.
- The PodText logo has been copied to `public/images/podtext-logo.jpg` for Filament/admin and public branding.

## Global Guardrails

- Use JSON-first settings and typed readers/registries before rendering.
- Do not create settings-only models: no `CardTemplate`, `PublicMenu`, `PublicMenuItem`, `AboutPage`, `AboutPageBlock`, `TeamProfile`, `PublicFormDefinition`, `PublicDisplaySection`, or `PublicLooper`.
- The only approved model exception is `PublicFormSubmission`.
- Do not create `Podcast` or `Episode` models.
- Do not store raw Tailwind classes, CSS, SQL, PHP class names, Blade paths, iframe HTML, or unsafe HTML in JSON.
- Keep Prompt 11R custom public Livewire/Blade listings; do not reintroduce public Filament Tables for listing pages.
- Keep Prompt 11B `Author` contributor discovery.
- Keep Prompt 12 safe media and parse-only transcript rendering.
- Every public query must preserve published group, published item, and public/effective transcription constraints.

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

- `homepage_sections` JSON columns until Step 3 unless a narrower implementation prompt explicitly needs them earlier.

Tests:

- Settings defaults without rows.
- Invalid keys fall back safely.
- Raw class/CSS/SQL/PHP/Blade-looking values are rejected or ignored.

## Step 2: Transcription Publication Policy

Use:

- `docs/research/public-front-v2/07-transcription-publication-policy.md`
- `docs/phase-02/blueprints/public-front-v2/07-transcription-publication-policy-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/blueprint-results/07-transcription-publication-policy-plan.md`

Implement:

- Default `allow_multiple_published_transcriptions_per_item = false`.
- Public selection policy: featured/effective transcription only.
- Validation in Transcription Resource, ContentItem transcriptions relation manager, and imports.
- Explicit admin action to publish-and-replace/unpublish siblings.
- Public transcript viewer renders one public transcription when the setting is false.

Tests:

- Second published transcription is blocked without explicit replace action.
- Replace action updates siblings intentionally.
- Import row fails safely on policy violation.
- Public item page does not expose extra published transcription tabs when policy is false.

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

- Public form definitions in settings JSON.
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

## Step 7: Public Menu And Header

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
- Filament/admin and public branding should use `public/images/podtext-logo.jpg` where Filament panel APIs support it.

Tests:

- Default menu renders.
- Missing/disabled form items are skipped or disabled server-side.
- Non-HTTPS and `javascript:` URLs are rejected.
- Public panel navigation remains disabled.

## Step 8: About Page Content And Team Builder

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

## Step 9: Podcasts And Groups UX

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

- Use `public/images/podtext-logo.jpg` for admin and public brand/logo settings.

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

When resumed, Prompt 13 should account for the new public-form submission status counts, demo/default warnings, one-public-transcription conflicts, and public-front editorial metrics only where those metrics are real schema-backed states.

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
