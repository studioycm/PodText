# Step 5B Card Template Preview UX Specification

## Forecast and control

- This is one logical docs-only specification task, Mini-task 2 and the final
  task in the Step 5B controller's two-task sequence.
- Remaining implementation-facing effort forecast: one later bounded feature,
  no more than two logical implementation tasks and approximately 3–4
  engineering hours now that the previously optional selector, freshness
  details, and browser measurements are required. The expected coding portion
  is approximately 1.5–2.5 hours; focused tests, browser evidence, and the
  repository's ordered verification account for the balance. The sequence
  remains within the four-hour stop limit, but with little scope margin.
- Dependency changes: none.
- Review model: one integrated specification review. There is no independent
  audit/remediation chain.
- Implementation remains unauthorized. This document is an input to a later,
  separately approved Laravel Simplifier Stage 1 audit, not an implementation
  plan or permission to edit application code.

## Evidence and confidence

### Verified current code facts

1. `CreateCardTemplate` and `EditCardTemplate` extend the same
   `CardTemplateEditorPage`. Each mount fills exactly one Builder-shaped draft
   beneath the form's `data` state path.
2. The editor already owns dirty-navigation protection, locked server-owned
   operation/original identity/source/fingerprint/capability/measurement state,
   import-lock presentation, protected-shell behavior, bilingual validation,
   and the existing save/cancel/delete behavior.
3. `PublicFrontConfigValidator` accepts Builder transport (`type` plus `data`),
   rejects nested groups, and normalizes one-level grouped parts into semantic
   card-template JSON.
4. `CardTemplateFocusedWriter` is the only Card Template persistence boundary.
   Its private draft conversion also removes stale group-only fields from
   non-group parts, recursively converts group children, filters nulls, and
   validates exactly one candidate before a real save.
5. `PublicFrontCardTemplate`, `PublicFrontCardTemplateResolver`,
   `PublicFrontCardTemplateRenderer`, the three family presenters, and the
   existing public Blade card/part components own controlled output for
   `content_item`, `content_group`, and `contributor`.
6. The presenters require family-specific models, relationships/aggregate
   aliases, URL inputs, options, and default-image resolution. They already own
   safe route generation, fallbacks, localized dates/counts, and card-part
   projection.
7. SP3C canaries measure component/server HTML, form controls/wrappers, and
   serialized state. They explicitly do not prove browser DOM, teleported
   modal, listener, heap, navigation, network, or TTFB behavior.
8. No full-card unsaved preview exists. Existing Builder block previews are
   edit summaries and remain separate. Step 5B is unimplemented.

### Installed-version and example research

- Laravel Boost reported PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire
  4.3.3, Pest 4.7.4, and Tailwind CSS 4.3.2.
- Installed-version documentation confirms custom-page header actions,
  responsive Filament grids, read-only actions, slide-overs with sticky
  headers, Builder block previews, and Livewire locked public properties for
  transient runtime values that must survive requests.
- FilamentExamples exposed search/snippet access only. It returned custom-page
  header-action composition, a two-column form/table custom page, and action
  modal examples. Reuse: normal custom-page actions and a responsive adjacent
  region. Avoid: external/API samples, duplicate form state, or a second
  editable schema. PodText adaptation: one existing editor form plus one
  read-only preview region/action. No source/detail endpoint was available, so
  this is not claimed as deep source research.

### Inferences requiring later verification

- `xl` (1280 CSS px) is the smallest credible breakpoint for keeping the
  existing Builder editor usable beside a representative card. Real browser
  acceptance must confirm this in Hebrew and English.
- A focused in-memory default-image context is the smallest likely way to reuse
  current presenters while guaranteeing no settings read on draft refresh.
  The later audit must validate the exact constructor/factory seam.
- Numeric preview delta caps cannot be honestly fixed before an implementation
  candidate exists. This specification defines the baseline and cap-adoption
  procedure instead.

## 1. Outcome, users, and non-goals

Step 5B helps an authenticated Admin or Super Admin answer one question before
saving: “Does this current unsaved template produce the intended public card
for a valid item in its selected family?”

The preview is a read-only presentation of the current unsaved draft. It is
never a save, backup, settings change, publication action, permission boundary,
or substitute for validation on Save. It must not create a template, alter a
sample record, change references, publish content, or recover a draft after a
remount.

Non-goals include persistence or revisions for preview state, autosave,
collaboration, simultaneous editing, sample management, generalized preview
infrastructure, roles/permissions/panels, a public-page preview, a second
settings reader/writer, or any AUTHZ/ARCH1/SP3D/Public Front queue work.

These exclusions are deliberate boundaries, not missing preview polish:

- Autosave changes the existing explicit Save, dirty-navigation, validation,
  stale-write, backup, and cache lifecycle; preview only reads the draft.
- Persisted preview state, revisions, and collaboration require new storage,
  recovery, conflict-resolution, and authorization contracts. A transient
  sample selector does not need any of them.
- Existing Admin/Super Admin access already governs the editor, so a new
  preview permission would add policy complexity without protecting a new
  data boundary.
- A generalized preview platform would introduce abstractions for hypothetical
  consumers. Step 5B has one proven consumer and should use a focused adapter.
- Unrelated queue work has different requirements, evidence, and acceptance
  gates; combining it would make preview harder to estimate and verify.

## 2. Required-versus-optional classification

| Element | Classification | Reason |
| --- | --- | --- |
| Preview the current unsaved single draft through existing validation, value object, renderer, presenter, and public Blade | **Required for Step 5B** | Core user decision and parity boundary. |
| Explicit Preview/Refresh action; no per-keystroke presentation | **Required for Step 5B** | Bounded Livewire/query/state cost and predictable validation. |
| Adjacent preview at `xl` and wider | **Required for Step 5B** | Original Step 3/5 intent and current selected scope. |
| Preview action with read-only slide-over below `xl` | **Required for Step 5B** | Keeps the Builder usable on narrower screens. |
| Automatic deterministic public-safe sample per family | **Required for Step 5B** | Makes preview usable without new state or fixtures. |
| Loading, no-sample, invalid-draft, sample-error, and restricted states | **Required for Step 5B** | Prevents silent saved-template fallback or blank UI. |
| Preview links/buttons inert, with visible parity retained | **Required for Step 5B** | Prevents navigation away from a dirty editor. |
| HE/EN, RTL/LTR, keyboard/focus, and error semantics | **Required for Step 5B** | Existing application and Filament requirements. |
| A bounded sample selector | **Required for Step 5B** | Operator-requested control for checking more than the automatic sample; it remains transient and public-safe. |
| “Last refreshed” copy and bounded sample identity details | **Required for Step 5B** | Makes explicit-refresh freshness and the selected sample unambiguous without polling. |
| Browser DOM/listener/heap/network evidence in addition to manual acceptance | **Required for Step 5B** | Operator-requested evidence for the responsive/teleported surface; record observed deltas without inventing unsupported caps. |
| Saved preview preferences, sample tables/settings, synthetic persisted samples, remote fetching | **Deferred/out of scope** | New persistence and lifecycle are prohibited. |
| Live/debounced per-field preview, autosave, collaboration, revisions, generalized preview platform | **Deferred/out of scope** | Exceeds the stop rule and current architecture. |

The required slice is one later prompt, at most two logical tasks, 3–4 hours,
with no dependency change. Stop for operator reapproval rather than dropping
the selector, freshness details, or browser evidence if implementation cannot
stay inside those bounds.

## 3. Unsaved-state-to-presenter contract

### Required read-only flow

1. A Preview/Refresh action obtains the current editor form's unsaved `data`
   in the same Livewire request. It does not call `save()`, `deleteTemplate()`,
   or `CardTemplateFocusedWriter`.
2. A focused read-only draft adapter converts the one Builder row to candidate
   semantics. It must use the exact cleanup now private to the writer:
   unwrap `type`/`data`, remove stale `columns`, `gap`, `alignment`, and
   `children` from non-group parts, recursively convert one level of group
   children, and filter null values without losing `false`, `0`, or empty
   values that validation treats meaningfully.
3. The adapter passes exactly `[candidate]` to the existing
   `PublicFrontConfigValidator` Card Template rules. Success requires exactly
   one normalized template. Zero, multiple, or invalid normalized rows produce
   an invalid-draft preview state; they never fall back to the stored template.
4. The normalized unsaved row becomes a `PublicFrontCardTemplate`. The existing
   resolver's in-memory `resolveFromTemplates([row], family, key, overrides)`
   seam may be used only if it resolves the supplied row without reading the
   configured template list. Direct `PublicFrontCardTemplate::fromArray()` is
   also acceptable if it preserves identical validated semantics.
5. A family sample selected through the contract in section 4 is passed to the
   existing renderer and matching presenter. Content-item options use an
   explicit in-memory/default `PublicContentCardOptions` instance, not
   settings-backed options.
6. The presenter output is rendered by the existing family public card Blade
   component in bounded `previewMode`. `previewMode` may make links/actions
   inert, but must not fork card-part semantics or restyle the public card.
7. Only a later explicit Save action may call `CardTemplateFocusedWriter` and
   the existing lifecycle.

### Smallest likely seams

The current writer's transport cleanup is private, so copying it into the page
would create drift. Extract that conversion into a focused stateless class such
as `CardTemplateDraftNormalizer`, used by both the writer and preview adapter.
The writer remains the sole persistence boundary and keeps candidate-count and
save orchestration. The extraction must be behavior-preserving and tested
against the current writer cases.

The public presenters use `PublicDefaultImageResolver`, whose ordinary render
context can read settings. A focused preview presenter/factory should construct
the existing presenter with a `PublicFrontRenderContext` seeded from registry
defaults in memory. It must not resolve `PublicFrontConfigReader`, configured
templates, or settings-backed card options. This is preview-specific
composition, not a new settings reader or generalized platform.

The contributor component currently presents internally, unlike the item and
group components that accept presented card data. The smallest parity seam is
an optional pre-presented `card` input plus `previewMode`; ordinary calls retain
their current presenter path. Do not duplicate contributor card markup.

### Zero-side-effect invariant

A draft-only preview or refresh must cause zero:

- `PublicContentSettings::save()` calls or settings mutations;
- `SettingsSaved` events;
- backup attempts;
- cache invalidations;
- reference scans;
- `CardTemplateFocusedWriter` calls;
- settings lifecycle derivations; and
- configured Card Template list reads.

Preview errors remain local to preview state. They must not replace existing
Save-field errors or mutate the one draft.

## 4. Family and sample contract

### Shared rules

- Only `content_item`, `content_group`, and `contributor` are supported.
- Load one bounded record only when the editor initially establishes preview,
  the family changes, the sample selection changes, or the user
  explicitly refreshes after the sample became unavailable. Draft-field
  keystrokes never reload a sample.
- Retain only a locked scalar family/sample identity plus a compact presented
  preview result/status. Do not serialize an Eloquent model, relation graph,
  second draft, or settings snapshot into Livewire state.
- The server re-fetches the sample through the public-safe query on an accepted
  refresh. A forged/deleted/unpublished identity becomes the no-sample state.
- Tests own database factories/fixtures. Production needs no seed, sample row,
  new table, settings key, or remote request.

| Family | Required reuse | Required sample shape | Deterministic automatic order | Empty state |
| --- | --- | --- | --- | --- |
| `content_item` | `PublicContentItemCardPresenter`; `<x-public.content-item-card>` and part component | `PublicContentItemQueries::base()` with categories, `contentGroup.categories`, enabled content tags, effective public transcription relations, transcription authors, effective date, and existing aggregate aliases; in-memory `PublicContentCardOptions` | `orderByEffectiveTranscriptionPublishedAt()` descending, then item ID descending, `limit(1)` | “No published episode with an effective public transcription is available for this preview.” |
| `content_group` | `PublicContentGroupCardPresenter`; `<x-public.content-group-card>` and part component | `PublicContentGroupQueries::base()` with visible/public group constraints, categories, published-item count, public transcription/transcriber/word/date aggregate aliases | title ascending, then group ID ascending, `limit(1)` | “No published podcast with published content is available for this preview.” |
| `contributor` | `PublicContributorCardPresenter`; `<x-public.contributor-card>` and part component | `PublicContributorDiscovery::contributors(sort: 'count_desc')`, which supplies public transcription/content-item aggregate aliases and requires public contribution existence; generate the existing public contributor URL from the current page/helper | public transcription count descending, content-item count descending, name ascending, ID ascending, `limit(1)` | “No public contributor is available for this preview.” |

Public visibility is mandatory: unpublished items/groups, items without an
effective public transcription, disabled tags, and contributors without public
work are never preview samples. Presenter-required data must be eager-loaded or
selected before rendering; lazy loading is a test failure.

Automatic selection and a selector are required. The selector uses the same
public-safe family query, caps results at 50, is searchable without preload,
and stores one transient scalar ID. Its visible option includes the minimum
bounded identity needed to distinguish records (title/name plus stable public
context); it does not persist a preference or preload full models.

Changing `data.family` clears the transient sample identity and preview result,
then performs one explicit family-change refresh after the select change—not a
save. It chooses the new family's deterministic sample and renders the current
unsaved draft. If the changed draft is temporarily invalid, show the
invalid-draft state and keep the new family/no persisted selection.

## 5. Interaction and responsive layout

### Refresh policy

Preview is explicit-refresh driven. Mount may establish the initial sample and
initial preview once. A family select change is the only automatic refresh
boundary because it invalidates both template family and sample type. All
other text/select/toggle/Builder changes mark the last preview “Changes not yet
previewed”; Preview/Refresh submits current form state once. There is no
per-keystroke, blur, debounce, polling, or per-part presentation.

This preserves Livewire's one authoritative form state, avoids repeated
normalization/query/presenter work, and makes the preview's freshness legible.

### Wide behavior (`xl` and wider, 1280 CSS px)

- Editor and preview share a responsive two-column shell. The editor owns the
  flexible/main column; the preview is a bounded logical-end column with a
  minimum usable card width.
- The preview region remains present while editing and owns its own vertical
  scroll. Its sticky local header contains title, sample identity, status, and
  Refresh.
- Only the active preview DOM is mounted. Viewport Alpine state may control
  placement/open visibility via `matchMedia`; it must not own draft, sample,
  validation, or presented card data.

```text
+--------------------------- editor scroll ----------------+ +-- preview scroll --+
| Card Template editor                                    | | Preview   [Refresh] |
| import-lock / restricted messages                       | | sample + status     |
| key / family / label / layout                           | | ------------------- |
| existing Builder summaries and selected-part editor     | | rendered public card|
| existing validation + Save / Cancel / Delete            | | inert links/actions |
+---------------------------------------------------------+ +---------------------+
```

### Narrow behavior (below `xl`)

- The adjacent region unmounts and a labelled Preview header/form action is
  available. Activating it refreshes current state and opens a read-only
  Filament slide-over.
- The slide-over header and close control remain sticky; only its content area
  scrolls. It displays sample/status, Refresh, failure/empty state, and the
  rendered card.
- Resizing below `xl` preserves the single server preview result but unmounts
  the adjacent preview DOM. The action opens that result; it refreshes only if
  requested or stale. Resizing to `xl` closes/unmounts the slide-over, renders
  the same result in the adjacent region, and returns focus to the preview
  heading without triggering presentation or a sample query.

```text
+------------------------ editor scroll -------------------------+
| Card Template editor                         [Preview] [Save]   |
| existing fields / Builder / errors                              |
+----------------------------------------------------------------+
                         opens
              +---------- slide-over ------------+
              | Preview                 [Close]  | sticky header
              | sample / status        [Refresh] |
              | --------------------------------- |
              | rendered public card             | content scroll
              | inert links/actions               |
              +-----------------------------------+
```

### State and error behavior

- **Loading:** retain region dimensions, show labelled busy state, disable only
  Preview/Refresh, and leave editor/save controls available.
- **Ready:** show family, bounded sample identity, a localized last-refreshed
  time, and whether it is current or stale relative to the draft. The timestamp
  updates only after an accepted refresh and never polls.
- **Invalid draft:** show concise preview error plus a link/button that focuses
  the existing first relevant editor field. Keep the slide-over open and the
  unsaved draft intact. Do not display a stored-template fallback.
- **No sample:** show the family-specific empty copy; editor and Save remain
  unchanged.
- **Sample error:** show a safe generic error, retain the draft, allow retry,
  and log server detail through existing application practice without exposing
  internals.
- **Restricted/protected shell:** if parts are absent by authorization, do not
  reconstruct, fetch, serialize, or preview protected parts. Show a restricted
  preview state. Shell edits still follow existing Save behavior.
- Save validation/stale/collision/reference errors leave an open preview open,
  retain the draft and last result, and mark it stale if form data differs.

Existing Builder block previews, selected-part editing, Save/Cancel/Delete,
import-lock badge, dirty-navigation warning, field error ownership, and
protected-state sanitation are not replaced.

## 6. Hebrew/English and RTL/LTR

- Hebrew is primary and the shell mirrors using logical start/end placement.
  In RTL, the preview remains logical-end rather than hard-coded left/right;
  English uses the corresponding LTR placement.
- Technical template keys and machine identities render `dir="ltr"` with
  appropriate isolation. User titles, labels, descriptions, category/tag
  names, and contributor names retain their own/content direction and wrap.
- Long titles/labels wrap and clamp inside the same public card rules. They must
  not widen the preview column, cover actions, or create horizontal page
  scrolling. Full technical identity may be available through accessible
  labelled text/title, not truncation alone.
- Slide-over placement follows Filament's logical/responsive conventions and is
  verified in both locales. Close/Refresh order follows reading direction.
- Every future visible title, hint, loading label, freshness status, empty
  state, error, restricted copy, sample label, Preview/Refresh/Close action,
  and accessibility label gets both `he` and `en` translation keys. No literal
  UI strings are introduced in implementation.

## 7. Accessibility and keyboard/focus behavior

- The persistent preview is a labelled `region` whose heading identifies
  family and sample. Status changes use a restrained `role="status"`/
  `aria-live="polite"` message after explicit refresh only; errors use the
  existing error pattern and are not announced on each keystroke.
- Preview, Refresh, and Close have visible translated labels, discernible
  accessible names, loading/disabled state, and keyboard activation. No global
  shortcut is added.
- Opening the slide-over moves focus to its heading (or first error when the
  refresh fails), traps focus using Filament's installed modal primitive,
  supports Escape and the Close action, and restores focus to the invoking
  Preview action. Resize-driven closure restores focus only when focus was in
  the slide-over.
- Editor and preview/slide-over scroll independently. Focused editor errors are
  never hidden behind the slide-over; closing/focusing the field is an explicit
  action.
- Loading, stale, invalid, empty, selected sample, and restricted states use
  text/icon semantics, not color alone.
- In `previewMode`, all generated anchors and interactive public-card controls
  are inert: no `href`, `wire:click`, submission, or tab stop. They render with
  the same visible content and layout, expose a translated “link disabled in
  preview” description where needed, and do not use a blanket click-capture as
  the only safeguard. This prevents dirty-editor navigation while preserving
  visual parity.

## 8. Bounded performance acceptance

### Required invariants

- One authoritative `data` draft only; no duplicate full draft in preview,
  mounted-action, Alpine, or serialized Livewire state.
- No persisted preview/sample state and no Eloquent model/relation graph in
  Livewire public state.
- On draft-only refresh: zero settings reads, reference scans, writer calls,
  lifecycle derivations, settings saves, `SettingsSaved` events, backups, and
  cache invalidations.
- Sample query occurs only on initial preview establishment, family change, a
  sample change, or re-fetch after invalidation. A refresh using a
  still-valid locked scalar sample ID may re-fetch exactly that one sample;
  this is one bounded family query, never a search/list query.
- Sample query count is constant for one sample and all presenter relations and
  aggregate aliases are loaded before presentation. Enable lazy-loading
  prevention in the focused tests; presenter/Blade rendering adds zero queries.
- Normalization/value-object/presentation runs once per accepted refresh, not
  once per part or Blade include.
- Preview mounts no Schema, Builder, field wrapper, form input, or duplicated
  Builder controls. Existing editor wrapper/control limits remain unchanged.

### Repeatable component baseline and delta procedure

Use `SettingsSp3cDeepestFixture`, `SettingsSp3cCanaryMeasurement`, its DOM
parser selectors, and its JSON encoding flags. Add a preview-aware canary that
uses the deepest valid ordinary template and one factory-owned sample for each
family. For three identical runs, record:

1. current editor unselected, top-part selected, and group+nested selected;
2. each same state with preview closed/stale;
3. each same state with one ready adjacent preview; and
4. the narrow slide-over component response separately from real browser DOM.

The frozen existing ceilings are preservation baselines, not values to rewrite:

| Existing SP3C surface | Frozen ceiling |
| --- | ---: |
| Library, 30 rows | 1,262 elements; 2 wrappers; 2 controls; 575,913 HTML bytes; 13,973 serialized-state bytes |
| Editor, unselected | 4,212 elements; 10 wrappers; 3 controls; 1,508,900 HTML bytes; 21,742 serialized-state bytes |
| Editor, one top-level part selected | 4,341 elements; 20 wrappers; 8 controls; 1,562,081 HTML bytes; 21,742 serialized-state bytes |
| Editor, group and nested part selected | 4,469 elements; 29 wrappers; 12 controls; 1,616,670 HTML bytes; 21,742 serialized-state bytes |

Before adopting a new numeric preview cap, the later implementation must run
the above baseline on unchanged code, then the candidate three times. The cap
is the maximum candidate observation plus 20%, recorded as a new Step 5B delta
budget. Required qualitative stop conditions apply immediately: zero added
editor wrappers/controls/wire-model paths; serialized-state delta contains only
compact scalar status/sample identity/presented payload, not `data.parts` or a
model graph; query count is constant by family; and hostile fixture text is
escaped. Do not alter SP3C's frozen numbers.

Server response headers, query logs, component HTML, and serialized state are
not browser DOM/listener/heap/network/TTFB evidence. Required real authenticated
browser acceptance must verify: `xl` resize transition, teleported slide-over
single mount, focus trap/Escape/restoration, independent scroll, inert links,
Hebrew/English layout, dirty Back/navigation protection, and no duplicate
preview DOM. The same run must record before/after observations for DOM element
count, active preview-root count, interactive/focusable element count, refresh
network requests, Livewire listener observations available from the runner,
heap snapshot/used-heap observation where supported, and refresh/navigation
timings. These are recorded evidence, not pass/fail numeric ceilings, until the
same runner produces a repeatable baseline. Unsupported metrics must be named
explicitly; they may not be relabelled from component tests or silently
omitted.

## 9. Preservation matrix

| Existing boundary | Step 5B preservation |
| --- | --- |
| SP3A fixture/SHA and measurement mode | Do not change the fixture payload/SHA, profiling activation, response headers, or measurement ownership. Add separate preview delta evidence only. |
| SP3A lifecycle derivation | Preview uses an in-memory registry-default render context and causes no settings lifecycle derivation. Existing save remains one lifecycle. |
| SP3B ownership/fresh owned-path saves | `card_templates` ownership and fresh-snapshot overlay remain unchanged; preview has no owned path and no save. |
| SP3C library | Read-only library, projection, reference scan, filters, and actions remain unchanged. |
| SP3C one-template draft | Reuse the single `data` draft; do not mount a second form/schema/draft or persist recovery state. |
| Builder summaries/editors | Existing block previews, selected top/nested editor behavior, clone/delete/reorder, and control reductions remain unchanged. |
| Locked/protected state | Existing locked identity/capability/measurement values remain. Unauthorized protected parts stay absent from HTML/state and are never reconstructed for preview; Save still restores them through the writer. |
| Fingerprint/stale/default/reference/collision guards | Preview neither reads nor advances the fingerprint and runs no guard scan. Save continues to enforce all existing guards. |
| Focused writer and lifecycle | Writer remains sole mutation boundary. Preview calls it zero times. Successful Save still produces one save event, backup attempt, and cache invalidation. |
| Siblings/foreign roots/existing data | Preview never overlays or rewrites settings. Writer keeps exact sibling/foreign-root preservation. No migration or data rewrite. |
| Dirty navigation | Existing warning remains authoritative; preview links are inert and slide-over close does not clear dirty state. |
| Import locks/import/restore/normalize/backups/snapshots/cache | No ownership, schema, lock, command, backup, snapshot, restore, normalize, or invalidation change. |
| Roles and mode gates | Existing Admin/Super Admin and single/multi capability checks remain authoritative; no new permission. |
| Public renderer/presenter/Blade | Reuse controlled semantic output. `previewMode` only removes navigation/interaction and optional pre-presented contributor input; public calls retain existing behavior. |

No claim is made for simultaneous-request serialization, scan-to-save TOCTOU
closure, literal database JSON bytes, durable remount recovery, or unmeasured
browser behavior.

## 10. Acceptance checklist and likely implementation forecast

### Required acceptance

- [ ] Create and Edit preview exactly the current unsaved single `data` draft.
- [ ] Preview never calls Save, focused writer, settings reader/list resolver,
      reference scanner, lifecycle save, backup, or cache invalidation.
- [ ] Builder transport cleanup is shared with the writer, not duplicated.
- [ ] Existing validator normalizes exactly one candidate; invalid/zero/multiple
      results show an error and never fall back to a saved template.
- [ ] Value object/resolver uses only the normalized unsaved row.
- [ ] Item, group, and contributor reuse their renderer, presenter, and public
      card/part component.
- [ ] Each family uses one deterministic public-safe sample with all required
      relations/aggregates eager-loaded and zero rendering queries.
- [ ] No valid sample produces the correct translated family empty state.
- [ ] Family change clears transient selection and performs one non-persisting
      refresh; other edits require explicit Refresh.
- [ ] Wide `xl` adjacent region and sub-`xl` slide-over show the same one result
      with one active preview DOM.
- [ ] Loading, stale, invalid-draft, sample-error, and restricted states retain
      the draft and preserve existing editor error ownership.
- [ ] Existing Builder previews/actions, import badge, dirty warning, and
      Save/Cancel/Delete remain unchanged.
- [ ] All new strings exist in HE and EN; RTL/LTR, logical placement, technical
      key direction, and long content are correct.
- [ ] Region/status labels, keyboard actions, focus trap/Escape/restoration,
      independent scroll, and non-color status meet section 7.
- [ ] Preview links/actions are structurally inert and cannot navigate from a
      dirty editor.
- [ ] Protected parts remain absent for unauthorized actors and never appear in
      preview HTML or serialized state.
- [ ] One draft only; no model graph or persisted preview state; no added editor
      controls/wire models.
- [ ] Three-run component/state/query delta procedure passes without changing
      SP3C frozen ceilings.
- [ ] Real authenticated browser acceptance covers responsive resize,
      teleported slide-over, focus, scroll, inert links, locales, and Back, and
      records the required DOM/focusable/network/listener/heap/timing evidence
      or explicitly identifies a runner limitation.
- [ ] Existing writer stale/collision/reference/default/sibling/lifecycle tests
      remain green.

### Likely touched files in a later implementation

| Group | Classification | Forecast |
| --- | --- | --- |
| `app/Filament/Pages/CardTemplateEditorPage.php` and `resources/views/filament/pages/card-template-editor.blade.php` | Likely change | Preview action/state boundary, responsive shell, status and slide-over composition; retain one form. |
| `app/Support/Settings/CardTemplates/CardTemplateFocusedWriter.php` plus one focused draft-normalizer class | Likely change | Extract private transport cleanup without changing writer persistence/guard behavior. |
| One focused preview sample/presenter factory under current Public Front/Card Template support | Likely change | Family-safe bounded queries, in-memory registry-default render context, one presented payload. Not a generalized framework. |
| Public item/group/contributor presenters and renderer | Reuse only unless constructor injection needs a minimal focused seam | Existing semantic projection remains authoritative. |
| Public card and card-part Blade components | Likely minimal change | Bounded `previewMode` inert interaction; contributor may accept optional pre-presented card. Public default behavior unchanged. |
| `lang/he/admin.php`, `lang/en/admin.php` (or existing admin translation ownership files) | Likely change | Every preview action/status/error/empty/accessibility string in both locales. |
| `tests/Feature/SettingsSp3cTest.php` | Test-only | Unsaved parity, zero lifecycle/writer/settings/reference effects, protected state, error retention, family refresh, preservation regressions. |
| `tests/Feature/PublicFrontCardTemplateBuilderTest.php` | Test-only | Presenter/component parity and inert preview links for all families. |
| SP3C canary support/test files | Test-only | Deepest-fixture component/state/query deltas using existing parser/encoding contract. |
| Real-browser measurement and operator procedure in the eventual implementation handoff | Test/manual only | Responsive/focus/teleport/RTL/LTR/Back acceptance plus recorded DOM/focusable/network/listener/heap/timing observations; no fabricated numeric cap. |

Likely implementation split: Task 1 extracts the shared read-only normalization
seam and adds sample/presenter tests; Task 2 adds editor interaction/components,
the bounded selector, freshness details, translations, component budgets, and
browser measurements. Forecast: 1.5–2.5 hours of coding and 1–1.5 hours of
focused tests/browser evidence/ordered verification, 3–4 hours total, with no
migration, dependency, configuration, or data work. Stop for an amended audit
if the candidate needs more than two tasks/four hours, new persistence, a
generalized platform, presenter rewrites, persisted sample management, new
permissions, or any lifecycle/ownership change.

## 11. Open decision and recommendation

Current evidence resolves the design: explicit refresh, automatic and
user-selectable bounded public-safe samples, localized freshness details, `xl`
adjacent layout, sub-`xl` slide-over, shared writer transport cleanup,
in-memory default render context, existing presenters and components,
structurally inert preview interactions, and recorded real-browser evidence.

**Accept the v1 specification or request revisions before any Laravel
Simplifier implementation audit.**
