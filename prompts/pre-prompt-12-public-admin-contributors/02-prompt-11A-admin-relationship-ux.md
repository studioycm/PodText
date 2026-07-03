# Prompt 11A: Admin Relationship UX

## Goal

Improve admin content-management speed and relationship UX before Prompt 12.

Implement:
- create/edit option modals for practical relationship selectors;
- `ContentItemsRelationManager` under `ContentGroupResource`;
- tests.

## Scope

Allowed:
- Filament admin forms/resources/relation managers;
- helper form-schema methods if they reduce duplication;
- translations/helper text;
- tests;
- current-state update.

Out of scope:
- no public homepage/search refactor;
- no public contributor pages;
- no Prompt 12 item page/parser/media work;
- no import/export changes;
- no Shield/permissions install;
- no broad admin redesign.

## Read first

- `AGENTS.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/ai-development-lessons.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/research/filament-examples-admin-resource-relation-managers.md`
- `docs/phase-02/blueprints/09-admin-content-management-blueprint.md`
- `.ai/guidelines/tooling-quality.md`
- `.ai/guidelines/transcriptions.md`
- `.ai/guidelines/taxonomy-tags.md`
- `.ai/guidelines/settings-dashboard.md`

Inspect:
- `app/Filament/Resources/ContentGroups/ContentGroupResource.php`
- `app/Filament/Resources/ContentGroups/Schemas/ContentGroupForm.php`
- `app/Filament/Resources/ContentItems/ContentItemResource.php`
- `app/Filament/Resources/ContentItems/Schemas/ContentItemForm.php`
- `app/Filament/Resources/ContentItems/Tables/ContentItemsTable.php`
- `app/Filament/Resources/ContentItems/RelationManagers/TranscriptionsRelationManager.php`
- `app/Filament/Resources/Transcriptions/**`
- `app/Filament/Resources/Categories/**`
- `app/Filament/Resources/HomepageSections/**`
- `app/Filament/Resources/ContentTags/**`
- existing admin tests.

## Research / docs verification

Use Laravel Boost `search_docs` and installed source for current Filament 5 syntax before coding:
- `Select::relationship()`;
- `createOptionForm()`;
- `createOptionUsing()`;
- `editOptionForm()`;
- `createOptionAction()`;
- `editOptionAction()`;
- relation managers;
- `getRelations()`;
- stable relation URL key;
- `CreateAction`;
- `AssociateAction`;
- sharing Resource form/table definitions with a relation manager;
- `hiddenOn()` where applicable.

Use FilamentExamples MCP only if useful for relation-manager or admin form patterns.

## Preflight

Run:
- `git status --short --branch`
- `git log --oneline --decorate -12`

Confirm:
- Prompt 11R is complete if it was run.
- Prompt 12 has not started.
- Working tree is clean.

Run baseline:
- `php artisan test`
- `vendor/bin/pint --test`
- `vendor/bin/filacheck`
- `npm run build`

Stop if baseline fails outside this prompt scope.

## Implementation requirements

### 1. Relationship selector audit

Audit all Filament admin relationship selectors and classify each as:

1. Simple — add create and edit option modal.
2. Medium — add create only if schema is short and safe.
3. Complex — do not add create option; use relation manager or full Resource page.

Record the audit in the final report and, if useful, in a short doc section.

### 2. Add create/edit option modals where appropriate

Likely simple/medium candidates:
- `ContentItemForm::content_group_id`;
- `ContentItemForm::authors`;
- `ContentItemForm::categories`;
- `ContentGroupForm::categories`;
- `CategoryForm::parent_id`;
- `TranscriptionForm::author_id`;
- `HomepageSectionForm::category_id`;
- `HomepageSectionForm::content_group_id`.

Possible but verify:
- `HomepageSectionForm::tag_id` if implemented as a normal `Select` to `ContentTag`.

Do not blindly add create modals to:
- `featured_transcription_id` — create transcriptions through the item relation manager;
- Spatie `SpatieTagsInput` — do not replace plugin behavior with custom pivot logic;
- complex `content_item_id` selectors unless a minimal safe create form is clearly tested.

Modal forms must:
- use translation keys;
- use helper text where fields are technical;
- preserve slug auto-generation where applicable;
- keep date fields day-first/Asia-Jerusalem where applicable;
- avoid broad duplicated form schemas if shared simple schema helpers are better.

### 3. ContentItemsRelationManager under ContentGroupResource

Add:

`App\Filament\Resources\ContentGroups\RelationManagers\ContentItemsRelationManager`

Register it in `ContentGroupResource::getRelations()`.

Rules:
- owner relation: `contentItems`;
- list items for the current group;
- create item under the current group;
- hide or prefill `content_group_id` because owner context supplies it;
- edit item;
- delete only if existing policies/conventions allow;
- add record action to open full ContentItem edit page;
- add action to add transcription if existing ContentItemsTable action can be reused safely, otherwise defer and document;
- stable relation title/key so URLs do not rely on numeric relation index.

Prefer sharing `ContentItemResource::form()` and `ContentItemResource::table()` patterns where safe, but avoid pulling in fields/actions that break owner-context UX.

### 4. Tests

Add/update tests for:
- create option creates an Author from a selector and selects/attaches it;
- create option creates a Category from a selector and selects/attaches it;
- create option creates a ContentGroup from ContentItem `content_group_id` where implemented;
- edit option updates a selected simple related record where implemented;
- complex selectors are intentionally not create-enabled and documented;
- ContentGroup edit page renders ContentItemsRelationManager;
- ContentItemsRelationManager CreateAction creates a content item owned by the current group;
- owner `content_group_id` is hidden/prefilled in relation-manager create form;
- relation manager edit works;
- relation manager does not break existing ContentItemResource tests;
- full admin quality gates pass.

## Documentation update

If successful, update `docs/phase-02/current-project-state.md` before commit:
- mark Prompt 11A complete;
- keep Prompt 11B next/not started;
- record relationship selector policy;
- record ContentItemsRelationManager;
- record exceptions/deferred selectors.

Patch other docs only if stable requirements changed.

## Quality gate

Focused:
- `php artisan test --filter=AdminPhase02ResourcesTest`
- add/run a focused relation-selector test if separate.

If PHP changed:
- `vendor/bin/pint --dirty --format agent`

Final:
- `php artisan test`
- `vendor/bin/pint --test`
- `vendor/bin/filacheck`
- `npm run build`

Do not run `vendor/bin/filacheck --fix`.

## Commit

Commit only after full gate passes:

`feat: improve admin relationship management ux`

## Final report

Include:
- selector audit categories;
- selectors changed;
- selectors intentionally not changed and why;
- ContentItemsRelationManager behavior;
- tests added/updated;
- commands/results;
- FilaCheck summary;
- commit hash if committed;
- current git status;
- confirm Prompt 12 was not started.

End with exactly:

“Prompt 11A admin relationship UX is complete. Prompt 12 has not been started.”
