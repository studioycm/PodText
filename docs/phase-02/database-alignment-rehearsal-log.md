# Database Alignment — Rehearsal Log

Date: 2026-08-09 · Plan: [database-alignment-implementation-plan.md](database-alignment-implementation-plan.md) Task 8 · Seed snapshot: `podtext-20260808-180202-phase0-baseline.sql.gz`
Tooling under rehearsal: `db:seed-rehearsal-edges`, `db:preflight-alignment`, `db:alignment-oracle`, the alignment migration `2026_08_09_000000` (commits `837782c`…`8abadbe`).

## Verdict

Both engines converted cleanly with **byte-identical value preservation** (oracle PASS) and identical end states. Two real defects were caught by rehearsal and fixed **before any real database was touched** — which is the reason this gate exists.

| | MySQL 9.4.0 (3306, `podtext_restore_check`) | MySQL 8.0.46 (3307, `podtext_rehearsal`) |
|---|---|---|
| Seed | 154 clones / 22 tables + 7 in-place updates / 1 pivot; 5 empty tables skipped | identical profile |
| Seeded-artifact prune | 2 trailing-space rows | 2 rows (identical) |
| Pre-flight | 30 unique indexes, clean | identical |
| Oracle capture | 36 tables, 390 columns; double-capture byte-identical (determinism check) | 36 tables, 390 columns |
| **Migration** | **629.44 ms** | **706.26 ms** |
| Oracle compare | **PASS** — values byte-identical, only intended property changes | **PASS** (identical wording) |
| End state | schema+tables ×40+columns ×183 all `utf8mb4_0900_ai_ci`, `datetime ×80`, zero timestamp | identical |
| Remaining findings | clock (Phase 3) + configured-collation PAD (clears at Task 9) | identical |

The 8.0.46 figures are the production-window estimate: **the migration itself is sub-second at this data size**; the window's real cost is the snapshot + capture + compare bookends.

## Defects rehearsal caught (all plan-inherent, all fixed in code and re-proven)

1. **Seeder collided on composite-unique pivots** (`author_transcription (author_id, transcription_id)`, SQLSTATE 1062 on the first clone). Root cause: `COLUMN_KEY` cannot see composite unique indexes (first column reports MUL) — the earlier "measured zero uncloneable keys" canary was itself blind. Fix `1c0d294`: unique constraints read from `information_schema.STATISTICS`; tables whose every unique index intersects the varying set keep the insert path, others get an in-place update path (date edges only, primary-key-addressed, up to 7 rows).
2. **Oracle hashed the `migrations` ledger** — the migration run inserts its own ledger row, so capture→migrate→compare could never pass as planned (`value drift in migrations: rows 82 → 83`). Fix `8abadbe`: the ledger is excluded from value hashing (its mutation IS the act under test); its column properties stay captured, so its collation conversion is still verified.

## Drop-and-recreate verification (operator-requested, 2026-08-09)

After the third fix (`cc86be5`, below), both rehearsal databases were **dropped, recreated, and run end-to-end with zero manual intervention**: restore → seed → pre-flight CLEAN (no prune step) → capture → migrate (613ms / 639ms) → oracle PASS → check-settings aligned. The sequence is clean by construction on a fresh database on both engines.

3. **Seeder manufactured B5 findings at truncation boundaries** — 7-codepoint payloads (`שָׁלוֹם`, `ם סופית`) + separator truncate at exactly 8 chars in `settings_backup_snapshots.format`, leaving trailing spaces that made pre-flight exit 1 until manually pruned. Fix `cc86be5`: the truncated payload is `rtrim`'d — only an accidental boundary-cut space is stripped; `'טעם '`'s designed space sits mid-string in every wide column, so collation coverage is unchanged. (The first prune attempt was its own lesson: under PAD SPACE, `col <> TRIM(...)` compares equal, so the delete matched 0 rows — the byte-length predicate `LENGTH(col) <> LENGTH(TRIM(TRAILING CHAR(32) FROM col))` is the correct form. That very phenomenon is what this migration retires.)

## Notes for the production window (Task 11)

- Production runs pre-flight on real data only (no seeding) — Task 4's live scan of that lineage was clean.
- The **configured-collation PAD finding** in `db:check-settings` persists until Task 9 hardcodes `utf8mb4_0900_ai_ci` in `config/database.php` — expected and transient; only the clock finding is a Phase 3 matter.
- Spot check: seeded emoji title `🎧 …` renders intact post-conversion; NULL-alternated clones stayed NULL (nullability preserved, also proven by the oracle property rules).
- Rehearsal databases **kept** (drop only on operator approval): `podtext_restore_check` (3306) and `podtext_rehearsal` (3307), both now in the converted end state.
