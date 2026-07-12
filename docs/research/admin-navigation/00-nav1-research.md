# NAV1 Admin Navigation Research

Date: 2026-07-12

## Scope

NAV1 restructures the admin navigation immediately after IMG-B. It also records a
suite timing diagnosis from the final profiled test run, but timing fixes are limited
to zero-risk test-env configuration only if a missing optimization is proven.

## Local Preflight

- `git status --short --branch` reported `## main...origin/main [ahead 1]` with no
  working-tree dirt.
- Recent history includes the expected IMG-B implementation commit:
  `8c590ab feat: add episode images, media guards, and content images export`.
- The NAV1 prompt commit is present at `HEAD`: `eb49e0d docs: add NAV1 navigation
  restructure prompt`.
- The prompt has not already started; no `docs/research/admin-navigation` files or
  `docs/phase-02/admin-navigation-nav1-handoff.md` existed before this run.
- `rg` is not installed in this local environment, so repository searches used
  `git ls-files`, `grep`, and `find`.

## Boost Findings

Tools used: `application_info`, `database_schema`, and `search_docs`.

- Boost confirmed the installed stack: PHP 8.4, Laravel 13.19.0, Filament 5.6.7,
  Livewire 4.3.3, Pest 4.7.4, Tailwind CSS 4.3.2, Horizon 5.47.2.
- The database schema already includes `public_form_submissions`, and the repository
  already has `App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource`.
  No new form-submissions resource is needed.
- `public_form_submissions.status` is indexed and defaults to `new`, so the navigation
  badge should count `PublicFormSubmissionStatus::New` records rather than all rows.
- Filament panel navigation groups are configured with
  `Panel::navigationGroups([...])`, passing `NavigationGroup::make()` instances in
  the desired order. Groups can define labels, `Heroicon` icons, and collapsibility.
- Filament's navigation manager sorts a blank-label group before labeled groups. This
  supports the requested ungrouped first segment when item groups are `null`.
- Filament resource/page navigation badges use `getNavigationBadge()` and
  `getNavigationBadgeColor()`. Boost did not return a global navigation-badge deferral
  API. Local vendor source confirms resource/page navigation items pass the evaluated
  badge value into `NavigationItem::badge()`.
- Filament 5.6 has `deferBadge()` for schema tabs and relation-manager tabs, plus
  `RelationManager::$isBadgeDeferred`, but not for sidebar navigation items.

## FilamentExamples Findings

Access level: `search_examples` snippet/search access only. No separate
source/read/fetch/detail tool was exposed.

Query batches used:

- `navigationGroups panel provider NavigationGroup make icon collapsible`
- `resource navigation badge deferred lazy getNavigationBadge`
- `read only resource view page no create edit`
- `Curator plugin navigation group sort Filament plugin`
- `resource navigation sort group override`
- `Filament custom create action label resource header action`
- refined pass: `NavigationGroup make label icon collapsible panel provider order`
- refined pass: `getNavigationBadge resource count status unread Filament 4`
- refined pass: `Filament table recordActions custom labels create edit actions`

Relevant examples and PodText adaptation notes:

- Multi-panel provider examples showed `PanelProvider` as the expected place for
  panel-level navigation configuration. PodText adapts this by adding ordered
  `NavigationGroup` objects to the existing admin panel provider.
- Resource examples showed class-level `navigationIcon`, `navigationLabel`, and
  table action-label patterns. PodText keeps labels translated and drives sort/group
  through the existing central navigation support.
- Read-only/view resource snippets confirmed that list/view-only resources are normal
  Filament surfaces. This is not needed because the public form submissions resource
  already exists.
- Search results did not expose a navigation badge deferral example. This matches
  Boost/vendor evidence that NAV1 cannot use a native global deferred navigation badge
  switch in Filament 5.6.

## Vendor Source Findings

- `vendor/filament/filament/src/Navigation/NavigationManager.php` sorts visible
  navigation items by `NavigationItem::getSort()`, groups them by serialized group,
  returns a blank `NavigationGroup` for ungrouped items, and sorts blank groups first.
- `vendor/filament/filament/src/Resources/Resource/Concerns/HasNavigation.php` and
  `vendor/filament/filament/src/Pages/Page.php` call `static::getNavigationBadge()`
  before passing the value to `NavigationItem::badge()`.
- `vendor/filament/filament/src/Navigation/NavigationItem.php` accepts a badge string
  or closure but has no `deferBadge()` or `isBadgeDeferred()` API.
- `vendor/awcodes/filament-curator/src/CuratorPlugin.php` supports
  `navigationGroup()`, `navigationSort()`, and `showBadge()`. PodText can therefore
  move Media ungrouped and keep Curator's package resource registered through plugin
  configuration.
- `vendor/awcodes/filament-curator/src/Resources/Media/MediaResource.php` computes a
  count badge only when `CuratorPlugin::shouldShowBadge()` is true. NAV1 should keep
  Curator's badge disabled to avoid eager Media counts in navigation.

## Local Code Findings

- `App\Filament\Support\AdminNavigationOrder` currently maps class names to numeric
  sort values only.
- `UsesAdminNavigationOrder` currently exposes only `getNavigationSort()`.
- Every app admin resource/page uses either `UsesAdminNavigationOrder` or plugin
  configuration for navigation order.
- Public form submissions currently sit in the content group. They need to become
  ungrouped with a deferred configured badge count.
- The episode workspace create navigation item is appended from
  `ContentItemResource::getNavigationItems()` and currently uses the content group,
  label `workspace_navigation`, and sort just after Episodes. NAV1 needs it ungrouped
  first with label `New episode` / `פרק חדש`.
- EP1 table behavior already routes rows to the workspace and keeps classic edit as a
  secondary action. Labels currently say `classic_create` / `classic_edit`; NAV1 needs
  the visible classic labels to carry the `(system)` / `(מערכת)` suffix wherever both
  workspace and classic actions appear.
- `phpunit.xml` already has low-cost test settings: `BCRYPT_ROUNDS=4`,
  `CACHE_STORE=array`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`,
  `SESSION_DRIVER=array`, and SQLite `:memory:`. `tests/Pest.php` also forces the
  safety-critical test env values before app boot.

## Decisions

1. Grow `AdminNavigationOrder` into the central admin navigation map for sort, group,
   label overrides, and badge-deferred intent.
2. Keep ungrouped items first by assigning `null` group and low sort values:
   workspace create, public form submissions, Media.
3. Configure ordered panel groups in `AdminPanelProvider` with translated labels and
   `Heroicon` enum icons:
   content management, taxonomy management, site management.
4. Implement form-submission badge counting on the existing resource, counting
   `status = new`, because that is the existing unhandled flag. Mark deferral through
   the central map and tests, while documenting that Filament 5.6 has no native
   navigation-badge async loading API.
5. Disable the Curator Media badge through plugin configuration to keep existing
   package navigation from adding eager counts.
6. Do not change Composer files.

