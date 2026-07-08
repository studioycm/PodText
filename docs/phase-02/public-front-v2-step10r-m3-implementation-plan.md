# Public Front v2 Step 10R-M3 Implementation Plan

## 1. Selected Mini-Step and Dependencies

Selected mini-step: Step 10R-M3 - Public transcription policy, query scopes, aggregators, and counts.

Dependencies verified:

- Step 10R-M1 is complete.
- Step 10R-M2 is complete.
- `author_transcription` exists.
- `author_content_item` has been removed.
- Step 10R-B4 remains paused until M1-M6 complete.

## 2. Current Local Repo Evidence

- HEAD: `e813513 feat: replace episode authors with transcription transcribers`.
- Working tree was clean before M3, then the central ledger was marked in progress.
- Migration status shows M1 and M2 migrations ran.
- Public routes for `/podcasts`, `/contributors`, and `/search` are present.
- Prompt 13, Step 11, and Step 9F/10F have not started.

## 3. Files Inspected

- `app/Settings/PublicContentSettings.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicFront/PublicFrontRenderContext.php`
- `app/Support/PublicFront/PublicFrontRenderContextFactory.php`
- `app/Models/Author.php`
- `app/Models/ContentGroup.php`
- `app/Models/ContentItem.php`
- `app/Models/Transcription.php`
- `app/Support/PublicContent/PublicContentItemQueries.php`
- `app/Support/PublicContent/PublicContributorDiscovery.php`
- `app/Support/PublicFront/Groups/PublicContentGroupQueries.php`
- `app/Support/PublicFront/Sections/PublicDisplaySectionQueryResolver.php`
- public Livewire components for search, podcast details, contributor directory, contributor items, top transcribers, and transcript viewer
- public card presenters for item, group, and contributor cards
- public-front settings tests and contributor/podcast public tests

## 4. Laravel Boost Findings

Boost confirmed installed package versions, schema shape, and aggregate-query support in Laravel. The database currently has multi-transcriber pivot rows matching transcription compatibility authors. Laravel query builder subselects and relationship eager loading are suitable for M3.

## 5. FilamentExamples MCP Findings

FilamentExamples access was search/snippet only. The useful patterns were focused aggregate services, settings-page field state, and public page data prepared through support classes. No public Filament Table pattern will be adopted.

## 6. Old Front-Card Leftovers Found

Card renderers still use the `authors` data key internally for transcriber badges, but the values now come from effective transcription transcribers. Renaming that compatibility key is deferred to M4/B4 to avoid broad rendering churn in M3.

## 7. Current Model/Relationship Reality

- `Transcription::authors()` is the many-to-many transcriber relation.
- `Transcription::author()` and `transcriptions.author_id` remain compatibility/primary transcriber storage.
- `Author::authoredTranscriptions()` exists.
- `Author::transcriptions()` remains legacy hasMany compatibility.
- `ContentItem::authors()` and `Author::contentItems()` are absent.

## 8. Settings/Render-Context Impact

Add normalized `transcription_policy` to `PublicContentSettings`, settings migrations, registry defaults/schema, validator, settings page fields, and `PublicFrontRenderContext`.

## 9. Card-Template/Rendering Impact

No card-template renderer changes are planned in M3. Existing cards will receive updated query/count attributes where already used. Full transcriber/aggregate card attribute expansion is M4.

## 10. Livewire/Blade Impact

Use policy-aware query helpers for public transcriber options and filters where safe. Keep Livewire URL aliases unchanged.

## 11. Admin/Import/Export Impact

No admin import/export behavior changes are planned in M3. M2 already converted admin/import/export to transcription transcribers.

## 12. Query/Scopes/Aggregation Impact

Add:

- `PublicTranscriptionPolicy`
- `PublicTranscriptionSelector`
- `PublicTranscriptionAggregates`

Update:

- `PublicContentItemQueries`
- `PublicContributorDiscovery`
- `PublicContentGroupQueries`
- search transcriber options/filter usage where safe

## 13. Episode-Author Removal Impact

M3 must not recreate item authors. Public counts must use `author_transcription`, not `transcriptions.author_id`.

## 14. Tests to Add/Update

Add focused tests for:

- normalized/default policy settings;
- featured-only item count selection;
- all-published count selection;
- first transcription remains featured;
- content-group aggregate word/read-time/count behavior;
- contributor/top-transcriber counts using pivot and respecting policy;
- draft/unpublished transcriptions excluded.

Update existing contributor expectations where default behavior changes from counting all published rows to counting one effective transcription per item.

## 15. Exact Files to Change

Planned:

- `app/Settings/PublicContentSettings.php`
- `app/Providers/AppServiceProvider.php`
- `app/Support/PublicContent/PublicTranscriptionPolicy.php`
- `app/Support/PublicContent/PublicTranscriptionSelector.php`
- `app/Support/PublicContent/PublicTranscriptionAggregates.php`
- `app/Support/PublicContent/PublicContentItemQueries.php`
- `app/Support/PublicContent/PublicContributorDiscovery.php`
- `app/Support/PublicFront/Groups/PublicContentGroupQueries.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicFront/PublicFrontRenderContext.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `database/settings/*add_public_transcription_policy_setting.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- focused public feature tests

## 16. Risks/Conflicts

- Contributor count expectations change under the new default `featured_only` policy.
- SQL aggregate helpers must work on SQLite and avoid raw user input.
- Rendering changes must stay limited so M4 remains the public rendering mini-step.

## 17. Out of Scope

- Step 10R-M4 public rendering updates.
- Step 10R-M5 grouped part/label/icon rendering.
- Step 10R-B4 card-options convergence.
- Full Step 2 publication workflow.
- Removing `transcriptions.author_id`.
- Any public table reintroduction.

## 18. Stop Conditions

Stop before coding if policy normalization cannot coexist with existing settings migrations, if `author_transcription` is absent, or if query changes require reintroducing item authors.
