# LaravelDaily: Laravel 13 Eloquent Expert — course notes

- **URL**: https://laraveldaily.com/course/laravel-eloquent-expert — 41 lessons, 1h34m, video **with full text bodies**, **Mar 2026**
- **Read**: 2026-08-07 (5 lessons via captions) + 2026-08-08 (**19 lessons in full from `raw/`, all 41 digested** — headings + identifiers extracted per lesson)
- **Verified against**: `laravel/framework v13.23.0`; this repo's models as of today (including the concurrent sessions' scope/attribute migration)
- **Staleness verdict**: **passes, and is the most current course on the site for framework surface** — it teaches the Laravel 13 model attributes (`#[Fillable]`, `#[Scope]`, …) that this repo adopted this week

Full rewrite of the first version (5/41, captions only). Its two load-bearing conclusions
survive re-verification and are kept in §1; everything else is new.

**Headline: the tree has already adopted this course's "modern" recommendations — in some
cases days ago, by the concurrent larastan/Boost sessions — and the framework holds
substantially more attribute surface than the course shows.** One genuine gap (§3) and one
cleanup candidate (§8) came out of the full read.

---

## 1. Kept from the first pass, re-verified

- **`ofMany` cannot express featured-else-latest** — the subquery is a MIN/MAX aggregate over
  the child table alone, attached via `joinSub` as an uncorrelated derived table
  (`CanBeOneOfMany.php`). The parked 2b refactor's conclusion stands; nothing in the full
  course reopens it.
- **`->one()` on an existing `HasMany`** (`$this->projects()->one()->latestOfMany()`) is the
  DRY construction form and inherits the parent relation's constraints. Construction sugar
  only — winner selection is unchanged.
- **Do not enable `automaticallyEagerLoadRelationships()` here.** The course recommends it
  (lesson 26: "probably always use" it on Laravel 12.9+); measured in
  `HasAttributes::getRelationValue()`, autoloading returns **before** the
  `preventLazyLoading` check, so it would replace this repo's local N+1 exception with a
  silent fix. Decision recorded: keep `preventLazyLoading`, leave autoloading off.

---

## 2. Laravel 13 model attributes — the course undersells its own headline

The course presents `#[Fillable]` / `#[Unguarded]` as "the modern recommendation"
(fillable lesson), `#[Scope]` as "the recommended way" with `scopeX()` explicitly labelled
**legacy**, plus `#[ScopedBy]` and `#[ObservedBy]`.

Measured in `Illuminate/Database/Eloquent/Attributes/`: **24 attributes**. The course shows
five. The rest, none mentioned:

`Appends` · `Boot` · `CollectedBy` · `Connection` · `DateFormat` · `Guarded` · `Hidden` ·
`Initialize` · `RouteKey` · `Table` · `Touches` · **`UseEloquentBuilder`** · `UseFactory` ·
`UsePolicy` · `UseResource` · `UseResourceCollection` · `Visible` · `WithoutIncrementing` ·
`WithoutTimestamps`

### PodText is already migrated — this week

Measured today: `#[Fillable]` + `#[Hidden]` on `User` (`app/Models/User.php:18-19`),
attribute imports across 6+ models, and **21 `#[Scope]` attributes with zero legacy
`scopeX()` methods left**. The larastan playbook's note that `checkModelMethodVisibility`
"would conflict with this repo's existing `public function scopeX`" is now obsolete — the
concurrent sessions' model migration removed the conflict. (That larastan flag is now
enableable in principle; one for the phpstan owner.)

### `UseEloquentBuilder` — flagged for the larastan Builder-typing debt

`#[UseEloquentBuilder(ContentItemBuilder::class)]` + a custom builder class is the framework's
own typed answer to the `Builder<Model>` boundary problem recorded in
[larastan-playbook](../larastan-playbook.md) §4b (61 raw-`Builder` scope errors at the
Filament boundary). A custom builder gives the scopes a real class PHPStan can see, no stub
needed. **Not proposed — noted as the alternative to stubs** if that debt family ever gets a
dedicated pass; it is a real refactor with test implications, not a drop-in.

---

## 3. The one genuine gap: strictness is one-third enabled

The fillable lesson's opening failure — `create()` silently **dropping** a field missing from
`$fillable` (their `email_verified_at` demo) — is the exact failure class
`Model::preventSilentlyDiscarding()` turns into an exception. The course's blanket fix:

```php
Model::shouldBeStrict(! app()->isProduction());
```

`shouldBeStrict()` enables three guards (`Model.php:562`): `preventLazyLoading` **+**
`preventSilentlyDiscarding` **+** `preventAccessingMissingAttributes`.

Measured here: only `preventLazyLoading(! isProduction())` is on
(`AppServiceProvider.php:148`). The other two are off, so a mass-assignment of a non-fillable
field still drops silently in dev, and a typo'd attribute read returns null instead of
throwing.

**Proposal (operator decides):** widen to `Model::shouldBeStrict(! $this->app->isProduction())`.
Caveat honestly: the missing-attribute guard can fire on legitimate dynamic access patterns
(Filament forms occasionally read optional attributes), so run the suite before adopting —
1850 tests will say quickly whether the strict pair is compatible. If it isn't, adopt
`preventSilentlyDiscarding` alone, which is the half that catches real bugs.

---

## 4. Relation querying — the modern methods, with local usage counts

All verified in `Eloquent/Concerns/QueriesRelationships.php` (`:186,440,735,795`):

| method | what it replaces | uses in `app/` |
| --- | --- | --- |
| `has('rel')` / `has('rel', '>', 1)` | manual join/count | (baseline) |
| `whereHas('rel', fn)` | conditional join | in use |
| `whereRelation('rel', 'col', 'op', 'v')` | one-condition `whereHas` closure | 1 |
| `withWhereHas('rel', fn)` | duplicated `whereHas` + `with` closures | **0** |
| `whereBelongsTo($model)` | `where('user_id', $user->id)` | 1 |
| `whereAttachedTo($model)` | manual pivot `whereHas` | 0 (BelongsToMany taxonomy could use it) |

`withWhereHas` is the one worth remembering: the filter-and-eager-load-the-same-subset
pattern appears in public listing queries, and the duplicated-closure form is the bug-prone
version (edit one closure, forget the other).

## 5. Aggregates and serialization

- `withCount` (5 uses here already), plus `withMin/Max/Avg/Sum/Exists` →
  `{relation}_{fn}_{column}` attributes. Their 50k-row demo's point stands: aggregates in
  SQL, not `->count()` on loaded collections — the `with('projects')->count()` version OOMs
  at 400k rows.
- Serialization: `$hidden`/`$visible`/`$appends` + runtime `makeHidden()/makeVisible()/append()`.
  PodText note: `User` now carries `#[Hidden([...])]` — the attribute form of the same
  contract — and **zero `$appends`** anywhere, which is the right default (appends run their
  accessor on every serialization).

## 6. Pivots and polymorphics — matches the tree

`withTimestamps()`, `withPivot()`, `wherePivot()`-scoped secondary relations, `->as()`
renaming (author: never used it). Polymorphic `morphs()`/`morphTo()`/`morphMany()` with
`Relation::enforceMorphMap()` recommended.

PodText: 2 `withPivot` uses; `MediaAttachment` is the polymorphic hub (2 `morphTo`) and
`AppServiceProvider:178` registers a **`morphMap`** — the course shows `enforceMorphMap`,
whose only delta is throwing on unmapped models instead of silently storing FQCNs. With every
morph type already mapped here, enforcing would be a one-word hardening; low value, zero
urgency.

## 7. The utility lessons — one-line verdicts

| lesson | verdict for PodText |
| --- | --- |
| `firstOrCreate/firstOrNew/updateOrCreate/upsert` | Known; 2 `updateOrCreate` uses. `upsert` (0 uses) is the bulk-import primitive if importers ever need row-level dedupe at scale — note it bypasses model events, which matters here (observers). |
| `wasRecentlyCreated / isDirty / wasChanged` | In use (3 × `wasRecentlyCreated`). The `isDirty`-before-save vs `wasChanged`-after-save split is the part people get wrong. |
| `find/first` variants, `whereDate/whereX` | Generic. The dynamic-`whereEmail` magic is flagged by the author himself as not recommended — agreed, it defeats static analysis. |
| `whereAll/whereAny`, `when()` | Generic sugar. |
| Query caching (`Cache::remember`) | Generic; PodText caches at the settings/config layer instead, deliberately. |
| Seeding via chunked `insert()` | Good trick (bypasses Eloquent per-row cost); factories stay canonical here for tests. |
| Raw queries, whereHas-vs-join, N+1 debugbar | Covered by kept §1 conclusions and the perf demo above. |
| Model stubs customization, singular/plural naming, `touch()`, `saveQuietly()` | Generic/known. |

## 8. Prunable — a real cleanup candidate found

`Prunable` (per-row, fires events) vs `MassPrunable` (single `DELETE`, no events), both driven
by `php artisan model:prune` on the scheduler, with `--model` / `--except` scoping.

**PodText has zero prunable models but one obvious candidate**: `FormVerificationCode` has
`expires_at`, scopes exclude expired rows, and nothing deletes them — expired codes accumulate
forever. `MassPrunable` + `prunable(): where('expires_at', '<', now()->subDays(N))` +
scheduling `model:prune` is the textbook fit (no side effects on delete → mass variant).
**Proposal, small and self-contained.** Check first whether any retention/audit expectation
exists for used codes; if so, prune only long-expired ones.

## 9. The package-survey lessons (38–40) — mostly not for here

Spatie query-builder (URL-driven filters — Filament owns this surface here), Tucker-Eric
EloquentFilter, spatie/laravel-searchable and protonemedia/cross-eloquent-search
(**MySQL 8.0+ only** — fine here), staudenmeir HasManyDeep, Tighten Parental (single-table
inheritance), cascade soft-deletes. PodText's Hebrew folded-column search is deliberate and
none of the search packages handle folding — no change. Useful as a map of what exists;
new dependencies need approval regardless.

## 10. The bonus "AI skill" (lesson 41, 27.6k chars in corpus)

Same standing position as the queues/testing/structure ones: **not installed** — third-party
`SKILL.md` is executable instruction surface, and §2–§8 show the codebase already ahead of
most of its checks.

---

## 11. Sources

- All 41 lessons digested (headings + identifiers, from `raw/laravel-eloquent-expert.json`,
  scraped 2026-08-08, premium-verified); **24 read in full** across the two passes —
  including [fillable/guarded](https://laraveldaily.com/lesson/laravel-eloquent-expert/model-fillable-guarded),
  [local & global scopes](https://laraveldaily.com/lesson/laravel-eloquent-expert/local-global-scopes),
  [query relationship data](https://laraveldaily.com/lesson/laravel-eloquent-expert/query-relationship-data),
  [model properties](https://laraveldaily.com/lesson/laravel-eloquent-expert/model-properties-tables-keys),
  [firstOrCreate family](https://laraveldaily.com/lesson/laravel-eloquent-expert/firstorcreate-methods),
  [wasCreated/isDirty](https://laraveldaily.com/lesson/laravel-eloquent-expert/wascreated-isdirty),
  [prunable](https://laraveldaily.com/lesson/laravel-eloquent-expert/prunable-and-massprunable-cleaning-up-old-records),
  [withCount](https://laraveldaily.com/lesson/laravel-eloquent-expert/withcount),
  [advanced belongsToMany](https://laraveldaily.com/lesson/laravel-eloquent-expert/advanced-belongs-to-many),
  [polymorphic relations](https://laraveldaily.com/lesson/laravel-eloquent-expert/polymorphic-relations),
  [serialization](https://laraveldaily.com/lesson/laravel-eloquent-expert/27-hidden-visible-appends-model-serialization),
  [filter/search packages](https://laraveldaily.com/lesson/laravel-eloquent-expert/filter-search-packages),
  and the five from the first pass.
- Framework source at v13.23.0: `Eloquent/Attributes/` (24 files),
  `Eloquent/Concerns/QueriesRelationships.php:186,440,735,795`, `Eloquent/Model.php:562`
  (`shouldBeStrict`), `CanBeOneOfMany.php`, `HasAttributes::getRelationValue()`.
- This repo, measured 2026-08-08: `User.php:18-19`, scope counts (21 attribute / 0 legacy),
  `AppServiceProvider.php:148,178`, usage counts per §4–§8, `FormVerificationCode.php`.

### What I could not obtain / did not read in full

17 lessons remain digest-only (casts/dates/enum, accessors deep-dive beyond the read
excerpt, brackets/or, when(), subselect performance details, caching, seeds, raw queries,
n1-packages, collections-vs-query, api-response, wherehas-vs-join, hasManyThrough,
polymorphic M2M, relationship/extra packages, make:model options, stub customization).
Their headings and identifiers are indexed in [index.md](index.md); nothing in the digests
contradicted a claim made here. The `model-casts-dates-enum` lesson turned out to be 1.9k
chars of basics — the larastan `casts()` interaction remains uncovered by this course.
