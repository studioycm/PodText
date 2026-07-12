# EP1-R Episode Workspace Research

Date: 2026-07-12

This is a docs-only research run. No app code, migrations, Composer changes, test suite, Pint, FilaCheck, or build commands were run. Validation for this run is limited to `git diff --check` and `git status --short`.

## Scope And Tool Access

- Installed stack confirmed by Laravel Boost `application_info`: PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, Laravel Boost 2.4.11.
- Laravel Boost used: `application_info`, `database_schema`, and `search_docs`.
- FilamentExamples used: `search_examples` only. No source/read/fetch/details tool was exposed, so findings from FilamentExamples are search/snippet-level evidence only.
- Local source audit used read-only shell probes against app, vendor, config, database, tests, and docs files.
- No probe scratch files were created.

## FilamentExamples Batches

Access level: snippets/search results only.

- Relationship/forms batch: `relationship fieldset hasOne belongsTo`, `form relationship section`, `mutateRelationshipDataBeforeCreate`, and neighboring action/form patterns. Useful snippets showed custom field relationship loading/saving and a create-form-plus-table custom page. No source access was exposed.
- Custom resource page batch: `custom resource page InteractsWithRecord`, `resource page custom edit`, and `table recordUrl`. Useful snippets included `box-score-form`, where a resource registers a custom page and builds record-specific URLs through the resource.
- Settings batch: `SettingsPage tabs`, `profile settings custom page`, and settings form examples. Useful snippets showed page forms backed by settings/profile data.
- Wizard/modal batch: `CreateRecord HasWizard`, `wizard summary step`, and form actions. Useful snippets showed `CreateRecord\Concerns\HasWizard` pages and form field actions mounted beside fields.

## Job 1 - Architecture Race

### Current Schema And Model Reality

- `content_items.featured_transcription_id` is nullable and foreign-keyed to `transcriptions.id` with `nullOnDelete`.
- `transcriptions.content_item_id` is required. A transcription cannot be saved without its parent episode.
- `ContentItem::featuredTranscription()` is a `BelongsTo` relation from the item to the child transcription.
- `ContentItem::transcriptions()` is the owning `HasMany`.
- `ContentItem::effectiveTranscription()` returns a same-item published featured transcription when valid, otherwise the latest published transcription.
- `Transcription::booted()` auto-pins the first transcription for an item when the item has no featured transcription.
- `ContentItem::booted()` validates that a dirty `featured_transcription_id` belongs to the same item before saving.

### Filament Relationship Binding Evidence

Filament 5.6.7 layout relationship binding supports singular `HasOne`, `BelongsTo`, and `MorphOne` relationships. Vendor evidence:

- `vendor/filament/forms/docs/01-overview.md` says layout components can use `->relationship()` with `HasOne`, `BelongsTo`, or `MorphOne`, and missing related records are created automatically.
- `vendor/filament/schemas/src/Components/Concerns/EntanglesStateWithSingularRelationship.php` sets the state path to the relationship name, loads the existing related record, validates the child schema through `getState()`, supports `mutateRelationshipDataBeforeFillUsing()`, `mutateRelationshipDataBeforeCreateUsing()`, and `mutateRelationshipDataBeforeSaveUsing()`, and saves all layout components for the same relationship together.
- The same trait creates a new related record when none exists. For `BelongsTo`, it saves the child, associates it to the parent, and saves the parent. For `HasOne` and `MorphOne`, it calls `$relationship->save($record)`.
- `vendor/filament/filament/src/Resources/Pages/CreateRecord.php` creates the parent record first and then calls `$this->form->model($this->getRecord())->saveRelationships()`.
- `vendor/filament/filament/src/Resources/Pages/EditRecord.php` calls `$this->form->getState(afterValidate: ...)`; the schema saves relationships during `getState()` before the parent record update is handled, but inside the page transaction.

### Direct `featuredTranscription` Binding Fails

Binding the workspace section directly to `featuredTranscription` is the wrong direction for creating the child:

- Filament would treat it as `BelongsTo`.
- On create, the new child `Transcription` would be saved before association, but Filament's `BelongsTo` save path does not set `transcriptions.content_item_id`.
- Because `transcriptions.content_item_id` is non-nullable, direct `BelongsTo` creation cannot satisfy the database contract.
- If the implementation tried to prefill `featured_transcription_id`, `ContentItem::saving()` validates ownership while a new parent has no key yet.

### Dedicated `HasOne` Binding Survives

The first viable architecture is still native relationship-bound form state, but not through the current `BelongsTo`.

EP1 should add a dedicated write relation on `ContentItem`, for example `workspaceTranscription(): HasOne`, that resolves one target row for the workspace:

1. If `featured_transcription_id` is set, target that transcription.
2. If no featured transcription exists, order child transcriptions so published rows win, then latest `published_at`/`id`, then newest draft.
3. If no transcription exists, let Filament create one through the `HasOne` relation.

Because the relation is a `HasOne` over `transcriptions.content_item_id`, Filament's `$relationship->save($record)` path fills the required child foreign key after the episode exists. Page hooks then synchronize `content_items.featured_transcription_id` to the saved workspace transcription after create/save. That synchronization is required even though first-transcription auto-pin already exists, because saving an unpinned target must adopt it as featured by D-EP1.

The relationship-bound section can render normally, or its same schema can be mounted inside a modal or slideover action for the presentation-mode setting. Filament's MarkdownEditor source waits briefly when initialized inside `.fi-modal`, which supports modal usage from a rendering standpoint.

### Wizard Fallback Does Not Beat The Native Relation

Filament wizards validate a step before moving to the next step, but the vendor `Wizard::nextStep()` only calls the current step validation hooks and dispatches the next-step event. `CreateRecord\Concerns\HasWizard` wraps the final submit action; it does not natively commit the parent record at step-1 completion. A create wizard could be forced to save between steps with custom code, but that is more glue than a native relationship-bound `HasOne`.

### Save-Draft-First Fallback Is Last

Auto-saving a draft episode mid-create and reloading the workspace would work, but it creates a multi-request draft lifecycle, error-preservation problem, and accidental draft-record risk that Filament's create transaction avoids.

## Job 2 - Page Type, Routing, And Actions

The least-glue page type is resource pages, not standalone Livewire:

- Use a `CreateRecord` subclass for the create workspace and an `EditRecord` subclass for the edit workspace.
- Keep the classic `CreateContentItem` and `EditContentItem` pages registered.
- Register new resource page keys in `ContentItemResource::getPages()` before wildcard routes, for example a create-workspace route and a per-record workspace route.
- Share one workspace form schema so create/edit behavior stays aligned.

Evidence:

- `ContentItemResource::getPages()` already registers `index`, `create`, and `edit`.
- `CreateRecord` and `EditRecord` already provide transactions, form state, relation saving, notifications, redirects, header actions, and authorization entry points.
- Vendor docs for table record URLs show `$table->recordUrl()` and say resource table row URLs can be overridden.
- Vendor `ListRecords` sets a default row URL from `view`/`edit` actions only if the table has no custom record URL.
- Filament resource custom page generators use `InteractsWithRecord` for custom record pages, but EP1 needs native create/edit form save pipelines, making `CreateRecord`/`EditRecord` the better base than a bare `Page`.

Integration points in current app:

- `ContentItemsTable` currently eager-loads `contentGroup`, featured/latest transcription authors, and `transcriptions_count`.
- Its record actions are `EditEffectiveTranscriptionAction`, `addTranscriptionAction()`, and classic `EditAction`.
- `ContentItemsRelationManager` mirrors those actions and also exposes a modal classic edit plus an `openResource` action.
- `ListContentItems` has a classic `CreateAction`.

EP1 should make the workspace URL the default row URL and the first/default edit action on both episode list surfaces. Classic edit remains a secondary action with a translated label.

## Job 3 - Settings And Per-User Preference

`AdminUxSettings` should be a new Spatie settings class, not an extension of `PublicContentSettings`.

Required keys:

- `transcription_presentation_mode`: `collapsible`, `modal`, or `slideover`, default `collapsible`.
- `transcription_mode`: `single` or `multi`, default `single`.
- `show_episode_workspace_hint_line`: boolean, default `true`.
- `show_episode_workspace_language_code`: boolean, default `false`.
- `tb1_picker_container`: `modal` or `slideover`, default `modal`, reserved for TB1.
- `media_naming_strategy`: `slug`, `reference_key`, or `slug_key`, default `slug`, reserved for the IMG arc.

Evidence:

- `config/settings.php` registers `PublicContentSettings::class`, has `setting_class_path` as `app/Settings`, and settings migrations live in `database/settings`.
- `app/Filament/Pages/PublicContentSettings.php` extends `Filament\Pages\SettingsPage` and declares `protected static string $settings`.
- Vendor `SettingsPage` fills `public ?array $data`, calls `app(static::getSettings())`, uses `statePath('data')`, and saves settings inside a transaction.
- `users` currently has no `admin_preferences` JSON column in the schema probe.

Per-user override finding:

- A `users.admin_preferences` JSON column plus a workspace mode switcher is technically straightforward, but it adds schema, cast, setting precedence, UI persistence, and tests unrelated to the core EP1 create/edit workflow.
- EP1 should implement the global setting only.
- A follow-up can add `users.admin_preferences->episode_workspace.transcription_presentation_mode`, defaulting to `AdminUxSettings`, with a mode switcher on the workspace.

## Job 4 - Form Surfaces Inventory

Reuse existing components and rules where possible:

| Workspace area | Existing source to reuse | EP1 adaptation |
|---|---|---|
| Podcast select | `ContentItemForm::content_group_id` and `RelationshipOptionForms::configureContentGroupSelect()` | Keep searchable relationship select and inline create/edit option forms. |
| Title | `ContentItemForm` title field | Add `title_prefix` nullable string beside `title`; live-fill from selected podcast title when blank; never combine into `title`. |
| Combined title preview | Filament live state and `Placeholder`/computed state are sufficient | Render `[title_prefix ?? group title] + separator + [title]` as preview only. |
| Slug | `SlugInput::source('title')` and UX3 smart slug behavior | Add a clear hint action plus full public URL preview. |
| Media URL fields | `media_url`, `embed_url`, `embed_provider`, `external_thumbnail_url`, `direct_media_url`, `media_duration_seconds`, `media_metadata` in `ContentItemForm` | Keep `ContentItemMediaRules`; use existing `direct_media_url` for MP3 URL. |
| Embed paste | Existing `ApprovedEmbedUrl` rejects raw HTML for the URL path; D-EMB1 now adds trusted `embed_html` storage for admin-pasted code | Add two affordances: keep pasted iframe/embed code verbatim in `embed_html`, or run an extract-src helper that fills `embed_url` for the allowlisted URL path. Helper text must state `embed_html` renders first. |
| Spotify fetch | `SpotifyConnector::fetchEpisode()` and `ImportConnection` Spotify client credentials | Add a small app service boundary, for example `EpisodeSpotifyLookup`, that accepts URL/ID plus an optional connection and returns normalized form-fill fields. |
| Audio preview | Existing `direct_media_url` HTTPS validation | Add owned Blade/Alpine preview rendered only when the current URL validates. |
| Taxonomy | Categories select and `SpatieTagsInput` in `ContentItemForm` | Put in a collapsed taxonomy section. |
| Transcript | `TranscriptionForm::components(includeContentItem: false, useRelationshipTranscriberSelect: ...)` and `RelationshipOptionForms::configureTranscriberOptionsSelect()` | Use MarkdownEditor for `transcript_markdown`; no repeater; no rich HTML storage; title/status/transcribers; language field hidden unless setting enables it. |
| Publication | Status and day-first `DateTimePicker` fields in `ContentItemForm` and `TranscriptionForm` | Keep `d/m/Y H:i` and `Asia/Jerusalem`. |
| Visibility checklist | `ContentItem::published()`, `ContentItem::effectiveTranscription()`, group/item/transcription statuses | Compute from loaded form/model state and already-loaded relations; avoid per-keystroke database queries. |
| Footer actions | Native page form actions | Add save draft, publish, and save-and-add-another actions. |

Spotify service boundary:

- Input: Spotify episode URL, URI, or ID; optional selected `ImportConnection`; optional market.
- Output: normalized array with title, show name, duration seconds, release date, thumbnail URL, embed URL/source URL, external provider/id, `media_metadata`, and HTML/plain description fields when the client returns them.
- Existing `SpotifyHttpClient` currently returns title, show, duration, release date, and thumbnail only. EP1 may expand it without changing Composer dependencies.
- Tests should fake the service at the workspace-page layer. WB7 can later reuse the same service for bulk enrichment.

### D-EMB1 Trusted Embed HTML Update

D-EMB1 supersedes the EP1-R prompt's URL-only embed assumption:

- Add nullable `content_items.embed_html` text storage for admin-pasted embed code.
- Store `embed_html` verbatim. Do not sanitize, rewrite, normalize, or extract on save. This uses the same trusted-admin model as D30 maintenance HTML.
- Render `embed_html` only through the owned public media-embed component, which gets a raw mode.
- Public media precedence is `embed_html` first, then the existing `embed_url` allowlist flow.
- `embed_html` must not render through Markdown, public cards, table columns, exports, generic presenters, or any other surface.
- The workspace keeps two paths: keep pasted code as trusted `embed_html`, or use an explicit extract-src helper action that fills `embed_url`.
- `ContentItemMediaRules` should pass `embed_html` through as nullable trusted text with only bounded string/length checks if needed; no sanitizer rule belongs there.
- This EP1-R amendment updates `docs/phase-02/media-embed-spec.md` and `.ai/guidelines/media-embeds.md` to record this exception to the prior URL-only rule.

## Job 5 - Presenter/Template Touch For `title_prefix`

Current public combined-title behavior lives in these places:

- `PublicContentItemCardPresenter` sets card `title` to `contentGroup->title + groupTitleSeparator + item->title` when group badge mode is `combined_title`.
- `PublicContentItemCardPresenter::textValue()` maps `content_item.title` to that prepared title.
- `ShowContentItem::getTitle()` and `resources/views/filament/public/pages/show-content-item.blade.php` render the raw item title.
- `resources/views/components/public/content-item-row.blade.php` still renders `$item->title` directly.
- `resources/views/components/public/content-item-grid.blade.php` and `resources/views/filament/tables/columns/public-content-item-card.blade.php` route through the card presenter.
- `resources/views/components/public/item-page-podcast-identity.blade.php` renders the separate podcast identity label.

EP1 should add a small display-title boundary, for example `ContentItemDisplayTitle`, with:

- `prefix(ContentItem $item): string` returning `title_prefix` when filled, otherwise the content group title.
- `combined(ContentItem $item, string $separator): string`.
- Existing public combined title mode calls this boundary.
- Item page title can either show the combined title where configured or keep the current title plus podcast identity. The required EP1 change is that any combined public display uses `title_prefix ?? group title`.

Tests to update or add:

- Card-template/content-item presenter output for combined title with prefix.
- Combined title fallback to group title when prefix is null.
- Public item page title/header behavior if EP1 enables combined title there.
- Row component or any remaining direct public list title rendering if still used by active surfaces.

## Job 6 - Paste Cleanup And Bracket Conventions

Filament 5 MarkdownEditor behavior:

- Vendor docs state the editor outputs raw Markdown and HTML and sends it to the backend; rendering must sanitize.
- PodText already stores Markdown and public rendering goes through safe rendering paths.
- The MarkdownEditor JavaScript creates an EasyMDE instance, syncs CodeMirror changes back to Livewire state, supports an optional `setUpUsing` callback in the JS component, and watches state changes.
- The current Blade view for the MarkdownEditor does not expose a PHP `setUpUsing` API directly in the server component surface.
- EasyMDE's drop/paste handler handles image files; it does not convert rich Google Docs/Colab HTML paste into clean Markdown.

Bounded hook design:

- Add an app-owned admin JavaScript boundary later, not EP1-R, that finds workspace transcript MarkdownEditor roots and attaches a CodeMirror `paste` listener after EasyMDE initialization.
- The listener should read clipboard HTML/plain text, call a `TranscriptPasteNormalizer` function, prevent default only when the normalizer returns a transformed value, and insert Markdown into CodeMirror so Livewire state sync remains native.
- Keep `[]` styling/convention conversion deferred until the pending WB format probe records the real source shapes.
- Server-side storage remains Markdown only; no raw rich HTML should be stored as a transcript feature.

## R-Decisions

1. R1 - Use native relationship-bound form sections, but bind them to a dedicated `HasOne` workspace transcription relation rather than the existing `featuredTranscription` `BelongsTo`. Evidence: Filament supports `HasOne`/`BelongsTo`/`MorphOne`, auto-creates missing related records, and for `HasOne` calls `$relationship->save($record)`, which fills `transcriptions.content_item_id` after the episode exists. Rejected alternative: direct `featuredTranscription` binding, because Filament's `BelongsTo` create path saves the child before association and does not populate the required `content_item_id`.
2. R2 - Synchronize `featured_transcription_id` explicitly in workspace page hooks after the relationship save. Evidence: the workspace relation writes the child through `content_item_id`; D-EP1 requires newly-created or unpinned workspace targets to become featured. Rejected alternative: rely only on `Transcription::created()` first-transcription auto-pin, because it does not cover adopting an existing unpinned target.
3. R3 - Reject the Create-Wizard fallback for EP1. Evidence: Filament's wizard next-step path validates the step but does not commit the parent record until final submit unless custom code is added. Rejected alternative: custom between-step persistence, because it is more glue than the surviving `HasOne` relationship binding.
4. R4 - Reject save-draft-first as a fallback unless relationship binding fails in implementation. Evidence: it introduces a multi-request draft lifecycle and error-preservation problem. Rejected alternative: auto-save draft when adding transcription mid-create, because it is more failure-prone than a single create transaction.
5. R5 - Implement the workspace as `CreateRecord` and `EditRecord` resource pages under `ContentItemResource`, not a standalone Livewire page. Evidence: those page bases already own transactions, resource routing, form state, relation saving, header actions, and authorization. Rejected alternative: standalone Livewire page, because it would recreate resource page plumbing.
6. R6 - Override table row URLs/default actions to the workspace URL while keeping classic edit as a secondary action. Evidence: Filament supports `$table->recordUrl()` and current list/relation tables share explicit record actions. Rejected alternative: only add a separate workspace action, because Yoni decided it becomes the default edit path.
7. R7 - Add a separate `AdminUxSettings` settings class and settings page for admin workspace preferences. Evidence: the existing Spatie settings setup registers typed classes, settings migrations live in `database/settings`, and `SettingsPage` provides fill/save behavior. Rejected alternative: store admin workspace controls in `PublicContentSettings`, because these are admin UX defaults, not public rendering config.
8. R8 - Defer per-user presentation-mode overrides from EP1. Evidence: `users` has no `admin_preferences` column, and adding one creates schema, cast, precedence, and mode-switcher tests beyond the core workflow. Rejected alternative: add per-user preferences now, because global settings cover the first EP1 release.
9. R9 - Add `content_items.title_prefix` as a nullable string and use it only for display composition. Evidence: no such column exists, `title` is already the stored episode title, and D-EP3 forbids storing combined title. Rejected alternative: store the combined podcast/episode title in `title`, because it would corrupt search/import/export semantics.
10. R10 - Allow trusted admin embed HTML only in `content_items.embed_html`, rendered verbatim only through the owned public media-embed component raw mode, with precedence over `embed_url`. Evidence: D-EMB1 intentionally supersedes the prior URL-only assumption and matches the D30 trusted-maintenance-HTML model; existing `ApprovedEmbedUrl` still protects the URL path. Rejected alternative: keep URL-only storage, because Yoni explicitly chose trusted embed HTML for this field; also rejected rendering raw HTML through Markdown, cards, tables, or generic presenters because the trust exception is component-scoped.
11. R11 - Wrap Spotify episode lookup in a new app service that reuses the WB1 connector. Evidence: `SpotifyConnector` already validates Spotify connections and fetches episode metadata through a bound client factory. Rejected alternative: call HTTP directly from a form action, because WB7 later needs the same normalization boundary.
12. R12 - Centralize public display title composition so combined displays use `title_prefix ?? group title`. Evidence: combined card title logic currently lives in `PublicContentItemCardPresenter`, while item page and row templates still render raw titles. Rejected alternative: patch only the card presenter, because item pages/templates would drift.
13. R13 - Use an admin JavaScript paste-transform boundary for transcript cleanup and defer bracket conversion rules to the WB format probe. Evidence: Filament MarkdownEditor stores raw Markdown/HTML and EasyMDE handles image paste only; the real `[]` source conventions are not documented in code yet. Rejected alternative: hard-code bracket/rich-paste conversion in EP1, because the source shapes are still pending research.
