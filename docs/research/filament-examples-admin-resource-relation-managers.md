# FilamentExamples Research: Admin Resources and Relation Managers

## Purpose

This research supports Prompt 09 admin UX. It does not implement features.

## MCP access summary

- MCP tool used: `mcp__filament_examples.search_examples`.
- Search terms used:
  - `relation manager tabs form content ContentTabPosition hasCombinedRelationManagerTabsWithContent getContentTabComponent getTabComponent badge icon`
  - `ManageRelatedRecords resource sub navigation relation page relation manager`
  - `RelationManager CreateAction EditAction MarkdownEditor hasMany long content child records`
  - `CreateRecord getRedirectUrl EditRecord getRedirectUrl Resource getUrl index create another false`
  - `split Resource schemas tables relation manager hiddenOn sharing Resource form table with relation manager`
  - `slug auto generation afterStateUpdated live onBlur manually edited slug Filament Resource`
- Whether source snippets were returned: yes, `search_examples` returned source snippets and file paths.
- Whether a separate fetch/read/detail/source tool existed: no separate fetch/read/detail/source tool was exposed in this session.
- Access level: source snippets returned directly by `search_examples`; no full repository fetch was used.
- Limitations: examples are practical snippets, not package documentation. Official Filament 5 docs through Laravel Boost remain the source of truth for method names and version-specific APIs.

## Official Filament docs summary

Laravel Boost `search_docs` was available and returned Filament 5.6.7 documentation.

- Resource relation managers are intended for `HasMany`, `HasManyThrough`, `BelongsToMany`, `MorphMany`, and `MorphToMany` relationships. They let admins list, create, edit, and manage related records without leaving the owner record's Edit or View page.
- Relation managers are created with `php artisan make:filament-relation-manager OwnerResource relationship titleAttribute` and registered through the owner Resource `getRelations()` method.
- `EditRecord` and `ViewRecord` pages may combine the owner form/content tab with relation manager tabs by overriding `hasCombinedRelationManagerTabsWithContent(): bool` and returning `true`.
- The owner form/content tab can be customized with `getContentTabComponent(): Tab`.
- The owner form/content tab renders before relation tabs by default. Override `getContentTabPosition(): ?ContentTabPosition` and return `ContentTabPosition::After` only if the relation tabs should appear first.
- A relation manager tab can be customized by overriding `getTabComponent(Model $ownerRecord, string $pageClass): Tab`, with label, icon, badge, badge color, and badge tooltip.
- Expensive tab badge counts can use `deferBadge()` when the badge value is supplied by a closure. The general tabs docs require a stable tabs key for deferred badges; implementers should verify whether Filament's relation manager tab container provides that key before using deferral.
- Relation managers may share a Resource form/table by calling `TranscriptionResource::form($schema)` and `TranscriptionResource::table($table)`, but owner-context fields or columns should be hidden with `hiddenOn(RelationManagerClass::class)`.
- Relation manager table queries can be adjusted with `modifyQueryUsing()` to eager-load relationships or apply owner-scoped ordering without query work in table closures.
- `ManageRelatedRecords` pages are the official alternative when relationship management should be separated from owner edit/view content, especially with resource sub-navigation.
- Standalone Create pages redirect after save by overriding `getRedirectUrl()` and returning `$this->getResource()::getUrl('index')`.
- Standalone Edit pages normally do not redirect after save, but may override `getRedirectUrl()` to return `$this->getResource()::getUrl('index')` when the intended workflow is returning to the list.
- Create pages disable "create another" with `protected static bool $canCreateAnother = false` or `canCreateAnother(): bool`. Modal `CreateAction` disables it with `createAnother(false)`.
- Relation manager tests instantiate the relation manager Livewire component with `ownerRecord` and `pageClass`, and can call table actions with `Filament\Actions\Testing\TestAction`.

## Example findings

### Example: Tournament relation managers and dedicated stats page

- MCP tool used: `mcp__filament_examples.search_examples`.
- Access level: source snippets.
- Files/classes/snippets observed:
  - `v4/full-projects/box-score-form/app/Filament/Resources/Tournaments/TournamentResource.php`
  - `v4/full-projects/box-score-form/app/Filament/Resources/Tournaments/RelationManagers/MatchesRelationManager.php`
  - `v4/full-projects/box-score-form/app/Filament/Resources/Tournaments/RelationManagers/TeamsRelationManager.php`
  - `v4/full-projects/box-score-form/app/Filament/Resources/Tournaments/Pages/ManagePlayerStats.php`
- Relevant Filament concepts: `getRelations()`, relation manager forms/tables, header `CreateAction`, row `EditAction`, custom row action linking to a Resource URL, and a dedicated custom manage page for specialized editing.
- Pattern to copy: use a relation manager for normal child-record CRUD directly on the owner Resource; use Resource URL helpers for owner-scoped deep actions.
- Pattern to avoid: do not create a custom page for ordinary transcript CRUD when the relation manager is enough.
- Applicability to PodText: high. `ContentItem::transcriptions()` is a normal child collection and should be managed from the item edit page.
- Should affect Prompt 09? yes.
- Should affect Prompt 08? no.
- Should affect future studio? yes, as a reference for when transcript tooling grows into a dedicated page.
- Confidence: high.

### Example: Stock item transactions relation manager

- MCP tool used: `mcp__filament_examples.search_examples`.
- Access level: source snippets.
- Files/classes/snippets observed:
  - `v4/full-projects/stock-management/app/Filament/Resources/Items/RelationManagers/TransactionsRelationManager.php`
  - `v4/full-projects/stock-management/app/Filament/Resources/Transactions/TransactionResource.php`
- Relevant Filament concepts: relation manager using a related Resource form, owner record access through the relation manager, header `CreateAction`, and explicit read/write relation-manager behavior.
- Pattern to copy: keep item-scoped child create/edit in the relation manager, and reuse Resource form/table code only when owner-specific fields can be hidden or overridden cleanly.
- Pattern to avoid: avoid manual owner FK assignment unless Filament's relationship create flow cannot meet the requirement; relation managers should usually let the relationship context attach the owner record.
- Applicability to PodText: medium-high. It supports a relation manager as the main item-scoped transcript surface, but PodText should not expose `content_item_id` in the relation-manager form.
- Should affect Prompt 09? yes.
- Should affect Prompt 08? no.
- Should affect future studio? no.
- Confidence: medium-high.

### Example: Restaurant menu `ManageRelatedRecords`

- MCP tool used: `mcp__filament_examples.search_examples`.
- Access level: source snippets.
- Files/classes/snippets observed:
  - `v4/tables/restaurant-menu/app/Filament/Resources/Categories/Pages/ManageCategoryDishes.php`
  - `v4/tables/restaurant-menu/app/Filament/Resources/Categories/CategoryResource.php`
  - `v4/tables/restaurant-menu/app/Filament/Resources/Categories/Tables/CategoriesTable.php`
- Relevant Filament concepts: `ManageRelatedRecords`, owner Resource page route registration, related Resource linkage, and table row action that opens the relation page with `CategoryResource::getUrl()`.
- Pattern to copy: reserve a dedicated relation page for a relationship that needs its own route, sub-navigation, or larger working area.
- Pattern to avoid: do not move transcriptions to a separate relation page in Prompt 09 unless the relation manager becomes too cramped after implementation.
- Applicability to PodText: medium. Full transcript editing may later justify a dedicated relation page, but Prompt 09 should start with the relation manager plus standalone `TranscriptionResource`.
- Should affect Prompt 09? yes, as a deferred alternative.
- Should affect Prompt 08? no.
- Should affect future studio? yes.
- Confidence: high.

### Example: Livewire sidebar in an edit page

- MCP tool used: `mcp__filament_examples.search_examples`.
- Access level: source snippets.
- Files/classes/snippets observed:
  - `v4/forms/livewire-component-in-editform-sidebar/app/Filament/Resources/Tickets/Pages/EditTicket.php`
  - `v4/forms/livewire-component-in-editform-sidebar/app/Filament/Resources/Tickets/Schemas/TicketForm.php`
- Relevant Filament concepts: overriding an edit page `content()` method, composing the form and relation manager content components, and placing owner-context UI beside the form.
- Pattern to copy: if Prompt 09 needs owner-context status or warnings, use the edit page content composition pattern rather than putting unrelated state in the form fields.
- Pattern to avoid: do not add a custom sidebar just to manage transcriptions; combined relation tabs are the simpler official pattern.
- Applicability to PodText: low for Prompt 09, medium for future editorial/studio UX.
- Should affect Prompt 09? optional only.
- Should affect Prompt 08? no.
- Should affect future studio? yes.
- Confidence: medium.

### Example: Split Resource schemas/tables and slug generation snippets

- MCP tool used: `mcp__filament_examples.search_examples`.
- Access level: source snippets.
- Files/classes/snippets observed:
  - `v4/full-projects/cms-blog-system/app/Filament/Resources/Posts/PostResource.php`
  - `v4/full-projects/cms-blog-system/app/Filament/Resources/Posts/Schemas/PostForm.php`
  - `v4/full-projects/cms-blog-system/app/Filament/Resources/Posts/Tables/PostsTable.php`
  - `v4/full-projects/create-form-and-table-on-the-same-page/app/Filament/Pages/Category.php`
- Relevant Filament concepts: split `Schemas/*Form` and `Tables/*Table` classes, `TextInput::make('title')->live(onBlur: true)->afterStateUpdated(...)`, `Set` utility injection, and Resource `form()`/`table()` delegating to split classes.
- Pattern to copy: keep Prompt 09 Resource form/table implementations in split classes, matching the existing PodText Resources.
- Pattern to avoid: the simple slug snippets always overwrite the slug. PodText requires manual slug edits to be preserved, so implementers must add a guard that only updates the slug while it is blank or still matches the previous auto-generated value.
- Applicability to PodText: high for Resource structure and slug behavior caveat.
- Should affect Prompt 09? yes.
- Should affect Prompt 08? no.
- Should affect future studio? no.
- Confidence: high.

## Recommended PodText admin UX

Prompt 09 should keep a standalone global `TranscriptionResource` and add `ContentItemResource\RelationManagers\TranscriptionsRelationManager`.

The standalone `TranscriptionResource` should be used for global transcript discovery, filtering, and maintenance across all content items. Its table should include searchable item title, group title, author, status badge, language, published date, featured/main state, and updated date. Filters should cover content item, content group, author, status, language, published date, and featured/main state.

The `TranscriptionsRelationManager` should be the primary item-scoped transcript editing surface. It should manage `ContentItem::transcriptions()` from the item edit page, use a header create action and row edit action, and include a row action to set a published same-item transcription as featured/main. The relation-manager form must not expose `content_item_id`; the owner context supplies it. It should include author, title, language code, status, `published_at`, and `transcript_markdown` Markdown editor fields. Parser JSON, word count, reference key, and similar derived/technical fields should be hidden, read-only, or grouped under an advanced section only where useful.

`EditContentItem` should combine the item form tab and relation manager tabs with `hasCombinedRelationManagerTabsWithContent(): true`. Keep the content/form tab first, because item identity, publication, category, media, and featured-transcription context should precede transcript management. Customize the content tab with a translation-key label such as `admin.tabs.item_details`, and customize the relation manager tab with a translation-key label such as `admin.tabs.transcriptions`, a `Heroicon` enum icon where possible, and a badge count for the owner's transcriptions. Use deferred badges only after verifying the relation-manager tab component supports the needed keying for `deferBadge()`.

Standalone Create pages for Prompt 09 Resources should redirect to the Resource index after successful create by overriding `getRedirectUrl()` and returning `$this->getResource()::getUrl('index')`, unless a specific Resource has a documented reason to continue editing immediately. Standalone Edit pages should also redirect to the Resource index after save for list-driven admin maintenance, but item edit pages using relation managers may stay on the edit page if the admin is expected to continue managing related records. Relation manager create/edit actions should stay on the owner item edit page. Disable "create another" for standalone transcript create pages and relation-manager transcript create modals unless preserving the owner context and repeated transcript entry is explicitly tested.

Do not use a Repeater for full transcript Markdown. A full transcript body is too large for inline nested form rows, and relation-manager actions provide a clearer modal/page boundary. A dedicated `ManageRelatedRecords` page is a future option if transcript management outgrows the combined tabs surface or needs sub-navigation, bulk transcript workflows, or studio-style tooling. Prompt 14 remains the planning location for future studio behavior.

## Prompt 09 patch summary

Patch these active docs/prompts/guidelines:

- `docs/phase-02/blueprints/09-admin-content-management-blueprint.md`: add the `TranscriptionsRelationManager` plan, combined tab decision, redirect behavior, relation-page/repeater decision, and tests.
- `prompts/09-phase-02-admin-content-management.md`: require reading this research, implementing the relation-manager plan, using researched redirects, and reporting relation-manager/redirect checklist status.
- `docs/phase-02/answers-coverage-matrix.md`: add coverage rows for relation-manager research, combined tabs, redirects, and relation-page/repeater decisions.
- `.ai/guidelines/transcriptions.md`: add durable admin-scoped transcript management guidance.
- `.ai/guidelines/tooling-quality.md`: add durable relation-manager API/reporting guidance.
- `docs/phase-02/feature-map.md`: record Prompt 09 admin Resource UX and relation-manager decisions.
- `prompts/README.md`: note that this is a pre-Prompt-08 docs-only refinement and Prompt 08 remains next.
- `docs/phase-02/tooling-and-quality-gates.md`: add relation-manager FilaCheck/FilaCheck Pro pitfalls.
