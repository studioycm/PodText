# Public Front v2 Step 10 Contributors and Top Transcribers MCP Research

## Purpose

Record the Step 10 Laravel Boost and FilamentExamples MCP research before implementation. This prompt implements contributor directory refinements, the top-transcribers homepage selector/preview, contributor page refinements, and contributor settings.

## Access Level

- Laravel Boost MCP was available and used.
- FilamentExamples exposed `search_examples` only.
- No separate source/read/fetch/detail tool was exposed, so FilamentExamples access was search/snippet access only.
- No tokens, headers, or private access values were written here.

## Laravel Boost Usage

Boost tools used:

- `application_info`
- `database_schema`
- `search_docs`

Installed-version docs searched before implementation:

- Livewire URL state, pagination, `WithPagination`, named paginator behavior, and reset behavior.
- Filament settings form tabs, sections, fieldsets, toggle/select controls, repeaters, and full-width collapsible sections.
- Laravel Eloquent aggregate subqueries, relationship existence queries, `withCount`-style patterns, and pagination.
- Pest and Livewire component testing APIs.
- Blade component attribute merge/class behavior.

## Query Batches

Batch 1 - contributor/directory UX:

- `Livewire directory cards`
- `card preview list`
- `selected preview state`
- `search inside preview`
- `public profile cards`

Batch 2 - top/ranked sections:

- `top users section`
- `ranking cards`
- `homepage dynamic sections`
- `horizontal cards selector`
- `section preview cards`

Batch 3 - pagination/grid patterns:

- `Livewire pagination cards`
- `page size selector`
- `responsive card grid`
- `grid controls`
- `public card layout`

Batch 4 - settings/admin controls:

- `settings page tabs`
- `repeater settings cards`
- `ToggleButtons settings`
- `section form schema`
- `Builder settings preview`

Batch 5 - surrounding best practice:

- `public Livewire page`
- `custom page layout`
- `card grid contentGrid`
- `recordUrl false`
- `Filament public page`

Refined second pass:

- `paginationPageOptions recordUrl false`
- `leaderboard aggregate query`
- `Livewire sidebar preview`
- `homepage sections contentGrid`
- `selectedDoctor Url page`

## Useful Examples

### Public Table / Card Grid Examples

Example:
`v4/tables/table-as-grid-with-cards/app/Filament/Resources/Users/UserResource.php`

- File/class/snippet found: Filament Resource table using card-grid/table `contentGrid`, explicit pagination page options, and disabled direct record URLs.
- Pattern to copy: semantic control over page-size choices and grid density.
- Pattern to avoid: public Filament Table markup and Resource-backed public browsing.
- PodText adaptation: keep custom Livewire/Blade public UI and use this only as validation that page-size/grid controls should be explicit and finite.
- Access: snippet-only through `search_examples`.

Example:
`v4/tables/public-products-table/app/Livewire/Products.php`

- File/class/snippet found: public-facing Livewire state around product listing controls.
- Pattern to copy: component-owned search/filter state.
- Pattern to avoid: public Filament Table rendering.
- PodText adaptation: Livewire owns contributor selection, preview search, sort, and page sizes; Blade renders cards.
- Access: snippet-only through `search_examples`.

### Selected Preview / Sidebar Examples

Example:
`v4/forms/livewire-component-in-editform-sidebar/app/Livewire/TicketSidebar.php`

- File/class/snippet found: Livewire preview/sidebar component receives a record and renders current state plus actions.
- Pattern to copy: focused Livewire component for preview content instead of Alpine-owned state.
- Pattern to avoid: admin-only record actions or notification workflows.
- PodText adaptation: `TopTranscribersSection` owns selected contributor and preview page size while rendering public-safe content only.
- Access: snippet-only through `search_examples`.

Example:
`v4/forms/livewire-component-in-editform-sidebar/app/Filament/Resources/Tickets/Pages/EditTicket.php`

- File/class/snippet found: custom page layout placing a form/content area beside a Livewire sidebar.
- Pattern to copy: separation between primary content and focused Livewire preview component.
- Pattern to avoid: Resource edit-page structure and admin-only assumptions.
- PodText adaptation: top-transcriber selector and preview are separate regions inside the homepage section.
- Access: snippet-only through `search_examples`.

### URL-Backed Custom Page Examples

Example:
`v4/full-projects/student-or-user-attendance/app/Filament/Pages/Attendance.php`

- File/class/snippet found: Filament Page using Livewire `#[Url]` state and custom Blade layout.
- Pattern to copy: URL-backed state can live directly on a custom Livewire/page surface.
- Pattern to avoid: table markup and public `User` exposure.
- PodText adaptation: contributor directory keeps URL-backed search, selected contributor, sort, and page-size state; full contributor page search/sort is URL-backed.
- Access: snippet-only through `search_examples`.

Example:
`v4/full-projects/schedule-for-doctors/app/Filament/Pages/ManageDoctorSchedule.php`

- File/class/snippet found: `#[Url]` selected-record state and selected preview object on a custom page.
- Pattern to copy: normalize selected state and reload current entity from the database.
- Pattern to avoid: exposing application users publicly.
- PodText adaptation: selected contributor remains an `Author` id, resolved through public contributor constraints only.
- Access: snippet-only through `search_examples`.

### Homepage Sections / Settings Examples

Example:
`v4/full-projects/manage-homepage-sections/...`

- File/class/snippet found: homepage section management with `type`, ordering, visibility, and limit controls.
- Pattern to copy: homepage section source types should stay finite and settings-driven.
- Pattern to avoid: creating a new mini-framework or generic CMS/page system.
- PodText adaptation: keep `HomepageSection` and Step 4 section resolver; swap top-transcriber rendering to a focused component.
- Access: snippet-only through `search_examples`.

Example:
`v4/forms/large-employee-form-with-sections/...`

- File/class/snippet found: full-width sections with nested fields for complex forms.
- Pattern to copy: full-width collapsible sections with compact fieldsets inside.
- Pattern to avoid: cramped top-level columns.
- PodText adaptation: add a full-width Contributors settings tab with identity, directory, top-transcribers, and card/grid fieldsets.
- Access: snippet-only through `search_examples`.

Example:
`v4/forms/repeater-five-advanced-use-cases/...`

- File/class/snippet found: repeaters/builders with nested configuration groups and preview-like field organization.
- Pattern to copy: group complex JSON settings into semantic fieldsets.
- Pattern to avoid: arbitrary Blade paths/classes or raw rendering config.
- PodText adaptation: use fixed select/toggle/text input controls for finite contributor settings.
- Access: snippet-only through `search_examples`.

### Aggregate / Ranking Examples

Example:
quiz/leaderboard widget snippets returned for top user/leaderboard terms.

- File/class/snippet found: aggregate query ranking records by count/sum and limiting to top results.
- Pattern to copy: centralize aggregate ordering and tie-breakers.
- Pattern to avoid: widget-specific polling or public `User` exposure.
- PodText adaptation: `PublicContributorDiscovery` remains the aggregate boundary and counts public published transcriptions only.
- Access: snippet-only through `search_examples`.

## Decisions From Research

- Use `Author` only; do not expose `User`.
- Keep contributor directory state in Livewire, URL-backed where practical.
- Add a focused `TopTranscribersSection` Livewire component for selector/preview pagination.
- Add JSON-first `contributors_page` settings with finite semantic tokens only.
- Keep public item cards as `ContentItem` cards.
- Group multiple transcriptions for the same author/item under one item card while counting each published transcription.
- Reuse Step 9R podcast grid/page-size patterns where they fit, without introducing public Filament Tables.
