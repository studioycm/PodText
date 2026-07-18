# Step 5B Template Parts Auto-Refresh Implementation Plan

## Control and boundaries

- Audit: `LS-20260718-STEP5B-PARTS-AUTOREFRESH-01`.
- Approved option: `STEP5B-PARTS-AUTOREFRESH-O1`.
- Starting commit: `911f707ae691f0ee49ab4e640b579b1ffd077fa8`.
- One bounded outcome: keep the transient preview current while editing
  template parts and correct the directly reported sticky-preview and Cancel
  action defects.

Excluded: live updates for every root template field; sample eligibility,
ordering, result-cap, or selection changes; new permissions; persistence,
migrations, models, dependencies, settings fields, writers, lifecycle, backup,
cache, import, restore, publication, generalized preview infrastructure,
public-card redesign, unrelated cleanup, and selection of another roadmap item.

## Implementation

1. Configure only the editor's top-level `parts` Builder with
   `live(debounce: 500)` so fields bound directly under its state inherit an
   intentional bounded update frequency. Keep the existing block-preview edit
   modal deferred until the operator accepts its action.
2. Extend `updatedInteractsWithSchemas()` to refresh for `data.parts` and its
   descendants after the existing capability enforcement. Preserve the
   existing family behavior, including clearing the transient sample when the
   family changes.
3. Add a Builder `afterStateUpdated()` callback which calls the existing
   `refreshPreview()` for add, delete, clone, and reorder mutations that do not
   use the descendant field state-path hook.
4. Leave root non-family fields deferred and leave the shared part schema,
   previewer, selector, writer, and restricted boundary unchanged.
5. Override the settings page form actions so Save is followed by a localized
   Cancel link. Remove only Cancel from the header actions; keep Preview and the
   edit page's Delete action in the header.
6. Add `admin.actions.cancel` in English and Hebrew.
7. Move the wide preview aside to `top-[5.5rem]` with
   `max-h-[calc(100vh-5.5rem)]`. Keep the preview's internal sticky header at
   `top-0` inside its own scroll container.

## Regression coverage

1. Extend `CardTemplateEditorPreviewTest` to prove:
   - a direct nested part-state update refreshes rendered preview output while
     preserving transient sample identity;
   - a Builder structural mutation refreshes the preview;
   - the Builder exposes the 500ms live binding while an ordinary root field
     remains explicit-refresh;
   - no settings write occurs during automatic refresh;
   - the bilingual Cancel action is in form actions beside Save and absent
     from header actions;
   - existing restricted state, protected sentinel, selector cap, all three
     families, and sample behavior remain intact.
2. Extend `CardTemplatePreviewBrowserTest` with a controlled Builder-part edit
   through the real deferred edit modal. Accepting the edit must produce one
   Livewire request, changed preview output, a single preview root, no
   JavaScript errors, correct sticky topbar clearance, and the localized
   form-action placement.
3. Run the unchanged previewer and SP3C canary tests to preserve public-safe
   sample semantics and frozen component/query/state boundaries.

## Documentation and completion

Update the Step 5B handoff with requirement classifications, commands/results,
limitations, and numbered manual acceptance steps. Synchronize current state,
the active pending-decision queue if its live-preview wording is stale, and the
ledger without selecting another implementation.

Perform the requirements sweep, then run the final gates sequentially in the
canonical order: Pint test, FilaCheck, frontend build, and the full Pest suite
last. The implementation commit will leave its hash pending in the handoff and
ledger; the immediately following docs-only commit will backfill that full
hash. Do not push or create/switch a branch or worktree.
