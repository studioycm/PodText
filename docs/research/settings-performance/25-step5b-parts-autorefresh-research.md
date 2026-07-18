# Step 5B Template Parts Auto-Refresh Research

## Control

- Laravel Simplifier audit: `LS-20260718-STEP5B-PARTS-AUTOREFRESH-01`.
- Approved option: `STEP5B-PARTS-AUTOREFRESH-O1`.
- Stage 2 baseline: clean `main` at
  `911f707ae691f0ee49ab4e640b579b1ffd077fa8`.
- Preserved Step 5B history:
  `c75d0f2b2d476c58d12c16610ea97ba4088c5e79`,
  `2861c320bbeb1091e57b436623241feea039f64a`,
  `69813dbd4002ed8e7c3e42e640f7d48085e275da`, and
  `911f707ae691f0ee49ab4e640b579b1ffd077fa8`.
- Scope: refresh the transient preview when a template-part setting changes,
  keep the editor preview below the Filament topbar, localize Cancel, and place
  Cancel beside Save.

## Verified behavior and seams

1. `CardTemplateEditorPage::updatedInteractsWithSchemas()` currently refreshes
   only for `data.family`. Ordinary root fields deliberately leave the preview
   stale until Refresh is used.
2. The `parts` Builder is currently deferred. Its descendant inputs therefore
   do not send their changed state until another Livewire request.
3. Installed Filament 5.6.7 state-binding source lets fields bound directly
   below a parent component inherit its `live(debounce: 500)` modifiers when
   they do not declare their own modifier. The existing `source` field remains
   explicitly live for its conditional child schema. Builder block previews
   clone their edit fields into a modal action schema, where those inputs stay
   deferred until the edit is accepted; the authoritative Builder update then
   invokes its state callback.
4. Descendant field requests reach the page's
   `updatedInteractsWithSchemas()` hook with a `data.parts...` state path.
   Builder add, delete, clone, and reorder operations instead mutate the
   Builder state and invoke the Builder's own state-updated callback. Both seams
   are required for complete part-edit coverage without modifying shared part
   schema infrastructure.
5. `refreshPreview()` already renders from transient form state, preserves the
   selected family/sample when valid, does not persist settings, and exits
   through the existing restricted-safe path. No previewer, writer, model,
   permission, or sample-query change is necessary.
6. The wide preview aside uses `top-6`, while the Filament topbar occupies a
   sticky 4rem block. The aside can therefore settle behind the topbar. A
   5.5rem top offset preserves the existing 1.5rem gap below that topbar, and a
   matching viewport-height calculation keeps its scroll box bounded.
7. `admin.actions.cancel` is absent from both English and Hebrew translation
   arrays. The editor defines Cancel as a header action, while the inherited
   settings form renders Save through `getFormActions()` in the form footer.
   Overriding that method is the existing Filament seam for placing both form
   actions together; Delete remains an edit-page header action.

## Installed-version research

Laravel Boost reported PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire
4.3.3, Pest 4.7.4, and Tailwind CSS 4.3.2. Version-aware documentation confirms
that Filament fields are deferred by default, `live(debounce: 500)` waits for a
500ms typing pause before sending state, and custom fields must propagate state
binding modifiers. Filament Builder documentation and installed source confirm
that reorder and item actions are component-state mutations.

FilamentExamples was searched in two query passes. Search/snippet access only
was available; no separate source/detail endpoint was exposed. Relevant
examples used a live Repeater/Builder-style container with
`afterStateUpdated()` for derived output and placed page save actions in the
form. PodText will reuse its own page, preview method, and action conventions
rather than copy an example architecture.

## Performance and security boundaries

- The live boundary applies only to `parts`, with a 500ms debounce for fields
  bound directly under that state. The Builder edit modal remains deferred
  until its Save action. It does not make every root template field live.
- One part edit may cause one preview render request after the debounce. It does
  not add polling, persistence, a second preview root, or a new measurement
  plane.
- The existing 50-option sample-search cap, eligibility, ordering, label
  resolution, and selection refresh remain unchanged.
- Existing restricted-state enforcement remains before protected template
  parts or sample queries. The change is not evidence of prior protected-data
  exposure and must retain the existing protected-sentinel regressions.
- The frozen SP3C component/query/state budgets remain unchanged; the browser
  regression will measure the new interaction separately.

## Focused conclusion

The smallest safe implementation is to make only the top-level `parts`
Builder live with a 500ms debounce, refresh on descendant `data.parts...`
updates, and attach the same refresh to the Builder state callback for
structural actions. The same bounded change moves Cancel to the settings form
actions with bilingual labels and offsets the wide preview below the topbar.
No new class, dependency, migration, persistence, capability, or public-sample
semantic is required.
