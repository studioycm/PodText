# Step 5B Restricted Preview Selector Closure Implementation Plan

## Control and boundaries

- Audit: `LS-20260717-STEP5B-CLOSURE-01`.
- Approved option: `STEP5B-CLOSURE-O1`.
- Starting commit: `2861c320bbeb1091e57b436623241feea039f64a`.
- One bounded closure: hide and server-block the sample selector in a
  restricted preview shell, prove that block, and synchronize the Step 5B
  record.

Excluded: new roles or permissions; public-sample query/order/eligibility
changes; persistence, migrations, dependencies, settings ownership, lifecycle,
cache/import/restore/publication changes; generalized preview infrastructure;
and unrelated roadmap work.

## Implementation

1. In `CardTemplateEditorPage`, add one private predicate for whether a sample
   can be chosen in the current preview state. It will reuse the existing
   restricted/protected/current-capability state and valid-family registry.
2. Apply that predicate to the sample action's `visible()` and `disabled()`
   configuration. Disabled state is the server-side Filament mount boundary.
3. Apply the same predicate before calling `sampleOptions()`, `sampleLabel()`,
   or `refreshPreview()` from the action. A restricted callback returns an
   empty result or `null` before resolving the previewer; a restricted
   submission returns without a refresh.
4. In the preview Blade shell, render Choose sample only for a valid,
   non-restricted preview status. Refresh remains available and continues to
   use the existing restricted-safe refresh branch.
5. Extend `CardTemplateEditorPreviewTest`:
   - establish a protected restricted shell with public fixtures for all three
     sample families;
   - assert the selector is absent/hidden and disabled;
   - forge `mountAction('choosePreviewSample')` and verify it unmounts without
     a sample schema or sample-table query;
   - preserve the protected-sentinel HTML and serialized-state assertions;
   - mount the authorized selector for all three families and exercise its real
     search and option-label callbacks, selection, and resulting refresh;
   - retain the selector's 50-result result bound.
6. Keep `CardTemplatePreviewerTest`, the browser test, and the SP3C canary as
   preservation coverage; change them only if the focused test exposes a real
   regression.
7. Update the Step 5B handoff wording and manual check; synchronize the stale
   pending-decision queue; update the ledger/current state only with the repair
   outcome and hash while preserving that no next implementation is selected.

## Focused verification

Run the affected Pest file sequentially during iteration, then the existing
previewer, SP3C canary, and browser files as appropriate. Before the final
gate, perform the requirements sweep. The required final order is Pint test,
FilaCheck, frontend build, then the full Pest suite last. Record every command
and result in the handoff. Any file change after a gate restarts that order.

## Completion record

The implementation commit will contain code, tests, this research/plan,
documentation synchronization, ledger update, and a pending implementation
hash in the handoff. It will be followed immediately by a docs-only hash
backfill commit. No push or branch/worktree action is part of this plan.
