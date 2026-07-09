# Public Front v2 Admin and Settings Enhancement MCP Research

Date: 09/07/2026

## Scope

This research note covers the post-M6 Yoni requests to extend the continuation queue before resuming the remaining Public Front v2 implementation work:

1. Admin navigation order.
2. Default/no-image upload settings and public fallback rendering.
3. Expanded icon settings with icon select UI.
4. Custom color picker behavior.
5. Podcast-image sampled colors with light/dark contrast rules.
6. Relation managers as tabs above edit/view content where applicable.
7. Table actions as the first non-checkbox column.
8. Wide table-action modals with full-width form sections.
9. Effective transcription edit action on episode lists.
10. Settings import/export planning.
11. Settings backup-version planning.

## Local Repository Evidence

- Current branch is `main`; pre-research `git status --short --branch` reported a clean working tree.
- Step 10R-M6 is complete and records R1-R23 as landed.
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md` previously listed Step 10R-P1 as next.
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md` previously listed Step 10R-P1 as the first pending mini-step.
- `ContentItemResource` has a `TranscriptionsRelationManager`; `ContentGroupResource` has a `ContentItemsRelationManager`.
- `EditContentItem` already combines relation manager tabs with the content tab. Other resource pages with relation managers need audit during implementation.
- Table `recordActions()` exist in the resource/relation-manager table classes for public form submissions, content items, transcriptions, homepage sections, authors, content groups, content tags, and categories.
- `PublicFrontCardTemplateRegistry::icons()` and `PublicFrontCardIconResolver` currently expose a short finite app-owned icon list.
- `PublicItemPagePodcastPalette` currently samples three raw colors from local public-disk podcast covers, but does not normalize light/dark contrast variants.
- The schema has `settings` rows for Spatie Settings, `content_groups.cover_path` for uploaded podcast covers, and `content_items.external_thumbnail_url` for item thumbnails. There is no settings backup/version table yet.

## Laravel Boost Findings

Tools used: `application_info`, `database_schema`, and `search_docs`.

- Boost confirmed the installed stack: Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, Spatie Laravel Settings 3.9.0, SQLite locally.
- Filament navigation supports explicit navigation sort values; the request can be implemented with per-resource/page navigation sort values rather than a new navigation model.
- Filament table record actions support position control. `RecordActionsPosition::BeforeColumns` is the target position because it places the record-actions column before data columns while still leaving bulk checkboxes first.
- Filament resource pages can combine relation-manager tabs with the main content tab via `hasCombinedRelationManagerTabsWithContent()`. Create pages generally cannot show relation managers until a record exists, so implementation should target edit/view pages that actually have relations.
- Filament actions support wide modals via `modalWidth()` and `Filament\Support\Enums\Width`; custom action schemas should keep major `Section` components `columnSpanFull()`.
- Filament `FileUpload` supports image constraints, disk/visibility configuration, max size, and accepted file types. The fallback image settings should keep uploads on the public disk with finite directories and no remote fetching during public rendering.
- Filament `ColorPicker` is the right control for custom colors. Any stored custom color must be strict hex only and rendered through an app-owned sanitizer/helper.
- Laravel transactions should wrap settings imports/restores and backup creation.
- Settings import/export should use a versioned JSON package, not CSV import/export, because these settings are nested JSON configuration, not tabular editorial records.

## FilamentExamples MCP Findings

Access level: `search_examples` snippet/search access only. No source/read/detail tool was exposed, and no token/header data was recorded.

Query batches used:

- `select icon options`, `icon select field`, `heroicon select`
- `combined relation manager tabs`, `relation manager tabs form`, `relation manager tab component`
- `record actions before columns`, `table actions first column`, `wide action modal`
- `settings import export action`, `settings backup versions`, `settings page file upload`
- `color picker settings`, `image upload settings`, `custom color field`

Useful examples and adaptations:

- Yoni-selected icon picker reference: **Icon Picker Select Field with Live Icon Display - Select With Custom HTML Values and Search Results**.
  - Public example page: `https://filamentexamples.com/project/filament-v4-filament-icon-select-field-with-preview`
  - GitHub path provided by Yoni: `https://github.com/LaravelDaily/FilamentExamples-Projects/tree/main/v4/forms/select-with-custom-html-values-and-search-results`
  - Public page evidence: the example targets Filament 4/5, uses Filament's `Filament\Support\Icons\Heroicon` enum, and describes a customized Select with HTML-formatted results and search.
  - MCP snippet path: `v4/forms/select-with-custom-html-values-and-search-results/.../CategoryForm.php`.
  - Pattern to adapt: searchable `Select`, `allowHtml()`, HTML option labels, live icon display/search results, and options/search results derived from `Heroicon::cases()`.
  - PodText adaptation: implement this through an app-owned `PublicFrontIconRegistry`/form helper, store only normalized finite icon tokens, preserve existing aliases, and render only through `PublicFrontCardIconResolver`.
  - Access note: the public FilamentExamples page was reachable; the provided GitHub tree URL was not publicly fetchable from this environment, so V1 should use the MCP snippet/search access plus any Yoni-provided or locally accessible source if available.
- `v4/full-projects/clusters-with-profile-settings/...` shows `protected static ?int $navigationSort` on settings pages and clustered pages. PodText can use explicit sort values on Resources and the `PublicContentSettings` page without creating clusters unless a later UX step needs them.
- `v4/full-projects/eshop-with-front-page/.../ManageSettings.php` shows a SettingsPage with `FileUpload::make('logo')->columnSpanFull()`. PodText fallback-image settings should use this pattern with stronger image constraints and public disk visibility.
- `v4/forms/wizard-invoice-form/...` shows full-width wizard/section usage and reusable schema helpers. PodText wide action modals should reuse shared form helpers and keep major modal sections full width.
- Several action examples use reusable action classes/services for repeated row actions. PodText should implement the effective-transcription edit action once and reuse it in the content item table and content group item relation manager.

## Request-by-Request Research Implications

1. Admin navigation order can be handled with explicit navigation sort values: podcasts, episodes, transcriptions, transcribers, categories, tags, form submissions, homepage sections, settings.
2. Default/no-image behavior needs new public settings defaults, a settings migration, upload constraints, render-context access, and fallback helper coverage across cards/pages.
3. Expanded icon settings should move from the short static list to a finite registry built from `Heroicon::cases()` plus compatibility aliases for current stored keys.
4. Custom colors need a deliberate safe exception to the finite semantic-token-only pattern: store only strict `#rrggbb`, never classes or raw CSS snippets.
5. Podcast-image color sampling must normalize sampled colors into theme-safe dark/light variants and must not fetch remote images or block public rendering on queued work.
6. Relation-manager tabs belong on edit/view pages with persisted records; create pages should be documented as not applicable unless a concrete page supports relations after save.
7. Table actions should use `RecordActionsPosition::BeforeColumns` everywhere to keep the checkbox column first.
8. Modal width and full-width sections should be centralized to prevent inconsistent future action schemas.
9. The effective-transcription edit action should reuse the existing transcription form schema where safe and resolve the same featured/effective/main transcription semantics used by public rendering.
10. Settings import/export should be versioned JSON with validation, dry-run/diff, transaction, backup-before-import, and cache invalidation.
11. Settings backup versions need their own schema/retention/restore plan. This is admin-only and must not expose `User` publicly.

## Stop Conditions For Implementation Steps

- Stop if an implementation step finds unexpected app-code dirt before coding.
- Stop if adding custom color storage conflicts with the active finite-token guardrail and Yoni has not accepted the strict-hex exception.
- Stop if icon enum values changed in a way that breaks existing stored icon aliases without a migration/compatibility layer.
- Stop if image color sampling would require public-request remote fetching.
- Stop if a relation manager is requested on a create page before the record exists and Filament cannot support that page shape safely.
- Stop if settings import/export scope expands into seeded content/demo data; that belongs to Step 11 after approval.
