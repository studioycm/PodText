# Codex Prompt — IE-1: Relation Import Modes + Tag Export Scope

Work in the current local clone of `studioycm/PodText`.

ONE implementation run (the IE-1 step from `docs/phase-02/images-media-track-plan.md`
and the IMG-R research Job 6 — read both first; Yoni's decisions below override
where they differ). Standing runner rules: research note + implementation plan
docs BEFORE code, no push unless asked, no `filacheck --fix`, fixture-owned
tests, en+he translations, NO Composer changes. The handoff is a COMMITTED
MARKDOWN FILE (`docs/phase-02/import-relations-ie1-handoff.md`) whose gate
outcomes are written into the file AFTER the gate passes and BEFORE the commit,
with `## Commit hash` and a numbered MANUAL `## Local Front Check Report`.
Backfill TOOLS1's commit hash `a6d6408` per the standing rule.

FINAL GATE ORDER (standing): requirements sweep → `vendor/bin/pint --test` →
`vendor/bin/filacheck` → `npm run build` → FULL `php artisan test` LAST
(once = once GREEN on final state; re-enter from Pint after any change; record
every run; ~8 quiet minutes each; never interrupt/parallelize).

## Preflight

```bash
git status --short --branch
git log --oneline -4
```

Clean tree; TOOLS1 `a6d6408` expected at or near HEAD.

## Yoni decisions (binding)

- Relation import modes are `replace` and `add_only` ONLY — "merge" is dropped
  (for flat relation sets it is identical to add_only).
- DEFAULT mode: `replace` (today's behavior, now named) — chosen per import in
  the import modal options.
- Blank relation cells mean LEAVE UNCHANGED in BOTH modes — this turns the
  current transcriber accident (sync skipped only because the resolved set is
  empty) into a written, tested rule for categories, tags, and transcribers.
  There is NO clear-via-import in v1.
- Export tag scope: `enabled_only` is the NEW DEFAULT (this CHANGES current
  default export output — document it in the handoff and docs); an `all_tags`
  toggle remains for full-fidelity admin exports.
- A DISABLED tag in an imported row: warn + skip THAT TAG, the row still
  imports. The warning must be user-visible — research the honest mechanism in
  native Filament imports (e.g., a translated skipped-tags summary appended to
  the completed notification body, since per-row warnings are not native);
  cite the choice. Silent dropping is over.

## Job 0 — carried corrections

1. **Curator Glide token hardening** (production incident 2026-07-12, orphaned
   from the unrun TOOLS1 v2): `config/curator.php` reads
   `env('CURATOR_GLIDE_TOKEN')` with NO fallback, which fatals the entire
   media grid when unset (`GlideManager::getToken(): Return value must be of
   type string, null returned`). Change to
   `env('CURATOR_GLIDE_TOKEN', env('APP_KEY'))`, add `CURATOR_GLIDE_TOKEN=`
   to `.env.example` with a short comment, add a deploy-notes line, and add a
   test asserting `config('curator.glide_token')` resolves non-empty. Lessons
   doc entry: a run introducing a new env-dependent config key must in the
   SAME run add it to `.env.example`, the deploy notes, and flag it for the
   production env.
2. Note in the MP2 handoff gap line that the gap is now closed-as-documented
   (no numbers will be recovered).

## Job 1 — relation import modes

- One shared import option (Select in `ConfiguresContentImports::
  getOptionsFormComponents()`): `relation_mode` = replace | add_only, default
  replace, translated label + helper text explaining both modes and the
  blank-cell rule.
- Apply to every multi-value relation the importers sync:
  `ContentGroupImporter` categories; `ContentItemImporter` categories and
  content tags; `TranscriptionImporter` transcribers (keys and names paths),
  preserving `author_id` compatibility sync.
- `replace`: provided cell = complete truth (current `sync()`); `add_only`:
  attach missing only, never detach. BLANK cell: unchanged in both modes —
  implement explicitly, not as a side effect of empty resolution.

## Job 2 — export tag scope

- Exporter option/toggle on the content-item export: `enabled_only` (default)
  vs `all_tags`; the `content_tag_slugs` column respects it. Update the
  eager-load in `modifyQuery` if the enabled-only path can reuse
  `enabledContentTags` without N+1. Document the default-output change.

## Job 3 — disabled-tag visibility

Implement the researched warn-and-skip mechanism; the skipped tag names reach
the admin (translated), the row imports, enabled tags on the same row still
attach.

## Tests (from the track plan + research Job 6, updated to the decisions)

Replace and add_only for group categories, item categories, item tags, and
transcribers (attach/detach matrices per mode); blank cells leave every
relation unchanged in BOTH modes (the transcriber rule test plus categories
and tags); default mode is replace when the option is untouched; disabled tag
in a row → row succeeds, tag skipped, warning surfaced, enabled tags attached;
export with default scope excludes disabled tags, with all_tags includes them;
export column eager-load stays N+1-free; round-trip: default export then
import no longer silently drops anything; Glide token config test (Job 0);
existing import/export regressions green. Full gate per the header order.

## Out of scope

Clear-via-import; image_path CSV columns; zip packages; WB2+; new relation
types; Composer changes.

## Docs and handoff

Ledger row `IE-1 - Relation import modes and tag export scope`;
`current-project-state.md`; update the D-IMG/IE register in
`docs/phase-02/images-media-track-plan.md` (IE-1 delivered, decisions as
implemented); research + plan docs BEFORE code
(`docs/research/images-media/03-ie1-*.md`); handoff per header rules with
manual checks (import a CSV with a categories cell in replace mode → set
replaced; same cell in add_only → nothing detached; blank transcriber cell on
update → transcribers untouched; a row with one disabled + one enabled tag →
row imports, enabled attaches, warning names the skipped tag; export an item
with a disabled tag → absent by default, present with all_tags; Hebrew RTL
in the import modal).

Commit: `feat: add relation import modes and tag export scope`

End with exactly:

```text
Import relations IE-1 is complete. Waiting for Yoni review before continuing.
```
