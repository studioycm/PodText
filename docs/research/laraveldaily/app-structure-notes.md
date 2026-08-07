# LaravelDaily: How to Structure Laravel 13 Projects — course notes

Notes on [How to Structure Laravel 13 Projects](https://laraveldaily.com/course/laravel-projects-structure)
(16 text lessons, 1h32m, **Mar 2026**), read 2026-08-07. Three lessons read in full.

**Short version: most of this course does not apply to PodText, and the one lesson that does
is the one arguing against the other fifteen.**

---

## 0. Staleness verdict: **passes, and is barely version-dependent anyway**

Mar 2026, Laravel 13-era. Uses `php artisan make:class`, current Form Request and
service-container idiom, and invokable controllers. Nothing dated appeared.

It ships a bonus **`laraveldaily-structure-audit`** agent skill as lesson 16 — the third
course in this round to do so. Same verdict as recorded in
[laraveldaily-queues-notes.md](queues-notes.md) §6: noted, not
installed.

---

## 1. Why most of it does not apply

The course's spine is a controller-extraction narrative: take a fat `store()` method and move
its parts outward into Form Request → DTO → Service/Action → Job → Event/Listener. Fifteen of
sixteen lessons are steps along that path.

PodText is not shaped that way. Measured:

| directory | files |
| --- | --- |
| `app/Support` | **206** |
| `app/Enums` | 47 |
| `app/Models` | 21 |
| `app/Http/Controllers` | **9** |
| `app/Jobs` | 4 |
| `app/Actions` | — does not exist |
| `app/Services` | — does not exist |
| `app/DataTransferObjects` | — does not exist |

Nine controllers in a codebase this size means the HTTP entry points are Filament resources
and Livewire components, not controllers. The logic the course wants extracted *from*
controllers already lives in `app/Support` as managers, resolvers, and renderers — an
equivalent destination under a different name.

So the course's central question ("where should this controller logic go?") is one this
project answered long ago, differently but consistently. The naming difference is not
interesting: the course itself says repeatedly that Service-vs-Action is a preference, and
`app/Support` is a third valid answer to the same question.

**No proposal to rename or restructure anything.** A sweep from `Support/` to `Services/` or
`Actions/` would be pure churn across 206 files, and the course gives no argument that would
justify it.

---

## 2. The lesson that does apply: "When NOT to Extract"

Lesson 14 is the one worth reading, and it is unusual for a course of this kind — it spends
its length arguing against its own preceding thirteen lessons.

> reaching for a pattern when you don't need it is just as bad as putting everything in the
> Controller

Its decision framework, which is the transferable part:

1. **Is this code complex enough to justify its own file?** Three to five lines: probably not.
2. **Is it reused from multiple places?** Used once: extraction adds indirection without value.
3. **Will a reader have to jump to another file to understand what happens?** If the extracted
   class only wraps a simple operation, the code got harder to read, not easier.

If all three answers are "no", leave it where it is.

Supporting positions, each with a concrete trigger rather than a slogan:

- **Events add indirection.** Use them when several *unrelated* things react to one action, or
  when future code should react without modifying existing code. Do not use them when there is
  exactly one consequence and cause-and-effect should be read together — "if creating a user
  always and only sends a welcome email, calling `Mail::send()` directly is clearer than
  `UserCreated::dispatch()` → `SendWelcomeEmailListener`."
- **Traits: wait for the third duplication.** *"Duplication is cheaper than the wrong
  abstraction."*
- **DTOs** earn their keep when data crosses several layers; not for three fields.
- **Pipelines** only when steps are dynamic, reordered, or independently testable.
- The closing argument: *"A junior developer who can follow the code in a Controller is better
  off than one lost in a maze of Actions, DTOs, Pipelines, and Events they can't trace."*

### Why this matters here specifically

This is an outside restatement of a position this project already reached under pressure and
recorded — the complexity calm-down review of 2026-07-17, which went through planned
post-SP3A/B complexity and neutralised most of it, and the subsequent demotion of the
`laravel-simplifier` skill to a secondary, on-request audit rather than an enforcement gate.

The value of lesson 14 is not that it teaches something new. It is that it gives an
**independent, non-project-specific articulation of the same rule**, which is useful when the
question comes up again — and it will, because every pattern in lessons 1-13 is individually
defensible. The three questions above are a better artefact for that argument than "we decided
this in July".

**Proposal: none, beyond keeping this reference to hand.** Specifically *not* proposing that
this become a rule in `.ai/` — the project already has its own recorded position, and adding a
second source of truth for the same rule is itself the kind of unnecessary structure the lesson
warns about.

---

## 3. Applies here vs. generic

| lesson | applies to PodText? |
| --- | --- |
| 14 When not to extract | **Yes** — the only one. Corroborates an existing position, §2. |
| 04 Service vs Action | **No** — `app/Support` is the established answer; renaming would be churn. |
| 13 Reusing logic everywhere | **No, already true** — `app/Support` managers are called from Filament, Livewire, jobs, and commands alike. The course's payoff argument is a description of the current state. |
| 01 Form Requests, 02 DTOs, 05 Pipelines, 06 Jobs, 07 Events, 08 Base controllers/traits, 09 Helpers | Generic; controller-centric framing that does not match a Filament/Livewire app. |
| 10 Admin/user role areas, 11 Modules/packages | Not read; unlikely to apply — panel separation is already a Filament concern here. |
| 12 Blade components | Not read; this repo already has an extensive `resources/views/components/public/` set. |
| 16 Bonus AI skill | Noted, not installed. |

---

## 4. Sources

- [Course index](https://laraveldaily.com/course/laravel-projects-structure), Mar 2026, 16 text lessons.
  Read: [when not to extract](https://laraveldaily.com/lesson/laravel-projects-structure/when-not-to-extract-avoiding-over-engineering),
  [save data: service or action](https://laraveldaily.com/lesson/laravel-projects-structure/save-data-service-action),
  [reusing logic everywhere](https://laraveldaily.com/lesson/laravel-projects-structure/beyond-the-controller-reusing-logic-everywhere).
- This repo, measured 2026-08-07: file counts under `app/`.

### What I could not obtain / did not read

13 of 16 lessons. Given §1 — the course's premise does not match this codebase's shape — the
remaining lessons were judged unlikely to repay the reading, and that is a judgement call
rather than a measurement. If anyone disagrees, `structure-admin-user-role-areas` and
`modules-packages` are the two most likely to contain something, and neither was opened.
