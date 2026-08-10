# Consolidated open findings — all registers, one priority order

> **STATUS: committed SEED / INPUT to the (unstarted) Documentation Architecture and
> Consolidation Audit — not yet an authoritative register** (operator decision +
> two-session consultation, 2026-08-11). Standing disciplines agreed by both sessions:
> **regenerate wholesale, never hand-edit** (a hand-edit is itself a defect); the header
> pins the harvest SHA; refresh happens **at round close, by the session closing the
> round** — never by the auditor, whose independence is the reason this record came out
> clean. At the first regeneration, rows convert to **pointer-not-restatement** form
> (stable ID → owning doc/section → tier; status lives only in the owning doc), so a
> stale index can only over-report open items, never contradict a status. What this file
> owns exclusively: the cross-program priority order, convergences (items that are
> secretly one piece of work), and contradictions between documents — relations owned by
> no single doc.

**Snapshot:** 2026-08-10, from `main` @ **`7b7097d`**. Read-only harvest; nothing was written to the repo.
**Window:** documents modified since 2026-07-31 (`find docs -name '*.md' -newermt '2026-07-31'`), cross-checked with `git log --since`.
**Sources:** 21 documents in two clusters (list at the end, with per-doc item counts).
**Precedence when docs disagree:** commit evidence beats prose · newer dated statement beats older · a doc's own amendment beats its own table. Every resolution is recorded in Appendix A.

> ⚠️ **This register was overtaken mid-harvest.** The *"Triage post-alignment residuals"* session is working live in the tree and moved HEAD `0437705 → 8296f16` (7 commits) while this was being written. Already folded in below:
> - **Task S3 shipped** (`89a2ee1`, `810f6f2`) — the machine-global lane lock + fingerprint under `~/.cache/podtext-test-lane/`. The cross-worktree gap (D3/DP7) is **closed**; it was the top blocker in this register's first draft.
> - **F12** added to the triage ledger, **DP9** closed in place in F11.
> - New source doc `docs/research/laraveldaily/pest5-notes.md`, carrying one new TIA pre-check.
> - A Rector rule recorded in `.ai/rules/general.md` (new file).
> - **F12 diagnosed and committed** (`ecb1ecf`) — the "per-unit re-saves" reading is disproven; the real mechanism is per-backup snapshot fan-out, **confirmed production-affecting**. Item 1.8 rewritten, and its side-flag became new item **1.9**.
> - **A15 resolved at source** (`7b7097d`) — the sharding status was corrected in both places it was wrong.
>
> Re-read triage §F and `mysql-test-lane-spec.md` §4 before acting on anything sourced from them. Two sibling sessions are actively editing these files; this register is a snapshot, not the record.

---

## Tier 1 — Live production impact

Things affecting production **now**. Two classes, deliberately kept in one tier because both are live, but never conflate them:

- **Correctness (1.1–1.7)** — a wrong result, a crash path, or state that ends up wrong.
- **Cost (1.8–1.9)** — right answers, paid for at a price production is charged on every operation. Not data bugs. 1.8 is bounded per operation; 1.9 is unbounded but slow-burning and admin-action-bound, so it is *cumulative*, not urgent.

Do these first, correctness before cost. **1.8 + 1.9 + 2.6 are one piece of work, not three** — see the note under 2.6.

| # | Item | Where | Status | Notes |
|---|---|---|---|---|
| 1.1 | `MediaMutationOperationType::LegacyOwnerRepair` missing from `assertOperationShape()` and four `in_array` lists | triage **C1** + patterns `unhandled-arm`, `set-membership-without-totality` | `OPEN` (guard closed, defect open) | Rows park forever in `CleanupPending`/`ManualReviewRequired`. Does not crash — it silently never completes. The arm was **deliberately not added**: it belongs to the parked legacy owner-column retirement, which must clear all five lists at once |
| 1.2 | `PublicFrontConfigValidator.php:69` — `match` over 14 string literals with no default arm | triage **B3** | `OPEN`, re-characterised 2026-08-07 | `UnhandledMatchError` in production the moment the two hand-maintained lists disagree. Fix it **with** C3 (derive from enum predicates), not alone |
| 1.3 | `set-membership-without-totality` — `in_array($case, [...])` decides membership with no exhaustiveness property | triage **C3** + patterns entry | `OPEN`, **no guard exists and none can exist** for the literal form | The doc rates this above its louder `match` sibling. Only remedy: `array_filter(cases())` per `php-enums-playbook.md` §5 |
| 1.4 | `rule-in-fixture` — a product rule whose only home is a test array literal | patterns | `OPEN`. Founding fix (`EpisodePublicState::scope()`) **specified, never applied** | 6 further candidates catalogued in the 2026-08-06 rules audit (2 correct-as-written) |
| 1.5 | `SafeMarkdownRenderer.php:30,35` — the public prose contract sits outside **both** compiled Tailwind themes | `tailwind-scan-scope-research.md` | ACTUAL, user-visible | Renders unstyled on public item / about / transcript pages. Fix is 4 glob additions + a guard test; ownership was outside that session's mandate |
| 1.6 | Production `cron` + `rsyslog` still stamp `+03:00` | triage **F7** + rethink spec + plan Task 7 Step 3 | operator window (decided) | T12 restarted mysql/php-fpm/Horizon only. Rides the **next deploy window checklist** (+ nginx reload) |
| 1.7 | `failed_jobs.failed_at` frame unverified for pre-existing production rows | alignment spec §10.1 | dormant hazard, not corrupted data | Default dropped; 5 local rows checked. Rows predating this Laravel version or hand-inserted must be re-verified **there** before claiming otherwise |
| 1.8 | Settings backup: **per-backup visual-snapshot fan-out**, ×2 backups per operation, no dedup on the full-set path | triage **F12** | `DIAGNOSED` 2026-08-10 (`ecb1ecf`); fix **propose-only, operator gate** | The per-unit reading is **disproven** — one `import()` fires exactly one batched `SettingsSaved` (4 units → 1 event, instrumented). Real mechanism: one node+Playwright spawn per snapshot row — **12 spawns/import bare, 18 content-rich, 10 for a fully locked NO-OP import**. Verdict: **confirmed production-affecting (efficiency, not correctness)** — worker time + headless-Chromium load on live public pages per settings operation. Suite-side tax already neutralized by S2b. Three fixes proposed, none implemented |
| 1.9 | `prune()` retention is **System-only** (`SettingsBackupManager.php:196`) — Manual / BeforeImport / BeforeRestore backups and their snapshot files accumulate with no retention path | triage **F12** side-flag | new, surfaced by the F12 diagnosis; shape verified from the diagnosis side | Class: **cost**, and the only item here with no ceiling — non-System backups die only by explicit admin delete, and every import/restore leaves a backup row + 10–16 rendered files. **Unbounded by design, slow-burning: growth is admin-action-bound, not traffic-bound** — the fuse length scales with how often someone clicks import/restore/manual-backup, and bytes are dominated by the snapshot PNG/PDF/HTML, not the rows. Ends in a full disk if never acted on; not an imminent availability risk. Worst under heavy admin iteration bursts |

---

## Tier 2 — Latent traps with a proven mechanism

Not firing today; the failure path is demonstrated and one change away.

| # | Item | Where | Status | Notes |
|---|---|---|---|---|
| 2.1 | Hand-typed `migrate:fresh` / `db:wipe` / seeder still reaches the **development** database | mysql-lane §8.1 | open | Its own doc: *"the largest remaining hole; the lane does nothing about it."* Partly mitigated — alignment Task 1 moved `.env` off `root` |
| 2.2 | `tests/Unit` bypasses the lane guard (`tests/Pest.php:109-111`) | mysql-lane §8.2 | "latent today" — those tests don't boot the app | Phase S "unit-suite bypass closure" candidate, unshipped. Needs an arch rule or guard binding |
| 2.3 | `.env.testing` is not gitignored | mysql-lane §8.7 | open, unaddressed in every later doc | The one residual no successor document picked up |
| ~~2.4~~ | ~~Cross-worktree lane lock gap — two worktrees hold independent flocks~~ | triage **D3** + notes §1 + **DP7** + plan **Task S3** | ✅ **CLOSED `89a2ee1`/`810f6f2`** during this harvest | Lock and fingerprint are now machine-global under `~/.cache/podtext-test-lane/`, HOME-anchored and purge-proof; a legacy per-tree fingerprint is adopted once and removed |
| 2.5 | `single-read-race` sweep — one-shot `x-show` reads race Alpine's frame cadence | patterns + notes R4 | first sighting; second has now arrived | Recon scope **6 browser files × 15 `x-show` views, UNCHECKED**. S1/S1b converted the boolean family; rect-delta reads partly remain |
| 2.6 | `SettingsBackupSnapshotManager.php:104` — `Storage::disk('local')->put(...)` with no atomicity, no lock, no size bound | settings-performance/21 | "not asserted as a defect — worth one look" | Open question left unowned since the settings-performance round. **Confirmed from the F12 diagnosis side: the batching refactor (F12 proposal 1) rewrites this exact job-JSON `put()` site.** So batching + job-JSON hardening (atomic write, size bound) + non-System retention should ship as **one ticket, not three** — 1.8 + 1.9 + 2.6 |
| 2.7 | MUX3 **D2=A decision lapse** — `3900645` removed the managed-scope guard from `repair` | mini4 handoff | **lapsed, not violated** | `CuratorMediaPolicy.php:124-150` carries no `MediaRecordScope::allows()` while `delete`/`swap` still do. Operative boundary since 2026-07-31 is the wider one |
| 2.8 | Purge exposure is suite-wide — 40 files fake the `public` disk | browser-timeout open flag 3 | open | *"A census of which browser waits depend on faked-disk presence would size the true blast radius."* Fix 1 (`a3fa4f2`) landed; the census did not |
| 2.9 | Dead `instanceof` guard in `ContentImagesExportManager`, now provably unreachable | triage §B5 residual | "still open" | The single error separating 445 from 444. Split off because it is the only runtime-affecting edit in the family |
| 2.10 | `Category::descendantIds()` (`app/Models/Category.php:63-75`) queries per level — naive recursive adjacency read | laraveldaily/database-design-notes | one real finding | The lesson's whole point is that this does not scale |
| 2.11 | Compiled assets and views are an unexamined shared surface between concurrent runs | browser-timeout open flag 4 | open | No build arm was ever run during the contention investigation |
| 2.12 | Step 5B O1: narrow-to-wide root race + focus restoration gaps in both directions | settings-performance/33 | open findings | Listener sets `wide = true` before awaiting `$wire.unmountAction()`; a settled root-count assertion **cannot** detect the peak duplicate |

---

## Tier 3 — Gate blockers

Real work that stops the next phase from starting. Ordered by what unblocks the most.

| # | Item | Where | Status | Blocks |
|---|---|---|---|---|
| ~~3.1~~ | ~~**Task S3** — machine-global lane lock + fingerprint (D3 + DP7)~~ | rethink plan | ✅ **SHIPPED `89a2ee1`/`810f6f2`** — Phase S closed (`a50b15f`) | Was the last unshipped Phase S addendum. **Phase U's structural gate is now open** |
| 3.2 | **Phase U** — Pest 5 + plugins upgrade | rethink plan/spec + pest5-notes §2 | *"blocked on the operator's SEPARATE go-ahead"*; possibly a dedicated session | Now the **only** thing holding the queue below. `composer.json` pins `^4.7`; a v5 move needs a plugin-matrix check (browser/laravel plugins must follow major), not a version bump |
| 3.3 | Run-lock lifetime re-prove — the `$GLOBALS` fix is bootstrapper-shape-dependent | rethink plan, Phase U | *"re-prove before anything else"* | A silent lock regression un-protects the lane. Mid-run probe, sleep raised to 20s |
| 3.4 | Task 10 — CI pipeline per **DP-CI** (workflow + `mysql:8.0.46` service container + lane bootstrap) | rethink plan | Phase S candidate, unscheduled | Entry condition **satisfied 2026-08-10 at the R gate** (full scope, all four buckets) |
| 3.5 | Unit-suite bypass closure — arch rule so `tests/Unit` never touches DB facades | rethink plan | Phase S candidate, unscheduled | Closes 2.2 |
| 3.6 | larastan `bootstrapFiles` never run inside Rector's container → 147 per-file PHPStan errors | rector report §0b | fix (`withBootstrapFiles()`) written and **deliberately not applied** — *"a documented, deferred decision for the operator"* | Every future Rector dry run reports against a 23% error floor |
| 3.7 | **DP4** — each Rector write pass is its own operator approval; every run needs cold cache **and** serial | rector report | 🛑 GATE | `RectorScriptContractTest` warms the shared cache each suite run, so warm is the routine state |
| 3.8 | DP4 rule verdicts: `EloquentOrderByToLatestOrOldestRector` (**defer**), `SleepFuncToSleepStaticCallRector` (**defer**) | rector report | recommendations, not decisions | The first grew 2→5 files under serial and now touches the shared public query core |
| 3.9 | **DP1** grant widening to `podtext_test%` · **DP2** parallel opt-in · **DP3** PCOV for TIA · **DP5** level-6 re-measure (~426) · **DP6** `lane:reset` UX (name, refusals, who runs it) · **DP8** Pest 5 upgrade session — this tree or dedicated | notes §7 | DP1 *"second blocker, not the first"* · DP2 *"opt-in first"* · DP3 *"defer to the Pest 5 phase"* · DP6 tracked to spec F4 design · DP8 operator schedules, separate approval | Parallel suite (600s → est. 150–250s). TIA is **blocked on DP3** — no coverage driver on Herd PHP 8.4.23 (measured) |
| 3.9b | TIA replay vs the machine-global lane-lock/fingerprint bootstrap in `Pest.php` — compatibility unchecked | pest5-notes §2 | recorded pre-check, unexamined; cross-referenced both ways `7b7097d` | The lane infra assumes tests execute; replayed runs may skip lane-setup expectations. *"Same file, same scope subtleties as the lane-lock GC trap."* The source doc now points at `89a2ee1`/`810f6f2` — the S3 commits that moved that exact bootstrap mid-write |
| 3.10 | Rehearsal databases `podtext_restore_check` (3306) + `podtext_rehearsal` (3307) still live | alignment plan Task 27 Step 4 + rehearsal log | *"drop only on operator approval"* | Outstanding; no commit or note closes it |
| 3.11 | Browser plugin `4.3.1 → 5.0.0` shakedown | pest5 notes risk 2 + notes R4 | Deferred to Phase U; *"no changelog text available to pre-read"* (blocked-**empty**) | Waits/Alpine boot probes were calibrated on 4.x — a major bump re-rolls those dice |
| 3.12 | R9(b) browser/Chrome CI prerequisite + `mysqldump` on Linux runners | notes R9 | *"defer-post-U, dry-run first"* · *"verify with a real dry run, do not assume"* | Binary-discovery mechanism genuinely unverified |
| 3.13 | MUX3 **Package 5** (`F046`–`F051`: Files Discovery, physical move, Trash, restore, purge, lifecycle recovery) | media-ops/07 | *"has therefore never been gated"* | Gate-record correction: `3694919` opened Storage Truth, not Package 5. Two of its findings advanced anyway |
| 3.14 | MUX3 `F032`, `F034`, `F035`, `F036`, `F037` — five diagnostic reasons remain review-only | media-ops/07 + mini4 | **unstarted**; operator rulings 2026-07-31 unblock each | `F031`/`F040` satisfied for **one reason of six** (`unsanitized_svg` only) |
| 3.15 | Google service-account setup + `importer:probe-formats` | back-log-triage §B | **WAITING-ON-YONI since WB1** | Gates WB2 and WB4; `WB-PROBE-HF1` must run before the 20-doc probe |
| 3.16 | Prompt 13 phase 4 — on-demand RTL browser evidence | current-project-state | *"only phase 4 remains"* | The last open piece of the dashboard program |
| 3.17 | `MUX3-F022` — Spotify show artwork auto-admits; no admission choice, no per-item receipt | media-ops/07 + mini3b | *"the genuine partial in the 3B block"* | The one inherited FETCH2 candidate |
| 3.18 | Step 5B **FU04** (order-compatibility closure) then **FU05** (per-request idempotence guard) | current-project-state + step5b handoff | FU04 next and **unapproved**; FU05–FU06 later and unapproved | FU01–FU03 complete |

---

## Tier 4 — Hygiene, watch-tier, parked by budget

Real, agreed, and deliberately not scheduled. Grouped rather than ranked.

**Static analysis**
- PHPStan 443-error backlog + 5 `varTag.nativeType` — `parked-roadmap` (triage **B4**/**F10**). Slotting order is written down: two `class.notFound` one-liners → the `BuildsPublicContentSettingsSubjectSchemas` trait-host fatal (*the one genuine runtime bug*, operator may pull forward) → Carbon-macro stub → Filament-boundary narrowing (122) → own-relation typing (49) → B3+C3 enum totality → `phpstan.neon` comment refresh.
- Not wired into `composer test` on purpose — *"wire it when the count reaches zero."* Level-6 wiring is Pest-5-gated.
- Touched files must not raise their count.

**`one-home` app-wide sweeps** (dashboard scope closed `b24490a`; app-wide parked, separately budgeted)
- 63 `d/m/Y` + 7 `number_format(` lines · 52 date formats app-wide vs 10 in dashboard paths · 218 raw Tailwind colour classes vs 49.
- Two bypass sites queued as quick fixes: `PublicFormSubmissionResource.php:58`, `PublicContentItemCardPresenter.php:581`.

**R10 `keep-deferred` rows** (all judged, all cheap, none scheduled)
- Views survive `ResetTestLane` (`:79` filters `TABLE_TYPE = 'BASE TABLE'`) — moot until the schema gains a view · fixture fixed-filename in the real snapshot dir (*revisit if DP2 flips*) · describe-block placement · `+00:00` literal coupling (has a mitigating canary) · `handle()` guard extraction (~70 lines) · Task 1 test asymmetry · Task 5 session settings lost on mid-drop reconnect · Task 6 newline-free dump carry buffer.

**Pattern sweeps awaiting a trigger**
- `unarchived-binding` — sweep registered, both sightings closed · `expectation-from-home` — watch for a third sighting · `planned-fixture-drift` — *"the discipline is the fix, no sweep needed"* · `unrouted-enum` — 4+ sightings, sweep **unblocked**, queued after B1 · `no-type-home` — `StreamEventType` + intake provider strings remain · `multi-instant-render` — watch-tier, measured ≈ once per nine years · `client-payload` — watch-tier, admin-only, registered as an architectural handoff contract.

**Decisions parked on the operator**
- App-wide enum-literal ban guard — approved; unblocks when the dashboard program is declared done. First chip run was consumed without starting.
- Admin-defaulting `User::factory()` — three bites; structural fix queued for the next decisions round.
- Governance globalization — ruled dashboard-only for now.
- Tailwind scan-scope Q1–Q5: enum symmetry in public theme globs · dead `resources/css/app.css` entry (77 KB never served) · fix ownership/sequencing · split badge home · guard strictness.

**Watch items with a named location**
- E4 residual: 9 enums without Filament label/color contracts · `embed_provider` full-table `distinct` per render (`ContentItemsTable.php:173`) · Blade string-icon duplication · importer authz is panel-only · phase-3 Task-1: legacy-alias morph rows never enter `mediaEvents()` (POTENTIAL only).
- **M1** (opened 2026-08-01, not started): Storage panel has no de-dup filter (decided: hide them) and the candidate list is a silent cap (decided: paginate).
- Unfiled upstream Filament report — batched-message clone race, `partials.js`, PR #19242 unchanged through 5.x HEAD, this fingerprint unreported.
- `shared-index-entanglement`: the FilaCheck-Pro guideline prescribes `--fix --dirty`; **guideline amendment queued for the next docs pass.**
- **`silent-vendor-surface`** — candidate pattern registered 2026-08-10 in `pest5-notes.md` §2 with commit evidence (`303beab` brought time-balanced sharding into the tree via a routine package bump; POTENTIAL, not ACTUAL). Awaiting orchestrator merge into `defect-cause-patterns.md` per that ledger's one-owner rule — **do not write it there directly.**

**Product backlog, deferred by decision** (from `settings-performance/10`, `back-log-triage`, `current-project-state`)
- `MAINT-LW-UX1` — must run **before** any later public Livewire nav/polling/lazy/stream/upload expansion.
- Step 10R-P2, 10R-P3, LENS1 review packs (25–40-row packs; the 269-row table is **not** approved wholesale), `SIMPLIFY-REVIEW1` (optional, suggestions only, never a prerequisite).
- Public Front queue: `P2`, `P3`, `AX1`, `SL1`–`SL4`, `AX2`, `AX3`, `B4`, `C2`, `9F-A`–`9F-C` — all **DEFER-POST-13**; the AX arc moved last 2026-07-31.
- `WB2`–`WB7` pending, Path A in effect.
- `transcript_file` imports · curated homepage query sections · homepage result previews in admin forms · footer-builder v2 + nested/dropdown menu editing · public form email notifications and file uploads · "select all filtered" · associate-existing transcription · separate public volunteer/contributor profile table · EP1 per-user presentation preference · EP1 paste-cleanup/`[]` conventions · IMG-3 zip packages · C4 category images · H8 expandable pulse row · Livewire islands · Chart.js option C.
- Production human checks (legacy role assignment, `transcription_mode=single`, normalize dry-run, Redis/settings-cache scoping, `FORMS_OTP_*`, Resend/DNS, mail verification) · real mailer + from-address before any mail-sending feature · Spotify credentials on production when the fetcher runs there.
- **Documentation Architecture and Consolidation Audit** — *"the prompt has not been written and the task has not started."*
- `MUX3-F039` reopens for the second cycle (D8's "intrinsic metadata rows" seed predates Storage Truth) · `MUX3-F044` assigned to the deferred admin Markdown previewer task, and **must be terminally closed if that task is cancelled** · `MUX3-F047` partially superseded — bounded journaled move shipped, general case future-only.
- Moderator, transcriber and user roles have no admin panel access in v1 (by design); LENS1 owns the public copy/vocabulary cleanup.
- `Q1`–`Q18` in `public-front-v2-open-questions.md` are formally unresolved, but 17 of them are answered by the same file's own "Resolved User Decisions" block and by the execution plan. **Needs one reconciliation pass, not 18 decisions.**

**Research follow-ups**
- `pest-plugin-phpstan` × agent-mode formatter vs `LarastanCastResolutionGuardTest`'s isolated-config trick — *"worth a look, not a worry."*
- `toBeUlid()` can replace the hand-rolled ULID regex in test-side assertions (production regex stays) — Pest-5-gated.
- `pest-plugin-rector` (Pest coding-style set, tests/ paths, dry-run only) · version pins are deliberate, the upgrade is an operator decision · peripheral gear naming Pest (`filament/blueprint`, FilaCheck, `spatie/*`) — a `composer update` dry run surfaces conflicts.
- Leading-wildcard `FoldedSearch::pattern()` gets no range access; local holds 10 `content_items` rows — **re-run both `EXPLAIN`s against production row counts before sizing.**
- R8 residual gap, recorded honestly: a table sorted by `created_at` whose sort lives only in the Filament resource is invisible to the grep patterns used.
- Two contributor names garbled by auto-captions — verify against the Pest repo before crediting anyone in writing.

---

## Tier 5 — Accepted forever / explicitly not doing

Listed so nobody re-litigates them.

- **F8** `possible_keys` vs `key` — asserting `key` would pin an optimizer tie-break MySQL does not guarantee. In-test comment is the record.
- **Autumn DST fold** — one hour of ordering ambiguity once a year on manually-typed input. `accept-later`.
- **Task 24 guard scoping** — pre-alignment migration files are permanently exempt by lexicographic date compare on `2026_08_09_000000`.
- **Alignment §12 non-goals** — no indexes on the LONGTEXT shadows (a B-tree gives no range access under `LIKE '%term%'`) · JSON columns untouched, scalar comparison stays `utf8mb4_bin` · the 23 `datetime` casts stay (a future immutable move is *plausible, as its own decision*) · not a schema redesign, not a search change.
- **mysql-lane rejected outright** — relaxing the guard to "any connection when `APP_ENV=testing`"; removing `force="true"`.
- **`MUX3-F038` Recheck/Retry** — declined twice. *"Must stop being re-litigated as if undecided, and it must not be retired."*
- **Triage D1** — commit `ce0f3a0` won't bisect (it references `HasFoldedSearchColumns` before the file exists). `OPEN`, **ruled leave-and-record**: HEAD is correct, only bisect is affected. *"Next: nothing, unless a history rewrite is wanted."*
- **`abstraction-blinds-the-detector`** — open as a *standing rule*, both instances closed: when a call-site-matching tool goes blind because a pattern was centralised, that is evidence the abstraction worked. Teach or replace the detector; never un-abstract the code.
- **`imports.name` / `file_name`** deliberately unfolded (vendor model, machine filenames).
- **Time-balanced sharding** — present in the installed Pest 4.7.8 (`Shard.php`, `--update-shards`, `tests/.pest/shards.json`) but idle by choice: it is a CI horizontal-scaling feature and this suite runs locally on the lane. Nothing to do; know it exists.
- **FULLTEXT / Scout** — the only thing that makes `%term%` fast; deliberately out of scope. Transcript-body search is "a different project."
- **Step 11 seeders/demo content** — DROPPED 2026-07-31, do not revisit. **EXIF stripping**, **record-level clone**, **Playwright-scraping Spotify** — all closed/rejected.
- **`App\Auth\LegacyRoleBackfill` deletion** — proposed and rejected; revisit only if a third kind of cost appears.
- **`JerusalemDailySeries` name** and the 12 test files with hardcoded timezone — correct as written, not a miss.
- **`ContentItemForm::featured_transcription_id`** and **`TranscriptionForm::content_item_id`** stay create-disabled; `SpatieTagsInput` stays plugin-managed.
- **`sqlMoment()` stays** — it does not revert to `CURRENT_TIMESTAMP`.
- **`PrivateArtifactRepository`** — do not speculatively generalise.
- Governance rules live in `dashboard-governance-principles.md`; the cause-pattern ledger has **one owner** (the orchestrator session) — two writers on one ledger would be one-home itself.

---

## Appendix A — Contradictions between source documents

Each is a stale line in a real file. Fixing them is a docs pass, not engineering.

| # | Item | Doc that says open | Doc that says closed | Verdict |
|---|---|---|---|---|
| A1 | **F9** per-boot `information_schema.COLUMNS` count | `open-findings-triage.md:249` — *"`accepted`, pending one measurement"* | `test-suite-rethink-notes.md` R3: 0.524 ms × 1,931 boots ≈ **1.0s = 0.16% of a 622.2s wall**; *"F9 verdict: stays accepted, closed"* | **Closed.** Triage line is stale — the measurement it waits for was taken |
| A2 | **DP9** `DB_CONNECTION` default | *(was stale; now fixed in-source)* | `203125c` + `57c1898`, both verified | **Shipped.** The live session already corrected `F11` to read *"DP9 is CLOSED"* mid-harvest |
| A3 | `decorative-cap` (P14) | `defect-cause-patterns.md` — *"open — fix queued in the post-B1 batch"* | Same doc's Post-B1 row + `abd46f3` *"rewire three decorative-cap filters"* (verified) | **Shipped.** Status line stale |
| A4 | `service-hop-cost` (P15) | Same doc — *"open — fix queued in the post-B1 batch"* | Same doc's checklist: 105 → 3 queries | **Shipped.** Status line stale |
| A5 | Dashboard **A4** | `current-project-state.md` ~L1558 — *"A4 and the phase-4 evidence items remain open"* | remediation audit + `c36f6c4` *"make the reason-bar doorway promise true on-board (A4)"* (verified) | **Fixed.** Only phase-4 RTL evidence (3.16) is still open |
| A6 | Dashboard **F1/F2/F3** | `dashboard-metrics-phase-2R-handoff.md` "Findings raised" table marks them Open | Same doc's queue entries with `b24490a`, `b3d6de4` | **Fixed.** Table is stale against its own queue |
| A7 | Four R10 `keep-deferred` rows (`dropStatements` backtick doubling, `LANE` const, `fopen`-message conflation, ignored `unlink()`) | `test-suite-rethink-notes.md` R10 verdicts them keep-deferred | `e9ac5cf` shipped all four (verified) | **Fixed anyway.** R10 table stale for those rows |
| A8 | `mysql-test-lane-spec.md` §3 target collation | Shows `utf8mb4_unicode_ci` | alignment spec: `utf8mb4_0900_ai_ci` everywhere | **Superseded.** Only the top banner covers this; §3 itself was never updated |
| A9 | `database-alignment-spec.md` status line | *"approved … **Not implemented**"* | Program closed `6dbc3be` (verified) | **Stale header** |
| A10 | `pest5-rector-phpstan-notes.md` header + §1 | *"Nothing here is installed or changed"* / *"`rector/rector` is not installed and there is no `rector.php`"* | `7b6b52e` installed it (verified) | **Stale header** |
| A11 | Both implementation plans' checkboxes | `test-suite-rethink`: 0 of 61 ticked · `database-alignment`: 0 of 107 ticked | ~95% and 100% executed, commit-verified | **Checkbox state is not status.** Driving from boxes re-does finished work |
| A12 | MUX3 "separate phase" copy | Issue Review still says folder moves are *"handled in a separate phase"* | That phase shipped | **Stale copy** |
| A13 | `current-project-state.md` §Tooling State | Laravel 13.21.1 / Filament 5.7.3 | 13.23.0 / 5.7.5 elsewhere in the tree | **Stale versions** |
| A14 | Triage §F item count | Sweep reported F1–F11 | File carries **F12** (added 2026-08-10) | **Register gained an item mid-harvest** — see the warning at the top |
| A15 | Time-balanced sharding | `test-suite-rethink-notes.md` §3 — *"CI-day concern; there is no CI. **Parked.**"* and §6 listed it as Pest-5-gated | `laraveldaily/pest5-notes.md` §1f — verified in the installed tree: `Shard.php` in **pest 4.7.8** already implements `--shard`, `--update-shards` and the `tests/.pest/shards.json` warning | ✅ **RESOLVED AT SOURCE `7b7097d`** — both sites corrected (verified): §3's parked note now states it is not Pest-5-gated, §6 no longer claims it. Idle by choice, not by gate |
| A16 | **Task S3 / D3 / DP7** cross-worktree lock | Every doc harvested at `0437705`, incl. this register's first draft, calls it the last open Phase S item | `89a2ee1` + `810f6f2`, Phase S closed `a50b15f` | **Shipped mid-harvest.** Any doc still describing the fingerprint as living under `storage/framework/testing/mysql-lane/` is stale — it is `~/.cache/podtext-test-lane/` now |

---

## Appendix B — Sources and item counts

Counts are the census used to verify nothing was dropped (`grep -c` on each doc's own ID convention).

**Cluster A — defect / test / database**

| Doc | Mod | Items |
|---|---|---|
| `docs/phase-02/open-findings-triage.md` | 08-10 | 28 headings: A1–A5, B1–B5, C1–C3, D1–D3, F1–**F12**, §E ×4 · 17 `FIXED`, 8 `OPEN`, 1 `WITHDRAWN` |
| `docs/research/defect-cause-patterns.md` | 08-10 | 33 pattern entries + 2 tracking tables · aliases P1–P16 complete, no gaps |
| `docs/phase-02/test-suite-rethink-implementation-plan.md` | 08-10 | Tasks 1–11 + S1/S1b/S2/S2b/S3/S4/S5 · **0 of 61 boxes ticked** |
| `docs/phase-02/test-suite-rethink-spec.md` | 08-10 | 6 decision records + out-of-scope list + Phase U gate |
| `docs/research/test-suite-rethink-notes.md` | 08-10 | R1–R10 + R10 verdict table (5 `fix-in-S`, 12 `keep-deferred`) + DP1–DP9 + DP-CI |
| `docs/research/pest5-rector-phpstan-notes.md` | 08-10 | 5 pre-upgrade risks + 6 expand-later items |
| `docs/research/laraveldaily/pest5-notes.md` | 08-10 | **New, arrived mid-harvest.** 6 v5 features × local relevance; *"No proposals"* — 2 TIA pre-checks recorded, everything else Pest-5-gated |
| `docs/research/rector-dry-run-reports/2026-08-10-laravel-code-quality.md` | 08-10 | 5 rule verdicts (0 adopt / 2 defer / 3 reject) + §0b, §0c gaps |
| `docs/phase-02/mysql-test-lane-spec.md` | 08-09 | §8 residuals 1–7 + 2 rejected-outright |
| `docs/phase-02/hebrew-collation-and-clock-plan.md` | 08-09 | B1–B9 (6 dissolved, 3 survived and ran clean) — `DO NOT RUN`, superseded |
| `docs/phase-02/database-alignment-spec.md` | 08-08 | §13 Open ×5, §10.1–10.7 caveats, §12 non-goals |
| `docs/phase-02/database-alignment-implementation-plan.md` | 08-08 | Tasks 1–27 · **0 of 107 boxes ticked** · 2 operator stops |
| `docs/phase-02/database-alignment-rehearsal-log.md` | 08-09 | 3 defects (all fixed) + rehearsal-DB drop pending |
| `docs/research/browser-timeout-contention-investigation.md` | 08-04 | Open flags 1–5 + ranked fixes 0–4 |

**Cluster B — product / feature backlog**

| Doc | Mod | Items |
|---|---|---|
| `docs/research/media-operations-ux3/07-program-reconciliation-and-finding-coverage.md` | 07-31 | `MUX3-F001`–`F051`, **51 distinct IDs verified** · 18 implemented, 24 pending with an owner, 6 Package 5, 2 not reproduced, 1 satisfied |
| `docs/research/settings-performance/10-pending-decision-question-queue.md` | 07-16 | 14 surviving-work rows + a deferred/not-current block |
| `docs/phase-02/back-log-triage-2026-07-13.md` | 07-31 | §A ×6, §B ×2, §C ×8, §D ×5 (all answered) |
| `docs/phase-02/public-front-v2-open-questions.md` | 07-31 | Q1–Q20 (19/20 struck through) + 17 resolved decisions |
| `docs/phase-02/current-project-state.md` | 08-10 | 3 open sections: Known Blockers, Deferred Items, Media Ops Forward Route |
| `docs/phase-02/dashboard-metrics-phase-2R-handoff.md` | 08-05 | E4, M1, anti-drift + a stale findings table |
| `docs/phase-02/dashboard-metrics-phase-{1,2}-handoff.md`, `-phase-1-2-remediation-audit.md` | 07-31 | A1–A4, B1–B7, C1–C4, D1–D2, E1–E5 · deviations 1–8 |
| `docs/phase-02/settings-step5b-*-handoff.md` + `docs/research/settings-performance/33-*.md` | 07-31 | 13-item deferred inventory (items 8/9 = FU05/FU04 live) + O1 findings |
| `docs/phase-02/media-operations-ux3-mini3b/mini3c/mini4-*.md`, `roles-gates-roles1-handoff.md` | 07-31 | Per-mini deferred lists + the D2=A lapse record |

**Deliberately excluded** (not selected for this pass — both are live open-finding lists in the window):
`docs/research/authz-gate-surface-map.md` §4 — 7 ranked gate holes, incl. policy-less resources reachable by any panel user, two ungated actions, two silent-deny paths. Several would land in Tier 1–2.
`docs/research/laraveldaily/supply-chain-security-notes.md` §7 — commonmark 2.8.3→2.9.0 advisory, `.env.*` gitignore gap, `composer audit` in the gate, Forge deploy-script verification.

**Checked, nothing open:** `browser-timeout-contention-run-log.md` · `filament-examples-phase-02.md` · `php-enums-playbook.md` · `dashboard-widget-principles.md` · `dashboard-governance-principles.md` · `dashboard-metrics-combined-ux-plan.md` · `dashboard-metrics-filawidgets-adoption-analysis.md` · `dashboard-metrics-phase-3-plan.md` (D-4 decided) · `dashboard-metrics-phase-4-plan.md` ("None blocking") · `episodes-lens-design-research.md` · `episodes-lens-design-spec.md` §9 (pattern-avoidance audit, not live defects) · `episodes-lens-r1-implementation-plan.md` · `hebrew-search-folding-spec.md` (§6 answered 2026-08-06) · `larastan-playbook.md` (policy, not triage) · `media-program/00`–`04` · the 15 remaining `docs/research/laraveldaily/*` notes.

---

## Already fixed — closing list

One line each, no description. SHA where the source doc gives one; all SHAs quoted below were spot-checked against `git log`.

**Triage §A–§D**
- A1 clock is a timezone, not a broken clock — `FIXED` 2026-08-09.
- A2 DST-observing `IDT`, not fixed `+03:00` — `FIXED` 2026-08-09.
- A3 schema default `utf8mb3` — `FIXED` 2026-08-09.
- A4 `mysql.time_zone_name` unreadable — `FIXED` 2026-08-09.
- A5 production 8.0.46 vs local 9.4.0 version identity — `FIXED` 2026-08-09.
- B1 enum/datetime casts resolving as `string` — `FIXED` 2026-08-07.
- B2 the motivating rule doesn't fire — `WITHDRAWN`, the premise was wrong.
- B5 relationship generics missing on 43 of 45 relations — `FIXED` 2026-08-07.
- C2 two enums outside `app/Enums` — `FIXED` `005eda6`.
- D2 push pairing and backlog — `FIXED` 2026-08-08.

**Triage §F (the post-alignment residual round)**
- F1 dead `expected('sqlite')` branches — `e3a539c`.
- F2 nullability-drift fixture coverage — `7cdaadf`.
- F3 `EpisodesTableR1Test` payloads dodging the DST rule — `982a65b`.
- F4 fresh-worktree lane-fingerprint refusal — `a45efc4`, `0f3a32a`, `274b536`, `fb6b212`.
- F5 undocumented `mysqldump` + `gzip` prerequisites — `732c710`.
- F6 pre-alignment snapshot replay under the pinned connection — `17c19ff`, `f83949f`, `3bba8cc`, `a63d23f`.
- F11 SQLite artifacts, and DP9's default flip with it — `d1d0671`, `203125c`, `57c1898`.

**Test-suite rethink**
- Phase R (measurement round) — `c7f3c19`, `b53c095`.
- Phase T (Rector wired to larastan, dry-run-locked) — `7b6b52e`, `c780a9c`, `1701bdc`, `89eb07b`, `00a4ff6`.
- S1 + S1b stable browser reads for the layout booleans — `c720777`, `cd6f88c`, `f1793cf`.
- S2 + S2b settings-backup snapshot job faked — `cef86bc` (149.7s → 5.8s), `0437705`.
- S4 review one-liners (LANE const, failure messages, backtick doubling, stale comment) — `e9ac5cf`.
- S5 DP9 config default — `203125c`, `57c1898`.
- **S3 machine-global lane lock + fingerprint, closing the cross-worktree gap (D3/DP7)** — `89a2ee1`, `810f6f2`.
- Phase S closed, with the machine-global lane paths written into the spec and the R/T/S record into the state doc — `a50b15f`, `4176bf1`.
- Rector's two-package distinction led in the notes, and the extension-wiring rule recorded in `.ai/rules/general.md` — `b971c6f`, `e753327`.
- Rector `withPHPStanConfigs` boot crash, fixed by the two-path form and pinned by `RectorScriptContractTest`.
- Rector parallel nondeterminism, fixed by `withoutParallel()` with the string asserted in the contract test.
- R5 coverage-honesty risk did not materialize; R6 found no PHPUnit 13 blockers; R8 found no new DATETIME-tie flake candidates; R9(a) achievable without weakening a clause.

**Database alignment (program closed `6dbc3be`, 55 commits `94a3328..7d715c9`)**
- Tasks 1–27 across six phases, `db:check-settings` exit 0 on local and production.
- Rehearsal defect 1: seeder colliding on composite-unique pivots — `1c0d294`.
- Rehearsal defect 2: oracle hashing the `migrations` ledger — `8abadbe`.
- Rehearsal defect 3: seeder manufacturing B5 findings at truncation boundaries — `cc86be5`.
- Hebrew-plan blockers B1, B2, B4, B6, B7, B9 dissolved by the in-place `ALTER` design; B3, B5, B8 survived and all ran clean on production 2026-08-08.
- Lane bring-up: 89 red tests → green in 12 commits, surfacing two genuine app defects — `de83d26`, `e031bcb`.
- Post-close fixes: recursive model scan and snapshot-restore caveat `7d715c9`; run-lock held for process lifetime `538d8d9`.

**Cause patterns (20 of 33 closed)**
- `proxy-oracle`, `double-registration` (census clean across all 71 events), `event-halting-return` `9b494a4`, `flake-label` (contention hypothesis resolved), `line-guard` (met), `options-state-cast`, `unpinned-promise` `c36f6c4`, `unscanned-home` `b9825c6`/`12285a7`/`ecc9eda`, `two-writer-channel` `3cc4906`/`914de1c`, `fake-root-purge` `a3fa4f2`, `driver-lenient-fallback`, `db-clock-coupling`, `test-residue` `a14d50a`, `boundary-type-loss`, `decorative-cap` `abd46f3`, `service-hop-cost`, plus the partially-closed `one-home`, `raw-state`, `implicit-keys`, `silent-cap`.
- Route checklist: all 22 rows done; operator questions Q1–Q7 all decided; closure pushed `72f307e`, release `74780426`.

**Dashboard metrics**
- A1 `2831ee9`, A2 `b9825c6`, A3 `103b728`, A4 `c36f6c4`, B1 `168a618`/`87aa8bf`/`cbee0d3`, E5, F1 `b24490a`, F2 `b24490a`, F3 `b3d6de4`.
- Remediation audit A1–A4, B1–B7, C1–C4, D1–D2, E2 — all fixed; E1 and E3 closed later by phase 4.
- Phase 2 deviations 3 and 4 resolved by phase 2R (two tiers; filawidgets data contracts adopted).
- M2 media-picker clone race — CLOSED 2026-08-02 via per-mount key nonces, with a deterministic repro test.
- `OwnerImageWorkspaceTest` 1-in-4 flake — CLOSED 2026-08-03, same mechanism, 10/10 soak.
- Phase 3 fresh re-reconciliation — COMPLETE 2026-08-04, 12 commits.

**Media operations UX3**
- `MUX3-F001`–`F016`, `F023`, `F042`, `F043` implemented; `F017`–`F021` closed 2026-07-28; `F024`–`F030` closed 2026-07-28.
- `F033` SVG sanitize shipped; `F045` evidence gap closed `def171b`; `F048` retention contract shipped (`media:prune-quarantine`, 90-day default).
- `F041` and `F044` not reproduced in 3A.
- Browser-test hygiene and the contention investigation both closed end-to-end, both defects fixed.

**Product / process**
- Public Front Q19 and Q20 closed 2026-07-30 by operator decision — do not re-raise.
- ROLES1 first-super-admin promotion done, proven by Users-resource visibility.
- Step 11 seeders/demo dropped; EXIF stripping closed; record-level clone dropped; Playwright-scraping Spotify rejected; MP2 gate numbers closed-as-documented; SF1/TOOLS1↔WB7 closed.
- Step 5B FU01, FU02 `a8be0aa4`, FU03 `659762f9`, O2 `f56ef369`, RECON2 R3, renderer Tailwind `@source` coverage, un-flattened validator paths, aligned sample ranking.
- Step 5B Card Template Admin Preview UX — complete. SP3 browser acceptance evidence — retired, do not reactivate.
- MySQL test lane BUILT 2026-08-09; collation change to `utf8mb4_0900_ai_ci` DONE 2026-08-09.
