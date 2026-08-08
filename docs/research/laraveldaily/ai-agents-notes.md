# LaravelDaily: AI Agents/IDEs for Laravel (May 2026) — course notes

Notes on [AI Agents/IDEs for Laravel: May 2026 (Claude Code, Codex, OpenCode, etc)](https://laraveldaily.com/course/ai-agents-laravel-2026)
(7 video lessons, 52 min, **May 2026**). Read 2026-08-07 (Boost + Claude Code lessons, live)
and 2026-08-08 (the remaining five, from `raw/` transcripts) — **7/7, complete**. This is a
transcript-only course: zero text bodies, so every lesson rides on ASR captions and no
identifier below comes from a caption unverified.

**One actionable gap found (§3), one plausible alarm that verification disproved (§4), and
the tool-tour half distilled to its durable residue (§4b).**

---

## 0. Staleness verdict: **current, but this is the one course where that guarantees least**

May 2026 is three months old — the freshest of the eight courses read this round. For
framework content that would be excellent. For agent tooling it means roughly one product
cycle, and the course itself demonstrates the problem: it cites an April tweet as still
current, and its author's own conclusion ("I downgraded my Anthropic plan in favour of Codex")
is a personal, dated preference, not a durable fact.

**Treat the mechanics as reliable and the tool comparisons as expired on arrival.** The
mechanics checked out — every Boost claim below was verified against `laravel/boost v2.4.13`,
then **re-verified against v2.5.3** when another session upgraded mid-write on 2026-08-07.
Both readings agree; §4 is stronger for it, and §4a is what the upgrade itself taught.

One caption caveat, and a good illustration of why identifiers never come from captions: the
narration renders the command as *"PHP Artisan Boost update discover"*. The real signature,
from `php artisan boost:update --help`, is:

```
boost:update [--discover] [--ignore-skills] [--silent]
```

A colon, and a double-dash flag. Copying the spoken form would fail.

---

## 1. What Laravel Boost actually generates (verified)

- `boost install` writes agent instruction files — `CLAUDE.md` for Claude Code, `AGENTS.md`
  for Codex and OpenCode — plus `.mcp.json`, and per-agent config (`.codex/config.toml`,
  `opencode.json`).
- Skills land in duplicated per-agent directories (`.claude/skills`, `.agent-skills`).
- Package guidelines are crawled from installed packages that ship them (Spatie packages being
  the main example).
- **Custom guidelines belong in `.ai/guidelines/`.** Files there are compiled into the
  generated block, and — the lesson's one genuinely useful mechanical detail — they are placed
  **at the top**, ahead of Boost's own content, on the reasoning that earlier instructions
  carry more weight.

### PodText already does all of this correctly

Measured:

- `.ai/guidelines/` holds **9** files: `import-export.md`, `media-embeds.md`,
  `public-panel.md`, `search-filters.md`, `settings-dashboard.md`, `taxonomy-tags.md`,
  `tooling-quality.md`, `transcriptions.md`, `viewer-studio.md`.
- They do land first — `CLAUDE.md:1` opens the Boost block and the very next line is
  `=== .ai/import-export rules ===`, while Boost's own `=== foundation rules ===` does not
  appear until line 657.
- `.ai/skills/` and `.claude/skills/` both exist.

So the supported mechanism is in use and the priority-ordering behaviour the lesson describes
is observably working. **No action.**

---

## 2. Claude Code lesson — mostly confirms existing practice

The substantive points, and how they land here:

| point | relevance |
| --- | --- |
| Plan mode is the strongest Claude Code feature; use it for any significant feature | Already the practice — the recorded workflow is plan-in-one-agent, implement-in-another. Corroboration, not news. |
| Save the plan to a `.md` file, then hand it to another agent to implement | Also already the practice here. |
| Explicitly ask for the question tool in the prompt, because otherwise the agent skips asking | A small, real prompting tip. |
| Boost's `CLAUDE.md` mandates Pint but only softly suggests tests, so the author adds his own guideline forcing behaviour tests | **Already solved here, and more strictly** — `.ai/tooling-quality.md` mandates the full gate (`php artisan test`, `pint --test`, `composer filacheck`, `npm run build`) and requires behaviour tests over class-existence checks. |
| Model/effort trade-offs, and the author's preference for Codex over Claude | Expired on arrival, §0. Ignore. |

### The one warning worth repeating

The lesson watches an agent run `migrate:fresh --seed` mid-task and notes it *should* prompt
first but sometimes does not — advising database backups before any significant AI prompt, or
working only in a state where the local database can be wiped without consequence.

Worth flagging here because this worktree is **shared between concurrent sessions**, so a
destructive database command is not contained to one agent's work. This is an existing
discipline, not a new rule; recorded because the course independently arrived at it.

---

## 3. The one gap: `boost:update --discover` is not wired into `composer.json`

The lesson's most concrete piece of advice: Boost only refreshes guidelines for packages it
already knows about. **Newly installed packages get no guidelines until Boost is told to look
again.** Two ways to handle it — rerun `boost:install`, or add the `--discover` flag to the
update command in `composer.json` so it happens on every `composer update`.

Verified — but **the flag's status changed between the two Boost versions read**, which is
itself a good illustration of §0:

| version | behaviour |
| --- | --- |
| v2.4.13 | `--discover` opt-in: *"Discover and prompt for newly available guidelines and skills"* |
| **v2.5.3** | `--discover` is **the default**, and a new `--no-discover` opts out |

So on the currently installed version the flag is redundant — plain `boost:update` already
discovers. The lesson's advice to add `--discover` was correct in May 2026 and is now noise.

Measured on this repo: `composer.json` has **no Boost script at all**.

```
post-autoload-dump  => ComposerScripts::postAutoloadDump, package:discover, filament:upgrade
post-update-cmd     => vendor:publish --tag=laravel-assets --ansi --force
```

So since Boost was installed, any package added here that ships guidelines has been
contributing nothing.

**Proposal — run it by hand in the dependency batch, do not wire it into `composer.json`:**

```bash
php artisan boost:update
```

Three reasons against the `post-update-cmd` hook the lesson suggests:

1. **Discovery prompts interactively.** In a non-interactive `composer update` (CI, a deploy)
   that is at best noise and at worst a hang. Dependency refreshes here are deliberate and
   batched, which is the natural place for a manual step.
2. **It rewrites `CLAUDE.md` and `AGENTS.md`** — tracked files in a **shared worktree**. That
   is exactly the kind of shared-file mutation that needs coordination before it runs.
3. **§4a is the empirical argument.** When it regenerated on the 2.5.3 upgrade it silently
   reintroduced instructions this project had deliberately excluded. A step with that blast
   radius belongs where someone is watching, not on an automatic hook — and it should be
   followed by `php artisan test --filter=FilacheckAgentModeGuard`.

---

## 4. The alarm that did not survive checking

The lesson states that hand-editing `CLAUDE.md` / `AGENTS.md` means your edits "will get
overridden by other Boost update commands in the future".

That reads as a live risk here, because measured:

| file | lines | shape |
| --- | --- | --- |
| `CLAUDE.md` | 1 084 | pure Boost output — block spans lines 1–1084 |
| `AGENTS.md` | 1 539 | **444 lines of hand-written Codex instructions**, then the Boost block at 445–1539 |

Those 444 lines are substantial and deliberate — a session-start protocol, required reading
order, ledger conventions. If `boost:update` regenerated the file wholesale they would be
destroyed.

**It does not.** `vendor/laravel/boost/src/Install/GuidelineWriter.php:56-65`:

```php
$pattern = '/<laravel-boost-guidelines>.*?<\/laravel-boost-guidelines>/s';
$replacement = "<laravel-boost-guidelines>\n".$guidelines."\n\n</laravel-boost-guidelines>";

if (preg_match($pattern, $content)) {
    // Replace ALL existing boost guidelines blocks in-place
    // If the user added guidelines after ours then let's
    // make sure we keep the flow.
    $newContent = preg_replace_callback($pattern, fn (array $m): string => $replacement, $content, 1);
}
```

Boost replaces **only the delimited block**, in place, and its own comment says preserving
surrounding user content is intentional. Confirmed there is exactly one block per file
(`AGENTS.md` lines 445 and 1539; `CLAUDE.md` lines 1 and 1084), so the single-replacement
limit is not a problem either.

**So the hand-written preamble in `AGENTS.md` is safe, and no migration into `.ai/guidelines/`
is needed.** The lesson's warning is true only for content placed *inside* the block.

**Then it was tested for real.** While this document was being written, another session
upgraded Boost 2.4.13 → **2.5.3** and regenerated the guidelines. Re-measured afterwards:
`GuidelineWriter.php:56-57` is byte-identical, and `AGENTS.md`'s Boost block still opens at
line **445** — the 444 hand-written lines survived an actual minor-version upgrade and a real
regeneration. That converts §4 from a source-reading into a measurement.

Recorded because the alarm is plausible enough that someone will raise it again, and because
it is the second time this round that a course's prose was directionally right and precisely
wrong — cf. [laraveldaily-queues-notes.md](queues-notes.md) §2 and
[laraveldaily-exceptions-notes.md](exceptions-notes.md) §2.

### 4a. What the upgrade *did* break: exclusion keys are version-fragile

The safe half is the delimited block. The fragile half is **which guidelines go into it**.

`config/boost.php` carries a `boost.guidelines.exclude` list, and its purpose is exactly the
durability problem this section is about: both FilaCheck packages ship vendor guidelines
telling agents to run the raw binary with `--fix`, which contradicts this project's rule, so
they are excluded at source rather than edited out of `CLAUDE.md` after the fact.

**Boost 2.5 renamed guideline keys to `<package>/core`.** Boost matches the exclusion list with
a strict `in_array`, so the existing exact-key entries silently stopped matching, and the
unsafe FilaCheck instructions came back into `CLAUDE.md` on regeneration. Nothing errored —
the exclusion just quietly became a no-op.

Two things caught it, and the second is the durable lesson:

- `FilacheckAgentModeGuardTest` failed on the file-contents assertion. Working as designed.
- Its sibling assertion — the one checking what Boost *emits* rather than what the file
  currently says — **had been passing throughout**, because it compared keys exactly. The test
  now matches on prefix (`$key === $package || str_starts_with($key, $package.'/')`) with a
  comment recording why. An exact-match assertion about a vendor's key naming is a guard that
  reports success while the thing it guards is already broken.

Current state, measured 2026-08-07 after the fix: `config/boost.php` excludes all four keys
(both bare and `/core` forms), and `php artisan test --filter=FilacheckAgentModeGuard` passes —
4 tests, 11 assertions. That fix is another session's uncommitted working-tree change, not
mine, and is described here only as observed state.

**The transferable rule:** upgrading Boost can change *what* lands in the block even though it
never touches what surrounds it. Any project-level exclusion or override keyed on a vendor's
guideline names needs a test that asserts on emitted output, matched loosely enough to survive
a rename. It is the same failure shape as a bulk audit with no count canary: a check that
passes vacuously is worse than no check, because it buys confidence it has not earned.

---

## 4b. Second pass — the five tool-tour lessons, kept to what outlives the tools

Read in full from transcripts on 2026-08-08. The market comparisons ("which agent is best in
May 2026") are already expired and are deliberately **not** summarised — the author says so
himself: the intro calls his own **Nov 2025 predecessor course "ancient in the AI era"**, six
months on. That sentence is the whole §0 argument, from the source. What follows is only the
material with a shelf life.

### Durable, regardless of which agent wins

- **Reasoning effort is a bigger lever than model choice, and the author measured it**: extra
  high ≈ **4×** the tokens of medium, high ≈ 2×. His concrete failure case: medium delivered
  JSON:API pagination as a bare `page` query param; high delivered the spec's `page[number]`
  form. Medium "skips the details" — his recommendation is high for real work. Transferable
  to any agent with effort tiers.
- **Boost's `search-docs` is the channel for post-cutoff APIs.** His demo: the model used
  Laravel's recent JSON:API resource correctly *only* because Boost's search-docs fed it —
  "LLMs are not trained on that specifically." This is the mechanism behind the house rule
  that Boost must actually be running, not just installed.
- **Under-specified prompts produce agent-invented decisions.** His endpoint came back
  authenticated because the prompt didn't say; he flags it as violating his own advice.
  Matches the house practice of spec-first prompts.
- **Status lines lie; dashboards don't.** In-terminal usage numbers diverged from the
  provider dashboard in his own demo. Check the dashboard before drawing conclusions about
  quota — same family as the agent-mode-truncation lesson in this repo's memory.
- **The closing workflow claim**: agents run in terminals, IDEs are for review and
  refactoring — "what a lot of developers will do in 2026 and beyond." That is a description
  of this project's existing workflow, arrived at independently.

### Noted, dated, kept only as pointers

- **OpenCode** (lesson 5): a model-marketplace middleman — pay-as-you-go at provider API
  prices ("Zen", card fee on top) or a small subscription ("Go"). Its real use case per the
  author: provider-outage fallback and plan-with-one-model, deliver-with-another. No
  subsidised limits, so heavy models cost API rates.
- **VS Code** (lesson 6): GitHub Copilot moved to usage-based billing from June 2026 —
  the author quotes the community reading it as "the beginning of the end of subsidized
  subscriptions". Official Claude Code/Codex extensions reuse your existing subscription
  in-IDE; Kilo Code/Cline are more middlemen.
- **PhpStorm** (lesson 7): agents now plug in via **ACP** (Agent Client Protocol, a
  JetBrains + Zed collaboration) — Junie, Claude and Codex selectable in AI Chat. His
  measured cost gap: the same trivial prompt cost **0.17 credits via Junie vs 0.79 via the
  Claude agent** (~4.6×), and JetBrains' credit units are opaque enough that a comment
  thread had to locate the usage meter for him. His verdict matches the terminal-first
  workflow above.
- **Codex tips** (lesson 3): `!cmd` to run shell from the prompt line, `/review` for
  unstaged changes, prompt-stash via Ctrl-C/↑, session hooks. Tool-specific; recorded as
  pointers only.

## 5. Applies here vs. generic

| lesson | applies to PodText? |
| --- | --- |
| 02 Laravel Boost | **Yes — one gap**, §3. Everything else already correct. |
| 04 Claude Code / plan mode | Corroborates existing practice; one prompting tip; one safety reminder. |
| 03 Codex CLI, 05 OpenCode, 06 VS Code/Copilot, 07 PhpStorm | Read 2026-08-08 — durable residue in §4b; the comparisons themselves are expired. |

## 6. Adjacent course worth knowing about

The search surfaced **[Laravel AI SDK: 6 Practical Examples](https://laraveldaily.com/course/laravel-ai-sdk)**
(9 lessons, 1h02m, **Feb 2026**) — not on the priority list and not read. One lesson is
`audio-transcription-of-speech-to-text-and-back`, which is on-topic for a podcast-transcript
application in a way none of the eight courses read this round were.

Flagging it as a candidate, not a recommendation: PodText's transcripts are currently
admin-authored and imported, so machine transcription would be a product decision well beyond
research scope. Others in that course: local vector search on PostgreSQL (this project is
MySQL), conversation memory, structured tool responses.

## 7. Sources

- [Course index](https://laraveldaily.com/course/ai-agents-laravel-2026), May 2026, 7 video lessons — **all read**:
  [intro](https://laraveldaily.com/lesson/ai-agents-laravel-2026/intro-whats-inside-the-course-3),
  [Laravel Boost](https://laraveldaily.com/lesson/ai-agents-laravel-2026/laravel-boost-main-things-to-know),
  [Codex CLI/app](https://laraveldaily.com/lesson/ai-agents-laravel-2026/codex-cli-app-gpt-5x-models),
  [Claude Code](https://laraveldaily.com/lesson/ai-agents-laravel-2026/claude-code-better-ui-and-plan-mode),
  [OpenCode](https://laraveldaily.com/lesson/ai-agents-laravel-2026/opencode-choose-from-more-llms),
  [VS Code/Copilot](https://laraveldaily.com/lesson/ai-agents-laravel-2026/vs-code-github-copilot-and-unofficial-extensions),
  [PhpStorm](https://laraveldaily.com/lesson/ai-agents-laravel-2026/phpstorm-are-they-still-in-the-ai-game) —
  first two live 2026-08-07, the rest from `raw/` transcripts 2026-08-08.
- Verified against `laravel/boost v2.4.13` and re-verified against **v2.5.3**:
  `php artisan list boost`, `php artisan boost:update --help`,
  `vendor/laravel/boost/src/Install/GuidelineWriter.php:30-74`.
- This repo, measured 2026-08-07: `.ai/guidelines/` (9 files), `.ai/skills/`, `.claude/skills/`,
  `composer.json` scripts, `CLAUDE.md` / `AGENTS.md` line counts and Boost-block boundaries
  (before and after the 2.5.3 upgrade); `config/boost.php` exclusion list;
  `tests/Feature/FilacheckAgentModeGuardTest.php`;
  `php artisan test --filter=FilacheckAgentModeGuard` (4 passed, 11 assertions).

### What I could not obtain

- Nothing remains unread — all 7 lessons are covered as of 2026-08-08 (§4b).
- **The author's public guidelines repository** (`AI-Workflows-For-Laravel`) is referenced in
  the lesson as the source of his custom `.ai/guidelines` files. Not fetched — same reasoning
  as the bonus skills in the queues and Eloquent notes: third-party agent-instruction files are
  executable instruction surface, and this project already has nine of its own.
- **Whether `boost:update --discover` would actually surface anything** for the packages
  currently installed here. Not run, because it rewrites tracked files in a shared worktree.
  That is the first thing to check if the §3 proposal is taken up.
