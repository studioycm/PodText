# Public Front v2 Step 10R-UX3 MCP Research

Date: 10/07/2026

## Scope

Step 10R-UX3 adds Hebrew-aware slug generation, aligns admin form slug UX with the
server slug contract, repairs Spatie content-tag slug generation for Hebrew names, and
hardens import key/slug validation against MySQL column limits.

This run is explicitly limited to UX3. S1c inline locks and Importer Workbench were not
run.

## Local Repository Evidence

- `git status --short --branch` reported clean `main...origin/main`.
- Recent history includes `ada29fb feat: add settings import locks and add-only mode`,
  `f719d30 fix: bound snapshot index column lengths for mysql`, and
  `e94a8d9 docs: add UX3 and S1c codex prompt files`.
- The application default database connection is `mysql`.
- `php artisan migrate` completed with `INFO Nothing to migrate`.
- The central ledger had no UX3 row before this run. S1c had not run. HF2 was the latest
  completed settings-arc row, so UX3 was inserted immediately after HF2 and before the
  paused normal queue.
- The five sluggable models have duplicated private `uniqueSlug()` methods:
  `Author`, `Category`, `ContentGroup`, `ContentItem`, and `HomepageSection`.
  `ContentItem` is the only scoped slug, using `content_group_id`.
- Existing form code already had `live(onBlur: true)` for some slug sources, but it used
  `Str::slug()` and still required manual slug values. `AuthorForm` had no form-side
  slug sync.
- `ContentTag` extends Spatie Tags 4.12. The installed vendor `HasSlug` trait calls
  `generateSlug($locale)`, which by default uses `config('tags.slugger')` and then
  `Str::slug()`.
- `config/tags.php` currently has `slugger => null`, so Hebrew tag names produce empty
  slug translations unless the app overrides this behavior.
- Boost schema confirmed relevant MySQL limits:
  - `reference_key` columns are `char(26)` on authors, content groups, content items,
    and transcriptions.
  - slugs are `varchar(255)`.
  - `content_items.media_url`, `embed_url`, `external_thumbnail_url`, and
    `direct_media_url` are `varchar(2048)`.
  - `content_items.embed_provider` is `varchar(50)`.

## Laravel Boost Findings

Tools used: `application_info`, `database_schema`, and `search_docs`.

- Boost confirmed installed versions: PHP 8.4, Laravel 13.18.0, Filament 5.6.7,
  Livewire 4.3.3, Pest 4.7.4, Tailwind 4.3.2, and MySQL.
- Filament 5 source fields use `live(onBlur: true)` with
  `afterStateUpdated(Set $set, Get $get, ...)` for form-side derived state.
- Filament 5 relationship option modal forms use `Select::createOptionForm()` and
  optional `createOptionUsing()`; those modals can reuse the same slug field helper.
- Filament 5 importers use `ImportColumn::rules()` to validate row values before save.
  Failed validation rows are written to failed-row output instead of becoming unhandled
  database errors.
- Filament 5 select option action objects can be customized through
  `createOptionAction()` / `editOptionAction()`, but UX3 does not need modal shape
  changes beyond shared slug components.

## FilamentExamples Findings

Access level: `search_examples` snippet/search access only. No separate source/read/fetch
tool was exposed.

Initial query batch:

- `TextInput live onBlur slug`
- `hint action TextInput`
- `form slug unique validation`
- `relationship option modal form`
- `Filament import column validation`
- `Spatie tags slug`

Refined query batch:

- `TextInput hintAction Action`
- `suffixAction TextInput regenerate`
- `createOptionForm Select relationship`
- `ImportColumn rules validation`
- `Filament importer row validation`
- `TagResource Spatie tags form`

Relevant examples and PodText adaptation notes:

- **WordPress-Style Blog Post Form**
  - File/class: `app/Filament/Resources/Posts/Schemas/PostForm.php`.
  - Pattern to copy: source `TextInput` uses `live(onBlur: true)` and
    `afterStateUpdated()` with `Set` to derive `slug`.
  - Pattern to avoid: `Str::slug()`, because it strips Hebrew.
  - PodText adaptation: a shared `SlugInput` helper will use the app-owned
    `HebrewSlugger` and only fill an empty slug on source blur.
- **Blog CMS With Filament Admin**
  - File/class: `app/Filament/Resources/Posts/Schemas/PostForm.php`.
  - Pattern to copy: normal Resource schema composition with `TextInput` source/slug
    fields and relationship selects.
  - Pattern to avoid: required slug fields as the only create path.
  - PodText adaptation: slug fields become optional with max length and existing unique
    rules; model hooks remain the server truth.
- **Fill Form Field Using OpenAI API**
  - File/class: `app/Filament/Resources/JobOffers/Schemas/JobOfferForm.php`.
  - Pattern to copy: `Select::createOptionForm()` and `createOptionUsing()` modal
    workflow for inline relationship creation.
  - Pattern to avoid: unrelated AI service calls and custom tag service creation.
  - PodText adaptation: relationship option forms can reuse the same author/category/
    content-group slug helper without changing selector ownership.

## Spatie Tags Evidence

Installed package evidence came from `vendor/spatie/laravel-tags/src/HasSlug.php`:

- `bootHasSlug()` regenerates slug translations on every save from translated `name`
  locales.
- `generateSlug(string $locale)` is the correct override point if behavior should live
  on the app's custom `ContentTag` model.
- `config('tags.slugger')` is another global callback option. UX3 uses the model
  override so the behavior stays explicit on `ContentTag` and avoids surprising any
  future non-content tag model.

## Implementation Implications

- Add `App\Support\Slugs\HebrewSlugger` for pure Hebrew-aware slug normalization and
  reusable unique suffixing.
- Replace duplicated private model slug methods with calls into the shared slugger while
  preserving each creating hook and `ContentItem` group scope.
- Override `ContentTag::generateSlug()` to use the shared slugger for each locale.
- Add an idempotent artisan command to repair empty, punctuation-only, or ULID-like
  tag slug translations without renaming ordinary historical public slugs.
- Add a shared `SlugInput` form helper, mirroring `IconSelect`, for source/slug field
  pairs, optional scope callbacks, and a regenerate hint action.
- Apply the helper to the five Resource forms and the author/category/content-group
  relationship option modals. `ContentTagForm` remains disabled-display for slug.
- Add `max:26` to reference-key importer columns and reference-key relationship columns
  where those values target `char(26)` references.
- Keep slug import rules at `max:255` and the item importer group-scoped unique rule.
- Align `ContentItemForm`/importer `embed_provider` to `maxLength(50)` / `max:50`.

## Stop Conditions

- Stop if the tree is dirty before implementation.
- Stop if local migration preflight fails on MySQL.
- Stop if UX3 cannot be inserted after HF2 without running S1c or Importer Workbench.
- Stop if the fix would require renaming existing public slugs, widening columns,
  adding public route changes, or creating custom import controllers.
