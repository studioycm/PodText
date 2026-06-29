<laravel-boost-guidelines>
=== .ai/import-export rules ===

# Import Export Guideline

## Purpose

Keep data portability on Filament-native import/export classes and already-created schema fields.

## Preferred architecture

Native Filament Importer/Exporter classes with portable reference keys and failed-row behavior.

## Do

- Extend existing import/export classes.
- Import transcripts into `Transcription`.
- Use category slugs/paths and typed tag slugs.
- Import/export only fields created by Prompts 07-09.
- Export large Markdown fields disabled by default.
- Preserve formula-injection protection.

## Do not

- Do not build custom CSV controllers.
- Do not export numeric database IDs as portable identifiers.
- Do not fetch remote media during imports.
- Do not write transcript imports to the legacy item transcript field.

## Testing rules

- Create/update imports.
- Relationship resolution.
- Failed rows.
- Export columns.
- Authorization.

## Security rules

- Validate all imported values.
- Keep failed-row download authorization.
- Escape spreadsheet formula values.

## FilaCheck / FilaCheck Pro notes

- Use `Filament\Actions\ImportAction`, `ExportAction`, and `ExportBulkAction`.
- Bulk export should deselect records after completion where supported.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields should auto-generate from title/name fields in admin forms but allow manual override.
- Technical import/export fields such as reference keys, provider IDs, external IDs, metadata, and file references must have helper text, hints, or descriptions.
- Date/date-time UI and import/export presentation should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Admin dashboard widgets should include available import/export editorial metrics when useful and avoid polling unless needed.

## Related active docs

- `docs/phase-02/import-export-revision-spec.md`
- `docs/phase-02/blueprints/10-import-export-blueprint.md`
- `docs/research/filament-examples-phase-02.md`

=== .ai/media-embeds rules ===

# Media Embeds Guideline

## Purpose

Keep media storage URL-only, safe to render, and available before import/export revisions.

## Preferred architecture

Store URLs/metadata on `ContentItem`; render through the app-owned Blade media component.

## Do

- Add media metadata foundation before import/export revision.
- Accept HTTPS URLs only.
- Use provider/host allowlists.
- Render original source link fallback.
- Keep metadata extraction explicit and admin-triggered.

## Do not

- Do not store raw iframe HTML.
- Do not fetch remote media during import.
- Do not render unapproved embed URLs.

## Testing rules

- Approved embed accepted/rendered.
- Unknown host rejected/fallback.
- HTTP rejected.
- Raw iframe HTML rejected.

## Security rules

- URL-only storage.
- Owned component controls iframe attributes.
- Sanitize displayed metadata.

## FilaCheck / FilaCheck Pro notes

- FileUpload fields, if later added, require accepted file types and max size.
- Avoid Blade Tailwind classes outside theme coverage.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields, where present, should auto-generate from title/name fields but allow manual override.
- Technical fields such as provider, external ID, external metadata, thumbnail URL, source URL, and direct media URL must have helper text, hints, or descriptions.
- Date/date-time UI should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Admin dashboard widgets should include available media warning metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/media-embed-spec.md`
- `docs/phase-02/blueprints/08-taxonomy-tags-pinning-settings-media-foundation-blueprint.md`
- `docs/phase-02/blueprints/12-public-item-page-media-parser-blueprint.md`
- `docs/research/filament-examples-phase-02.md`

=== .ai/public-panel rules ===

# Public Panel Guideline

## Purpose

Define public browsing/search behavior for guest-facing Filament panel pages.

## Preferred architecture

Guest Filament Public panel with custom Pages, class-based Livewire for server-driven state, and Blade components for reusable content presentation.

## Do

- Return `ContentItem` records for homepage/search/category/tag listings.
- Require published group, published item, and effective/main published transcription.
- Use Blade for cards, group badges, type labels, media embeds, and transcript output.
- Use Alpine only for local UI behavior.
- Keep search/sort/filter state in URL where practical.

## Do not

- Do not render public result cards as `Transcription` records.
- Do not expose admin Resource routes publicly.
- Do not duplicate persisted state in Alpine.

## Testing rules

- Guest access tests.
- Draft/no-effective-transcription exclusion tests.
- RTL marker tests where feasible.
- Livewire search/sort/filter tests.

## Security rules

- Public queries must include publication/effective transcription constraints.
- Public Markdown must use safe renderer.
- Media embeds must use the owned component.

## FilaCheck / FilaCheck Pro notes

- Avoid table/card closures that query relationships.
- Ensure searchable text columns exist.
- Avoid deprecated Filament methods/namespaces.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields, where present in admin surfaces feeding public pages, should auto-generate from title/name fields but allow manual override.
- Technical fields must have helper text, hints, or descriptions in admin forms.
- Date/date-time UI should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Admin dashboard widgets should include available public-content editorial metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/public-panel-ux-spec.md`
- `docs/phase-02/search-and-filters-spec.md`
- `docs/phase-02/blueprints/11-public-homepage-search-blueprint.md`
- `docs/research/filament-examples-phase-02.md`

=== .ai/search-filters rules ===

# Search and Filters Guideline

## Purpose

Keep public search/filter/sort behavior explicit, URL-aware, and scoped to public `ContentItem` records.

## Preferred architecture

Filament Table inside a public Livewire component, rendered as item cards or rows.

## Do

- Default search: item title, group title, enabled tags, categories.
- Use explicit filters for category, tag, group, author, date ranges, duration, and provider.
- Add active indicators for custom filters.
- Persist important state in URL.
- Implement all required sort modes.

## Do not

- Do not make transcript body search the default live search.
- Do not let disabled tags appear publicly.
- Do not lock search pages to pinned-first order when the user selected another sort.

## Testing rules

- Search field coverage.
- Filter and sort order tests.
- URL state tests.
- Disabled tag exclusion.

## Security rules

- Search query must use public item scope.
- Avoid raw SQL with user input.

## FilaCheck / FilaCheck Pro notes

- Tables need searchable columns.
- Custom filters need indicators.
- Relationship filters should be searchable/preloaded where record count can grow.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields, where present in admin surfaces feeding public filters, should auto-generate from title/name fields but allow manual override.
- Technical fields must have helper text, hints, or descriptions in admin forms.
- Date/date-time UI should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, sort labels, and date labels.
- Admin dashboard widgets should include available search/filter editorial metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/search-and-filters-spec.md`
- `docs/phase-02/blueprints/11-public-homepage-search-blueprint.md`
- `docs/research/filament-examples-phase-02.md`

=== .ai/settings-dashboard rules ===

# Settings and Dashboard Guideline

## Purpose

Define durable rules for global public settings, homepage sections, and editorial dashboard widgets.

## Preferred architecture

Spatie Settings for global options, normal database records for ordered homepage sections, simple editorial Filament widgets. Spatie Settings package usage is approved for Phase 02 implementation; do not ask for package approval again when Prompt 08 reaches this work.

## Do

- Use typed settings classes.
- Use homepage section records for visible ordered sections.
- Keep dashboard widgets editorial.
- Link widgets to Filament Resources through Resource URL helpers.
- Include available editorial metrics as dashboard widgets and extend them as later schema becomes available.

## Do not

- Do not add analytics/search logging.
- Do not add observability dashboards or retry managers.
- Do not use item pinning as settings storage.

## Testing rules

- Settings defaults and save behavior.
- Homepage section visibility/order.
- Widget render/count tests.
- Admin-only access.

## Security rules

- Settings/admin widgets require authenticated admin panel access.
- Public section queries must use public item visibility rules.

## FilaCheck / FilaCheck Pro notes

- Avoid default polling in widgets unless needed.
- Use searchable table columns and useful warning filters.
- Use enum icons instead of string icons.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields should auto-generate from title/name fields but allow manual override.
- Technical settings, homepage section targets, pin fields, and metric filters must have helper text, hints, or descriptions.
- Date/date-time UI should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Dashboard widgets should include available editorial metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/homepage-settings-spec.md`
- `docs/phase-02/dashboard-metrics-spec.md`
- `docs/phase-02/blueprints/08-taxonomy-tags-pinning-settings-media-foundation-blueprint.md`
- `docs/phase-02/blueprints/13-dashboard-metrics-blueprint.md`
- `docs/research/filament-examples-phase-02.md`

=== .ai/taxonomy-tags rules ===

# Taxonomy and Tags Guideline

## Purpose

Separate custom hierarchical categories from Spatie flat content tags.

## Preferred architecture

Custom hierarchical `Category` model plus Spatie Laravel Tags with the Filament Spatie Tags plugin, scoped to type `content`. Spatie tag package usage is approved for Phase 02 implementation; do not ask for package approval again when Prompt 08 reaches this work.

## Do

- Use categories for hierarchy.
- Use Spatie taggables for tags.
- Enable tags before public display.
- Include group category inheritance in public item filters.
- Include descendant categories when filtering by a parent.

## Do not

- Do not create a duplicate custom tag pivot when using Spatie tags.
- Do not use unscoped free-form tag inputs.
- Do not make tags hierarchical.

## Testing rules

- Category hierarchy.
- Group-to-item inheritance.
- Descendant filtering.
- Tag type scoping.
- Disabled tag hiding.

## Security rules

- Disabled tags are admin-only.
- Public category/tag pages return public `ContentItem` records only.

## FilaCheck / FilaCheck Pro notes

- Relationship selects should be searchable/preloaded.
- Category/tag tables need searchable name/slug columns and useful filters.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields should auto-generate from category/tag title/name fields but allow manual override.
- Technical fields must have helper text, hints, or descriptions.
- Date/date-time UI should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Admin dashboard widgets should include available category/tag metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/taxonomy-tags-spec.md`
- `docs/phase-02/blueprints/08-taxonomy-tags-pinning-settings-media-foundation-blueprint.md`
- `docs/research/filament-examples-phase-02.md`

=== .ai/tooling-quality rules ===

# Tooling Quality Guideline

## Purpose

Keep AI/tooling quality gates consistent across planning and implementation tasks.

## Preferred architecture

Every implementation prompt uses Boost where available, reads its blueprint, checks FilamentExamples for relevant code patterns, and runs the full quality gate.

## Do

- Retry Laravel Boost MCP tools before implementation.
- Read the relevant blueprint first.
- Use FilamentExamples MCP before Filament code.
- Run full final quality gate.
- Record FilaCheck/FilaCheck Pro output.
- Preserve cross-cutting form, locale, and dashboard requirements from active specs/guidelines.
- Use current Filament 5 relation-manager APIs for relation manager work.

## Do not

- Do not claim Boost was used if MCP calls fail.
- Do not run `filacheck --fix` without explicit approval.
- Do not write secrets, tokens, licenses, Composer auth, MCP headers, or machine paths to tracked files.

## Testing rules

- Each implementation prompt must add/update Pest tests.
- Final implementation gate:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Security rules

- Review diffs for secrets before final report.
- Keep `.env`, MCP config, Composer auth, and license files untouched.

## FilaCheck / FilaCheck Pro notes

- Treat remaining violations as blockers in implementation prompts.
- Local iteration may use `vendor/bin/filacheck --dirty`.
- Final verification uses full `vendor/bin/filacheck`.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.
- If a prompt uses combined relation tabs with content, use the official Filament method names for the installed version.
- Prompt 09 final reports must state whether combined tabs, relation manager badges, redirect behavior, and create-another behavior were implemented.

## Cross-cutting UI rules

- Slug fields should auto-generate from title/name fields but allow manual override.
- Technical fields must have helper text, hints, or descriptions.
- Date/date-time UI should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Admin dashboard widgets should include available editorial metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/research/filament-examples-phase-02.md`

=== .ai/transcriptions rules ===

# Transcriptions Guideline

## Purpose

Keep transcript content in `Transcription` child records while public listings remain `ContentItem` based.

## Preferred architecture

`Transcription` child model with canonical Markdown transcript content. Public listings remain `ContentItem` based.

## Do

- Add `ContentItem::transcriptions()`.
- Add `Transcription::author()`.
- Implement effective/main transcription resolution.
- Hide items without an effective/main published transcription.
- Keep parser output derived.
- When admin management is implemented, make transcript body management available from the owning item admin page through a relation manager.
- Keep standalone `TranscriptionResource` useful for global transcript search, filtering, and maintenance.

## Do not

- Do not keep writing new transcript content to legacy `content_items.transcript_markdown`.
- Do not reintroduce the legacy item transcript field in item admin forms.
- Do not expose draft transcriptions publicly.
- Do not pin transcriptions.
- Do not use a Repeater for full transcript Markdown editing.

## Admin management rules

- Item-scoped transcript editing should prefer `ContentItemResource`'s transcriptions relation manager.
- The relation manager should create/edit `Transcription` records in the owning item context and should not expose `content_item_id` as a normal form field.
- Standalone `TranscriptionResource` is for global discovery and maintenance, not the only transcript editing path.
- If transcript management later needs a larger workspace, a dedicated relation page is a future option, not the default Prompt 09 approach.

## Testing rules

- Relationships and casts.
- Backfill.
- Effective/main resolution.
- Same-item featured validation.
- Draft hiding.
- XSS regression.

## Security rules

- Render Markdown through `SafeMarkdownRenderer`.
- Validate featured transcription ownership and publication state.

## FilaCheck / FilaCheck Pro notes

- Enum columns shown in Filament should use label/color contracts.
- Avoid N+1 when listing effective transcription metadata.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields, where present, should auto-generate from title/name fields but allow manual override.
- Technical fields such as reference keys, featured transcription selectors, language codes, parser JSON, and derived counts must have helper text, hints, or descriptions.
- Date/date-time UI should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Admin dashboard widgets should include available editorial transcription metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/transcriptions-model-spec.md`
- `docs/phase-02/blueprints/07-transcriptions-model-revision-blueprint.md`
- `docs/research/filament-examples-phase-02.md`

=== .ai/viewer-studio rules ===

# Viewer and Studio Guideline

## Purpose

Separate Prompt 12 parse-only public viewer work from Prompt 14 future sync/studio planning.

## Preferred architecture

Prompt 12 implements parse-only public viewer behavior. Prompt 14 plans future sync/studio only.

## Do

- Parse timestamps/speakers from `Transcription::transcript_markdown`.
- Keep Markdown canonical.
- Use Alpine/localStorage for show/hide preferences.
- Plan future studio prerequisites before implementation.

## Do not

- Do not implement player sync in Prompt 12.
- Do not implement studio UI in Prompt 14.
- Do not add autosave without failure/recovery design.

## Testing rules

- Parser single-line and multi-line formats.
- Fallback safe Markdown.
- Draft transcription hidden.
- Viewer controls do not persist server state.

## Security rules

- Parser output must be escaped/sanitized.
- Timestamp anchors must not expose unpublished transcripts.

## FilaCheck / FilaCheck Pro notes

- Avoid Blade query work.
- Keep Livewire component state explicit and tested.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields, where present in related admin forms, should auto-generate from title/name fields but allow manual override.
- Technical fields must have helper text, hints, or descriptions in admin forms.
- Date/date-time and timestamp UI should use Hebrew/Israel locale behavior where dates are shown: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Admin dashboard widgets should include available viewer/transcription editorial metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/transcript-viewer-and-studio-future-plan.md`
- `docs/phase-02/blueprints/12-public-item-page-media-parser-blueprint.md`
- `docs/phase-02/blueprints/14-viewer-studio-future-plan-blueprint.md`
- `docs/research/filament-examples-phase-02.md`

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- filament/filament (FILAMENT) - v5
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== filament/filament rules ===

## Filament

- Filament is a Laravel UI framework built on Livewire, Alpine.js, and Tailwind CSS. UIs are defined in PHP via fluent, chainable components. Follow existing conventions in this app.
- Use the `search-docs` tool for official documentation on Artisan commands, code examples, testing, relationships, and idiomatic practices. If `search-docs` is unavailable, refer to https://filamentphp.com/docs.

### Artisan

- Always use Filament-specific Artisan commands to create files. Find available commands with the `list-artisan-commands` tool, or run `php artisan --help`.
- Inspect required options before running, and always pass `--no-interaction`.

### Patterns

Always use static `make()` methods to initialize components. Most configuration methods accept a `Closure` for dynamic values.

Use `Get $get` to read other form field values for conditional logic:

<code-snippet name="Conditional form field visibility" lang="php">
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('type')
    ->options(CompanyType::class)
    ->required()
    ->live(),

TextInput::make('company_name')
    ->required()
    ->visible(fn (Get $get): bool => $get('type') === 'business'),

</code-snippet>

Use `Set $set` inside `->afterStateUpdated()` on a `->live()` field to mutate another field reactively. Prefer `->live(onBlur: true)` on text inputs to avoid per-keystroke updates:

<code-snippet name="Reactive field update" lang="php">
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

TextInput::make('title')
    ->required()
    ->live(onBlur: true)
    ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
        'slug',
        Str::slug($state ?? ''),
    )),

TextInput::make('slug')
    ->required(),

</code-snippet>

Compose layout by nesting `Section` and `Grid`. Children need explicit `->columnSpan()` or `->columnSpanFull()`:

<code-snippet name="Section and Grid layout" lang="php">
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

Section::make('Details')
    ->schema([
        Grid::make(2)->schema([
            TextInput::make('first_name')
                ->columnSpan(1),
            TextInput::make('last_name')
                ->columnSpan(1),
            TextInput::make('bio')
                ->columnSpanFull(),
        ]),
    ]),

</code-snippet>

Use `Repeater` for inline `HasMany` management. `->relationship()` with no args binds to the relationship matching the field name:

<code-snippet name="Repeater for HasMany" lang="php">
use Filament\Forms\Components\Repeater;

Repeater::make('qualifications')
    ->relationship()
    ->schema([
        TextInput::make('institution')
            ->required(),
        TextInput::make('qualification')
            ->required(),
    ])
    ->columns(2),

</code-snippet>

Use `state()` with a `Closure` to compute derived column values:

<code-snippet name="Computed table column value" lang="php">
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),

</code-snippet>

Use `SelectFilter` for enum or relationship filters, and `Filter` with a `->query()` closure for custom logic:

<code-snippet name="Table filters" lang="php">
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

SelectFilter::make('status')
    ->options(UserStatus::class),

SelectFilter::make('author')
    ->relationship('author', 'name'),

Filter::make('verified')
    ->query(fn (Builder $query) => $query->whereNotNull('email_verified_at')),

</code-snippet>

Actions are buttons that encapsulate optional modal forms and behavior:

<code-snippet name="Action with modal form" lang="php">
use Filament\Actions\Action;

Action::make('updateEmail')
    ->schema([
        TextInput::make('email')
            ->email()
            ->required(),
    ])
    ->action(fn (array $data, User $record) => $record->update($data)),

</code-snippet>

### Testing

Testing setup (requires `pestphp/pest-plugin-livewire` in `composer.json`):

- Always call `$this->actingAs(User::factory()->create())` before testing panel functionality.
- For edit pages, pass `['record' => $user->id]`, use `->call('save')` (not `->call('create')`), and do not assert `->assertRedirect()` (edit pages do not redirect after save).

<code-snippet name="Table test" lang="php">
use function Pest\Livewire\livewire;

livewire(ListUsers::class)
    ->assertCanSeeTableRecords($users)
    ->searchTable($users->first()->name)
    ->assertCanSeeTableRecords($users->take(1))
    ->assertCanNotSeeTableRecords($users->skip(1));

</code-snippet>

<code-snippet name="Create resource test" lang="php">
use function Pest\Laravel\assertDatabaseHas;

livewire(CreateUser::class)
    ->fillForm([
        'name' => 'Test',
        'email' => 'test@example.com',
    ])
    ->call('create')
    ->assertNotified()
    ->assertHasNoFormErrors()
    ->assertRedirect();

assertDatabaseHas(User::class, [
    'name' => 'Test',
    'email' => 'test@example.com',
]);

</code-snippet>

<code-snippet name="Edit resource test" lang="php">
livewire(EditUser::class, ['record' => $user->id])
    ->fillForm(['name' => 'Updated'])
    ->call('save')
    ->assertNotified()
    ->assertHasNoFormErrors();

assertDatabaseHas(User::class, [
    'id' => $user->id,
    'name' => 'Updated',
]);

</code-snippet>

<code-snippet name="Testing validation" lang="php">
livewire(CreateUser::class)
    ->fillForm([
        'name' => null,
        'email' => 'invalid-email',
    ])
    ->call('create')
    ->assertHasFormErrors([
        'name' => 'required',
        'email' => 'email',
    ])
    ->assertNotNotified();

</code-snippet>

Use `->callAction(DeleteAction::class)` for page actions, or `->callAction(TestAction::make('name')->table($record))` for table actions:

<code-snippet name="Calling actions" lang="php">
use Filament\Actions\Testing\TestAction;

livewire(ListUsers::class)
    ->callAction(TestAction::make('promote')->table($user), [
        'role' => 'admin',
    ])
    ->assertNotified();

</code-snippet>

### Correct Namespaces

- Form fields (`TextInput`, `Select`, `Repeater`, etc.): `Filament\Forms\Components\`
- Infolist entries (`TextEntry`, `IconEntry`, etc.): `Filament\Infolists\Components\`
- Layout components (`Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.): `Filament\Schemas\Components\`
- Schema utilities (`Get`, `Set`, etc.): `Filament\Schemas\Components\Utilities\`
- Table columns (`TextColumn`, `IconColumn`, etc.): `Filament\Tables\Columns\`
- Table filters (`SelectFilter`, `Filter`, etc.): `Filament\Tables\Filters\`
- Actions (`DeleteAction`, `CreateAction`, etc.): `Filament\Actions\`. Never use `Filament\Tables\Actions\`, `Filament\Forms\Actions\`, or any other sub-namespace for actions.
- Icons: `Filament\Support\Icons\Heroicon` enum (e.g., `Heroicon::PencilSquare`)

### Common Mistakes

- **Never assume public file visibility.** File visibility is `private` by default. Always use `->visibility('public')` when public access is needed.
- **Never assume full-width layout.** `Grid`, `Section`, `Fieldset`, and `Repeater` do not span all columns by default.
- **Use `Select::make('author_id')->relationship('author', 'name')` for BelongsTo fields.** `BelongsToSelect` does not exist in v4.
- **`Repeater` uses `->schema()`, not `->fields()`.**
- **Never add `->dehydrated(false)` to fields that need to be saved.** It strips the value from form state before `->action()` or the save handler runs. Only use it for helper/UI-only fields.
- **Use correct property types when overriding `Page`, `Resource`, and `Widget` properties.** These properties have union types or changed modifiers that must be preserved:
  - `$navigationIcon`: `protected static string | BackedEnum | null` (not `?string`)
  - `$navigationGroup`: `protected static string | UnitEnum | null` (not `?string`)
  - `$view`: `protected string` (not `protected static string`) on `Page` and `Widget` classes

=== filament/blueprint rules ===

## Filament Blueprint

You are writing Filament v5 implementation plans. Plans must be specific enough
that an implementing agent can write code without making decisions.

**Start here**: Read
`/vendor/filament/blueprint/resources/markdown/planning/overview.md` for plan format,
required sections, and what to clarify with the user before planning.

=== laraveldaily/filacheck rules ===

## laraveldaily/filacheck

- After you have created/modified any files in `app/Filament` folder, you must run `vendor/bin/filacheck --fix`, to ensure there is no deprecated Filament code. Reported not fixed issues MUST be fixed before continuing.

=== laraveldaily/filacheck-pro rules ===

## laraveldaily/filacheck-pro

- After creating or modifying any files under `app/Filament/`, run `vendor/bin/filacheck --fix --dirty` to auto-fix deprecated Filament code and flag performance, security, UX, and best-practice issues from FilaCheck-Pro. `--dirty` limits the scan to files with uncommitted git changes — fastest after a targeted edit.
- Exit code 0 means no remaining issues; exit code 1 means violations remain after `--fix`. Any reported violation that `--fix` could not resolve MUST be addressed (consult the rule's suggestion message) before continuing the task.

</laravel-boost-guidelines>
