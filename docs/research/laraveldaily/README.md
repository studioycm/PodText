# Researching laraveldaily.com — method, traps, and archive format

How to mine laraveldaily.com without learning stale material and without going back to the site
twice. Everything here was measured on 2026-08-07 while reading eight courses; where something
is inferred rather than measured it says so.

**The standing rule this all serves: do not learn deprecated or stale material.** The site gives
you almost no help with that, so the checking is on us.

---

## 1. Access

The **in-app Claude browser** (`mcp__Claude_Browser__*`, *not* claude-in-chrome) holds a
persisted premium login. Open it with:

```
preview_start {"url": "https://laraveldaily.com/courses"}
```

`navigate` fails if no preview pane is open yet, so `preview_start` must come first.

If the session has expired, **ask the operator to sign in** — never type credentials.

Useful entry points:

| what | URL |
| --- | --- |
| Catalogue | `/courses` |
| Filtered | `/courses?tech_stack=filament` (also `livewire`, `vue`, `react`, `inertia`, `mobile-apps`) |
| Search | `/search?q=<term>` |
| Course | `/course/<course-slug>` |
| Lesson | `/lesson/<course-slug>/<lesson-slug>` |

### Being logged in is not obvious

Checking `document.body.innerText` for "Logout" returns **false even when signed in**. The
reliable signal is progress data: an "In Progress" badge or a percentage on the catalogue card.
Don't conclude you're logged out from a text probe.

### Same-origin fetch is the workhorse

Once any laraveldaily.com page is loaded, fetch every other page from inside it with
`credentials: 'include'` and parse with `DOMParser`. **One tool call can pull a whole course.**
Navigating per lesson is slow and unnecessary.

`read_page` usually returns "(empty page)" on this site — the page is Livewire-driven (9+
`wire:id` roots per lesson). Use `get_page_text` or `javascript_tool` instead.

---

## 2. Freshness — the staleness trap

**The catalogue shows one date, labelled "Released". There is no "last updated" field.** A 2023
course whose content was refreshed looks identical to one that has rotted. The date is a floor,
not a guarantee — and the reverse also happens: courses get re-badged for a new Laravel version
without their examples being re-run.

### Era rule of thumb

| released | assume |
| --- | --- |
| Mar 2026+ | Laravel 13 era — current |
| 2025 | Laravel 12 era — check before trusting |
| ≤ Dec 2024 | Laravel 11 or older — **stale by default** |

### Always spot-check content, not just the date

Markers that settle it fast, all visible in lesson 1 or 2:

- **Package names.** `nunomaduro/larastan` (pre-rename) vs `larastan/larastan`.
- **Redundant config.** Hand-written `includes: ./vendor/...` lines that `phpstan/extension-installer` has made unnecessary.
- **Version claims.** "Level 9 is the highest" (PHPStan 2.x has 10).
- **Job stub shape.** Four traits (`Bus\Queueable`, `Foundation\Bus\Dispatchable`, `Queue\InteractsWithQueue`, `Queue\SerializesModels`) = pre-Laravel-11. Single `Foundation\Queue\Queueable` = current.
- **Model casts.** `protected $casts = []` = old; `protected function casts(): array` = Laravel 11+.
- **PHP version in shell/config samples.** `php8.1` in a supervisor conf dates the lesson.
- **Screenshot/log timestamps.** A lesson titled "Laravel 12" carrying a 2023 log line was re-badged, not rewritten.
- **Newest comment date.** Three-year-old discussion on a "current" course is a signal.

### On anything older than Laravel 12, check the *pattern*, not just the API

A lesson can use perfectly current APIs and still teach an approach the same publisher has
since moved away from. Checking that the code still runs is not enough.

Worked example: the May 2023 timezone course builds three events and three listeners for
model created/updated/deleted, dispatched from a controller. Every class uses current idiom —
nothing to flag on an API sweep. But the Aug 2024 design-patterns lesson would route that to an
Observer, and the Mar 2026 structure course would question whether it should be events at all.
**The architecture was superseded while the syntax stayed valid.**

So on any pre-Laravel-12 lesson, ask both questions: do these APIs still exist, *and* does this
publisher still recommend this shape? Their own later courses are the best check.

### Verify against what is actually installed — this is the important one

**Every API claim worth acting on gets confirmed against `vendor/` in this repo**, not against
the lesson. This is what makes the notes trustworthy and it repeatedly changed conclusions:

```bash
composer show --direct              # what we actually run
composer show <vendor/package>      # one package
```

Then read the vendor source. Worked examples from the first round:

- The queues course said a timed-out job is "silently killed". `Worker.php` shows it is failed
  properly once attempts are exhausted. The correction **removed** a false finding.
- An exceptions lesson's transaction sample does not run at all (closure imports `$data`, body
  uses `$row`) and its handler tip is a regression.
- The AI-agents course's `--discover` flag was opt-in on Boost 2.4 and is **the default** on
  2.5 — a claim that expired between reading and writing.

**A course being current is not evidence its code is correct.** Three of eight courses had prose
that was directionally right and precisely wrong.

### Also check the packages the lesson is about

If a lesson concerns a package we ship, run `composer audit` while you are there. That is how the
six `league/commonmark` advisories were found — published the day before, on the Markdown engine
behind every transcript page.

---

## 3. Getting the content out of a lesson

**Lessons have up to four separate content channels. Take all of them.** Missing one is the
single most likely way to under-read a course — it happened on the first round.

### 3a. The text body — `.prose` (also matches `article`)

```js
document.querySelector('.prose').innerText
```

Present on text courses **and on some video courses**. Measured:

| lesson | `.prose` length |
| --- | --- |
| `queues-laravel/unique-jobs` (text course) | 4 167 |
| `laravel-eloquent-expert/oneofmany` (**video** course) | **5 446** |
| `livewire-v4/islands` (video course) | 64 — *only* a repo link |
| `ai-agents-laravel-2026/...` (video course) | `null` |

**The trap:** the Eloquent course is a video course with full authored write-ups, and the first
round read only its captions. That lost a real API (`HasMany::one()`) which the narration never
mentioned. And a 64-character `.prose` is not empty — on the Livewire course it is the demo
repository link, which was recorded as "could not obtain".

**Rule: always read `.prose`, however short, and never assume "video course" means "no text".**
Falling back to `main` and splitting on "Course Lessons" — which the first round did — silently
discards short `.prose` blocks.

### 3b. The video transcript — Vimeo auto-captions

Video lessons embed a Vimeo player carrying auto-generated English captions. Getting them needs
**both** the browser and the shell; neither works alone.

1. **Collect video IDs — one same-origin fetch loop over the lesson URLs:**
   ```js
   h.match(/player\.vimeo\.com\/video\/(\d+)\?h=([a-z0-9]+)/)
   ```
2. **Navigate** (not fetch) to `https://player.vimeo.com/video/<id>?h=<hash>`, then read:
   ```js
   window.playerConfig.request.text_tracks[0].url
   ```
3. **`curl` that signed URL from the shell.**
4. Strip `WEBVTT`, cue numbers, `-->` lines, and consecutive duplicates.

**Why both tools are needed** — measured failures:

| attempt | result |
| --- | --- |
| `fetch()` the Vimeo config from a laraveldaily page | `Failed to fetch` |
| `curl` the config with browser UA + Referer | **HTTP 403** |
| `fetch()` another video's config from *inside* the player page (same origin) | HTML error doc, not JSON |
| `fetch()` the `.vtt` from inside the player page | `Failed to fetch` |
| `curl` the `.vtt` | **works** |

So: **config needs a real browser navigation; the caption file needs curl.** (The same-origin
config failure suggests referer gating, but only the symptom was measured.)

Cost is 2 browser calls per lesson. Batch step 1 for the whole course first.

### Auto-caption caveats — treat captions as evidence, never as source

The tracks are ASR (`lang: en-x-autogen`, `provenance: ai_generated`). They mangle every
identifier, consistently:

| caption says | means |
| --- | --- |
| LVO R / live R / LIR / live O | Livewire |
| Larval / lateral | Laravel |
| wire pole | `wire:poll` |
| sentiment element | sentinel element |
| bullying variable | boolean |
| Alpine GS | Alpine.js |
| of many | `ofMany()` |
| fire folks | Firefox |
| backhand logic | backend |

**Never take an API name, flag, or command from a caption.** The narration said "PHP Artisan
Boost update discover"; the real signature is `boost:update --discover`. Confirm every
identifier against vendor source or `--help`.

Signed caption URLs carry `expires=` and die in roughly a day — collect and download in one
sitting.

### 3c. Comments — worth reading

Comments sit under `.comments-*` classes (e.g. `.comments-date` wraps the "N months ago"
timestamps). They are not noise:

- On the Livewire v4 course, a comment thread discussed getting Laravel Boost to recognise
  Livewire 4 — with the author replying, and a reader correcting him that newer versions handle
  it. That exchange is more current than the lesson.
- Comment dates are a freshness signal (§2).
- Author replies sometimes contain corrections that never made it into the lesson.

### 3d. Course-level metadata

From the course page's flattened text: lesson count, total duration, release month, and format.
Format is legible from the duration wording — **"N min read" = text**, **"Video Course" /
per-lesson `M:SS` timestamps = video**. Both can appear; see §3a.

---

## 3e. The tooling — scrape once, then search locally

Three files in this folder, so nobody has to repeat the crawl:

| file | what it is |
| --- | --- |
| [`scrape.mjs`](scrape.mjs) | Playwright scraper. Writes one JSON per course to `raw/`. |
| [`build-index.mjs`](build-index.mjs) | Generates `index.md` from `inventory.json` + `raw/`. |
| **[`index.md`](index.md)** | **The search surface — start here.** |
| `inventory.json` | 407 lessons across 27 courses; regenerate with `--inventory`. |
| `raw/` | Scraped bodies, links, comments, transcripts. **Gitignored** — large, and licensed material from a paid account. |

```bash
node scrape.mjs --login      # once — you log in; the script never sees credentials
node scrape.mjs              # full crawl, checkpointed per lesson, resumable
node build-index.mjs         # regenerate index.md
```

### Searching

`index.md` is **one line per lesson with every searchable term on that line**, so grep returns
the lesson rather than a heading you then have to read around:

```bash
grep -i "observer"   docs/research/laraveldaily/index.md    # concept
grep -i "ofMany()"   docs/research/laraveldaily/index.md    # API identifier
grep -i "wire:sort"  docs/research/laraveldaily/index.md    # directive
grep -i "2026"       docs/research/laraveldaily/index.md    # recency
```

Identifiers are mined from lesson bodies, capped at the 12 most frequent per lesson — so the
index finds *which lesson discusses an API*, and `raw/<course>.json` holds the full text.

Two things learned building it, both worth keeping:

- **Match PHP attributes without the closing bracket.** Real usage is
  `#[ObservedBy([UserObserver::class])]`, so a `#\[[A-Za-z]+\]` pattern matches nothing and the
  index silently returned zero hits for the exact names it exists to find.
- **A truncation flag needs two signals.** Flagging any body that does not end in terminal
  punctuation produced 46 false positives and 0 true ones — every lesson ending in a code block.
  Requiring *also* that the body is well under its course median dropped that to 3.

## 4. Archive format for future research

Each course gets **one file per course** in this folder, named `<topic>-notes.md`. The point is
that a later session can work from the archive **without returning to the site**.

Start with metadata, then the extracted content, then the analysis:

```markdown
# LaravelDaily: <Course Title> — course notes

- **URL**: https://laraveldaily.com/course/<slug>
- **Released**: <Mon YYYY>   (the only date the site gives — a floor, not an update stamp)
- **Format**: text | video | video with text bodies
- **Size**: N lessons, H h MM min
- **Read on**: <date>, <N of M lessons in full>
- **Verified against**: <package> v<version> as installed in this repo
- **Staleness verdict**: passes | stale | mixed — with the markers checked

## 0. Staleness verdict
## 1. Extracted content
   Per lesson: .prose text, transcript, notable comments.
   Mark which channels existed and which were empty.
## 2..N Analysis
   What it claims vs what was measured. Applies-here vs generic.
## Sources
   Every lesson URL. Vendor files read.
## What I could not obtain
   Unread lessons, missing channels, unverified claims.
```

Conventions that earned their place:

- **State measured vs claimed, every time.** A table of "course says / measured here" is worth
  more than prose.
- **Negative results are results.** "Stale, stopped after two lessons, here are the three
  markers" is a complete and useful outcome.
- **Record the unread lessons by name.** It tells the next session where to resume.
- **Never write machine paths, tokens, or credentials** into these files (CLAUDE.md rule).
- **Propose, don't apply.** These are research files; code changes are the operator's call.

---

## 5. Site facts worth not re-deriving

Established 2026-08-07:

- **57 courses total.**
- **Exactly one Filament course** — "Filament 4/5 From Scratch" (Aug 2025, 28 lessons, video).
  Note, correcting an earlier read: its doc links pointing at `filamentphp.com/docs/4.x/…` are
  **not** a staleness signal on their own, because Filament keeps the same doc path structure
  across majors — `4.x` → `5.x` substitutes directly. The comment threads arguing about
  Shield's beta status are not a signal either. **Judge this course by checking its APIs
  against the installed Filament 5, not by its links or its comments.**
- **Exactly one PHPStan/Larastan item** — the stale Mar 2023 course. Site search for "phpstan"
  returns nothing else. **LaravelDaily is a dead end for static analysis.**
- Some 2026 courses ship a **bonus agent skill** as a final lesson
  (`laraveldaily-queues-audit`, `-eloquent-audit`, `-structure-audit`), served from
  `LaravelDaily/AI-Workflows-For-Laravel` on GitHub. Standing position here: **note them, do
  not install them.** The author says they are personal preference, and a `SKILL.md` curl'd
  into an agent's instruction path is executable instruction surface — the exact trust model
  the site's own security course argues against.
- Course slugs are not guessable from titles. Find them via `/search?q=` or a tech-stack filter;
  guessing 404s.

### Courses already covered in this folder

| file | course | released | verdict |
| --- | --- | --- | --- |
| [livewire-v4-notes.md](livewire-v4-notes.md) | Livewire v3 to v4 | Jan 2026 | passes |
| [supply-chain-security-notes.md](supply-chain-security-notes.md) | Practical Laravel Security | May 2026 | passes — freshest on the site |
| [queues-notes.md](queues-notes.md) | Queues in Laravel 13 (+ 2023 companion) | Mar 2026 | passes / companion stale — **18/18, rewritten** |
| [eloquent-notes.md](eloquent-notes.md) | Laravel 13 Eloquent: Expert Level | Mar 2026 | passes — **24/41 full, all digested, rewritten** |
| [database-design-notes.md](database-design-notes.md) | Structuring Databases in Laravel 12 | Apr 2025 | passes — **18/18 covered**, schema ahead of it |
| [app-structure-notes.md](app-structure-notes.md) | How to Structure Laravel 13 Projects | Mar 2026 | passes — **16/16**, premise doesn't fit this app |
| [exceptions-notes.md](exceptions-notes.md) | Handling Exceptions and Errors in Laravel 12 | Mar 2025 | mixed — **8/8, rewritten**; sample code broken |
| [roles-permissions-notes.md](roles-permissions-notes.md) | Roles and Permissions in Laravel 13 | Apr 2026 | passes — validates the staged authz position |
| [multi-language-notes.md](multi-language-notes.md) | Multi-Language Laravel 11 | card Aug 2024 | **mixed — re-badged, ~2023 authorship**; stable half implemented here |
| [ai-agents-notes.md](ai-agents-notes.md) | AI Agents/IDEs for Laravel | May 2026 | passes — **7/7**, tool comparisons expire fast |

### Known unread, ranked for a next pass

1. **`laravel-eloquent-expert`** — 36 of 41 lessons unread, and the five that were read used
   captions only, so their text bodies are unchecked (§3a). `model-casts-dates-enum` and
   `local-global-scopes` are the highest-value.
2. **`queues-laravel`** — lesson 17, testing queues with `Queue::fake` / `Bus::fake` /
   `withFakeQueueInteractions`, against a 1850-test suite.
3. **`structuring-databases-laravel`** — `indexes-useful-useless-composite`, relevant to the
   Hebrew folded-search columns.
4. **`laravel-ai-sdk`** (Feb 2026, 9 lessons) — includes speech-to-text transcription, on-topic
   for this app in a way none of the eight read so far were.
