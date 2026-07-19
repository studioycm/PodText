# Step 5B card-template strict image-order follow-up implementation plan

Date: 2026-07-19
Audit: `LS-20260719-STEP5B-CARD-UX2-FOLLOWUP-01`
Approved option: `STEP5B-CARD-UX2-FU01-STRICT-IMAGE-ORDER`

## Objective

Implement FU01 only: make the configured top-level part sequence authoritative
for content-item and content-group images. Preserve the existing leading-image
row/card path, and use one ordered stacked flow when an image is interleaved.

The research contract is
`docs/research/settings-performance/31-step5b-card-template-image-order-followup-research.md`.
Documents 29/30 remain the completed historical UX2 O1 record and are not
rewritten.

## Approved invariants

- Reuse the presenter's already sorted `parts` array.
- Preserve the current leading-image media/body rendering path and geometry.
- Force effective stacked/card geometry only for an interleaved top-level image.
- Keep configured layout in compatibility attributes and expose effective
  layout honestly through the existing result-layout attribute.
- Preserve preview/public parity through shared card components.
- Preserve image URL/fallback, source, fit, radius, link, accessibility, label,
  icon, and diagnostics behavior.
- Keep contributor cards unchanged.
- Do not change the validator, value object order contract, draft normalizer,
  focused writer, import, restore, backup, settings lifecycle, native Builder
  move action, schema, models, dependencies, permissions, or production state.
- Do not implement FU02, FU03/O4, nested-image enablement, O2, or O3.

## Implementation sequence

### 1. Lock and document the baseline

1. Confirm cwd and Git root are `/Users/studioycm/Herd/PodText`.
2. Confirm the only new files are research 31 and plan 32 before application
   edits.
3. Re-read both documents and stop if the scoped renderer/view baseline has
   materially changed from `4999b960188ebc1b563f135c8ce07d745a969242`.
4. Keep the complete FU02/FU03 and omitted-finding inventory in research 31 and
   later handoff; do not turn it into code during FU01.

### 2. Add one explicit presentation-flow decision

1. In `PublicFrontCardTemplateRenderer`, inspect each family's already filtered
   and sorted top-level configured parts.
2. Mark ordered stacked flow when an image occurs after index zero. This also
   covers a later second image.
3. For an interleaved image, use the existing card-layout article/image classes
   and set effective layout to `cards`.
4. For a leading, hidden, or absent image, return existing presentation values.
5. Add the flow flag to the documented presentation array shapes; create no new
   service or extension point.

### 3. Reuse image markup in both flow branches

1. Extract the existing content-item image markup into a small explicit Blade
   component with declared props.
2. Extract the existing content-group image markup the same way.
3. Preserve every current conditional link, preview accessibility attribute,
   fallback, image fit/radius class, test hook, source marker, and part-order
   diagnostic.
4. Add an `image` case to each existing family part component so an interleaved
   image can render at its exact slot.
5. Do not alter nested `part_group` child filtering.

### 4. Select leading versus ordered rendering in the card views

1. Add one stable diagnostic attribute identifying `media-leading` versus
   `ordered-stack` flow.
2. In ordered-stack flow, render the existing `parts` array exactly once inside
   the existing density-aware body stack.
3. Otherwise retain the current media loop followed by the body loop.
4. Do not duplicate or query presentation data in Blade.

### 5. Add focused regressions

Update existing Pest coverage to prove:

- content-item leading image + rows retains `rows` effective layout and the
  media-leading flow;
- content-item custom text → image → title renders in that order and uses
  ordered stacked flow;
- content-group custom text → image → title renders in that order and uses
  ordered stacked flow;
- configured rows compatibility metadata remains `rows` when effective layout
  becomes `cards`;
- the native owning-Builder move action can move an item image between parts
  and the in-memory preview immediately reflects that exact order;
- preview links remain inert and image/fallback diagnostics remain present;
- existing contributor ordering coverage remains green.

Use the existing factories and isolated test database. Do not use live network,
mail, or the local development database.

### 6. Perform the bounded simplification pass

1. Confirm there is a single flow decision in PHP and no independent Blade
   inference.
2. Confirm image markup is not duplicated between leading and ordered paths.
3. Confirm no global order, sample-selection, validation-targeting, nested-image,
   or production code entered the diff.
4. Compare actual files/classes/dependencies/tasks with the FU01 forecast and
   record any deviation in the handoff.

### 7. Create the FU01 handoff and synchronize active state

Create
`docs/phase-02/settings-step5b-card-template-image-order-followup-handoff.md`
and update:

- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/settings-step5b-card-template-preview-handoff.md`

The handoff must include:

- requirement classification for every FU01 acceptance item and exclusion;
- the July 7 history/root-cause finding;
- actual files and tests changed;
- every command and result, including failures/reruns;
- explicit FU02, FU03/O4, nested-image, and remaining-correction deferrals;
- the statement that O4 is not a GitHub issue;
- no production normalization run or prescription;
- numbered imperative Local Front Check steps;
- `## Commit hash` pending for the implementation commit.

## Targeted verification before final gates

Run focused tests sequentially while iterating, including the exact modified
feature/Livewire test files. Run the card-preview browser test when the final
test design includes browser evidence. If Chromium hits the documented macOS
sandbox rendezvous failure, rerun the same suite with the needed permission and
record the infrastructure deviation; do not change application code to work
around it.

No targeted test substitutes for the full suite in the canonical final gate.

## Canonical final gate and closeout

On the final code and documentation state, run sequentially:

1. requirements sweep against research 31, this plan, the approved FU01 option,
   and all exclusions;
2. `vendor/bin/pint --test`;
3. `vendor/bin/filacheck`;
4. `npm run build`;
5. full `php artisan test` last, without interruption or parallelization.

After any file edit, re-enter the ordered gate at Pint. Never run
`vendor/bin/filacheck --fix`.

Then:

1. commit application code, tests, research 31, plan 32, handoff, state, ledger,
   and preview-handoff updates in one implementation commit;
2. immediately stamp that implementation hash into the FU01 handoff and ledger;
3. commit the stamp only with `docs: backfill Step5B FU01 hash`;
4. leave the worktree clean and do not push.

## Stop conditions

Stop and return to a new Stage 1 audit if FU01 requires:

- a validator/value-object/import/restore/backup order-contract change;
- sample ranking/query changes or path-aware validation changes;
- nested image enablement or another new public rendering rule;
- a migration, dependency, model, permission, lifecycle, or settings-write
  change;
- more than the approved bounded renderer/view/test outcome;
- production normalization or any other production action;
- another roadmap selection, branch/worktree, push, or remote publication.

After FU01 closure, run the requested fresh Simplifier audit for the complete
post-FU01 inventory in research 31 before creating FU02/FU03 implementation
plans or code.
