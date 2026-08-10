# LaravelDaily: Practical Laravel Security — course notes + PodText audit

Notes on [Practical Laravel Security: Packages, Secrets, Supply-Chain Attacks](https://laraveldaily.com/course/laravel-security-composer)
(7 text lessons, 43 min, released **May 2026**), read in full on 2026-08-07.

The course is a checklist. So rather than restate it, **this document runs its checklist
against this repo and records what was measured.** That produced one live finding (§3) that
is unrelated to the course's own subject matter but was surfaced by following its advice.

---

## 0. Staleness verdict: **the freshest material on the site**

The course is built around the `laravel-lang/*` Packagist compromise of **22–23 May 2026**,
and was published days later. Nothing in it is a rehash of older security advice: it names a
specific incident, a specific payload, and a specific delivery mechanism.

Two claims that would date it if wrong, both checked:

| claim | status |
| --- | --- |
| `APP_PREVIOUS_KEYS` exists "since Laravel 11" for graceful `APP_KEY` rotation | correct; still current in Laravel 13 |
| `composer audit` is built into Composer | correct; ran it here, output in §3 |

The one thing to hold loosely is the incident narrative itself (the fork-tag mechanism, the
`flipboxstudio.info` second stage, the Aikido attribution). That is reported, not verified
here — I did not independently confirm the forensic details, and the linked Aikido report was
not read. The *defensive* advice does not depend on those details being exactly right.

---

## 1. The delivery mechanism, because it changes what "trusted" means

Worth stating precisely, since it is the part most likely to be misremembered:

- The payload ran from **`vendor/autoload.php`**, not from any API of the package. You did not
  have to *use* `laravel-lang` — only to have it installed. Every web request, every Artisan
  command, every test run executed it.
- Tags in the official repo were made to **point at commits living in a fork**. GitHub allows
  this. Packagist resolved the tags and served the fork's code, while the official repo's
  commit history stayed visibly clean. Reviewing the GitHub repo would not have found it.
- The target was **credentials on the developer's machine** — cloud keys, SSH keys, browser
  password stores, every `.env` under `$HOME` — not the Laravel app.

The consequence the course draws is the right one and worth adopting as a standing
assumption: `composer require` grants arbitrary code execution as your user, permanently, to
that package *and its whole transitive tree*, including `require-dev` and anything installed
with `composer global require`.

---

## 2. Was PodText exposed to this attack? **No.** (measured)

```
composer show | grep laravel-lang   → no match
grep -c laravel-lang composer.lock  → 0
```

`laravel-lang/*` has never been a dependency here, direct or transitive. Following the
course's own Lesson 5 triage: this repo lands cleanly in the "I don't use that package — I'm
clear" bucket. **No rotation is warranted on account of this incident.**

Recorded explicitly so the question does not get re-asked. Note this is a *different* incident
from the cryptominer compromise this project actually had; nothing here changes that history.

---

## 3. Finding (since resolved): 6 `league/commonmark` advisories, of which **1 applied**

> **Status 2026-08-10: closed.** Commit `303beab` (2026-08-07, the Boost session's stack
> bump) shipped 2.8.3 → 2.9.0 — hours before these notes were committed, so proposal §7.1
> was already done at publication. The lock reads 2.9.0 and `composer audit` is clean.
> The analysis below is kept as the worked example §7.3 refers to.

Lesson 2 says to run `composer audit` before deploying and weekly. Ran it on 2026-08-07:

```
Found 6 security vulnerability advisories affecting 1 package.
```

All six are `league/commonmark`. Installed here: **2.8.3**. All six are fixed in **2.9.0**.
They were published **2026-08-06 — the day before this session**, so this is genuinely new,
not a backlog item.

This matters here more than it would elsewhere: `league/commonmark` is the engine behind
`SafeMarkdownRenderer`, which renders every transcript and every description on the public
site.

### 3a. Which of the six actually apply

`Str::markdown()` builds a `GithubFlavoredMarkdownConverter`, which registers exactly
`CommonMarkCore`, `Autolink`, `DisallowedRawHtml`, `Strikethrough`, `Table`, `TaskList`
(`vendor/league/commonmark/src/Extension/GithubFlavoredMarkdownExtension.php:27-31`).
`SafeMarkdownRenderer` passes **no** extra extensions. So:

| advisory | requires | applies here? |
| --- | --- | --- |
| CVE-2026-71478 — `AttributesExtension` unsafe-link bypass → **stored XSS** | `AttributesExtension` | **No** — not registered |
| DoS via adjacent inline attribute blocks | `AttributesExtension` | **No** |
| DoS via duplicate footnote definitions | `FootnoteExtension` | **No** |
| DoS via colliding heading slugs | `HeadingPermalink` / `Footnote` / `TableOfContents` | **No** |
| DoS via deeply nested XML output | the XML renderer (`convertToXml`) — opt-in | **No** — HTML path only; no `convertToXml` in `app/` |
| **CVE-2026-71488 — quadratic-time DoS parsing crafted Markdown** | core parser **and** `Autolink`/GFM | **Yes** |

The extension mapping for the first four was taken from the advisories themselves for the
heading-slug and XML ones (fetched), and from the advisory titles plus extension membership
for the footnote and attributes ones (not individually fetched — stated as inference).

The XSS one being inapplicable is the important half of this: it is the only advisory of the
six with a confidentiality impact, and it is ruled out by configuration, not by luck.

### 3b. The one that applies, measured rather than assumed

The advisory says a **single non-ASCII character anywhere on a line** puts that whole line on
the slower multibyte path. This is a Hebrew app, so every line of real content is non-ASCII —
which looked like it might make the quadratic behaviour PodText's *default* path.

**Measured, and that hypothesis is wrong.** Rendering realistic Hebrew vs ASCII transcript
content through `SafeMarkdownRenderer::toTranscriptHtml()` on this machine:

| transcript lines | ASCII | Hebrew | ratio |
| --- | --- | --- | --- |
| 200 | 17.9 ms | 7.2 ms | 0.40× |
| 800 | 26.4 ms | 28.4 ms | 1.07× |
| 3200 | 101.6 ms | 113.0 ms | 1.11× |

Ordinary Hebrew prose costs about **10%** over ASCII at realistic transcript length, and
scales roughly linearly (4× the lines → ~3.8× the time). The 200-line row is warm-up noise,
not a Hebrew speed-up. **Being permanently on the multibyte path is a ~10% baseline tax here,
not a latent DoS.**

The quadratic blowup needs *crafted* input, and it does reproduce cleanly:

| payload (single line) | time |
| --- | --- |
| 2 000 leading spaces + one Hebrew char | 7.7 ms |
| 4 000 | 5.0 ms |
| 8 000 | 19.1 ms |
| 16 000 | 74.2 ms |
| 2 000 repeated `*` + one Hebrew char | 4.4 ms |
| 4 000 | 12.9 ms |
| 8 000 | 41.3 ms |

Doubling the run length roughly **quadruples** the time — O(n²), as advertised. A 16 KB
single-line payload already costs 74 ms; extrapolating, a ~1 MB payload would run into
minutes of CPU.

### 3c. Trust boundary — why this is low urgency here

Every call site that reaches the renderer takes **admin-authored** content:

| site | field |
| --- | --- |
| [content-item-transcript-viewer.blade.php](resources/views/livewire/public/content-item-transcript-viewer.blade.php) | `Transcription::transcript_markdown` |
| [show-content-item.blade.php:244](resources/views/filament/public/pages/show-content-item.blade.php:244) | `ContentItem::description_markdown` |
| [show-content-group.blade.php:135](resources/views/filament/public/pages/show-content-group.blade.php:135) | `ContentGroup::description_markdown` |
| [browse-category-content-items.blade.php:10](resources/views/filament/public/pages/browse-category-content-items.blade.php:10) | `Category::description_markdown` |
| [content-item-search.blade.php:210](resources/views/livewire/public/content-item-search.blade.php:210) | homepage section `displayConfig['body']` |
| [PublicAboutPageRenderer.php](app/Support/PublicFront/About/PublicAboutPageRenderer.php) | settings-authored About body |

Checked specifically: **`PublicFormSubmission` content is never rendered as Markdown** — no
Markdown reference in the submission model or its Filament resource. So there is no
anonymous→renderer path.

Exploiting CVE-2026-71488 here therefore requires an authenticated admin (or a crafted
transcript import, which is also admin-gated) to deliberately submit a megabyte of pathological
Markdown. That is a privileged actor abusing their own panel.

**Verdict: real, low urgency, and free to fix.**

### 3d. The fix is a single-package update

`league/commonmark` is a transitive dependency of `laravel/framework v13.23.0`, constrained
`^2.8.1` — which already permits 2.9.0. No constraint edit is needed. Dry-run, measured:

```
Lock file operations: 0 installs, 1 update, 0 removals
  - Upgrading league/commonmark (2.8.3 => 2.9.0)
```

Nothing else moves. This is exactly the shape Lesson 2 calls "Better: `composer update
vendor/specific-package`" rather than a blind `composer update`.

**Proposed, for the operator to run when a gate-green batch is convenient:**

```bash
composer update league/commonmark
```

Then re-run the gate (`php artisan test`, `vendor/bin/pint --test`, `composer filacheck`,
`npm run build`). The Markdown-rendering tests and the XSS regression tests already in the
suite are the relevant coverage. **Not run in this session** — this is a research session and
`composer.lock` is shared with other sessions in this worktree.

---

## 4. The course's Composer checklist, audited against this repo

Lesson 2's eleven habits, each with what is actually true here.

| # | habit | PodText status |
| --- | --- | --- |
| 1 | Commit `composer.lock` | **Pass** — tracked; `.gitignore` ignores `/vendor` only |
| 2 | Don't run bare `composer update` | Consistent with existing practice — the pinned stack is deliberate and recorded; the 2026-07-31 bump was a considered batch, not a blind update |
| 3 | Pin security-sensitive packages | Partly by design already — the shield↔spatie pin chain and the pest pin are deliberate |
| 4 | Vet packages before adding | Matches the standing "no new dependencies without approval" rule in CLAUDE.md |
| 5 | Minimise dependencies | Same rule |
| 6 | Care with `composer global require` | **Unaudited** — outside the repo; operator action, see §6 |
| 7 | Run `composer audit` | **Gap** — not wired into the gate. It found §3 on first run |
| 8 | `--no-scripts` when investigating | Nothing to change; worth knowing the flag exists |
| 9 | Never run Composer as root | **Unverified for production.** Relevant: the Forge scheduler was found living in *root's* crontab, so "which user runs what" is already known to be non-obvious on this server |
| 10 | Deploy with `--no-dev --optimize-autoloader` | **Unverified** — Forge's default script includes both; not confirmed against this site's actual script |
| 11 | Don't panic-update during an incident | Advice noted; no action |

### The one concrete repo gap (since closed): `.gitignore` had no `.env.*` wildcard

> **Status 2026-08-10: shipped.** Commit `a093baf` applied exactly the glob-plus-re-include
> below; verified at HEAD — `git check-ignore -v .env.staging` matches `.gitignore:16`
> (`.env.*`) and `.env.example` stays tracked. The measurement below describes the
> pre-`a093baf` file.

Measured at research time — `.gitignore` listed exactly:

```
.env
.env.backup
.env.production
```

Three literal names, no glob. So `.env.old`, `.env.copy`, `.env.local`, `.env.production.real` —
every filename in Lesson 3's "real `ls -la` from a real production server" example except the
two that happen to be listed — **would be committable**. Nothing currently violates this
(root holds only `.env` and `.env.example`), so this is a latent trap, not a present leak.

**Proposal (now the file's actual content, per `a093baf`):** replace the three literals
with a glob plus a re-include, in `.gitignore`:

```gitignore
.env
.env.*
!.env.example
```

Small, and it closes the whole class rather than three instances of it.

---

## 5. Incident response — how it maps onto this project's actual setup

Lessons 6 and 7 are an ordered response sequence. The ordering argument is the valuable part
and it is correct: **rotate code-push credentials first**, because an attacker holding a
GitHub token can commit a new `.env` and let CI re-deploy their backdoor *after* you have
finished rotating everything else.

Two project-specific consequences, neither of which the course could know:

1. **Auto-deploy makes step 1 load-bearing.** Every `git push origin main` deploys production
   here. The course's first step — "pause active deployments" — is not a formality in that
   configuration; the push gate *is* the deploy gate. In an incident, the correct first action
   is to stop pushing, which is already the standing discipline for a different reason.
2. **The deploy webhook URL is a credential.** The course states plainly that "deploy via
   webhook" URLs are unauthenticated — knowing the URL is enough to trigger a deployment.
   The quick-deploy webhook was repaired on 2026-07-31, so a live one exists. It belongs in
   the Tier 1 rotation list below, and it should never be pasted into a doc, an issue, or a
   commit message.

### Tier order, condensed (Lesson 7)

1. **Code-push and deploy**: GitHub/GitLab tokens, SSH keys used for push, **deploy webhook
   URLs**, Forge/Envoyer/Cloud API tokens, container-registry credentials.
2. **Production access**: DB credentials, cloud access keys, SSH keys to servers, server
   root/sudo passwords.
3. **Third-party API keys**: payment, mail, SMS, AI providers, OAuth client secrets
   (Socialite — this project uses it), monitoring tokens.
4. **When convenient**: `APP_KEY` (via `APP_PREVIOUS_KEYS`), dashboard sessions, app sessions,
   internal tokens, unexpired JWTs.

Note the deliberate inversion between planned and emergency rotation, which is the course's
sharpest single point:

- **Planned**: generate new → update everywhere → verify → **revoke old last**. Revoking first
  takes your own app down.
- **Incident**: **revoke first**, accept the breakage, then generate and update. The attacker
  may be using the credential right now.

### `APP_KEY` rotation

`APP_PREVIOUS_KEYS` (Laravel 11+) lets `APP_KEY` rotate without invalidating sessions or
existing encrypted values:

```
APP_KEY=base64:NEW
APP_PREVIOUS_KEYS=base64:OLD
```

New data uses `APP_KEY`; old data still decrypts via `APP_PREVIOUS_KEYS`; drop the old key
after enough time. Worth knowing *before* it is needed — this is the secret most teams avoid
rotating precisely because they have not looked up the mechanism.

---

## 6. Applies here vs. generic

| lesson | applies to PodText? |
| --- | --- |
| 01 laravel-lang attack | Context only. **Not exposed** — §2. |
| 02 Composer habits | **Yes.** One gap found (`composer audit` not in the gate), one live finding through it — §3, §4. |
| 03 Secrets | **Yes, one concrete gap** — the `.gitignore` glob — §4 (since closed, `a093baf`). `APP_PREVIOUS_KEYS` is worth knowing now. |
| 04 Local development | **Operator-scope, not repo-scope.** Deliberately not audited here — see below. |
| 05 Am I affected | **Yes, and it was used** — §2 and §3 are both outputs of this lesson's method. |
| 06 Emergency checklist | **Yes**, with the auto-deploy and webhook caveats in §5. |
| 07 Rotation order | **Yes.** The Tier 1 list should be written down before it is needed. |

### Deliberately not done

Lesson 4 opens with `find ~ -name ".env*"` and `grep -Ei 'token|password|secret|key=' ~/.zsh_history`.
**I did not run those, and would not record their output if I had.** They enumerate
credentials and machine paths across the operator's whole home directory, and CLAUDE.md
forbids writing machine paths to tracked files. These are worth running — by the operator, in
a terminal, with the results going nowhere near a repo:

```bash
find ~ -name ".env*" -type f 2>/dev/null | wc -l
```

```bash
composer global show
```

The course's own framing is the right one: the goal is not to harden the laptop like a server,
it is to stop the laptop being a single point of failure. The three highest-value items from
that lesson, none of which touch this repo: split SSH keys per purpose, a separate browser
profile for Forge/production consoles, and hardware-key 2FA on Forge and GitHub (it survives
session-cookie theft, which password-based 2FA does not).

---

## 7. Proposals arising (no code changed in this session)

1. ~~**`composer update league/commonmark`** (2.8.3 → 2.9.0) in the next gate-green batch — §3d.
   Single package, no constraint change, closes the one applicable advisory.~~
   **Shipped before publication**: `303beab` (2026-08-07) bumped it as part of the stack
   update, ~2h before these notes were committed. Verified 2026-08-10: lock at 2.9.0,
   audit clean. Nothing left to do here.
2. ~~**Widen the `.gitignore` env patterns to `.env.*` with `!.env.example`** — §4.~~
   **Shipped**: `a093baf` (2026-08-10) applied it verbatim; verified working at HEAD
   (`check-ignore` matches, `.env.example` still tracked). Nothing left to do here.
3. **Add `composer audit` to the quality gate.** It cost one command and surfaced §3 on its
   first run. Candidate: a `composer.json` script alongside the existing `filacheck` entry, so
   it runs with the rest of the gate rather than being remembered. Note it fails the build on
   *any* advisory, including inapplicable ones, so the team needs a documented way to record a
   reviewed-and-inapplicable finding — §3a is the worked example of that review.
4. **Write the Tier 1 rotation list down** — §5. Specifically: which token deploys, where the
   quick-deploy webhook URL lives, and who holds push access. A private note, not a repo file.
5. **Confirm the Forge deploy script uses `--no-dev --optimize-autoloader`, and confirm which
   user runs Composer on the server** — §4 items 9 and 10. Both unverified; both one look.

---

## 8. Sources

- [Course index](https://laraveldaily.com/course/laravel-security-composer) — 7 text lessons, May 2026.
  - [01 laravel-lang Attack Example](https://laraveldaily.com/lesson/laravel-security-composer/new-level-of-laravel-security-laravel-lang-attack-example)
  - [02 Best Composer Habits for Safety](https://laraveldaily.com/lesson/laravel-security-composer/best-composer-habits-for-safety)
  - [03 How to Secure Secret Keys and Passwords](https://laraveldaily.com/lesson/laravel-security-composer/how-to-secure-secret-keys-and-passwords)
  - [04 Secure Local Development](https://laraveldaily.com/lesson/laravel-security-composer/secure-local-development-protect-your-machine)
  - [05 How To Know If You're Affected](https://laraveldaily.com/lesson/laravel-security-composer/how-to-know-if-youre-affected)
  - [06 Emergency Response Checklist](https://laraveldaily.com/lesson/laravel-security-composer/emergency-response-checklist-what-to-do)
  - [07 What Secrets Need Rotation](https://laraveldaily.com/lesson/laravel-security-composer/what-secrets-usually-need-rotation-and-in-what-order)
- Advisories read directly:
  [GHSA-2q4p-g7hv-5rgv](https://github.com/advisories/GHSA-2q4p-g7hv-5rgv) (CVE-2026-71488),
  [GHSA-29pj-957v-52mc](https://github.com/advisories/GHSA-29pj-957v-52mc) (CVE-2026-71478),
  [GHSA-mh25-x5hq-wrqp](https://github.com/advisories/GHSA-mh25-x5hq-wrqp),
  [GHSA-mj63-m3rc-8ppr](https://github.com/advisories/GHSA-mj63-m3rc-8ppr).
  Not fetched: [GHSA-jfm3-95jq-q3rf](https://github.com/advisories/GHSA-jfm3-95jq-q3rf),
  [GHSA-g2gp-3wwq-f4ph](https://github.com/advisories/GHSA-g2gp-3wwq-f4ph) — ruled out by
  extension membership only.
- Measured on this repo, 2026-08-07: `composer audit`, `composer why`,
  `composer update league/commonmark --dry-run`; `laravel-lang` absence;
  `.gitignore` env patterns; Markdown call-site inventory; the two timing tables in §3b
  (ad-hoc scripts in the session scratchpad, not added to the repo).
- Source read directly: [SafeMarkdownRenderer.php](app/Support/Markdown/SafeMarkdownRenderer.php),
  `vendor/laravel/framework/src/Illuminate/Support/Str.php::markdown()`,
  `vendor/league/commonmark/src/Extension/GithubFlavoredMarkdownExtension.php`.

### What I could not obtain

- **The Aikido Security incident report** was linked but not read; the forensic narrative in §1
  is reported from the course, not verified.
- **Production configuration.** Whether the Forge deploy script actually passes `--no-dev
  --optimize-autoloader`, and which OS user runs Composer there, are both unverified — I did
  not reach into the production server from a research session.
- **Whether 2.9.0 actually removes the quadratic behaviour.** The before-numbers in §3b are
  measured; the after is not, because that needs the upgrade applied. Re-run the §3b crafted
  payloads after the update to confirm the fix rather than assuming it.
- **Anything about the operator's laptop** — deliberately, see §6.
