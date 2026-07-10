# Codex Prompt — Step 10R-UX3: Hebrew-Aware Smart Slugs + Key/Slug Contract Alignment

Work in the current local clone of `studioycm/PodText`.

ONE mini-step: UX3. Standing runner rules apply: full quality gate incl.
`git diff --check`, no push unless asked, no `filacheck --fix`, no `model:show`,
fixture-owned tests, en+he translations, RTL-safe UI, research note + implementation
plan doc before code, handoff ends with `## Commit hash` (backfill the previous run's
hash from git log per the standing rule) and `## Local Front Check Report`.
NOTE: local runtime DB is MySQL now; run `php artisan migrate` against it in preflight.
First docs job: insert the `Step 10R-UX3 - Hebrew smart slugs and key contract
alignment` ledger row after the last completed settings-arc row (S1c or HF2, whichever
is latest) and before the WB-gate note.

## Audit facts (verified by Fable — build on these, re-verify only if contradicted)

- All five sluggable models (`Author`, `Category`, `ContentGroup`, `ContentItem`,
  `HomepageSection`) already have `creating` hooks calling a PRIVATE per-model
  `uniqueSlug()` (suffix dedupe; ContentItem's is content_group_id-scoped; empty base
  falls back to a lowercased ULID). The five implementations are near-duplicates.
- `Str::slug()` strips Hebrew → Hebrew titles currently produce ULID junk slugs.
- Every admin form slug field is a plain `required()` TextInput with NO live
  autogeneration, so the server hooks never get to run for form creates.
- Form maxLength(255) matches varchar(255) everywhere; unique rules match the DB
  (items scoped to group, matching the composite unique). `reference_key` appears on
  NO form; models auto-ULID it and revert edits (immutable). `language_code` form
  max 10 = column 10. ContentTag's slug is disabled on the form; Spatie generates it
  internally via `Str::slug` → suspected EMPTY slugs for Hebrew tag names (verify).

## Job 1 — one Hebrew-first slugger (server truth)

- `App\Support\Slugs\HebrewSlugger` (final name per conventions): keeps Hebrew
  letters, Latin letters, and digits; lowercases Latin; whitespace/underscore runs →
  single dash; strips other punctuation (incl. geresh/gershayim ׳ ״ and quotes);
  collapses/trims dashes; mb-safe; caps length (~120 chars); empty result → the
  existing lowercased-ULID fallback. Pure + unit-tested (Hebrew, mixed, niqqud,
  punctuation-only, English, empty).
- Replace the five duplicated private `uniqueSlug()` bodies with ONE shared support
  implementation (slugger + exists-check callback + optional scope), keeping each
  model's `creating` hook and the ContentItem group scope exactly as they behave now.
- `ContentTag`: verify how Spatie derives slugs for Hebrew names; override the proper
  Spatie hook so tag slugs come from the shared slugger. Audit existing tags for
  empty/ULID slugs; if any exist, fix them in the same run via a small idempotent
  artisan command (report counts in the handoff), and prove public tag pages resolve.

## Job 2 — form-side smart slugs (the user request)

- One shared form helper (e.g. `SlugInput::make(source: 'title')`, mirroring the V1b
  `IconSelect` precedent; FilamentExamples-inspired): the SOURCE field gets
  `live(onBlur: true)`; on blur it fills the slug ONLY when the slug is blank
  (manual entries are never clobbered), using the shared slugger + the same
  uniqueness-suffix logic (group-scoped for items via `Get`). The slug field gets a
  "regenerate" hint action to explicitly re-derive from the current source, helper
  text (en+he) explaining autogeneration + override, and keeps its unique rule.
- Slug fields stop being `required()` — the model hook guarantees a value on create
  (keep a sensible max length rule). Apply the helper to: AuthorForm, CategoryForm,
  ContentGroupForm, ContentItemForm (scoped), HomepageSectionForm, and BOTH option
  modals in `RelationshipOptionForms`. ContentTagForm stays disabled-display.
- Hebrew end-to-end proof: a public page (e.g. podcast route) must resolve a
  Hebrew slug created through the form (percent-encoded URL).

## Job 3 — key/slug contract alignment (the audit's open items)

- Importers: enforce `max:26` (+ ULID-shape rule if cheap) on every reference_key
  ImportColumn (Author/ContentGroup/ContentItem/Transcription importers) so oversized
  keys fail as row errors instead of MySQL 1406s; verify slug ImportColumns carry
  max:255 and the item importer respects the group-scoped unique.
- Verify ContentItemForm URL fields (`media_url`, `embed_url`) carry maxLength(2048)
  and `embed_provider` maxLength(50) per columns; add what's missing.
- Do NOT touch existing stored slugs (public URLs are stable); ULID-fallback slugs on
  old rows are acceptable history. Do NOT widen any column.
- Docs lesson (ai-development-lessons + ledger guardrail note): seeders and importers
  must produce contract-valid keys — `char(26)` ULIDs for reference keys — and the
  Step 11 promoted demo seeder must generate ULIDs, never descriptive strings
  (SQLite masks length violations; MySQL enforces them).

## Tests

Slugger unit set (incl. Hebrew output, not ULID, for a Hebrew title); form create
with blank slug autogenerates on blur and saves; manual slug override survives source
edits; uniqueness suffixing (global + per-group scoped); regenerate action; tag
Hebrew slug + public tag page resolution; importer rejects a 30-char reference_key as
a row failure; URL/provider maxLengths; existing unique-rule regressions; bounded
public harness; full gate. No literal counts.

## Out of scope

Renaming existing slugs; column type changes; public route changes; Importer
Workbench; S1c inline locks (separate run).

## Docs and handoff

Ledger row, current-state, research + plan docs, handoff with numbered Local Front
Check (create a podcast with a Hebrew title → slug fills in Hebrew on blur → save →
public page opens; same for episode within a podcast incl. duplicate-title suffix;
author + category + homepage section + relationship option modals; tag with Hebrew
name gets a real slug; import a CSV row with an oversized reference key → row error
not a crash; Hebrew RTL + light/dark).

Commit: `feat: add hebrew smart slugs and key contract alignment`

End with exactly:

```text
Public Front v2 mini-step UX3 is complete. Waiting for Yoni review before continuing.
```
