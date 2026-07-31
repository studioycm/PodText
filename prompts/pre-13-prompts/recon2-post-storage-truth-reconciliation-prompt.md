# RECON2 — Post-Storage-Truth Reconciliation

> Kickoff prompt. Self-contained: paste into a fresh session and start at R1.

## What this is, and why it has a name

After the Storage Truth work closed (2026-07-30), a full audit of the media
program's records against the actual code found that the documentation layer had
drifted badly behind reality — and that two real, user-visible bugs had been
filed as "performance work" and therefore never triaged as defects.

This body of work is **RECON2** (`RECON2-POST-STORAGE-TRUTH`). The name follows
the existing convention: `docs/research/media-operations-ux3/07-program-reconciliation-and-finding-coverage.md`
was the first reconciliation, and this is the second. Its phases are `R1`–`R6`.
Do not call it "Package 5" or "P5" — see the naming rules below.

RECON2 exists to do three things:

1. **Fix the two real bugs** the drift concealed.
2. **Make the records true**, so nobody re-plans finished work again.
3. **Land the operator's decisions** on questions that had been open for weeks.

## Ground rules

- **Local repository work only.** Everything in this document is code, tests and
  documentation on the developer machine. Do not connect to, inspect, or change
  any remote or production system. Items that would require production access are
  explicitly marked "operator-only" and are out of scope for this task.
- **Repository:** `/Users/studioycm/Herd/PodText`, branch `main`.
- **Stack:** Laravel 13, Filament 5, Livewire 4, Pest 4, Tailwind 4, PHP 8.4.
  Hebrew-first RTL admin and public front; dates day-first, `Asia/Jerusalem`.
- **Test/verify gate** for every phase:
  ```
  php -d memory_limit=2G vendor/bin/pest --compact
  vendor/bin/pint --dirty --format agent
  vendor/bin/filacheck          # never --fix without explicit approval
  npm run build                 # only when assets change
  ```
  The `-d memory_limit=2G` is required; the CLI default of 128M causes an OOM.
- **TDD, bounded.** Write or update Pest tests for behavior, not class existence.
  If a test fails in a way that looks environmental, attribute it before chasing:
  stash, re-run on clean `main`, compare. Do not chase indefinitely.
- **Commits stay local.** Commit each phase with a real message. Pushing and
  deploying are separate actions the operator approves per batch; do not push
  without being asked.
- **Documentation culture is strict here.** `docs/phase-02/current-project-state.md`
  is the single source of truth for rolling progress and must be updated before
  the final commit of each phase. Operator-accepted artifacts are amended with
  dated notes, never silently rewritten.

## Naming rules (a collision was just untangled — do not re-tangle it)

- **Storage Truth** (`UX3-STORAGE-TRUTH`, phases P1–P5) is the work that
  **shipped** 2026-07-29/30: the managed relocation engine, the root-file
  relocation, legacy owner-column retirement, the column-drop migration, and the
  in-use sanitize lift.
- **Package 5 — Files and Physical Lifecycle** is the media-program package that
  has **not started**: Files Discovery, general physical move, Trash with
  retention, Restore, Purge, operator-visible lifecycle recovery
  (`MUX3-F046`–`F051`).
- A third sense exists and is fine: `3B P1–P5` means a mini-task's own phases.
- Bare "P5" is no longer used for a package. Dossier IDs and commit subjects
  already in git are immutable and keep their original wording.

---

# Operator decisions (authoritative — these are settled, do not re-ask)

These were open questions in the media findings matrix. The operator ruled on
2026-07-31. Implement to these rulings.

| Question | Ruling |
|---|---|
| **`MUX3-F035`** — may an operator mint a portable reference key on an existing row? | **Yes**, when the key is missing. It is a legitimate repair. |
| **`MUX3-F037`** — may an admin flip a file to public audience from Issue Review? | **Yes, super-admin only.** The action must inherit the same weight as the SVG trust mark: explicit consequence dialog, recorded actor and timestamp, revocable. |
| **`MUX3-F036`** — is a wrong storage disk an editable record defect? | **Yes, conditionally.** The app should detect that the named disk does not exist, and in that state let a **super-admin** correct it to an existing configured disk. Not a free-text edit. |
| **`MUX3-F032`** — missing files | Provide **both** `restore-by-upload` **and** `detach-and-delete`. Detach-and-delete needs a confirmation step and danger styling. Additionally: the `swap` machinery must be made able to operate on **referenced** rows for these intended cases — that is the cohort this feature exists for. |
| **`MUX3-F038`** — the Recheck button | **Defer, do not retire.** It is wanted later. Record it as an explicit deferred item with its unmet proof gate intact, so it stops being re-litigated as if undecided. |
| **EXIF on upload** | Get a fix plan and implementation into the next suitable mini-task. |
| **`word_count`** | Add the computation back to the plans, or execute it in the next suitable task. |
| **Step 10R-P2 tripwire** | Settle P2's scope ambiguity explicitly first, then implement it. |
| **Quarantine** | It is an asset, not a leak. Record that, and plan the prune carefully. |
| **`MUX3-F044`** | Already assigned (2026-07-30) to the deferred admin Markdown previewer task, with a reopen route. No further action. |
| **FilamentExamples source access** | Closed by decision. The `filament-examples` MCP `search_examples` tool is the sanctioned route. Do not re-raise. |

## Operator corrections to earlier assumptions

- **`podtext.data4.work`** is simply the original domain of this same app on the
  hosting panel — the folder is named after it, and `podtext.co.il` is now the
  primary domain. The Google redirect URI was updated to `podtext.co.il/...`
  weeks ago. **Action:** the backlog-triage line proposing its removal is stale
  and confusing; correct or delete it in
  `docs/phase-02/back-log-triage-2026-07-13.md:53`. Nothing else to do.
- **First super-admin:** already promoted. The operator can see the Users
  resource in the production admin, and `UserResource::canViewAny()` is
  `Gate::allows('super-admin')`, so visibility is proof. Close this item.
- **Spotify credentials:** believed already configured (deployed long ago).
  The remaining concern is that an *untested* connection silently falls back to
  reduced mode, because the fetcher's query only accepts `status = connected`.
  **In-scope local work:** make reduced-mode use clearly indicated in the UI.
  Verifying the actual production connection state is operator-only.

---

# Audit findings (evidence for the work below)

All verified against code at commit `15f2aa2`.

## Two real bugs

**B1 — `word_count` is never computed.** No application code assigns it.
`Transcription::booted()` sets `reference_key`, `language_code` and `status` and
stops. The column is fillable (`app/Models/Transcription.php:27`), cast (`:252`),
exported and shown in admin tables — but only factories and seeders ever fill it,
which is exactly why the test suite cannot catch this.

Consequence: `public_total_word_count` is
`sum(coalesce(transcriptions.word_count, 0))`
(`app/Support/PublicContent/PublicTranscriptionAggregates.php:30` and `:47`),
consumed by `app/Support/PublicFront/Cards/PublicContentGroupCardPresenter.php:62`
and `resources/views/filament/public/pages/show-content-group.blade.php:13`. Any
transcript created through the admin or an import therefore advertises **zero
words** on public cards and podcast headers. The transcript viewer itself is fine
— `app/Livewire/Public/ContentItemTranscriptViewer.php:110-127` has a recompute
fallback — which is why the reading page looks right while the cards lie.

**B2 — uploaded images keep their EXIF, including GPS.** `ImageUploadValidator`
has two tails selected by `$preserveRaster`: a re-encode tail
(`app/Support/Media/ImageUploadValidator.php:179-184`, `strip: true`) and a
verbatim tail (`:164-177`). The live admin upload path, the picker upload, and
external/Spotify fetches all use `preserveRaster: true` and write bytes
unchanged (`app/Support/Media/MediaAcquisitionManager.php:207`). Sanitize
(`MediaFilesystemMutationCoordinator.php:648`) and Swap (`:310`) **do** strip.

So EXIF handling is currently *inconsistent*: two images uploaded the same day
differ depending on whether an admin later hit Sanitize. The old deferral said
this waited on "an image re-encoding step"; that step exists, and was then
deliberately removed from admission by requirement **MI-R044** ("Raster
admission preserves source bytes",
`docs/research/media-program/00-media-program-requirements-decisions-and-method.md:78`).
The precondition arrived and was reversed, so the triage note is misleading.

**Design note for the fix:** flipping `preserveRaster` to `false` is the wrong
fix — it also re-compresses at quality 90, silently degrading originals. The
right shape is a metadata-only strip pass before `Storage::put` in
`MediaAcquisitionManager::admitNewFile` (`:204-220`), which keeps image bytes
otherwise intact. Note that byte-verbatim admission is load-bearing for the
mutation journal's `source_sha256`/`quarantine_sha256` invariants, so either
strip *before* the hash is taken, or record a decision amending MI-R044.

## Records that are false

**D1 — fourteen findings marked "pending" are actually shipped.** The matrix
`docs/research/media-operations-ux3/07-program-reconciliation-and-finding-coverage.md`
still shows `MUX3-F017`–`F021` as `pending — Mini-task 3B` and `F024`–`F030` as
`pending — Mini-task 3C`, plus `F045` as an evidence gap and `F033` as pending.
All are implemented and operator-closed (3B `a638fd4`, 3C `bc0ce8f` + closure
`9393fef`, M4 `24b13fb`, F045 proven in `def171b`). Anyone planning from the
matrix would rebuild finished work.

**Root cause:** 3B and 3C were closed but **never got handoff documents**.
`docs/phase-02/` has mini1, mini2, mini3, mini3a and mini4 handoffs — no 3B, no
3C. No handoff, no matrix update.

`F022` is the one genuine partial in that block: Spotify artwork auto-admits
(`SpotifyShowInput.php:84-91`) with no admission choice and no per-item receipt.

**D2 — the same pattern hit Step 5B.** The "deferred inventory" in
`docs/research/settings-performance/33-step5b-card-template-renderer-overhaul-research.md:184-207`
and `docs/phase-02/settings-step5b-card-template-preview-lg-column-handoff.md:279-292`
was copied forward verbatim after O2/FU02/FU03 shipped. **Six of its thirteen
findings are resolved in code**: O2 (`f56ef369`), FU02 (`a8be0aa4`), FU03
(`659762f9`), the Tailwind `@source` coverage (`resources/css/filament/admin/theme.css:6`
and `.../public/theme.css:6`), the flattened validator paths, and the sample
ranking.

**D3 — an M4 safety boundary lapsed silently, and the operator accepts the new
boundary.** M4 decision D2=A restricted sanitize to managed rows, recorded as
safe because there were "zero live production targets until relocation". Both
halves have since changed: commit `3900645` removed the managed-scope guard from
the `repair` ability (verify: `app/Policies/CuratorMediaPolicy.php:124-150` has
no `MediaRecordScope::allows()` check, while `delete` at `:47` and `swap` at
`:180` still do), and the relocation moved all root-level rows. The M4 handoff,
written afterwards, still narrates D2=A as operative. **Lapse it properly**:
record that D2=A no longer applies, that sanitize now has live targets, and that
the operator accepts this boundary.

**D4 — a research gate was opened for the wrong package.** Commit `3694919` is
titled "close mini-task M4 and open package 5 research gate", and its content
opens research for what is now called Storage Truth. The **lifecycle** package
(F046–F051) has therefore never actually been gated, while two of its findings
advanced without that approval: bounded physical move shipped and ran, and every
committed mutation now writes permanent quarantine copies. The operator wants
help resolving this — see R4.

## Quarantine: an asset with a dangerous naive fix

Every committed rename/swap/sanitize/relocation/delete/registration writes a
verified copy of the original bytes to `local:media-quarantine/{operationKey}/`
and never removes it; deletion happens only on the rollback path
(`MediaFilesystemMutationCoordinator.php:458`). Retention past completion is a
**tested invariant** (`tests/Feature/MediaMutationCoordinatorTest.php:344`), not
an oversight. Local footprint is ~592K; production is ~35MB, dominated by the
394-row relocation. Unbounded, invisible, but not urgent.

It is an **asset**: the journal already records the quarantine path and
checksum, the exact original source path, the media id snapshot, the reference
key, the actor and the timestamps — roughly 85% of a Trash substrate. The gap is
the human-authored `alt`/`title`/`caption`/`description`, which are hard-deleted
and journaled nowhere; widening the delete `context` at
`MediaFilesystemMutationCoordinator.php:359-361` would close it, and `context`
is inert to the machinery.

**The one thing that makes a prune dangerous:** pruning by file age or by
directory sweep. For any row still at `committed` or `cleanup_pending`, the
quarantine file is a hard precondition — `completeCommittedCleanup()` re-asserts
its checksum at `:772-777` before deleting the public source, and `repair()`
re-drives that path. Remove the file and the row can never complete, and because
`MediaMutationFence::assertNoOpenMutation()` treats those statuses as open, that
media row could never be renamed, swapped, sanitized, relocated or deleted
again, with no admin UI to unwedge it. This is not hypothetical: the local
database currently holds two `sanitize` rows stuck at `cleanup_pending` since
2026-07-28. **Age tells you nothing. Prune on `status = completed` and
`completed_at` only.** Also do not null `quarantine_path` afterwards —
`assertOperationShape` (`:1412-1418`) requires it non-blank for those types.

## FU04 / FU05 / FU06 — all still needed, none obsolete

- **FU04** (`STEP5B-CARD-UX2-FU04-ORDER-COMPAT-CLOSURE`) — "pin effective-order
  semantics and import/public/focused-save compatibility without a global
  cutover". The effective-order computation is still duplicated: inline at
  `app/Filament/Pages/BuildsPublicContentSettingsSubjectSchemas.php:3132-3144`
  and authoritatively at `app/Support/PublicFront/PublicFrontConfigValidator.php:249-251`
  and `:307-309`. They agree behaviorally today, so this is duplication risk, not
  a live bug. Focused-save contiguity is **already done**
  (`CardTemplateDraftNormalizer.php:75,86`). Missing: the extracted helper, the
  boundary tests (negative, >1000, non-numeric, duplicate — none exist), and the
  import/edit regression (`tests/Feature/SettingsImportExportTest.php` contains
  zero occurrences of `order`). ~3–5h, mostly tests.
- **FU05** (`-FU05-INTERACTION-CLOSURE`) — one refresh, plus move-modal keyboard
  evidence. **Trap: do not delete either refresh hook.**
  `CardTemplateEditorPage.php:195` and `:397` fire on *different* triggers; the
  correct fix is a per-request idempotence guard inside `refreshPreview()`. The
  move modal itself is implemented (`:3008-3065`, with `autofocus()` and
  `onfocus="this.select()"`); only browser evidence is missing. ~2–4h.
- **FU06** (`-FU06-COPY-CLEANUP`) — nearly free. **12 strings** still describe
  "future renderers" for features that are live: `lang/en/admin.php:669,670,671,673,683,689`
  and `lang/he/admin.php:684,685,686,688,697,703`. Plus 4 provably dead
  `card_template_part_order` keys (`en:259,685`, `he:203,699`) with zero PHP or
  Blade references. ~1–2h, very low risk.

## P2 / P3 — the tripwire, settled

`MAINT-LW-UX1` is a **named deferred task that has never run** (zero commits, no
prompt, no code). Its precondition: *"run before any later public Livewire
navigation/polling/lazy/deferred/stream/upload expansion."* That baseline still
holds — grepping `app/Livewire/Public/` and the public views for `wire:poll`,
`wire:navigate`, `wire:stream`, `#[Lazy]`, `wire:init` or `WithFileUploads`
returns zero hits.

**Step 10R-P2** ("bound listing fetch windows, lazy filter options/form
definitions, opt-in aggregates") has an ambiguous scope word:

- *Reading A — PHP-lazy*: memoize/cache the five option builders so they resolve
  once per request instead of per call. **Does not trip the tripwire.**
- *Reading B — client-lazy*: fetch options only when the filter drawer opens, via
  `#[Lazy]`/`wire:init`. That is literally a public Livewire lazy expansion.
  **Trips the tripwire.**

**The operator's instruction is to settle this ambiguity in P2's scope before
selecting P2.** Recommended settlement: **pin P2 to Reading A** and state in the
ledger that Livewire-lazy/deferred option loading is out of P2's scope. Rationale:
the drawer is server-rendered inside `x-show` (`resources/views/components/public/public-filter-panel.blade.php:13-19`),
so the options are in the payload on first render regardless; Reading A captures
the query savings without the tripwire. Record that `MAINT-LW-UX1` must still run
before the SL2/SL4 slider arc, which trips it unambiguously.

What P2 would actually fix: `ContentItemSearch::render()` (`:389-401`) rebuilds
all five option lists on **every** render, and `search` is bound with
`wire:model.live.debounce.350ms`, so every debounced keystroke pays for them.
Two are expensive: `providerOptions()` (`:338`) builds the full aggregate query
just to pluck one column, and `transcriberOptions()` runs an **uncapped**
contributor ranking with correlated counts to fill a `<select>`.

**Step 10R-P3** ("persist/render derived transcript segments and word counts")
does **not** trip the tripwire — it is schema, model and server-render only.
It also contains bug B1, which is why P3 delivers more visible value than P2.
Note the ledger states `P2 → P3`, but nothing technical couples them; P3 touches
`ContentItemTranscriptViewer` and `TranscriptSegmentParser`, which P2 does not.

## Small dead code

`app/Console/Commands/ReportMediaIntegrity.php:57-73` reads
`rowless_transition_candidates`, a reporter key retired in `d5be68f`. It is
always `[]` and falsely implies a Files Discovery capability exists.

---

# The plan

Run in this order. Each phase ends with the full gate, a state-doc update and a
local commit.

### R1 — the two real bugs
1. **`word_count`**: compute on save for `Transcription` (a `saving`/`saved`
   hook or an explicit service — match how the model already derives
   `reference_key`/`language_code`), plus a backfill command for existing rows.
   Reuse the counting logic already proven in
   `ContentItemTranscriptViewer::wordCount()` (`:116-126`) rather than inventing
   a second definition. Add a feature test that creates a transcription through
   the application (not a factory value) and asserts both the column and the
   public aggregate are non-zero — the current suite misses this precisely
   because factories set the value.
2. **EXIF**: implement the metadata-only strip on admission per the design note
   above, and make the behavior consistent across upload, picker and external
   fetch. Record the MI-R044 interaction explicitly in the state doc.
3. **Spotify reduced-mode indication**: the fetcher already sets
   `usedReducedMode` and a warning (`SpotifyLinksFetcher.php:155-159`); make it a
   clear, unmissable indication in the UI rather than one line among warnings.

### R2 — quarantine retention
New `media:prune-quarantine` command, dry-run by default with `--apply`, matching
the existing shape of `media:repair-mutations` and `media:relocate-root`. Prune
**only** `status = completed` with `completed_at` older than the window; never
`staged`, `copied`, `committed`, `cleanup_pending` or `failed`. Config key
`media.quarantine.retention_days` defaulting to **90**, `0` = never prune, plus
the `.env.example` line. Re-derive the directory from `operation_key` after
validating it against the ULID pattern; never `deleteDirectory()` on a raw
database value. Keep `quarantine_path` and `quarantine_sha256` after pruning.
Do **not** modify `MediaFilesystemMutationCoordinator.php`.

Tests must include the regression that encodes the danger: an aged
`cleanup_pending` row survives the prune **and** `repair()` on it still returns
`completed_cleanup` afterwards.

Note: the app has no scheduler registered (`bootstrap/app.php` has no
`withSchedule()`, `routes/console.php` has only `inspire`). Adding a
`Schedule::command()` line is fine, but say plainly in the handoff that it only
runs if the operator enables the hosting panel's scheduler; the fallback
precedent is `SettingsBackupManager::prune()`, which prunes inline at write time.

Consider in the same phase: widening the delete `context` to snapshot
`alt`/`title`/`caption`/`description` (4 lines), so the next 90 days of deletes
become fully restorable when Trash is eventually built.

### R3 — FU06
The 12 stale "future renderer" strings and the 4 dead order keys. Cheapest real
win available.

### R4 — record truth
1. Correct the 12 false-pending matrix rows (F017–F021, F024–F030, F045) and
   F033, as a dated amendment consistent with how the matrix was amended on
   2026-07-30. Mark `F022` as the genuine partial.
2. Write the **missing 3B and 3C handoffs**, reconstructed from commits and code
   and labelled as reconstructions — follow the pattern of
   `docs/phase-02/media-operations-ux3-mini4-reason-specific-resolution-handoff.md`.
3. Correct the Step 5B deferred inventory in research 33 and the lg-column
   handoff: mark O2/FU02/FU03 and the three resolved findings as shipped.
4. **Lapse M4 D2=A properly** per D3 above, recording the operator's acceptance
   of the new boundary.
5. **Resolve the wrong-gate record** per D4: state plainly that `3694919` opened
   Storage Truth's gate, and that the Files-and-Physical-Lifecycle package
   remains ungated and still requires its own research, audit and approval.
6. Record `MUX3-F038` as an explicit **deferred-not-retired** item with its
   unmet proof gate, so it stops being re-litigated.
7. Record the quarantine finding as an asset-with-retention item.
8. Fix the stale `podtext.data4.work` line in
   `docs/phase-02/back-log-triage-2026-07-13.md:53` and close the
   first-super-admin item.
9. Delete the dead `rowless_transition_candidates` block in
   `ReportMediaIntegrity.php:57-73`.

### R5 — M4 Group A, now that the operator has decided
Implement the five reason-specific resolutions per the rulings table. Suggested
grouping:
- **Shares the shipped mechanism**: missing-file (restore-by-upload +
  detach-and-delete) and metadata. Both need the failing fact isolated —
  `MediaRecordScope::allowsForBackfill()` (`app/Support/Media/MediaRecordScope.php:242-296`)
  currently folds **eight distinct failure modes** into one boolean, so operators
  are told "something is wrong" with no idea what. Refactor it into a structured
  verdict **without changing existing callers' semantics** (it also feeds
  `hasMetadataIssue`, admission and the picker).
- **Needs the swap lift**: `CuratorMediaPolicy::mutateFileResponse` (`:174-197`)
  currently denies referenced rows, which is exactly the missing-file cohort. Lift
  it for this intended case, mirroring how the sanitize lift was scoped (allow
  attachment-referenced rows; keep the settings-path carve-out).
- **Needs its own authority**: reference-key minting (re-opens the immutability
  invariant at `app/Models/Media.php:88-100` via the existing
  `MediaMutationLease` window — note the previous executor command was deleted,
  but `MediaMutationOperationType::ReferenceKeyBackfill` and
  `StoredMediaValidator::validateForReferenceKeyBackfill()` survive).
- **Super-admin + trust-mark weight**: audience flip and disk correction.

### R6 — P2 and P3
Settle P2's scope in the ledger per the recommendation above, then implement.
Recommended order **P3 first** (smaller blast radius, no tripwire), unless the
operator prefers to honor the ledger's stated `P2 → P3` sequence.

---

# Things not to do

- Do not retire `MUX3-F038`. The operator wants it later; record it as deferred.
- Do not prune quarantine by file age, mtime, or directory sweep.
- Do not delete either `refreshPreview()` hook in FU05.
- Do not flip `preserveRaster` to `false` to fix EXIF.
- Do not rewrite operator-accepted matrix rows in place; amend with dated notes.
- Do not rename Storage Truth back to "Package 5", and do not rename the
  lifecycle package.
- Do not perform any remote or production operation.
