# Step 5B Card Template Sample-Ranking Parity Handoff

## Status

Implementation, focused verification, authenticated browser verification,
independent review, and the canonical final gate are complete. The
implementation commit is pending.

- Audit: `LS-20260719-STEP5B-CARD-UX2-FU02-SAMPLE-RANKING-01`
- Option: `STEP5B-CARD-UX2-FU02-SAMPLE-RANKING-PARITY`
- Starting HEAD: `27f38aeaebc8ab2ff4279abd2a905efdce82b495`
- Starting branch: `main`, four commits ahead of `origin/main`
- Implementation hash: pending
- Push/PR/production: forbidden and not performed

## Contract and provenance

The operator directly approved this exact Audit ID and Option ID in this task.
Research 37 and implementation plan 38 were created and fully consulted before
application code changed. The verified baseline contains O1 implementation and
stamp `215340d3` / `14285b34`, O2 implementation and stamp `f56ef369` /
`27f38aea`, and the `d8f42da` navigation baseline. The checkout had not changed
from the expected O2 closeout when Stage 2 began.

O1's exact 1024px adjacent/slide-over boundary, focus restoration, transient
state, and single-root behavior remain binding. O2's actual-part flow,
geometry, exact diagnostics, group link structure, and finite theme-source
coverage remain binding. Public eligibility, restricted zero-render/zero-query
guards, selected-label and forged-ID safety, contributor ordering, settings
lifecycle, navigation, translations, and explicit-order compatibility remain
authoritative.

## Outcome

`CardTemplatePreviewer` now consumes the request-scoped validated
`PublicFrontRenderContext`. The preview renderer, public transcription policy,
aggregates, default-image resolver, automatic sample, preloaded options,
searched options, and selected-label lookup therefore share one current
context. The unsaved normalized Card Template remains the explicit render
template; no configured template replaces it.

One ranked family query now controls automatic choice, preload, search, and
label eligibility before `first()` or `limit()`:

1. Content items with a nonblank own local path or external thumbnail rank 0.
2. Content items with a nonblank inherited group cover rank 1 when item mode is
   not `none`.
3. Otherwise, a validated effective family or global configured default ranks
   2 when family mode permits it.
4. No effective image ranks 3 and retains the existing visual fallback.

Items retain effective-transcription publication descending then ID descending
inside every tier. Groups rank own cover at 0, effective family/global default
at 2, and none at 3, then retain title ascending and ID ascending. Contributor
ordering is unchanged because configured contributor/global defaults are
uniform across that family.

The SQL uses model-derived table names, bound validated boolean flags, and one
correlated group-cover `EXISTS`. It does not load an unbounded collection,
resolve images per row, probe storage, or fetch external URLs. Preview rendering
no longer disables permitted group-cover inheritance, so its visible source
matches the rank.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| Current validated preview context | Implemented | The scoped render context is injected and reused for policy, resolver, queries, and rendering. |
| One automatic/preload/search/label rank | Implemented | The former automatic-versus-`imageFirst` fork was removed. |
| Item local/external rank 0 | Implemented | Nonblank own columns share rank; local still wins rendering when both exist. |
| Permitted inherited group cover rank 1 | Implemented | A bound policy flag plus correlated `EXISTS` matches resolver inheritance. |
| Family/global configured default rank 2 | Implemented | Resolver-effective validated defaults are exposed through one minimal policy seam. |
| No effective image rank 3/fallback | Implemented | `none` suppresses inheritance/global default and renders the existing fallback. |
| Group own/default/none ranking | Implemented | Group SQL uses the same current default-image context and existing title/ID ties. |
| Contributor behavior | Already existed; verified | Discovery query and count/name/ID order are unchanged; configured default rendering remains covered. |
| Exact item/group tie behavior | Implemented; verified | Equal-time own-image items prove ID-desc; groups retain title/ID order. |
| Exactly 10 preload / capped 50 search | Already existed; verified with new rank | Both limits apply after the shared SQL order and are visible in authenticated Chromium. |
| Explicit selected sample and label safety | Already existed; verified | Explicit eligible IDs remain honored; missing/ineligible IDs and labels remain rejected. |
| Public eligibility | Already existed; verified | Existing item/group/contributor public query services remain the only bases. |
| Restricted zero-render/zero-query/forged behavior | Already existed; verified | Restricted schema stays empty; forged direct/schema interactions issue no sample-model query. |
| Request-scoped lean query/state budgets | Already existed; verified | Family query planes remain constant, no lazy load appears, and option maps/models/config are not serialized. |
| No storage/HTTP ranking probes | Implemented | Ranking uses stored nonblank strings and validated config facts only. |
| Authenticated local/external/inherited/family/global/none proof | Implemented | Chromium asserts the corresponding rendered source markers and fallback. |
| O1 responsive/focus/single-root behavior | Already existed; verified | The complete browser file remains green. |
| O2 flow/geometry/diagnostics/theme-source behavior | Already existed; verified | O2 matrices remain green with current-context default sources. |
| `d8f42da` navigation/translations/tests | Already existed; verified | No navigation, translation, or navigation-test file changed. |
| Settings lifecycle compatibility | Already existed; verified | One mount-time context-cache read and the existing one save-time invalidation are pinned separately. |
| FU03/O4 validation targeting | Deferred | It remains an internal Step 5B bug requiring separate approval. |
| FU04 order compatibility closure | Deferred | No global compatibility path changed. |
| FU05 interaction/duplicate-refresh closure | Deferred | No interaction architecture changed. |
| FU06 copy cleanup | Deferred | No translation or copy file changed. |
| Blocked requirements | Blocked | None. |

## Files changed

Application:

- `app/Support/PublicFront/PublicDefaultImageResolver.php`
- `app/Support/Settings/CardTemplates/CardTemplatePreviewer.php`

Tests:

- `tests/Feature/CardTemplatePreviewerTest.php`
- `tests/Feature/CardTemplateEditorPreviewTest.php`
- `tests/Feature/SettingsSp3cTest.php`
- `tests/Browser/CardTemplatePreviewBrowserTest.php`

Research, plan, and synchronization:

- `docs/research/settings-performance/37-step5b-card-template-sample-ranking-parity-research.md`
- `docs/research/settings-performance/38-step5b-card-template-sample-ranking-parity-implementation-plan.md`
- `docs/phase-02/settings-step5b-card-template-sample-ranking-parity-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/settings-step5b-card-template-preview-handoff.md`
- `docs/phase-02/settings-step5b-card-template-ordered-flow-foundation-handoff.md`

No Blade, CSS, JavaScript asset, translation, navigation, navigation-test,
model, migration, dependency, permission, configuration ownership, or settings
writer file changed.

## Test coverage added or updated

- Item matrix: own external, own local, inherited cover, family default,
  global default, none, equal-timestamp ID tie, reverse publication ordering,
  public draft/future/no-transcription exclusions, explicit selection, missing
  label, and forged ID.
- Group matrix: own cover, family default, global default, none, automatic,
  preload, search, and existing title/ID ties.
- Contributor continuity with configured default.
- Livewire selector parity for automatic, preload, search, and selected preview.
- Exact 10/50 caps and existing two-query group selector plane.
- Restricted direct/schema forged interaction with zero item/group/author
  sample queries and absent protected state.
- Current-context cache lifecycle: one mount-time read and one save-time
  invalidation.
- Authenticated Chromium: automatic choice, visible 10-option order, visible
  50-result cap/order, effective source changes, keyboard/focus, request count,
  same-origin fixture resources, restricted surface, and full O1/O2 regression.

## Browser evidence

The FU02 fixture used an authenticated admin editor, English locale, LTR
direction, and a 1440 x 900 CSS-pixel viewport. The existing combined file also
reverified Hebrew RTL and English LTR O1/O2 surfaces across the established
responsive widths.

- Fresh automatic selected value: `FU02 Browser External`, source
  `item_external`.
- Visible ten-option preload order: External, Local, Inherited, Configured
  Default, then Filler 01 through Filler 06.
- Search `FU02 Browser`: exactly 50 visible results in that same tier order,
  ending at Filler 46 from 56 eligible matches.
- Explicit sources: external `item_external`, local `item`, inherited `group`,
  family configured `content_item_default`, global configured
  `global_default`, and mode-none inherited sample `fallback`.
- The search input was focused after the Select's real asynchronous open; an
  ArrowDown moved navigation into the option plane and Escape returned focus
  to the Select button.
- Livewire's installed request interceptor observed at least four actual
  request dispatches during search/selection. This is a request-count plane,
  not HAR, TTFB, response-size, or browser-memory measurement.
- Test-owned local image resources were served relative to the browser test
  server; the observed FU02 image-resource cross-origin list was empty.
- Restricted mode displayed its warning, rendered zero sample Selects, exposed
  no protected sentinel, retained one preview root, and had no horizontal
  overflow.
- Every FU02 state passed `assertNoSmoke()` and
  `assertNoJavaScriptErrors()`. No unexpected console error was ignored.

The first sandboxed Chromium launch failed before assertions with macOS
`MachPortRendezvousServer ... Permission denied (1100)`. The identical test was
retried with the permitted runner as required. The final focused browser run
passed 1 test / 42 assertions; the final isolated browser file passed 11 tests
/ 1,731 assertions in 59.920 seconds.

Measurement limits: browser evidence is Chromium DOM/computed state, Filament
Select behavior, Livewire request interception, and Resource Timing names. It
is not a DevTools HAR, server query log, heap snapshot, listener enumeration,
or production measurement. Query evidence is separately fixture-backed Pest
SQL logging; restricted model-query absence and constant family query planes
must not be promoted to browser timings.

## Independent reviews

1. Architecture/simplification review: no P0–P3 finding. It confirmed one
   injected scoped context, shared ranked query, resolver-semantic flags,
   preserved public/contributor/restricted boundaries, and untouched O1/O2/nav
   surfaces. Its delta review also accepted the one-read/one-invalidation
   lifecycle expectation.
2. Test/performance/security review: initially found two low evidence gaps.
   The browser counter observed `fireAction` calls rather than aggregate
   network requests, and item equal-time ID ties were implicit. The browser now
   uses `Livewire.interceptRequest`, and the item matrix now gives local and
   external records one publication timestamp and directly proves later-ID
   first. The reviewer rechecked both and reported no actionable finding.

Both reviews were static and read-only; reviewers ran no tests, browser,
database probes, or mutations.

## Commands and results

### Preflight and research

- Repository-owned instructions, lessons, current state, ledger head, newest
  handoffs, O1/O2 handoffs, research 33–36, earlier selector research, relevant
  code/tests/specs/history, and every selected skill/reference were read fully.
- `pwd`, `git rev-parse --show-toplevel`, branch/HEAD/upstream/status/log/diff
  probes: `/Users/studioycm/Herd/PodText`, Git root identical, `main`, exact
  clean HEAD `27f38aea...`, four ahead/zero behind, required O1/O2 commits
  present, and no `d8f42da..HEAD` navigation/translation/test diff.
- Laravel Boost `application_info`: PHP 8.4, Laravel 13.19.0, Filament 5.6.7,
  Livewire 4.3.3, Pest 4.7.4, Tailwind CSS 4.3.2.
- Laravel Boost version-aware Select research: custom search and selected-label
  callbacks confirmed. No database-schema tool was used because the local
  development database is off-limits.
- FilamentExamples two-pass search: constrained Eloquent `limit(50)` snippets
  found. Only `search_examples` is exposed; no source/read/detail tool exists.
- Research 37 and plan 38 were created, fully reread, and passed
  `git diff --check` before application code changed.

### Focused implementation and iteration

- PHP lint for both application files and later browser test lint: passed.
- Initial `CardTemplatePreviewerTest`: failed 1 old expectation after the
  approved current-context fallback changed (13 of 14 passed / 67 assertions).
  Updated focused file passed 15 / 95.
- `CardTemplateEditorPreviewTest` before and after its new parity case: passed
  14 / 251, then 15 / 259.
- First sandbox browser run: infrastructure failure before assertions on the
  Chromium Mach-port bootstrap. Identical permitted retry reached the test.
- Browser iteration recorded the following application-level failures before
  the fixture/test synchronization settled: four runs stopped after 5
  assertions with zero visible search results; one run repeated that result
  after 100.535 seconds while waiting on the wrong async-open boundary; one run
  stopped after 13 assertions because action calls were mislabeled requests;
  one stopped after 14 because absolute `podtext.test` fixture URLs were
  cross-origin to the test server; and one stopped after 24 because it sampled
  the old preview source before the selected render completed. These were
  test-only synchronization/measurement defects; no application workaround was
  added. The existing transient selector regression independently passed 1 / 27.
- Settled focused FU02 browser: passed 1 / 42; the final event-driven rerun also
  passed 1 / 42. After review corrections, it passed again 1 / 42.
- Combined FU02 feature files: passed 30 / 354.
- First full browser file after FU02: failed 2 of 11 after 705 assertions because
  two O2 datasets still expected registry-default `fallback`. Those test
  expectations were narrowed to the now-approved current configured item/group
  default sources. Isolated full browser retry passed 11 / 1,731.
- First related default-image/config/cache/builder/SP3C/canary batch: failed 1
  of 87 after 86 passed / 1,249 assertions because a strict cache mock allowed
  invalidation but not the new mount-time current-context read. The test now
  expects one resolver-backed `remember()` plus the existing one `forget()`.
- Focused lifecycle retry: passed 1 / 5. Related batch retry passed 87 / 1,252.
- Equal-time item tie review correction: focused retry passed 1 / 14.
- Iteration `vendor/bin/pint --test`: passed.
- Iteration `vendor/bin/filacheck --dirty`: passed with 0 issues.
- Process-list probe before browser work could not read macOS sysmon inside the
  sandbox. One main-agent browser owner was known and maintained; no overlapping
  browser or test suite was launched.

### Canonical final gate

First final-tree attempt:

1. Requirements sweep — passed. `git diff --check` was clean; the only app
   changes were the two planned support classes; O1 shell/Blade/theme paths and
   O2 renderer/presenter/Blade paths had no diff from `27f38aea`; the
   `d8f42da` navigation map, translations, and navigation test had no diff; and
   dependency, migration, and config paths were untouched.
2. `vendor/bin/pint --test` — passed.
3. `vendor/bin/filacheck` — passed with 0 issues.
4. `npm run build` — passed with Vite 8.1.0.
5. Sandboxed `php artisan test` — failed only after Chromium bootstrap closed
   the browser: 774 of 793 passed / 9,786 assertions, followed by 19 browser
   failures/risky cases rooted in
   `MachPortRendezvousServer ... Permission denied (1100)` and the inherited
   closed browser. No application assertion triggered the first failure.
6. Identical permitted `php artisan test` retry — passed 793 tests / 11,578
   assertions in 417.077 seconds.

Documentation-record restart after adding the results above:

1. `vendor/bin/pint --test` — passed.
2. `vendor/bin/filacheck` — passed with 0 issues.
3. `npm run build` — passed with Vite 8.1.0.
4. Full serial `php artisan test` last with the permitted runner — passed 793
   tests / 11,578 assertions. No file changed afterward before the
   implementation commit.

## Assumptions and limits

- Stored nonblank image/default paths indicate effective configured candidates;
  ranking deliberately does not assert file existence or fetch URLs.
- `multiMode: false` remains the focused preview's established single-mode
  public-eligibility contract. FU02 adopts the current validated transcription
  policy values without expanding contributor or multi-mode behavior.
- The current context adds one expected mount-time cache read. It does not add a
  save, invalidation, backup, reference scan, or settings lifecycle mutation.
- Browser fixture paths are test-owned. The local development database, live
  HTTP clients, live mail, production, and remote services were not used.
- The installed FilamentExamples integration is search/snippet-only.
- No material baseline, scope, dependency, migration, security, task-count, or
  effort drift occurred; no amended audit was required.

## Full deferred inventory

- FU03/O4 validation-path correction; O4 remains an internal bug, not a GitHub
  issue.
- FU04 order-compatibility closure.
- FU05 interaction and duplicate-refresh closure.
- FU06 copy cleanup.
- Legacy UX2 O2 inline-header editing.
- UX2 O3 global explicit-order cutover.
- Production normalization and every production action.
- Nested `part_group` image enablement and nested-media redesign.
- Contributor image-field invention or contributor renderer redesign.
- Persistence for preview width, sample, or Builder mode.
- Migrations, dependencies, permission redesign, settings lifecycle changes,
  generalized renderer/platform work, another roadmap step, branch/worktree,
  push, PR, deploy, or publication.

## Numbered Local Front Check

1. Open Admin > Settings > Card Templates and edit a content-item template in
   Hebrew. Expect RTL and exactly one preview root at the established responsive
   boundary.
2. Open Choose Sample. Expect exactly ten initial public-safe options.
3. Prepare eligible episodes with an own local image, own external thumbnail,
   inherited podcast cover, configured episode/global default, and no effective
   image in a test environment.
4. Reopen Choose Sample. Expect own local/external records first, inherited
   covers second, configured-default records third, and no-image records last.
5. Give two records inside one tier the same effective transcription date.
   Expect the larger item ID first.
6. Search for a term matching more than fifty eligible samples. Expect exactly
   fifty visible results in the same rank and tie order.
7. Select the own-local sample. Expect the preview image source to be `item`.
8. Select the own-external sample. Expect the preview image source to be
   `item_external`.
9. Select the inherited-cover sample. Expect the preview image source to be
   `group` when content-item image mode permits inheritance.
10. Select the family-default sample, then configure only a global default and
    repeat. Expect `content_item_default`, then `global_default`.
11. Set content-item image mode to `none` in the test environment and select the
    inherited/no-image sample. Expect inheritance/global default suppression and
    the existing `fallback` treatment.
12. Press ArrowDown in the open searchable Select, then Escape. Expect option
    navigation and focus returned to the Select button.
13. Reload the editor. Expect sample choice and width to reset without a
    settings write, backup, notification, or cache invalidation.
14. Edit a protected template without the current capability. Expect a
    restricted warning, no sample Select, no protected value, and no accepted
    forged sample interaction.
15. Repeat at 1023px and 1024px in Hebrew and English. Expect O1's one-root
    slide-over/adjacent transition, focus restoration, no overflow, and O2's
    unchanged effective geometry and diagnostics.

## Commit hash

Pending canonical implementation commit and immediate docs-only hash stamp.
