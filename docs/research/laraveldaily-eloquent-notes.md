# LaravelDaily: Laravel 13 Eloquent Expert — course notes

Notes on [Laravel 13 Eloquent: Expert Level](https://laraveldaily.com/course/laravel-eloquent-expert)
(41 video lessons, 1h34m, **Mar 2026**), read 2026-08-07.

Per the operator's instruction this was **skimmed for relationship optimization**, not watched
linearly. **5 of 41 lessons** were read in full via Vimeo auto-captions (extraction method in
[laraveldaily-livewire-v4-notes.md](docs/research/laraveldaily-livewire-v4-notes.md) §1);
the other 36 were not. What was skipped is listed in §6.

Every claim below was re-verified against `laravel/framework v13.23.0` as installed.

**Two findings worth the session:** the course confirms the parked
effective-transcription conclusion, and I can now say *why* at the framework-source level
(§2); and the course's headline N+1 recommendation would, if adopted here, **silently disable
a guard this repo already relies on** (§3).

---

## 0. Staleness verdict: **passes**

Mar 2026, Laravel 13-era. The N+1 lesson explicitly discusses `automaticallyEagerLoadRelationships`
as "released pretty recently in Laravel 12.9" — a fine-grained, current version claim, and it
checks out: the method exists at
`vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php:586`. Nothing pre-11
idiom appeared in the lessons read.

Like the queues course, it ships a bonus **`laraveldaily-eloquent-audit`** agent skill as
lesson 41. Same verdict as recorded in
[laraveldaily-queues-notes.md](docs/research/laraveldaily-queues-notes.md) §6 — noted, not
installed, and for the same reasons.

---

## 1. What the read lessons actually say

| lesson | substance |
| --- | --- |
| 18 — Subqueries and subselects | `addSelect(['last_post' => Post::select('created_at')->whereColumn('user_id', 'users.id')->latest()->take(1)])`. The demo's honest result: duration barely improved, but **memory dropped hard** (91 MB → much lower) because you fetch one scalar per parent instead of hydrating a related model. Framed as a rare-but-useful optimisation, not a default. |
| 22 — `load()` and `$with` | You can eager-load after the fact with `->load('user')`. You can also set `protected $with = ['user']` on the model — and the lesson **argues against it**: it loads the relation even where it is not needed, and the next developer editing that controller has no way to see it. Verdict: eager-load explicitly. |
| 23 — Detect and prevent N+1 | `Model::preventLazyLoading(! app()->isProduction())` throws on any lazy load outside production. `Model::automaticallyEagerLoadRelationships()` (Laravel 12.9+) instead *fixes* it silently. Course advice: "you should probably always use automatically eager load relationships." **See §3.** |
| 26 — `whereHas` vs `join` vs raw | Measured on 30 000 posts: Eloquent 3 queries / 77 MB / 0.4 s; Query Builder 2 queries / 66 MB / faster; raw `DB::select` ≈ Query Builder. Query Builder is close to raw. The stated cost is losing accessors/mutators and model features. |
| 34 — One of many | `latestOfMany()`, `oldestOfMany()`, `ofMany('price', 'max')`, `ofMany(['price' => 'max', 'updated_at' => 'min'])`, and `ofMany([...], fn ($q) => $q->where('title', 'like', …))`. **See §2.** |

---

## 2. `oneOfMany` and the parked effective-transcription refactor

The parked 2b refactor wants one **eager-loadable** `HasOne` expressing *featured if set, else
latest published*, and its recorded conclusion is that **`ofMany` cannot express it**. Lesson
34 is the course's full treatment of `ofMany`, so it is the natural place to check whether
anything was missed.

**It confirms the conclusion, and nothing in the lesson unblocks it.** The four forms it
teaches are: aggregate on `id`, aggregate on another column, multiple aggregate columns as
tiebreakers, and a closure that adds *filtering*. None of them makes the winner depend on a
value that lives on the parent row.

### Why, from the framework source — the part the course does not give

`Illuminate/Database/Eloquent/Relations/Concerns/CanBeOneOfMany.php`:

1. **The aggregate is restricted to MIN/MAX**, enforced with a throw (`:94-96`):
   ```php
   if (! in_array(strtolower($aggregate), ['min', 'max'])) {
       throw new InvalidArgumentException("Invalid aggregate [{$aggregate}] used within ofMany relation. Available aggregates: MIN, MAX");
   }
   ```
   No `CASE`, no expression, no arbitrary ordering key.

2. **The subquery is built on the related model alone**, grouped by the foreign key
   (`newOneOfManySubQuery`):
   ```php
   $subQuery = $this->query->getModel()->newQuery()->withoutGlobalScopes(...);
   foreach (Arr::wrap($groupBy) as $group) { $subQuery->groupBy($this->qualifyRelatedColumn($group)); }
   ```
   It selects only `MAX(...) as x_aggregate` / `min(...)` columns.

3. **That subquery is then attached with `joinSub`** (`addOneOfManyJoinSubQuery`) — i.e. as a
   **derived table**.

The closure form runs against *that* subquery. So a constraint like
`whereColumn('transcriptions.id', 'content_items.featured_transcription_id')` would emit a
reference to the parent table from inside a derived table that does not have it in scope.
Derived tables are not correlated (absent `LATERAL`, which Eloquent does not emit here).
**The rule is not expressible in `ofMany` for structural reasons, not for want of API surface.**

### What the course *does* contribute, indirectly

Lesson 18's subselect is the complementary half, and it maps exactly onto the approach already
recorded for the parked refactor (a correlated subquery). The distinction worth writing down:

| technique | can express a parent-dependent winner? | gives a hydrated model? | eager-loadable? |
| --- | --- | --- | --- |
| `ofMany` / `latestOfMany` | **no** (MIN/MAX over child columns only) | yes | yes |
| `addSelect` correlated subselect (lesson 18) | yes | **no** — a scalar column | n/a |
| `HasOne` + correlated subquery in `ORDER BY` | yes | yes | yes |

The third row is the parked approach, and it is the only one of the three that satisfies all
three columns. **Nothing here changes that decision** — recorded so the `ofMany` avenue does
not get re-opened by someone reading lesson 34 in isolation.

For context on what is being replaced: the current
[`ContentItem::workspaceTranscription()`](app/Models/ContentItem.php:156) branches on
`$this->featured_transcription_id` **while building the relation**, which is precisely why it
carries a docblock warning not to eager-load it from list or collection queries. That
instance-conditional shape is the thing the refactor removes.

---

## 3. The N+1 recommendation would disable a guard this repo already has

Measured — PodText already implements the stricter half of lesson 23, at
[AppServiceProvider.php:148](app/Providers/AppServiceProvider.php:148):

```php
Model::preventLazyLoading(! $this->app->isProduction());
```

exactly as the lesson teaches, production guard included. `automaticallyEagerLoadRelationships()`
is **not** enabled.

The course's closing advice is to "always use automatically eager load relationships". **Do not
apply that here without deciding deliberately**, because the two features are not additive.
From `HasAttributes::getRelationValue()` (`:563-582`):

```php
if ($this->attemptToAutoloadRelation($key)) {
    return $this->relations[$key];
}

if ($this->preventsLazyLoading) {
    $this->handleLazyLoadingViolation($key);
}
```

**Autoloading is attempted first and returns early.** Wherever it succeeds, the lazy-loading
violation never fires. Enabling it would therefore not *add* protection on top of
`preventLazyLoading` — it would **replace the local exception with a silent fix**, and the N+1
signal this repo currently gets in development would stop appearing for exactly the cases
autoloading handles.

That is a policy choice between *surface the problem* and *paper over the problem*. This repo
currently chooses to surface it. The course does not mention the interaction at all.

**Recommendation: no change.** Keep `preventLazyLoading`, leave
`automaticallyEagerLoadRelationships` off. Recorded so this is a decision on the record rather
than an omission — and so that a future reader of lesson 23 does not enable it as an obvious win.

---

## 4. `protected $with` — an existing-convention check

Lesson 22's warning against model-level `$with` is worth checking against a codebase that has
strict lazy-load prevention on: with `preventLazyLoading` enabled there is standing pressure to
"just add it to `$with`" whenever the exception fires, which is the failure mode the lesson
describes.

**Checked: `protected $with` appears nowhere in `app/Models/`.** No model-level eager loading
has crept in, so the pressure has not produced any. Nothing to do; recorded as a clean result
rather than an open item.

---

## 5. Applies here vs. generic

| lesson | applies to PodText? |
| --- | --- |
| 34 One of many | **Yes** — confirms the parked refactor's conclusion, §2. No action. |
| 18 Subqueries/subselects | **Yes, as background** to the parked refactor. Also a real option for list pages that need one scalar from a relation rather than the whole model. |
| 23 Detect/prevent N+1 | **Yes, and the answer is "no change"** — §3. Half already implemented; the other half would be a regression here. |
| 22 `load()` / `$with` | Mostly generic. The `$with` caution was checked and is clean, §4. |
| 26 whereHas vs join vs raw | Generic. The measured trade-off (Query Builder ≈ raw, but loses accessors/mutators) is worth knowing before anyone optimises a public listing query by dropping to `DB::table()` — this codebase leans on model accessors and enum casts, so that trade would cost more here than in the demo. |

---

## 6. What I could not obtain / did not read

- **36 of 41 lessons went unread.** This was a deliberate skim per the operator's instruction,
  not a limitation. The unread set includes several that plausibly matter and are candidates
  for a follow-up pass:
  - `model-casts-dates-enum` — directly adjacent to the `casts()` / larastan finding in
    [larastan-playbook.md](docs/research/larastan-playbook.md) §1. **Highest-value unread lesson.**
  - `local-global-scopes` — adjacent to the public-visibility scopes and to the
    `Builder<Model>` typing debt in `larastan-playbook.md` §4b.
  - `withcount`, `query-relationship-data`, `has-many-through`, `advanced-belongs-to-many`,
    `polymorphic-relations` — `MediaAttachment` is polymorphic here (`attachable_type`), so the
    polymorphic lessons may be relevant.
  - `n1-query-problem-debugbar-eager-loading`, `n1-query-packages-examples`,
    `query-result-caching`, `api-reponse-optimization`, `hidden-visible-appends-model-serialization`.
  - `prunable-and-massprunable`, `model-observers`, `wascreated-isdirty`, `firstorcreate-methods`.
- **The bonus skill's full text** — only the lesson page was read; the GitHub raw file was
  deliberately not fetched.
- **The course's demo repository**, if one exists — not located; all code above was
  reconstructed from captions and then confirmed against framework source.
- Auto-caption caveat, as in the Livewire notes: the ASR mangles identifiers (*"Larval"*,
  *"eloquence"*, *"bullying condition"*, *"of many"*). **No API name in this document comes
  from a caption** — each was confirmed in `vendor/laravel/framework`.

---

## 7. Sources

- [Course index](https://laraveldaily.com/course/laravel-eloquent-expert), Mar 2026, 41 video lessons.
  Read: [18 subqueries and subselects](https://laraveldaily.com/lesson/laravel-eloquent-expert/subqueries-and-subselects),
  [22 eager loading with load() and $with](https://laraveldaily.com/lesson/laravel-eloquent-expert/eager-loading-load-and-with),
  [23 detect and prevent N+1](https://laraveldaily.com/lesson/laravel-eloquent-expert/detect-and-prevent-n1-query),
  [26 whereHas vs join vs raw SQL](https://laraveldaily.com/lesson/laravel-eloquent-expert/eloquent-query-builder-wherehas-vs-join),
  [34 one record from many](https://laraveldaily.com/lesson/laravel-eloquent-expert/oneofmany).
- Framework source read directly at v13.23.0:
  `Eloquent/Relations/Concerns/CanBeOneOfMany.php` (`ofMany`, `newOneOfManySubQuery`,
  `addOneOfManyJoinSubQuery`), `Eloquent/Concerns/HasAttributes.php:563-582`
  (`getRelationValue` guard order), `Eloquent/Model.php:562-586`
  (`shouldBeStrict`, `preventLazyLoading`, `automaticallyEagerLoadRelationships`).
- This repo, measured 2026-08-07: [ContentItem.php:126-202](app/Models/ContentItem.php:126)
  (relations and effective-transcription resolution),
  [AppServiceProvider.php:148](app/Providers/AppServiceProvider.php:148).
