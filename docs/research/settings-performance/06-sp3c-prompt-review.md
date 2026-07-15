# SP3C prompt v1 review and corrected execution contract

> **Forward-architecture supersession — 2026-07-16:** The SP3C audit and shipped
> behavior remain historical evidence. Its no-migration future boundary is
> superseded by the operator-approved ARCH1 decisions in
> `07-sp3d-pre-research.md`: versioned Card Template and Public Form Resources
> must be accepted before SP3D performs legacy cleanup and final enforcement.

> **Historical review notice — 2026-07-15:** This document audits SP3C prompt
> v1 and remains evidence for that review round. The authoritative execution
> contract is now
> `prompts/pre-13-prompts/settings-sp3c-codex-prompt.md`, prompt version v3.
> V3 incorporates this report and the later second-order audit, including
> corrections to route decoding, measurement-state trust, protected-template
> actions, delete semantics, backup/transaction claims, canonical sibling
> preservation, reference derivation, virtual-default identity, and canary
> measurement. If this historical review conflicts with v3, v3 governs.

Date: 2026-07-15
Reviewed prompt: `prompts/pre-13-prompts/settings-sp3c-codex-prompt.md`
Prompt version: v1 — 2026-07-14
Review only: no SP3C application code was implemented.

## Executive verdict

**Do not execute SP3C v1 unchanged.** Its product direction is sound: replace
the temporary all-template Repeater with a small library and a focused editor,
retain SP3B's fresh settings snapshot discipline, and gate the editor design on
a real worst-case canary. However, the prompt currently contains five critical
contract cavities and several high-severity factual mismatches.

The most important corrections are:

1. Define a target-only writer that validates one template without normalizing
   or replacing its siblings. The SP3B subject-page writer owns the entire
   `card_templates` property and cannot be reused unchanged.
2. Make the original `family:key` the authorization and stale-check identity
   until the final splice. A proposed rename must not change the identity used
   to restore protected fields from the same fresh snapshot.
3. Block renames while explicit consumers still reference the old identity,
   and protect the three registry-default identities. SP3C does not own all
   reference locations and cannot rewrite them atomically.
4. Treat Builder previews as a rendered-form-control optimization, not as
   backend schema deferral or literally zero DOM elements. Filament still
   constructs a child schema for every Builder item and renders item chrome.
5. A selected-part modal or nested component may edit the parent page's
   unsaved draft, but it must never create an independent settings-save
   boundary. One successful page save must emit exactly one `SettingsSaved`
   event and therefore exactly one existing lifecycle run.

With the corrections below, SP3C remains migration-free, keeps the SP3A
lifecycle units byte-identical, preserves SP3B's ownership split, and provides a
credible performance experiment instead of asserting unverified DOM budgets.

## Audit basis

The review checked the prompt against:

- the current branch through the SP3B implementation/backfill and the later
  logo/favicon, Curator hydration, collapsible-navigation, and OTP work;
- `docs/research/settings-performance/02-sp3-prompt-review-and-alternatives-report.md`;
- the SP3A research, plan, and handoff, including the frozen fixture and
  lifecycle hash;
- the SP3B research, implementation plan, and handoff;
- the current settings pages, ownership registry, validator, card-template
  resolver/registry, authorization overlay, lifecycle listener, measurement
  middleware, lock model, and affected tests;
- installed Filament 5.6.7, Livewire 4.3.3, Laravel 13.19.0, and
  `spatie/laravel-settings` 3.9.0 source;
- installed Filament Blueprint planning guidance;
- Laravel Boost's installed-version package report and documentation search;
- two FilamentExamples search passes. The configured server exposed search
  results only, not a source/detail reader, and returned no example that is
  authoritative for PodText's focused settings writer. Installed vendor source
  therefore remains the deciding evidence.

Relevant primary-source locations include:

- `app/Filament/Pages/PublicContentSettingsSubjectPage.php`
- `app/Filament/Pages/SettingsSubjectOwnershipRegistry.php`
- `app/Filament/Pages/BuildsPublicContentSettingsSubjectSchemas.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRegistry.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateResolver.php`
- `app/Support/Transcriptions/MultiTranscriptionSurfaces.php`
- `app/Providers/AppServiceProvider.php`
- `app/Support/Settings/SettingsPageProfiler.php`
- `app/Http/Middleware/MeasureSettingsSp3aResponse.php`
- `vendor/filament/forms/src/Components/Builder.php`
- `vendor/filament/forms/resources/views/components/builder.blade.php`
- `vendor/filament/forms/src/Components/Concerns/HasPreview.php`
- `vendor/filament/tables/docs/09-custom-data.md`
- `vendor/spatie/laravel-settings/src/Settings.php`
- `vendor/filament/spatie-laravel-settings-plugin/src/Pages/SettingsPage.php`

## Current shipped baseline that SP3C must preserve

### SP3A

- The committed measurement fixture contains nine templates and 54 flat
  `custom_text` parts and is 37,982 bytes. It is deliberately not a deepest
  nested Builder fixture.
- The frozen lifecycle serialization is 30,413 bytes with SHA-256
  `61e551a60016b1ac0c9aa8051463818adf31677bea465ac0e9b269fe3d2386b8`.
- The authenticated browser baseline reported 29,404 DOM elements for the
  Advanced tab. That number is a browser measurement, not a server-rendered
  crawler count.
- Lifecycle units are family-level units. There is no part-level lock unit, and
  changing that segmentation is outside SP3C.

### SP3B

- `SettingsSubjectOwnershipRegistry::CARD_TEMPLATES` owns the complete
  `card_templates` root property and validator group.
- `PublicContentSettingsSubjectPage::save()` refreshes the canonical settings
  object, takes one fresh full snapshot, overlays only the subject's owned root
  properties, applies authorization against the same snapshot, validates the
  owned group, fills the full settings payload, and calls `save()` once.
- The temporary `CardTemplateSettings` page mounts the complete existing
  `cardTemplatesTab()` Repeater/Builder schema at
  `/settings/card-templates`.
- SP3B proves sequential disjoint-owner freshness. It does not promise
  same-owner merging or simultaneous-request serialization.

### Changes after SP3B

The later changes do not alter card-template storage, normalization, lifecycle
units, or settings ownership:

- `9d8296f` changes panel logo/favicon assets and providers;
- `23a6ce9` plus `2ea189f` preserve Curator picker hydration and document it;
- `d128cfd` makes the existing admin navigation groups collapsible;
- `0394ab5` plus `41cf3c5` configure and document the OTP policy.

SP3C must preserve those changes, keep the library item in the existing
collapsible Settings group with the current item icon/order conventions, and
keep its editor pages out of navigation. There is no reason to rewrite Curator,
OTP, public forms, logo/favicon, import, restore, normalize, backup, cache, or
Admin UX persistence in this step.

## Claim verification matrix

| Prompt claim | Verdict | Evidence and correction |
|---|---|---|
| A lightweight page can read the normalized array without a fake model. | Verified with qualification | Filament 5.6.7 supports `Table::records()` custom array data. Record keys must be stable and unique. Search, sort, filter, and pagination are not automatic for custom data and must be implemented in the `records()` closure if enabled. |
| Exactly one template can be mounted in the editor. | Verified | Use a separate editor state containing one target template, not the SP3B whole-property form schema. |
| Unselected templates/parts can own zero rendered elements. | False as written | Filament renders Builder list/item/action chrome. `Builder::getItems()` also constructs and clones a child `Schema` for every valid item. The enforceable contract is zero **editor form controls and field-wrapper DOM** for unselected templates/parts. |
| `blockPreviews()` can avoid rendering collapsed forms. | Partially verified | The Builder Blade renders a block preview only when the Builder enables previews and the Block has `preview()`. Otherwise it renders the complete item schema. Edit uses a modal cloned from the selected child schema. This is a viable canary, not proof of backend schema deferral. |
| Every block preview can be safe and escaped. | Feasible but not automatic | `Block::renderPreview()` passes the raw state array to a Blade view. Each preview must explicitly escape user content and use only app-owned translations/formatters. No `{!! !!}`, raw HTML, or renderer side effects. |
| Sibling templates can survive byte-identically. | Feasible only with a new writer | Whole-group validation normalizes every template and can rewrite/drop invalid or legacy sibling shapes. Validate one candidate in isolation and splice it into the untouched fresh raw array at the original index. |
| The SP3B fresh owned-path writer can directly provide the focused save. | False without specialization | SP3B owns all of `card_templates`; overlaying the editor's mounted copy would overwrite siblings. Reuse its transaction/hooks/profiler/fresh-snapshot/one-save lifecycle, but replace its whole-property extraction and group validation with the focused algorithm below. |
| Stale same-template edits can be detected without timestamps. | Verified for sequential requests | Capture a canonical target fingerprint at mount, refresh on save, locate the original identity exactly once, and compare only that target. This does not serialize truly simultaneous requests and the prompt must not claim that it does. |
| Rename can simply redirect to the new identity. | Unsafe as written | Explicit references exist outside the editor's owned root. A rename can strand `podcasts_page.template_key`, `podcasts_page.item_template_key`, and `HomepageSection.display_config.template_key`. Default identities also have implicit resolver meaning. |
| The authorization overlay prevents protected parts being exposed or saved. | False for exposure; incomplete for rename | The existing overlay is a save guard, not a read-state filter. It matches templates by the incoming identity, restores a stored template's complete `parts` list when it contains an unauthorized part, and can mis-handle a proposed identity rename unless original identity is carried explicitly. |
| Library columns include enabled state and template type. | False for current storage | Allowed template keys are `key`, `family`, `label`, `layout`, `density`, `image_size`, `title_size`, and `parts` (plus legacy aliases accepted during normalization). There is no template `enabled` field and no generic template `type` field. |
| The template label is translated. | Misleading | `label` is stored administrator-authored content. It must be HTML-escaped and can fall back to `key`; it is not itself a translation key. Family/layout/column/action labels are translated in Hebrew and English. |
| Where-used can be cached or deferred if expensive. | Underspecified | A durable cache needs invalidation on both `SettingsSaved` and every relevant `HomepageSection` mutation. Deferred computation cannot supply a synchronous table count. SP3C should use one request-local scan and one projected HomepageSection query; add persistent caching only in a separately justified future step. |
| Library `< 2,000` and preselection editor `< 4,500` are established budgets. | Unverified | They are inherited aspirations, not measurements. They use a different measurement surface from the 29,404 browser baseline and omit a selected-part ceiling. Calibrate using the canary and freeze final budgets with headroom. |
| Absence of unselected inputs can be asserted by input name. | Insufficient | Filament/Livewire may use generated IDs, state paths, and `wire:model` rather than useful HTML names. Add stable `data-sp3c-*` markers and sentinel values and inspect field wrappers, control IDs, `wire:model` paths, and serialized component state. |
| Profiler subject keys already exist as an extension point. | False | `SettingsPageProfiler` currently records phase, milliseconds, request kind, and optional payload bytes only. SP3C must define a subject context/API or a stable phase-prefix convention and test it. |
| Measurement mode automatically works on both new pages. | False without explicit integration | The SP3A response middleware handles local, query-gated GET responses. A custom library/editor must deliberately carry measurement mode, fixture projection, save refusal, and Livewire-request semantics. |
| The SP3A fixture is suitable for the deepest canary. | False | It is a frozen flat load fixture. Keep it byte-identical and add a separate test-owned SP3C fixture containing nested `part_group` children, repeated block types, and all applicable block types. |
| Import locks should gate ordinary editor saves. | False | Existing import locks protect import behavior. They must continue to display where useful but must not become a new normal-save lock. The family-level lifecycle lock surface is the relevant ordinary settings lock. |

## Severity-ranked findings and exact corrections

### Critical — C1: the focused writer is not specified tightly enough

**Risk:** Reusing `PublicContentSettingsSubjectPage::save()` with a form whose
state contains `card_templates` can overwrite later sibling edits. Running
`validateGroups(..., ['card_templates'])` on the whole fresh list can normalize
siblings and violates the promised byte identity even if only one target was
edited.

**Correction:** Require the exact target-only save algorithm in the next prompt:

1. Authorize page access and edit ability. Refuse all saves in measurement
   mode before starting the lifecycle.
2. Get the editor's validated/dehydrated one-template draft without any sibling
   templates in Livewire state.
3. Resolve the canonical `PublicContentSettings` instance, call `refresh()`,
   and take one fresh full settings snapshot.
4. Read the fresh **raw** `card_templates` array from that snapshot. For edit,
   locate the immutable original `family:key` exactly once; zero or multiple
   matches are a conflict. For create, no original target exists.
5. Compare the fresh target's deterministic canonical fingerprint with the
   locked mount fingerprint. If it differs, refuse with a stale conflict.
6. Check the desired identity against all fresh siblings. Reject a collision.
7. Keep the requested new identity aside. Associate the dehydrated draft with
   the **original** identity and rehydrate/restore protected state against the
   same fresh target and snapshot before validation.
8. Restore the requested identity onto that guarded target, then validate and
   normalize a **one-element** `card_templates` list using the existing
   validator. Require exactly one normalized result and surface every
   `invalidConfig` result as a validation failure; never silently accept a
   dropped or defaulted candidate.
9. Apply the final target-specific authorization overlay to the normalized
   result against the same fresh snapshot. Its API must accept the original
   identity explicitly; it must not attempt to match a renamed candidate by
   its requested identity.
10. Splice only that final guarded target into the raw fresh array at the
    original numeric position. Creation and clone creation append in a defined
    order. Do not normalize, reindex semantically, sort, or rebuild siblings.
11. Overlay the resulting `card_templates` root into the same fresh full
    settings snapshot, call `fill()` once, and call `save()` exactly once.
12. Preserve the existing transaction, hooks, profiler measurements,
    notification, redirect, `rememberData()`, and exception behavior.

Tests must compare the JSON/canonical bytes, array index, and order of every
fresh sibling before and after edit, and must prove a concurrently changed
foreign root property survives.

### Critical — C2: rename can break references that SP3C does not own

**Risk:** Template selectors are stored in two settings paths and in normal
database records. SP3C prohibits schema/storage expansion and the focused writer
owns only `card_templates`, so it cannot safely rewrite all consumers in the
same save lifecycle. The three default identities also have fallback meaning in
`PublicFrontCardTemplateResolver` even when no explicit selector exists.

**Correction:** Define one centralized reference scanner and adopt this policy:

- Explicit settings references:
  `podcasts_page.template_key` in family `content_group` and
  `podcasts_page.item_template_key` in family `content_item`.
- Database references: every non-null
  `HomepageSection.display_config.template_key`, using
  `display_config.template_family` when valid and otherwise
  `PublicDisplaySectionRegistry::defaultTemplateFamilyForSourceType()`.
- The three registry defaults are reported separately as implicit/default use,
  not as a finite database count.
- Block rename and delete when explicit references exist. Instruct the operator
  to repoint them first.
- Always block rename/delete of a configured override whose identity equals a
  registry default identity. SP3C has no alias/tombstone metadata.
- Permit rename only for an unused, non-default configured template. After a
  successful rename, redirect to the new editor URL and make the old URL 404.

This also defines deletion behavior. The current all-template Repeater permits
row deletion, while v1 does not say where that existing capability goes.
Deletion should be a guarded editor header action using the same focused writer,
not a second writer on the library page.

### Critical — C3: authorization must protect hydration as well as saving

**Risk:** `MultiTranscriptionSurfaces::filterCardAttributeOptions()` preserves
a current protected attribute so existing state can render, and
`overlayUnauthorizedSettings()` restores/strips state only during save. A
non-capable user could therefore receive `transcription_count` in initial HTML,
Livewire state, a preview summary, or a modal even if a forged save is later
blocked. Capability also means super-admin **and** multi-transcription mode, not
merely "super-admin."

**Correction:** Specify all of the following:

- Run an authorized read projection before filling library/editor/preview
  state. Protected part tokens, labels, values, and summaries must be absent
  from initial HTML and serialized Livewire state for a non-capable user.
- Because the current save overlay restores the entire `parts` list when a
  stored template contains any protected part, adopt an explicit conservative
  editing policy: a non-capable user may edit the template shell fields, but
  the complete parts editor for such a template is unavailable/read-only and
  fresh stored parts are restored wholesale. Do not promise "editing any
  block" to that role.
- New/forged protected parts are stripped/rejected, including nested children.
- Carry locked original identity separately through authorization. Do not ask
  the existing identity-based overlay to infer a renamed target.
- Test initial HTML, preview HTML, Livewire state, modal state, forged create,
  forged edit, forged nested state, and forged rename for admin, super-admin in
  single mode, and super-admin in multi mode.

If a later product decision wants non-capable users to edit allowed parts
around a protected part, that requires a new per-part merge policy and should
not be invented inside SP3C.

### Critical — C4: the fallback must not introduce a second save lifecycle

**Risk:** v1 permits a nested component or modal "with its own authorization
and save boundary." If that boundary calls Spatie settings save, one user page
save can produce multiple `SettingsSaved` events, backups, cache invalidations,
and render-context resets. `AppServiceProvider` already performs those effects
after every `SettingsSaved` event.

**Correction:** Replace "own save boundary" with:

> The modal or nested component owns only selection validation and mutation of
> the parent page's unsaved draft. It never resolves or saves
> `PublicContentSettings`. Cancel, validation failure, and stale conflict cause
> zero settings saves. One successful page-level save causes exactly one
> `SettingsSaved` event, one backup creation, one cache invalidation sequence,
> and one render-context reset sequence.

### Critical — C5: zero elements and schema deferral are impossible claims

**Risk:** The literal acceptance test cannot pass honestly. Builder preview
items necessarily render wrapper/action elements, and `Builder::getItems()`
constructs a cloned schema for each valid item before Blade decides between the
preview and full item rendering.

**Correction:** Replace every "zero rendered elements" claim with:

> Unselected templates are absent from editor state and DOM. Within the selected
> template, unselected parts render only escaped summary/action chrome and zero
> editor form controls or Filament field-wrapper DOM. Exactly one selected part
> may render editor controls. No claim is made that Filament defers all PHP
> schema construction.

The canary should still reject previews if the measured reduction is
insufficient; it simply evaluates the real mechanism.

### High — H1: library schema columns/actions contradict current storage

There is no stored `enabled` field and no generic template `type`. Adding either
would violate v1's own no-new-fields guard.

**Correction:** The library columns should be:

- identity: `family:key`;
- label: escaped stored label, fallback to key;
- family: translated family label;
- layout: translated `cards`/`rows` label;
- parts count;
- explicit where-used count plus a separate default/implicit-use indicator.

Remove enable/disable actions. Provide create, edit, and clone from the library;
preserve deletion as a guarded editor header action. Every UI label, helper,
empty state, conflict, and action needs `he` and `en` keys; stored administrator
labels remain user content.

### High — H2: configured templates and virtual defaults are conflated

`PublicFrontCardTemplateResolver` begins with three code defaults and overlays
configured rows. Therefore an empty stored array still resolves three usable
templates.

**Correction:** Make the library contract explicit. Preferred behavior:

- configured rows are the only editable table records;
- show the three missing registry defaults as clearly marked, read-only virtual
  rows with a single **Create override** action;
- editing a virtual default opens create state prefilled from the registry and
  persists only after the page-level save;
- a configured row with a default identity is marked **default override** and
  cannot be renamed or deleted in SP3C.

An alternative configured-only table is safe but less discoverable. The prompt
must choose one rather than leaving the implementation to guess.

### High — H3: where-used computation and invalidation are underspecified

**Correction:** Introduce one request-scoped scanner/service that accepts the
fresh settings snapshot and one projected HomepageSection result set. It returns
a map keyed by `family:key` with explicit setting paths, section IDs/counts, and
the implicit-default flag. Compute once per request before table column
closures. Do not add persistent cache state in SP3C. If the measured single
query is unexpectedly expensive, report it and defer persistent caching with an
explicit invalidation design.

### High — H4: stale detection needs an exact fingerprint contract

**Correction:** Store the original family/key and mount fingerprint in Livewire
`#[Locked]` properties. The fingerprint must use one deterministic JSON
encoding/canonicalization function over the raw target row only. On save:

- 0 original matches: missing/stale conflict;
- more than 1 original match: corrupt/duplicate conflict;
- fingerprint mismatch: stale conflict;
- sibling-only or foreign-root changes: allowed and preserved.

State clearly that this catches sequential stale pages/tabs. It does not add
database, cache, advisory, or application locks and does not claim simultaneous
request serialization.

### High — H5: canary sequencing contradicts the docs-before-code rule

The plan cannot record a measured canary verdict before the canary exists.

**Correction:** Use this sequence:

1. Write research and a provisional implementation plan before code.
2. Build only the isolated/test-owned canary.
3. Run the canary and collect its element/control/state/response metrics.
4. Amend the implementation plan with the measured verdict and frozen chosen
   path before production-page adoption.
5. If neither previews nor the selected-part fallback meets the corrected
   acceptance criteria, stop and report; do not adopt a collapsed full Builder.

The SP3A fixture and lifecycle regression test remain untouched. Add a separate
SP3C deepest fixture.

### High — H6: nested Builder behavior needs a deeper canary

A preview on the outer `part_group` hides its parent schema in the list, but the
edit modal contains the nested children Builder. If nested Builder blocks lack
previews, every nested child form renders when the parent modal opens.

**Correction:** The canary must exercise:

1. top-level preview list with every applicable block type;
2. repeated instances of the same block type with distinct sentinel state;
3. opening one top-level part and proving only its controls appear;
4. opening `part_group`, proving nested children still render summaries;
5. opening one nested child and proving only that child's controls appear;
6. edit/confirm, edit/cancel, validation failure, clone, delete, reorder, nested
   reorder, close/reopen, browser back, and unsaved-draft survival;
7. escaped hostile strings in every summary path;
8. authorization non-exposure and forged-state refusal at both depths.

### High — H7: the budget proxies are not comparable or complete

The 29,404 SP3A number came from an authenticated browser DOM. A Symfony crawler
count over initial server HTML is a different surface. v1 also names no
selected-part ceiling and treats response headers as if they describe Livewire
selected state.

**Correction:** Split the evidence:

- **Initial GET:** uncompressed response bytes, total queries, settings reads,
  lifecycle derivations, duplicate lifecycle loads, and server-HTML element
  count for library and unselected editor.
- **Selected state:** `Livewire::test(...)->html()` after mounting the exact
  selected part/edit action; count elements, Filament field wrappers,
  `wire:model` paths, control IDs, and sentinel leakage. Separately record the
  Livewire response/delta size if measured.
- **Browser:** keep the 29,404 Advanced value only as historical browser
  contrast and require operator-collected TTFB/DOM/listener/heap samples. Do not
  calculate a percentage improvement between unlike surfaces.

Treat `< 2,000` and `< 4,500` as provisional stop/go targets until the canary
measures the common shell. Freeze final caps in the amended plan using the
measured deterministic value plus 20% headroom. Add a selected-part cap and a
same-surface control that renders the current full Builder against the same
SP3C fixture; require at least a 70% reduction in editor field wrappers and
rendered form controls versus that control. Do not derive any absolute proxy
ceiling from the unlike 29,404 browser measurement.

Also require bounded settings reads, zero duplicate lifecycle loads, and
canary-derived query/response ceilings rather than recording headers without
pass/fail criteria.

### High — H8: route and navigation behavior must be decided before coding

**Correction:** Preserve the existing public admin URL and redirect seam:

- library: existing `CardTemplateSettings` at
  `/settings/card-templates`, registered in the Settings navigation group at
  the current SP3B sort/icon position;
- create editor: `/settings/card-templates/create`, hidden from navigation;
- edit editor: `/settings/card-templates/edit/{family}/{key}`, hidden from
  navigation.

The explicit `/edit/` segment avoids collision with `/create`. Mount must
whitelist the three registry families, validate key against
`^[a-z][a-z0-9_-]*$` and the existing 80-character form limit, URL-decode only
once, and 404 on missing/corrupt/duplicate identity. Continue routing the old
Advanced-tab compatibility link through `CardTemplateSettings::getUrl()` so it
lands on the library. Test route generation, direct URLs, back navigation,
unauthorized access, missing identity, invalid family/key, renamed old URL, and
SPA navigation.

### High — H9: clone behavior must be fixed rather than left open

**Correction:** Choose clone-unsaved:

1. Resolve the source from a fresh snapshot when the clone action runs.
2. Copy only its editable projected state.
3. Generate a provisional semantic key in the same family, respecting the
   80-character limit and using deterministic `_copy`, `_copy_2`, ... suffixes.
4. Append a translated copy suffix to the stored label only in editor draft.
5. Open the create editor without saving, backups, events, or cache effects.
6. Recheck identity collision against a fresh snapshot on final save.

This is the only choice consistent with one explicit lifecycle save and easy
cancel behavior.

### High — H10: profiler and measurement integration need explicit APIs

**Correction:** Extend the profiler with a scoped subject context such as
`withSubject('card-template-library', ...)` and
`withSubject('card-template-editor', ...)`, adding `subject` to every record
without changing existing phase names. Retain request kinds `initial load`,
`livewire update`, and `save`.

Both custom pages must implement the existing local-only `sp3a_measure=1`
contract deliberately:

- initial GET uses the committed measurement projection appropriate to that
  page;
- edit measurement selects an explicit fixture identity;
- the mode survives Livewire updates;
- all mutations and settings saves are refused;
- ordinary requests never expose measurement headers/state;
- the existing five decomposed response headers retain their meanings.

### Medium — M1: the preflight history expectation is incomplete

SP3C v1 names four feature commits, but two of those have immediate docs-only
follow-ups. The exact post-SP3B chain includes `9d8296f`, `23a6ce9`, `2ea189f`,
`d128cfd`, `0394ab5`, and `41cf3c5`.

**Correction:** List all six in preflight. In Job 0, inspect the existing state
and ledger before adding rows: Curator and OTP are already recorded. Reconcile
only missing compact status, and do not duplicate entries.

### Medium — M2: lock language should distinguish lifecycle locks from import locks

**Correction:** Render the existing family-level template lifecycle lock state
on library/editor as required by the SP3A lock model. Do not create per-template
or per-part locks. Do not let `SettingsImportLocks` block an ordinary editor
save; those locks remain import-only.

### Medium — M3: a custom-data table needs stable-key and behavior constraints

**Correction:** If the library uses Filament Table, use a stable unique record
key derived from the configured `family:key`; type action records as arrays.
Keep the small list unpaginated unless measurement proves otherwise. If search
or sorting is enabled, implement it inside `records()` using Filament's injected
custom-data arguments; merely marking columns searchable/sortable is not
sufficient. Recompute/reset the table after state-changing actions.

### Medium — M4: registry ownership does not move between page classes

The registry maps the `card-templates` subject to the complete
`card_templates` root; it does not map that root to a specific Filament page
class. The prompt's test language saying that the owner "moves from the
temporary page to the editor" is therefore inaccurate.

**Correction:** Keep the registry definition unchanged. Make the library a
read-only projection whose create/edit/clone actions only route or initialize
an unsaved editor draft. Put every persistence operation, including guarded
template deletion, through the editor's focused writer. Test registry
completeness/uniqueness independently from page schema isolation.

## Corrected SP3C implementation plan

This is the minimum complete plan a v2 execution prompt should mandate.

### Phase 0 — preflight and baseline

1. Require a completely clean tree and verify the exact SP3B and six post-SP3B
   commits above.
2. Read the full lessons/state/ledger/handoffs and all SP3A/SP3B research and
   implementation contracts named in this report.
3. Write `05-sp3c-research.md` and a provisional
   `05-sp3c-implementation-plan.md` before code.
4. Run the targeted green baseline in the prompt's declared order and stop if
   red.

### Phase 1 — deepest canary only

1. Add a separate test-owned deepest fixture; do not edit the SP3A measurement
   fixture or lifecycle regression fixture.
2. Add escaped preview summaries for every applicable Block and both Builder
   levels.
3. Exercise the complete H6 interaction/authorization matrix.
4. Measure the corrected initial/selected-state proxies from H7.
5. Amend the plan with the preview or fallback verdict and frozen budgets.

### Phase 2 — centralized read projections

1. Add a request-scoped library projection for configured rows and virtual
   default rows.
2. Add the centralized reference scanner with one projected HomepageSection
   query and the two settings selectors.
3. Add an authorization-safe editor/summary projection that never hydrates
   protected state for a non-capable user.
4. Keep these readers side-effect-free and uncached beyond the request.

### Phase 3 — template library page

**Page:** existing `App\Filament\Pages\CardTemplateSettings` becomes the
library at the existing slug.

**Table:** `Filament\Tables\Table` with custom array `records()` and stable
identity keys.

**Columns:** `Filament\Tables\Columns\TextColumn` for escaped label, composite
identity, translated family, translated layout, parts count, and where-used
count/status. Do not define enabled/type/updated-at columns.

**Actions:** `Filament\Actions\Action` for edit, clone-unsaved, and create
override; a header `Filament\Actions\Action` for create. These actions route or
initialize a draft and never save settings. Each action must specify icon,
visibility, authorization, behavior steps, and bilingual notification/error
copy. Group row actions if more than three are simultaneously visible.

**Authorization:** authenticated admin-panel users may view the library; page
and every mutating action use the existing settings edit gate. Default and
referenced identities apply the additional guards above.

### Phase 4 — create/edit page and focused writer

1. Add hidden create/edit Filament custom pages at the routes in H8, sharing one
   editor schema and one focused writer.
2. Mount only one authorized projected template or one blank draft.
3. Store original identity and fingerprint as Livewire locked properties.
4. Use the canary-selected parts interaction. A fallback child/modal mutates
   the parent draft only.
5. Implement C1's exact writer and C3's conservative protected-template policy.
6. Add a guarded editor header delete action. It rechecks the fresh target,
   fingerprint, explicit references, default identity, and authorization, then
   removes only that target through the same one-save focused lifecycle.
7. Perform one and only one Spatie `save()`. Preserve the existing lifecycle
   listener unchanged.
8. Redirect after create/allowed rename/delete; stale/collision/validation failures
   remain on the page with the draft intact and zero settings saves.

### Phase 5 — tests

At minimum, add independent coverage for:

- library projection, configured/default-override distinctions, stable keys,
  bilingual labels, and one-query reference scan;
- create, edit, clone-unsaved, guarded delete, allowed unused rename, referenced
  rename refusal, default-identity rename/delete refusal, collision, source
  disappearing, corrupt duplicate identity, invalid family/key, direct URL,
  redirect, back/SPA navigation, and 404 behavior;
- the full nested canary matrix and summary escaping;
- authorized read-state non-exposure plus forged top-level/nested create/edit/
  rename attempts for every role/mode combination;
- one successful save dispatches `SettingsSaved` exactly once; cancel,
  validation failure, collision, and stale conflict dispatch it zero times;
- sequential same-template stale-page conflict;
- sequential sibling-template edits both survive;
- an independent ownership-completeness/schema-isolation test proving the
  registry still owns each of all 37 root properties exactly once and the
  editor never mounts foreign settings roots;
- byte identity, order, and index of all untouched siblings and freshness of a
  foreign root;
- no simultaneous serialization claim or test;
- lifecycle SHA regression unchanged, SP3A fixture byte identity unchanged,
  existing SP3B suites green;
- corrected initial/selected performance proxies, bounded settings reads, and
  zero duplicate lifecycle loads;
- measurement mode hydration and mutation refusal on both pages;
- import locks remain import-only and lifecycle lock units remain family-level.

### Phase 6 — docs, final gate, and commits

1. Reconcile only missing compact state/ledger entries for post-SP3B work.
2. Update the SP3C research/plan after the canary, state, ledger, and handoff.
3. Record every command, failure, rerun, gate, and objective manual front-check
   step in the handoff.
4. Run the standing final gate exactly as specified, with the full suite last
   and once green on the final file state.
5. Commit implementation as
   `feat: add template library and one-template editor`, immediately followed
   by the docs-only `docs: backfill settings sp3c hash` commit.
6. Do not push.

## Required v2 wording changes

A corrected prompt should make these direct substitutions:

1. Replace "ZERO rendered elements" with the C5 form-control/field-wrapper
   contract.
2. Replace "modal with its own authorization and save boundary" with the C4
   parent-draft-only contract.
3. Remove `enabled state`, generic `template type`, and enable/disable actions.
4. Replace "translated label" with "escaped stored label; translated UI family,
   layout, columns, actions, hints, errors, and empty states."
5. Add the configured/default-override policy in H2.
6. Add the centralized reference list and rename/delete policy in C2.
7. Add the original-identity authorization ordering and hydration protection in
   C3.
8. Insert C1's 12-step writer algorithm and prohibit whole-list validation.
9. Replace persistent cache/defer language with H3's request-local scanner.
10. Replace fixed proxy claims with H7's calibration/freeze protocol and add a
    selected-state cap.
11. Add the separate deepest SP3C fixture and preserve the SP3A fixture
    byte-identically.
12. Add H8's exact routes, H9's clone-unsaved decision, and H10's profiler and
    measurement contracts.
13. State explicitly that stale detection is sequential only and that SP3C
    neither implements nor tests simultaneous-request serialization.
14. List all six post-SP3B commits and avoid duplicate Job 0 documentation.
15. Require the sequential stale-page test and the independent ownership-
    completeness/schema-isolation test as separate mandatory tests.

## Final assessment

SP3C is viable without migrations, locks, lifecycle changes, or storage-format
changes. Filament 5.6.7 provides enough custom-data table and Builder-preview
surface to run the experiment. The current v1 prompt nevertheless overstates
what previews can remove, leaves a whole-array persistence hazard, treats the
save overlay as a read authorization filter, and permits identity changes that
can break consumers. Those are execution blockers, not documentation polish.

Issue a v2 prompt incorporating the corrections above before starting SP3C.
