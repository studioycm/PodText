# LaravelDaily: Structuring Databases in Laravel 12 — course notes

Notes on [Structuring Databases in Laravel 12](https://laraveldaily.com/course/structuring-databases-laravel)
(18 text lessons, 1h21m, **Apr 2025**), read 2026-08-07. Four lessons read in full, chosen for
PodText relevance: enum columns, JSON columns, status-history, and unlimited-depth hierarchies.

---

## 0. Staleness verdict: **Laravel 12-era, and it shows — but the content is schema design, which ages slowly**

Apr 2025 puts this a full major version behind. The operator's rule treats ≤ Dec 2024 as stale
by default; this is *after* that line but still pre-Laravel-13. Applying the sniff test:

| marker | status |
| --- | --- |
| `protected function casts(): array` | current — this is the Laravel 11+ form, not the old `$casts` property |
| `php artisan make:enum Enums/TicketStatus --string` | still exists in Laravel 13 |
| Migration/`Schema::create` syntax | unchanged |
| Relationship return-type hints (`: BelongsToMany`) | current idiom |

**No stale API found in the lessons read.** The reason is that the subject is relational
schema design, which is mostly not framework-versioned. The risk with this course is not
rot — it is that its examples are simple e-commerce shapes and the reasoning is more valuable
than the code.

One dated detail worth noting: the JSON lesson's headline example is
`spatie/laravel-medialibrary`'s `generated_conversions` column. That package is **not** used
here — PodText has its own `MediaAttachment` model — so the example is illustrative only.

---

## 1. The four lessons, and what each says

### Enum columns vs native PHP enums — **settled here already**

The lesson argues against MySQL `enum()` columns and for native PHP enums:

- A DB `enum()` column stores allowed values in the schema, so adding a value means an
  `ALTER TABLE` that rewrites every row.
- The alternative it rejects — model constants (`public const STATUS = ['Open','Closed']`) —
  keeps values in code but leaves the DB unaware.
- Preferred: `$table->string('status')->default(TicketStatus::OPEN)` plus
  `casts(): ['status' => TicketStatus::class]`. Adding a value is a one-line enum change.

**PodText is already fully here.** Measured: `app/Enums/` holds **47** enum classes, and the
recent commit `refactor(enums): move the last two enums into app/Enums, and guard the rule`
closed the last exceptions with an architecture rule. There is already a
[php-enums-playbook.md](docs/research/php-enums-playbook.md) in this folder that goes deeper
than this lesson.

**No action.** Recorded only as external corroboration of a decision already made and enforced.

Worth connecting, though: the `casts()` method being the modern form is exactly what breaks
larastan by default — see [larastan-playbook.md](docs/research/larastan-playbook.md) §1. The
course teaches the right pattern and, like every other source found, has no idea it interacts
badly with static analysis.

### JSON columns — the trade-off, stated fairly

Use when the shape is genuinely open-ended and likely to change. Costs, as the lesson lists
them: no relational integrity, no foreign keys, hard to inspect, and slower to query inside.

Relevant here because PodText has several JSON-ish columns —
`content_items.media_metadata`, homepage-section `display_config` / `source_config`,
card-template and public-form revision documents. Those are all cases where the lesson's test
("you don't precisely know what properties will be there") genuinely applies, and where the
project has additionally chosen **validated revision JSON** rather than free-form — which is
stricter than anything the lesson proposes.

**No action.** The lesson would not have changed any of those decisions.

### Order-status history — corroborates `featured_transcription_id`

The lesson's shape: a `belongsToMany` history pivot with timestamps, plus the question of how
to read "the current one". It offers two options, and **Option 2 is a denormalised
`status_id` column on the parent**, precisely so the common read does not have to load the
whole history.

That is structurally the same decision as PodText's `content_items.featured_transcription_id`:
a parent-side pointer at the chosen child, so listings do not have to load every
`Transcription` to find out which one counts.

The lesson's Option 1 (load the whole relation, then `sortByDesc(...)->first()` in PHP) is
exactly the pattern the effective-transcription work exists to avoid. Useful mainly as a
reminder of *why* the denormalised column is there, for anyone tempted to remove it.

Connects to [laraveldaily-eloquent-notes.md](docs/research/laraveldaily-eloquent-notes.md) §2 —
the pointer column is what makes the rule parent-dependent, and therefore what makes `ofMany`
unable to express it.

### Unlimited-depth hierarchies — **one real finding**

The lesson's whole point is that the naive adjacency-list read (`with('children.children')`,
or recursing per node) does not scale, and it walks through fixed-depth eager loading, a
recursive Blade partial, and finally a package.

**PodText uses the naive recursive form.** Measured, [Category.php:63-75](app/Models/Category.php:63):

```php
public function descendantIds(bool $includeSelf = true): Collection
{
    $ids = $includeSelf ? collect([$this->getKey()]) : collect();
    $children = static::query()->where('parent_id', $this->getKey())->get(['id', 'parent_id']);

    foreach ($children as $child) {
        $ids = $ids->merge($child->descendantIds());
    }

    return $ids->unique()->values();
}
```

That is **one query per node in the subtree**, and it is on the public filtering path —
[ContentItem.php:300](app/Models/ContentItem.php:300) calls `$category->descendantIds()->all()`.

**Measured on the local database today:**

```
categories = 5     roots = 4     max depth = 1
largest root subtree: 2 ids, 2 queries
```

So **it costs 2 queries right now and is not a problem.** It is a latent one: the cost is
linear in node count, and it sits on a request path a visitor can hit. There are no
materialised-path, depth, or nested-set columns on the table, so nothing else bounds it.

**Proposal, low priority and explicitly not urgent:** if the taxonomy ever grows past a
couple of dozen categories, replace the recursion with a single query. MySQL 8 supports
recursive CTEs natively, so this needs no new dependency:

```sql
WITH RECURSIVE tree (id) AS (
    SELECT id FROM categories WHERE id = ?
    UNION ALL SELECT c.id FROM categories c JOIN tree t ON c.parent_id = t.id
) SELECT id FROM tree
```

`staudenmeir/laravel-adjacency-list` wraps this in Eloquent, but adding a dependency for a
five-row table is the wrong trade today — and adding dependencies needs approval here anyway.
**The right action now is to note the ceiling, not to raise it.** Re-measure when the category
count changes; the numbers above are the baseline to compare against.

---

## 2. Applies here vs. generic

| lesson | applies to PodText? |
| --- | --- |
| Enum DB columns vs PHP enums | **Already done**, 47 enums in `app/Enums`, rule-enforced. No action. |
| JSON column type | **Already reasoned through**, and PodText's validated-revision-JSON approach is stricter than the lesson. No action. |
| Order status history | **Corroborates** `featured_transcription_id`. No action; useful as rationale. |
| Unlimited levels parent/children | **One finding** — `descendantIds()` is O(nodes) on a public path. Currently 2 queries. Watch, don't fix. |

## 3. What I did not read

14 of 18 lessons: `belongsto-vs-belongstomany-vs-polymorphic`, `hasone-relationship-examples`,
`normalization-three-normal-forms`, `hasmany-delete-parents-validate-cascade`,
`polymorphic-many-to-many-query-data`, `naming-things-databases`,
`pivot-tables-extra-field-operations`, `foreign-keys-grandparent-duplicate-data`, `uuid-ulid`,
`custom-fields-eav`, `invoice-numbers-with-series-prefixes`,
`indexes-useful-useless-composite`, `primary-keys-unique-indexes`,
`practice-change-db-structure-live-project`.

Two are worth a follow-up if anyone returns to this course:

- **`indexes-useful-useless-composite`** — this repo runs Hebrew search folding with dedicated
  folded columns (`HasFoldedSearchColumns`), so composite-index reasoning is directly relevant
  and was not checked here.
- **`uuid-ulid`** — the operator flagged it in the original priority list. Not read; PodText
  uses integer keys with portable reference keys/slugs for import-export, which is a
  deliberate existing decision (`.ai/import-export`), so the lesson is unlikely to change
  anything.

## 4. Sources

- [Course index](https://laraveldaily.com/course/structuring-databases-laravel), Apr 2025.
  Read: [enum DB columns vs PHP enums](https://laraveldaily.com/lesson/structuring-databases-laravel/enum-db-columns-php-foreign-keys),
  [JSON column type](https://laraveldaily.com/lesson/structuring-databases-laravel/json-column-type),
  [order status history](https://laraveldaily.com/lesson/structuring-databases-laravel/order-status-history-querying-latest),
  [unlimited levels](https://laraveldaily.com/lesson/structuring-databases-laravel/unlimited-levels-parent-children).
- This repo, measured 2026-08-07: `app/Enums/` count; [Category.php:38-75](app/Models/Category.php:38);
  [ContentItem.php:300](app/Models/ContentItem.php:300); `database/migrations/*_create_categories_table.php`
  (no path/depth/nested-set columns); category tree shape and query count via tinker against
  the **local** database.

### What I could not obtain

- **Production category counts.** The tree measured above is local. If production's taxonomy is
  materially larger, the `descendantIds()` numbers change and should be re-measured there.
- The enum lesson's benchmark section was cut off mid-sentence in the fetched page
  ("Running the benchmark ten times gives a little more than 6ms" … then truncated), so its
  enum-varchar vs tinyint-FK performance comparison was not read in full. Not pursued — PodText's
  enum decision is already made on other grounds.
