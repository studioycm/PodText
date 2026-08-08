# LaravelDaily: Multi-Language Laravel 11 — course notes

- **URL**: https://laraveldaily.com/course/multi-language-laravel — 18 lessons, 1h20m, text, card says **Aug 2024**
- **Read**: 2026-08-08 from `raw/` — staleness probe over all 18 bodies first, then 4 lessons
  in full, package-survey lessons verified against live packagist instead of read at face value
- **Verified against**: this repo's `lang/` tree, live packagist metadata (2026-08-08), PHP `intl`
- **Staleness verdict**: **mixed — a re-badged course.** The card date is not the authorship
  date. Core localization lessons survive because the API is old and stable; the plumbing
  details are Laravel-10-era; one surveyed package is abandoned.

**Headline: the probe mattered more than the reading.** The evidence says this course was
written ~2022–23 and re-badged "Laravel 11", and PodText already implements everything its
stable half teaches — in Hebrew, with one deliberate divergence (§3).

---

## 1. The probe — how the re-badge shows

Run over all 18 lesson bodies before reading any (markers per [README](README.md) §2):

| marker | where | meaning |
| --- | --- | --- |
| Comments "3 years ago" | on 9 of 18 lessons (2026 − 3 ≈ **2023**) | true authorship era, vs the Aug 2024 card |
| "in Laravel 10 that lang folder is not included" | lesson 1 | L10-era authorship, plus a mangled sentence about the old `resources/lang` location — sloppy re-badge editing |
| `app/Http/Kernel.php` middleware registration | mcamara lesson | **Kernel.php was removed in Laravel 11** — those install steps do not work as written on 11+ |
| `resources/lang` paths | mcamara, outhebox lessons | pre-Laravel-9 location |
| `protected $casts = []` | translating-db-models lesson | pre-L11 idiom |

None of this kills the course's *concepts* — `__()`, `trans_choice`, `lang/` files and JSON
translations have been stable for many majors. It kills trust in its *plumbing instructions*
and, critically, in its package survey being current. Hence §4.

## 2. The stable half — four lessons read in full, all already implemented here

| lesson | teaches | PodText, measured |
| --- | --- | --- |
| [PHP vs JSON files](https://laraveldaily.com/lesson/multi-language-laravel/php-json-trans-underscores) | PHP files: nested keys, per-feature split, comments; JSON: prose keys for translators, no nesting | **PHP-file route, exactly as their benefit list describes**: `lang/{en,he}/` with 4 domain files (`admin`, `app`, `authz`, `public`), no JSON files, 3 239 `__()` calls |
| [Plural/singular forms](https://laraveldaily.com/lesson/multi-language-laravel/translating-plural-singular-forms) | `trans_choice` + `{0}…|{1}…|[2,*]…`, `@choice`, PHP keys beat JSON for this | **31 pipe-pluralised Hebrew strings** in `lang/he/{public,admin,authz}.php`, e.g. `'{0} אין פרקים|{1} פרק אחד|[2,*] :count פרקים'`; 54 `trans_choice`/`@choice` call sites (reading minutes, word counts, filter counts) |
| [Validation messages](https://laraveldaily.com/lesson/multi-language-laravel/translating-validation-messages) | `attributes()` on Form Requests so messages match labels; `products.*.name` + `:index` for arrays | Different surface here: 0 Form Requests — Filament fields carry translated labels and validation runs against them, which achieves the same match. The `attributes()`/`:index` mechanics are worth knowing if a plain controller form ever appears |
| [Dates & currencies](https://laraveldaily.com/lesson/multi-language-laravel/localizing-dates-currencies) | `Carbon::setLocale(app()->getLocale())` + `isoFormat`; raw `NumberFormatter` (needs `intl`) wrapped in a function helper | See §3 for the deliberate date divergence. For numbers, PodText uses **Laravel's `Number` facade** (`Number::fileSize` ×3) — the framework's own wrapper over the same intl machinery, more current than the lesson's hand-rolled helper. `intl` loaded, verified |

## 3. The one deliberate divergence: no `Carbon::setLocale`

Measured: **zero** `Carbon::setLocale`/`translatedFormat` anywhere in `app/` or `config/`.
That is not a gap by house rules — date-time presentation here is the fixed Hebrew/Israel
**day-first format** (`dd/mm/yyyy`, `Asia/Jerusalem`) mandated by every `.ai` guideline, not
locale-negotiated month names. The lesson's approach (localised month names via
`isoFormat('dddd, D MMMM YYYY')`) is what you reach for if the public UI ever wants prose
dates ("8 באוגוסט 2026") rather than numeric ones. **Recorded as a decision, not a defect** —
and as the one-line mechanism (`Carbon::setLocale` in a provider) if that presentation choice
is ever revisited.

## 4. The package survey — re-verified against packagist, 2026-08-08

The course's survey half (6 lessons) is where a re-badged course rots fastest, so the
packages were checked live instead of trusting the lessons:

| package | course presents as | live state | verdict |
| --- | --- | --- | --- |
| `spatie/laravel-translatable` | model-translation option (JSON-column) | 6.14.1, 2026-04, **L13 ✓** | alive |
| `astrotomic/laravel-translatable` | model-translation option (translation tables) | v11.17.0, 2026-03, **L13 ✓** | alive |
| `mcamara/laravel-localization` | URL-prefix locale routing | v2.4.1, 2026-07, **L13 ✓** | alive — but the lesson's Kernel.php install steps are for ≤L10; follow the package README, not the lesson |
| `nikaia/translation-sheet` | Google-Sheets translation workflow | v1.7.1, **2024-05, ABANDONED**, no L12/13 | **dead — the lesson presents a dead option** |
| `outhebox/laravel-translations` | translation-management UI | v2.1.1, 2026-07, **L13 ✓** | alive (lesson slug says "mohmmedashraf"; vendor is `outhebox`) |
| `spatie/laravel-translation-loader` | DB-backed translation source | 2.8.3, 2026-02 | alive |

Cross-reference the course could not have known: it also name-drops **`laravel-lang/common`**
for pre-translated framework strings — the package family at the centre of the **May 2026
supply-chain compromise** ([supply-chain-security-notes.md](supply-chain-security-notes.md)).
Not installed here (verified then); if pre-translated Hebrew validation strings are ever
wanted, apply that note's §2 triage to the package first.

## 5. The model-translation lessons — digest-only, by design

`translating-db-models-no-packages` (14.7k), `translating-models-livewire` (8.1k),
`spatie-laravel-translatable` (11.4k), `astrotomic-laravel-translatable` (11.6k) were
**digested, not read**. They answer a question PodText has not asked: translating *content*
(episode titles, transcripts) into a second language. The public product is Hebrew-only;
`app.locale = he`, `fallback = en`, and the `en` tree exists for fallback, not for a second
audience. If a second content language ever becomes a product decision, these four lessons
plus the two live packages above are the mapped starting point — with both packages verified
L13-compatible today, which is the part that would actually have rotted.

## 6. Applies here vs. generic

**No proposals.** The stable half is already implemented (§2); the divergence is deliberate
(§3); the survey half needed live re-verification, which is now recorded (§4); the
model-translation half awaits a product decision that has not been made (§5).

## 7. Sources

- Probe over all 18 bodies in `raw/multi-language-laravel.json`; read in full:
  [php-json-trans-underscores](https://laraveldaily.com/lesson/multi-language-laravel/php-json-trans-underscores),
  [translating-plural-singular-forms](https://laraveldaily.com/lesson/multi-language-laravel/translating-plural-singular-forms),
  [translating-validation-messages](https://laraveldaily.com/lesson/multi-language-laravel/translating-validation-messages),
  [localizing-dates-currencies](https://laraveldaily.com/lesson/multi-language-laravel/localizing-dates-currencies).
- Live packagist (`repo.packagist.org/p2/*.json`, 2026-08-08) for the six surveyed packages —
  version, release date, illuminate constraints, abandoned flag.
- This repo, measured 2026-08-08: `lang/` tree; `config app.locale/fallback/faker`; `__()`
  count (3 239); pluralised-string and `trans_choice` counts; `Carbon::setLocale` absence;
  `Number::` usage; `php -m | grep intl`.

### What I could not obtain

- The four model-translation lessons' full text remains unread — deliberate (§5), and the
  corpus holds it for the day it is needed.
- The course's true authorship date — inferred from comment ages and internal markers, not
  stated anywhere by the site. This is the strongest instance yet of the README's
  "Released is a floor, not a guarantee" rule, in the *other* direction: a card date newer
  than the content.
