# Media Operations UX3 Mini-task 3A Owner Image Choice and Commit Technical Research

## Accepted design contract

The operator accepted **Approach A** from the Mini-task 3A visual research:

- a canonical responsive modal for dedicated podcast-cover and episode-image
  actions;
- the same owner-state language embedded in record forms, relation-manager
  forms, workspaces, and public-settings image slots;
- an owner- and slot-specific heading;
- **Direct image** and **Shown now** evidence in parallel;
- one visible pending choice and one real owner commit;
- no chooser-local **Use selected image** commit;
- no **Current folder** owner concept; owner choice begins in complete
  **All Media** inventory;
- only chooser-safe actions, with Media maintenance routed to non-mutating
  **Media Details** in a new tab;
- owner-specific action labels such as **Save episode image** and
  **Save podcast cover**;
- the existing page, relation-modal, workspace, or Settings Save remains the
  real commit outside a dedicated action;
- Episode Image is separated from Player/Embed;
- exact opener focus, scroll, and return continuity;
- a stale conflict refreshes current owner evidence and requires review plus a
  deliberate second commit;
- semantic full-screen behavior at 390 pixels, with Hebrew RTL and English LTR
  parity, keyboard/touch operability, and no clipped action;
- truthful cancellation: the owner is unchanged, while Media already admitted
  through Upload, URL, or Storage remains permanent.

The accepted product-fidelity evidence is outside the repository:

- dossier:
  `/Users/studioycm/.codex/visualizations/2026/07/25/019f9b6d-2572-73f0-b71a-1eea7e7dc82f/media-operations-ux3-mini3a-owner-image-choice/output/PODTEXT-MEDIA-OPERATIONS-UX3-MINI3A-OWNER-IMAGE-CHOICE-REVIEW.pdf`;
- visual index:
  `/Users/studioycm/.codex/visualizations/2026/07/25/019f9b6d-2572-73f0-b71a-1eea7e7dc82f/media-operations-ux3-mini3a-owner-image-choice/output/VISUAL-INDEX.md`;
- accepted modal:
  `proposals/P001__RECOMMENDED_MODAL__HE_DESKTOP.png`;
- accepted 390-pixel modal:
  `proposals/P002__RECOMMENDED_MODAL__HE_390.png`;
- English parity:
  `proposals/P003__RECOMMENDED_MODAL__EN_DESKTOP.png` and
  `proposals/P004__RECOMMENDED_MODAL__EN_390.png`;
- state sequence:
  `proposals/P006__CRITICAL_STATE_SEQUENCE__HE.png`;
- Episode separation:
  `proposals/P007__EPISODE_IMAGE_VS_PLAYER__HE_DESKTOP.png`;
- settings parity:
  `proposals/P009__SETTINGS_OWNER_PARITY__HE_DESKTOP.png`;
- matched current/proposed comparisons:
  `proposals/P012__CURRENT_VS_RETHOUGHT__HE_DESKTOP.png` and
  `proposals/P013__CURRENT_VS_RETHOUGHT__HE_NARROW.png`.

The proposals are accepted design evidence, not implementation screenshots.

## Checkout baseline and preserved ownership

Technical research was performed in:

- working directory and Git root:
  `/Users/studioycm/Herd/PodText`;
- branch: `main`, 35 commits ahead of `origin/main`;
- HEAD:
  `576f5ada925d035baef75615f24d0fc9f8c7aa06`.

The following pre-existing operator-owned changes are expected inputs and must
remain untouched, unstaged, and uncommitted by Mini-task 3A:

- modified:
  - `docs/phase-02/current-project-state.md`;
  - `docs/phase-02/media-program-context.md`;
  - `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`;
  - `docs/research/media-program/02-media-program-master-plan.md`;
  - `docs/research/media-program/04-active-document-supersession-map.md`;
  - `prompts/README.md`;
- untracked:
  - `.agents/skills/ux-design-thinking`;
  - `.ai/skills/ux-design-thinking/`;
  - `.claude/skills/ux-design-thinking`;
  - `.junie/skills/ux-design-thinking`;
  - `docs/research/media-operations-ux3/07-program-reconciliation-and-finding-coverage.md`;
  - `prompts/pre-13-prompts/media-operations-ux3-program-reconciliation-finding-coverage-codex-prompt.md`.

The two Mini-task 3A research/plan files are the only repository writes made
before the fresh Laravel Simplifier Stage 1.

## Bounded product outcome

An authorized operator can:

1. identify the exact podcast, episode, or settings slot whose image is being
   chosen;
2. distinguish the saved direct image from the image currently shown and its
   fallback source;
3. choose one existing Gallery image without mutating Media or the owner;
4. admit Media from Upload, URL, or Storage under the already-implemented
   permanent-admission rules;
5. see exactly one pending owner choice;
6. clear that pending choice back to the saved owner state;
7. choose automatic/fallback presentation as a pending owner change when the
   current direct attachment can safely be removed;
8. commit the owner relationship exactly once at the owner’s real boundary;
9. cancel with the owner unchanged and with any admitted Media retained;
10. open Media Details in a new tab without losing the owner, pending choice,
    scroll position, or opener in the original tab;
11. recover from a stale owner-image conflict by reviewing refreshed current
    evidence and explicitly committing again.

The operator explicitly authorized the discovered relation-manager parity gap
for implementation in this Mini-task. Creating or editing an episode through a
podcast’s Episodes relation-manager form must use the same
`MediaAttachmentFormState::prepare()` and `persist()` lifecycle as the full
episode forms. This is mandatory 3A work and is not deferred.

`MediaAttachmentRole` currently contains exactly two roles:

- `cover` for `ContentGroup`;
- `primary_image` for `ContentItem`.

The approved implementation must keep the shared attachment lifecycle at
least as strong for both roles and apply any necessary stale/error-contract
improvement once at the shared manager/form-state seam. Every interactive 3A
entry point for both roles is in scope. Existing non-interactive consumers
such as imports, provider jobs, conversion, and backfill keep their established
semantics and receive regression coverage when a shared seam changes; this
approval does not redesign those workflows or create another attachment role.

## Protected Media authorities

| Authority | Mini-task 3A treatment |
|---|---|
| All Media is complete Curator inventory | Owner-choice Gallery starts from `MediaRecordScope::inventoryQuery()` with All Media active |
| `media_attachments.media_id` owns direct attachment | Preserve `MediaAttachmentManager`; do not infer ownership from paths |
| Curator `path` owns physical file location | Show only as evidence; never turn it into owner state |
| Gallery choice is mutation-free | Tile selection changes pending form/action state only |
| Upload/URL/Storage admission is immediately permanent | Preserve current `MediaAcquisitionManager` behavior and permanence copy |
| Outer cancel is owner no-op | Do not attach, detach, delete, or clean up on cancel |
| Admitted Media survives owner cancel | State explicitly in modal/form/settings copy |
| No known direct attachment is not unused | Do not imply deletion safety |
| Existing owner update/create policy authorizes commits | Add no ability or policy |
| Existing Media policies authorize Gallery and Details | Reauthorize every record; no raw URL authority |
| Unsafe legacy identity is not an ordinary direct attachment | Permit a reviewed healthy replacement; do not expose generic repair or automatic detach inside the chooser |
| File maintenance remains coordinator-owned | Rename, byte replacement, download, delete, and bulk work are not chooser actions |
| Package 5 owns new lifecycle authority | No move, Files Discovery, Trash, restore, purge, or lifecycle placeholder |

No schema, migration, model, durable workflow state, dependency, package,
queue, cache, production, or live-database change is required.

## Current entry-point topology

| ID | Owner/slot and current boundary | Required 3A treatment |
|---|---|---|
| EP01 | Podcast list row dedicated action | Canonical modal; podcast/cover heading; direct/shown/pending; Save podcast cover |
| EP02 | Podcast thumbnail dedicated action | Same modal; return focus and scroll to thumbnail opener |
| EP03 | New podcast cover on create page | Shared state block; prospective owner; outer Create commits |
| EP04 | Existing podcast cover on edit page | Shared state block for field; canonical header action; outer Save or dedicated action remains explicit |
| EP05 | Episode list row dedicated action | Canonical modal; episode/image heading; Save episode image |
| EP06 | Episode thumbnail dedicated action | Same modal; return focus and scroll to thumbnail opener |
| EP07 | Classic episode create/edit image field | Separate Episode Image section; shared state block; outer Create/Save commits |
| EP08 | Episode Workspace create/edit image field and header action | Separate Episode Image from Player/Embed; shared state plus canonical action |
| EP09 | Episode row/thumbnail inside podcast relation manager | Canonical action; episode wording; preserve relation-row opener |
| EP10 | Relation-manager create/edit episode modal | Shared state block; relation modal Create/Save commits; complete attachment prepare/persist lifecycle |
| EP11 | Menu/Header Settings light and dark logo slots | Shared current/shown/pending language; outer Settings Save commits |
| EP12 | Display Settings global/episode/podcast/contributor defaults | Shared slot language; inherited/global fallback truth; outer Settings Save commits |
| EP13 | About Settings image blocks and team profiles | Stable block/profile key identifies saved state; outer Settings Save commits |
| EP14 | Spotify-assisted podcast/episode image admission | Preserve permanent admission and pending field state; receipt redesign remains 3B |

The shared semantic order is:

`owner and slot → direct image → shown now and source → pending choice → Gallery/source choice → real commit and cancel truth`

Dedicated actions use the canonical modal. Embedded record and settings fields
use the same state block but keep their existing outer commit.

## Current implementation findings

### Dedicated owner actions

`app/Filament/Actions/ContentImageActions.php` already centralizes podcast and
episode image actions and correctly commits through
`MediaAttachmentFormState::persist(..., enforceExpectedIdentity: true)`.

Current gaps against the accepted design:

- the heading and submit action are generic;
- Replace and Details are separate tabs, so saved/effective evidence is not in
  the choice reading order;
- the picker has a second **Use selected image** action before the real action
  submit;
- direct removal and external import are extra immediate footer submissions,
  creating multiple mutation meanings;
- `AdminUxSettings::tb1_picker_container` can switch the action to a
  slide-over;
- there is no native sticky modal header/footer configuration;
- stale refusal protects data but does not refresh the expected snapshot for
  a deliberate second submission.

The accepted design should replace the tabs with one owner-state block plus
one owner-aware picker, keep the existing locked persistence authority, and
remove chooser-local mutation alternatives. The existing standalone Spotify
and unsafe-legacy actions remain separate and truthfully labelled.

### Shared Media picker field

`app/Filament/Forms/Components/PathCuratorPicker.php` already provides:

- trusted selection and attachment authorization;
- one reference-key field contract;
- selected-item hydration;
- a nested full-screen picker action;
- explicit close handling;
- double-`requestAnimationFrame` focus restoration with `preventScroll`;
- an inline picker variant used by dedicated owner actions.

Its current selected summary is generic and exposes View, Edit, Download, and
Remove. It has no owner/slot contract and no saved-versus-pending projection.

The smallest reusable seam is an explicit owner-choice mode on this existing
field. It should:

- accept a lazily evaluated `OwnerImageChoicePresentation`;
- render the shared state block from the field’s current state;
- register safe local actions to restore the saved selection and choose
  automatic/fallback when eligible;
- pass an explicit owner-choice flag and saved Media identity to the nested or
  inline `MediaPickerPanel`;
- leave every non-owner `PathCuratorPicker` behavior unchanged.

### Livewire picker

`app/Livewire/Admin/MediaPickerPanel.php` and
`resources/views/livewire/admin/media-picker-panel.blade.php` already own:

- complete-inventory query and bounded search/pagination;
- source-specific Upload, URL, and Storage admission;
- authorization and diagnostic selection fences;
- explicit permanence copy;
- offline/loading/focus handling;
- single- and multi-selection;
- existing-file actions and generic insertion.

Current owner-choice gaps:

- `allMedia` defaults false and the header presents a purpose-root
  **Current folder** concept;
- Gallery is always beside a secondary acquisition panel instead of being one
  of the four top-level source choices;
- a single Gallery tile changes child selection but does not immediately
  update the owner field;
- `insertMediaAction()` remains the chooser-local commit;
- card actions include View, Download, Edit, Rename, Swap, and Delete;
- desktop uses six gallery columns and narrow layout can put source work before
  Gallery;
- inline mode owns an independent sticky footer inside the outer action.

An explicit locked `isOwnerChoice` flag must be separate from
`isInlineOwnerWorkspace`:

- owner-choice mode selects All Media on mount;
- Gallery, Upload, URL, and Storage become one top-level source navigation;
- a valid single tile immediately dispatches pending selection;
- inline dedicated-action mode stays open after selection;
- nested form/settings mode updates the field, closes through the existing
  parent handler, and restores focus;
- a dedicated clear-pending event resets both child selection and parent field
  to the saved owner state without committing;
- cards expose selection and one authorized Media Details new-tab route only;
- generic picker selection, insertion, folders, multi-select, and maintenance
  actions remain unchanged.

### Owner-image presentation

`OwnerImagePresenter` and `OwnerImagePresentation` already resolve:

- fresh owner and attachment state;
- direct attachment presence;
- inherited, external, configured, and global fallback source;
- broken direct identity;
- safe preview;
- expected attachment Media ID and legacy path;
- unsafe legacy fingerprint;
- authorized Media detail metadata and routes.

The current `media` property collapses direct and effective Media into one
detail record. The accepted design requires parallel direct and shown-now
projections plus pending state.

A focused `OwnerImageChoicePresentation` should contain only view-ready values:

- owner kind, owner label, and slot label;
- prospective/saved state;
- direct image projection or explicit absence/broken state;
- shown-now projection and source;
- saved reference key and Media ID;
- pending projection derived from current field state;
- pending kind: unchanged, replacement, or automatic/fallback;
- clear-pending and automatic/fallback eligibility;
- expected Media ID, expected legacy path, and unsafe fingerprint;
- localized commit-boundary and cancellation/permanence semantics;
- authorized Details URLs only.

`OwnerImagePresenter` should build this value object for `ContentGroup` and
`ContentItem` by reusing its existing authority resolution. It must not copy
diagnostic or default-image rules into Blade.

### Record-form persistence and stale state

Full podcast and episode create/edit pages already call
`MediaAttachmentFormState::prepare()` before the record write and
`persist()` after it. Dedicated actions already enforce expected attachment
identity.

Two gaps remain:

1. edit-page form commits do not carry the initial owner-image identity into
   `persist()` and can therefore overwrite a concurrently changed image;
2. `ContentItemsRelationManager` reuses `ContentItemResource::form()` but its
   `CreateAction` and `EditAction` do not run the Media attachment
   prepare/persist lifecycle at all.

The implementation must:

- capture a server-trusted expected owner-image snapshot for every existing
  podcast/episode edit surface;
- pass it to `persist(..., enforceExpectedIdentity: true)`;
- on a stale conflict, preserve the pending choice, refresh current evidence
  and the expected snapshot, show explicit review/reconfirm copy, and require a
  second Save;
- add `mutateDataUsing`, `mutateRecordDataUsing`, and `after()` hooks to the
  relation-manager Create/Edit actions;
- prove that relation create/edit writes the canonical
  `media_attachments.media_id` relationship and matching compatibility path;
- keep create flows free of a meaningless stale check.

The locking, authorization, and actual attach/detach transaction stay in
`MediaAttachmentManager`. A specific owner-image-changed exception may replace
the current generic `RuntimeException` at that exact mismatch seam so UI
callers can refresh/reconfirm without mistaking other failures for a stale
conflict.

### Form information architecture

The classic ContentItem form places the image in technical Media Metadata,
while player/embed inputs are in the Content section. Episode Workspace places
description, image, player URL, trusted embed HTML, embed URL, direct media
URL, and preview in one Media and Source section.

The accepted information architecture requires:

- a localized **Podcast cover** owner section in `ContentGroupForm`;
- a localized **Episode image** owner section in `ContentItemForm`;
- a localized **Episode image** section in `EpisodeWorkspaceForm`;
- description remains content;
- player URL, embed HTML, embed URL, direct media URL, and audio preview remain
  together under a localized **Player and embed** section;
- provider IDs, external metadata, duration, and metadata key/value remain
  technical/advanced fields.

No renderer, embed policy, Spotify behavior, or public fallback rule changes.

### Relation-manager language

The accepted research reproduced `MUX3-F042`: a Hebrew podcast relation
context can show generic English **Content Items**.

`ContentItemsRelationManager` should set an explicit localized Episodes table
heading and explicit episode Create/Edit modal headings. Internal model and
relationship names remain `ContentItem` and `contentItems`. User-configured
owner titles remain content and use semantic `dir="auto"` or `<bdi>` when
shown; controls and headings use translation keys.

### Public settings image owners

The current focused settings pages have real outer Save boundaries and already
normalize these Media identity pairs:

- light and dark menu logos;
- global, episode, podcast, and contributor defaults;
- About image blocks;
- About team-profile images.

The current fields show only a generic selected-Media summary. A focused
`SettingsOwnerImagePresenter` should:

- read the persisted settings snapshot and the current field state;
- identify dynamic About items by their existing stable `key`, never array
  position;
- resolve Media through `MediaIdentityResolver`;
- reuse `PublicDefaultImageResolver` for default-family inheritance instead of
  copying its fallback rules;
- return the same `OwnerImageChoicePresentation` shape used by record owners;
- cache the persisted settings projection within one request to avoid
  per-field repeated reads.

Settings pages also need a subject-scoped Media identity fingerprint captured
when the form is filled. Before Save, compare it with a freshly loaded
persisted subject snapshot:

- no change: continue with the existing validated overlay/save;
- changed: do not save, preserve the pending form state, update the baseline
  fingerprint, refresh current owner evidence, notify that an image changed,
  and require an explicit second Save;
- after success: refresh the baseline to the committed settings state.

The fingerprint contains only stable slot IDs/modes/reference keys/legacy paths
and adds no durable state. Unrelated settings subjects must not create false
conflicts.

### Canonical container setting

`AdminUxSettings` exposes `tb1_picker_container`, and
`ContentImageActions::applyConfiguredContainer()` switches between modal and
slide-over. Accepted Approach A makes that runtime choice obsolete.

Mini-task 3A should:

- stop reading the setting for owner-image actions;
- remove its selector from the active Admin UX page;
- update the focused settings test;
- retain the stored property, enum, and historical settings value as inert
  backwards-compatible data;
- add no migration and delete no operator setting data.

The transcription modal/slide-over setting remains unrelated and unchanged.

### Responsive and focus seam

Filament should own modal semantics, focus trapping, Escape behavior, and
focus return. The action should use:

- `Width::SevenExtraLarge` at desktop;
- `stickyModalHeader()` and `stickyModalFooter()`;
- a scoped modal class from `extraModalWindowAttributes()`;
- one modal body scroll;
- a 390-pixel CSS rule that makes the dialog semantically full-screen;
- two gallery columns at narrow widths and five at the accepted desktop width.

The existing nested-picker explicit close/focus code remains the field/settings
return seam. Details opens in a new tab, so the original owner state, pending
choice, scroll, and focus context remain intact without a return token.

## Installed-version research

The current installed stack is:

- PHP 8.4;
- Laravel 13.21.1;
- Filament 5.7.3;
- Livewire 4.3.3;
- Pest 4.7.5;
- Tailwind CSS 4.3.3.

Laravel Boost and installed Filament guidance confirmed:

- action modals support `stickyModalHeader()`, `stickyModalFooter()`,
  `modalWidth()`, `modalHeading()`, `modalDescription()`,
  `modalSubmitActionLabel()`, and `extraModalWindowAttributes()`;
- custom fields may render their own Blade state and call exposed server
  methods;
- nested Livewire schema components can use modelable/event-mediated state;
- relation-manager `CreateAction` and `EditAction` support data mutation
  hooks, and action `after()` runs with the created/edited record available;
- browser tests can resize to 390 pixels, inspect console/JavaScript errors,
  take screenshots, and run accessibility checks.

Official references:

- <https://filamentphp.com/docs/5.x/actions/modals>
- <https://filamentphp.com/docs/5.x/forms/custom-fields>
- <https://filamentphp.com/docs/5.x/schemas/custom-components>
- <https://filamentphp.com/docs/5.x/actions/create>
- <https://filamentphp.com/docs/5.x/actions/edit>
- <https://filamentphp.com/docs/5.x/testing/overview>
- <https://pestphp.com/docs/browser-testing>

## FilamentExamples research

The configured server exposed search/snippet results only; no source, fetch,
read, or detail tool was available. Research therefore does not claim deep
source access.

| Example | Relevant snippet/pattern | PodText adaptation |
|---|---|---|
| `v4/forms/quote-form-with-custom-table-field-and-product-picker-modal/.../QuoteProductsField.php` | Custom `Field` coordinates a nested picker | Keep `PathCuratorPicker` as the owner-state field rather than create a second field stack |
| Same example, `ListQuoteProducts.php` and Blade | `#[Modelable]` nested Livewire state and picker event | Continue the existing trusted event boundary; add explicit owner-choice and restore-saved events |
| `v4/forms/multiple-checkboxes-for-manytomany-relationship/.../GroupsTable.php` | Modal width/autofocus and action-modal customization | Use native Filament modal geometry/focus APIs and a scoped responsive class |
| `v4/full-projects/stock-management/.../TransactionsRelationManager.php` | Relation-manager `CreateAction` with custom action and `after()` | Prepare relation form data before create/edit and persist the attachment after the record exists |

Patterns deliberately not copied:

- a second durable selection model;
- a custom modal implementation outside Filament;
- action callbacks that bypass current owner or Media policy checks;
- generic file maintenance inside a relationship chooser.

## Filament forms, performance, security, and UX findings

- The owner state belongs adjacent to the field it explains, not in helper
  text or a separate hidden tab.
- The custom field must expose a readable label and state before the Gallery
  grid.
- A single state object prevents direct/effective/pending copy from diverging
  across action, page, relation, workspace, and settings surfaces.
- Presenter and settings reads should be cached per request; gallery cards
  must not run owner/effective queries.
- Owner and Media authorization stays server-side on mount, selection,
  Details URL generation, and commit.
- `isOwnerChoice`, purpose, saved identity, and container semantics are
  server-owned/locked; client input cannot broaden inventory or action access.
- User content is escaped and rendered with semantic direction. No raw owner
  title or filename becomes HTML.
- Details links use only `MediaResource::getUrl()` after the existing update
  policy permits them, open with `target="_blank"` and
  `rel="noopener noreferrer"`.
- No chooser action calls `MediaFilesystemMutationCoordinator`.
- No action or field claims that changing descriptive metadata repairs a file.
- Narrow behavior must be tested by viewport/DOM geometry, not inferred from
  Tailwind classes.

## Finding disposition for implementation

| Finding | Accepted implementation disposition |
|---|---|
| `MUX3-F007` | Owner- and slot-specific heading plus shared reading order |
| `MUX3-F008` | Direct and shown-now/source evidence before choice |
| `MUX3-F009` | One pending state; remove local Use selected; one real commit |
| `MUX3-F010` | Selection/search/source/Details only; route maintenance out |
| `MUX3-F011` | All Media default; remove Current folder as owner concept |
| `MUX3-F012` | Shared presentation/field pattern across EP01–EP14 with intentional outer boundaries |
| `MUX3-F013` | Separate Episode Image from Player/Embed |
| `MUX3-F014` | Full 390-pixel proof, one body scroll, fixed real footer, two-column gallery |
| `MUX3-F015` | Canonical responsive modal; legacy owner-image slide-over setting inert |
| `MUX3-F016` | Exact opener focus/scroll/return and new-tab Details continuity |
| `MUX3-F041` | Not reproduced; translation-key regression only, no speculative fix |
| `MUX3-F042` | Reproduced; explicit localized Episodes relation headings |
| `MUX3-F043` | Translate controls; preserve user content and apply semantic bidi direction |
| `MUX3-F044` | Not reproduced and unrelated to owner-image design; no implementation assignment |

## Chosen technical design

The minimum coherent design is:

1. extend the existing owner presenter with one shared
   `OwnerImageChoicePresentation`;
2. add a focused settings presenter and subject-scoped Media snapshot;
3. add explicit owner-choice behavior to the existing custom field and picker
   without changing generic picker contracts;
4. replace dedicated action tabs/extra mutation footers with one canonical
   responsive modal and one commit;
5. apply the existing attachment lifecycle and stale guard consistently to
   both current roles across full forms, workspaces, dedicated actions, and
   relation-manager forms;
6. separate Episode Image from Player/Embed;
7. retire only the active owner-image container selector, without deleting
   stored data;
8. prove the accepted desktop/narrow, HE/EN, state, focus, stale, and
   persistence contracts with feature and browser tests.

This design reuses current authority, storage, acquisition, attachment,
fallback, field, picker, and modal mechanisms. A new standalone owner workflow,
new persistence layer, or replacement Media picker would add more state and
more authority seams without improving the accepted outcome.

## Explicit exclusions

Mini-task 3A does not design or implement:

- 3B acquisition receipts, per-file outcomes, partial failure, interruption,
  or Retry;
- 3C rename, byte replacement, permanent delete, bulk operations, export
  result design, Preview/View/Download contract redesign, or file-operation
  recovery;
- reason-specific repair, generic Fix, Recheck, or Retry;
- a selected Mini-task 4 diagnostic reason;
- Package 5 Files Discovery, move, Trash, restore, purge, or lifecycle;
- Markdown editor debugging absent an independent reproduction;
- dependency or schema changes;
- documentation consolidation;
- production changes, push, or publication.
