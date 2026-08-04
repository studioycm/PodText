# Browser-suite timeout contention investigation

Investigation session, 2026-08-04, chip `task_d395ead7`. Charter: reproduce the
three residual timeout data points registered under `flake-label` in
`docs/research/defect-cause-patterns.md` under controlled induced concurrency
versus a quiesced baseline, classify the mechanism per data point, and rank
fixes. Report-only with respect to the ledger (orchestrator-owned); no app-code
changes.

**Verdict up front: all three data points are the same defect.** A concurrent
process that calls `Storage::fake('public')` **deletes** the shared fake-disk
root out from under an in-flight browser test. It is not port collision, not
tree churn, not CPU starvation, and not `test-residue`. Reproduced
deterministically (0/10 pass under induced load vs 23/24 quiesced) and a
per-process isolation fix was verified end-to-end (10/10 pass under the exact
interferer that produced 0/10).

## The three data points

- **DP1** — 1/30 Storage-listing timeout on `MediaPickerBrowserTest`
  `it('keeps the acquisition workspace accessible responsive and stateful')`:
  a wait on the Storage panel listing its file timed out, no JS errors
  (2026-08-01, ~100 instrumented runs; unclassified until now).
- **DP2** — `OwnerImageWorkspaceBrowserTest` "Hebrew RTL iPhone 15": one raw
  Playwright 30s timeout during a FULL run executed while another session's
  uncommitted work sat in the shared tree; standalone 12/12 after.
- **DP3** — the scan-scope session's first full run hit the Storage-listing
  timeout on BOTH datasets of the acquisition test; isolated 4/4 and a clean
  second full run; suspected confounding by concurrent mid-run tree churn.

## Mechanism (source-verified before any runs)

1. **`Storage::fake` purges a process-shared directory.**
   `Storage::fake($disk)` roots at `storage/framework/testing/disks/<disk>`
   and calls `cleanDirectory($root)` on every invocation
   (`vendor/laravel/framework/src/Illuminate/Support/Facades/Storage.php:103-115`).
   The only isolation is a `ParallelTesting::token()` suffix — and that token
   comes from `$_SERVER['TEST_TOKEN']`
   (`vendor/laravel/framework/src/Illuminate/Testing/ParallelTesting.php:297-302`),
   which nothing sets in this project: **paratest is not installed**
   (`composer.json`). So every pest process on the machine shares one root per
   disk, and each `Storage::fake('public')` call empties it for everyone.
   40 test files fake `public` (35 Feature + 5 Browser); `local` and
   `tmp-for-tests` share the same exposure.
2. **The browser suite reads that root live, per HTTP request.** The suite's
   web server is *in-process* — an Amp `SocketHttpServer` inside the pest
   process, handling requests through the same kernel
   (`vendor/pestphp/pest-plugin-browser/src/Drivers/LaravelHttpServer.php:102-108,219-313`).
   The Storage panel walks `Storage::disk('public')` on each render
   (`app/Support/Media/StorageImageCandidateBrowser.php:47`, source
   `laravel_public` rooted at `''`, `config/media.php:16-24`). A purge between
   fixture write and listing renders the panel empty, so the DOM wait spins
   out **with no JS errors** — precisely DP1/DP3's reported signature.
   `resolve()` throws "The Storage candidate is unavailable" if the purge lands
   between listing and acquire (`StorageImageCandidateBrowser.php:139-141`).
3. **Port collision is structurally ruled out.** Ports are OS-assigned
   ephemerals via bind-to-0 (`vendor/.../src/Support/Port.php:21-27`); there is
   no `SO_REUSEPORT` dual-bind, so a collision fails the bind loudly rather
   than producing a silent timeout. This corrects the
   `local_51518218`/`local_51579218` hypothesis on two counts: the mechanism
   is storage, not ports, and it is *cross-process* (concurrent sessions), not
   parallel workers within one run — which cannot occur here at all.
4. **Not `test-residue`.** Residue is stale files persisting on the **real**
   disk; this is live deletion of the **fake** root. They are different
   mechanisms with different cures, and the distinction is load-bearing (see
   Fix 0 below). Browser pages do fetch subresources from the real disk —
   `tests/Pest.php:52-55` pins the public URL to relative `/storage`, and the
   in-process server serves `public_path($path)` directly
   (`LaravelHttpServer.php:233-235`) — but the waits in these tests observe DOM
   text, not image bytes, so real-disk residue cannot produce these timeouts.
   Disk snapshots captured at every failure confirmed the fixture was present
   in the *real* tree while the *fake* root had been emptied.
5. **No other cross-process channel exists.** DB is sqlite `:memory:`,
   cache/session are array drivers, all force-enforced by `Tests\TestCase`.
   The shared mutable surface is exactly: the testing-disks tree, the real
   `storage/app/public`, built assets, compiled views, and machine resources.

## Method

Two target surfaces, each run standalone with `vendor/bin/pest --compact`:

- **acq** = `MediaPickerBrowserTest --filter="keeps.the.acquisition.workspace"`
  (1 test × 2 datasets) — DP1/DP3's surface.
- **owner** = `OwnerImageWorkspaceBrowserTest.php` (3 tests × 4 datasets = 12)
  — DP2's surface.

Machine-quiet gate before every batch: no pest/phpunit/vite/build processes
(long-lived idle MCP stdio servers excluded and named), and git status stable
across repeated 30s checks. Peer sessions were held by orchestrator order for
the collection window. Every run logged with outcome, duration, 1-minute load
average, failure text, and disk snapshots on failure. **123 runs total; every
run executed is counted below — no batch was dropped or truncated silently.**

All runs at HEAD `7f4cc76`; both target files byte-identical to session start
(md5-verified), so the hygiene session's concurrent fixture work
(`a14d50a`, `171deeb` — both `CardTemplatePreviewBrowserTest` only) cannot
have influenced any measurement.

## Run tables

### Baseline — quiesced

| Batch | Surface | N | Pass | Fail | Load | Notes |
|---|---|---|---|---|---|---|
| recon | acq | 1 | 1 | 0 | 2.9 | 2 tests / 90 assertions |
| recon | owner | 1 | 1 | 0 | 2.6 | 12 tests / 617 assertions |
| baseline | acq | 12 | 11 | 1 | 2.6-3.6 | run 11 failed, 57s — see residual below |
| baseline2 | acq | 12 | 12 | 0 | 2.8-6.9 | |
| baseline | owner | 12 | 12 | 0 | 3.0-4.8 | |

Quiesced acq: **24/25 pass**. Quiesced owner: **13/13 pass**. Durations are
bimodal on acq (6-28s: the locale re-serve branch at
`MediaPickerBrowserTest.php:91-94` adds ~10s when it fires) and tight on owner
(13-15s).

### Induced arms

| Arm | Interferer | Surface | N | Pass | Fail | Load | Failure signature |
|---|---|---|---|---|---|---|---|
| **A** | 2nd pest process looping ONE feature test that fakes `public` (`MediaPublicAltChainTest`) | acq | 10 | **0** | **10** | 2.2-4.0 | storage-listing wait, both datasets, ~83s/run |
| **D** | distilled purge loop (`rm` shared root contents @1s) | acq | 10 | **0** | **10** | 0.9-2.6 | identical to A |
| **D** | same | owner | 5 | **0** | **5** | 1.8-3.3 | 4 of 12 tests, ~165s/run — see concentration |
| A0 | 2nd pest process looping a test that never touches Storage (`PanelAuthHardeningTest`) | acq | 10 | 10 | 0 | 2.9-3.8 | — |
| B | scratch-file churn loop in repo root, 40 files @0.5s | acq | 10 | 10 | 0 | 2.1-3.0 | — |
| C | 8 CPU busy-loops + `dd` IO storm | acq | 10 | 0 | 10 | 6.0-**65.2** | storage-listing wait |
| C | same | owner | 5 | 3 | 2 | 15.4-25.6 | modal-close and action-return waits |
| C2 | 3 CPU busy-loops, no IO storm | acq | 10 | 9 | 1 | 4.8-12.2 | storage-listing wait |
| **E** | **purge loop + per-process `TEST_TOKEN`** | acq | 10 | **10** | **0** | 2.1-3.9 | — (fix verification) |
| **E** | same | owner | 5 | **5** | **0** | 1.4-2.1 | — (fix verification) |

Arm D owner was capped at N=5 by decision, not by accident: each failing run
costs ~165s and the result was already unanimous and structured. Stated here
rather than presented as a full-size batch.

## What the arms prove

**A vs A0 is the whole case.** Both add a second pest process at comparable
load (~3). The one whose test calls `Storage::fake('public')` takes the target
suite from 24/25 to **0/10**; the one that does not leaves it at **10/10**. The
variable is the fake, not the process, not the CPU, not the tree.

**Arm D reproduces every data point's exact signature.**

- DP1/DP3: the acq storage-listing wait, both datasets, no JS errors.
- **DP2 exactly**: `OwnerImageWorkspaceBrowserTest` "Hebrew RTL iPhone 15"
  failing with a raw `Timeout 30000ms exceeded.` — the plugin-level protocol
  timeout from `pest()->browser()->timeout(30000)` (`tests/Pest.php:45`,
  applied at `vendor/.../Playwright/Client.php:83`) — in 3 of 5 runs. DP2's
  "Playwright 30s" flavor never needed a separate starvation explanation; a
  purge produces it on that dataset.

**A pre-registered prediction held.** Before running, I predicted that under
H-purge the owner failures would concentrate in test 1 (the only storage-driven
test, `OwnerImageWorkspaceBrowserTest.php:630`, acquiring
`media-imports/browser-owner-admission.jpg` at lines 665-792) across datasets,
while tests 2-3 survived. Observed in all 5 runs: exactly 4 of 12 failing,
always test 1, all four datasets, other 8 green. Failure stages split as the
code predicts — 1× "waiting for the target Storage candidate row" (the
he-desktop admission branch), plus "proving pending owner choice" / the 30s
protocol timeout on the mobile and remaining datasets, where the selected
gallery Media rows point at files the purge removed.

**Blast radius is wider than the Storage panel.** The "pending owner choice"
failures show that *any* wait depending on faked-disk file presence is
vulnerable, not just the Storage listing.

**CPU starvation is real but not the historical cause.** Arm C fails, but only
at load 24-65 on 8 cores (3-8× oversubscription, `dd`-driven IO storms). At
load ~5 (arm C2) it is 9/10; at load ~3.3 with a genuine concurrent pest
process (arm A0) it is 10/10. Historical failures occurred at ordinary
two-session load, which this brackets as insufficient. Starvation is a
distinct, lower-ranked concern.

**Tree churn is exonerated.** Arm B is 10/10. The "another session's
uncommitted work in the tree" detail recorded with DP2 and DP3 was a correlate
of that session *running tests*, not of files changing.

## Fix verification (arm E)

`ParallelTesting::token()` reads `$_SERVER['TEST_TOKEN']`, and
`Storage::fake` consults it directly — while `inParallel()` additionally
requires `LARAVEL_PARALLEL_TESTING`, so a token alone flips **only** the
fake-disk root, touching no other parallel-testing behavior. Setting
`TEST_TOKEN` per run therefore roots fakes at
`storage/framework/testing/disks/public_test_<token>`.

Arm E ran the **same purge loop that produced 0/10 in arm D**, with a
per-process token: **acq 10/10 pass, owner 5/5 pass**. Honest mechanics: the
purge loop stayed active throughout but found the shared root empty (1 purge
event across arm E vs 69 across arm D) — because isolation moved the fixtures
out of the purged location. That is the cure working, described precisely
rather than as "purge pressure absorbed". Real concurrent sessions purge only
their own token's root, so this models reality correctly.

Verified as environment-only; **no repo file was modified by this
investigation.**

## Ranked fix proposals

**Fix 0 (do this regardless — it is a correction, not a change).** Do not let
the `test-residue` fixture-rename work be recorded as closing these timeouts.
Run-scoped fixture *names* cannot help: the purge deletes the directory
wholesale, whatever the files are called. Both patterns are real and both need
their own cure.

**Fix 1 — per-process fake-disk root isolation (recommended).** Set a
per-process `$_SERVER['TEST_TOKEN']` (PID-derived) in `Tests\TestCase` /
`tests/Pest.php` bootstrap, so concurrent sessions become structurally safe
instead of depending on hold discipline. Proven by arm E. Small and
framework-sanctioned (it is the seam Laravel already uses). Open decisions for
whoever lands it — deliberately not settled here: (a) always-on versus gated on
an env flag; (b) garbage collection, since per-PID roots accumulate under
`storage/framework/testing/disks/` and need cleanup (the residue already there
from past `--parallel` runs — `public_test_1..8`, `local_test_*`,
`tmp-for-tests_test_*` — shows the shape); (c) whether to isolate every disk or
only `public`/`local`/`tmp-for-tests`. **Required before landing: a full-suite
run with the token set** — arm E only exercised the two target surfaces (15
runs, all green).

**Fix 2 — cheap interim guard for humans.** Document in the handoff/gotchas
that concurrent pest processes across sessions corrupt browser runs, so
"re-run and it passed" is expected and meaningless; make the hold explicit in
multi-session rounds. Zero code, zero risk, but relies on discipline — this is
what the sessions have effectively been doing by accident.

**Fix 3 — scope the picker's disk exposure in tests.** Point the Storage
candidate source at a per-test dedicated disk so the listing does not read a
shared root at all. Narrower than Fix 1 and does not protect the gallery-based
waits that arm D also broke.

**Fix 4 — starvation headroom (low priority, separate concern).** The in-script
budgets (7s in `MediaPickerBrowserTest`, 10s in `OwnerImageWorkspaceBrowserTest`)
and the 30s protocol timeout only break at load ≥15-24. Do not raise budgets to
mask contention: with Fix 1 in place the remaining failures are genuine
starvation, and raising budgets would just lengthen the feedback loop.

## Open flags

1. **A residual quiesced intermittent exists and is NOT explained by this
   investigation.** Baseline acq run 11 failed on a fully quiet machine (1/25,
   ~4%) at the **post-upload settle wait**
   (`tests/Browser/MediaPickerBrowserTest.php:234`), not the storage-listing
   wait — the fixture was present in the disk snapshot and there were no JS
   errors. It predates both peer commits that landed in my window, so it is not
   peer contamination. That wait conjoins five conditions including
   `picker().contains(document.activeElement)`, and focus is exactly the kind of
   state the `single-read-race` family concerns. Candidate for the
   `single-read-race` sweep; it is a **second sighting-shaped observation**, and
   whether it trips that pattern's 2+ trigger is the orchestrator's call.
2. **DP1's original rate is now suspect as a measurement.** 1/30 was recorded
   on 2026-08-01 during a session that ran ~100 browser runs; if any other
   process faked `public` in that window, the rate reflects interference
   frequency, not a property of the test.
3. **The purge exposure is suite-wide, not confined to the two target
   surfaces.** Any browser test whose waits depend on faked-disk files is
   affected; 40 files fake `public`. A census of which browser waits depend on
   faked-disk presence would size the true blast radius.
4. **Compiled assets and views are an unexamined shared surface.** Ruled in as
   theoretically shared (a concurrent `npm run build` swaps `public/build`
   mid-run) but not tested — no build arm was run.
5. **Peer commits landed inside the collection window** (`171deeb`,
   `adaaa77`, at 17:44 and 17:46). Both are recorded above; `171deeb` touched
   only `CardTemplatePreviewBrowserTest` and the runs it bracketed (baseline
   owner 10-12) all passed, so per the sequencing rule no batch was redone.
   Batch-level timestamps are in the session's scratchpad logs if anyone wants
   to re-audit the correlation.

## Reproduction recipe

To re-demonstrate in one minute, from a quiet machine:

```bash
while :; do rm -rf storage/framework/testing/disks/public/*; sleep 1; done &
vendor/bin/pest tests/Browser/MediaPickerBrowserTest.php --filter="keeps.the.acquisition.workspace"
```

The same command with `TEST_TOKEN=iso$$` prefixed passes.
