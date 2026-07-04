# Blueprint Result: Public Front v2 Implementation Sequence

Source blueprint: `docs/phase-02/blueprints/public-front-v2/12-implementation-sequence-blueprint.md`

Generated with Laravel Boost context and Filament Blueprint planning docs.

## Commands

No implementation commands. This file sequences future implementation prompts.

Before any future prompt:

```bash
git status --short --branch
git log --oneline --decorate -15
```

Then use Laravel Boost:

- `application_info`
- `database_schema`
- `search_docs`

Use FilamentExamples MCP before Filament code.

## Recommended Sequence

1. Implement `01-json-settings-architecture-plan.md`.
2. Implement `02-card-template-builder-plan.md`.
3. Implement `03-public-display-sections-loopers-plan.md`.
4. Implement `09-latest-search-ux-plan.md`.
5. Implement `04-public-menu-header-manager-plan.md`.
6. Implement `06-public-forms-submissions-plan.md`.
7. Implement `05-about-page-content-team-builder-plan.md`.
8. Implement `10-podcasts-groups-ux-plan.md`.
9. Implement `08-contributors-transcribers-ux-plan.md`.
10. Implement `11-seeders-demo-data-plan.md`.
11. Implement `07-transcription-publication-policy-plan.md`.
12. Prompt 13 dashboard metrics only after user approval.

## Dependencies And Rationale

- JSON settings first because every later feature stores semantic JSON and needs readers/validators.
- Card template foundation before loopers/latest/groups/contributors because public display choices depend on templates.
- Loopers before latest because latest becomes a looper.
- Latest/search before menu/about because it repairs the existing public browsing workflow.
- Menu before forms/about so public links and form triggers have a shell.
- Forms before About only if request/volunteer actions are intended for the header; otherwise About can move earlier.
- Groups and contributors reuse template/display foundations.
- Seeder cleanup after JSON shape stabilizes.
- Transcription policy late because the default value is a product decision and may need dashboard conflict visibility.

## Cross-Cutting Models

Preserve internal names:

- `App\Models\ContentGroup`
- `App\Models\ContentItem`
- `App\Models\Author`
- `App\Models\Transcription`

Do not create:

- `Podcast`
- `Episode`
- `CardTemplate`
- `PublicMenu`
- `PublicMenuItem`
- `AboutPage`
- `AboutPageBlock`
- `TeamProfile`
- `PublicFormDefinition`
- `PublicDisplaySection`
- `PublicLooper`

Only candidate exception:

- `PublicFormSubmission`, if public submission persistence is approved.

## Resources And Pages

Each future prompt should update only the pages/resources named by its result plan.

Most common targets:

- `App\Filament\Pages\PublicContentSettings`
- `App\Filament\Resources\HomepageSections\HomepageSectionResource`
- `App\Filament\Public\Pages\BrowseContentGroups`
- `App\Filament\Public\Pages\ShowContentGroup`
- `App\Filament\Public\Pages\AboutPage`
- optional `App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource`

## Authorization

- Admin settings/resources: authenticated admin only.
- Public pages/components: guests, but only public-safe records.
- Public form submission: guests only for enabled form definitions and with server-side validation.

## Widgets

Do not add dashboard widgets in public-front v2 unless a later prompt explicitly expands scope. Prompt 13 owns dashboard metrics.

## Tests

Every implementation prompt must include focused Pest tests for:

- settings defaults.
- invalid JSON fallback.
- admin settings save/resource behavior.
- public visibility and draft hiding.
- Livewire URL state where relevant.
- security sanitization.
- no public Filament Table regression for listings.

## Security

Global public-front rules:

- No raw Tailwind classes in JSON.
- No raw CSS in JSON.
- No raw SQL in JSON.
- No arbitrary PHP class names in JSON.
- No arbitrary Blade paths in JSON.
- No unsafe HTML or iframe HTML.
- Media embeds remain URL-only and allowlisted.
- Public queries always use public visibility constraints.

## Quality Gate

For implementation prompts:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

For docs-only prompt updates:

```bash
git diff --check
git status --short
```

## Open Decisions Before Coding

- Default transcription publication policy.
- Whether `PublicFormSubmission` persistence is in v1.
- Whether public forms need notification, honeypot, rate limiting in v1.
- Whether `/groups` path is fixed while labels can say podcasts.
- Markdown-only vs RichEditor JSON for About content.
- Whether homepage section JSON columns are implemented with loopers or earlier.

## Final Report Checklist

Each future implementation report must state:

- files changed.
- tests added/updated.
- Boost tools used.
- FilamentExamples MCP access/result.
- FilaCheck result for Filament code.
- blueprint requirements implemented/already existed/deferred/not applicable/blocked.
- current git status.
