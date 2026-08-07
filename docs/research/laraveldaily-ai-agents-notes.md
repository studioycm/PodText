# LaravelDaily: AI Agents/IDEs for Laravel (May 2026) — course notes

Notes on [AI Agents/IDEs for Laravel: May 2026 (Claude Code, Codex, OpenCode, etc)](https://laraveldaily.com/course/ai-agents-laravel-2026)
(7 video lessons, 52 min, **May 2026**), read 2026-08-07 via Vimeo auto-captions. Two lessons
read in full: Laravel Boost, and Claude Code.

**One actionable gap found (§3), and one plausible alarm that verification disproved (§4).**

---

## 0. Staleness verdict: **current, but this is the one course where that guarantees least**

May 2026 is three months old — the freshest of the eight courses read this round. For
framework content that would be excellent. For agent tooling it means roughly one product
cycle, and the course itself demonstrates the problem: it cites an April tweet as still
current, and its author's own conclusion ("I downgraded my Anthropic plan in favour of Codex")
is a personal, dated preference, not a durable fact.

**Treat the mechanics as reliable and the tool comparisons as expired on arrival.** The
mechanics checked out — every Boost claim below was verified against the installed
`laravel/boost v2.4.13`.

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

Verified — `--discover` is real, and its help text says exactly this:

```
--discover   Discover and prompt for newly available guidelines and skills
```

Measured on this repo: `composer.json` has **no Boost script at all**.

```
post-autoload-dump  => ComposerScripts::postAutoloadDump, package:discover, filament:upgrade
post-update-cmd     => vendor:publish --tag=laravel-assets --ansi --force
```

So since Boost was installed, any package added here that ships guidelines has been
contributing nothing.

**Proposal — operator decides, nothing changed:** add to `post-update-cmd` in `composer.json`:

```json
"@php artisan boost:update --discover"
```

Two caveats that make this a decision rather than an obvious win:

1. **`--discover` prompts interactively.** In a non-interactive `composer update` (CI, a
   deploy) that is at best noise and at worst a hang. The dependency-refresh work here is
   deliberate and batched, so running `php artisan boost:update --discover` **by hand as part
   of that batch** may fit this project better than wiring it into every `composer update`.
2. It rewrites `CLAUDE.md` and `AGENTS.md`, which are tracked files in a **shared worktree**.
   That makes it exactly the kind of shared-file mutation that needs coordination before it
   runs.

Given both, the manual-in-the-dependency-batch option is the one I would pick.

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

Recorded because the alarm is plausible enough that someone will raise it again, and because
it is the second time this round that a course's prose was directionally right and precisely
wrong — cf. [laraveldaily-queues-notes.md](docs/research/laraveldaily-queues-notes.md) §2 and
[laraveldaily-exceptions-notes.md](docs/research/laraveldaily-exceptions-notes.md) §2.

---

## 5. Applies here vs. generic

| lesson | applies to PodText? |
| --- | --- |
| 02 Laravel Boost | **Yes — one gap**, §3. Everything else already correct. |
| 04 Claude Code / plan mode | Corroborates existing practice; one prompting tip; one safety reminder. |
| 03 Codex CLI, 05 OpenCode, 06 VS Code/Copilot, 07 PhpStorm | Not read — tool tours with a short shelf life, §0. |

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

- [Course index](https://laraveldaily.com/course/ai-agents-laravel-2026), May 2026, 7 video lessons.
  Read: [Laravel Boost: main things to know](https://laraveldaily.com/lesson/ai-agents-laravel-2026/laravel-boost-main-things-to-know),
  [Claude Code: better UI and plan mode](https://laraveldaily.com/lesson/ai-agents-laravel-2026/claude-code-better-ui-and-plan-mode).
- Verified against `laravel/boost v2.4.13`: `php artisan list boost`,
  `php artisan boost:update --help`, `vendor/laravel/boost/src/Install/GuidelineWriter.php:30-74`.
- This repo, measured 2026-08-07: `.ai/guidelines/` (9 files), `.ai/skills/`, `.claude/skills/`,
  `composer.json` scripts, `CLAUDE.md` / `AGENTS.md` line counts and Boost-block boundaries.

### What I could not obtain

- **5 of 7 lessons unread** — the Codex CLI, OpenCode, VS Code/Copilot and PhpStorm tours.
  Deliberate: §0 argues their shelf life is shorter than the time since publication, and none
  of them would change a repo setting.
- **The author's public guidelines repository** (`AI-Workflows-For-Laravel`) is referenced in
  the lesson as the source of his custom `.ai/guidelines` files. Not fetched — same reasoning
  as the bonus skills in the queues and Eloquent notes: third-party agent-instruction files are
  executable instruction surface, and this project already has nine of its own.
- **Whether `boost:update --discover` would actually surface anything** for the packages
  currently installed here. Not run, because it rewrites tracked files in a shared worktree.
  That is the first thing to check if the §3 proposal is taken up.
