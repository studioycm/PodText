# Media Operations UX3 Mini-task 3A Owner Image Choice and Commit Implementation Plan

> **Status: implemented locally.** Canonical outcome and calibrated proof are
> recorded in
> `docs/phase-02/media-operations-ux3-mini3a-owner-image-choice-and-commit-handoff.md`.
> Do not re-execute this plan.

> Do not execute this plan until a fresh Laravel Simplifier Stage 1 has issued
> an Audit ID and Option ID and a new operator message explicitly approves
> both. The relation-manager attachment lifecycle is mandatory scope and must
> not be deferred.

## 1. Execution contract

Execute sequentially in the current checkout. Preserve all operator-owned
baseline changes listed in the technical research. Do not create a worktree,
stash, revert, dependency, migration, live-database probe, production change,
push, or publication.

Use strict TDD:

1. add the smallest focused expectation for one approved behavior;
2. run it and record the expected RED result;
3. implement only enough to make it GREEN;
4. refactor while focused tests remain GREEN;
5. continue to the next behavior;
6. run browser work serially;
7. perform the requirements/diff/audit sweep;
8. after the final file change, run final gates in mandatory order:
   `vendor/bin/pint --test`, `vendor/bin/filacheck`, `npm run build`, then
   full serial `php artisan test` last;
9. any later file edit restarts at Pint;
10. create the implementation commit with the handoff hash pending, then
    immediately create the docs-only hash-stamp commit.

## 2. Persistence, authority, and stop conditions

No new table, column, index, migration, relationship, model, setting, queue,
cache, package, dependency, policy, ability, durable task state, or file
operation is planned.

Reuse:

- `MediaRecordScope`;
- `MediaIdentityResolver`;
- `MediaAttachmentIdentityResolver`;
- `MediaAttachmentFormState`;
- `MediaAttachmentManager`;
- `MediaInventoryDiagnostics`;
- `MediaAcquisitionManager`;
- `OwnerImagePresenter`;
- `PublicDefaultImageResolver`;
- `PublicContentSettings`;
- existing owner and Media policies;
- existing Filament actions, fields, modals, and settings pages.

Stop for an amended Stage 1 before implementing if work requires:

- a migration or dependency;
- a new authorization ability;
- a new Media/file mutation;
- durable draft/selection/session state;
- a generic picker behavior change that cannot be isolated from owner mode;
- 3B receipt/retry semantics;
- 3C file operations;
- reason-specific repair/Recheck/Retry;
- Package 5 lifecycle authority;
- a materially different class set or forecast.

## 3. Task 1 — Lock the presentation and stale-state contracts

### Tests first

Extend `tests/Feature/OwnerImageWorkspaceTest.php` with focused expectations
for:

- podcast and episode owner/slot labels;
- direct Media and shown-now Media represented separately;
- inherited podcast cover, external URL, configured default, global default,
  no image, broken direct, denied, and unsafe SVG states;
- unchanged versus replacement versus automatic/fallback pending state;
- saved reference key/Media ID for clear-pending behavior;
- only authorized Media Details URLs;
- user content preserved as content, not translated;
- no generic repair, Recheck, Retry, rename, swap, delete, or download action
  in the owner-choice projection.

Extend `tests/Feature/MediaAttachmentModelTest.php` where the shared stale
exception/manager contract changes, proving equivalent attach, replace,
detach, authorization, and expected-identity protection for both current
roles: `cover` and `primary_image`.

Run the new test subset to RED.

### Production files

Add:

- `app/Support/Media/OwnerImageChoicePresentation.php`;
- `app/Support/Media/OwnerImageChangedException.php` if the stale mismatch
  cannot be distinguished safely without it.

Modify:

- `app/Support/Media/OwnerImagePresentation.php`;
- `app/Support/Media/OwnerImagePresenter.php`;
- `app/Support/Media/MediaAttachmentManager.php`;
- `app/Support/Media/MediaAttachmentFormState.php`.

Implementation:

- keep the current details projection available to existing table/details
  callers;
- add a focused `choice(...)` projection that receives the current pending
  field identity and a commit-boundary descriptor;
- reuse current fresh-owner, attachment, diagnostic, fallback, preview, and
  policy decisions;
- project direct, shown-now, and pending Media independently;
- compare pending identity to the saved direct identity without changing it;
- expose automatic/fallback only for a safely detachable direct attachment;
- preserve the unsafe-legacy replacement path but do not expose unsafe detach
  inside the chooser;
- replace only the expected-identity mismatch `RuntimeException` with a
  specific exception; leave other runtime failures distinct;
- let owner UI callers refresh/reconfirm that specific stale state.

Rerun the focused tests to GREEN.

## 4. Task 2 — Make the custom field owner-aware

### Tests first

Extend `tests/Feature/OwnerImageWorkspaceTest.php` and
`tests/Feature/AppOwnedMediaPickerTest.php` to prove:

- owner mode renders the shared owner-state block;
- a generic field does not render it;
- owner mode receives a server-owned saved Media identity;
- clear pending restores the saved identity;
- selecting automatic/fallback sets a pending null identity without detaching;
- owner selection remains limited to one Media;
- forged owner-mode/purpose/saved-identity state cannot broaden access;
- selected and Details content uses semantic direction.

Run the new expectations to RED.

### Production files

Modify:

- `app/Filament/Forms/Components/PathCuratorPicker.php`;
- `resources/views/filament/forms/components/path-curator-picker.blade.php`.

Add:

- `resources/views/filament/forms/components/owner-image-choice-state.blade.php`.

Implementation:

- add a lazily evaluated owner-choice presentation callback;
- add explicit `isOwnerChoice()` state independent of inline layout;
- render the state view before the generic selected summary;
- suppress generic View/Edit/Download/Remove summary actions in owner mode;
- add server methods/actions for restore-saved and eligible
  automatic/fallback pending state;
- pass `isOwnerChoice` and the trusted saved Media ID to nested/inline
  `MediaPickerPanel`;
- add distinct selection and restore events so parent and child remain in sync;
- preserve the existing nested close and exact focus restoration;
- preserve the entire non-owner field contract.

Rerun focused tests to GREEN.

## 5. Task 3 — Implement the owner-choice Gallery/source behavior

### Tests first

Extend `tests/Feature/AppOwnedMediaPickerTest.php` to prove owner mode:

- mounts with All Media active;
- exposes Gallery, Upload, URL, and Storage as one source navigation;
- does not expose Current folder;
- dispatches a valid single Gallery tile immediately as pending selection;
- keeps the inline dedicated-action picker open after tile selection;
- lets the nested field handler close after selection;
- clears pending back to the saved owner selection without committing;
- exposes one authorized Details new-tab route per eligible card;
- omits View, Download, Edit, Rename, Swap, Delete, bulk delete, and
  Use selected;
- retains permanent Upload/URL/Storage behavior and permanence copy;
- keeps multi-upload admission truth: all successful Media remain permanent
  and no arbitrary batch member becomes the owner choice;
- leaves generic picker behavior and existing tests unchanged.

Run each new subset to RED before production changes.

### Production files

Modify:

- `app/Livewire/Admin/MediaPickerPanel.php`;
- `resources/views/livewire/admin/media-picker-panel.blade.php`;
- `app/Support/Media/MediaRecordProjector.php` only if the existing authorized
  projection lacks the exact Details URL needed by owner cards.

Implementation:

- add locked `isOwnerChoice` and saved-owner identity inputs;
- set All Media and Gallery active at owner-mode mount;
- retain current bounded inventory/search/pagination;
- branch only the owner-mode layout into top-level
  Gallery/Upload/URL/Storage;
- use five columns at the accepted desktop width and two at narrow width;
- make tile choice update the pending parent state immediately;
- use the existing acquisition actions unchanged;
- after a single successful admission, dispatch that Media as pending;
- after multi-file admission, preserve all permanent successes but require an
  explicit single Gallery choice;
- implement restore-saved synchronization without an owner or file mutation;
- expose Details only after current Media update authorization;
- leave generic mode’s folder filter, insertion action, multi-select, and
  maintenance actions unchanged.

Rerun the entire `AppOwnedMediaPickerTest.php` file to GREEN.

## 6. Task 4 — Replace the dedicated action workspace with one canonical modal

### Tests first

Extend `tests/Feature/OwnerImageWorkspaceTest.php` to prove:

- owner-specific podcast/episode headings and descriptions;
- Add versus Change wording follows saved direct state;
- submit labels are Save podcast cover / Save episode image;
- one schema reading order: state block then source chooser;
- no Replace/Details tabs;
- no extra immediate Remove Direct or Import External footer submit;
- direct removal is pending automatic/fallback and commits only through the
  primary submit;
- Gallery selection is pending until primary submit;
- cancel leaves the owner unchanged;
- successful owner save writes exactly one canonical attachment;
- legacy slide-over setting cannot change owner-image actions;
- sticky header/footer and scoped modal attributes are configured;
- unauthorized actor and unselectable Media remain rejected.

Add stale tests:

1. open action and retain snapshot A;
2. independently commit owner image B;
3. submit pending image C from the stale action;
4. assert no overwrite, refreshed current evidence B, pending C preserved,
   updated expected snapshot, and explicit review/reconfirm error;
5. submit again without another concurrent change;
6. assert C commits exactly once.

Run to RED.

### Production files

Modify:

- `app/Filament/Actions/ContentImageActions.php`;
- `resources/views/filament/actions/current-content-image.blade.php` only if
  existing non-choice details callers still need a compatibility view;
- `app/Filament/Pages/AdminUxSettings.php`;
- `tests/Feature/EpisodeWorkspaceTest.php`.

Implementation:

- configure one `Width::SevenExtraLarge` modal;
- use sticky native header/footer and scoped modal window attributes;
- remove owner-image `Tabs` and extra mutation footer actions;
- render the owner-aware field and inline owner picker in one schema;
- set owner-specific heading, description, submit label, and opener
  attributes;
- always use the modal; remove `applyConfiguredContainer()` and its setting
  read;
- remove only the `tb1_picker_container` selector from Admin UX;
- retain the property, enum, stored value, and migration as inert
  backwards-compatible data;
- preserve separate existing Spotify import and unsafe-legacy actions;
- catch only the specific stale mismatch, refresh expected snapshot and
  presenter evidence, preserve pending choice, and require a second submit.

Rerun focused action/settings tests to GREEN.

## 7. Task 5 — Apply shared state and complete persistence across owner forms

### Tests first

Extend appropriate feature files:

- `tests/Feature/OwnerImageWorkspaceTest.php`;
- `tests/Feature/AdminPhase02ResourcesTest.php`;
- `tests/Feature/EpisodeWorkspaceTest.php`.

Add relation-manager tests to prove:

- create episode with an image through the podcast Episodes relation modal;
- edit episode image through the same relation modal;
- outer relation cancel leaves the owner unchanged;
- admitted Media remains after relation cancel;
- attachment row uses role `primary_image`;
- owner compatibility `image_path` matches the attached Media path;
- replacing and clearing use the same prepare/persist validation as full
  episode forms;
- unsafe legacy selection behavior remains truthful;
- stale relation edit refreshes/reconfirms instead of overwriting;
- the table and Create/Edit modal headings say localized Episodes/Episode,
  never the generic English Content Items in Hebrew.

Add full-page form tests for podcast and episode edit stale conflicts and
create/edit attachment persistence.

Run each test subset to RED.

### Production files

Modify:

- `app/Filament/Resources/ContentGroups/Schemas/ContentGroupForm.php`;
- `app/Filament/Resources/ContentItems/Schemas/ContentItemForm.php`;
- `app/Filament/Resources/ContentItems/Schemas/EpisodeWorkspaceForm.php`;
- `app/Filament/Resources/ContentGroups/Pages/CreateContentGroup.php`;
- `app/Filament/Resources/ContentGroups/Pages/EditContentGroup.php`;
- `app/Filament/Resources/ContentItems/Pages/CreateContentItem.php`;
- `app/Filament/Resources/ContentItems/Pages/EditContentItem.php`;
- `app/Filament/Resources/ContentItems/Pages/CreateEpisodeWorkspace.php`;
- `app/Filament/Resources/ContentItems/Pages/EditEpisodeWorkspace.php`;
- `app/Filament/Resources/ContentGroups/RelationManagers/ContentItemsRelationManager.php`.

Add a focused reusable page/action helper only if it removes the repeated
prepare/persist/stale-snapshot protocol without hiding authorization or
transaction boundaries.

Implementation:

- configure every podcast/episode image field with the shared presentation;
- capture the expected saved identity on existing-record surfaces;
- enforce expected identity on edit commits;
- on stale conflict, preserve form choice, refresh evidence/snapshot, and
  require a deliberate second Save;
- create localized Podcast Cover and Episode Image sections;
- move player/embed inputs into a separate Player and Embed section;
- keep description and technical metadata in their correct existing domains;
- add relation `CreateAction::mutateDataUsing()` to prepare image state;
- persist the canonical attachment in `after()` after the episode exists;
- add relation `EditAction::mutateRecordDataUsing()` for current picker and
  expected state;
- add Edit `mutateDataUsing()` and `after()` for prepare/persist;
- set explicit localized relation table and modal headings;
- keep internal `ContentItem` architecture unchanged.

The relation-manager lifecycle is a mandatory acceptance gate. Do not close
3A if its create/edit persistence proof is absent.

## 8. Task 6 — Give public-settings image slots the same state and stale truth

### Tests first

Extend:

- `tests/Feature/PublicMenuHeaderUxFixesTest.php`;
- `tests/Feature/PublicDefaultImagesSettingsTest.php`;
- `tests/Feature/PublicAboutPageContentTeamTest.php`.

Test:

- light and dark logo current/pending/committed state;
- global, episode, podcast, and contributor default modes and shown source;
- global inheritance versus custom versus none;
- About image blocks matched to persisted state by stable block key;
- team profiles matched by stable profile key;
- newly added block/profile has prospective state;
- outer Settings Save remains the only settings commit;
- nested picker cancel preserves unsaved settings state;
- navigation/reload discards pending owner choice but does not delete admitted
  Media;
- unrelated subject changes do not produce a stale conflict;
- same-subject Media identity change halts the first Save, refreshes evidence,
  preserves pending form state, and succeeds only after explicit second Save;
- forged or nonselectable Media remains rejected by existing normalization;
- HE/EN labels and semantic content direction.

Run the new expectations to RED.

### Production files

Add:

- `app/Support/Media/SettingsOwnerImagePresenter.php`;
- `app/Support/Media/SettingsOwnerImageSnapshot.php`.

Modify:

- `app/Support/PublicFront/PublicDefaultImageResolver.php`;
- `app/Filament/Pages/BuildsPublicContentSettingsSubjectSchemas.php`;
- `app/Filament/Pages/PublicContentSettingsSubjectPage.php`.

Implementation:

- expose one read-only default-family projection from
  `PublicDefaultImageResolver` so settings presentation cannot duplicate
  fallback logic;
- cache one persisted settings projection per request;
- map static logo/default slots directly;
- map About block/profile slots by stable key;
- configure each settings Media picker with the shared owner presentation;
- compute a deterministic subject-scoped Media identity fingerprint;
- store the baseline as server-owned Livewire state;
- compare against freshly loaded settings before the existing overlay/save;
- on conflict, preserve form state, update baseline, refresh presentation,
  notify, and halt without saving;
- refresh the baseline after a successful Save.

No settings schema or stored payload shape changes.

## 9. Task 7 — Localization, RTL/LTR, and responsive modal styling

### Tests first

Extend localization assertions in
`tests/Feature/OwnerImageWorkspaceTest.php` and the relevant settings/relation
tests for every new key in Hebrew and English.

Add assertions that:

- no raw/dotted key renders on the covered owner paths;
- generic relation wording is absent in Hebrew;
- UI controls localize;
- owner titles, filenames, and profile names render as content with
  `dir="auto"` or `<bdi>`;
- no translation attempts to translate user content.

Run to RED.

### Production files

Modify:

- `lang/he/admin.php`;
- `lang/en/admin.php`;
- `resources/css/filament/admin/theme.css`;
- the owner-state and picker Blade files from earlier tasks.

Add localized groups for:

- owner kinds and slots;
- direct, shown-now, and pending states;
- unchanged/replacement/automatic semantics;
- Add/Change and Save owner-image labels;
- clear-pending;
- chooser-only Details;
- cancel/permanent-admission truth;
- stale refresh/reconfirm;
- Podcast Cover, Episode Image, and Player and Embed sections;
- relation Episodes headings.

CSS:

- scope all overrides under the owner-image modal class;
- at narrow width, use viewport-sized modal geometry;
- retain one scrollable body with header/footer fixed;
- use logical properties for RTL/LTR;
- keep two gallery columns at 390 pixels and accepted desktop density;
- do not add global modal or generic picker overrides.

## 10. Task 8 — Browser and visual acceptance proof

Update:

- `tests/Browser/OwnerImageWorkspaceBrowserTest.php`;
- `tests/Browser/MediaPickerBrowserTest.php` only for generic-picker
  non-regression or shared nested-picker return behavior.

Run browser files serially. Use deterministic fixtures and
`Http::preventStrayRequests()`; do not use live network, live mail, local
development database, or uncommitted external assets.

Required browser matrix:

| Surface/state | HE RTL desktop | HE RTL 390 | EN LTR desktop | EN LTR 390 |
|---|---:|---:|---:|---:|
| Podcast dedicated modal | yes | yes | yes | yes |
| Episode dedicated modal | yes | yes | yes | yes |
| Episode Workspace image vs Player/Embed | yes | yes | yes | yes |
| Relation create/edit owner field | yes | yes | proportionate | proportionate |
| Settings shared owner state | yes | yes | yes | yes |

Prove:

- exact owner and slot heading;
- direct and shown-now evidence;
- Gallery tile creates one pending choice with no Use selected;
- one real Save remains visible;
- All Media default and no Current folder;
- only Details on a card, opened in a new tab;
- original modal/page retains pending choice and scroll after Details opens;
- Cancel owner no-op and admitted Media permanence copy;
- stale conflict current evidence refresh and deliberate second Save;
- native dialog semantics, focus trap, Escape, and exact opener focus return;
- relation row/field and settings field focus restoration;
- one body scroll; no nested scroll trap;
- no clipped header/footer/tile action at 390 pixels;
- keyboard selection and touch-visible action;
- no horizontal page overflow;
- no unexpected console error, page error, or accessibility violation.

Capture implementation screenshots outside the repository for the operator
outcome review. Name them as implementation evidence, never as the accepted
proposal.

## 11. Focused regression sequence

Before final gates, run affected tests serially:

1. `php artisan test tests/Feature/OwnerImageWorkspaceTest.php`;
2. `php artisan test tests/Feature/AppOwnedMediaPickerTest.php`;
3. `php artisan test tests/Feature/EpisodeWorkspaceTest.php`;
4. relation/resource-focused tests in
   `tests/Feature/AdminPhase02ResourcesTest.php`;
5. `php artisan test tests/Feature/PublicMenuHeaderUxFixesTest.php`;
6. `php artisan test tests/Feature/PublicDefaultImagesSettingsTest.php`;
7. `php artisan test tests/Feature/PublicAboutPageContentTeamTest.php`;
8. `php artisan test tests/Feature/MediaIssueReviewTest.php`;
9. existing attachment, legacy-owner, acquisition, and Media mutation
   regression files selected by the final diff;
10. importer/provider/conversion regressions when the shared attachment
    manager or form-state seam changed;
11. browser owner-image file;
12. browser picker file if changed shared behavior requires it.

Do not parallelize browser tests or the full suite.

## 12. Requirements and audit sweep

Before Pint:

- classify every accepted design bullet and `MUX3-F007`–`F016`,
  `F041`–`F044`;
- confirm EP01–EP14 coverage;
- confirm relation-manager lifecycle proof is present;
- inspect the final diff for unauthorized 3B, 3C, Mini-task 4, Package 5, or
  documentation-consolidation work;
- run Laravel Simplifier Stage 2 review;
- apply the repository Filament forms UX audit;
- review authorization, stale state, bidi, localization, narrow geometry,
  accessibility, performance/query count, and generic-picker non-regression;
- run PhpStorm inspections on changed PHP files when callable;
- run `git diff --check`.

## 13. Ordered final gates

After the final file change:

1. requirements sweep — GREEN;
2. `vendor/bin/pint --test` — GREEN;
3. `vendor/bin/filacheck` — GREEN;
4. `npm run build` — GREEN;
5. full serial `php artisan test` last — GREEN.

Record every invocation, including failures and reruns. Any edit after a gate
restarts at Pint.

## 14. Documentation and canonical closeout

Create a committed handoff under `docs/phase-02/` containing:

- requirement classification;
- accepted design and Audit/Option IDs;
- baseline and preserved operator-owned changes;
- files changed;
- tests added/updated;
- every command and result;
- FilaCheck and build outcomes;
- implementation screenshots/PDF review path outside the repository;
- deferred items and explicit exclusions;
- numbered imperative Local Front Check steps;
- `## Commit hash` pending.

Update only the authoritative status/coverage locations required by the active
Media program:

- `docs/research/media-operations-ux3/07-program-reconciliation-and-finding-coverage.md`;
- `docs/phase-02/current-project-state.md`;
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`;
- the appropriate Media program handoff/state references;
- `prompts/README.md` only if its canonical prompt status changes.

Preserve unrelated operator edits and stage only the approved 3A files.

Canonical ending:

1. implementation commit with handoff hash pending;
2. immediately update the handoff and ledger with that implementation hash;
3. docs-only commit:
   `docs: backfill media operations ux3 mini-task 3a hash`;
4. no push;
5. stop for operator outcome review and 3A closure;
6. do not begin 3B until that review closes 3A.
