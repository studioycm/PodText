# Step 5B card-template editor UX2 implementation plan

Date: 2026-07-19  
Audit: `LS-20260719-STEP5B-CARD-TEMPLATE-UX2-01`  
Approved option: `STEP5B-CARD-UX2-O1-COMPAT-MODAL`

## Objective

Implement only the approved O1 compatibility-preserving editor refinement:
position-canonical focused editing, native scoped modal movement, compact and
collapsible editor UI, a reachable transient label/icon control group, and a
real-width preview control. Preserve all global explicit-order compatibility
and all Step 5B security/performance boundaries.

The research contract is
`docs/research/settings-performance/29-step5b-card-template-editor-ux2-research.md`.

## Approved invariants

- Do not change `PublicFrontConfigValidator` or
  `PublicFrontCardTemplate::fromArray()` ordering behavior.
- Do not change import, restore, backup, lifecycle, registry defaults, models,
  migrations, permissions, renderer, or presenter behavior.
- Do not add production normalization guidance.
- Do not expose or query restricted preview samples.
- Do not create a recursive cross-Builder move endpoint.
- Preserve UUID item keys, the browser-local Builder display-mode preference,
  native slide-over Apply-time behavior, and one refresh after an authoritative
  inline Builder update.
- Do not implement O2, O3, or O4.

## Implementation sequence

### 1. Lock the baseline

1. Confirm the worktree contains only the two required research/plan documents.
2. Run the focused SP3 baseline required by the audited UX2 work.
3. Stop before application edits if the baseline reveals an unrelated failure.

### 2. Make focused-editor ordering position canonical

1. Add one recursive Builder-hydration helper in
   `BuildsPublicContentSettingsSubjectSchemas`:
   - sort siblings by effective legacy `order`;
   - keep original position as the stable duplicate-order tie-breaker and
     missing-order fallback;
   - remove `order` from Builder-facing part data;
   - recurse into `part_group.children`;
   - add transient label/icon visibility state derived from the existing
     position fields.
2. Remove `TextInput::make('order')` from the shared card-part schema so the
   focused, nested, and Advanced consumers no longer expose it.
3. Change `CardTemplateDraftNormalizer::candidateParts()` only:
   - strip raw/forged `order` and both transient helper keys;
   - retain existing null-filter semantics for all other fields;
   - assign `10, 20, 30, ...` after invalid non-array parts are discarded;
   - recurse and renumber each child sibling list independently.
4. Keep the global validator/value object unchanged and prove compatibility in
   tests.

### 3. Add native scoped modal movement and item chrome

1. Define a compact `Action` for each relevant Builder through the shared trait:
   - one required integer position input;
   - current position as the default;
   - clamp to `1..N` server-side;
   - remove and insert at the final requested slot;
   - preserve UUID keys;
   - mutate only `$component->getRawState()` for the owning Builder;
   - call the owning Builder's after-state-updated hook exactly once.
2. Attach the action with `extraItemActions()` to the focused top-level Builder
   and nested child Builders. Preserve the existing Advanced consumer when it
   uses the same shared Builder configuration.
3. Use an escaped Blade block-label view for
   `position -> separator -> translated part type` and explicitly disable native
   trailing block numbers.
4. Enable native item-header collapsibility only where parts are edited inline;
   keep items open by default and keep slide-over summary/edit behavior intact.
5. Add escaped, aria-hidden separators to the existing part-summary/footer view.

### 4. Refine sections and label/icon form UX

1. Split `CardTemplateEditorPage::form()` into minimal, open-by-default,
   collapsible Template settings and Template parts sections.
2. Preserve the stable editor data hook, restricted-mode content, form actions,
   and visual display-mode control.
3. Wrap label and icon controls in a compact translated Fieldset.
4. Keep Show label and Show icon toggles always visible and live.
5. Hide only subordinate label/icon fields when their toggle is off.
6. Map toggle state to the existing `hidden`/`inline_before` position tokens,
   preserve entered label/icon values, and keep helper keys non-dehydrated.
7. Correct touched helper copy and add complete English/Hebrew translation keys.

### 5. Refine the preview panel without changing rendering

1. Keep the sample selector's label accessible but visually hidden.
2. Put sample, width, and icon-only refresh controls in one compact responsive
   row.
3. Replace zoom with transient `100/90/80/70/60` width choices.
4. Center the ready preview plane and apply percentage width only; use no zoom or
   transform.
5. Render short `d/m H:i` Jerusalem time in an LTR isolation boundary while
   retaining an accessible full refresh status/title.
6. Preserve the existing loading, unavailable, invalid, and ready status planes,
   focus-return behavior, and refresh request boundary.

### 6. Add targeted regression coverage

Add or update Pest coverage for:

- stable legacy-order hydration and recursive order stripping;
- focused normalization to contiguous x10 orders after raw, blank, duplicate,
  missing, and forged order values;
- no old order field in top-level/nested/Advanced schemas;
- native block-number disabling and escaped index/type labels;
- modal moves forward/backward, exact position, clamping, no-op, UUID stability,
  nested owning-Builder isolation, and one preview refresh;
- no writer/lifecycle/settings-save side effect;
- always-reachable live toggles, subordinate visibility, retained values, and no
  helper-key persistence;
- compact sample/width/refresh hooks, hidden sample label, short timestamp, and
  absence of zoom/transform hooks;
- restricted-state query/action/state protections;
- unchanged global validator/import/restore explicit-order compatibility;
- recalculated SP3 measurements with each changed exact value explained.

Use the installed Livewire/Filament/Pest APIs and existing test factories. Do not
probe the local development database or use live network/mail.

### 7. Browser and manual evidence

Run the existing preview browser suite and verify, at minimum:

- English LTR and Hebrew RTL;
- light and dark modes;
- wide aside and supported narrow modal/slide-over layouts;
- item header collapse versus move-action click behavior;
- modal movement and badge renumbering;
- compact one-row controls and icon-only refresh accessibility;
- width 60% centered with approximately equal margins;
- computed font/image presentation unchanged across width changes;
- no preview overflow at the supported minimum width;
- label/icon toggle visibility behavior in inline and slide-over modes.

If the browser runner hits the documented macOS Chromium rendezvous permission
failure, rerun the same suite with the required permission and record it as an
infrastructure deviation rather than changing application code.

## Verification and canonical closeout

On the final code and documentation state, run sequentially:

1. Requirements sweep against O1 acceptance and exclusions.
2. `vendor/bin/pint --test`
3. `vendor/bin/filacheck`
4. `npm run build`
5. Full `php artisan test` last, without interruption or parallelization.

After any file edit before the implementation commit, restart at Pint. Never run
`vendor/bin/filacheck --fix`.

Before the gates, create the dedicated UX2 handoff and update:

- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/settings-step5b-card-template-preview-handoff.md`

The UX2 handoff must classify every requirement, list files/tests/commands and
results, record gate outcomes, identify the native scoped modal as the shipped
move mechanism, include numbered imperative Local Front Check steps, state that
no production normalization was run or prescribed, and keep `## Commit hash`
pending for the implementation commit.

Then:

1. Commit implementation, tests, research, plan, state docs, ledger, and handoff.
2. Immediately stamp the implementation hash into the handoff and ledger in a
   docs-only commit using `docs: backfill Step5B UX2 hash`.
3. Leave the worktree clean and do not push.

## Stop conditions

Return to a new Stage 1 audit and stop before expanding work if any of these is
required:

- changing the global validator/value-object explicit-order contract;
- a migration, dependency, model, permission, lifecycle, import/restore/backup,
  renderer, presenter, or public-card change;
- a cross-Builder recursive public move endpoint or inline header input;
- a higher component/query/response/state budget not explainable by the approved
  Fieldset, two transient switches, move action, and compact preview controls;
- weakening restricted-sample protections;
- production normalization or any production action;
- more than the approved O1 forecast or work on O2, O3, O4, another roadmap step,
  branch, worktree, or push.

