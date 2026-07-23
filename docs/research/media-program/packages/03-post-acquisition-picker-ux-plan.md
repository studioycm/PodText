# Post-Package-3 Acquisition Picker UX Implementation Plan

> Execute this plan sequentially under Audit
> `LS-20260723-PODTEXT-MEDIA-P3-POST-ACQUISITION-UX-01`, Option
> `MEDIA-P3-POST-O3-IMMEDIATE-SOURCE-WORKSPACE`.

## Goal

Correct the completed Package 3 immediate-acquisition picker and add the O3
source workspace without changing persistence semantics. Upload, URL and
Storage remain immediate permanent library acquisitions. Owner attachment
remains pending until the outer owner form is saved.

## Fixed constraints

- Five mini-tasks, executed in order.
- Test-first application changes.
- No Package 4 or Package 5 implementation.
- No dependency change except the operator's later explicit bounded Filament
  5.7.1 to 5.7.3 patch-refresh amendment; no manifest, npm or unrelated
  dependency change.
- No migration or relational schema change.
- No live/local-development data, Storage or cache probe.
- No production/deployment/process action.
- No branch/worktree or push.
- Preserve user/concurrent changes; stop for overlapping application changes.
- Preserve Gallery inventory visibility and mutation-free existing selection.
- Preserve SSRF pinning, positive input validation, raster-byte preservation
  and SVG sanitation.

## Mini-task 1 — durable research, plan and active-route reconciliation

### Files

- Create
  `docs/research/media-program/packages/03-post-acquisition-picker-ux-research.md`.
- Create
  `docs/research/media-program/packages/03-post-acquisition-picker-ux-plan.md`.
- Update `docs/phase-02/media-program-context.md`.
- Update `docs/research/media-program/02-media-program-master-plan.md`.
- Update `docs/research/media-program/04-active-document-supersession-map.md`.
- Update `docs/phase-02/current-project-state.md`.
- Update
  `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`.

### Steps

1. Record exact baseline, Audit/Option IDs, permanence semantics and exclusions.
2. Preserve the original Package 3 handoff as historical evidence.
3. Mark original Package 3 complete and the post-P3 O3 correction active.
4. Mark the earlier Package 4 audit/options superseded and unauthorizing.
5. Record that a fresh Package 4 audit follows this completed correction.
6. Record installed-version and FilamentExamples evidence honestly.
7. Run `git diff --check` and inspect `git status --short`.

### Acceptance

- New research and plan are durable before application code.
- All five stale active-route locations agree.
- No history document is rewritten.
- No placeholder, pending decision or hidden future scope remains.

## Mini-task 2 — acquisition correctness and Storage convergence

### TDD files

- Update `tests/Feature/MediaAcquisitionTest.php`.
- Update `tests/Feature/AppOwnedMediaPickerTest.php`.
- Update `tests/Feature/AppOwnedMediaResourceTest.php`.

### Production files

- Add `app/Enums/MediaAcquisitionDisposition.php`.
- Add `app/Support/Media/MediaAcquisitionResult.php`.
- Add `app/Support/Media/MediaUploadBatchResult.php`.
- Update `app/Support/Media/MediaAcquisitionManager.php`.
- Update `app/Livewire/Admin/MediaPickerPanel.php`.
- Update `app/Filament/Resources/Media/Pages/CreateMedia.php`.
- Update `app/Filament/Resources/Media/Schemas/MediaForm.php` only if shared
  batch-result feedback requires form copy; retain its real multi-upload mode.
- Update HE/EN translations.

### Red tests

1. Assert picker single mode configures one file, while a multi-picker and
   Media Resource keep bounded batches.
2. Forge two uploads into single mode; assert status/error and zero Media,
   asset, binding and destination files.
3. Force the second operational admission to fail after the first succeeds;
   assert the first row/file/kernel remain, the remainder is reported, and the
   picker selects the success.
4. Exercise the same partial result through Media Resource; assert truthful
   warning feedback and no false all-success message.
5. Assert Storage outcomes distinguish reuse, register-in-place and copy.
6. Hold the exact Storage lock and assert safe contention failure without a
   duplicate row.
7. Assert the existing Media row is rechecked and reused inside the lock.
8. Assert the default Storage lock lease is 60 seconds.

Run the narrow red set and confirm failure for the intended missing behavior.

### Green implementation

1. Make picker `FileUpload::multiple()` and `maxFiles()` mode-aware.
2. Add a server-side single-mode count guard before validator/admission calls.
3. Keep all-file validation before the first permanent write.
4. Admit sequentially; on the first operational failure, report it and return:
   successful permanent results, one failed attempt and the unattempted count.
5. Re-throw authorization failures instead of converting them into UX errors.
6. Let callers select/report successes; convert zero-success operation failure
   to a safe field error.
7. Return acquisition disposition with Storage results.
8. Resolve the opaque Storage token to derive a hashed lock key, obtain a
   default/capped 60-second Laravel cache lease, then resolve and recheck inside
   it. Treat this as lease-bounded serialization, not relational uniqueness.
9. Keep register-in-place source files untouched on all failures.
10. Add partial-result notification handling to `CreateMedia`.

### Verification

Run:

```bash
php artisan test --compact \
  tests/Feature/MediaAcquisitionTest.php \
  tests/Feature/AppOwnedMediaPickerTest.php \
  tests/Feature/AppOwnedMediaResourceTest.php
```

Then run Pint on the changed PHP files and `vendor/bin/filacheck --dirty`.

## Mini-task 3 — shared URL budget and safe failure categories

### TDD files

- Update `tests/Feature/ExternalImageSecurityTest.php`.
- Update URL-error coverage in
  `tests/Feature/AppOwnedMediaPickerTest.php`.
- Update Spotify/external caller regressions only where safe failure mapping
  changes their observable copy.

### Production files

- Add `app/Enums/ExternalImageFailureReason.php`.
- Add `app/Support/Media/ExternalImageRejectedException.php`.
- Add `app/Support/Media/ExternalImageUnavailableException.php`.
- Add a narrow failure-to-translation mapper only if it removes repeated,
  inconsistent mappings across picker, queued jobs and Spotify forms.
- Update `app/Support/Media/SafeExternalImageFetcher.php`.
- Update `app/Support/Media/PinnedExternalImageTransport.php`.
- Update `config/media.php` with a fixed bounded interactive budget.
- Update the picker and shared external-image callers for safe category copy.
- Update HE/EN translations.

### Red tests

1. Redirect twice and assert transport calls receive a decreasing remaining
   allowance instead of a fresh 20 seconds.
2. Consume the test budget after a redirect and assert no later request occurs.
3. Let mocked synchronous DNS overrun the budget and assert the next checkpoint
   prevents transport; do not claim the DNS call itself is interrupted.
4. Cover blocked/invalid URL, not found/rejected response, temporary
   unavailability and deadline exhaustion.
5. Cover valid fetched bytes rejected by image admission as “invalid image.”
6. Cover unexpected admission failure as generic safe copy.
7. Assert UI/database notifications contain no URL, host, IP, opaque token,
   storage path or internal exception message.
8. Preserve every existing DNS, redirect, pinning, size and response test.

### Green implementation

1. Inject `CuratorImageUploadPolicy` into the fetcher instead of resolving it
   repeatedly through `app()`.
2. Create one monotonic shared fetch budget.
3. Check remaining time before/after blocking DNS and response stages. The
   synchronous system resolver is not interruptible here, so an overrun stops
   the next stage rather than preempting DNS.
4. Pass the remaining float allowance into transport `timeout()` and cap
   `connectTimeout()` to that remainder.
5. Preserve manual redirects and DNS revalidation on every hop.
6. Convert known rejection/transient/deadline cases to narrow categorized
   exceptions.
7. Keep queued transient behavior retryable; map only user-visible copy.
8. Do not add in-fetch retries.

### Verification

Run:

```bash
php artisan test --compact \
  tests/Feature/ExternalImageSecurityTest.php \
  tests/Feature/AppOwnedMediaPickerTest.php \
  tests/Feature/SpotifyMediaAcquisitionTest.php
```

Then run Pint on changed PHP files and `vendor/bin/filacheck --dirty`.

## Mini-task 4 — immediate source workspace and truthful interaction

### TDD files

- Expand `tests/Feature/AppOwnedMediaPickerTest.php`.
- Add narrow component tests for `PathCuratorPicker` dismissal configuration
  where current test helpers expose it.

### Production files

- Update `app/Livewire/Admin/MediaPickerPanel.php`.
- Update
  `resources/views/livewire/admin/media-picker-panel.blade.php`.
- Update
  `app/Filament/Forms/Components/PathCuratorPicker.php`.
- Delete the raw nested-Livewire
  `resources/views/filament/forms/components/media-picker-modal.blade.php`
  wrapper if installed-version research confirms that the action schema should
  own the child Livewire component directly.
- Update `app/Support/Media/StorageImageCandidateBrowser.php` only for a
  read-only configured-source status method.
- Update HE/EN translations.

### Red tests

1. Assert Storage candidate enumeration is not invoked during mount.
2. Activate Storage and assert one enumeration; switch away/back and assert no
   second enumeration; Refresh must enumerate once more.
3. Assert Upload/URL state survives source switching.
4. Assert malformed preserved state in an inactive Upload/URL source neither
   validates nor blocks a valid active-source acquisition.
5. Assert source-specific failures activate the correct source and render an
   associated error.
6. Assert unconfigured, no-candidate and search-empty Storage states differ.
7. Assert Upload/URL actions remain visible but disabled without usable input.
8. Assert a successful single acquisition dispatches the trusted ID
   immediately; multi mode retains explicit insertion.
9. Assert result copy distinguishes permanent admission, field choice and
   pending outer Save.
10. Assert the modal disables Escape/backdrop dismissal and exposes only an
    explicit close route.
11. Assert loading/offline directives and source-specific status copy render.
12. Assert the display-only outer picker action disables its form wrapper so
    picker-owned action modals do not create nested forms.
13. With the real browser context offline, assert explicit Close performs a
    local full modal close without a server request, restores a nested parent
    and focus, then reconciles safely on reconnect and can reopen cleanly.
14. Delay the parent field-update request after `insert-media`; assert the
    picker stays inert, Close stays natively disabled, a close attempt is a
    no-op, and normal selection/focus completion resumes after release.

### Green implementation

1. Add a locked server-owned active source and locked Storage-loaded state.
2. Validate all source changes against Upload/URL/Storage.
3. Keep Gallery outside the source navigation as the primary workspace.
4. Keep the permanence warning outside source content.
5. Load Storage on first activation and explicit Refresh only.
6. Preserve current candidates/search when switching tabs.
7. Route each error to its source and reset only that source’s stale error.
8. Keep action buttons visible with disabled/offline state.
9. After single acquisition, trust/select the result, dispatch to the
   schema-owned host component, await the field update, unmount only the picker
   action and restore focus to its stable opener.
10. Keep multi-mode acquisition selected in the picker until explicit insert.
11. Make the picker header/footer sticky inside its full-screen content.
12. Use Filament’s existing modal/focus lifecycle; add no parallel modal state.
13. Keep the Upload FileUpload subtree mounted at one stable location across
    source changes; hide/inert inactive source sections instead of tearing down
    FileUpload's entangled Alpine state during a child Livewire morph.
14. Dehydrate and validate only the active Upload or URL section so inactive
    preserved state remains non-blocking without removing either schema
    subtree.
15. Disable the outer action form wrapper; it has no submit action, while
    picker-owned Edit/rename/swap/delete actions retain their own modal forms.
16. Keep ordinary online Close server-authoritative. While genuinely offline,
    use Filament's full local close lifecycle, suppress only its automatic
    server unmount, reopen a parent action when nested, and reconcile through
    an exposed method that no-ops unless this picker remains the mounted top
    action.
17. Add a transient parent-scope `returningSelection` flag around the exposed
    field update and picker unmount. Let the nested workspace consume that
    flag for `inert`, native Close disabled state and `aria-busy`, covering the
    request plane after child loading has ended.

### Verification

Run:

```bash
php artisan test --compact tests/Feature/AppOwnedMediaPickerTest.php
```

Then run Pint on changed PHP files and `vendor/bin/filacheck --dirty`.

## Mini-task 5 — accessibility, browser integration and canonical closeout

### TDD and browser files

- Complete accessibility assertions in
  `tests/Feature/AppOwnedMediaPickerTest.php`.
- Add `tests/Browser/MediaPickerBrowserTest.php`.
- Update adjacent picker/resource tests only for real regressions.

### Production and documentation files

- Finish picker Blade semantics and HE/EN translations.
- Create
  `docs/phase-02/media-program-p3-post-acquisition-picker-ux-handoff.md`.
- Update `docs/phase-02/current-project-state.md`.
- Update
  `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`.
- Correct any active research/context status that changed during
  implementation.

### Red tests

1. Assert search inputs have explicit accessible labels.
2. Assert selectable cards expose accessible names and `aria-pressed`.
3. Assert blocked cards associate their exact reason.
4. Assert selected count is a polite atomic live region.
5. Assert focus-visible and coarse-pointer action classes are present.
6. In HE and EN real-browser cases:
   - open the picker from a real owner form;
   - confirm RTL/LTR direction and one modal owner;
   - switch source navigation and preserve focus/state;
   - confirm Storage was absent before activation and visible after;
   - confirm Escape does not dismiss the irreversible picker;
   - click the backdrop and confirm it does not dismiss the picker;
   - simulate an active FilePond transfer and confirm the acquisition action is
     inert while transfer cancellation remains usable;
   - confirm the unscoped loading ancestor also guards Close for every picker
     request;
   - take the real browser context offline, close both a direct picker and a
     picker nested in Add/Replace Image, assert zero Livewire requests plus
     correct focus-trap/scroll-lock release and parent-lock retention, confirm
     immediate parent/focus return, reconnect, then reopen and close the parent
     without stale action state, leaked scroll lock or JavaScript error;
   - delay the parent selection-return request and confirm the whole picker,
     including Close, remains guarded until parent update/unmount completes;
   - close explicitly and confirm focus returns to the opener;
   - reopen, perform a real Storage acquisition, and confirm the modal closes,
     the permanent Media/kernel exists before owner attachment, the field
     updates, and the attachment appears only after outer Save;
   - repeat through the real outer Add/Replace Image action, confirm the parent
     action remains open after child selection and focus returns to its picker
     opener, cancel once to prove the attachment stays pending while the
     acquisition remains permanent, then reopen and Save to attach it;
   - open and cancel a picker-owned Edit action twice; confirm two modal layers
     while open, one picker owner after each close and no JavaScript error;
   - confirm no horizontal overflow or JavaScript error.

### Green implementation

1. Add semantic state and labels without duplicating visible content.
2. Keep native button semantics and Filament source-navigation styling.
3. Use Alpine only for local focus-after-error presentation when required.
4. Keep item actions reachable on hover, focus-within and coarse pointers.
5. Fix only browser-proven focus/RTL/layout issues inside this package.

### Focused regression gates

Run focused tests serially, including:

```bash
php artisan test --compact \
  tests/Feature/MediaAcquisitionTest.php \
  tests/Feature/AppOwnedMediaPickerTest.php \
  tests/Feature/AppOwnedMediaResourceTest.php \
  tests/Feature/ExternalImageSecurityTest.php \
  tests/Feature/SpotifyMediaAcquisitionTest.php

php artisan test --compact tests/Browser/MediaPickerBrowserTest.php
```

If the macOS browser sandbox reports
`MachPortRendezvousServer ... Permission denied`, retry the identical browser
command outside that sandbox and record both runs.

## Requirements sweep

Before final gates, classify every approved requirement as:

- Implemented;
- Already existed;
- Deferred by approved boundary;
- Not applicable; or
- Blocked.

Also confirm:

- no Package 4 owner-image detail work;
- no Package 5 discovery/lifecycle work;
- no migration diff and no dependency diff beyond the operator-approved
  Filament 5.7.1 to 5.7.3 patch amendment;
- no live-data/runtime probe;
- no arbitrary client path;
- no hidden inventory;
- no altered owner attachment timing;
- no leaked external or Storage internals; and
- no concurrent/unattributed repository changes.

## Final gate and commit protocol

After the final application/document state is fixed, run exactly:

1. requirements and drift sweep;
2. `vendor/bin/pint --test`;
3. `vendor/bin/filacheck`;
4. `npm run build`;
5. full serial `php artisan test` last.

After any later file change, restart at Pint.

The committed handoff must contain:

- full requirement classification;
- files changed;
- tests added/updated;
- every command and result, including failures;
- ordered final gates;
- assumptions and deferrals;
- current Git status; and
- numbered imperative Local Front Check steps.

Canonical local closeout:

1. commit code, tests, research, active docs and handoff with
   `## Commit hash` pending;
2. immediately write that implementation hash into the handoff and ledger;
3. commit the hash-only documentation closeout as
   `docs: backfill media program p3 post acquisition ux hash`;
4. verify clean status; and
5. do not push.
