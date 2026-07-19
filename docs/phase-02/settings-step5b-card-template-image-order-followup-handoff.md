# Step 5B Card Template strict image-order follow-up handoff

## Contract and provenance

- Laravel Simplifier audit:
  `LS-20260719-STEP5B-CARD-UX2-FOLLOWUP-01`.
- Approved option: `STEP5B-CARD-UX2-FU01-STRICT-IMAGE-ORDER`.
- Clean implementation baseline:
  `4999b960188ebc1b563f135c8ce07d745a969242`.
- Authoritative checkout and Git root: `/Users/studioycm/Herd/PodText`.
- Branch remained `main`; preflight was clean at
  `main...origin/main [ahead 44]` and the Herd checkout had not changed.
- Required new research and consulted plan:
  `docs/research/settings-performance/31-step5b-card-template-image-order-followup-research.md`
  and
  `docs/research/settings-performance/32-step5b-card-template-image-order-followup-implementation-plan.md`.
- Completed historical documents 29/30 were not rewritten.
- No branch, worktree, push, remote publication, production action, production
  probe, or production normalization occurred or was prescribed.

## Outcome

Content-item and content-group cards now treat their configured top-level part
sequence as authoritative when an image is interleaved with body parts. The
renderer detects that configuration once in PHP, reports an explicit ordered
stack flag, and uses effective card/stacked presentation. The shared public and
preview Blade path then renders the presenter's already sorted `parts` array
exactly once, so an image moved to position 3, 9, or 10 stays in that position.

A single leading image retains the established media/body path and existing row
or card geometry. Configured `rows` remains in compatibility metadata while an
interleaved image reports effective `cards`, accurately reflecting the stacked
layout. Hidden/absent images and contributor cards retain their prior behavior.

The existing content-item and content-group image markup was extracted into
small family-specific Blade components and reused by both rendering branches.
Preview link inertness, fallback/source diagnostics, image fit/radius, loading,
and accessibility attributes remain unchanged.

## Historical finding

The media-first behavior did not begin in the last one or two editor releases.
Before dynamic parts, the July 4 card rendered a hard-coded image before the
body. Commit `e3c81dec2420a23f5b5078245099da8c41395d6c` introduced the
content-item `parts`/`media_parts`/`body_parts` split and media-first loops on
2026-07-07. Commit `f7127914` introduced the group equivalent later that day.
Current blame still points to those commits. The Builder row could visibly move
in recent versions while the item/group output continued to hoist media; the
contributor family already used one ordered body stream.

## Requirement classification

| Requirement | Classification | Evidence |
| --- | --- | --- |
| Create and consult new follow-up research 31 and plan 32 before code | Implemented | Both files were created, read back in full, diff-checked, and used as the implementation contract before application edits. |
| Treat configured item image position as authoritative | Implemented | Public and Livewire tests prove custom text → image → title order; a native Builder move to position 2 changes preview output to that exact sequence. |
| Treat configured group image position as authoritative | Implemented | The podcast-index regression proves custom text → image → title order for the group family. |
| Preserve leading-image row geometry | Implemented | Renderer dataset coverage proves both item and group leading-image rows keep effective `rows`; their existing media/body branch remains in place. |
| Use ordered stacked flow for an interleaved image | Implemented | Interleaved item/group rows retain configured `rows` metadata, report effective `cards`, expose `ordered-stack`, and render `parts` once. |
| Use the native scoped Builder move action | Already existed and verified | No editor action changed. Livewire and Chromium use the owning-Builder modal; the browser moved the image to position 10 and observed badge 10 plus preview order. |
| Preserve preview and public parity | Implemented | Both paths use the same renderer and public card components; public item/group, Livewire preview, and browser evidence are green. |
| Preserve image/fallback/accessibility diagnostics | Implemented | Extracted components retain source, fit/radius, fallback, loading, preview-disabled link, and part metadata; focused preview asserts fallback source. |
| Preserve contributor ordering | Already existed and verified | Contributor renderer/view were not changed; the affected feature and full suites remain green. |
| Preserve validator/import/restore/editor-order compatibility | Already existed and unchanged | No validator, value object, draft normalizer, writer, import, restore, backup, lifecycle, or Builder action file changed. |
| FU02 sample ranking | Deferred by approved option | Selector queries and UI ranking were not changed. The complete own-local/external/inherited/none evidence requirement remains in research 31. |
| FU03/O4 path-corrected validation closure | Deferred by approved option | No validation-path mapping changed. O4 is an internal Step 5B bug, not a GitHub issue. |
| Nested `part_group` image enablement | Deferred for a fresh audit | The existing body-only child filter remains unchanged and is explicitly inventoried in research 31. |
| Remaining Fable corrections/evidence gaps | Deferred for a fresh audit | Effective-order boundary helper/tests, the named double-refresh pair, modal browser detail coverage, import→public→editor-save regression, width caveat, stale helper copy, and related risks are retained in research 31. |
| Production normalization or production action | Not applicable and excluded | None was run, prescribed, or added to an operator procedure. |
| O2/O3, migration, dependency, permission, persistence, lifecycle, branch/worktree, push, or another roadmap step | Not applicable and out of scope | None was implemented or performed. |

## Compatibility and scope boundary

No FU01 change was made to:

- `PublicFrontConfigValidator` or `PublicFrontCardTemplate` ordering;
- `CardTemplateDraftNormalizer`, the focused writer, or the native scoped move
  action;
- sample preload/search/ranking queries or restricted-state handling;
- validation error targeting;
- import, restore, backup, settings lifecycle, settings persistence, or schema;
- nested child filtering; or
- contributor presentation.

The presenters retain `parts`, `media_parts`, and `body_parts`. FU01 reuses
those existing contracts rather than removing or generalizing them.

## Files changed

- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRenderer.php`
- `resources/views/components/public/content-item-card.blade.php`
- `resources/views/components/public/content-item-card-part.blade.php`
- `resources/views/components/public/content-item-card-image-part.blade.php`
- `resources/views/components/public/content-group-card.blade.php`
- `resources/views/components/public/content-group-card-part.blade.php`
- `resources/views/components/public/content-group-card-image-part.blade.php`
- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `tests/Feature/CardTemplateEditorPreviewTest.php`
- `tests/Browser/CardTemplatePreviewBrowserTest.php`
- `docs/research/settings-performance/31-step5b-card-template-image-order-followup-research.md`
- `docs/research/settings-performance/32-step5b-card-template-image-order-followup-implementation-plan.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/settings-step5b-card-template-preview-handoff.md`
- this handoff.

## Tests added or updated

- Public Card Template feature coverage now tests leading row presentation for
  item/group and exact public interleaved-image order for both families.
- Focused editor Livewire coverage moves an image with the native scoped action
  and proves the in-memory preview order, effective layout, and fallback source.
- Authenticated Chromium coverage opens the real native move modal, moves the
  image to position 10, observes the updated badge, proves title/custom text
  precede the image, rejects settings persistence, and reports no JS/smoke
  errors.

## Commands and results

### Preflight and research

- `git status --short --branch`: clean
  `main...origin/main [ahead 44]` before research 31/plan 32.
- `git rev-parse --show-toplevel`: `/Users/studioycm/Herd/PodText`.
- Recent-log, `git log --follow`, `git log -S`, `git blame`, and historical
  `git show` inspections pinned the item split to `e3c81de`, the group split to
  `f712791`, and the earlier hard-coded media-first view to the July 4 baseline.
- Repository instructions, lessons, current state, ledger head, recent
  handoffs, completed docs 29/30, relevant code/tests, and required skill rules
  were consulted before application edits.
- A Laravel-guidance read-only subagent inspected the applicable Blade, testing,
  style, architecture, and sibling-code conventions; it made no changes.
- `git diff --check` after creating and re-reading research 31/plan 32: passed.

### Iteration and focused verification

- First `vendor/bin/pint --dirty --format agent`: fixed import order and unary/
  not-operator spacing in the new feature tests.
- Focused public FU01 filter: passed 4 tests / 16 assertions.
- Focused Livewire native-move preview filter: passed 1 test / 13 assertions.
- Full `PublicFrontCardTemplateBuilderTest.php`: passed 29 tests / 338
  assertions.
- Full `CardTemplateEditorPreviewTest.php`: passed 14 tests / 242 assertions.
- Second `vendor/bin/pint --dirty --format agent`: passed.
- Sandboxed focused browser run: failed before application execution because
  Chromium `bootstrap_check_in ... Permission denied (1100)` closed the browser.
  No application change was made for this infrastructure failure.
- The identical focused browser test with the permitted external Chromium
  runner: passed 1 test / 12 assertions.
- Full affected browser file with the permitted external Chromium runner:
  passed 6 tests / 122 assertions.

### Ordered final gate

- Requirements sweep: passed; FU01 files stay inside renderer/view/test/docs
  scope and all explicit exclusions remain absent.
- First `vendor/bin/pint --test`: passed.
- First `vendor/bin/filacheck`: passed with 0 issues.
- First `npm run build`: passed with Vite 8.1.0.
- First full serial `php artisan test`, run last with the permitted external
  Chromium runner: passed 777 tests / 9,806 assertions in 373.340 seconds.
- The staged `git diff --cached --check` then exposed four trailing spaces in
  the two new, previously untracked research/plan documents; the earlier
  unstaged check could not inspect untracked content. Only those spaces and
  this command record changed; application code stayed byte-identical.
- The ordered gate restarted on that documented final tree:
  `vendor/bin/pint --test` passed; `vendor/bin/filacheck` passed with 0 issues;
  `npm run build` passed with Vite 8.1.0; and full serial `php artisan test`
  passed last with the permitted external Chromium runner.

No full suite or browser suite was parallelized or interrupted. No
`vendor/bin/filacheck --fix` command was run.

## Deferred follow-up audit

FU02 and FU03 may run sequentially immediately after this two-commit closeout,
but neither is implicitly selected. The next action is a fresh read-only
Simplifier audit at the FU01 baseline using the complete inventory in research
31. Only a later explicit option approval may authorize new research/plan files
and implementation.

That inventory includes:

- FU02 visible/automatic own-image-first sample ranking and the local/external/
  inherited/none browser matrix;
- FU03/O4 UUID-aware top-level/nested validation-path targeting;
- effective-order range/fallback helper and tests;
- the `updatedInteractsWithSchemas()` plus Builder `afterStateUpdated()`
  double-refresh pair;
- autofocus/select/Enter/Escape modal evidence;
- import explicit-order public rendering followed by focused x10 save;
- width transience and viewport-breakpoint caveat;
- remaining current-renderer helper copy and cosmetic line-anchor drift;
- conditional collapse rules and the rejected O2/O3 risks; and
- nested image-block rendering semantics.

## Assumptions and limitations

- FU01's authoritative sequence is the sorted top-level part stream identified
  by the approved audit. Nested image enablement would reveal previously omitted
  public content and therefore remains separately auditable.
- Effective stacked presentation intentionally changes an interleaved `rows`
  template to result-layout `cards`; the configured compatibility attribute
  remains `rows`.
- The browser test uses the English translated `Image` heading in an explicitly
  English session. HE/RTL rendering remains covered by the existing affected
  browser file and full suite.
- Browser geometry evidence is browser-plane evidence only; no component or
  query performance claim is inferred from it.

## Local Front Check Report

1. Open Admin > Settings > Card Templates and edit a content-item template with
   a visible Image part.
2. Choose inline Builder mode and confirm the Image row is initially first.
   Expect the preview to retain its existing leading-image card or row geometry.
3. Open the Image row's native move action, focus the position field, and type a
   later slot such as 3 or 9. Expect the current value to be selected for
   replacement; press Enter and expect the modal to submit.
4. Compare the Builder badge with the preview. Expect the image to appear at the
   same configured position between the surrounding parts, not at the card top.
5. Move the Image part again and press Escape before submitting. Expect the
   modal to close without changing Builder or preview order.
6. Set the template layout to Rows with Image first. Expect the established row
   geometry and `media-leading` diagnostic to remain.
7. Move Image after another part while Rows remains configured. Expect one
   ordered vertical stack, effective result layout Cards, and configured layout
   Rows retained in compatibility diagnostics.
8. Repeat with a content-group template. Expect the podcast/group image to
   remain between its configured neighbors.
9. Test an episode with an own image and one using fallback. Expect existing
   source, fit, radius, alt/fallback, and lazy-loading behavior in either flow.
10. Open the narrow Preview slide-over. Expect the same ordered output and inert
    public links as the wide preview.
11. Save only if desired, reload, and expect the configured order to persist
    through the existing contiguous x10 focused-save contract.
12. Separately search the sample selector for image-bearing episodes. Treat any
    ranking mismatch as FU02, not FU01; do not normalize production data.
13. Trigger an unrelated invalid nested part field. Treat any jump to the key/
    slug field as FU03/O4, not a GitHub issue and not FU01.

## Current Git status

Before the implementation commit, `main...origin/main [ahead 44]` contains only
the approved FU01 application/test changes, research 31/plan 32, and requested
closeout documentation. No unrelated pre-existing change is present.

## Commit hash

`2d028255e416399d9b167b2ca9a091e2eec852f5`
