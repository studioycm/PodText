# Pest 5 Upgrade (Phase U) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking — but per the program's A11 ruling, checkbox state is not status; the record is the task reports and the commits they name.

**Written:** 2026-08-11 (Phase U session; research-verified against pestphp.com, Packagist, and GitHub the same day). **Status: EXECUTED 2026-08-12** — commits `5eaa7bb` (browser evidence-script fix) / `8617bb2` (composer batch) / `bf605db` (pest-rector report); gate 2,012/20,991/370.1s + pint + FilaCheck 35/35, counts identical to the pre-upgrade baseline. Execution record: `docs/research/test-suite-rethink-notes.md` § Phase U record. DP3 answered (Xdebug, installed), DP5 answered (record + defer). Per the A11 ruling, checkbox state below is not status — the record is authoritative. One planned assumption failed at execution and is corrected in the record: the Step-1-before-pin-edit dry-run cannot move pest (the pins gate it); execution reordered pins-then-dry-run.

> **Gate history, kept for the record.** 2026-08-11: "only research and plan" (operator, in-session). 2026-08-12 morning: orchestrator argued the founding COLLECT→PLAN→EXECUTE mandate was standing approval; operator ruled **no — stay plan-only**. 2026-08-12 later: F13 session relayed a conditional opening ("when F13 finishes with no blocker"); put to the operator directly, answered **yes — execute now**. The durable rule both rounds proved: a session-opening mandate stops being approval once narrowed in-session, and peer relays carry information, not authority — every opening here came from the operator's own answer. The rethink orchestrator read the Phase U session's founding mandate (COLLECT → PLAN → EXECUTE) plus spec decision-record #5 as meaning approval had already been given, and asked this session to proceed. The question went to the operator directly and the answer was **no: stay plan-only.** So: a session-opening mandate is not a standing execution approval once the operator has narrowed it in-session, and a peer session cannot lift an instruction the operator gave directly — it can supply information, not authority. Execution starts only on the operator's own explicit word, in a message from them.

**Goal:** Upgrade the suite from Pest 4.7.8 to Pest 5 (PHPUnit 13 underneath), prove the lane run-lock survived the bootstrapper change, shake down the browser plugin against the R4 trap inventory, and land the two new plugin measurements (pest-plugin-phpstan level-6 re-measure → DP5; pest-plugin-rector coding-style dry-run → DP4) plus the TIA prerequisite decision (DP3) — closing the test-suite-rethink program's final phase.

**Architecture:** One composer batch moves the whole pest line together (the plugins follow the major together — same partial-update trap family as the Boost/roster pin chain). The **first executable step after the composer moves is the mid-run two-process run-lock probe** — the `$GLOBALS` persistence in `tests/Pest.php` is bootstrapper-shape-dependent and a silent regression un-protects the lane for every concurrent run. Everything after that is measurement and reporting behind existing operator gates (DP3/DP4/DP5); no Rector writes, no TIA runs, no level-6 wiring happen in this plan.

**Tech Stack:** Laravel 13.24 / PHP 8.4.23 (Herd) · target: `pestphp/pest` 5.1.0 + `pest-plugin-browser` 5.0.1 + `pest-plugin-laravel` 5.0.1 + `pest-plugin-drift` 5.0.0 + new dev deps `pest-plugin-phpstan` 5.0.2, `pest-plugin-rector` 5.0.3 · PHPUnit 13.3.0 · Playwright 1.62.1 (already installed in `node_modules`) · MySQL 8.0.46 test lane @ 127.0.0.1:3307.

**Spec:** `docs/phase-02/test-suite-rethink-spec.md` Phase U (U1–U7) + `docs/phase-02/test-suite-rethink-implementation-plan.md` Task 11. Briefing: `docs/research/laraveldaily/pest5-notes.md` §2b. Blockers/pre-checks: `docs/phase-02/consolidated-open-findings.md` Tier 3 rows 3.2/3.3/3.9/3.9b/3.11/3.12 (read-only — that register is regenerate-wholesale, never hand-edit).

## Global Constraints

- Full gate before every code commit batch: `php -d memory_limit=2G vendor/bin/pest --compact`, `vendor/bin/pint --dirty --format agent`, `composer filacheck`. `npm run build` only if assets change (none planned). Docs-only commit batches may cite the most recent green full-gate run **if and only if no tracked code changed since it** (Phase R precedent); any code change re-runs the gate.
- **The gate baseline is a moving target — do not trust any number written here.** Last known green: **2,012 tests / 20,991 assertions** at `d84b1c7` (F13 fix, 2026-08-12; three consecutive proof runs 353.0s / 366.6s / 353.9s). That figure moved five times during this plan's authoring (`4da7542` → `8a6db8d` → `c1cbae9` → `1443b7d` → `d84b1c7`) because concurrent sessions land tests in this shared tree. **The authoritative pre-upgrade baseline is the freshest green full-gate run on `main` at execution time** — Task 0 Step 4 establishes it, and Task 4 compares against *that*, not against this paragraph. A test-count delta is only a finding if it survives that comparison.
- **Never** run `vendor/bin/filacheck` directly — it force-enables `--fix` under agents. `composer filacheck` only. `composer filacheck:fix` and `composer rector:fix` each need explicit operator approval.
- `-d memory_limit=2G` on every `vendor/bin/pest` invocation; the flag does not propagate through `php artisan test` — always invoke `vendor/bin/pest` directly.
- ONE pest run at a time machine-wide (machine-global flock at `~/.cache/podtext-test-lane/<sha1>.lock`); coordinate with other sessions via session messages before every full or browser run. Never wipe the lane, its lock, or its fingerprint to unblock anything.
- Commits to local `main` with **explicit pathspecs only** — never `git add -A`. No push: auto-deploy is OFF and push happens only on the operator's word. End every commit message with: `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`
- No test deletions without operator approval. Do not weaken any existing assertion.
- PHPStan protocol for touched app files: `php -d memory_limit=2G vendor/bin/phpstan analyse <file> -v` before and after; count must not rise (443-error backlog parked). The `-v` matters — the agent formatter truncates without it.
- Operator decision points (DP3 coverage driver, DP5 level-6 wiring, any DP4 Rector write) go to the operator via AskUserQuestion **when reached**, never assumed.
- Rector runs: serial (`->withoutParallel()` stays pinned) **and** cold cache (`composer rector -- --clear-cache`) for any number that feeds a decision (`.ai/rules/general.md`). `composer rector` is the only sanctioned dry-run entry point.
- Editing any doc's status claim triggers a whole-doc present-tense status sweep of that doc (`.ai/rules/docs.md`). `consolidated-open-findings.md` and `defect-cause-patterns.md` follow the custodian/evidence-gated-write protocol — do not hand-edit; report to the orchestrator session.
- Browser-test failures follow the stash-baseline attribution protocol (Task 3 Step 5 spells it out for the composer-lock case).

## Research facts the tasks stand on (all verified 2026-08-11 by this session)

- **Versions (Packagist, live):** pest **5.1.0** (2026-08-10; bumps PHPUnit to 13.3, TIA fixes incl. "livewire sfc on --tia"), browser **5.0.1**, laravel **5.0.1**, drift **5.0.0**, phpstan-plugin **5.0.2**, rector-plugin **5.0.3**, phpunit **13.3.0**. The 2026-08-07 research doc's 5.0.4/5.0.0 figures are already stale — pin `^5.1` for pest.
- **Isolated resolution dry-run (scratchpad copy of composer.json+lock, pins applied): SUCCEEDS — 5 installs, 39 updates, 2 removals, zero conflicts.** The feared blueprint/FilaCheck/spatie conflicts do not exist. Ride-alongs to surface: `laravel/framework` 13.24.0→13.25.0, `league/commonmark` 2.9.0→2.10.0, `laravel/pao` 1.1.3→**1.1.4**, `brianium/paratest` 7.20.0→7.24.0 (pest 5.1 requires ^7.24), `nesbot/carbon` 3.13.1→3.13.2, symfony 8.1.2→8.1.4 patches, full sebastian/phpunit family majors, removals: `composer/pcre`, `composer/xdebug-handler`.
- **`laravel/pao` conflict map (1.1.4):** `phpunit <12.5.23 || >=13.0.0 <13.1.7 || >=14`, `pest <4.6.3 || >=6.0.0` — phpunit 13.3.0 + pest 5.1.0 are both clear. pao's agent-mode `--output-format=json` argv injection continues to apply to Rector and friends — expect JSON where a human sees console output.
- **Upgrade guide (pestphp.com, live):** PHP ≥ 8.4 (have 8.4.23), all pest-maintained plugins to `^5.0` together, "no API-level breaking changes"; PHPUnit 13 is the real migration. R6's changelog memo (already in `test-suite-rethink-notes.md`) found **no entry** touching this suite's patterns; the one residual check is `grep -r "RunClassInSeparateProcess" tests` (expected empty).
- **Browser plugin 4.3.1→5.0.1 has ZERO release notes (re-verified: releases list empty), but the tag compare is small — 14 commits, 42 files (28 are snapshots).** Substantive: (a) timeout now sent in **metadata** too (Playwright ≥1.62 reads it there; params kept for older installs) and per-call numeric `timeout` params now override the global; (b) `LaravelHttpServer::asset()` sends JS files as a string instead of a stream — **fixes** >8 KB asset truncation (our in-process server serves public-disk subresources; this can only help); (c) Playwright pin 1.59.1→**1.62.1**; (d) `typeSlowly` made non-retryable + timeout-extended; (e) `setInputFiles` now ships file **contents** (base64) instead of paths; (f) `waitForText` / `Page::querySelector` gain `#[Deprecated]` (still functional); (g) `symfony/process` floor `^8.1.0` (installed: 8.1.0 ✓).
- **Our browser suite uses none of the changed APIs**: no PHP `waitForText`/`typeSlowly`/`setInputFiles`/`querySelector()` calls (all `querySelector` hits are in-page JS strings). `pest()->browser()->timeout(30000)` and `script()` are still the documented API.
- **Playwright axis pre-cleared:** `node_modules/playwright` is already **1.62.1** (package.json `^1.61.1`), and `~/Library/Caches/ms-playwright` holds chromium-1234. If the first browser run complains, `npx playwright install` refreshes builds — no npm version move needed.
- **pest-plugin-phpstan self-registers** via `phpstan/extension-installer` (`extra.phpstan.includes: [extension.neon]`) — this repo runs the installer (allow-listed in composer.json), so the real `vendor/bin/phpstan` picks it up with **no `phpstan.neon` edit**. The keynote's manual `includes:` block is only for non-installer setups. Rules ship stable identifiers (`pest.expectation.impossible`, `pest.test.staticClosure`, `pest.config.redundantLocalUse`, …) for precise `ignoreErrors` if ever needed (policy: empty until a rule is proven wrong here).
- **pest-plugin-rector set:** `Pest\Rector\Set\PestSetList::CODING_STYLE` (~55 rules, full inventory on pestphp.com/docs/rector), requires `rector/rector ^2.6.1` (installed: 2.6.1 ✓). `ChainExpectCallsRector` merges different-variable expectations with `->and()` by default — `merge_different_variables: false` opts out via `withConfiguredRule`.
- **No coverage driver** (re-verified today): neither xdebug nor pcov loaded, no .so in the extension dir, `Loaded Configuration File => (none)`, ini scan dir `~/Library/Application Support/Herd/config/php/84/`. TIA baseline recording is **impossible** until DP3 is decided — likely install-not-uncomment.
- **Machine-global state (3.9b, MECHANISM):** the TIA graph lives at `~/.pest/tia/<project-key>` keyed by normalized git remote — every worktree and concurrent session shares ONE graph, exactly like the S3 lane lock/fingerprint under `~/.cache/podtext-test-lane/`. Two sessions in one tree are the condition neither mechanism was designed against. Settle before enabling TIA, not after.
- **Run-lock geometry post-S3:** `tests/Pest.php` computes `TestLaneContract::runLockPath($host, $port, $database)` from raw pre-boot env (getenv-then-.env fallback), flocks it `LOCK_EX|LOCK_NB`, and persists the handle in `$GLOBALS['mysqlLaneRunLock']` because the include runs inside `BootFiles::load()` — a method scope (the GC trap, `538d8d9`). Pest 5 may reshape that bootstrapper; the probe in Task 2 is the only proof that matters.

---

### Task 0: Preflight and cross-session coordination

**Files:** none modified.

- [ ] **Step 1: Git preflight.** `git status --porcelain` and `git log --oneline -5`. Stop on unexpected app-code dirt unless the operator resolves it (tooling-quality rule). Other sessions' in-flight work in this shared tree is the expected hazard — identify it, don't touch it.
- [ ] **Step 2: Session coordination.** `mcp` session list; message any running session in this tree (send_message) announcing: Phase U will hold the lane for a full-gate run and will keep `composer.json`/`composer.lock` dirty between Task 1 and Task 4 — request clearance / quiet window. Wait for clearance before Task 1.
- [ ] **Step 3: PHPUnit 13 residual check** (R6's one leftover): `grep -rn "RunClassInSeparateProcess" tests` — expected empty. If not empty, stop and reassess against the PHPUnit 13 changelog before proceeding.
- [ ] **Step 4: Establish the pre-upgrade baseline — this step, not the Global Constraints paragraph, is its source of truth.** Record in the task report: `composer show --direct | grep pest`, `vendor/bin/pest --version`, `git log --oneline -1`, and the test/assertion counts from the freshest green full-gate run on `main`. If the most recent gate run predates any commit touching `app/` or `tests/`, it is stale — run the gate yourself (lane coordination from Step 2 covers it) and use those numbers. Last known value at plan time: 2,012 / 20,991 at `d84b1c7`. **Task 4 compares against the number recorded here.**

### Task 1: Composer moves — the whole pest line in one batch

**Files:**
- Modify: `composer.json` (require-dev block only)
- Modify: `composer.lock` (by composer)

**Interfaces:**
- Produces: pest 5.1.x vendor tree; `pest-plugin-phpstan` auto-registered into PHPStan via extension-installer (Task 5 consumes); `Pest\Rector\Set\PestSetList` available (Task 6 consumes).

- [ ] **Step 1: Dry-run on the real tree and surface the full ride-along list verbatim.**

```bash
cd /Users/studioycm/Herd/PodText
composer update --dry-run --with-all-dependencies 'pestphp/*' 'phpunit/*' brianium/paratest laravel/pao 2>&1 | grep -E "^Lock file|Upgrading|Locking|Removing|Problem"
```

Expected (from the 2026-08-11 isolated dry-run): 5 installs / 39 updates / 2 removals, incl. `laravel/framework` 13.24→13.25 and `league/commonmark` 2.9→2.10. **Surface this list to the operator in the task report verbatim.** If the resolution differs materially from the recorded expectation (new conflicts, packages not on the recorded list), STOP and report before editing anything. Optionally also run the narrower `--with-dependencies` (transitive-only) variant and record whether it resolves — if it does AND avoids the framework/commonmark ride-alongs, prefer it; record which form was chosen and why.

- [ ] **Step 2: Edit `composer.json`** — require-dev block, six lines:

```json
        "pestphp/pest": "^5.1",
        "pestphp/pest-plugin-browser": "^5.0",
        "pestphp/pest-plugin-drift": "^5.0",
        "pestphp/pest-plugin-laravel": "^5.0",
        "pestphp/pest-plugin-phpstan": "^5.0",
        "pestphp/pest-plugin-rector": "^5.0",
```

(replacing the four `^4.x` pest lines in place, keeping alphabetical sort — `composer.json` has `sort-packages: true`).

- [ ] **Step 3: Run the real update** with the exact form chosen in Step 1, e.g.:

```bash
composer update --with-all-dependencies 'pestphp/*' 'phpunit/*' brianium/paratest laravel/pao
```

Expected: clean install; `post-autoload-dump` runs `package:discover` + `filament:upgrade` (normal). Record the full "Lock file operations" block in the report.

- [ ] **Step 4: Verify the installed matrix.**

```bash
composer show --direct | grep -E "pest|rector|larastan|filacheck|blueprint|pao"
vendor/bin/pest --version
composer validate --no-check-all
```

Expected: pest 5.1.x, browser 5.0.x, laravel 5.0.x, drift 5.0.x, phpstan-plugin 5.0.x, rector-plugin 5.0.x; blueprint 2.2.0 / filacheck 1.2.5 / filacheck-pro 1.2.7 / larastan 3.10.0 **unchanged**.

- [ ] **Step 4b: Confirm the `__pestBrowser` touchpoint on the REAL installed artifact (unconditional — do not skip because Task 3 records the verdict).**

Task 3's "touchpoint unchanged" finding was established pre-upgrade against a *fetched* tag, and it became load-bearing for cross-session sequencing (the F13 session's bump-urgency premise was retired on it). This step re-derives it from the artifact this machine actually installed. The pin is a hash, not a snapshot file, so it survives scratchpad cleanup and a later session executing this plan:

```bash
shasum -a 256 vendor/pestphp/pest-plugin-browser/src/Playwright/InitScript.php
grep -n "__pestBrowser" vendor/pestphp/pest-plugin-browser/src/Playwright/Page.php
```

Expected: the hash is **`fb322d0c4c48cc03e987a244e06a0535dadbf6bb045da9f8bd53746d049eaff9`** — measured 2026-08-12 and identical across three sources (this machine's installed 4.3.1, upstream `v4.3.1`, upstream `v5.0.1`), which also proves the installed copy carries no local vendor drift. `Page.php`'s readers stay at `:459` (`consoleLogs`) and `:493` (`jsErrors`).

**If the hash differs:** the plugin moved the internal after all. The review set (post-F13-fix, verified 2026-08-12) is deliberately small: **`tests/Pest.php`** (the one mutating filter home — `knownResizeObserverArtifact()` / `stripKnownResizeObserverArtifacts()` / `assertNoUnexpectedJavaScriptErrors()` and their script block), **`CardTemplatePreviewBrowserTest`** (8 retained lines), **`JavaScriptErrorArtifactFilterBrowserTest`** (the filter's proof harness — it seeds the internal directly and would be the first thing a rename breaks), and the read at **`MediaPickerBrowserTest:644`**. Review all of it *before* Task 3's shakedown. Record the outcome either way in the execution log; "verified unchanged on the installed artifact" is the sentence that closes the loop.

- [ ] **Step 5: One micro-file smoke run (also checks the phpunit.xml schema).**

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/EnvironmentGuardsTest.php --compact
```

Expected: 4 passed. If PHPUnit 13 emits a "deprecated schema — migrate your XML configuration" warning, record it and run `vendor/bin/pest --migrate-configuration` **only after reading the diff it proposes** (`git diff phpunit.xml` before accepting); the `<groups><exclude>` block (rtl-board, compiled-sentinels) must survive byte-meaning-identical.

- [ ] **Step 6: NO COMMIT YET.** The composer batch commits only after Task 4's full gate. Note in the report that the tree is deliberately dirty (`composer.json`, `composer.lock`, possibly `phpunit.xml`) and which sessions were told.

### Task 2: Re-prove the run-lock lifetime (MANDATORY — first executable step after the composer moves)

**Files:** none modified (probe only; `tests/Pest.php` changes only if the probe fails).

**Why first:** the `$GLOBALS['mysqlLaneRunLock']` persistence exists because `tests/Pest.php` is included inside `BootFiles::load()` — a method scope; PHP GC would release the flock at bootstrap-end without it (`538d8d9`, `pest-lane-lock-gc-trap` memory). Pest 5 may have changed that bootstrapper shape. A silent lock regression un-protects the lane for every concurrent run — nothing else in this plan runs before this proof.

- [ ] **Step 1: Read the new bootstrapper.** `vendor/pestphp/pest/src/Bootstrappers/BootFiles.php` (or wherever Pest 5 loads `tests/Pest.php` — `grep -rn "Pest.php" vendor/pestphp/pest/src/Bootstrappers/` if the file moved). Record in the report: is the include still inside a method scope? Does anything now retain the file's locals? The probe below is the authority either way — this read is for the report's mechanism note, not a substitute.

- [ ] **Step 2: Run the mid-run two-process probe** (pattern: `.superpowers/sdd/alignment-program/final-review-fixes.md` §1, sleep raised to 20s, filter chosen so the suite runs well past it — `SettingsSp3aTest` measured ~70s in R1). Coordinate with other sessions first (Task 0 Step 2 clearance covers this). One Bash invocation:

```bash
cd /Users/studioycm/Herd/PodText
SCRATCH=<this session's scratchpad>
LOCK_PATH=$(php -r '
require "vendor/autoload.php";
$raw = function (string $key, string $default): string {
    $v = getenv($key);
    if ($v !== false && $v !== "") { return $v; }
    if (is_file(".env") && preg_match("/^".preg_quote($key, "/")."=(.*)$/m", (string) file_get_contents(".env"), $m) === 1) { return trim($m[1], "\"\x27 \r"); }
    return $default;
};
echo App\Support\Testing\TestLaneContract::runLockPath($raw("DB_TESTING_HOST", "127.0.0.1"), $raw("DB_TESTING_PORT", "3307"), $raw("DB_TESTING_DATABASE", ""));
')
echo "LOCK_PATH=$LOCK_PATH"
php -d memory_limit=2G vendor/bin/pest tests/Feature/SettingsSp3aTest.php --compact > "$SCRATCH/lockprobe-suite.log" 2>&1 &
SUITE_PID=$!
echo "SUITE_PID=$SUITE_PID"
sleep 20
echo "=== PROBE 1 (suite mid-run; expect false = lock held) ==="
php -r '$h = fopen($argv[1], "c+"); var_export(flock($h, LOCK_EX | LOCK_NB));' "$LOCK_PATH"; echo
kill -0 $SUITE_PID 2>/dev/null && echo alive || echo dead
echo "=== REFUSAL CHECK (expect exit 1 + lane message) ==="
php -d memory_limit=2G vendor/bin/pest tests/Feature/TestLaneGuardTest.php --compact; echo "refusal_exit=$?"
wait $SUITE_PID; echo "suite_exit=$?"
echo "=== PROBE 2 (suite done; expect true = released) ==="
php -r '$h = fopen($argv[1], "c+"); var_export(flock($h, LOCK_EX | LOCK_NB));' "$LOCK_PATH"; echo
tail -2 "$SCRATCH/lockprobe-suite.log"
```

Expected, all four together: PROBE 1 `false` **and** `alive` (lock genuinely held past bootstrap) · refusal run exit 1 with "Another pest run holds the MySQL lane." · suite_exit 0 · PROBE 2 `true` (no leak). Record the verbatim output in the report.

- [ ] **Step 3: If PROBE 1 prints `true` (lock regressed):** STOP the phase's forward motion. Diagnose with superpowers:systematic-debugging against the new bootstrapper shape from Step 1; the fix lives in `tests/Pest.php` (a persistence mechanism that survives the new scope — `$GLOBALS` today; whatever the new shape needs tomorrow), re-run this probe verbatim to green, and record fix + probe in the report before any other task proceeds. Also update the `pest-lane-lock-gc-trap` memory (it explicitly says the fix is v4-shape-dependent — re-verify under Pest 5).

### Task 3: Browser shakedown against the R4 trap inventory

**Files:** none planned (test fixes only if failures attribute to the upgrade).

**The R4 inventory this shakes down** (from `test-suite-rethink-notes.md` R4): (1) 54 `horizontal_overflow` reads across 4 files — since converted to stable reads by S1/S1b; (2) bare `getBoundingClientRect()` reads, partly converted; (3) per-file `waitFor` labelled polling helpers (correct pattern, all 10 matched files); (4) `_x_dataStack` boot probe in `DashboardSparklineBrowserTest` (correct, `closest('[x-data]')`); (5) the plugin bump itself. Plus the diff facts from this plan's research block: metadata-timeout, asset-serving change, new Playwright.

**F13 — FIXED at `d84b1c7` (2026-08-12); the old contingency is retired and its instinct RETARGETED.** The pre-existing flake (`MediaPickerUploadFocusReturnBrowserTest` failing ~1-in-2 on first full-suite attempts at bare `assertNoJavaScriptErrors()` on Chromium's ResizeObserver artifact, ~+30.5s signature) **can no longer occur**: the three bare sites now route through the strict counted filter that got one home in `tests/Pest.php` (`knownResizeObserverArtifact()` / `stripKnownResizeObserverArtifacts()` / `assertNoUnexpectedJavaScriptErrors()`), proven by `tests/Browser/JavaScriptErrorArtifactFilterBrowserTest.php` and three consecutive artifact-retry-free full runs. **Consequence for this shakedown: a `MediaPickerUploadFocusReturn` failure is now a REAL signal — it is NOT the known artifact, and it DOES go through Step 5's attribution protocol like any other failure.** Do not resurrect the re-run-once exemption from this plan's history.

**Helper-typing trap for any shakedown-written browser code** (F13 session, 2026-08-12; verified against `tests/Pest.php:297/:320`): `visit()` returns `PendingAwaitablePage`, not `AwaitableWebpage` — the latter only exists after a call like `->resize()`. A helper typed narrowly to `AwaitableWebpage` passes the entire existing suite (every current call site arrives post-resize) and then TypeErrors on the first plain-`visit()` caller. The shared helpers take the union `AwaitableWebpage|PendingAwaitablePage`; anything this phase writes does the same.

**Touchpoint stability — verified by this session, contradicting a briefing premise.** Test-side code reaches into the plugin's vendor internal `window.__pestBrowser.jsErrors`; since the F13 fix (`d84b1c7`) the geography is: the one mutating filter home in `tests/Pest.php` (three functions, one script block), `CardTemplatePreviewBrowserTest` (8 lines, retained by design — its shape embeds the artifact count in asserted measurement payloads per the mini2 plan), the filter's own proof harness `JavaScriptErrorArtifactFilterBrowserTest` (2 lines, seeds and reads the internal deliberately), and one read-only payload line at `MediaPickerBrowserTest:644`. A briefing had stated the plugin bump *moves* that touchpoint. **It does not, for 4.3.1 → 5.0.1:** `src/Playwright/InitScript.php`, which defines `window.__pestBrowser` and pushes `jsErrors`, is **byte-identical** between the installed 4.3.1 and v5.0.1 (diffed directly, not inferred), and `src/Playwright/Page.php`'s readers remain at `:459`/`:493` with identical expressions — that file's only 5.x hunks are a `use Deprecated;` import and the `querySelector()` docblock→attribute conversion. **Consequence for this task:** a `jsErrors`-shaped failure during the shakedown is NOT the plugin renaming an internal — look elsewhere (F13 first). **Verification strength (upgraded 2026-08-12 after a peer epistemics check):** the finding no longer rests on one fetched-tag diff. `InitScript.php` hashes **`fb322d0c4c48cc03e987a244e06a0535dadbf6bb045da9f8bd53746d049eaff9`** identically across three independent sources — this machine's installed 4.3.1, upstream `v4.3.1`, and upstream `v5.0.1` — so the installed copy is also proven free of local vendor drift. **Task 1 Step 4b re-derives that hash from the actually-installed 5.x artifact, unconditionally**, which is what closes the loop; the pre-upgrade check alone never can. **Caveat:** the fetched half is v5.0.1 specifically; if the browser plugin resolves past 5.0.1 at execution time, Step 4b's hash comparison still detects any change, and this two-command check re-establishes the upstream side:

```bash
diff vendor/pestphp/pest-plugin-browser/src/Playwright/InitScript.php <(curl -s "https://raw.githubusercontent.com/pestphp/pest-plugin-browser/<resolved-tag>/src/Playwright/InitScript.php")
grep -rn "__pestBrowser" vendor/pestphp/pest-plugin-browser/src/
```

- [ ] **Step 1: Playwright presence check.** `php -r '$p = json_decode(file_get_contents("node_modules/playwright/package.json"), true); echo $p["version"];'` — expect 1.62.1 (already installed). If the plugin errors `PlaywrightNotInstalledException`/`PlaywrightOutdatedException` at Step 2, run `npm install` then `npx playwright install` and record it.

- [ ] **Step 2: Browser suite alone, coordinated** (lane lock held for the duration):

```bash
php -d memory_limit=2G vendor/bin/pest tests/Browser --compact
```

Expected: all pass (12 files minus the two default-excluded groups). Record duration vs R1's 159.2s browser share.

- [ ] **Step 3: Known-flake file 3× consecutively** (the S1 stability standard):

```bash
for i in 1 2 3; do php -d memory_limit=2G vendor/bin/pest tests/Browser/CardTemplatePreviewBrowserTest.php --compact; done
```

Expected: 3× green.

- [ ] **Step 4: The two default-excluded groups, explicitly** (they never run in the default gate — phpunit.xml excludes `rtl-board` and `compiled-sentinels`; a plugin major is exactly when to look at them once):

```bash
php -d memory_limit=2G vendor/bin/pest tests/Browser/DashboardRtlBoardBrowserTest.php --group rtl-board --compact
php -d memory_limit=2G vendor/bin/pest tests/Feature/CompiledThemeSentinelTest.php --group compiled-sentinels --compact
```

Record results; failures here are informational (pre-existing exclusion reasons apply), not gate-blocking — attribute before judging.

- [ ] **Step 5: On any failure — stash-baseline attribution protocol, composer edition.** (The former F13 short-circuit is retired — the artifact filter got one strict home at `d84b1c7`, so a `MediaPickerUploadFocusReturn` failure is a real signal now and goes through this protocol like any other; see the F13 block above. The one artifact-shaped exception that remains legitimate: an assertion failure whose message shows the filter itself reporting an UNCLASSIFIED new browser message — that is the filter working, not flaking, and it gets root-caused, not re-run.) A browser failure after a plugin major is attributed, never assumed: (a) record the failure verbatim; (b) restore the pre-upgrade vendor: `git stash push composer.json composer.lock && composer install` (this is the "stash baseline"); (c) re-run the exact failing test 3×; (d) restore the upgrade: `git stash pop && composer install`; (e) verdict: fails-on-both = pre-existing (report, do not fix in this phase unless trivial); fails-only-on-5 = upgrade-caused (root-cause with superpowers:systematic-debugging; suspect list in order: timeout-metadata semantics, new Playwright/Chromium rendering timing, asset serving change). Time-box each chase per the TDD-bounded-attribution memory; test-side fixes ride as their own commits in Task 4 with the mechanism named in the message.

### Task 4: Full gate → the upgrade commit batch

**Files:**
- Commit: `composer.json`, `composer.lock` (+ `phpunit.xml` if migrated, + any Task 3 test fixes as separate commits)

- [ ] **Step 1: Full suite** (coordinated; the lane is held ~6 min):

```bash
php -d memory_limit=2G vendor/bin/pest --compact
```

Expected: **the exact counts Task 0 Step 4 recorded** (2,012 / 20,991 at `d84b1c7` was the last known value at plan time — but use Step 4's figure; any commit landed since then by another session moves it legitimately, and comparing against a stale plan number manufactures a phantom delta). Any real difference is explained test-by-test in the report — e.g. a pest 5 counting change — and never waved through. The three guard tests named by Task 11 — `LarastanCastResolutionGuardTest` (its isolated-config subprocess now also loads pest-plugin-phpstan via extension-installer — a known "worth a look" interaction), `FilacheckAgentModeGuardTest`, `RectorScriptContractTest` (cold-cache cost was ~28s; re-record under pest 5) — are inside this run; verify each shows green individually in the output. A browser failure here follows Task 3 Step 5’s attribution protocol (the F13 re-run-once exemption is retired); re-running a file in isolation is evidence-gathering within that protocol, never a substitute for it.

- [ ] **Step 2: Rest of the gate.**

```bash
vendor/bin/pint --dirty --format agent
composer filacheck
```

Expected: pint passed (composer.json edits don't touch PHP; any Task 3 fixes must pass), FilaCheck all checks green.

- [ ] **Step 3: Commit the batch** (explicit pathspecs; separate commits if Task 3 produced test fixes — each named by mechanism):

```bash
git add composer.json composer.lock
git commit -m "chore(pest): upgrade to Pest 5.1 — PHPUnit 13.3 underneath, plugin line moves together (Phase U)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

(Include `phpunit.xml` in the pathspec only if Step 1/Task 1 migrated it, and say so in the message body.)

- [ ] **Step 4: Notify the sessions from Task 0** that the tree is clean again.

### Task 5: pest-plugin-phpstan — wiring check + level-6 re-measure (→ DP5)

**Files:**
- Create (scratchpad only): `<scratchpad>/phpstan-level6-probe.neon`
- No repo changes. Output feeds the Task 8 report section.

**Interfaces:**
- Consumes: pest-plugin-phpstan 5.0.x installed by Task 1 (auto-registered via extension-installer).
- Produces: three measured error counts for DP5 (level 5 + tests / level 6 + tests / level 6 without tests) and the AskUserQuestion record.

- [ ] **Step 1: Prove auto-registration** (the Task 11 "check whether extension-installer auto-registers it first" — research says YES; prove it on this tree):

```bash
grep -n "pest-plugin-phpstan" vendor/phpstan/extension-installer/src/GeneratedConfig.php
```

Expected: one entry mapping `pestphp/pest-plugin-phpstan → extension.neon`. Record. (Consequence, worth stating in the report: NO `phpstan.neon` edit is needed for registration — the keynote's manual `includes:` block is for non-installer setups only.)

- [ ] **Step 2: Baseline sanity — current config, current paths** (tests/ NOT included): `php -d memory_limit=2G vendor/bin/phpstan analyse -v 2>&1 | tail -3` — expect the parked 443-error backlog count unchanged (the plugin adds tests-side knowledge; app-side counts must not move). If the count moved, STOP and diagnose before measuring anything else.

- [ ] **Step 3: Build the throwaway config** at `<scratchpad>/phpstan-level6-probe.neon` — a full copy of `phpstan.neon`'s parameters with **absolute paths** (relative paths resolve against the config file's own directory, which is the scratchpad — absolute paths are the point):

```neon
parameters:
    paths:
        - /Users/studioycm/Herd/PodText/app
        - /Users/studioycm/Herd/PodText/database
        - /Users/studioycm/Herd/PodText/routes
        - /Users/studioycm/Herd/PodText/tests
    stubFiles:
        - /Users/studioycm/Herd/PodText/phpstan/filament-macros.stub
    databaseMigrationsPath:
        - /Users/studioycm/Herd/PodText/database/migrations
    parseModelCastsMethod: true
    level: 6
    ignoreErrors: []
```

- [ ] **Step 4: Measure three counts** (run from the repo root so extension-installer discovery + larastan + the pest plugin all load; `-v` against agent truncation; each ~1–2 min):

```bash
php -d memory_limit=2G vendor/bin/phpstan analyse -c <scratchpad>/phpstan-level6-probe.neon --no-progress -v 2>&1 | tail -3   # (a) level 6, app+tests
# edit the copy: level: 5 → re-run → (b) level 5, app+tests (isolates "what does adding tests/ cost at today's level")
# edit the copy: level: 6, remove the tests path → re-run → (c) level 6, app-only (isolates "what does level 6 cost without tests")
```

Record all three against the documented ~426 estimate (`phpstan.neon:86`'s "Level 6 is bounded (~426 reports, mostly test closures)"). Spot-read ~10 of the tests-side reports and classify: genuine findings (impossible expectations, undefined methods) vs noise the plugin should have prevented.

- [ ] **Step 5: DP5 to the operator via AskUserQuestion** — options shaped as: (1) wire level 6 + tests/ into `phpstan.neon` now as its own follow-on batch; (2) wire tests/ at level 5 first, level 6 later; (3) record the measurement, defer wiring (default — DP5's own text says "after pest-plugin-phpstan lands + re-measure", which this completes). **Do not edit `phpstan.neon` in this phase regardless of answer** — wiring is its own future batch with its own gate; this task only measures and records the decision.

### Task 6: pest-plugin-rector — Pest coding-style dry-run report (→ DP4 gate, report only)

**Files:**
- Modify (temporarily, restored in-task): `rector.php`
- Create: `docs/research/rector-dry-run-reports/2026-08-DD-pest-coding-style.md`

**Interfaces:**
- Consumes: `Pest\Rector\Set\PestSetList::CODING_STYLE` (Task 1), the recorded serial/cold-cache rules (`.ai/rules/general.md`), the DP4 narrowing-then-restore procedure (rethink plan Task 9 gate note).

- [ ] **Step 1: Temporarily replace `rector.php`** with the tests-scoped probe config (the DP4-established narrow-then-restore pattern). Keep the EXACT two-path `withPHPStanConfigs` string and `withoutParallel()` — `RectorScriptContractTest` pins both, and the pest coding-style rules are syntactic (they do not need pest-plugin-phpstan's type info in Rector's container; do NOT add a third config path — Rector's container is the component that crashes on unexpected neon keys, §0 of the 2026-08-10 report):

```php
<?php

use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/tests'])
    ->withPHPStanConfigs([__DIR__.'/phpstan.neon', __DIR__.'/vendor/larastan/larastan/extension.neon'])
    ->withCache(__DIR__.'/storage/framework/cache/rector')
    ->withSets([PestSetList::CODING_STYLE])
    // Serial on purpose — see docs/research/rector-dry-run-reports/2026-08-10-laravel-code-quality.md §0c.
    ->withoutParallel();
```

Note: `RectorScriptContractTest` will FAIL while this replacement is in place (it also asserts the committed Laravel set shape) — do not run the suite mid-task; restore before any pest run.

- [ ] **Step 2: Cold-cache serial dry-run** (the only trustworthy mode on this codebase — measured 2026-08-10):

```bash
composer rector -- --clear-cache > <scratchpad>/rector-pest-coding-style.txt 2>&1; tail -5 <scratchpad>/rector-pest-coding-style.txt
```

Expected: JSON output (pao agent injection), `totals.changed_files` + `totals.errors` + per-file `applied_rectors`. Repeat once to confirm serial determinism (byte-identical totals; the 2026-08-10 report's standard).

- [ ] **Step 3: Write the report** `docs/research/rector-dry-run-reports/2026-08-DD-pest-coding-style.md`, same shape as the 2026-08-10 one: command + totals + determinism note, representative diffs, then a **per-rule verdict table** (rule → files → adopt/defer/reject → why, argued from THIS codebase's measured conventions). Known verdict-relevant house facts to check against: the suite's `expect()` style is already chain-heavy; `ChainExpectCallsRector`'s default `merge_different_variables: true` would merge unrelated-value expectations with `->and()` — evaluate against real diffs, note the opt-out; any rule touching guard tests' literal string assertions (e.g. `RectorScriptContractTest` asserting file contents) defaults to reject; browser-test JS strings are untouchable by Rector (PHP AST only) — confirm zero browser-file diffs or explain each.

- [ ] **Step 4: Restore `rector.php`** — `git checkout -- rector.php`; verify `git diff rector.php` is empty; then prove restoration:

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/RectorScriptContractTest.php --compact
```

Expected: PASS (cold-cache cost ~28s recorded in the 2026-08-10 report; re-record under pest 5).

- [ ] **Step 5: Commit the report** (docs-only batch; Task 4's gate is the covering green run — no tracked code changed since):

```bash
git add docs/research/rector-dry-run-reports/2026-08-DD-pest-coding-style.md
git commit -m "docs(rector): Pest coding-style set dry-run report — tests-side, serial, cold cache (Phase U / DP4 material)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

**No write pass in this phase.** The report is DP4 material; each write is its own future operator approval with the full DP4 procedure (cold cache → narrow → `composer rector:fix` → diff review → phpstan → pint → targeted pest → full gate → restore).

### Task 7: TIA prerequisites — DP3 to the operator, 3.9b on the record

**Files:** none (unless the operator approves the driver install — then follow-on steps below).

- [ ] **Step 1: Present DP3 via AskUserQuestion** with the measured facts: no coverage driver on Herd PHP 8.4.23 CLI (no .so either — install-not-uncomment; ini scan dir `~/Library/Application Support/Herd/config/php/84/`); TIA is the strongest pull (~361s suite and climbing as sessions add tests, exactly TIA's profile; pest 5.0.x–5.1.0 release notes are dominated by TIA fixes — the engine is actively stabilizing); **but 3.9b is a confirmed mechanism**: the TIA graph is machine-global (`~/.pest/tia/<project-key>`, keyed by git remote), shared by every concurrent session/worktree of this repo, and replay skips execution while the lane bootstrap assumes tests execute. Options: (1) install PCOV now (needs `pecl install pcov` or Herd-specific steps + ini edit — operator's machine, operator's call) and run the 3.9b contention experiment before trusting any baseline; (2) defer TIA entirely (default — DP3's standing default and the briefing's own ordering); (3) defer but record the intended config shape for adoption day.
- [ ] **Step 2: Whatever the answer, record the adoption-day config shape** in the Task 8 notes section (doc-verified 2026-08-11, `pestphp.com/docs/tia`): local-only (`pest()->tia()->locally()`), NO `->baselined()` (needs authenticated `gh` + CI artifacts; no CI exists), `--baseline` only prints the storage path (a briefing that says "run --baseline" does nothing), built-in Laravel/Livewire/Blade/browser watch defaults, graph auto-rebuilds on composer.lock/phpunit.xml/Vite-lockfile/PHP-version changes.
- [ ] **Step 3: If (and only if) DP3 = install now:** the driver install + `php -m` proof + a `--tia` baseline record + the two-sessions-one-worktree contention experiment (two concurrent filtered runs, one graph — observe selection interference) become a NEW task addendum brought back to the operator before execution — not improvised here. The lane run-lock serializes suite runs machine-wide, which already forces TIA sessions into sequence; the experiment's question is whether **sequential** runs from different sessions shape each other's selection through the shared graph.

### Task 8: State docs, memories, and phase close

**Files:**
- Modify: `docs/phase-02/current-project-state.md` (Tooling State §, Phase U completion block)
- Modify: `docs/research/laraveldaily/pest5-notes.md` (header staleness verdict; §2b checklist outcomes)
- Modify: `docs/research/pest5-rector-phpstan-notes.md` (header: upgrade executed; §2 version map superseded note)
- Modify: `docs/research/test-suite-rethink-notes.md` (append `## Phase U record (2026-08-DD)`; §3 TIA + §6 gate-language updates)
- Modify: `docs/phase-02/test-suite-rethink-implementation-plan.md` (Task 11 header: executed, pointer to this plan)
- Memory dir: `pest-5-upgrade-readiness.md`, `pest-lane-lock-gc-trap.md`, `dependency-pins-and-upgrades.md` + MEMORY.md hooks
- NOT touched: `docs/phase-02/consolidated-open-findings.md` (regenerate-wholesale register — rows 3.2/3.3/3.9b/3.11 are refreshed by the round-closing custodian, not hand-edited)

- [ ] **Step 1: Append the Phase U record** to `test-suite-rethink-notes.md`: composer resolution (verbatim ride-alongs), run-lock probe output, browser shakedown results + duration delta, full-gate numbers, the three Task 5 counts vs the ~426 estimate, the Task 6 totals + verdict summary, DP3/DP5 answers as given. Numbers measured, provenance named, gaps stated — the R-section house style.
- [ ] **Step 2: Doc updates above**, each under the docs.md rule: sweep the WHOLE doc's present-tense status claims while editing it — still-true → leave; falsified by this phase → fix citing the proving commit; unverifiable → register for the Documentation Architecture audit. Known falsified-by-this-phase claims to catch: "the Pest 4 → 5 upgrade is still unapproved and unstarted" (pest5-rector-phpstan-notes header), "a version this repo has *not yet* adopted" (pest5-notes header), §6's "upgrade is the rethink's final phase, behind its own gate" (now executed), the memory files' "we use Pest 4" standing rules. `current-project-state.md`: only the `## Tooling State` current-state block gets version refreshes (re-measure at edit time — A13's lesson); historical completion records stay untouched.
- [ ] **Step 3: Memory updates** (session memory dir): `pest-5-upgrade-readiness` → executed, outcome one-liner; `pest-lane-lock-gc-trap` → re-verified under Pest 5 (probe date + verdict, new bootstrapper shape note); `dependency-pins-and-upgrades` → the `^4.7` pin moved by this sanctioned session, new pin `^5.1`.
- [ ] **Step 4: Commit docs** (docs-only batch; cite the covering gate):

```bash
git add docs/phase-02/current-project-state.md docs/research/laraveldaily/pest5-notes.md docs/research/pest5-rector-phpstan-notes.md docs/research/test-suite-rethink-notes.md docs/phase-02/test-suite-rethink-implementation-plan.md
git commit -m "docs: Phase U record — Pest 5 landed, probes green, DP3/DP5 answered, staleness swept

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

- [ ] **Step 5: Close the phase.** Write `.superpowers/sdd/task-U-report.md` (gitignored ledger dir; commits list, gate record, probe transcripts, DP answers). Message the orchestrator session ("Triage post-alignment residuals; rethink the test suite") that Phase U is closed: commits, register rows now stale (3.2/3.3/3.9b/3.11 + Tier-4 research follow-ups), and the `silent-vendor-surface` reminder that the register refresh is the round-closing custodian's job. 🛑 Report to the operator: commits list, gate record, DP answers, anything deferred. Push only on their word.

---

## Self-review record (writing-plans checklist)

- **Spec coverage:** U1 → Task 1 · U2 → Task 2 · U3 → Task 3 · U4 → Task 5 · U5 → Task 6 · U6 → Task 7 · U7 → Tasks 4+8. Task 11's checklist items all mapped; its "dry-run first / surface constraints verbatim" is Task 1 Steps 1+6; the three named guard tests are Task 4 Step 1.
- **Deliberate deviations, disclosed:** (a) the full gate runs at Task 4 (after the shakedown) rather than at the very end, so the composer batch commits as early as a green gate allows and the shared tree's dirty window stays short — U7's "full gate + state-doc update" is satisfied by Task 4 + Task 8 together; (b) `pest` pins `^5.1` not `^5.0` (5.1.0 is current and its release notes are the TIA/PHPUnit-13.3 stabilization line); (c) Task 5 does NOT edit `phpstan.neon` even on a "wire it" answer — wiring is its own future batch (DP5's own text separates measure from wire).
- **Placeholder scan:** the one intentional unknown is `2026-08-DD` in report filenames/headers (execution date, unknowable at plan time) and `<scratchpad>` (session-specific path, listed in the executor's system prompt). No TBDs otherwise.
- **Type consistency:** `TestLaneContract::runLockPath(string $host, string $port, string $database): string` matches `app/Support/Testing/TestLaneContract.php:116`; `PestSetList::CODING_STYLE` matches pestphp.com/docs/rector; the probe's raw-env closure mirrors `tests/Pest.php:62-76`.
