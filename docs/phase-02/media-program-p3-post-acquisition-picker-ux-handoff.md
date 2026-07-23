# Media Program Post-Package-3 Acquisition Picker UX Handoff

## Scope and baseline

- Audit: `LS-20260723-PODTEXT-MEDIA-P3-POST-ACQUISITION-UX-01`
- Approved option: `MEDIA-P3-POST-O3-IMMEDIATE-SOURCE-WORKSPACE`
- Approved forecast exception: five sequential mini-tasks, 42–62 engineering
  hours
- Repository: `/Users/studioycm/Herd/PodText`
- Starting branch/HEAD: clean `main` at
  `786f7c5f699a3d9d2c02f4c93baff02b0ddcbc1f`, 19 commits ahead of
  `origin/main`
- Package 3 implementation:
  `656a7c2ed1b64b3f6fd8392bff88f7cca36d2695`
- Initial installed stack: Laravel 13.21.1, Filament 5.7.1, Livewire 4.3.3
  and Alpine 3.15.12
- Final installed stack: Laravel 13.21.1, Filament 5.7.3, Livewire 4.3.3
  and Alpine 3.15.12

Preflight found no baseline drift, unexpected application changes or
overlapping repository writer. The five approved mini-tasks ran sequentially
in the current checkout.

The initial approval excluded dependencies. During Stage 2, the operator
explicitly amended only that boundary and authorized the bounded Filament
5.7.1 to 5.7.3 patch refresh without another audit. The update changed 12
Filament family packages and the required transitive
`kirschbaum-development/eloquent-power-joins` 4.3.2 to 4.3.3 patch. It added or
removed no package, changed no manifest, reported no advisory and republished
four tracked Filament assets. Composer's user cache directories were
unwritable, so Composer completed without cache.

## Outcome

The completed picker keeps Package 3's fixed persistence contract:

1. Gallery selection remains a mutation-free existing-record choice.
2. Upload, URL and Storage acquisition becomes a permanent library item when
   acquisition succeeds.
3. Every new item receives its Curator row, `MediaAsset` and Curator provider
   binding together.
4. The picker updates pending owner field state, but owner attachment is not
   written until the outer owner form is saved.
5. Closing the picker or cancelling the owner form does not delete a completed
   acquisition.

O3 adds one immediate source workspace beside the Gallery: Upload, URL and
Storage have local state, Storage enumeration is lazy, single acquisition
chooses directly, multi-selection remains explicit, and busy/offline/error
feedback distinguishes permanent acquisition from pending owner attachment.

The outer Filament action is the only modal owner. Its schema now owns the
nested `MediaPickerPanel` Livewire component, selection return is awaited
through the exposed schema-component method, the picker action then unmounts,
and focus returns to the stable opener without scrolling. Upload's FileUpload
subtree stays mounted while source sections become hidden/inert, preventing
the browser-proven Alpine entangled-state teardown race across modal
close/reopen. Only the active Upload or URL section dehydrates and validates,
so preserved inactive-source state cannot block the visible acquisition.
Because the outer picker action has no submit operation, its schema explicitly
disables Filament's form wrapper. Picker-owned Edit/rename/swap/delete action
modals can therefore own their forms without invalid nesting; a real browser
case opens and closes Edit twice while the picker survives.

Close is inert during every picker Livewire request and throughout upload
transfer, but remains usable while idle and genuinely offline. In that offline
case, Filament performs the full local child-modal cleanup without attempting
an unreachable request, a nested Add/Replace parent reopens immediately, and
focus returns to the stable opener. The next browser online event reconciles
through a guarded exposed method that can unmount only this still-current
picker action. Real BrowserContext isolation covers both direct and nested
actions through reconnect and clean reopen.

The child and parent request planes are guarded separately. After the child
dispatches a trusted selection, an outer Alpine flag makes the entire picker
inert, natively disables Close and exposes `aria-busy` while the owner field
update and picker unmount run. A delayed-parent browser regression proves Close
cannot race that handoff after the child's own loading state has ended.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| Durable research and implementation plan before code | Implemented | The post-P3 research and five-mini-task plan were created and active routing was reconciled before application changes. |
| Preserve immediate permanent acquisition | Already existed / preserved | Successful Upload, URL and Storage work remains permanent immediately; no staging/finalizer/cleanup lifecycle was added. |
| Preserve pending owner attachment | Already existed / regression-preserved | Real browser proof confirms the Media/kernel exists before attachment and the attachment is written only by outer Save. |
| Single-mode upload safety | Implemented | FileUpload is mode-aware and forged multi-file single state is rejected before validation or admission. |
| Complete batch prevalidation | Already existed / regression-preserved | All inputs validate before the first write. |
| Partial operational batch truth | Implemented | Earlier successful items remain permanent and visible; structured results report failed/unattempted remainder without claiming rollback. |
| Media Resource batch compatibility | Implemented | The standalone resource retains bounded multi-upload and reports partial completion truthfully. |
| Storage errors and dispositions | Implemented | Dedicated associated errors, stale-error clearing and reused/registered/copied feedback are present. |
| Storage concurrency | Implemented with lease boundary | A hashed `(disk, path)` atomic lock uses a deliberately oversized 60-second default/cap, inside-lock token resolution and Media recheck. It serializes only while the cache lease remains owned and is not relational uniqueness. |
| URL shared budget | Implemented with synchronous-DNS boundary | One monotonic allowance decreases across DNS, pinned requests, redirects and response-policy checkpoints; transport receives only the remainder. Synchronous DNS may overrun, after which the next checkpoint prevents transport. |
| Safe URL failure categories | Implemented | Rejected, missing, unavailable, timed-out, invalid-image and unexpected cases map to bilingual copy without leaking URL/host/IP/path/token/exception text. |
| Authorization failure boundary | Implemented | URL acquisition rethrows authorization failures like Upload and Storage; it never converts them into ordinary field feedback. |
| Immediate single choice | Implemented | Successful single acquisition updates the trusted host field and closes; Gallery and multiple mode retain explicit Use Selected. |
| O3 source workspace | Implemented | Filament source navigation, source-local state, active-only source validation/dehydration, lazy Storage, explicit refresh and distinct empty states are present. |
| Busy/upload/offline truth | Implemented | The Close/search ancestor is inert for every child picker request; a separate parent-handoff flag inerts the whole workspace and natively disables Close during field update/unmount; Upload guards the header and acquisition action while transfer cancellation stays usable; idle true-offline Close performs local Filament cleanup plus guarded reconnect reconciliation. |
| Irreversible-work dismissal policy | Implemented | Escape, backdrop and default modal close are disabled; explicit Close is the sole idle dismissal path. |
| Nested action-modal ownership | Implemented | Raw nested modal content was replaced by a uniquely keyed schema-owned Livewire component; duplicate action-modal partial ownership is absent. The display-only outer action has no form wrapper, and a two-cycle browser regression proves picker-owned Edit modals open/close without losing the picker owner. |
| FileUpload source-switch lifecycle | Implemented | FileUpload remains mounted and inactive sections become hidden/inert; repeated open/close/reopen/source-switch browser runs have no Alpine `state` error. |
| Accessible Gallery/source controls | Implemented | Search labels, button names, `aria-pressed`, blocked-reason association, selected-count live region, focus ring and coarse-pointer actions are present. |
| RTL/LTR responsive integration | Implemented | Real Hebrew RTL and English LTR browser cases cover one modal owner, wide/narrow layout, no horizontal overflow, focus return and acquisition/save flow; a separate real outer Add/Replace action case covers parent return, cancel and Save. |
| Filament 5.7.3 patch amendment | Implemented | Lockfile-only bounded refresh plus generated tracked assets; 5.7.2 focus restoration is consumed and no unrelated dependency changed. |
| Package 4 owner image UX | Deferred by approved boundary | Preview/detail/metadata/copy/download/change/remove/automatic/broken-association owner UX requires a fresh Package 4 audit. |
| Package 5 filesystem lifecycle | Deferred by approved boundary | Discovery, move, rename, replace, trash, restore and purge remain Package 5. |
| Relational/settings migrations | Not applicable | No migration or schema change was needed or executed. |
| Live data, production and push | Not applicable / excluded | No such action occurred. |

## Files changed

### Acquisition results, Storage convergence and URL safety

- `app/Enums/ExternalImageFailureReason.php`
- `app/Enums/MediaAcquisitionDisposition.php`
- `app/Support/Media/ExternalImageFailureMessage.php`
- `app/Support/Media/ExternalImageRejectedException.php`
- `app/Support/Media/ExternalImageUnavailableException.php`
- `app/Support/Media/MediaAcquisitionManager.php`
- `app/Support/Media/MediaAcquisitionResult.php`
- `app/Support/Media/MediaUploadBatchResult.php`
- `app/Support/Media/PinnedExternalImageTransport.php`
- `app/Support/Media/SafeExternalImageFetcher.php`
- `app/Support/Media/StorageImageCandidateBrowser.php`
- `config/media.php`

### Picker, Filament, Spotify and queued callers

- `app/Filament/Forms/Components/PathCuratorPicker.php`
- `app/Filament/Forms/SpotifyShowInput.php`
- `app/Filament/Resources/ContentItems/Schemas/EpisodeWorkspaceForm.php`
- `app/Filament/Resources/Media/Pages/CreateMedia.php`
- `app/Jobs/DownloadExternalContentGroupImage.php`
- `app/Jobs/DownloadExternalContentItemImage.php`
- `app/Livewire/Admin/MediaPickerPanel.php`
- `resources/views/filament/forms/components/media-picker-modal.blade.php`
  (deleted)
- `resources/views/filament/forms/components/path-curator-picker.blade.php`
- `resources/views/livewire/admin/media-picker-panel.blade.php`
- `lang/en/admin.php`
- `lang/he/admin.php`

### Bounded Filament patch refresh

- `composer.lock`
- `public/css/filament/filament/app.css`
- `public/js/filament/actions/actions.js`
- `public/js/filament/forms/components/markdown-editor.js`
- `public/js/filament/support/support.js`

### Tests

- `tests/Browser/MediaPickerBrowserTest.php`
- `tests/Feature/AppOwnedMediaPickerTest.php`
- `tests/Feature/AppOwnedMediaResourceTest.php`
- `tests/Feature/ExternalImageSecurityTest.php`
- `tests/Feature/MediaAcquisitionTest.php`
- `tests/Feature/SpotifyMediaAcquisitionTest.php`

### Documentation

- `docs/phase-02/current-project-state.md`
- `docs/phase-02/media-program-context.md`
- this handoff
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/research/media-program/02-media-program-master-plan.md`
- `docs/research/media-program/04-active-document-supersession-map.md`
- `docs/research/media-program/packages/03-post-acquisition-picker-ux-plan.md`
- `docs/research/media-program/packages/03-post-acquisition-picker-ux-research.md`

## Tests added or updated

- Picker single versus multiple upload configuration and forged-state refusal.
- Complete batch prevalidation and partial operational success/failure.
- Media Resource partial batch result handling.
- Storage reuse/register/copy disposition, 60-second default lease, lock
  contention and inside-lock concurrent-row recheck.
- Lazy Storage activation, state preservation, refresh count, stale errors and
  capacity preflight before permanent work.
- Active-only Upload/URL validation while malformed inactive state stays
  mounted and preserved.
- One decreasing URL transport allowance across redirects, post-DNS overrun
  checkpoint, deadline exhaustion after transport, every safe localized failure
  category and authorization propagation.
- Spotify authorization propagation and queued attachment-specific safe failure
  behavior.
- Direct single selection, multi explicit insertion, permanent/pending-save
  copy and nested modal configuration.
- Associated accessible names/states/errors, upload action guard, FilePond
  cancellation availability, offline Close, actual backdrop refusal and
  responsive source workspace markup.
- Real Hebrew RTL and English LTR owner-form browser flow, including explicit
  close/focus, second modal lifecycle, real Storage acquisition, permanent
  kernel before owner attachment and attachment after outer Save.
- Real nested Add/Replace Image action flow, including return to the parent
  action, focus restoration, cancel preserving the acquired library item
  without attachment, then reopen and Save attachment.
- Real picker-owned nested Edit action flow across two open/cancel cycles,
  proving two modal layers while open and one surviving picker afterward.
- Real direct and nested BrowserContext-offline Close, including full local
  close with zero Livewire request, child trap/lock release, correct nested
  parent-lock retention, parent/focus restoration, guarded online
  reconciliation, final parent-lock release, clean reopen and no JavaScript
  error.
- Delayed parent selection-return request proving the whole picker and Close
  remain guarded until field update and action unmount complete.

## Commands and results

| Command / check | Result |
|---|---|
| Mandatory repository orientation (`pwd`, Git root/status/log/show, lessons/state/ledger/handoffs/audits/plans/source/tests) | PASS; exact clean approved baseline, 0 behind / 19 ahead, no overlapping work. |
| Laravel Simplifier Stage 2 approval check | PASS; exact Audit ID, Option ID, five-task/forecast exception and exclusions matched. |
| Laravel Boost, official Laravel/Filament/Livewire/Alpine docs and release research | PASS; installed-version guidance and official Filament 5.7.2/5.7.3 release evidence recorded. |
| FilamentExamples multi-query/refinement search | PASS with search/snippet/path-only limitation; the Product Picker Modal schema-owned Livewire pattern was adopted. |
| Initial `php artisan test --compact tests/Feature/AppOwnedMediaPickerTest.php` | Expected RED: 29 passed, two failed and two errored of 33; missing single guard, lazy workspace and feedback behavior exposed. |
| Mini-task 2/3/4 focused red/green Pest iterations | Expected REDs exposed missing partial-result, Storage, deadline/category, source-state, offline Close and capacity behavior; corrected focused runs passed. |
| `php artisan test --compact tests/Feature/AppOwnedMediaPickerTest.php tests/Feature/MediaAcquisitionTest.php tests/Feature/ExternalImageSecurityTest.php` | PASS after independent-review corrections: 67 tests / 783 assertions. |
| `php artisan test --compact tests/Feature/AppOwnedMediaPickerTest.php` | PASS after nested picker/selected-state correction: 35 / 446. |
| Active-source-only validation regression | Expected RED: inactive malformed URL blocked Upload; final focused PASS in both Upload-to-URL directions. |
| URL authorization propagation regression | Expected RED: authorization was converted to a field error; final direct-action PASS after explicit rethrow. |
| Upload transfer action guard regression | Expected RED: acquisition action remained actionable during transfer; final focused browser PASS after an inert guard while transfer controls remain usable. |
| Batch programming-error boundary regression | Expected RED: `Throwable` became an ordinary partial result; final focused PASS after operational `Exception` narrowing. |
| Queued attachment and Spotify authorization regressions | Expected REDs: attachment errors used fetch copy and authorization could be swallowed; final focused PASS with attachment-specific copy and explicit authorization rethrows. |
| Initial browser command in the managed macOS sandbox | Infrastructure FAIL: Chromium `MachPortRendezvousServer ... Permission denied`; identical commands were retried outside the sandbox. |
| Escalated browser diagnostic iterations | Expected failures identified duplicate `action-modals.*` partial ownership, visually closed but mounted action state, focus restoration, selected-item rehydration, then intermittent Alpine `ReferenceError: state is not defined` during second-lifecycle Upload teardown. |
| Repeated escalated Hebrew browser command after lifecycle correction | PASS four consecutive intermediate lifecycle-stability repetitions: 1 test / 40 assertions each. |
| Earlier `php artisan test --compact tests/Browser/MediaPickerBrowserTest.php` | PASS at that implementation point: 2 / 80. |
| Nested outer Add/Replace Image browser regression | Initial sandbox infrastructure FAIL with the known Mach-port denial; identical escalated command PASS: 1 / 13. |
| Picker-owned nested Edit action browser regression | Expected RED exposed Livewire owner loss from the outer action's unnecessary form wrapper; `formWrapper(false)` final focused PASS: 1 / 8 across two child-modal cycles. |
| Intermediate picker feature/browser/widened regressions after nested-action correction | PASS: picker feature 37 / 464, browser 3 / 95 and widened five-file feature matrix 81 / 1,065. |
| Storage lease contract regression | Expected RED: configured default was 15 rather than 60 seconds; final focused PASS after the default/fallback correction. |
| Synchronous-DNS overrun checkpoint regression | PASS: mocked DNS exceeded the budget, transport was not called and the safe result was timed out. |
| Earlier widened five-file feature matrix | PASS at that implementation point: 79 / 1,047, then 86 / 1,109 after the final nested-modal, lock and DNS regressions. |
| PHP-review cleanup run of the same five-file feature matrix | Expected test-double FAIL after replacing constructor-free anonymous stubs: 84 passed / 2 errors / 1,112 assertions because exact once-only expectations observed four and two calls. Root-cause correction changed the stubs to at-least-once mocks. |
| Final pre-gate five-file feature matrix | PASS: 86 / 1,112. |
| Final pre-gate `php artisan test --compact tests/Browser/MediaPickerBrowserTest.php` | Managed-sandbox infrastructure FAIL with the known Chromium Mach-port denial; identical escalated command PASS: 4 / 105. |
| True-offline Close RED/diagnostic sequence | Initial real BrowserContext isolation RED proved Close could remain open offline (1 failed / 2 assertions). Successive lifecycle diagnostics localized intermittent handler-readiness/focus races and an Alpine `state` error caused by closing a just-reopened FileUpload subtree before initialization; implementation/test iterations reported failed counts of 1/5, 1/6, 1/2, 1/2, 1/8 and 1/8, followed by a diagnostic PASS at 1/9. |
| Stabilized direct true-offline Close repetitions | PASS three consecutive runs at 1 / 5 after waiting for Alpine/FileUpload readiness; no offline request, stale mounted action or JavaScript error. |
| Nested true-offline parent restoration sequence | Initial dataset run: 1 of 2 passed / 10 assertions and exposed asynchronous parent reactivation; focused nested diagnostics failed at 1 / 3, then passed at 1 / 7. The first combined rerun had 1 of 2 pass / 14 assertions with only the known Chromium ResizeObserver diagnostic; the identical filtered-noise run then passed twice at 2 / 14. |
| Final pre-gate five-file feature matrix after offline correction | PASS: 86 / 1,112. |
| Final pre-gate complete picker browser file after offline correction | PASS outside the macOS browser sandbox: 6 / 121. |
| Independent post-offline scope/lifecycle review | Found two bounded medium test/guard gaps: parent selection return outlived child loading, and offline evidence did not yet prove zero requests/trap/lock cleanup. No authorization, persistent-listener or scope issue. Both gaps were corrected inside Mini-task 5. |
| Delayed parent-handoff browser regression | First test-construction run FAIL with 0 assertions because the synthetic exposed-method arguments used the wrong nesting and produced `Unknown named parameter $mediaId`; corrected test then reached the intended RED at 1 assertion with Close unguarded. Final PASS: 1 / 6 with picker inert, native Close disabled, attempted Close a no-op, then successful selection/focus completion. |
| Strengthened true-offline lifecycle regression | PASS: 2 / 26 across direct and nested actions, proving zero offline Livewire requests, child focus-trap/scroll-lock release, correct remaining parent lock, guarded reconnect, clean reopen and final parent lock release. |
| Post-review five-file feature matrix | PASS: 86 / 1,112. |
| Post-review complete picker browser file | PASS outside the macOS browser sandbox: 7 / 139. |
| `composer update filament/filament filament/spatie-laravel-settings-plugin filament/spatie-laravel-tags-plugin --with-all-dependencies --no-interaction --dry-run` | PASS; bounded 13-package patch graph, no install/removal. |
| Same Composer command without `--dry-run` | PASS; Filament family 5.7.1 to 5.7.3, required power-joins 4.3.2 to 4.3.3, asset publication and package discovery completed, no advisory. |
| `composer validate --no-check-publish` | PASS with only the repository's existing exact-version warnings for Filament Shield and Spatie Permission. |
| `composer audit --locked --no-interaction` | PASS with no security advisory; Composer disclosed its unwritable user cache and continued without cache. |
| Installed/locked version check | Initial multi-package `composer show` syntax FAIL; corrected locked-list query confirmed Laravel 13.21.1, Filament 5.7.3, Livewire 4.3.3 and power-joins 4.3.3. |
| Changed-PHP `php -l` sweep | PASS: all 29 changed or new PHP/Blade PHP files had no syntax error. |
| PhpStorm WARNING-or-higher changed-PHP inspection | PASS after test-only inference cleanup: 0 remaining problems across all changed PHP files. After the operator corrected the MCP URL, this running task did not hot-load a native PhpStorm tool, but a fresh MCP handshake confirmed PhpStorm MCP 2026.2.0.1 and its `execute_tool` router; routed `get_inspections` reruns on the final `PathCuratorPicker.php`, browser test and `SpotifyMediaAcquisitionTest.php` also returned 0 problems. |
| HE/EN translation-key parity | Initial standalone helper attempt FAIL because Laravel's `collect()` helper was not bootstrapped; corrected pure-PHP recursive check PASS with no missing key in either locale. |
| Changed-PHP lint sweep after offline correction | Initial shell-loop attempt FAIL because the loop variable used zsh's special `path` array and cleared command lookup; corrected neutral variable run PASS on all 29 changed/new PHP and Blade PHP files. |
| Scope/name sweep and `git diff --check` | PASS: no migration, Composer/npm manifest or Package 4/5 implementation file; no whitespace error. |
| Iterative `npm run build` | PASS; existing optional Fontaine advisory only. |
| Iterative `vendor/bin/pint --test` | PASS before later nested-lifecycle corrections. |
| Iterative `vendor/bin/filacheck --dirty` | PASS with 0 issues before later nested-lifecycle corrections. |
| First canonical `vendor/bin/pint --test` attempt | FAIL on one unused test import in `SpotifyMediaAcquisitionTest.php`; bounded Pint formatting removed it before restarting the ordered gates. |
| First final requirements sweep and ordered gates | PASS: the 43-path candidate preserved all approved invariants and exclusions; Pint passed; FilaCheck passed with 0 issues; Vite built with only the existing optional Fontaine advisory; the full suite passed outside the macOS browser sandbox at 1,080 tests / 14,165 assertions. |
| Final exact-documentation-state ordered gates | PASS: Pint, FilaCheck with 0 issues, Vite with only the existing optional Fontaine advisory and the full 1,080-test / 14,165-assertion suite outside the macOS browser sandbox. This exact sequence ran after the completion-state documentation update and before staging. |

All HTTP-touching tests used committed fixtures and
`Http::preventStrayRequests()`. All persistence/filesystem/cache behavior used
the test database, fake storage and test cache locks.

## Drift, assumptions and deferred work

- The operator's Filament patch message was treated as an explicit,
  intentionally narrow amendment of the original dependency exclusion.
- No material baseline, security, migration, task-count or effort drift
  occurred.
- No Package 4 or Package 5 implementation entered the diff.
- No staging token, owner-save acquisition finalizer, cleanup sweeper, queue,
  inventory cache, relational uniqueness constraint or new lifecycle state was
  introduced.
- The Storage atomic lock is a 60-second default/capped cache lease, deliberately
  oversized for the bounded at-most-10-MiB read/validate/admit path. It cannot
  guarantee convergence if work outlives the lease; callers still wait at most
  three seconds, and relational uniqueness remains outside the approved scope.
- PHP's synchronous `dns_get_record()` cannot be preempted by the shared fetch
  budget. A DNS overrun is detected immediately afterward and prevents the next
  transport/redirect stage. The remaining allowance hard-bounds pinned HTTP
  transport; image decoding and Media admission occur after `fetch()`.
- Filament 5.7.2 improves modal focus restoration; it does not solve raw nested
  Livewire/action-partial ownership. The supported schema component and stable
  FileUpload lifecycle remain necessary. The display-only outer picker action
  also must disable its form wrapper so picker-owned action-modal forms are not
  nested.
- The browser suite filters only Chromium's known
  `ResizeObserver loop completed with undelivered notifications.` diagnostic;
  every other JavaScript error remains fatal.
- Composer completed without its unwritable user cache; installed packages,
  discovery, asset publication and advisory checks completed successfully.
- Package 4 requires a fresh audit after this closeout. Its earlier audit and
  option IDs remain superseded and unauthorizing.

## Local Front Check Report

Run only against a disposable test copy:

1. Open a podcast or episode Add/Replace Image action; expect one full-screen
   picker modal, the Gallery as the main workspace and Upload, URL and Storage
   as the side source navigation.
2. Switch repeatedly among Upload, URL and Storage; type an HTTPS URL before
   leaving URL and expect it preserved when you return.
3. Open Storage for the first time; expect candidates to load then, not when
   the picker first opens. Switch away and back; expect the same results until
   you click Refresh.
4. Start an upload; expect navigation, Gallery, search, footer and Close to
   guard competing work while FilePond's transfer cancellation remains usable.
5. Go offline; expect a visible offline message and disabled source work, but
   expect explicit Close to remain available.
6. Press Escape and click the backdrop; expect the picker to remain open.
   Click explicit Close; expect the picker to close and focus to return to the
   Add/Replace Image opener without page scrolling.
7. Reopen the picker, choose a valid Storage candidate in single mode and
   expect the field to update and the picker to close without a second Use
   Selected click.
8. Before saving the owner form, inspect the disposable database; expect the
   permanent Curator row, `MediaAsset` and provider binding, but no owner
   attachment. Save the owner form; expect the attachment to appear.
9. Cancel an owner form after a completed Upload, URL or Storage acquisition;
   expect the new library item to remain and only the pending owner attachment
   to be discarded.
10. Fill a multi picker to capacity and try URL or Storage acquisition; expect
    refusal before any permanent work.
11. Trigger blocked, missing, unavailable, timed-out and invalid-image URL test
    cases; expect distinct localized safe messages and no host, IP, URL, token,
    path or internal exception text.
12. Use keyboard navigation on Gallery cards; expect a strong focus ring,
    announced selection state/count and associated exact repair reason for
    blocked rows.
13. Check the picker at desktop and narrow mobile widths in Hebrew and English;
    expect RTL/LTR direction, stacked mobile sources and no horizontal
    overflow.
14. Open a Gallery item's Edit action, cancel it, and repeat; expect the child
    modal to open above the picker each time, the picker to remain after each
    cancellation and no browser-console error.
15. During a deliberately delayed single-choice return to the owner, expect
    the picker to become inert and Close to remain disabled until attachment
    state and focus handoff finish.
16. Open the picker directly and from a nested action, switch the browser
    offline, then click Close; expect immediate child focus-trap and scroll-lock
    cleanup with no network request. In the nested case, expect the parent
    action and its focus to return. Reconnect, reopen and close again; expect no
    stale modal or page scroll lock.

Do not perform these steps against local development or production without a
new exact environment-action approval.

## No-environment-mutation statement

No production or local-development database, storage, cache, migration,
acquisition, import, sanitation, deployment, process, branch, worktree or push
action occurred. The only network/package mutation was the explicitly
authorized bounded local Composer patch update.

## Git status before implementation commit

The exact pre-commit candidate contains 43 audited paths on `main`, which
remains 19 commits ahead of `origin/main` before this implementation commit.
All paths are attributable to the approved correction and bounded Filament
patch amendment; there is no unrelated drift.

## Commit hash

Pending
