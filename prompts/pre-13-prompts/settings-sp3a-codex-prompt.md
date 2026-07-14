# Codex Prompt — SP3A: Settings Measurement Protocol, Lifecycle Memoization, Lock Surface, Import Overlay, Select Sweep

Prompt version: v1 — 2026-07-14. Standing rule: stop and ask on a version
mismatch with the kickoff. This prompt supersedes
`settings-lazy-sp3-codex-prompt.md` (v3, never executed) per the SP3
review report — SP3 is now staged SP3A→SP3B→SP3C→SP3D.

Work in the current local clone of `studioycm/PodText`.

ONE run: the foundation stage. Freeze a repeatable measurement protocol
and record the baseline; memoize lifecycle derivation in its owning class
with payload-aware keys; introduce the lock-surface registry (lifecycle
units UNTOUCHED) with the operator-approved important-fields list; close
the import-path authorization gap; sweep every admin Select. NO page
split, NO template editor, NO tabs work — those are SP3B/SP3C.

Standing runner rules: read `docs/phase-02/ai-development-lessons.md` IN
FULL during preflight; research note + implementation plan docs BEFORE
code; no push unless asked; no `filacheck --fix`; fixture-owned tests;
en+he translations for any new label; NO Composer/npm changes.

CANONICAL RUN ENDING (standing): implementation commit (`## Commit hash`
pending) → immediately a docs-only commit stamping the hash into handoff
+ ledger (`docs: backfill settings sp3a hash`).

Handoff: `docs/phase-02/settings-sp3a-handoff.md`; gate outcomes written
in before committing; Local Front Check Report = numbered MANUAL OPERATOR
STEPS (imperative). Kickoff corrections rule (tightened per the review):
the kickoff carries either an ENUMERATED correction list or the word
"none" — nothing open-ended.

FINAL GATE ORDER (standing): requirements sweep → `vendor/bin/pint --test`
→ `vendor/bin/filacheck` → `npm run build` → FULL `php artisan test` LAST
(once = once GREEN on final state; re-enter from Pint after any change;
record every run).

## Preflight

```bash
git status --short --branch
git log --oneline -6
```

Expect LENS1 at or near HEAD. Stop on unexpected app-code dirt, EXCEPT:
the SP3 review report — ensure
`docs/research/settings-performance/02-sp3-prompt-review-and-alternatives-report.md`
is committed (include it if it sits uncommitted; note if absent). Job 0
also archives the superseded v3 prompt.

## Approved decisions this run implements (final)

- Operator approvals: staged SP3A–D; the important-fields lock list
  below; the review's budget table (enforced in SP3D, baselined now).
- IMPORTANT-FIELDS LOCK LIST (approved): `maintenance.enabled`;
  `maintenance.raw_html_override`;
  `public_forms.require_email_verification`;
  `transcription_policy.public_mode`;
  `transcription_policy.count_mode`;
  `transcription_policy.show_multiple_transcriptions_on_item_page`;
  plus `AdminUxSettings.transcription_mode` IF that settings class
  participates in the lifecycle import/lock mechanism (verify; report
  either way). Everything else — every nested template part, template
  row, and repeater item — loses individual lock decoration.
- Lifecycle units are the complete semantic registry for import/export,
  diff/merge, labels, and stale handling — they are NOT reduced. A
  separate lock-SURFACE registry maps the visible lock choices onto
  existing units.
- No general Filament `->deferred()` exists in installed 5.6.7 — never
  reference it. Collapse is never proof of lazy loading.

## Job 0 — housekeeping

1. Commit the review report if uncommitted (see Preflight). Move the
   superseded `prompts/pre-13-prompts/settings-lazy-sp3-codex-prompt.md`
   to `prompts/archive/` (or the repo's archive convention) with a
   one-line SUPERSEDED note pointing at this staged series.
2. Execute kickoff corrections (enumerated or "none").

## Job 1 — the measurement protocol + baseline (the yardstick for B–D)

Build the repeatable harness per the review's acceptance contract:

- A COMMITTED worst-case measurement fixture: seeds nine templates at the
  observed heavy shape into a THROWAWAY measurement state (never touching
  real local settings — snapshot/restore around the run, or a dedicated
  sqlite state), plus the ~37 KB payload shape.
- Fixed protocol, documented as numbered operator steps AND scripted
  where practical: fixed viewport, five warm runs + one cold, profiler
  off and on, medians and p95.
- Metrics captured per run: PHP phase timings (existing profiler);
  uncompressed response body length via a small response middleware
  (dev-gated, off by default — the PHP profiler does NOT measure browser
  facts); encoded transfer bytes, TTFB, DOMContentLoaded, load event,
  DOM element count, listener estimate, heap, and per-panel element
  counts via documented browser console/CDP snippets; QUERY COUNTS
  DECOMPOSED: total queries, settings-repository reads, duplicate
  lifecycle loads — reported separately, never as one ambiguous number.
- Record the BASELINE table in the handoff (expect the diagnosis order:
  ~40,765 elements, ~2.5–2.8 s TTFB, 192 total / 182 duplicate loads).
  Every later stage measures against this exact protocol.

## Job 2 — lifecycle memoization (owning class, payload-aware)

Memoize `SettingsLifecycleSchema` derivation (units, unit-for-path,
group payload) INSIDE the class, request-scoped, with cache keys that
include the lifecycle group AND payload identity — the same request can
inspect current, imported, and merged payloads, and an unkeyed memo
would serve stale data across them. Tests: memoized output identical to
fresh derivation; different payloads in one request do not
cross-contaminate; duplicate lifecycle loads for the same payload drop
to zero on the measured mount; no cross-request leakage.

## Job 3 — the lock-surface registry (units untouched)

- New registry (e.g. `SettingsImportLockSurfaceRegistry`) exposing ONLY:
  section-level lock choices and the approved important-field locks,
  each mapped to one or more EXISTING lifecycle units.
- All lock UI (inline hints, lock actions, `ManageSettingsImportLocks`)
  consumes the surface registry: per-item/per-part decoration inside
  repeaters and builders disappears; sections + approved fields keep
  their lock affordances with helper text (he+en).
- Stored locks pointing at now-invisible surfaces normalize gracefully
  through the surface mapping (the underlying units still exist, so
  imports, diffs, and old backups remain valid) — reported, never fatal.
- Tests: section lock still enforces on import for all covered units; an
  approved important-field lock enforces; a retired visible lock
  normalizes with a report; lifecycle unit outputs are byte-identical
  before/after this job (regression proof that units were untouched).

## Job 4 — import/restore authorization overlay (security fix)

Verified gap: `MultiTranscriptionSurfaces::overlayUnauthorizedSettings()`
runs only on the two settings pages. Import/restore paths do not apply
it, so an admin could import a payload that alters gated values (LENS1's
public clamp limits the damage publicly, but stored values and admin
surfaces are exposed).

- Enumerate EVERY path that writes settings from external payloads:
  settings import page, backup restore, lifecycle apply/merge, the
  normalize command's `--apply`. For each: apply the acting-user-aware
  overlay so gated paths remain byte-identical for non-super-admins, OR
  super-admin-gate the whole surface — decide per path, justify in the
  plan doc.
- Verify whether `AdminUxSettings` (the mode switch) is importable at
  all; if yes, it gets the same protection.
- Tests: an ADMIN importing/restoring a payload with modified gated
  values → gated values unchanged, rest applied; a SUPER-ADMIN in multi
  mode → full apply works; forged-payload variants covered.

## Job 5 — the Select sweep (with options-source analysis)

Enumerate EVERY `Select` (and multi-select/tags inputs) under
`app/Filament`. The plan doc gets a classification table with columns:
location, OPTIONS SOURCE (static array/enum | relationship | computed
closure or service | settings-derived), bounded vs growing, current
searchable/preload state, action taken.

- Bounded sets (≤ ~20 options): add `->preload()`; the tiny ones (≤ 10)
  also DROP `->searchable()` — a plain select beats a needless search
  box.
- Growing sets: keep `->searchable()` WITHOUT preload, add
  `->optionsLimit()`, confirm the search query is indexed/fast.
- COMPUTED-CLOSURE sources get extra scrutiny: a closure that calls a
  service/validator per render is the SP2 `cardTemplateOptions` disease —
  memoize in the owning class and note it in the table.
- Patch the cross-cutting guideline (`.ai` guidelines + the evergreen
  AGENTS.md rules section) with the durable rule so future selects are
  born correct.
- Tests: one preloaded select renders its options on mount without an
  extra request; one async select still searches server-side; one
  closure-source select's underlying service is invoked once per
  request.

## Tests

Per job; plus regression: settings import/export, lifecycle, backup,
ROLES1 forged-state, and LENS1 suites stay green. Full gate per header
order.

## Docs and handoff

Ledger row `SP3A - Settings measurement, lifecycle memoization, lock
surface`; `current-project-state.md`; research/plan docs BEFORE code
(`docs/research/settings-performance/03-sp3a-research.md`,
`03-sp3a-implementation-plan.md` — the select classification table and
per-path import decisions are deliverables); handoff per header rules
with the BASELINE measurement table. Local Front Check Report (operator
steps): run the measurement protocol once end-to-end and confirm the
baseline table matches what you see; open the locks page → only sections
+ the approved fields are lockable; open a template part → no lock
decoration; as admin, import a settings file with a changed
transcription-policy value → the value stays unchanged; open three named
selects (one preloaded, one tiny-plain, one async) → the first two are
instant.

Commit: `perf: add settings measurement protocol, lock surface, and import overlay`
Then the canonical docs-only backfill commit (see header).

End with exactly:

```text
Settings SP3A is complete. Waiting for Yoni review before continuing.
```
