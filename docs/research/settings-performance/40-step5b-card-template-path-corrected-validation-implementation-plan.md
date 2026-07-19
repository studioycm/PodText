# Step 5B Card Template path-corrected validation implementation plan

Date: 2026-07-19
Audit: `LS-20260719-STEP5B-CARD-UX2-FU03-PATH-CORRECTED-01`
Approved option: `STEP5B-CARD-UX2-FU03-PATH-CORRECTED-CLOSURE`
Research basis: `docs/research/settings-performance/39-step5b-card-template-path-corrected-validation-research.md`

## Objective

Preserve structured validator issues and route the first actionable invalid
Card Template field through current UUID-owned inline or native slide-over
Builder state, with exact focus and deterministic safe fallback, without
changing persistence, public rendering, ordering compatibility, restricted
behavior, navigation, or later Step 5B options.

## Implementation sequence

1. **Preserve issue provenance**
   - Extend `CardTemplateWriteException` with a separately typed structured
     issue collection while retaining existing string details.
   - Pass `PublicFrontConfigResult::invalidConfig()` objects through
     `CardTemplateDraftNormalizer`.
   - Format human-readable notification detail directly from structured path
     and reason where needed; never parse it and never expose `valuePreview`.

2. **Resolve positional paths against current UUID state**
   - Accept only `card_templates.0` root, top-item, and one-level child shapes.
   - Reproduce the normalizer's array filtering and current-order projection.
   - Validate positions, UUID ownership, part type, owning Builder, component,
     and allowlisted real field.
   - Return exact inline state path, owner chain, subordinate reveal token, and
     nearest verified Builder fallback without using key/slug/content identity.

3. **Route save and preview failures**
   - Keep capability/restricted refusal ahead of mapping.
   - Make save failure navigation use the structured exception already in the
     request.
   - Replace the preview's generic client-side first-control focus with one
     authorized Livewire action that recomputes the current invalid issue
     server-side and accepts no client path or UUID.
   - Preserve generic handling for measurement, protected, stale, duplicate,
     referenced, and other non-validation failures.

4. **Open native owners before placing errors**
   - Inline: add the exact UUID state-path error and dispatch one installed
     `form-validation-error` event.
   - Slide-over: mount native Builder action `edit` with the verified UUID and
     absolute component key; for nested fields mount parent then child.
   - Mount the complete action chain before `addError()` because installed
     Filament resets the error bag during mounting.
   - Target the final mounted schema at
     `mountedActions.<index>.data.<field>`.
   - If owner/action/field resolution fails, do not synthesize a modal; use the
     nearest verified Builder fallback.

5. **Reveal and focus only the verified target**
   - Reveal only transient `_show_label` or `_show_icon` state where the real
     field depends on it, without changing persistent values or modal cancel
     semantics.
   - Add narrow page-scoped render-delayed glue that locates only the
     server-verified target wrapper and dispatches installed `focus-input`.
   - Retain native modal Escape/cancel and opener-focus restoration.
   - Avoid a generalized listener, global selector, custom modal, or duplicate
     persistent target state.

6. **Focused component coverage**
   - Prove structured issue transport and exclusion of `valuePreview`.
   - Prove root, top-level, nested, reordered, collapsed, subordinate reveal,
     and exact action state paths.
   - Prove malformed, stale, unsupported, out-of-range, and unmountable
     fallback with no `data.key` mis-target.
   - Prove restricted/forged calls mount no action, expose no parts, make no
     sample/domain query, and mutate no settings.
   - Prove repeated navigation/cancel/retry leaves no stale action or error.

7. **Authenticated browser coverage**
   - Extend the owned Card Template browser fixture with deliberately invalid
     top-level and nested values and stable markers.
   - Verify inline and slide-over modes, reordered and collapsed rows, native
     parent/child action mounting, visible error placement, focused element,
     Escape/cancel restoration, deterministic fallback, restricted surface,
     locale/direction, viewport, UUID/path evidence, request plane, and no
     persistence.
   - Keep one browser owner. Retry an identical Chromium test with the
     permitted runner if macOS bootstrap/rendezvous permissions fail.

8. **Independent review and simplification**
   - Run focused tests sequentially.
   - Obtain independent read-only architecture/simplification and
     test/performance/security reviews after implementation.
   - Resolve or classify every finding, then perform one touched-code
     simplification pass without adjacent cleanup.

9. **Documentation and canonical closeout**
   - Complete the FU03 handoff with requirement classifications, files, tests,
     every command/result, browser evidence, reviews, limits, deferred
     inventory, and imperative numbered Local Front Check steps.
   - Update current project state, the mini-step ledger, and cumulative Card
     Template preview handoff. Leave FU04 unapproved and next.
   - Run requirements sweep, Pint, FilaCheck, Vite build, then the full serial
     suite last. Restart at Pint after any later file change.
   - Commit application/tests/docs with the implementation hash pending,
     immediately stamp that hash into the handoff and ledger, then make the
     docs-only commit `docs: backfill Step5B FU03 path-corrected hash`.
   - End clean on `main`; do not push.

## Expected change surface

- Application PHP:
  `app/Support/Settings/CardTemplates/CardTemplateWriteException.php`,
  `app/Support/Settings/CardTemplates/CardTemplateDraftNormalizer.php`, and
  `app/Filament/Pages/CardTemplateEditorPage.php`.
- Blade only for the existing preview invalid-focus interaction and narrow
  page-scoped target-focus glue:
  `resources/views/filament/pages/card-template-preview.blade.php` and, only if
  required, `resources/views/filament/pages/card-template-editor.blade.php`.
- Focused tests:
  `tests/Feature/SettingsSp3cTest.php`,
  `tests/Feature/CardTemplateEditorPreviewTest.php`, and
  `tests/Browser/CardTemplatePreviewBrowserTest.php`.
- New docs: research 39, plan 40, and
  `docs/phase-02/settings-step5b-card-template-path-corrected-validation-handoff.md`.
- Required synchronization: current state, mini-step ledger, and cumulative
  Card Template preview handoff.
- No expected new class, CSS, translation, navigation, navigation-test, model,
  migration, dependency, permission, configuration, production, or settings
  writer change.

## Verification budgets

- Zero database queries for mapping, action routing, reveal, and focus.
- No persistent structured issue/draft duplication.
- At most one Livewire request per navigation activation.
- No new settings lifecycle side effect.
- Preserve O1's single preview root and existing component/state canary caps.
- Keep browser DOM/request evidence separate from fixture-backed query and
  component-state evidence.

## Stop and drift conditions

Stop for an amended Stage 1 audit if the baseline changes unexpectedly, the
path contract needs arbitrary recursion, nested action mounting cannot stay in
one Livewire request, a generalized JS/validator subsystem or new persistent
state is required, navigation or translations need broader work, or any
migration, dependency, permission, lifecycle, production, FU04–FU06, renderer,
media, persistence, branch, push, PR, second task, or material forecast increase
is required.
