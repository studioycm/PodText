# Step 5B Card Template Ordered Flow Foundation Implementation Plan

Date: 2026-07-19

Audit: `LS-20260719-STEP5B-CARD-RENDER-O2-ORDERED-FLOW-01`

Option: `STEP5B-CARD-RENDER-OVERHAUL-O2-ORDERED-FLOW-FOUNDATION`

## Objective

Make each card's final presenter-produced part sequence authoritative for item
and group flow and geometry while preserving O1, navigation, preview safety,
public visibility, and legacy order compatibility.

## Commands and constraints

- Work directly in `/Users/studioycm/Herd/PodText` on `main`.
- Do not create a branch or worktree and do not push.
- Do not change dependencies, migrations, production, or the local development
  database.
- Use `apply_patch` for edits.
- Run focused tests serially.
- Never run `vendor/bin/filacheck --fix`.
- Add the Tailwind renderer sources only after server-side no-media geometry is
  fixed and its focused tests are green.

## Models, schema, authorization, and Filament form state

No changes. This option adds no model, migration, relationship, policy,
permission, setting, public property, field, Builder action, or persisted state.
The existing focused editor, O1 shell, and navigation remain unchanged.

## Application changes

### 1. Separate base presentation from actual-part finalization

Keep static density, typography, image, and card tokens in
`PublicFrontCardTemplateRenderer`. Add one explicit per-card finalization method
that receives actual presenter parts and returns:

- effective `layout`;
- `part_flow`;
- content-aware article and image classes;
- exact actual `controlled_parts` diagnostics; and
- ordered body/media `part_runs`.

The method must not query or inspect models. It must be deterministic and linear
in the number of parts.

### 2. Finalize each item and group independently

In `PublicContentItemCardPresenter` and `PublicContentGroupCardPresenter`:

1. produce actual `parts` through existing record/display gates;
2. ask the renderer to finalize presentation from those parts;
3. derive `media_parts`, `body_parts`, and `part_runs` from the same actual
   sequence; and
4. return only that single reconciled contract.

Do not change queries, image resolution, public scopes, sample selection,
validation, or settings persistence. `presentMany()` must share only static
tokens and finalize each result independently.

### 3. Render explicit ordered runs

Update the item and group card views to branch on `part_flow`:

- `ordered-stack`: iterate `part_runs`; render media directly and each body run
  inside exactly one density-padded body wrapper;
- `media-leading`: retain the existing media/body split;
- `body-only`: render the body run full width once;
- `media-only`: render the media part full bleed once; and
- `empty`: render no invented part.

Keep the article as the single component root. Preserve preview-mode link
suppression, public per-part links, attributes, and existing image/part
components.

### 4. Correct the group row contract

Move effective row/card geometry to the group article/container and remove the
unused `link` presentation key. Do not add a whole-card anchor.

### 5. Reconcile diagnostics

Keep configured template attributes unchanged. Change result layout, flow, and
renderer-parts diagnostics to actual output. Preserve repeated image types.

## Focused tests before Tailwind source changes

Extend existing Pest files rather than creating a parallel fixture framework.
Tests own all database state and do not use live HTTP or mail.

Cover both item and group:

- leading image plus body in configured rows;
- no image block and globally hidden image;
- fallback/default image still counting as media;
- image-only after body parts filter out;
- body-image-body ordered runs;
- two leading images plus body;
- image-body-image-body exact-once order;
- nested image omission;
- per-record `presentMany()` finalization;
- group row article and absence of an outer whole-card anchor;
- configured/effective diagnostics and repeated actual types.

Extend previewer performance assertions so all variants add zero queries and do
not trigger lazy loading. Retain restricted preview and frozen Livewire canary
ceilings.

Run the focused PHP tests and confirm green before editing either theme source.

## Tailwind source changes

After focused geometry tests are green, add:

```css
@source '../../../../app/Support/PublicFront/Cards/**/*';
```

to both public and admin Filament themes. Then build and prove the exact finite
row utility is present in both compiled theme outputs. Do not add broad
`app/Support/**/*` scanning or an inline safelist.

## Focused verification

Run serially:

1. targeted renderer/presenter/card-template tests;
2. targeted previewer, restricted preview, O1 shell, and navigation tests;
3. targeted browser tests;
4. `vendor/bin/pint --test` as an iteration check when appropriate;
5. `vendor/bin/filacheck --dirty` only if useful; and
6. `npm run build` after the source declarations.

Record every run and failure in the handoff.

## Browser acceptance

Use one browser owner at a time. Verify final shared rendering at 767, 768,
1024, and 1280px for HE/RTL and EN/LTR:

- item and group;
- row and card templates;
- leading, body-only, media-only, interleaved, multiple, fallback, and default
  images;
- exact logical part order;
- no row grid/gap/outer padding on body-only cards;
- leading row column rectangles at responsive widths;
- full-bleed ordered media edges;
- preview links inert and public links valid;
- O1 focus and single-root behavior where touched;
- overflow, console, network, loading, and smoke observations.

Record component/query/state and browser measurements as separate planes.

## Independent review

After implementation and focused verification, obtain at least two independent
read-only reviews:

1. architecture/compatibility/security review; and
2. tests/performance/browser-contract review.

Only the main task agent may edit. Resolve or explicitly classify every finding
before final gates.

## Documentation and classifications

Update:

- `docs/phase-02/current-project-state.md`;
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`;
- `docs/phase-02/settings-step5b-card-template-preview-handoff.md`; and
- the O2 option handoff.

Classify every approved requirement as Implemented, Already existed, Deferred,
Not applicable, or Blocked. Retain the complete deferred inventory.

## Ordered final gate

On the final tree, run exactly:

1. requirements sweep;
2. `vendor/bin/pint --test`;
3. `vendor/bin/filacheck`;
4. `npm run build`;
5. full serial `php artisan test` last.

After any edit following a green gate, restart at Pint and run the full suite
last again.

## Canonical closeout

1. Complete the handoff with `## Implementation commit hash` pending.
2. Commit code, tests, and documentation with an imperative allowed-prefix
   subject.
3. Stamp that implementation hash into the option handoff and ledger only.
4. Commit the docs-only stamp as
   `docs: backfill Step5B O2 ordered-flow hash`.
5. End clean and do not push.

## Stop conditions

Stop for a new audit if any approved baseline, security boundary, dependency,
migration, persistent state, nested-media behavior, task count, or effort
changes materially, or if unexpected overlapping worktree changes appear.
