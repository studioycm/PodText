# LaravelDaily catalogue — all courses, grouped

Full course inventory captured **2026-08-07**. **63 courses**, Feb 2021 → Jul 2026.

Titles and metadata are the site's; relevance notes are ours. Grouped by subject, **newest
first within each group**.

Status key: **✅ read** · **◐ partly read** · **★ shortlisted** · **⛔ confirmed stale, do not revisit**

> Supersedes the earlier "57 courses" figure — that was either wrong or predates recent
> additions. Re-count before trusting this number after ~Oct 2026.

---

## How to re-capture this list

The catalogue is **Livewire-rendered**: a raw same-origin `fetch()` of `/courses` returns
pre-hydration HTML with **zero** course links. You must `navigate` to it and read the live DOM.

Cards link to the course's **first lesson**, not to `/course/<slug>` — the course slug is the
middle path segment:

```js
[...new Set(Array.from(document.querySelectorAll('a[href*="/lesson/"]'))
  .map(a => a.getAttribute('href').split('/lesson/')[1].split('/')[0]))]
```

Metadata (lesson count, duration, format, release month, your progress %) comes from walking up
to the card element that contains both `Released` and `Lessons`.

---

## The site has five content types, not one

Search results (`/search?q=<term>`) return a mixed feed. Measured on `?q=api`: **27 results**,
spanning **2017–2026**.

| type | URL shape | notes |
| --- | --- | --- |
| **Course** | `/course/<slug>` | multi-lesson, premium — everything below |
| **Tutorial** | `/post/<slug>` | long-form articles, mostly premium, archive back to **2017** |
| **Video** | `/video/<slug>` | short standalone, mostly **free** |
| **Tip** | `/tip/<slug>` | very short |
| **Package** | `/package/<slug>` | package write-ups |

**This matters for freshness.** On `api`, the *course* is Mar 2026 but the tutorials are
2017–2025 — the article archive is far staler than the course catalogue, and search mixes them
without distinction. Anything found by search needs its type and date checked before reading.

Tag links (`/tag/<slug>`) appear on results but are **per-result labels, not a global taxonomy** —
there is no browsable tag index from search.

Search returned all 27 results without pagination; whether longer result sets paginate is
**unverified**.

---

## Group 1 — Eloquent & data

| date | course | size | status |
| --- | --- | --- | --- |
| Mar 2026 | Laravel 13 Eloquent: Expert Level · `laravel-eloquent-expert` | 41L video+text | ✅ 24/41 full + all digested — rewritten 2026-08-08 |
| Apr 2025 | Structuring Databases in Laravel 12 · `structuring-databases-laravel` | 18L text | ✅ 18/18 covered — finished 2026-08-08 |
| Sep 2023 | Laravel GroupBy: Practical Examples · `laravel-groupby` | 13L text | |
| Jul 2022 | Laravel Collections Chains: 15 Real Examples · `laravel-collections` | 16L video | |
| Oct 2021 | Better Eloquent Performance · `eloquent-performance` | 21L video | topic matters, age brutal |

## Group 2 — Testing & code quality

| date | course | size | status |
| --- | --- | --- | --- |
| Apr 2026 | Testing in Laravel 13 For Beginners · `testing-laravel` | 26L text | **★** only *current* testing course |
| Apr 2025 | Laravel API Code Review and Refactor · `laravel-api-review` | 15L text | review method |
| Sep 2024 | Testing in Laravel 11: Advanced Level · `testing-advanced` | 31L text | L11 era |
| Mar 2023 | Larastan: Catch Bugs with Static Analysis · `larastan` | 8L text | ⛔ pre-rename, level-9 claim |
| Dec 2021 | 10+ Laravel Refactoring Examples · `laravel-refactoring` | 12L video | |

## Group 3 — Queues & infrastructure

| date | course | size | status |
| --- | --- | --- | --- |
| Mar 2026 | Queues in Laravel 13 · `queues-laravel` | 18L text | ✅ 18/18 — rewritten 2026-08-08 |
| Mar 2025 | Laravel Reverb: Four "Live" Examples · `laravel-reverb` | 5L text | realtime, not in stack |
| May 2023 | Practical Laravel Queues on Live Server · `laravel-queues-server` | 7L text | ⛔ php8.1, `queue:listen`, 4-trait stub |
| Jan 2023 | Deploy Laravel to AWS EC2 · `deploy-laravel-aws-ec2` | 7L text | we're on Forge |

## Group 4 — Security & auth

| date | course | size | status |
| --- | --- | --- | --- |
| Apr 2026 | Roles and Permissions in Laravel 13 · `roles-permissions` | 14L video | ✅ 3 full + digest — notes written 2026-08-08 |
| May 2026 | Practical Laravel Security: Packages, Secrets, Supply-Chain · `laravel-security-composer` | 7L text | ✅ 7/7 |

## Group 5 — Filament

| date | course | size | status |
| --- | --- | --- | --- |
| Aug 2025 | Filament 4/5 From Scratch · `filament-4` | 28L video | **the only one** — see note below |

**One Filament course exists on the entire site**, and it is beginner-oriented. For a
Filament 5 app this is the single biggest gap in the catalogue. Recommend a 2-lesson staleness
probe to see whether it covers v5 specifics at all, then decide — rather than assuming.

## Group 6 — Livewire

| date | course | size | status |
| --- | --- | --- | --- |
| Jan 2026 | Livewire v3 to v4: Changes You Need to Know · `livewire-v4` | 7L video | ✅ 7/7 |
| Mar 2025 | Livewire 3 for Beginners w/ L12 Starter Kit · `livewire-beginners` | 19L video | v3 era |
| Feb 2024 | Practical Livewire 3: Order Management · `livewire-order-management-system` | 17L text | v3 era |

Three Livewire courses, **only one covering v4** — and it is 31 minutes. Two of three are
Livewire 3.

## Group 7 — Architecture & patterns

| date | course | size | status |
| --- | --- | --- | --- |
| Mar 2026 | How to Structure Laravel 13 Projects · `laravel-projects-structure` | 16L text | ✅ 16/16 — finished 2026-08-08 |
| Oct 2025 | Laravel Modules and DDD · `laravel-modules-ddd` | 16L video | |
| Dec 2024 | Laravel Project PROCESS: Start to Finish · `laravel-project-process` | 16L text | |
| Aug 2024 | Design Patterns in Laravel 11 · `design-patterns` | 17L text | **★ selected** |
| Aug 2021 | SOLID Code in Laravel · `solid-laravel` | 21L video | |

## Group 8 — Errors & validation

| date | course | size | status |
| --- | --- | --- | --- |
| Mar 2025 | Handling Exceptions and Errors in Laravel 12 · `exceptions-errors-laravel` | 8L text | ✅ 8/8 — rewritten 2026-08-08; sample code broken |
| Jul 2023 | Laravel Array Validation · `laravel-array-validation-all-you-need-to-know` | 7L text | |

## Group 9 — APIs & integrations

| date | course | size | status |
| --- | --- | --- | --- |
| Mar 2026 | How to Build Laravel 13 API From Scratch · `api-laravel` | 30L video | current, but 30L for a thin API surface |
| Jul 2025 | Laravel HTTP Client and 3rd-Party APIs · `laravel-http-client-api` | 7L video | **★** Spotify fetching — verify vs L13 |
| Sep 2023 | Laravel Web to Mobile API: Reuse Code with Services · `laravel-web-api-services` | 10L text | |

**Verify any API material against Laravel 13.** The `api` search surfaced tutorials from 2017,
2019, 2022 and 2023 alongside the Mar 2026 course, with no visual distinction.

## Group 10 — Localisation & UI

| date | course | size | status |
| --- | --- | --- | --- |
| Mar 2025 | Tailwind CSS v4 for Laravel Developers · `tailwind-laravel` | 9L text | **★** we run Tailwind 4 |
| Aug 2024 | Multi-Language Laravel 11: All You Need to Know · `multi-language-laravel` | 18L text | ✅ probed + 4 full — **re-badged, ~2023 authorship**; stable half already implemented here; translation-sheet pkg abandoned |
| May 2023 | Laravel User Timezones · `laravel-user-timezones` | 11L text | **★ selected** — `Asia/Jerusalem` presentation |
| Feb 2022 | Practical Alpine.js From Scratch · `alpine-js` | 19L video | Livewire bundles Alpine |

## Group 11 — AI & agent tooling

| date | course | size | status |
| --- | --- | --- | --- |
| May 2026 | AI Agents/IDEs for Laravel: May 2026 · `ai-agents-laravel-2026` | 7L video | ✅ 7/7 — finished 2026-08-08; the only current one |
| Feb 2026 | Laravel AI SDK: 6 Practical Examples · `laravel-ai-sdk` | 9L video | **★ selected** — incl. speech-to-text |
| Nov 2025 | Laravel Coding with AI Agents: Cursor, Claude Code, Codex · `laravel-ai-agents-cursor-claude-code-codex` | 5L video | superseded |
| Aug 2025 | PhpStorm Junie AI · `phpstorm-junie-ai` | 7L video | superseded |
| Jul 2025 | Claude Code for Laravel: Crash Course · `claude-code-laravel` | 8L video | superseded |
| Jun 2025 | Cursor for Laravel: Crash Course · `cursor-for-laravel-2025` | 14L video | superseded |
| Jan 2025 | Cursor AI: Hotel Booking Website · `cursor-ai-laravel` | 17L video | superseded |

**Shortest shelf life on the site.** Anything older than ~6 months here is expired regardless
of whether its APIs still exist.

## Group 12 — Media, packages, features

| date | course | size | status |
| --- | --- | --- | --- |
| Feb 2023 | Laravel Pennant Overview · `laravel-pennant` | 10L video | feature flags |
| Jan 2023 | How to Create Laravel Package · `create-laravel-package` | 12L text | |
| Feb 2021 | File Uploads in Laravel · `laravel-file-uploads` | 10L video | media handling, very old |

## Group 13 — Not applicable to this project

Kept for completeness so they are not re-evaluated each round.

**Mobile** — `react-native-flutter-nativephp` (Jun 2026) · `nativephp-mobile-v3` (Feb 2026) ·
`react-native-laravel` (May 2025) · `flutter-mobile-app-laravel` (Mar 2025)

**JS front-ends** — `nextjs-laravel` (Apr 2026) · `vue-laravel-starter-kit` (Mar 2025) ·
`react-laravel-starter-kit` (Mar 2025) · `laravel-vue-inertia-food-delivery` (Aug 2023) ·
`react-client-laravel-api` (Mar 2023) · `graphql-laravel` (Nov 2021)

**Beginner / general** — `laravel-13-beginners` (Mar 2026, free) · `laravel-from-scratch`
(Sep 2025) · `php-laravel` (Nov 2023)

**Other stacks / business** — `marketing-for-developers` (Jul 2026) · `laravel-13-teams`
(Apr 2026) · `laravel-saas` (Dec 2025) · `laravel-multi-tenancy` (May 2025) ·
`laravel-cms-review` (Oct 2024) · `laravel-reservations` (Jun 2023)

---

## Cross-cutting observations

**Eight courses carry "Laravel 13" in the title** — `laravel-13-beginners`, `laravel-13-teams`,
`roles-permissions`, `testing-laravel`, `api-laravel`, `laravel-eloquent-expert`,
`queues-laravel`, `laravel-projects-structure`. Those are the safest starting points for
current-framework material.

**A title's version number is a claim, not a guarantee.** `exceptions-errors-laravel` is titled
"Laravel 12" and carries a 2023 log sample — re-badged, not rewritten. Check content markers,
not the title.

**Format is not binary.** Some video courses carry full authored text bodies (Eloquent), some
carry only a repo link (Livewire), some carry nothing (AI agents). Always check `.prose`.

**Coverage skews away from our stack.** Of 63 courses: 1 Filament, 3 Livewire (1 on v4),
0 on static analysis that isn't stale, 0 on RTL specifically. The mobile and JS-front-end
groups together are 10 courses — more than Filament and Livewire combined.
