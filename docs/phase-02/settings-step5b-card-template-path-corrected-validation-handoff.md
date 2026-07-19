# Step 5B Card Template Path-Corrected Validation Handoff

## Status

FU03 is implemented under the directly approved audit and option. Research 39,
implementation plan 40, and this handoff were created and consulted before
application code changed.

- Audit: `LS-20260719-STEP5B-CARD-UX2-FU03-PATH-CORRECTED-01`
- Option: `STEP5B-CARD-UX2-FU03-PATH-CORRECTED-CLOSURE`
- Starting HEAD: `206963998b9513ea345ef657df7697b2901a7af3`
- Starting branch: `main`, zero behind and seven commits ahead of `origin/main`
- Implementation hash: pending
- Docs-only hash stamp: pending
- Push, PR, production, branch, and worktree actions: not performed

## Contract and provenance

The operator directly approved this exact Audit ID and Option ID in the same
task. The verified clean baseline contains FU02 implementation, stamp, and
closeout correction `a8be0aa4` / `23c3ac9` / `20696399`, O1 implementation and
stamp `215340d3` / `14285b34`, O2 implementation and stamp `f56ef369` /
`27f38aea`, and the `d8f42da` navigation baseline.

O1 responsive shell, focus restoration, dirty-state, and single-root behavior;
O2 position-canonical flow, renderer geometry, diagnostics, and theme sources;
FU02 sample ranking, public eligibility, contributor ordering, restricted/query
boundaries; legacy explicit-order compatibility; and navigation are preserved.
No navigation or translation file changed.

## Outcome

Structured `PublicFrontInvalidConfig` issues now survive validation through the
Card Template write exception independently of compatibility display strings.
The editor resolves the first actionable validator path against the current
UUID-keyed Builder state after hydration and reordering. It then targets the
real root, top-level, or one-level nested field in inline mode, or mounts the
verified native Filament Builder owner action stack before placing the error in
slide-over mode.

The preview's invalid-field control is now one authorized Livewire action. It
accepts no client path or UUID, recomputes validation from the current draft,
and applies the same routing as save. Only `_show_label` or `_show_icon` may be
revealed transiently when the verified field requires it. Installed
`form-validation-error` and `focus-input` behavior expands, exposes, scrolls,
and focuses the target. If exact targeting or action mounting is impossible,
the editor uses a deterministic verified owning-Builder or stable visible-focus
fallback; a part issue never mis-targets `data.key`.

## Structured path mapping semantics

Mapping follows the same filtered, positional current order that
`CardTemplateDraftNormalizer` sends to the validator. Associative UUID order is
authoritative; keys, slugs, labels, translated text, reasons, display strings,
and `valuePreview` are never parsed or used as identity.

| Accepted validator path | Verified inline state path | Slide-over behavior |
|---|---|---|
| `card_templates.0.<root-field>` | `data.<root-field>` | No Builder action; target the allowlisted root field. |
| `card_templates.0.parts` | `data.parts` | Target the visible top Builder. |
| `card_templates.0.parts.<p>.data.<field>` | `data.parts.<top-uuid>.data.<field>` | Mount verified top item `edit`, then target `mountedActions.0.data.<field>`. |
| `card_templates.0.parts.<p>.data.children` | `data.parts.<top-uuid>.data.children` | Mount the verified parent and target `mountedActions.0.data.children`. |
| `card_templates.0.parts.<p>.data.children.<c>.data.<field>` | `data.parts.<top-uuid>.data.children.<child-uuid>.data.<field>` | Mount parent then child before `addError()`, then target the final mounted action data field. |

Every mapped UUID is re-read from current state and verified against expected
part type, data shape, owning Builder, and schema component. Malformed prefix,
depth, position, owner, type, component, or field data degrades without
inventing an identity. For a nested slide-over field whose child action cannot
be mounted, the verified parent action remains open with the error on
`mountedActions.0.data.children`; if the parent action cannot mount, the target
degrades to visible `data.parts`.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| Preserve validator issues end-to-end without parsing display strings, translations, or `valuePreview` | Implemented | `CardTemplateWriteException` keeps a typed structured issue list separately from string `details`; the normalizer passes issue objects through. |
| Resolve positional root, top-level, and nested paths against current hydrated/reordered UUID state | Implemented | Strict card-template-specific grammar reproduces normalized current order and verifies UUID, type, Builder owner, data, schema, and leaf field. |
| Target the actual invalid field in inline mode | Implemented | Exact UUID-owned state paths receive the error; installed expansion plus bounded focus retry handles collapsed rows. |
| Target the actual invalid field in slide-over mode | Implemented | Native owning Builder actions mount before `addError()`; top and nested mounted action state paths are verified. |
| Reveal only necessary subordinate controls | Implemented | Only transient `_show_label` and `_show_icon` state is revealed; persistent draft values and positions are not changed. |
| Dispatch narrow installed-version events and restore safe focus | Implemented | One page-root listener dispatches Filament's installed `focus-input`; native/modal Escape and explicit safe restoration cover inline, wide slide-over, and narrow preview entry. |
| Deterministic fallback for mapping, action mounting, or focus failure | Implemented | Nearest verified Builder paths, verified-parent nested fallback, top Builder fallback, visible modal Close, Preview opener, and stable editor controls are bounded in that order. |
| Prove top-level, nested, reordered UUIDs, collapsed fields, slide-over, restricted, fallback, and no key/slug mis-target | Implemented | Focused component tests and authenticated Chromium scenarios cover each case, including two-child reorder and forced action-mount failure. |
| Restricted/forged safety and query/state budgets | Implemented | Authorization precedes routing; restricted surfaces expose no Builder/action and add no sample/domain query, settings write, or protected-state serialization. One activation remains one Livewire request. |
| O1 responsive shell/focus/single-root contract | Already existed | Preserved and covered by the combined browser file. |
| O2 ordered flow/geometry/diagnostics/theme-source contract | Already existed | Preserved and covered by the combined component/browser regressions. |
| FU02 shared effective-image sample ranking | Already existed | Preserved in selector, render, restricted, and compatibility regressions. |
| `d8f42da` navigation order, groups, labels, translations, and tests | Already existed | No navigation, provider, or locale file changed. |
| FU04 order-compatibility closure; FU05 interaction/duplicate refresh; FU06 copy cleanup | Deferred | Remain separate sequential, unapproved options; FU04 is next. |
| Legacy inline-header editing; global explicit-order cutover; nested media or contributor-image redesign | Deferred | Outside the approved FU03 defect. |
| Preview preference persistence, migrations, dependencies, permission/lifecycle changes, generalized validator/renderer work | Deferred | No such change was needed or made. |
| Production normalization/actions, push, PR, branch, worktree, or later roadmap work | Not applicable | Explicitly excluded and not performed. |
| Blocked requirement | Not applicable | None. |

## Files changed

- `app/Filament/Pages/CardTemplateEditorPage.php`
- `app/Filament/Support/CardTemplateValidationTarget.php` (new)
- `app/Support/Settings/CardTemplates/CardTemplateDraftNormalizer.php`
- `app/Support/Settings/CardTemplates/CardTemplateWriteException.php`
- `resources/views/filament/pages/card-template-editor.blade.php`
- `resources/views/filament/pages/card-template-preview.blade.php`
- `tests/Feature/CardTemplateEditorPreviewTest.php`
- `tests/Browser/CardTemplatePreviewBrowserTest.php`
- `docs/research/settings-performance/39-step5b-card-template-path-corrected-validation-research.md` (new)
- `docs/research/settings-performance/40-step5b-card-template-path-corrected-validation-implementation-plan.md` (new)
- `docs/phase-02/settings-step5b-card-template-path-corrected-validation-handoff.md` (new)
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/settings-step5b-card-template-preview-handoff.md`

No locale, navigation, provider, model, migration, configuration, dependency,
lockfile, or production file changed.

## Tests added or updated

`tests/Feature/CardTemplateEditorPreviewTest.php` now proves:

- typed issue transport, preserved path/reason, compatibility details, and no
  preview/value leak;
- root, reordered top-level, and reordered two-child nested UUID mapping;
- exact top and nested native action paths and mount-before-error ordering;
- verified-parent nested fallback and forced top-action mount failure;
- deterministic preference for an actionable issue over an earlier fallback;
- malformed prefix/depth, stale/out-of-range position, wrong owner/type, and
  unsupported/forged field fallback;
- structured save failure without `data.key` targeting; and
- restricted/forged zero-action, zero-protected-state, and zero-query behavior.

`tests/Browser/CardTemplatePreviewBrowserTest.php` now proves authenticated:

- Hebrew RTL wide inline navigation through reordered and collapsed top/nested
  owners, exact error placement, actual focus, and forged fallback;
- English LTR wide slide-over top/nested native action stacks, one request per
  activation, error/focus state, Escape/cancel restoration, retry cleanup, and
  no persistence;
- Hebrew RTL 1023px nested verified-parent fallback, visible mounted error,
  one request, no `valuePreview` leak, missing-wrapper Close focus, recovered
  root action identity, Escape restoration to the Preview opener, and no
  persisted preview root/state; and
- restricted absence of the sample selector, Builder, invalid-focus action,
  modal, protected value, and extra preview root.

## Authenticated browser evidence

| Context | Owning state / UUID evidence | Visible result and focus | Action/request state | Persistence and limits |
|---|---|---|---|---|
| Hebrew `rtl`, 1440 CSS px, inline Builder | Dynamic fixture UUIDs were read from current `data.parts`; reordered top and two-child nested bindings contained the current top/child UUIDs. | Collapsed owners expanded; the exact field wrapper showed the error and its installed control received focus. Forged leaf data fell back to its verified owning Builder, not key/slug. | No action modal; one Livewire request per invalid-focus activation. | Draft-only markers remained unsaved; one preview root; no horizontal overflow or console failure. |
| English `ltr`, 1440 CSS px, slide-over Builder | Top target became `mountedActions.0.data.<field>`; nested target used the current parent/child UUID owners and final mounted action data. | Native top and nested slide-over fields showed the error and received focus; Escape/cancel restored a safe visible trigger. | Same-response nested mounting empirically leaves action 0 and action 1 open after settling; child Escape leaves action 0, parent Escape closes it. Each navigation activation produced one request. | Apply/retry cleared stale action identity; no settings persistence or duplicate preview root. |
| Hebrew `rtl`, 1023 CSS px, preview slide-over | A dynamic nested forged path contained both current group and child UUIDs; verified parent fallback was `mountedActions.0.data.children`. | Parent action opened, its children Builder showed the error and focused visibly. A deliberately missing mounted wrapper focused visible Close; Escape restored `card-template-preview-open`. | One request for navigation; recovered modal identity ended in `-action-0`; no synthetic modal. | No `valuePreview` marker in HTML/state, no persistent preview root, and no settings mutation. |
| English `ltr`, 1440 CSS px, restricted template | No protected UUID state or Builder surface was present. | No invalid-field control or focus target existed. | No mounted action/modal and no sample/domain navigation request. | Protected marker absent; one inert preview root. |

UUIDs are fixture-generated per run and intentionally not fixed in this
handoff; the tests capture the actual runtime values and assert that the exact
current bindings, error paths, and owners contain them. Browser DOM, focus,
modal, console, and request observations are browser measurements. Query,
serialized-state, and settings-mutation assertions are fixture-backed
Livewire/component measurements and are not presented as browser heap, TTFB,
or production performance measurements.

## Independent reviews

Two independent read-only post-implementation reviews were completed after
focused implementation tests. Neither reviewer edited files, ran an overlapping
suite, staged, committed, created a branch/worktree, or probed a database.

1. The architecture/history/simplification reviewer verified structured issue
   provenance, current UUID ownership, strict scope, and compatibility. Initial
   findings asked for a verified-parent nested slide-over fallback, narrow
   Preview-opener restoration, and stale action-identity cleanup. The
   implementation now mounts the verified parent, targets
   `mountedActions.0.data.children`, clears identity when the action stack or
   target disappears, and restores the narrow opener. The resolution review
   found no blocker or unnecessary abstraction.
2. The test/performance/security reviewer verified reordered/collapsed coverage,
   one-request routing, restricted/forged safety, and deterministic fallback.
   Findings added explicit two-child reorder coverage and bounded focus-failure
   evidence. Its final edge was a missing mounted wrapper with no captured
   action identity; the page now derives the root action modal identity before
   focusing Close, and the 1023px browser scenario proves identity plus Escape
   restoration. The final resolution check found no blocker or new issue.

The installed same-response nested action stack settles with both native action
0 and action 1 visible; tests follow that observed Filament 5.6.7 behavior and
prove two-stage Escape instead of assuming a nominal single-modal state.

## Commands and results

### Preflight and research

- `pwd` and `git rev-parse --show-toplevel`: both
  `/Users/studioycm/Herd/PodText`.
- `git status --short --branch`: clean `main`, seven commits ahead of
  `origin/main`; no unexpected dirt.
- `git rev-parse HEAD`: exact approved baseline
  `206963998b9513ea345ef657df7697b2901a7af3`.
- `git rev-list --left-right --count origin/main...HEAD`: `0 7`.
- Required FU02, O1, O2, stamp, correction, and `d8f42da` ancestry checks:
  passed.
- Required AGENTS, lessons, state, ledger, newest handoffs, O1/O2/FU02/UX2
  research/plans/handoffs, validator/editor/tests/history, skills, and installed
  Filament/Livewire source reads: completed.
- Laravel Boost application info: PHP 8.4, Laravel 13.19.0, Filament 5.6.7,
  Livewire 4.3.3, Pest 4.7.4, Tailwind CSS 4.3.2.
- Laravel Boost version-aware Builder/action/validation/event/browser research:
  completed; installed source remained authoritative for exact mounted-state
  and focus behavior.
- FilamentExamples required multi-pass search: completed with search/snippet
  access only. No read/fetch/detail/source endpoint existed and no exact FU03
  implementation was returned.
- Authenticated Stage 1 browser inspection: reached the Hebrew RTL editor at
  1470 x 745 and observed live UUID-owned Builder state. The native Edit action
  stalled for 458 seconds; it was interrupted without changing or saving data,
  and no Stage 1 invalid-focus claim was made.
- Research 39, plan 40, and this handoff: created and fully consulted before
  application code changed.

### Focused implementation and review runs

- `php artisan test --compact tests/Feature/CardTemplateEditorPreviewTest.php`
  baseline: passed, 15 tests / 259 assertions.
- Early focused feature invocation: failed two new focus-wrapper expectations
  because Filament does not clone arbitrary wrapper attributes into the target
  DOM. The implementation changed to the installed Alpine `$statePath`
  contract; no vendor code changed.
- Subsequent focused feature invocations passed at 25 tests / 363 assertions,
  then after review fixes at 28 tests / 387 assertions.
- `php artisan test --compact tests/Feature/CardTemplateEditorPreviewTest.php
  tests/Feature/SettingsSp3cTest.php`: passed first at 55 tests / 648
  assertions, then after review fixes at 58 tests / 672 assertions.
- `php artisan test --compact tests/Feature/SettingsSp3cTest.php`: passed,
  30 tests / 285 assertions.
- The first browser launch failed before application assertions with macOS
  Chromium `bootstrap_check_in ... MachPortRendezvousServer ... Permission
  denied`; the identical command was retried with the permitted runner.
- Browser iteration failures, in order, exposed: unsupported client `$wire.$set`
  use; an Alpine parse/invalid CSS selector; focus racing collapsed expansion;
  Builder fallback choosing an arbitrary child; null focus after top action
  Escape; a nominal one-modal expectation while installed same-response nested
  mounting left two native actions open; and a stale nested UUID/DOM lookup
  after collapsed mode. Each failure produced a narrow code/test correction,
  not an application-scope change.
- Focused permitted browser invocations then passed: inline 1 test / 34
  assertions; wide slide-over 1 / 39; restricted 1 / 45; initial narrow 1 / 23;
  and final narrow missing-wrapper evidence 1 / 25.
- Full browser-file permitted invocations passed at 13 tests / 1,805 assertions,
  then 14 / 1,830 after review fixes.
- Final default-runner browser-file invocation again failed at Chromium launch
  with the same Mach-port permission error. The identical permitted rerun
  passed 14 tests / 1,832 assertions in 111.121 seconds.
- `php -l` for touched PHP files: passed during implementation and review
  resolution.
- `git diff --check`: passed during implementation and after the final review
  correction.
- The first closeout `vendor/bin/pint --test` invocation failed only on
  `tests/Feature/CardTemplateEditorPreviewTest.php` for `single_quote`,
  `unary_operator_spaces`, and `not_operator_with_successor_space`.
- `vendor/bin/pint tests/Feature/CardTemplateEditorPreviewTest.php`: formatted
  that one test file. The following PHP lint and combined focused run passed at
  58 tests / 672 assertions; the ordered closeout restarted from the
  requirements sweep.
- Independent read-only architecture resolution review: all findings resolved;
  no blocker.
- Independent read-only test/performance/security final resolution review: all
  findings resolved; no blocker.

### Final closeout

Final-tree requirements sweep and canonical gate results are recorded below.

## Assumptions and limits

- Validator positions describe the current filtered candidate order, not a
  historic explicit `order` value or persistent part identity.
- The accepted grammar intentionally stops at one nested `part_group` level,
  matching the current Builder and validator contract.
- Installed Filament 5.6.7 source and authenticated behavior are authoritative
  for native action mounting, mounted schema paths, expansion, focus, and
  Escape behavior.
- Browser tests own their fixtures. No local development database probe, live
  HTTP, live mail, secret output, production action, or persistent draft save
  was used.
- No claim is made about browser heap, production TTFB, or production network
  latency. One-request evidence is local authenticated browser instrumentation.

## Deferred inventory

- FU04 order-compatibility closure is the next sequential action and remains
  unapproved.
- FU05 interaction/duplicate-refresh closure and FU06 copy cleanup remain later
  and unapproved.
- Legacy UX2 O2 inline-header editing and UX2 O3 global explicit-order cutover.
- Production normalization/actions, nested `part_group` image enablement or
  nested-media redesign, and contributor image-field invention/redesign.
- Persistence for preview width, sample, or Builder mode.
- Migrations, dependencies, permission redesign, settings lifecycle changes,
  generalized validator/renderer platform work, another roadmap step, branch,
  worktree, push, and PR.

## Local Front Check Report

1. Sign in as an authorized administrator, open Card Templates, and edit an
   unrestricted content-item template in Hebrew. Expect the page direction to
   be RTL and exactly one preview root.
2. Select inline Builder mode, collapse a top-level row, make its real field
   invalid without saving, open Preview, and click the invalid-field action.
   Expect the row to expand, the error to appear on that field, and its control
   to receive focus rather than the key or slug field.
3. Reorder two top-level rows, make the second current row invalid, and repeat
   the invalid-field action. Expect the current UUID-owned row to open and focus
   regardless of its prior position.
4. Add or use a grouped part with two children, reorder the children, collapse
   the group, and make the second current child invalid. Expect the group and
   child to expand and the exact current child field to show the error and
   receive focus.
5. Turn Show label or Show icon off while retaining an invalid subordinate
   value, then navigate to the invalid field. Expect only the relevant
   transient control to reveal; cancel or reload and expect no persistent value
   or position change from navigation alone.
6. Switch to slide-over Builder mode in English LTR, make a top-level field
   invalid, and navigate to it. Expect the native Edit slide-over to open before
   the error appears, and expect the exact mounted field to receive focus.
7. In slide-over mode, make a nested child invalid and navigate to it. Expect
   the native parent and child ownership path to open in installed Filament
   order; press Escape through the open stack and expect focus to return to a
   safe visible trigger.
8. At 1023 CSS pixels in Hebrew RTL, open the Preview slide-over and navigate a
   nested invalid issue whose child cannot be targeted. Expect the verified
   parent action and its children Builder to remain visible with the error;
   press Escape and expect focus to return to the Preview opener.
9. Exercise an intentionally stale or unsupported part-field path in the test
   fixture. Expect the nearest verified Builder or visible modal Close control
   to receive focus, never the key/slug field, and expect no invalid value to
   appear in HTML or Livewire state.
10. Open a restricted template and attempt the invalid-focus interaction.
    Expect no parts Builder, invalid-field action, sample selector, protected
    value, native Builder modal, or additional query-producing surface.
11. Repeat invalid navigation, cancel, and retry. Expect no stale error or
    action identity, no duplicate modal/preview root, and one Livewire request
    per activation.
12. Leave without saving and reopen the template. Expect reorder, invalid
    markers, preview choice, width, and Builder display mode used only for this
    check not to have persisted.
13. Verify the admin sidebar in Hebrew and English. Expect the `d8f42da`
    navigation order, groups, labels, and translations to remain unchanged.

## Final gates

The first complete-tree closeout pass produced:

1. Requirements sweep: passed. `git diff --check` was clean; every touched PHP
   file parsed; navigation/provider/navigation-test, `lang/he/admin.php`,
   `lang/en/admin.php`, Composer/npm manifests and lockfiles were unchanged;
   the combined Card Template + SP3C compatibility run passed 58 tests / 672
   assertions.
2. `vendor/bin/pint --test`: passed after the recorded one-file formatting
   correction and restart.
3. `vendor/bin/filacheck`: passed with 0 issues. `--fix` was not used.
4. `npm run build`: passed with Vite 8.1.0 in 1.28 seconds.
5. `php artisan test`: passed last and serially with 809 tests / 11,807
   assertions in 480.410 seconds using the permitted runner for the already
   reproduced macOS Chromium sandbox limitation.

After recording those results, the canonical final-tree repeat passed in the
same required order: `vendor/bin/pint --test`, `vendor/bin/filacheck` with 0
issues, `npm run build`, then full serial `php artisan test` last with 809 tests
/ 11,807 assertions. No file changed after that final repeat.

## Commit hash

Pending implementation commit.
