# Codex Prompt — SP3C: Template Library + One-Template Editor

Prompt version: v1 — 2026-07-14. Standing rule: stop and ask on a version
mismatch with the kickoff. Stage 3 of SP3A→SP3B→SP3C→SP3D. This v1 is
issued for a REVIEW ROUND first — expect to receive it back as a review
task before any execution kickoff.

Work in the current local clone of `studioycm/PodText`.

ONE run: replace SP3B's temporary Card Templates page with a lightweight
TEMPLATE LIBRARY and a ONE-TEMPLATE EDITOR. Exactly one template is ever
mounted; unselected templates and unselected parts own ZERO rendered
elements. Adoption of Builder block previews is gated by a worst-case
canary — the fallback is a summary-plus-selected-part editor, never a
collapsed full Builder. Sibling templates survive every save
byte-identically.

SCOPE GUARD — explicitly prohibited in SP3C: database migrations;
splitting the Spatie settings class/storage group; changing stored
properties or import/export formats; per-template timestamps or any new
storage metadata; live/public preview (SP4 owns the preview decision —
this run only leaves the editor page seam for it); islands on ordinary
settings pages; monolith-code deletion (SP3D); locks/`lockForUpdate()`
(the SP3B narrow guarantee stands); any change to lifecycle unit
definitions or serialized bytes (frozen SHA `61e551a6…` keeps passing).

Standing runner rules: read `docs/phase-02/ai-development-lessons.md` IN
FULL during preflight; research note + implementation plan docs BEFORE
code; no push unless asked; no `filacheck --fix`; fixture-owned tests;
en+he translations for every new label; NO Composer/npm changes.
Commit-message house style is binding (`feat:`/`fix:`/`perf:` +
`docs: backfill … hash` for the ending) — recent external-agent runs
drifted.

CANONICAL RUN ENDING (standing): implementation commit (`## Commit hash`
pending) → immediately a docs-only commit stamping the hash into handoff
+ ledger (`docs: backfill settings sp3c hash`).

Handoff: `docs/phase-02/settings-sp3c-handoff.md`; gate outcomes written
in before committing; Local Front Check Report = numbered MANUAL OPERATOR
STEPS with OBJECTIVE expectations. Kickoff corrections: enumerated or the
word "none".

FINAL GATE ORDER (standing): requirements sweep → `vendor/bin/pint --test`
→ `vendor/bin/filacheck` → `npm run build` → FULL `php artisan test` LAST
(once = once GREEN on final state; re-enter from Pint after any change;
record every run).

## Preflight

```bash
git status --short --branch
git log --oneline -8
```

Expect SP3B `dedca88` + backfill `da64440` and the four ad-hoc commits
(OTP policy, curator picker fix, collapsible nav, logo/favicon) at or
near HEAD. Stop on unexpected app-code dirt. Run the targeted GREEN
BASELINE (settings, SP3A/SP3B, backup/import, lock, ROLES1, LENS1
suites) before any code; stop if red.

## Job 0 — carried items

1. Reconcile the ledger and `current-project-state.md` with the four
   ad-hoc commits (OTP policy `0394ab5`, curator picker `23a6ce9`,
   collapsible nav `d128cfd`, logo/favicon `9d8296f`) — compact rows so
   the rolling record stays complete.
2. If the kickoff carries Yoni's authenticated browser samples for the
   SP3B pages, record them as the SP3B measurement addendum in that
   handoff (docs-only touch) — the SP3B run could not collect them (the
   in-app browser runtime fails with `Cannot redefine property:
   process`; assume it still does and design this run's evidence
   accordingly: see Job 5).

## Job 1 — research + the block-preview CANARY (before any adoption)

Research/plan docs BEFORE code
(`docs/research/settings-performance/05-sp3c-research.md`,
`05-sp3c-implementation-plan.md`). Verify against installed vendor
source (Filament 5.6.7); FilamentExamples MCP for library/list-page and
preview patterns.

Build the canary against the DEEPEST real template shape (nested
`part_group` children, repeated blocks): every block type gets a safe,
escaped summary view; verify edit, cancel, validation failure, delete,
clone, reorder; nested children; a second instance of the same block;
unsaved edits surviving the preview↔edit transition; NO gated field
exposed or savable below super-admin; and a material rendered-element
reduction. The plan doc records the canary verdict and the chosen path:
`blockPreviews()` adoption, or the FALLBACK — a summary list of parts
where ONLY the selected part mounts a form (nested Livewire component or
modal with its own authorization and save boundary). Collapse alone is
never compliant.

## Job 2 — the template library page

Replaces the temporary Card Templates page (its slug/nav position is
inherited or redirected — keep the SP3B navigation map consistent and
update it in the plan doc):

- Lightweight custom page over the normalized `card_templates` array —
  no fake Eloquent model.
- Columns: composite identity (`family:key`), translated label, enabled
  state, template type, where-used count. NO updated-at column (storage
  has no per-template timestamp). Where-used comes from ONE centralized
  reference scanner computed once per request outside column closures
  (cache or defer if measured expensive — measure first).
- Actions: create, edit, clone (clone generates a unique key within the
  family, suffixed name, and lands in the editor unsaved or saved —
  pick one, justify), enable/disable if that exists in storage today
  (do not invent new stored fields).
- he+en labels; budget: initial rendered elements < 2,000.

## Job 3 — the one-template editor + focused writer

- Editor page loads exactly ONE template by `family:key`, or a blank
  CREATE state. The ORIGINAL identity stays immutable while family/key
  fields are edited; a successful rename redirects to the new composite
  identity.
- FOCUSED WRITER on save (inside the SP3B fresh owned-path contract,
  owning only `card_templates`): validate new `family:key` uniqueness
  against FRESHLY loaded siblings; locate the entry by ORIGINAL
  identity; replace only that entry; every sibling byte-identical
  (tested); apply `MultiTranscriptionSurfaces` overlay so gated template
  parts can't be exposed or saved below super-admin; a STALE same-template
  edit (the entry changed since mount) reports a conflict and refuses —
  never overwrites.
- Collision, rename, clone-collision, missing-identity, and direct-URL
  cases all covered; deep links and browser-back work.
- Inside the open template: the canary-chosen mechanism — unselected
  parts own zero rendered elements; editing any block/row fully works.

## Job 4 — gates, locks, profiler

ROLES1 macros/overlay carried onto both new pages (forged-state tests
retargeted); the SP3A lock surface renders whatever section-level lock
applies to templates (no per-part locks — the lock diet stands);
profiler subject keys for library and editor; measurement mode
(`?sp3a_measure=1` semantics) works on both.

## Job 5 — evidence (headless-first, operator for browser facts)

The runner's browser automation is assumed broken (see Job 0.2), so:

- HEADLESS budget proxies, asserted in tests: rendered-HTML element
  counts of the library page, the editor before selection, and the
  editor with one part selected (crawler count over the response —
  budgets: library < 2,000; editor pre-selection < 4,500; unselected
  parts contribute 0 form fields — assert absence of their inputs by
  name). Decomposed query headers + response-byte middleware numbers
  recorded for both pages under the fixture.
- OPERATOR browser steps (front check) collect TTFB/DOM/listener/heap
  samples with the frozen SP3A protocol and the adapted script —
  numbered steps, exact expected shapes, no fabricated medians.
- Handoff carries the headless table (with the SP3A baseline row for
  contrast: Advanced was 29,404 elements) and marks browser samples as
  operator-collected-or-pending, never invented.

## Tests

Canary suite per Job 1; sibling byte-identity; stale-conflict refusal;
rename/collision/clone matrix; gated-part protection under forged state;
ownership registry still complete/unique (templates owner moves from the
temporary page to the editor); rendered-element budget proxies; lifecycle
SHA regression untouched; SP3B page suites stay green; full gate per
header order.

## Docs and handoff

Ledger row `SP3C - Template library and one-template editor`;
`current-project-state.md`; research/plan docs BEFORE code (canary
verdict + navigation-map update are deliverables); handoff per header
rules. Local Front Check Report (operator steps, objective): open the
Settings group → Card Templates entry opens the LIBRARY (list of
templates, no builders); open one template → only that template's editor
mounts; expand/select one part → only it gains form fields; edit a block
through the chosen flow, save, verify the public card changed and every
other template is untouched; rename a template → redirected to the new
identity and old links 404/redirect per the plan; open the same template
in two tabs, save one then the other → the second reports a conflict;
run the browser protocol on library + editor and record the samples; as
admin verify gated parts are absent and unsavable.

Commit: `feat: add template library and one-template editor`
Then the canonical docs-only backfill commit (see header).

End with exactly:

```text
Settings SP3C is complete. Waiting for Yoni review before continuing.
```
