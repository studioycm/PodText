# Hebrew collation and the database clock — decision, and why the runbook is not ready

**Status: DO NOT RUN.** A full migration runbook was written and then attacked
from four angles. **All four found blocking defects — 28 total, 9 of them
blockers**, including two that would have destroyed or silently corrupted data.
The runbook is not reproduced here, because a plausible-looking runbook with
known blockers is more dangerous than no runbook. What follows is the decision,
the evidence, the blockers that must be closed first, and the facts about
production that must be measured before a window can be scheduled.

**Everything marked [MEASURED] was run read-only against the local database and
its output is quoted verbatim.** Production was never contacted.

---

## 1. The headline

> A search for `שלום` **should** find text stored as `שָׁלוֹם` — and **no
> collation can deliver that**, because PodText searches with `LIKE`, and
> `LIKE` folds nothing ignorable in any collation on the server.

[MEASURED]

| expression | result |
| --- | --- |
| `'שָׁלוֹם' = 'שלום' COLLATE utf8mb4_0900_ai_ci` | **1** |
| `'שָׁלוֹם עולם' LIKE '%שלום%' COLLATE utf8mb4_0900_ai_ci` | **0** |
| `'שָׁלוֹם עולם' LIKE '%שלום%' COLLATE utf8mb4_unicode_ci` | **0** |
| `LOCATE('שלום' COLLATE …_ai_ci, 'שָׁלוֹם עולם' COLLATE …_ai_ci) > 0` | **1** |

`=` folds niqqud perfectly. `LIKE` does not, in any of the six collations
tested. The mechanism: niqqud carries **zero primary weight**, so
`WEIGHT_STRING('שָׁלוֹם')` and `WEIGHT_STRING('שלום')` are byte-identical.
`=` compares whole weight strings and sees one word; `LIKE` walks
character-by-character and **cannot consume a zero-weight character in the
haystack that has no counterpart in the pattern**. `LIKE` is weight-aware but
not ignorable-aware.

**So the collation choice and the search problem are two separate questions,
and only one of them needs a migration.**

---

## 2. There is a live search bug, on real data, today

[MEASURED] `content_items` id 56 carries a single **U+05BF HEBREW POINT RAFE**
inside `description_markdown`:

```
bytes around the phrase:  D796 D794 20 D79C D79E D794 D6BF 21
                                                     ^^^^ U+05BF, invisible
LIKE '%זה למה!%'   → 0     (a user typing exactly what they see finds nothing)
LOCATE(… COLLATE …) → 1     (the same query, folded, finds it)
```

A user who types the phrase as it renders gets **zero results**; a shorter
query that stops before the invisible mark returns the row. No reviewer would
ever spot this in a diff or in the rendered page.

**This is fixable today, with no migration, no window and no collation
change** — replace the `LIKE '%…%'` predicates with
`LOCATE(? COLLATE utf8mb4_0900_ai_ci, col COLLATE utf8mb4_0900_ai_ci) > 0`.

Affected call sites:
`app/Livewire/Public/ContentItemSearch.php:468` (`$like = "%{$search}%"`) and
`:472-483`; `app/Livewire/Public/ContentItemBrowser.php:112-113`.

Two caveats, both measured:

1. **Both operands must carry the `COLLATE` clause.** Collating only the needle
   does *not* bind the comparison — MySQL silently falls back to the connection
   collation. Proof: `LOCATE('ABC' COLLATE utf8mb4_bin, 'abcdef')` → **1**
   (impossible byte-wise), versus both sides collated → **0**. A half-collated
   predicate behaves differently in the CLI, in PDO, and in tests.
2. **It is unindexable**, exactly like a leading-wildcard `LIKE`. This is a
   correctness win, not a performance one.

**Recommendation: ship this separately and first.** It is the only part of this
document with a live user-visible defect behind it, and bundling it with a
database migration means that if search behaves oddly afterwards, nobody will
know which change caused it.

---

## 3. The collation decision

With Hebrew search off the table, the collation is decided on what a collation
actually governs: `=` semantics (unique indexes, slug routing, exact lookups)
and sorting.

**Sorting contributes nothing.** [MEASURED] All six collations produce
byte-identical orderings of the real titles (`1,6,2,8,4,5,10,3,56,7` under
`utf8mb4_unicode_ci`, `utf8mb4_0900_ai_ci` and `utf8mb4_bin` alike). Synthetic
Hebrew word lists agree. **There is no Hebrew sort ordering to gain.**

**Recommendation: `utf8mb4` / `utf8mb4_0900_ai_ci`, uniformly, no per-column
exceptions** — for alignment with the MySQL 8/9 server default, NO PAD
semantics, and emoji distinctness.

**The null option is genuinely defensible.** Nothing in the Hebrew evidence
*forces* the change. Staying on `utf8mb4_unicode_ci` costs nothing in Hebrew
correctness and avoids every risk in §4. Given that the search fix (§2) is what
actually helps users and needs no migration, **"fix search, leave the collation
alone" is a legitimate outcome of this investigation** and should be weighed
seriously against a maintenance window.

---

## 4. Why the runbook is not ready — the blockers

Each of these was measured by a verifier attacking the plan. They are recorded
so that whoever writes v2 starts past them.

**B1 — `--databases` would have destroyed the original.** [MEASURED] A
`mysqldump --databases podtext` emits `CREATE DATABASE … podtext` and
`USE podtext` in its header, **plus 40 × `DROP TABLE IF EXISTS`**
(`add-drop-table` defaults on). So `mysql -u… podtext_new < dump.sql` does
**not** restore into `podtext_new` — the embedded `USE` overrides the
command-line target, and the restore drops and rebuilds the *original*. The
plan's stated scope (copy into a new database, keep the original for rollback)
was unachievable by the plan's own command.

**B2 — the clock fix was silently self-defeating.** [MEASURED]
`mysqldump --tz-utc` **defaults to ON** and the plan never mentions it. The
plan's premise — that the dump renders each epoch as its app-visible literal —
is false for any default invocation. The dump's own
`/*!40103 SET TIME_ZONE='+00:00' */` runs *after* a connect-time
`--init-command`, so the plan's verification could not even detect the problem.

**B3 — "it can only loosen uniqueness" is false, and false specifically in
Hebrew.** This was the plan's load-bearing safety claim and the reason it had
no pre-flight duplicate check. `utf8mb4_unicode_ci` is UCA **4.0.0**;
`utf8mb4_0900_ai_ci` is UCA **9.0.0**. Hebrew marks assigned to Unicode after
4.0 are *unassigned* to the older collation, so they receive non-ignorable
weights instead of folding. [MEASURED], with U+05C7 HEBREW POINT QAMATS QATAN:

```
'א' = 'א'+U+05C7  COLLATE utf8mb4_unicode_ci  → 0   (distinct today)
'א' = 'א'+U+05C7  COLLATE utf8mb4_0900_ai_ci  → 1   (EQUAL after)
```

Two rows legal today become a duplicate key after the change. A realistic slug
pair: `כׇּל-הזמן` versus `כל-הזמן`.

**B4 — and it would fail *silently*.** [MEASURED] The dump header carries
`SET UNIQUE_CHECKS=0`. MySQL documents that InnoDB may then assume the input is
duplicate-free, and that **the secondary index can become corrupted** if it is
not. So B3's failure mode is not a loud `ERROR 1062` that aborts the restore —
it is a quietly corrupt index. "The restore exited 0" is not proof.

**B5 — the trailing-whitespace pre-flight was a tautology.** The plan detected
padding with `col <> TRIM(TRAILING ' ' FROM col)` on a **PAD SPACE** collation,
which pads both sides and can never report a difference. [MEASURED] it returns
0 rows on every possible input. Must be length-based:
`LENGTH(col) <> LENGTH(TRIM(TRAILING CHAR(32) FROM col))`.

**B6 — the `sed` transform was unanchored**, running across `INSERT` data lines
as well as DDL. Any row containing the literal string `utf8mb4_unicode_ci` —
plausible in this project, whose own docs discuss collations — would be
silently rewritten.

**B7 — there was no cutover.** MySQL 8/9 removed `RENAME DATABASE`; there is no
atomic swap. Pooled php-fpm and Horizon connections keep writing to whichever
schema they were bound to, regardless of a config change. Both databases end up
believed authoritative.

**B8 — the index inventory was wrong by an order of magnitude.** The plan said
5, then 22. [MEASURED] **39** unique/primary indexes contain at least one
collated column. Any pre-flight must be generated from `information_schema`,
never hand-listed.

**B9 — DST fall-back is not injective.** Rendering an epoch under a
DST-observing zone loses information: [MEASURED] epochs 3600 seconds apart both
render `2026-10-25 01:30:00`. A `--skip-tz-utc` dump cannot round-trip those
rows.

Also worth carrying forward: pinning `DB_COLLATION` in `.env.example` changes
**nothing** — `.env.example` is not read at runtime, and
`config/database.php:57` is what actually decides.

---

## 5. What must be measured on production before any window

Nothing in the repo records any of this — there is no deploy script, no
Dockerfile, no CI config that names the database.

```sql
-- charset / collation
SELECT @@version, @@character_set_server, @@collation_server;
SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
  FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE();
SELECT TABLE_COLLATION, COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() GROUP BY 1;

-- the clock, and WHICH bug it is
SELECT @@global.time_zone, @@session.time_zone, @@system_time_zone,
       NOW(), UTC_TIMESTAMP(), TIMEDIFF(NOW(), UTC_TIMESTAMP());

-- are named zones even loaded? (locally: 0 rows)
SELECT COUNT(*) FROM mysql.time_zone_name;
```

Open questions that change the answer:

- **Is the ~180-minute drift a timezone offset or a wrong wall clock (NTP)?**
  Different bugs, different fixes. This work only addresses the first.
  `TIMEDIFF(NOW(), UTC_TIMESTAMP())` discriminates.
- **Is the session `SYSTEM` resolving to a DST-observing zone, or a fixed
  `+03:00`?** If DST-observing, the per-row shift is +02:00 for winter rows and
  +03:00 for summer rows.
- **Does production's `.env` set `DB_CHARSET`/`DB_COLLATION`?** Neither appears
  locally, so a production override would invalidate every inference here.
- **Does anything write TIMESTAMP columns outside Laravel's connection?** Such
  rows carry true epochs and would be shifted the wrong way.
- **Production row counts and niqqud density.** The local corpus is ~460 rows
  of near-ASCII identifiers with **one** non-ASCII unique-indexed value and
  **zero** niqqud in indexed columns — so the local "no collisions" clearance
  is close to vacuous. The B3 duplicate scan must run on production data.

---

## 6. Recommended sequence

1. **Fix search with `LOCATE`** (§2). No window, no migration, real user-facing
   defect closed. Needs its own tests — and note those tests cannot run on the
   default SQLite suite, which is `driver-lenient-fallback` again; see
   `mysql-test-lane-spec.md`.
2. **Measure production** (§5). Cheap, read-only, and it decides whether steps
   3-4 are even the right work.
3. **Decide whether the collation change earns a window at all** (§3's null
   option). If Hebrew correctness is the goal, step 1 already delivered it.
4. **Only then** write runbook v2, starting past B1-B9, and rehearse it end to
   end on a restored production copy before it goes near production.

## 7. Blast radius the runbook must own

Two files already work around the clock bug and become **actively misleading**
the day it is fixed:

- `app/Support/PublicContent/PublicTranscriptionSelector.php:196-219` —
  `sqlMoment()`, whose docblock at `:206-211` argues *against* this migration.
  That comment must be updated in the same commit, and the question of whether
  `sqlMoment()` reverts to `CURRENT_TIMESTAMP` answered explicitly.
- `app/Support/Dashboard/JerusalemDailySeries.php` — buckets days in PHP
  precisely because raw SQL `DATE()` buckets on the database timezone.

See `db-clock-coupling` in `docs/research/defect-cause-patterns.md`.
