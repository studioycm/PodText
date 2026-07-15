# SP3 Settings Performance Audit, Prompt Review, and Alternative Architecture

> **Forward-architecture supersession — 2026-07-16:** This remains authoritative
> evidence for the pre-SP3 diagnosis and alternatives considered at that time.
> It is not the current forward architecture for Card Templates or Public Forms.
> The operator-approved ARCH1 decisions in `07-sp3d-pre-research.md` now require
> both domains to become versioned model/Resource aggregates before SP3D.

Date: 2026-07-14

Reviewed prompt: `settings-lazy-sp3-codex-prompt.md`, prompt version v3

Scope: research and planning only; no SP3 application code was changed

## Executive verdict

The diagnosis is sound: the remaining delay is primarily a front-end payload and
hydration problem, not a slow Spatie Settings read. The current settings page
ships all nine tab panels, including all nested template Builders and Repeaters,
in the first response. A locally populated page produced roughly 40,765 DOM
elements, 13.8 MB of serialized live DOM, and about 27,000 JavaScript event
listeners. The Advanced panel alone produced about 72% of the elements.

SP3 v3 points in the right direction by separating templates and replacing the
monolith with subject pages. It should not be executed unchanged. Four parts of
the prompt are technically or architecturally unsafe:

1. Installed Filament 5.6.7 does not provide a general form/schema
   `->deferred()` mechanism. Livewire lazy components and islands exist, but
   they are not interchangeable with deferred Filament field construction.
2. Collapsed Repeater or Builder items are hidden, not lazy. Their fields are
   still built and rendered. This does not satisfy "load on first display."
3. Reducing visible locks must not reduce `SettingsLifecycleSchema` units. Those
   units also define import/export, diff, merge, labels, and semantic paths.
4. Splitting one Spatie Settings object across pages creates stale-page and
   whole-array overwrite risks unless every save reloads current state and
   merges only the page's owned paths. `card_templates` needs a more focused
   writer because it is one array property shared by every template editor.

The recommended approach is a staged SP3, not one all-or-nothing prompt:

- SP3A: repeatable browser measurement, lifecycle memoization, and a separate
  import-lock surface registry;
- SP3B: dedicated Settings navigation group and focused subject pages with a
  shared safe partial-save contract;
- SP3C: lightweight template library and one-template editor that mounts only
  one selected template part at a time;
- SP3D: browser-budget enforcement, regressions, and old-page cleanup.

This preserves the current storage format and shipped security behavior while
making each performance change measurable and reversible.

## 1. Evidence and diagnosis

### 1.1 Installed versions

Installed packages are the source of truth:

| Package | Installed version |
|---|---:|
| Filament | 5.6.7 |
| Livewire | 4.3.3 |
| Spatie Laravel Settings | 3.9.0 |

### 1.2 Browser baseline on populated settings

The signed-in local page was measured with the existing populated settings
payload.

| Metric | Observed |
|---|---:|
| DOM elements | 40,765 |
| Hidden elements | 16,050 |
| Serialized live DOM | 13,803,472 characters |
| Inputs | 306 |
| Buttons | 2,975 |
| Alpine roots | 3,787 |
| Livewire roots | 6 |
| JavaScript event listeners | approximately 26,970 |
| Layout objects | approximately 26,951 |
| Main document TTFB | 2.551 seconds |
| Main encoded transfer | 728,936 bytes |
| DOMContentLoaded | approximately 3.926 seconds |
| Load event | approximately 5.386 seconds |
| Reload wall time samples | 7.248 seconds; 6.858 seconds |
| JavaScript heap used | approximately 123 MB |

The main Livewire page root accounted for approximately 13.65 MB of the live
DOM. A post-load Livewire update was only about 4.9 KB and 45 ms, which further
points to initial response construction, DOM parsing, layout, and Alpine/
Filament initialization rather than ordinary subsequent Livewire updates.

### 1.3 Advanced/template panel concentration

| Metric | Advanced panel |
|---|---:|
| DOM elements | 29,404 |
| Share of all elements | 72.1% |
| Serialized DOM | 10,345,485 characters |
| Alpine roots | 2,695 |
| Buttons | 2,208 |
| Inputs | 180 |
| Builder items | 54 |
| Template rows | 9 |

The nine template rows ranged from approximately 1,728 to 5,835 DOM elements
each. One template alone produced more DOM than a reasonable complete settings
subject page should produce.

### 1.4 Code-shape evidence

The current page contains approximately:

- nine top-level tab panels;
- 19 `Section::make()` calls;
- 20 `Fieldset::make()` calls;
- six `Repeater::make()` calls;
- three `Builder::make()` calls;
- 293 schema/form component construction sites.

`App\Settings\PublicContentSettings` is 2,897 lines and has 37 properties: 23
scalars and 14 arrays. The current Filament Tabs implementation renders every
panel when the tabs do not use a Livewire-backed active-tab property. Alpine
then hides inactive panels. The current Repeater and Builder collapsed states
also render item content and hide it with client-side state.

### 1.5 What SP1 and SP2 proved

SP1 measured a warm settings read at about 3 ms and the form build at about
1.145 seconds. SP2 found repeated lifecycle semantic-path work and memoized it,
reducing the measured PHP form-build phase to about 71-83 ms on the same
approximately 37 KB settings payload.

This was an important backend fix, but it did not measure the remaining Blade
render, Livewire snapshot serialization, response transfer, browser parse,
layout, and Alpine/Filament initialization work. The remote 2-5 second and local
1-2 second reports after SP2 are therefore compatible with the profiler result;
they are not evidence that the memoization failed.

### 1.6 Backend residual

The current Spatie database repository fetches and decodes all properties in a
settings group. The mapper then selects requested properties. The public reader
also validates the whole settings payload before returning one group or slice.
With settings cache disabled locally, repeated consumers can repeat whole-group
reads and validation.

This is a real secondary issue, but it is not large enough to explain the 40,000
component page. Group/slice-scoped reads and per-group caching should remain SP4
work. SP3 must leave a compatible seam for them and must not introduce a storage
migration merely to solve the page payload.

## 2. Review of SP3 v3

### 2.1 Blocking corrections before execution

| Severity | Prompt issue | Required correction |
|---|---|---|
| Critical | The title and Job 3 say "cluster," while the decided construct says dedicated navigation group and no cluster machinery. | Rename the work to "Settings subject pages." Select one explicit navigation model and remove every cluster/sub-navigation alternative. |
| Critical | The prompt repeatedly requires Filament `->deferred()`, but installed Filament has no general form/schema method by that name. | Remove it as an accepted mechanism. Name the installed alternatives explicitly: page split, nested lazy Livewire component, Livewire island for independent content, or an on-demand modal/page. |
| Critical | Collapsed Repeater/Builder rows are treated as first-expand hydration. | State that collapse alone is disallowed as proof of lazy loading. Use a summary collection plus one selected editor, or a verified Builder preview implementation. |
| Critical | The lock diet asks to simplify `SettingsLifecycleSchema` to the reduced visible lock surface. | Keep lifecycle units unchanged. Add a separate registry that maps section and important-field locks onto lifecycle units. |
| Critical | Page/template saves can overwrite settings changed after mount. | Reload current stored settings at save time, overlay only owned validated paths, apply authorization overlays, and persist atomically. Add stale-page tests. |
| Critical | Each template editor writes an entry inside the shared `card_templates` array. | Add a focused template writer with an atomic reload/replace operation and optimistic conflict detection or an application lock around the short write transaction. |
| High | The template list requires a per-template updated timestamp that does not exist in current storage. | Remove the column. A group-row timestamp is not a truthful per-template timestamp. Adding per-template metadata is a storage contract change and belongs in a separately approved migration. |
| High | The prompt routes templates by `key`, but current uniqueness identity is `family:key`. | Route and replace by family plus original key, or by another unambiguous encoded identity. Keep original identity separate from editable identity during rename. |
| High | `blockPreviews()` is described as if it automatically produces previews and strict lazy construction. | Require a preview view for every supported block and a worst-case canary. Treat it as a DOM optimization, not guaranteed backend schema deferral. |
| High | The important-fields list is promised for operator veto, but the same run proceeds to implement it. | Make the list an approval gate in a research/plan prompt, or include the approved list in the implementation kickoff. |
| High | One prompt combines lock semantics, navigation, nine page schemas, a template editor rewrite, instrumentation, tests, and cleanup. | Split it into SP3A-D so attribution, review, and rollback remain practical. |
| Medium | "Every tab maps to exactly one page" conflicts with Advanced becoming a list/editor pair. | Say eight ordinary tabs map one-to-one; Advanced is replaced by the template library and template editor. |
| Medium | `SettingsPageProfiler` is called a trait and is expected to measure response completion and compressed bytes. | Keep PHP phase timing in the scoped profiler. Use response middleware for uncompressed response length and a browser harness for TTFB, transfer bytes, DOM, listeners, and interactivity. |
| Medium | "Mount queries <=12" is ambiguous because panel navigation and unrelated providers contribute queries. | Record total queries, settings repository reads, and duplicate lifecycle loads separately under one fixed harness. |
| Medium | "No tabs" is global wording. | Scope the rule to this public-content settings family; do not accidentally prohibit unrelated Filament tabs elsewhere. |
| Medium | The prompt can execute unspecified LENS1 audit corrections listed later in a kickoff. | Require an exact enumerated list or the word "none." Do not allow an open-ended scope rider. |
| Low | The durable lesson is written before the implementation proves the result. | Write the lesson after final measurements, or label it as a proposed lesson until proven. |

### 2.2 Navigation model

Use one new top-level Hebrew-first Settings navigation group. Filament navigation
groups do not nest. The pages can keep ordinary breadcrumbs and an app-owned
sibling-page link strip if that improves orientation, but this must not be
called a Filament cluster.

Existing dedicated pages should be linked and retained unless the prompt
explicitly requests a route/class migration:

- public forms;
- admin UX settings;
- settings backups;
- settings import;
- import-lock management, either still action-only/hidden or explicitly made a
  visible Settings page.

The old monolith slug should redirect to the first settings subject page while
preserving authorization and, where meaningful, query strings. Tests should
cover refresh, browser back, direct deep links, and unauthorized access.

### 2.3 Safe partial-settings page contract

Each subject page should declare:

- the scalar paths and top-level groups it owns;
- the focused schema factory it renders;
- the validator groups it invokes;
- its section-lock identifier;
- any super-admin visibility/authorization overlays;
- its profiler subject key.

A shared focused base page or concern should implement the persistence contract:

1. authorize the page and the direct Livewire save call;
2. get only the owned form state;
3. validate only owned groups through `validateGroups()`;
4. inside the save boundary, reload the latest complete stored settings;
5. overlay only the owned validated paths;
6. apply `MultiTranscriptionSurfaces::overlayUnauthorizedSettings()` so hidden
   settings and forged values cannot change;
7. save through the existing event/backup/cache-invalidation path;
8. notify and profile without logging settings values.

Do not build a generic field-definition engine. A small subject registry is
useful for ownership and navigation metadata, but each domain should retain a
typed schema factory. This avoids replacing one 2,000-line page with a new layer
of opaque configuration.

### 2.4 Lock diet without lifecycle damage

`SettingsLifecycleSchema` must remain the complete semantic registry for:

- import/export units;
- diff and merge behavior;
- path labels;
- selected-unit validation;
- stale import handling;
- existing backup and lifecycle tests.

Add a separate `SettingsImportLockSurfaceRegistry` (name illustrative) for the
admin lock UI. It should expose only:

- section-level lock choices, each mapped to one or more existing lifecycle
  units;
- approved important-field locks mapped to exact existing lifecycle units.

Stale lock normalization should resolve against this lock-surface registry.
Removing a visible per-item lock must not delete or merge the underlying
lifecycle unit. This separation is the safest interpretation of "locks only on
sections and important fields."

Memoization should be applied in the owning lifecycle class before or separately
from the lock UI reduction. Its cache key must include the lifecycle group and
payload identity; the same request can inspect current, imported, and merged
payloads, so a single unkeyed memo would return stale results.

### 2.5 Important-fields proposal for operator veto

Recommended minimal list:

1. `maintenance.enabled`
2. `maintenance.raw_html_override`
3. `public_forms.require_email_verification`
4. `transcription_policy.public_mode`
5. `transcription_policy.count_mode`
6. `transcription_policy.show_multiple_transcriptions_on_item_page`
7. `AdminUxSettings.transcription_mode`, if that class is included in the same
   lifecycle/import-lock mechanism

All other nested template parts, template rows, and Repeater items should lose
individual lock decoration. Sensitive maintenance content can remain protected
by its section lock unless the operator explicitly adds a field-level exception.

### 2.6 Template library and editor

The template library can remain a lightweight custom page over normalized array
data; a fake Eloquent model is unnecessary. It should display:

- family and key as the composite identity;
- translated label;
- enabled/disabled state;
- template type;
- where-used count from a centralized reference scanner;
- edit, create, and clone actions.

Do not show a per-template updated timestamp until the storage contract actually
has one. `where-used` must be calculated once outside table column rendering and
cached or deferred if measured as expensive.

The editor should load exactly one template. It must preserve an immutable
original identity while family/key fields are edited. On save it should:

1. validate the new `family:key` uniqueness against freshly loaded siblings;
2. find the original identity;
3. replace only that entry;
4. preserve every sibling byte-for-byte;
5. apply the multi-transcription overlay, including gated template parts;
6. redirect to the new composite identity after a rename;
7. reject a stale write when the same template changed since mount.

### 2.7 Builder preview is a canary, not a premise

Installed Filament has `Builder::blockPreviews()`, but preview mode requires
each Block to define a preview view. The current block factories do not define
those previews. Preview mode reduces rendered form DOM; it does not prove that
all cloned child component objects are absent from server construction.

Before adopting it across the editor, build a worst-case canary and verify:

- every block type has a safe, escaped summary view;
- edit, cancel, validation failure, delete, clone, and reorder work;
- reactive/conditional fields still update;
- nested `part_group` children work;
- adding a second instance of the same block works;
- unsaved edits survive the preview/edit transition;
- no gated field is exposed or saved for a non-super-admin;
- the browser DOM budget improves materially on the installed versions.

Two older Builder preview issues are closed, but that is not proof of the local
combination. Validate the installed version with the deepest real template.

If the canary fails, the fallback must not be a collapsed full Builder. Use a
summary list plus a selected-part editor in a nested Livewire component, modal,
or dedicated child route. Only the selected part owns a mounted form.

### 2.8 Appropriate use of Livewire 4

Use page splitting before adding islands. Most ordinary subject pages may be
small enough after the split and should not pay for extra deferred requests.

Use Livewire islands for independent read-only regions such as a preview or
where-used summary. Islands cannot be placed inside loops or conditionals, and
parallel island updates that share state can race. Use a nested Livewire
component with its own lifecycle and save boundary for a stateful selected-part
editor.

Every lazy/deferred child must repeat authorization. Hiding navigation or a
parent field is not sufficient protection for direct component requests.

## 3. Alternative approaches

| Alternative | Benefit | Cost/risk | Verdict |
|---|---|---|---|
| Subject pages plus separate template library/editor, current storage retained | Removes most initial DOM, matches operator navigation decision, limits migration risk | Requires disciplined partial-save and template concurrency handling | Recommended SP3 architecture |
| Keep monolith, use a Livewire-backed active tab, and render only the selected tab | Lowest-risk interim reduction; can be a rollback/canary | Tabs remain; PHP may still construct tab schemas unless explicitly conditional; does not satisfy the decided UX | Useful emergency interim only |
| Generic registry dynamically defines all settings fields and pages | Central metadata and less repeated boilerplate | Becomes a new framework, weakens type clarity, and can hide lifecycle/security rules | Use registry only for ownership/navigation metadata |
| Split into several Spatie Settings classes/storage groups | True smaller reads and clearer ownership | Storage migration, public-reader changes, import/export compatibility work | Consider after SP4 evidence, not SP3 |
| Promote templates to first-class database records | True per-template timestamps, row-level writes, optimistic locking, partial queries | Largest migration and portability change; settings backup/import format must evolve | Strong long-term option for a later approved phase |
| Client-side virtualized Filament form | Potentially small visible DOM | Poor fit for authoritative Livewire validation and nested Builder state; high complexity | Reject |
| Custom Livewire/Alpine normalized template editor | Can guarantee strict selected-part mounting | Highest custom UI and maintenance cost | Fallback if native preview canary fails |

### 3.1 Long-term template rethink

If template count, collaboration, history, or per-template timestamps continue
to grow, templates have crossed the boundary from "settings" into managed
records. A future design could use a `card_templates` table with stable UUID,
family/key unique constraint, JSON definition, enabled state, timestamps, and an
optimistic version. A compatibility serializer would still export/import the
portable settings representation.

That model is cleaner for concurrent editing and audit history, but it violates
SP3's no-migration contract. It should be evaluated explicitly later, not
smuggled into the performance fix.

## 4. Recommended replacement work sequence

### SP3A — measurement and lock foundation

Deliverables:

- freeze the repeatable browser dataset and measurement protocol;
- measure five warm and at least one cold run with profiler off and on;
- distinguish PHP phases, response body bytes, encoded transfer, browser DOM,
  listeners, heap, and time to interactive;
- memoize lifecycle derivation in its owning class with payload-aware keys;
- add the lock-surface registry without changing lifecycle units;
- publish the important-fields list and stop for operator veto before changing
  the lock UI.

### SP3B — settings subject pages

Deliverables:

- add the dedicated Settings navigation group;
- create eight focused subject pages from the eight non-Advanced tabs;
- retain/link existing forms, admin UX, backup, import, and lock-management
  pages according to one explicit navigation map;
- implement the shared owned-path hydration/validation/save contract;
- redirect the old slug;
- prove stale page A cannot overwrite a newer page B save;
- measure every ordinary subject page before introducing any island.

### SP3C — template library and selected editor

Deliverables:

- replace Advanced with the lightweight template library;
- route by composite identity;
- implement focused current-state template writes and conflicts;
- add clone/create/rename/collision behavior;
- run the Builder preview canary;
- if it passes, use defined block previews; if it fails, mount only the selected
  part in a nested editor;
- verify all template siblings remain byte-identical after one-template saves.

### SP3D — budgets and cleanup

Deliverables:

- enforce agreed DOM/response/query budgets with a repeatable browser audit;
- test all direct page/component authorization paths;
- test save-before-lazy-hydration and navigate-away/back behavior;
- remove the monolith only after old-slug and deep-link coverage is green;
- relocate tests without reducing behavior coverage;
- write the durable performance lesson from final evidence.

### SP4 — retained later scope

- group/slice-scoped settings reads and cache invalidation;
- live public preview and the Peek-versus-owned-preview decision;
- no storage migration unless separately approved.

## 5. Acceptance contract

### 5.1 Measurement protocol

Define before implementation:

- fixed browser and viewport;
- fixed nine-template worst-case fixture;
- profiler on/off state;
- cache cold/warm state;
- five-run median and p95 or clearly identified samples;
- initial navigation and fully expanded/editor-active measurements;
- deferred request count, aggregate transferred bytes, and aggregate hydration
  time so work cannot merely move after the load event.

### 5.2 Proposed budgets

Use one coherent budget set rather than overlapping targets:

| Surface | Initial DOM target |
|---|---:|
| Ordinary subject page | under 3,000 elements |
| Template library | under 2,000 elements |
| Template editor before a part is selected | under 4,500 elements |
| Unselected template/part form fields | zero in DOM |

Also target:

- warm median TTFB under 800 ms under the fixed local harness;
- no repeated lifecycle derivation for the same payload within one request;
- settings repository reads and total panel queries reported separately;
- no console errors or warnings;
- profiler logs contain only timing/count/size metadata, never settings values,
  trusted HTML, tokens, or upload content.

### 5.3 Required integrity and security tests

- guest, authenticated non-admin, admin, and super-admin access for every page;
- direct Livewire calls and lazy child component authorization;
- hidden settings remain byte-identical for unauthorized saves and forged
  payloads;
- stale page A save preserves newer page B state;
- saving without hydrating an optional child preserves that child's settings;
- one-template edit preserves all sibling templates byte-for-byte;
- same-template stale edit reports a conflict instead of overwriting;
- family/key collision, rename, clone, missing identity, and direct URL cases;
- every Builder block type and nested group operation;
- trusted maintenance HTML remains the deliberate trusted-admin exception while
  all preview summaries escape ordinary content;
- moved uploads retain MIME type, size, disk, visibility, and validation rules;
- retired lock normalization cannot fatal or alter unrelated settings;
- existing SettingsSaved, backup, public-render-context, and cache invalidation
  behavior still occurs.

## 6. Settings decision registry

| ID | Item | Status after this review | SP3 treatment |
|---:|---|---|---|
| 1 | Original slow-page complaint | Diagnosed | Keep repeatable browser and PHP measurements. |
| 2 | Remote 2-5s/local 1-2s after SP2 | Explained, not closed | Front-end payload/hydration is now the primary SP3 target; read-cache work stays SP4. |
| 3 | Locks only on sections and important fields | Direction accepted; veto pending | Use a separate lock-surface registry; do not reduce lifecycle units. |
| 4 | Tabs are optional | Decided | Remove top-level settings tabs in final architecture. |
| 5 | All-in-one page is optional | Decided | Use subject pages. |
| 6 | Modern Filament/Livewire mechanisms | Corrected | Use only installed, proven mechanisms; no generic Filament `->deferred()`. |
| 7 | Every heavy part loads on first display | Strengthened | Summary plus selected editor; collapse alone is non-compliant. |
| 8 | NAV1 placement under site management | Superseded | Move to one dedicated Settings navigation group. |
| 9 | Dedicated settings group | Confirmed | Ordinary pages, no cluster machinery. |
| 10 | Templates separate | Confirmed | Lightweight library plus one-template editor. |
| 11 | Existing-or-new template on one editor page | Confirmed with identity correction | Explicit create state; edit by composite identity. |
| 12 | Template preview/Peek investigation | Deferred | SP4 for public live preview; native block-summary canary is allowed in SP3C. |
| 13 | Clone settings items/templates | Partially shipped | Preserve existing cloner; add template clone on library with composite uniqueness. |
| 14 | Pull only necessary settings groups | Deferred | SP4; keep SP3 partial-save seams compatible. |
| 15 | Forms on their own page/plain POST maintenance rule | Shipped precedent | Retain dedicated forms page and safest maintenance submission behavior. |
| 16 | One main flag patterns | Shipped | Preserve global switches and exact save behavior. |
| 17 | Multi-transcription controls super-admin only | Shipped | Repeat authorization/visibility in every new page and child component. |
| 18 | White-label single-mode behavior | Shipped/terminology follow-up | Do not expose hidden capability through routes, previews, or payloads. |
| 19 | Hidden settings survive saves | Shipped, regression-critical | Fresh-state overlay and byte-identical tests are mandatory. |
| 20 | Trusted raw-HTML editor | Shipped | Move without changing trusted-admin semantics or LTR default. |
| 21 | Descriptions become hint icons/simple Hebrew | Queued | Preserve as a separate ADM1-B content task; do not mix into SP3 performance attribution. |
| 22 | Exact maintenance marker copy | Shipped | Preserve exact constant and regression test. |
| 23 | YouTube/LaravelDaily/FilamentDaily research | Completed enough for mechanism selection | Islands/lazy are available; videos are secondary to installed source and official docs. |
| 24 | Latest Filament 5/Livewire 4 research | Corrected with installed proof | Block previews and Livewire islands exist; general Filament deferred schemas do not. |

## 7. Suggested prompt rewrite rules

Before using SP3 as an implementation contract:

1. replace every "cluster" phrase with the selected navigation-group model;
2. delete every general Filament `->deferred()` requirement;
3. delete collapsed Repeater/Builder as an accepted lazy fallback;
4. keep lifecycle units intact and specify the new lock-surface registry;
5. provide the operator-approved important-fields list in the kickoff;
6. state the eight-page plus template-pair mapping explicitly;
7. remove the unsupported per-template updated column;
8. route templates by `family:key` and define rename/concurrency behavior;
9. specify the safe current-state partial-save algorithm and security overlay;
10. split the contract into SP3A-D, each with its own research, plan, tests,
    measurements, and canonical handoff;
11. make browser metrics the responsibility of a browser audit harness, not the
    PHP page profiler;
12. replace the exact final sentence with a normal requirement-classified
    handoff plus concise operator summary.

## 8. Research provenance and references

Research used:

- installed vendor source for Filament 5.6.7, Livewire 4.3.3, and Spatie
  Laravel Settings 3.9.0;
- local browser/CDP inspection of the signed-in populated settings page;
- SP1/SP2 profiler evidence and current settings-performance research docs;
- Laravel Boost installed-version documentation search;
- FilamentExamples search/snippet access; no source/detail endpoint was
  available, so this was not treated as deep source research;
- official documentation and relevant upstream issue status.

References:

- [Filament Builder and block previews](https://filamentphp.com/docs/5.x/forms/builder)
- [Filament navigation overview](https://filamentphp.com/docs/5.x/navigation/overview)
- [Filament custom pages](https://filamentphp.com/docs/5.x/navigation/custom-pages)
- [Livewire 4 islands](https://livewire.laravel.com/docs/4.x/islands)
- [Livewire 4 lazy loading](https://livewire.laravel.com/docs/4.x/lazy)
- [Filament Peek repository](https://github.com/pboivin/filament-peek)
- [Filament Peek page previews](https://github.com/pboivin/filament-peek/blob/4.x/docs/page-previews.md)
- [Closed Builder block-preview modal issue](https://github.com/filamentphp/filament/issues/17915)
- [Closed Builder preview reactivity issue](https://github.com/filamentphp/filament/issues/13971)
- [Laravel Daily Livewire 4 changes course](https://laraveldaily.com/lesson/livewire-v4/main-changes-from-v3)

## 9. Assumptions and deferred decisions

- The reviewed v3 file is a proposal, not an instruction to implement SP3 in
  this session.
- Storage stays in `PublicContentSettings` during SP3.
- No Composer or npm package is added in SP3.
- Peek is not selected or installed by this report.
- The important-fields list remains subject to operator veto.
- Making templates first-class records is a later architectural option, not an
  implicit SP3 change.
- Group-scoped reads, settings-cache policy, and public live preview remain SP4.
- Final numeric budgets should be frozen against the committed worst-case
  fixture before implementation begins.
