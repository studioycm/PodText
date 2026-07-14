# Codex Prompt — LENS1: Single-Transcription Ontology + Vocabulary Sweep

Prompt version: v1 — 2026-07-13. (Standing rule: if the committed prompt
differs from the version named in the kickoff, stop and ask.)

Work in the current local clone of `studioycm/PodText`.

ONE run: complete the white-label illusion that ROLES1's gates started —
in single mode the ENTIRE system (public site, admin, data-derived counts,
labels in both languages) presents a transcription as a property of its
episode: no per-episode plurality anywhere, counts that mathematically
cannot exceed one per episode, and episode-language everywhere. Multi mode
keeps today's behavior untouched — other deployments rely on it.

Standing runner rules: read `docs/phase-02/ai-development-lessons.md` IN
FULL during preflight; research note + implementation plan docs BEFORE
code; no push unless asked; no `filacheck --fix`; fixture-owned tests;
en+he translations for every new label; NO Composer/npm changes.

CANONICAL RUN ENDING (standing): implementation commit (`## Commit hash`
pending) → immediately a docs-only commit stamping the hash into handoff +
ledger (`docs: backfill single lens lens1 hash`).

Handoff: `docs/phase-02/single-lens-lens1-handoff.md`; gate outcomes
written in before committing; Local Front Check Report = numbered MANUAL
OPERATOR STEPS (imperative).

FINAL GATE ORDER (standing): requirements sweep → `vendor/bin/pint --test`
→ `vendor/bin/filacheck` → `npm run build` → FULL `php artisan test` LAST
(once = once GREEN on final state; re-enter from Pint after any change;
record every run).

## Preflight

```bash
git status --short --branch
git log --oneline -5
```

Expect ROLES1 `9cd7349` + backfill `908fa08` at or near HEAD. Stop on
unexpected app-code dirt. ROLES1 passed audit clean — no carried
corrections.

## Decisions this run implements (Yoni + Fable, final)

- Counting is PER-EPISODE in single mode: a count can never exceed one per
  episode because it counts DISTINCT EPISODES (through each episode's
  effective transcription), not transcription rows. Stray extra rows in
  the data must be unable to change any displayed number. Multi mode keeps
  record-based counting unchanged.
- The per-episode transcription-count card element renders NOTHING in
  single mode, even if a stored template still contains it (ROLES1 already
  blocks new template usage; this run adds runtime suppression).
- Approved single-mode labels (he / en): cards short "פרקים" /
  "episodes"; fuller surfaces "פרקים מתומללים" / "transcribed episodes";
  contributor heading "פרקים שתומללו"; podcast latest-date "פרק אחרון
  :date" / "latest episode :date". Multi strings are NOT edited — single
  mode selects VARIANT keys at render time.
- The first transcription of an episode auto-features itself on EVERY
  creation path (mode-independent — in multi it is the only candidate); a
  second transcription NEVER auto-features; in single mode creating a
  second is blocked server-side.
- The standalone Transcriptions resource stays for all admin+ roles, in
  episode-language: in single mode its default listing shows exactly ONE
  row per episode (the effective transcription); replaced/older rows are
  reachable only through a SUPER-ADMIN-ONLY history filter
  (mode-independent — super-admin may always inspect history; admins
  never see it).

## Job 0 — small carried items

1. Verify ROLES1's canonical ending stamped its hash (report only).
2. Record the D-LENS1 decision set above in
   `docs/phase-02/transcriptions-model-spec.md` beside ROLES1's
   white-labeling contract section, as the single-mode ontology contract.

## Job 1 — first-transcription auto-feature + single-mode creation block

- Auto-feature rule via a `Transcription` observer (or equivalent single
  choke point covering every path — factory/observer interplay verified):
  on create, if the owning item has no other transcriptions, set it as the
  item's featured transcription. Idempotent with the workspace's existing
  create-and-pin (which stays). A second created transcription never
  changes featured.
- Single-mode block: with `transcription_mode = single`, creating a
  transcription for an item that already has one is refused SERVER-SIDE
  with a clear localized message ("לפרק כבר יש תמלול" / "This episode
  already has its transcript") — enforced in the shared creation path so
  the standalone resource, the relation manager, and any import path all
  hit it; the workspace replace-with-fresh action remains the sanctioned
  way and is exempt (it replaces, then adopts).
- Tests: auto-feature on resource create, relation-manager create, and
  direct model create; second create never re-features (multi); second
  create blocked in single with the message; replace flow still works in
  single.

## Job 2 — per-episode counting (mode-aware)

- Contributor counts (cards, pages, anywhere `public_transcriptions_count`
  feeds a person): in single mode compute DISTINCT EPISODES whose
  EFFECTIVE transcription belongs to the person; in multi mode keep the
  current record count. Swap at the aggregate/subselect level (follow the
  existing correlated-subquery patterns), not in Blade.
- Podcast/group counts: in single mode the transcription-count value is
  the count of DISTINCT transcribed (public) episodes.
- Episode card count element: renders empty/nothing in single mode
  regardless of stored template content (presenter-level suppression on
  top of ROLES1's template guard).
- Tests: seed one episode with TWO transcription rows (bypassing the
  block via direct model state) → in single mode every surfaced count
  still says 1 / distinct-episodes; in multi mode counts show records;
  the episode count element emits nothing in single mode from a template
  that contains it.

## Job 3 — vocabulary sweep (he+en, variant keys)

- Build ONE small label-resolution helper that picks the single-mode
  variant key when it exists (e.g. `public.labels.single.*` falling back
  to the base key), so multi deployments keep today's strings untouched.
- Apply the approved labels (see Decisions) to: contributor cards/pages,
  podcast/group cards ("תמלולים" count label → "פרקים";
  "תמלול אחרון :date" → "פרק אחרון :date"), episode surfaces, and any
  admin strings the research inventory flags as per-episode-plural.
- THE INVENTORY IS A DELIVERABLE: the implementation plan doc contains a
  full before/after table of EVERY transcription-related user-facing
  string (public + admin, he + en) with its single-mode treatment: keep /
  variant / suppressed. Yoni vetoes via the handoff — flag any string you
  were unsure about explicitly.
- Tests: label helper picks variants in single mode and base keys in
  multi; representative rendered surfaces (a contributor card, a group
  card) show the episode-language strings in single mode.

## Job 4 — Transcriptions resource in episode-language

In single mode: the episode column ("פרק", episode title) leads the
table; featured column/filter/badges hidden; the DEFAULT query returns
one row per episode — its effective transcription (reuse the existing
effective-resolution SQL patterns; no N+1); a "היסטוריית תמלולים" /
"transcript history" filter — visible to SUPER-ADMIN ONLY, both modes —
reveals replaced/non-effective rows; create/edit helper texts speak
episode-language ("התמלול של הפרק"). In multi mode the resource behaves
exactly as today. Tests: default scoping hides a replaced row in single
mode; the history filter reveals it for super-admin and does not exist
for admin; multi mode unchanged.

## Job 5 — single-mode leak audit (verification job)

With mode=single, sweep the remaining surfaces for per-episode-plural
leaks the earlier jobs did not cover: admin item/group tables'
transcription-related columns, workspace visibility checklist wording,
dashboard/nav badges, exports/CSV headers, the public viewer (its multi
switcher must stay unreachable — assert, don't assume). Fix small finds
in-run; classify anything larger in the handoff instead of silently
skipping. The research doc lists every surface checked with its verdict.

## Tests

Per job; plus existing public-front, workspace, roles-gates, and
import/export suites stay green. Full gate per header order.

## Docs and handoff

Ledger row `LENS1 - Single-mode ontology and vocabulary sweep`;
`current-project-state.md`; research/plan docs BEFORE code
(`docs/research/single-lens/00-lens1-research.md`,
`00-lens1-implementation-plan.md` — the plan carries the full label
before/after table and the leak-audit surface list); handoff per header
rules. Local Front Check Report (operator steps): in single mode as
admin — a contributor card says "פרקים", a podcast card says "פרק אחרון"
and episode-based counts, no episode card shows a transcription count,
the Transcriptions resource lists one row per episode with no featured
column and no history filter; as super-admin — the history filter exists
and reveals a replaced row after using workspace replace; try creating a
second transcription for an episode via the resource → refused with the
message; flip to multi as super-admin → featured column and record
counts return; flip back to single.

Commit: `feat: apply single transcription ontology across public and admin`
Then the canonical docs-only backfill commit (see header).

End with exactly:

```text
Single-lens LENS1 is complete. Waiting for Yoni review before continuing.
```
