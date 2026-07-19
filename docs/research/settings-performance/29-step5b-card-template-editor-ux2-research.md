# Step 5B card-template editor UX2 research

Date: 2026-07-19  
Audit: `LS-20260719-STEP5B-CARD-TEMPLATE-UX2-01`  
Approved option: `STEP5B-CARD-UX2-O1-COMPAT-MODAL`

## Scope and approval boundary

This note records the read-only research used for the approved O1 implementation.
It does not reopen the audit or authorize O2, O3, O4, a production data rewrite,
another roadmap selection, dependency work, a migration, or a generalized Builder
abstraction.

O1 keeps position canonical inside the focused editor while preserving the
existing explicit-order behavior used by global validation, import, restore,
backup, and public value-object consumers. The editor may read legacy explicit
orders to establish its initial sibling order, but it must not change the global
compatibility contract.

## Verified baseline

- Checkout: `/Users/studioycm/Herd/PodText`
- Git root: `/Users/studioycm/Herd/PodText`
- Branch state at preflight: `main...origin/main [ahead 42]`
- Worktree at preflight: clean
- Audited HEAD: `8b3b5b06cedea984ffd277fbf29d8c3f3268e3da`
- The authoritative Herd checkout had not changed before this task started.
- Installed versions used as the source of truth include Laravel 13.19.0,
  Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, and Tailwind CSS 4.3.2.

## Current behavior and compatibility findings

### Ordering has two different compatibility planes

The focused editor currently exposes an `order` input inside every card part.
The shared validator preserves explicit values and sorts normalized sibling
lists by `order`; `PublicFrontCardTemplate::fromArray()` also defensively sorts.
Import, restore, backup, and other global settings paths rely on that behavior.
Changing those shared consumers to ignore explicit `order` would be an O3
cutover and could change public rendering before an editor save.

The safe O1 boundary is therefore:

1. On focused-editor hydration, sort every sibling list by its effective legacy
   order, with original array position as the stable tie-breaker and fallback.
2. Remove `order` from the Builder-facing state and from every visible part
   schema, including nested children and Advanced consumers of the shared
   schema.
3. Treat Builder array position as canonical while editing.
4. Before preview or focused save validation, synthesize `10, 20, 30, ...`
   independently for every sibling list.
5. Leave `PublicFrontConfigValidator`, `PublicFrontCardTemplate`, import,
   restore, backup, registry defaults, and settings lifecycle code unchanged.

This preserves existing public behavior until a template is deliberately saved
through the focused editor. It also makes raw `order => ''`, duplicate orders,
and missing orders harmless in the editor without changing unrelated settings
paths.

### Native scoped Builder actions avoid unsafe global key searches

Filament 5.6.7 Builder source confirms that `extraItemActions()` mounts an action
for one item in the owning Builder. The action receives the item key in
`$arguments['item']` and the owning `Builder` component. `getRawState()` and
`rawState()` operate on that exact sibling list, and `callAfterStateUpdated()`
uses the existing editor refresh hook.

This is safer than a public recursive Livewire method that searches all
Builders for a UUID. The scoped modal can implement take-the-slot semantics by
removing the selected UUID-keyed item and inserting it at the clamped target
position while preserving all UUID keys. It also uses Filament's native modal,
focus, Enter-submit, and Escape-cancel behavior.

The approved O1 mechanism is therefore the compact native modal, not the O2
inline header input.

### Native collapsibility already protects interactive header actions

The installed Builder view attaches click-to-toggle behavior to a collapsible
item header. Its action groups stop click propagation, so opening the scoped
move action does not collapse the item. `Block::label()` accepts an `Htmlable`
result and injects the live item index; an escaped Blade view can render:

`position badge -> aria-hidden separator -> translated part type`

`blockNumbers(false)` is required so Filament does not add a second sequence
number after the custom label. Inline-mode Builder items can use native
collapsibility and remain open by default. Slide-over summary mode keeps its
existing edit action and Apply-time state boundary.

### Transient label and icon switches can map to existing persisted fields

No new persisted booleans are required. Two transient Builder keys can control
the existing fields:

- disabling label sets `label_position` to `hidden`;
- enabling label restores `label_position` to `inline_before` when hidden;
- disabling icon sets `icon_position` to `hidden`;
- enabling icon restores `icon_position` to `inline_before` when hidden.

The label text and icon token remain in state while disabled, so re-enabling
does not discard user input. The two switches stay visible inside a small
"Label and icon" Fieldset; only their subordinate fields are conditionally
hidden. The transient keys are non-dehydrated and are also stripped defensively
by `CardTemplateDraftNormalizer` before preview/save validation.

The driving toggles must be live so subordinate visibility updates immediately.
In inline mode, the existing Builder debounce/refresh hook refreshes preview
state. In slide-over mode, fields remain cloned action state and the preview
continues to update only after Apply; no custom synchronization bridge is in O1.

### Preview width must produce real reflow

The current preview zoom changes the scale of text and images. O1 replaces it
with transient width choices `100, 90, 80, 70, 60` applied only as a percentage
width on a centered preview plane. No CSS `zoom` or `transform` is used. Font,
image, title, and other presentation tokens therefore remain controlled by the
template/part/page settings while the card genuinely reflows at narrower
widths.

The sample selector, width selector, and icon-only refresh action can share one
compact row. The refreshed state can remain server-owned while the visible time
uses short Jerusalem `d/m H:i` presentation, an LTR isolation boundary, and an
accessible full status/title.

### Security and performance boundaries remain unchanged

- Restricted mode must not construct, render, query, search, resolve, or accept
  forged sample-selector interactions.
- Preview interactions must not invoke the settings writer, settings lifecycle,
  backup, reference scan, cache invalidation, or production data mutation.
- Moving one item must trigger the existing preview refresh once and must not
  save settings.
- The 10-option preload, 50-result search cap, own-image preference, three
  sample families, and no-query in-memory preview renderer remain unchanged.
- SP3 component, query, response, serialized-state, and browser canaries must be
  remeasured after the Fieldset, transient toggle state, move action, and compact
  controls are present. Measurements must be described only in their measured
  plane.

## Installed-version documentation and source evidence

Laravel Boost returned installed-version guidance for:

- Builder `extraItemActions()` and item-scoped action arguments;
- action modal schemas;
- Builder collapsibility and reorder behavior;
- conditional field visibility with `Get`;
- live driving toggles.

Installed Filament source was then checked for the exact 5.6.7 behavior:

- Builder built-in move/reorder actions mutate the owning Builder raw state and
  call its after-state-updated hook;
- extra item actions are mounted with the owning item key;
- action clicks stop header-collapse propagation;
- block labels may return escaped `Htmlable` views and receive the live index;
- Builder state is UUID-keyed and can be reordered without regenerating keys.

## FilamentExamples research

Two search passes were completed before application code changes.

First-pass topics:

- Builder extra item action modal and move-to-position;
- reorder buttons and scoped item actions;
- custom Builder labels and position badges;
- Fieldset live toggles with conditional fields;
- collapsible inline Builder items and slide-over editing.

Second-pass refined topics:

- `extraItemActions`, `getRawState`, `rawState`, and a modal `TextInput`;
- live, non-dehydrated toggles with `Get`/`Set` visibility;
- `Htmlable` Builder block labels with collapsibility.

The server exposed search names, paths, and snippets but no full source/detail
reader. Results supported neighboring modal-action and reactive-form patterns,
but no example matched nested scoped Builder movement closely enough to copy.
The installed Filament source is therefore the decisive implementation evidence.

## UX and form-audit decisions

- Split the editor into open-by-default collapsible "Template settings" and
  "Template parts" sections with minimal headings and no descriptions.
- Preserve the existing restricted-mode copy and the stable
  `data-sp3c-template-editor` hook.
- Use the native scoped move modal for top-level and nested Builders.
- Use compact native components, translated labels, logical-direction spacing,
  and both English and Hebrew keys.
- Keep toggles reachable when both label and icon are disabled.
- Do not use inline JavaScript/HTML in PHP, override vendor Builder views, add a
  FormRequest, introduce a service abstraction, or add a database field.
- Correct the touched label helper copy so it describes current renderer
  behavior rather than a future renderer.

## O1 acceptance evidence to collect

1. Legacy explicit orders hydrate in the same effective order as the current
   public renderer, including stable duplicates and missing-order fallback.
2. No part schema exposes the old `order` input.
3. Dragging and modal movement preserve UUID keys and produce identical
   position-canonical normalized output.
4. Modal moves work in both directions, clamp out-of-range values, and remain
   inside the owning nested Builder.
5. Preview refreshes once after a move and no settings save occurs.
6. Focused preview/save normalization writes `10, 20, 30, ...` per sibling list
   and strips legacy/forged order plus transient helper keys.
7. Inline item headers collapse natively; action clicks do not toggle collapse.
8. Label/icon switches are always reachable, hide only subordinate fields, keep
   entered values, and do not persist helper keys.
9. Width steps center the card and change only its width, without zoom or
   transform and without changing configured font/image sizes.
10. The compact controls/status work at the supported minimum width in English
    and Hebrew, with short bidi-safe Jerusalem time and accessible full status.
11. Restricted state retains no sample action, query, protected data, or
    serialized-state exposure.
12. Import and restore continue honoring explicit order until the focused editor
    deliberately saves a template.

## Explicit exclusions

- O2 inline header input.
- O3 global explicit-order cutover or compatibility change.
- O4 path-aware generic invalid-field navigation.
- Production normalization commands or deploy instructions prescribing them.
- Migration, dependency, model, authorization, renderer, presenter, public-card,
  import, restore, backup, or lifecycle changes.
- Branch/worktree creation, push, production action, or next-roadmap selection.

