# Public Front v2 JSON Settings Research Final Report

## Status

This was a research, blueprint, and planning task only. No application features were implemented. No PHP, Blade, migration, test, config, package, or app code files were edited.

Latest local HEAD before this docs task: `aac9db9`.

## Current State Verified

- Prompt 12 is complete.
- Prompt 13 dashboard metrics is formally next but has not started.
- `docs/phase-02/current-project-state.md` remains the single source of truth for rolling prompt progress.
- Prompt 11R replaced public Filament Table listing with custom Livewire state and Blade card grids/rows.
- Prompt 11B added contributor discovery using `Author`.
- Prompt 12 added public item page, safe media behavior, and parse-only transcript viewer.

## JSON-First Decision

The recommended architecture is strict JSON-first configuration:

- Site-level public-front settings belong in Spatie Settings arrays/JSON payloads.
- Section-level display/source/pagination settings belong on the existing `HomepageSection` as JSON config only when they are section-specific.
- New settings-only models such as `CardTemplate`, `PublicMenuItem`, `AboutPageBlock`, `TeamProfile`, `PublicFormDefinition`, `PublicDisplaySection`, and `PublicLooper` are rejected by default.
- Runtime rendering must use typed registries/readers/validators. Public Blade/Livewire components should consume normalized safe config, not raw JSON.
- JSON may store semantic keys only. It must not store raw Tailwind classes, raw CSS, raw SQL, arbitrary PHP class names, arbitrary Blade paths, iframe HTML, or unsafe rich HTML.

The only planned exception candidate is `PublicFormSubmission`, because submissions are transactional user-generated records that may need queryable status, review, timestamps, and admin management.

## Boost Tools Used

- `application_info`: confirmed PHP 8.4, Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, Tailwind CSS 4.3.2, SQLite.
- `database_schema`: confirmed `settings.payload` and current `homepage_sections`, content, transcription, taxonomy, and settings schema.
- `search_docs`: used for Laravel validation/casts/seeders/testing, Filament Builder/Repeater/RichEditor/MarkdownEditor/FileUpload/Actions/Tables/Pages, Livewire URL/pagination, Pest/Laravel testing, and Tailwind layout behavior.

## FilamentExamples MCP Examples Used

Access level: `search_examples` snippets only. No fetch/read/detail/source tool was exposed.

Examples observed:

- `v4/full-projects/hotel-management-bookings/app/Filament/Hotel/Pages/MyHotel.php`
- `v4/full-projects/hotel-management-bookings/app/Filament/Booking/Pages/FindHotel.php`
- `v4/full-projects/hotel-management-bookings/app/Providers/Filament/HotelPanelProvider.php`
- `v4/full-projects/hotel-management-bookings/app/Providers/Filament/BookingPanelProvider.php`
- `v4/forms/edit-profile-custom-forms/app/Filament/Pages/EditProfile.php`
- `v4/full-projects/cms-blog-system-shield/app/Filament/Resources/Posts/Schemas/PostForm.php`
- `v4/full-projects/schedule-for-doctors/app/Filament/Resources/Doctors/Schemas/DoctorForm.php`
- `v4/forms/livewire-component-in-editform-sidebar/resources/views/livewire/ticket-sidebar.blade.php`
- `v4/full-projects/box-score-form/app/Filament/Resources/Tournaments/Pages/ManagePlayerStats.php`
- `v4/full-projects/box-score-form/app/Filament/Resources/Tournaments/RelationManagers/MatchesRelationManager.php`
- `v4/tables/table-as-grid-with-cards/app/Filament/Resources/Users/UserResource.php`
- `v4/tables/public-products-table/app/Livewire/Products.php`

## LaravelDaily/GitHub Sources Inspected

- `https://github.com/LaravelDaily/Filament-Menu-Builder-Demo`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/config/filament-menu-builder.php`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/database/migrations/2026_02_16_061744_create_menus_table.php`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/database/migrations/2026_02_16_061745_create_menu_items_table.php`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/resources/views/components/layouts/app.blade.php`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/routes/web.php`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/app/Providers/Filament/AdminPanelProvider.php`
- `https://laraveldaily.com/post/filament-appointment-booking-re-use-admin-panel-form-on-public-page`
- `https://filamentexamples.com/`

The LaravelDaily menu demo is useful UI inspiration, but it is model/table backed and stores class-related fields. PodText should avoid that architecture for public menu settings.

`https://github.com/studioycm/FilamentExamples` returned 404 through the public GitHub API, so direct source access was unavailable.

## Research Topics Completed

1. JSON settings/configuration architecture.
2. Card template builder.
3. Homepage sections and generalized loopers/query displays.
4. Public menu/header manager.
5. About page content builder and team profiles.
6. Configurable public forms and form submissions.
7. Main transcription publication policy setting.
8. Public contributor/transcriber UX refinements.
9. Latest/search UX refinements.
10. Podcasts/groups page and group-page refinements.
11. Seeders/demo-data strategy.
12. External/MCP source index.

## Files Created

Research:

- `docs/research/public-front-v2/01-json-settings-architecture.md`
- `docs/research/public-front-v2/02-card-template-builder.md`
- `docs/research/public-front-v2/03-public-display-sections-loopers.md`
- `docs/research/public-front-v2/04-public-menu-header-manager.md`
- `docs/research/public-front-v2/05-about-page-content-team-builder.md`
- `docs/research/public-front-v2/06-public-forms-submissions.md`
- `docs/research/public-front-v2/07-transcription-publication-policy.md`
- `docs/research/public-front-v2/08-contributors-transcribers-ux.md`
- `docs/research/public-front-v2/09-latest-search-ux.md`
- `docs/research/public-front-v2/10-podcasts-groups-ux.md`
- `docs/research/public-front-v2/11-seeders-demo-data.md`
- `docs/research/public-front-v2/12-povilas-filamentexamples-source-index.md`
- `docs/research/public-front-v2/index-and-agent-usage-guide.md`

Blueprints:

- `docs/phase-02/blueprints/public-front-v2/01-json-settings-architecture-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/02-card-template-builder-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/03-public-display-sections-loopers-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/04-public-menu-header-manager-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/05-about-page-content-team-builder-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/06-public-forms-submissions-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/07-transcription-publication-policy-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/08-contributors-transcribers-ux-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/09-latest-search-ux-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/10-podcasts-groups-ux-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/11-seeders-demo-data-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/12-implementation-sequence-blueprint.md`

Summary:

- `docs/phase-02/public-front-v2-final-report.md`
- `docs/phase-02/public-front-v2-open-questions.md`
- `docs/phase-02/public-front-v2-agent-usage-index.md`

## Implementation-Ready Topics

- JSON settings architecture.
- Card template builder foundation.
- Public display sections/loopers foundation.
- Latest/search UX repair, after the card/looper foundation.
- Public menu/header manager.
- About page content/team builder, after choosing Markdown vs RichEditor JSON.
- Seeders/default settings cleanup.

## Topics Requiring User Answers

- Whether `PublicFormSubmission` persistence is in scope for v1.
- Whether public form notifications/rate limiting/honeypot are required in v1.
- Default value for multiple published transcriptions per item.
- Whether `/groups` remains the permanent path while labels may say podcasts.
- Whether About content supports Markdown only or RichEditor JSON plus safe renderer.
- Whether homepage section JSON columns start in the architecture step or wait for loopers.

## Recommended Implementation Order

1. JSON settings architecture and renderer/validator conventions.
2. Card template builder foundation.
3. Public display sections / loopers.
4. Latest/search UX repair.
5. Public menu/header manager.
6. Configurable public forms/submissions.
7. About page content/team builder.
8. Podcasts/group page refinements.
9. Transcriber/top-transcriber refinements.
10. Seeder cleanup.
11. Transcription publication policy setting.
12. Prompt 13 dashboard metrics only after user approves.

## Prompt 13 Recommendation

Do not start Prompt 13 until Yoni reviews and approves this public-front v2 plan or explicitly chooses to proceed with Prompt 13 first.

## Validation

- `git diff --check`: passed.
- `git status --short` before commit: only the requested new Markdown files under `docs/research/public-front-v2/`, `docs/phase-02/blueprints/public-front-v2/`, and the three public-front v2 summary files were present.

## Commit

Commit hash is reported in the chat final after the docs commit is created.
