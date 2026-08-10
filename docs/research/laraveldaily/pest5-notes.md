# Pest 5 release research — Nuno Maduro keynote + 6-day series + Povilas Korop review — notes

- **Sources** (8 videos, all captured in full):
  - [Nuno Maduro — Pest 5 keynote @ Laracon US 2026](https://www.youtube.com/watch?v=71bMyZcDlM4) (26:21)
  - [Povilas Korop / Laravel Daily — Pest 5 review with own-project demos](https://www.youtube.com/watch?v=Qr3L7Y1FXnU) (22:18)
  - Nuno's 6-day feature series (one/day, 2026-07-28 → 08-02): [day 1 TIA](https://www.youtube.com/watch?v=9vt8hkAWLRM),
    [day 2 agent](https://www.youtube.com/watch?v=Z6878g7OSMo), [day 3 evals](https://www.youtube.com/watch?v=rzqnm4Jpz3U),
    [day 4 PHPStan](https://www.youtube.com/watch?v=MiJj66nLwrw), [day 5 Rector](https://www.youtube.com/watch?v=oNU41o3pz5M),
    [day 6 sharding](https://www.youtube.com/watch?v=bBXsr1ix_dQ)
- **Captured**: 2026-08-10 — all eight full transcripts via pot-URL interception (README
  §3b-ter Method A, with the resource-buffer fix), archived together in
  `dev code/laraveldaily/raw/youtube-pest5-collection.md`.
- **Verified against**: installed `pestphp/pest 4.7.8` (released 2026-08-03),
  `pest-plugin-browser 4.3.1`, `pest-plugin-laravel 4.1.0`, PHP 8.4.23, this repo's
  `phpstan.neon` and `rector.php`. pestphp.com docs are the durable API authority for
  anything beyond what's verified here.
- **Staleness verdict**: **the freshest material in the whole research effort** — released
  the week of Laracon US 2026 (late July/early Aug), about a version this repo has *not yet*
  adopted. **Status change 2026-08-11: the operator green-lit a dedicated Pest 5 upgrade
  session.** This doc is that session's briefing — §2b is its checklist; §1a's flag list is
  doc-verified against pestphp.com, not just the videos.

**Headline: Pest 5 is six features, and one of them is already installed.** Time-balanced
sharding shipped into the Pest **4.7.x** line — the installed 4.7.8 contains `--shard`,
`--update-shards`, and `tests/.pest/shards.json` handling (measured in
`vendor/pestphp/pest/src/Plugins/Shard.php`). The other five (TIA, agent, evals, PHPStan,
Rector plugins) are v5-only — none exist in 4.7.8.

---

## 1. The six features, crunched

### 1a. TIA engine — Test Impact Analysis (the headline)

- A dependency graph from source files (PHP **and** Blade **and** JS/TS) → pages →
  controllers → tests. A change can only affect tests reachable from it; everything else is
  **fully replayed** from recorded state — same output, same assertion counts, **same
  coverage report** (`--coverage` still works under `--tia`).
- Numbers claimed: Laravel Cloud suite 200s → 5s locally (**39×**, Taylor Otwell tweeted
  it); Povilas measured his 500-test suite 59s → **0.75s** with no changes; one changed
  Blade file → 9 affected test files re-executed (95 tests run, 446 replayed).
- **Prerequisite: Xdebug or PCOV** — the recording phase needs a coverage driver.
  Povilas's Herd fix: enable the already-shipped `zend_extension` line in the PHP ini via
  Herd's config UI. **Measured here: the Herd CLI PHP 8.4 loads neither** — a real gap to
  close before any future TIA adoption.
- First run is **slower** than normal (it records); runs after a change re-record the
  affected slice (Povilas's changed-file run still took 48s for that reason). Steady-state
  no-change runs are near-instant.
- Flags and config, **doc-verified 2026-08-11 against pestphp.com/docs/tia** (supersedes
  the ASR-derived list): `--tia` (replay, or record if no baseline), `--no-tia`,
  `--tia --fresh` (discard graph, re-record), `--tia --refetch` (bypass the 24h CI-baseline
  cooldown), `--tia --filtered` (narrow PHPUnit to affected files; auto-disabled with
  explicit paths or `--coverage`), `--tia --locally`, `--tia --baselined` (fetch the shared
  CI baseline — **requires an authenticated `gh` CLI**); plain `--baseline` only **prints
  the TIA storage path** and exits. Env equivalents `PEST_TIA*=1`. `Pest.php` config:
  `pest()->tia()->locally()/->always()/->baselined()/->filtered()` and
  `->watch(['glob' => 'tests/target'])`; built-in defaults already cover Laravel, Livewire,
  Blade, and browser-asset patterns with no config. CI baseline = a dedicated workflow
  (`--parallel --tia --coverage --fresh` daily/on-push, upload the `--baseline` path as an
  artifact with `include-hidden-files: true`) — the only CI job where `--tia` belongs.
- **Where the graph lives (doc fact that sharpens pre-check (2) below):**
  `~/.pest/tia/<project-key>/`, machine-global, keyed by the **normalized git remote URL**
  (abs-path hash for remoteless repos) — so all worktrees and all concurrent sessions of
  this repo share **one** graph directory. Graph auto-rebuilds on `composer.lock`,
  `phpunit.xml*`, Vite/Node lockfile, or PHP-version changes; comment/whitespace-only
  edits are hash-normalized to zero reruns.
- **Positioning is explicit: TIA is for local, sharding is for CI** — a Pest core member
  states it directly (quoted in Povilas's video; ASR mangles the name, see §4).

### 1b. Agent plugin — browser testing driven by a coding agent

- Builds on the **Pest 4 browser plugin** (Playwright underneath — the same
  `pest-plugin-browser` this repo runs at 4.3.1). What it adds: a single
  `pest --agent` shell invocation that **arranges** (factories, `actingAs`), **acts** in
  the browser, does **front-end assertions**, and **back-end assertions** (auth state,
  queue, mail) — one MCP/tool call, not a click-by-click session.
- The pitch against Playwright MCP / Vercel agent-browser: those can act and assert in the
  browser but "none of them is good arranging a scenario … or performing back-end
  assertions". This is the Laravel-native difference.
- Distribution is via **Laravel Boost**: `php artisan boost:install` offers a
  `pest-plugin-agent` skill (~200 lines of markdown) that Claude Code loads when a prompt
  matches ("is the login form working?"). Device emulation included (iPhone 13 Pro in the
  keynote; Pixel 7 / iPhone 14 Pro in Povilas's demo); screenshots are taken during runs
  and kept **only on failure**.

### 1c. Evals plugin — LLM-as-judge tests for AI agents

- API shape: `expect(SupportAgent::class)->prompt('what is the refund policy')
  ->toBeCorrect('refunds are available within 30 days')` — the judge is an LLM comparing
  non-deterministic output against a reference, because static assertions can't (the
  answer "might be in another language").
- **Skipped by default** — slow and costs money; run with `--evals` (CI-only is the
  suggested pattern). Assertions heard: `toBeCorrect`, `toContain`/`notToContain`,
  `toMatch`, `score`, `toBeRelevant`, `toBeSafe` (NSFW/toxicity); sampling via
  `repeat(3)` for non-determinism; `pest()->evals()->judgeUsing(...)` in `Pest.php` for a
  custom judge client. Includes prompt-injection evals (the "ignore all previous
  instructions" hijack test asserting the agent stays on-topic).
- Cost reality from Povilas's live run: smartest-model judging = 37s and ~5¢/prompt;
  cheapest models (Haiku / GPT nano tier) = 1–2s at ~0¢. Pairs naturally with the Laravel
  AI SDK but is not limited to it.

### 1d. PHPStan plugin — type-safe tests

- First-party plugin that teaches PHPStan/larastan the Pest closure world: the `$this`
  test-case context (the historical flood of false positives — keynote demo went 50+
  errors → 2 real ones), `beforeEach` property type inference, and Pest-specific
  diagnostics: **impossible expectation chains** (`toBeInt` then `toBeString` on the same
  value), undefined test-case methods, `describe` blocks without tests, duplicate test
  descriptions, empty closures.
- Why it matters now: Laravel starter kits ship larastan at level 7 **without `tests/` in
  the analysed paths** — precisely because analysing Pest tests was noise before.
  **PodText mirrors that omission**: `phpstan.neon` paths are `app`, `database`, `routes`,
  and line 86 already records the intent — "Path upward: 5 → add tests/ with the Pest
  extensions → 6 → 7 → 8". This plugin *is* that Pest extension; the pre-planned path now
  has a named vehicle (v5-gated).

### 1e. Rector plugin — idiomatic Pest rewrites

- A Pest set list for Rector's coding-style config. Rewrites heard/shown:
  `expect(strlen($name))->toBe(7)` → `expect($name)->toHaveLength(7)`;
  `expect(count($roles))->toBe(3)` → `toHaveCount(3)`; separate expectations chained with
  `->and(...)`; `foreach` assertion loops → `each->toBeString()`. Also auto-upgrades old
  Pest syntax across major versions (v2→v3→v4 idioms).
- Explicit framing: AI writes *correct but non-idiomatic* Pest; Rector restyles it — "you
  can now trust Rector PHP to style your tests. They might be written by AI."
- Scope control is the same lesson as [rector-video-notes.md](rector-video-notes.md):
  `withPaths(['tests'])` to keep a style sweep surgical. This repo's `rector.php` is
  dry-run-locked and Laravel-layer-only; the Pest set list is a **future add at v5 time**,
  owned by the Rector session's discipline, not something to bolt on now.

### 1f. Time-balanced sharding — the CI feature (already installed)

- History as Nuno tells it: v1 single job (~42 min) → v2 `--parallel` (2 GitHub cores,
  ~half) → v4 sharding (split the suite across N workflows; CI time = slowest shard) → v5
  balances shards **by recorded per-test timing** instead of file count, so all shards
  take ~the same time (7 min → 5 min in the demo; Laravel Cloud: ~20 shards × 2 min).
- Mechanics: run `--update-shards` periodically → writes timings to
  **`tests/.pest/shards.json`, which you commit**; CI shards read it to pick tests; Pest
  warns when the file is stale ("Run [--update-shards] to update it"). No plugin needed.
- **Verified in the installed tree**: `Shard.php` in pest 4.7.8 implements all of this —
  the constant `--shard`, the `--update-shards` argument, the outdated-file WARN string
  naming `tests/.pest/shards.json`. So this v5 keynote feature is usable on Pest 4 today.

### 1g. General v5 facts

- **PHP 8.4 minimum** (installed PHP is 8.4.23 — no blocker on that axis).
- New expectations: `toBeEmail`, `toBeIpAddress`, and a URL/domain one (Povilas shows
  `toBeEmail` on `user->email`, an IP check on reCAPTCHA's `remote_ip`). **Absent in
  4.7.8** — `Mixins/Expectation.php` has `toBeUrl` but none of the new ones.
- Full rebrand: new pestphp.com site + docs. Nuno's positioning quote: Pest 5 is "about
  50% agents, 50% humans" — features *for testing agents* (evals) and features *so agents
  can use Pest well* (agent plugin skill, PHPStan/Rector cleanliness).
- Credits: a Pest core member helped on the PHPStan + Rector plugins and is the source of
  the "TIA local, sharding CI" guidance; another contributor on evals; another on the
  website. All three names are ASR-mangled in captions (§4) — left unnamed rather than
  misspelled.

## 2. What this means for PodText (stay on Pest 4 — intelligence only)

| v5 feature | local relevance |
| --- | --- |
| TIA | The strongest future pull: the suite runs ~600s on the 3307 MySQL lane — exactly TIA's target profile. Two pre-adoption checks are already known: (1) **no Xdebug/PCOV in the Herd CLI 8.4 ini today** (measured); (2) TIA replay vs the machine-global lane-lock/fingerprint bootstrap in `Pest.php` needs a compatibility look — the lane infra assumes tests execute; replayed runs may skip lane setup expectations (same file, same scope subtleties as the lane-lock GC trap). That bootstrap moved *while this doc was being written*: Phase S S3 commits `89a2ee1` (machine-global lane lock + fingerprint) and `810f6f2` (HOME-anchored `~/.cache/podtext-test-lane/` root) — whoever picks up check (2) reads those two commits first. Registered as item 3.9b in the open-findings inventory. |
| Time-balanced sharding | Installed already (4.7.8) but idle: it's a CI horizontal-scaling feature and this project's suite runs locally/on-lane, not on sharded CI workflows. Nothing to do; know it exists. |
| PHPStan plugin | Slots into a pre-existing plan verbatim — `phpstan.neon:86` already names "add tests/ with the Pest extensions" as the level-5→8 path. When the repo reaches Pest 5, this plugin is that step's enabler. |
| Rector plugin | `rector.php` exists, dry-run-locked, Laravel-layer-only. The Pest set list + `withPaths(['tests'])` is a clean future sweep — same one-concern-per-run discipline as [rector-video-notes.md](rector-video-notes.md) §2. |
| Agent plugin | The repo already has the two prerequisites (browser plugin 4.3.1 + Boost) — at v5 time it's a `boost:install` skill toggle. Note the house TDD rule still governs: agent-arranged browser checks are a convenience layer, not a substitute for the committed feature tests. |
| Evals | Not relevant — PodText has no in-app AI agents. File under "if AI SDK features ever land" (the AI-SDK course in the backlog is the companion read). |
| Upgrade itself | `composer.json` pins `"pestphp/pest": "^4.7"`. Official upgrade guide (fetched 2026-08-11): PHP ≥ 8.4 (met), **built on PHPUnit 13** ("any notable changes … might have an impact" — the changelog read is the one unbounded item, already flagged as ungated prep in `test-suite-rethink-notes.md` §6), and **all pest-maintained plugins move to `^5.0` together** — here that's `pest-plugin-browser` 4.3.1, `pest-plugin-laravel` 4.1.0, `pest-plugin-drift` 4.1.0. The guide's "~2 minutes" claim covers dependency edits only, not a 1850-test suite with custom lane bootstrap. **Now green-lit for a dedicated session** — §2b. |

**No proposals.** Everything actionable is gated on a Pest 5 adoption decision that is
explicitly not being made now; the two TIA pre-checks above are recorded so the future
session starts warm.

### 2b. Dedicated upgrade-session checklist (added 2026-08-11 on the operator's green light)

Ordered so each step gates the next; sources in parentheses.

1. **PHPUnit 13 changelog read first** — the only unbounded-risk item (upgrade guide;
   rethink-notes §6 already lists it as ungated prep).
2. **Bump as one move**: `pest` + `pest-plugin-browser` + `pest-plugin-laravel` +
   `pest-plugin-drift` all to `^5.0` (upgrade guide: plugins follow the major together —
   same partial-update trap family as the Boost/roster pin chain).
3. **Re-probe the `Pest.php` bootstrap shape** before trusting the lane infra: the
   lane-lock `$GLOBALS` fix is bootstrapper-shape-dependent (`BootFiles::load()` method
   scope — the GC trap); v5 may have changed that shape. Mid-run lock probe, not
   boot-time-only.
4. **Lane-bootstrap × TIA compatibility** (pre-check (2) above): read `89a2ee1` +
   `810f6f2` first; then note the graph is machine-global at `~/.pest/tia/<project-key>`
   keyed by git remote — **all sessions/worktrees share one graph**, so two concurrent
   suite runs are a new contention surface next to the lane lock itself (registered as
   inventory item 3.9b).
5. **Install a coverage driver** before any TIA run: Herd ships Xdebug disabled — enable
   the `zend_extension` line in the **8.4** ini (Povilas's walkthrough used 8.5; measured
   here: neither xdebug nor pcov loaded, and rethink-notes adds no .so exists in the
   extension dir, so this may be install-not-uncomment).
6. **TIA config shape for this repo**: local-only (`pest()->tia()->locally()`), no
   `->baselined()` (needs `gh` auth + CI artifacts; there is no CI), built-in
   Laravel/Livewire/Blade/browser watch defaults should cover the app — verify browser
   tests' asset tracking against the Vite build before relying on replay.
7. **Then the gated follow-ons** in rethink-notes §6 order: `pest-plugin-phpstan`
   (the `phpstan.neon:86` "add tests/" path), `pest-plugin-rector` (own run, dry-run
   discipline per `rector-video-notes.md`), `--agent` (boost:install skill toggle),
   new expectations (`toBeEmail` etc. become available to tests).
8. **Full gate + doc sweep**: `php artisan test`, pint, `composer filacheck`, build; then
   update the docs this upgrade stales — this file's header, rethink-notes §3/§6 gate
   language, and the memory files' "we use Pest 4" standing rule.

### Candidate cause-pattern (contributed to the ledger owner, per its protocol)

Per `docs/research/defect-cause-patterns.md`'s contribution flow — side sessions register
candidates with evidence in their own reports; the orchestrator merges:

- **silent-vendor-surface** · a routine dependency bump delivers a new behavioral surface
  nobody decided to adopt, so capability and decision drift apart · **evidence**: commit
  `303beab` ("bump the stack", 2026-08-08) took pest to 4.7.8, which ships Pest 5's
  time-balanced sharding (`--update-shards`, a `tests/.pest/shards.json` staleness WARN) —
  discovered 2026-08-10 only because this research session diffed vendor source against
  keynote claims; meanwhile an active planning doc still classified the feature as
  v5-gated · **POTENTIAL**, not ACTUAL — no defect yet, but the WARN path and the stale
  doc claim are both live surfaces · **where else to look**: grouped-bump commits'
  composer.lock diffs for minor-version jumps of test-infra/tooling packages; vendor
  CHANGELOGs for features backported ahead of a major.

## 3. Feature ↔ video map (where to re-read what)

| feature | deep dive | also covered |
| --- | --- | --- |
| TIA | day 1 + keynote 18:48–24:30 | Povilas 1:02–6:03 (own-project measurements, Herd/Xdebug setup) |
| agent | day 2 + keynote 1:15–6:21 | Povilas 6:03–10:59 (boost:install skill walkthrough, device demo) |
| evals | day 3 + keynote 10:54–15:43 | Povilas 10:59–17:38 (cost/model measurements — the best practical pass) |
| PHPStan | day 4 + keynote 6:21–8:18 | Povilas 18:10–19:16 (starter-kit significance) |
| Rector | day 5 + keynote 8:55–10:22 | Povilas 19:16–19:47 ("syntax sugar" counter-take) |
| sharding | day 6 + keynote 16:17–18:48 | Povilas 19:47–20:47 |

## 4. ASR caveats (do not quote these spellings)

Captions render: *"Pest 3 5"/"past V5"* = Pest v5; *"test week"* = test suite; *"Pika
off"* = PCOV; *"Lotta Con US forever"* = an undefined-method demo string (unrecoverable);
*"e-test"* = `it`/`test`; the contributor names ("Pooya Parsa"/"Punya Pal", "push back",
"Nuno Gecha") are unverified renderings — check pestphp.com credits before naming anyone.
Per the house rule, every API name written above was either verified against installed
vendor source (sharding, `toBeUrl`-vs-new-expectations, plugin absence in v4) or is
attributed to the videos/docs as v5 claims, not asserted as local fact.

## 5. Sources

- All eight transcripts + capture metadata:
  `dev code/laraveldaily/raw/youtube-pest5-collection.md`.
- Official docs (fetched 2026-08-11, the API authority over the transcripts):
  [pestphp.com/docs/upgrade-guide](https://pestphp.com/docs/upgrade-guide),
  [pestphp.com/docs/tia](https://pestphp.com/docs/tia),
  [pestphp.com/docs/continuous-integration](https://pestphp.com/docs/continuous-integration)
  (sharding; confirms `tests/.pest/shards.json` is committed and time-balancing activates
  automatically once it exists).
- Installed-version verification: `composer show` (pest 4.7.8, released 2026-08-03;
  browser plugin 4.3.1), `vendor/pestphp/pest/src/Plugins/Shard.php` (time-balanced
  sharding present), `vendor/pestphp/pest/src/Mixins/Expectation.php` (no
  `toBeEmail`/`toBeIpAddress`), no `--tia` anywhere in v4 src, `php -m` (no Xdebug/PCOV
  in the CLI), `phpstan.neon` paths + line 86, `rector.php`, PHP 8.4.23.
- Extraction method: README §3b-ter Method A, plus this run's addition — bump the
  resource-timing buffer (`performance.setResourceTimingBufferSize(3000)`) and toggle CC
  off/on before reading the pot URL; the default 250-entry buffer silently drops it.

### What I could not obtain

- On-screen code frames — deliberately skipped: the transcripts carried the API shapes,
  pestphp.com docs are the durable authority for exact v5 signatures, and every claim that
  needed hard verification was checkable against installed vendor source instead.
- Exact publish dates for the keynote, Povilas's video, and day 1 (their capture headers
  lacked the metadata JSON; the day 2–6 dates are exact).
- Comment threads (not scraped — nothing in visible top comments suggested corrections).
