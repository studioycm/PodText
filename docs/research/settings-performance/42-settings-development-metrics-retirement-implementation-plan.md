# Settings Development Metrics Retirement Implementation Plan

> Execute sequentially in the current checkout. Repository rules override any
> generic worktree or parallel-modifier workflow. Stop after Mini-task 1.

## Approved boundary

- Audit: `LS-20260721-SETTINGS-DEV-METRICS-03`
- Option: `SETTINGS-METRICS-O1-FULL-RETIREMENT`
- Scope: Mini-task 1 only
- Baseline: clean `main` at `0d7c931`
- Preserve: Filament 5.7.1 dependency refresh, Curator G1/LMTC, and every
  Mini-task 2 canary/report artifact

## Step 1 - Lock functional regressions before production removal

- [ ] Move the maintenance marker/copy assertion from the profiler test into
  functional maintenance coverage.
- [ ] Rewrite SP3A tests to preserve lifecycle output and request-scope behavior
  without the fixture or counter API.
- [ ] Rewrite SP3B coverage so legacy measurement parameters are discarded and
  cannot make a focused page read-only.
- [ ] Rewrite SP3C mixed coverage so retired flags cannot expose fixtures,
  response headers, or validation bypasses.
- [ ] Add malformed media-reference-key normalization/reason coverage.
- [ ] Add an import regression proving reference-key/path selections are
  expanded and applied atomically.
- [ ] Run the focused tests and record the expected RED failures caused by the
  still-present runtime metrics path.

## Step 2 - Remove runtime wiring and support files

- [ ] Remove the profiling environment entry, config block, and logging channel.
- [ ] Remove middleware registration and delete the middleware.
- [ ] Remove the profiler binding and unwrap the settings-saved listener without
  changing its order.
- [ ] Delete the profiler, SP3A fixture, SP3B fixture, and browser harness.
- [ ] Remove lifecycle counters/metrics while retaining scoped memoization.

## Step 3 - Unwrap production settings behavior

- [ ] Call validator defaults/group normalization directly and preserve invalid
  config accumulation.
- [ ] Schedule backup snapshots directly and preserve backup/prune ordering.
- [ ] Unwrap subject-page fill/save/update/form/schema operations while keeping
  authorization, hooks, transactions, fresh owned-root saves, and notifications.
- [ ] Unwrap all nine subject-schema arrays without changing their components.
- [ ] Remove legacy query forwarding and runtime fixture overlays.
- [ ] Remove card-template measurement state/branches and always use fresh
  stored settings plus normal validation.
- [ ] Remove reference-scanner timing/row-count DTO fields while preserving its
  query and blocker result.
- [ ] Remove only the bilingual measurement-error entries.

## Step 4 - Focused verification and scope sweep

- [ ] Run the rewritten SP3A/SP3B/SP3C and functional maintenance tests.
- [ ] Run validator/import identity-pair regressions.
- [ ] Run the focused Curator G1/LMTC regression set serially.
- [ ] Run the card-template browser test serially without editing it.
- [ ] Search for retired runtime symbols, flags, headers, config, and scripts.
- [ ] Confirm every deferred SP3C canary/report artifact remains present and
  mixed report blocks changed only to decouple retired runtime fields.
- [ ] Confirm `unmountAction(false)`, dependency manifests/locks, and Curator
  implementation remain unchanged.

## Step 5 - Closeout and canonical final gate

- [ ] Update current state, ledger, and the Mini-task 1 handoff with requirement
  classification, files/tests, every command result, and numbered local checks.
- [ ] Run requirements sweep.
- [ ] Run `vendor/bin/pint --test`.
- [ ] Run `vendor/bin/filacheck`.
- [ ] Run `npm run build`.
- [ ] Run the full `php artisan test` suite last and serially.
- [ ] If any file changes after a gate, restart at Pint.
- [ ] Commit implementation and documentation with the handoff hash pending.
- [ ] Immediately stamp the implementation hash in the handoff and ledger in a
  docs-only commit.
- [ ] Confirm clean status, do not push, and stop before Mini-task 2.
