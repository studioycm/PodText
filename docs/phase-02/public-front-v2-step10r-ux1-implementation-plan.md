# Public Front v2 Step 10R-UX1 Implementation Plan

## Selected Step

Step 10R-UX1 - Admin navigation and table/modal standards.

Dependencies satisfied: Step 10R-M6 is complete in history; Step 11 and Prompt 13 have
not started. The central ledger still contains v1 rows and must be amended to v3 in this
run.

## Current Repo Evidence

- `git status --short --branch`: clean `main...origin/main`.
- Expected recent commits are present: `ebfa68e`, `6e7a74c`, and `06ba9e1`.
- Migrations are applied through `2026_07_09_000004_align_public_transcription_display_defaults`.
- Route preflight found the expected public `/podcasts`, `/contributors`, and `/search`
  routes.
- `EditContentItem` already has combined relation-manager tabs; `EditContentGroup` has a
  relation manager but not combined tabs.
- Admin theme CSS is already compiled from `resources/css/filament/admin/theme.css`.

## Files Inspected

- Runner and state docs listed by the prompt.
- Admin resources/pages/tables under `app/Filament/Resources`.
- `app/Filament/Pages/PublicContentSettings.php`.
- `app/Providers/AppServiceProvider.php`.
- `app/Providers/Filament/AdminPanelProvider.php`.
- Admin theme CSS at `resources/css/filament/admin/theme.css`.
- Admin and public-front test files.
- Filament vendor source for `Table`, `Action`, `Section`, content tab position, and
  panel resource/page registries.

## Boost Findings

- Installed versions: Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4,
  Tailwind 4.3.2, SQLite local DB.
- No schema migration is needed.
- Use `getNavigationSort()` for dynamic sort values.
- Use `RecordActionsPosition::BeforeColumns` for record actions before data columns while
  leaving bulk checkboxes first.
- Use `Action::configureUsing()` and `modalWidth(Width::SevenExtraLarge)` for global
  action modal widths, preserving `Width::Medium` confirmations.
- Use `Section::configureUsing()` and `columnSpanFull()` for admin section defaults.
- Use `hasCombinedRelationManagerTabsWithContent()` and
  `ContentTabPosition::Before` for explicit content-tab-first combined tabs.

## FilamentExamples Findings

Research note:
`docs/research/public-front-v2/20-step10r-ux1-mcp-research.md`.

Access was search/snippet only. Useful patterns were explicit navigation sort values,
reusable table configuration classes, `RecordActionsPosition::BeforeColumns`,
full-width schema sections, relation-manager tab components, and admin theme CSS scoped
to Filament classes.

## Settings / Render Context Impact

No public-front settings keys, settings migrations, validators, or render-context accessors
change in UX1.

## Admin / Public Impact

Admin:

- Stable admin navigation order from one map.
- Record actions render before data columns on admin tables.
- Non-confirmation action modals default to `7xl`; confirmations remain compact.
- Admin `Section` schemas default to full-width.
- Content item and content group edit pages use combined relation-manager tabs with the
  content tab explicitly first.
- Combined relation-manager tab labels are larger via scoped admin theme CSS.

Public:

- No intended public panel behavior change.

## Query / Cache Impact

No public query or cache behavior changes. Relation-manager tab badges continue using the
existing owner-record counts.

## Exact Files To Change

- Add admin navigation support classes under `app/Filament/Support`.
- Add an app dashboard page class so the dashboard consumes the navigation map.
- Update admin resources/pages to consume the map.
- Update `AppServiceProvider` with admin-scoped global Filament defaults.
- Update `EditContentItem` and `EditContentGroup` relation tab methods.
- Update admin theme CSS.
- Add/update Pest admin UX tests.
- Update UX1 docs, ledger, sequence, handoff, and current state.

## Tests

- Navigation order and map completeness across registered admin resources/pages.
- Representative table record-action position on the Episodes table and podcast Episodes
  relation manager.
- Global action modal-width default and compact confirmation behavior.
- Admin section default `columnSpanFull()`.
- Combined relation-manager tabs on `EditContentItem` and `EditContentGroup`.
- Existing admin smoke suites.
- Existing bounded public rendering harness.

## Risks

- The CSS selector relies on Filament's current component key/class output and may need
  review during Filament upgrades.
- Global `configureUsing()` defaults are intentionally panel-scoped. Tests must set the
  current admin panel before asserting defaults.
- Confirmation modals are intentionally not widened because Filament's compact default is
  better for destructive confirmations.

## Out Of Scope

- Public navigation/menu behavior.
- New resources, clusters, or settings groups.
- Effective transcription edit action (UX2).
- Default images, icon picker, custom colors, caching, backup/import/export, slider work,
  Step 11, or Prompt 13.

## Stop Conditions

- Stop if unexpected app-code dirt appears before coding.
- Stop if Filament 5.6 APIs for the selected defaults/tabs are unavailable.
- Stop if a requested relation-manager tab target is a create page without a persisted
  record.
- Stop if the implementation would require Step 11 or Prompt 13 approval-gated work.
