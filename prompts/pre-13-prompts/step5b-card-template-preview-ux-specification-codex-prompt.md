# Step 5B Card Template Preview UX Specification

Prompt version: v1 — 2026-07-17

## Purpose

Prepare one bounded, evidence-backed specification for adding an unsaved Card
Template preview to the existing SP3C one-template editor. This is a
Markdown/research/planning task only. It does not authorize implementation,
an implementation prompt, or a Laravel Simplifier Stage 1 audit.

The specification must be concrete enough that a later, separately authorized
implementation audit can estimate and evaluate one small feature without
inventing persistence, lifecycle, authorization, rendering, or generalized
preview architecture.

## Required kickoff and baseline

The kickoff must name this path and `v1`, name the exact clean commit that
contains this prompt, and state that the task remains docs-only. On a version
mismatch, dirty checkout, missing prompt commit, another active same-checkout
writer, or any unexpected PHP, Blade, migration, test, configuration,
dependency, translation, JS/CSS, or other application-code change, stop and
report.

Work directly in `/Users/studioycm/Herd/PodText` on the current branch. Do not
create a branch, worktree, parallel repository writer, subagent, or push.

Confirm that the AUTHZ command closure commits `0be8070` and `abca9ae` are
ancestors of `HEAD`. AUTHZ is done for now. The controlling reset in
`docs/research/settings-performance/19-authz-complexity-reset-and-feature-first-master-plan.md`
supersedes older forward-looking ARCH1/SP3D notices in historical SP3C and
Public Front records. Those notices remain historical evidence, not current
scope.

## Absolute task boundary

This task may read repository files and installed-version documentation and may
write only authorized Markdown. It must not change or generate:

- PHP, Blade, JavaScript, CSS, tests, fixtures, migrations, settings migrations,
  configuration, dependencies, lockfiles, translations, assets, or application
  code;
- database rows or schema, and it must not run Artisan database commands,
  seeders, migrations, model probes, or local-development-database experiments;
- browser state through clicks, form submissions, authentication, or other
  browser writes;
- production state or configuration;
- an implementation prompt, implementation plan presented as authorization,
  Laravel Simplifier Stage 1 audit, code change, or application implementation.

Do not add or revive ARCH1, SP3D, SP4, LOG1, autosave, collaboration,
simultaneous editing, per-user drafts, versioned aggregates, revisions,
checkpoints, publication workflows, new roles, permissions, panels, generalized
preview infrastructure, or an independent-audit/remediation chain.

Do not absorb `MAINT-LW-UX1`, P2, P3, Workbench/WB work, LENS review packs,
production settings/cache/mail checks, SP3 browser evidence as a separate
project, or any other Public Front queue item. Keep each independent.

## Mandatory session start

Before research or edits, complete the `AGENTS.md` session-start protocol in
order:

1. Read `AGENTS.md` in full.
2. Read `docs/phase-02/ai-development-lessons.md` in full.
3. Read `docs/phase-02/current-project-state.md`.
4. Read the head/current-run rows of
   `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`.
5. Read the newest one or two distinct `docs/phase-02/*-handoff.md` files by
   date in git history.
6. Read this prompt in full and confirm `Prompt version: v1 — 2026-07-17`.

Then run only read-only preflight commands:

```bash
git status --short --branch
git rev-parse HEAD
git log --oneline --decorate -12
git merge-base --is-ancestor 0be8070 HEAD
git merge-base --is-ancestor abca9ae HEAD
```

Stop on any baseline mismatch or uncertain checkout ownership. Do not repair or
absorb unrelated changes.

## Required skills and stage-fit control

Activate and apply these repository-owned skills for this specification task:

- `technical-debt-manager-php-laravel`, narrowly for stage-fit, impact/effort,
  reuse, risk, and the two-task/four-hour stop rule—not for a repository-wide
  debt inventory;
- `filament-forms-ux-audit`, for the current one-template form, adjacent versus
  slide-over interaction, validation/error discoverability, hidden state,
  localization, RTL/LTR, keyboard, focus, and responsive behavior; and
- `filament-performance-audit`, for Builder/Livewire state, hydration,
  presenter/query behavior, measurement planes, and bounded acceptance.

Follow any directly required reference routing from those skills. If the
performance audit touches Eloquent query shape, caching, Blade, or Livewire
state, apply the repository Laravel best-practices guidance read-only as
required by that skill. Do not turn any skill into a separate broad audit or
write its default standalone report in addition to this specification.

At the start of the specification, publish the forecast:

- this is one logical docs-only specification task, the second and final task
  in the controller's two-task sequence;
- estimated remaining effort and whether the combined controller stays within
  four engineering hours;
- no dependency changes;
- one integrated specification review only, with no independent-audit chain.

If the work would exceed two total controller tasks or four estimated hours,
stop and request operator reapproval instead of expanding scope.

## Required source inspection

Read the following before drafting conclusions. Use current code as the source
of truth and historical documents only for shipped intent and evidence.

### Controlling state and queue

- `docs/research/settings-performance/19-authz-complexity-reset-and-feature-first-master-plan.md`
- `docs/research/settings-performance/10-pending-decision-question-queue.md`
- `docs/phase-02/current-project-state.md`
- the current-run and Step 5B rows in
  `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/feature-map.md`
- `prompts/README.md`

### SP3C shipped behavior and evidence

- `docs/research/settings-performance/05-sp3c-research.md`
- `docs/research/settings-performance/05-sp3c-implementation-plan.md`
- `docs/phase-02/settings-sp3c-handoff.md`
- `prompts/pre-13-prompts/settings-sp3c-codex-prompt.md` only where needed to
  confirm a shipped preservation boundary

### Original Step 3 and Step 5 records

- `docs/research/public-front-v2/02-card-template-builder.md`
- `docs/phase-02/blueprints/public-front-v2/02-card-template-builder-blueprint.md`
- `docs/phase-02/blueprints/public-front-v2/blueprint-results/02-card-template-builder-plan.md`
- `docs/phase-02/public-front-v2-step3-card-template-builder-handoff.md`
- the Step 3 and Step 5 sections of
  `docs/phase-02/public-front-v2-execution-plan.md`
- `docs/research/public-front-v2/09-latest-search-ux.md`
- `docs/phase-02/blueprints/public-front-v2/09-latest-search-ux-blueprint.md`
- `docs/phase-02/public-front-v2-step5-latest-search-ux-handoff.md`

### Exact current editor, normalization, writer, and presentation surfaces

- `app/Filament/Pages/CardTemplateEditorPage.php`
- `app/Filament/Pages/CreateCardTemplate.php`
- `app/Filament/Pages/EditCardTemplate.php`
- `resources/views/filament/pages/card-template-editor.blade.php`
- the Card Template portions of
  `app/Filament/Pages/BuildsPublicContentSettingsSubjectSchemas.php`
- `app/Support/Settings/CardTemplates/`
- the Card Template portions of
  `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplate.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateResolver.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRenderer.php`
- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContentGroupCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContributorCardPresenter.php`
- `resources/views/components/public/content-item-card.blade.php`
- `resources/views/components/public/content-item-card-part.blade.php`
- `resources/views/components/public/content-group-card.blade.php`
- `resources/views/components/public/content-group-card-part.blade.php`
- `resources/views/components/public/contributor-card.blade.php`
- `resources/views/components/public/contributor-card-part.blade.php`

### Exact current tests, canaries, and fixtures

- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `tests/Feature/SettingsSp3cTest.php`, focusing on the one-draft editor,
  protected state, focused writer, stale/collision/reference behavior,
  lifecycle counts, and measured editor surfaces
- `tests/Feature/SettingsSp3cCanaryTest.php`
- `tests/Support/SettingsSp3cCanaryPage.php`
- `tests/Support/SettingsSp3cCanaryLibraryPage.php`
- `tests/Support/SettingsSp3cCanaryMeasurement.php`
- `tests/Support/SettingsSp3cDeepestFixture.php`
- `tests/Fixtures/settings-sp3c-canary/`

You may inspect additional directly relevant files for sample-query ownership,
presenter call sites, accessibility primitives, or installed package behavior.
Do not begin a second broad research cycle.

## Installed-version and FilamentExamples research

Use Laravel Boost when available to confirm installed Laravel, Filament, and
Livewire versions. Use installed-version `search_docs` before specifying any
version-sensitive Filament Page, Schema, Action, slide-over/modal, Livewire
state/update, loading, or accessibility behavior. Use `database_schema` only if
needed to validate a read-only sample query recommendation; do not query the
development database.

Use the normal FilamentExamples protocol when the configured server is
available:

1. Decompose the feature into short topics such as adjacent form preview,
   responsive slide-over, unsaved Livewire state, accessible focus handling,
   Builder preview coexistence, loading/empty/error states, and card/sample
   rendering.
2. Search multiple short batches with limits of 8–10 where accepted.
3. Inspect returned names, snippets, paths, and class names.
4. Run a refined second pass from useful terms found in the first pass.
5. Record each relevant example, pattern to reuse, pattern to avoid, and the
   PodText adaptation.

Report the exact access level honestly. If only search/snippet access exists,
say so; do not claim source/detail access. If Boost or FilamentExamples is
unavailable, record the limitation and rely on installed source/current code
without fabricating results. Do not let tool limitations open another research
cycle.

## Primary deliverables

Create exactly one primary specification at:

`docs/research/settings-performance/21-step5b-card-template-preview-ux-specification.md`

Create one concise docs-only handoff at:

`docs/phase-02/step5b-card-template-preview-ux-specification-handoff.md`

The handoff must summarize the outcome, required-versus-optional decision,
files changed, research/tool access, checks, assumptions, deferrals, budget
result, and the single accept/revise decision. It must not masquerade as an
implementation handoff or include a Local Front implementation check.

Minimum rolling pointer updates are allowed only in:

- `docs/phase-02/current-project-state.md`; and
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`.

Update those pointers only to say the specification was prepared and awaits
operator accept/revise. Do not update feature ownership, stable architecture,
the surviving queue, historical reports/handoffs, or other prompt files.

## Specification method

The specification must inventory current working behavior before proposing any
change. Separate verified code facts from inferred behavior and from decisions
that require a browser or data-volume sample.

At minimum, inventory these facts and confirm or correct them from current code:

- Create/Edit mount exactly one Builder-shaped draft under editor form state;
- the editor already has dirty-navigation protection, locked server-owned
  identity/capability/measurement state, protected shell behavior, bilingual
  validation, and one focused mutation path;
- `PublicFrontConfigValidator` accepts Builder `type` + `data` transport and
  normalizes one-level grouped parts to safe semantic JSON;
- `CardTemplateFocusedWriter` is the only Card Template persistence boundary
  and privately performs any additional draft transport cleanup before
  candidate-only validation;
- existing renderer/value-object/presenters and public Blade components own
  controlled output for `content_item`, `content_group`, and `contributor`;
- current presenters require family-specific model data and already own
  semantic output, safe URLs, fallbacks, and card-part projection;
- SP3C canaries measure server/component/state planes and explicitly leave real
  teleported-modal/browser DOM/listener/heap/Back behavior separate; and
- preview is currently absent and Step 5B remains unimplemented.

Do not repeat a full SP3C or Public Front audit. Use this inventory to define
the smallest preview slice.

## Required specification content

The specification must contain the following sections and decisions.

### 1. Outcome, users, and non-goals

Define the editor decision the preview helps an Admin/Super Admin make. State
that preview is read-only presentation of the current unsaved draft, never a
save, backup, settings change, publication action, or permission boundary.

### 2. Required-versus-optional classification

Classify every proposed element as:

- **Required for Step 5B**;
- **Optional if it fits the same bounded implementation**; or
- **Deferred/out of scope**.

The required slice must fit one later implementation prompt, no more than two
logical implementation tasks, four estimated engineering hours, and no
dependency change. Optional polish must be droppable without weakening the
core preview.

### 3. Unsaved-state-to-presenter contract

Define one explicit, read-only flow from the current editor draft to existing
controlled output. The flow must cover:

1. obtaining current unsaved `data` without calling `save()` or the focused
   writer;
2. converting Builder transport to the same candidate semantics used for a
   real save, including the writer's current cleanup of stale group-only fields
   and nested children;
3. validating/normalizing exactly one template through the existing
   `PublicFrontConfigValidator` card-template rules;
4. refusing or presenting a safe error for zero/multiple normalized templates
   or invalid config instead of falling back silently to a saved template;
5. creating/resolving the current family template value object from that
   normalized unsaved row without reading/saving the configured template list;
6. passing that template and one family-appropriate sample into the existing
   `PublicFrontCardTemplateRenderer`, family presenter, and public card Blade
   component; and
7. keeping every persistence operation routed through
   `CardTemplateFocusedWriter` only when the user explicitly saves.

Identify the smallest likely seam needed to avoid duplicating the writer's
private transport cleanup. A focused preview-state adapter or extraction may be
forecast only if current code proves it necessary. Do not design a general
preview framework, new settings reader, alternate writer, or second lifecycle.

State explicitly that preview normalization must cause zero
`PublicContentSettings::save()`, `SettingsSaved`, backup attempts, cache
invalidations, reference scans, writer calls, or settings mutations.

### 4. Family and sample contract

Cover only the current `content_item`, `content_group`, and `contributor`
families, and only where current registries, presenters, public Blade, and test
evidence support them.

For each family, specify:

- exact presenter and public Blade component reused;
- required eager-loaded model relations/aggregates or presenter inputs;
- deterministic sample-selection order;
- whether a sample selector is required or optional;
- how family changes refresh the sample without persisting selection;
- public-visibility and safe-data boundaries; and
- the empty state when no valid sample exists.

Prefer current public-safe query helpers and a bounded existing record over
synthetic persisted sample records. Tests must own factories/fixtures. Do not
add sample tables, settings keys, seed requirements, or remote fetches. Do not
load a sample on every draft keystroke.

### 5. Interaction and responsive layout

Specify:

- a persistent adjacent preview beside the editor on wide screens;
- a Preview action opening a slide-over on narrower screens;
- the exact responsive breakpoint/behavior and what happens during resize;
- whether preview refresh is explicit, change/blur-driven, or debounced by
  field type, with a reason grounded in Livewire 4 state and performance;
- whether the preview stays open across validation errors and retains the
  unsaved draft;
- loading, empty, invalid-draft, sample-error, and protected/restricted states;
- no replacement of the existing Builder block previews, save/cancel/delete
  actions, import-lock badge, dirty-navigation warning, or error ownership.

Include one compact wide-screen wireframe and one compact narrow-screen
slide-over wireframe. The diagrams must identify editor, preview, refresh/state
feedback, close control, and scroll ownership without becoming visual design
mockups.

### 6. Hebrew/English and RTL/LTR

Define HE-first and EN behavior, logical placement, text direction for
technical keys versus user content, long-title/label handling, responsive
mirroring, and translation-key ownership for every future visible label,
hint, error, loading, empty, and action string. Do not add translations in this
task.

### 7. Accessibility and keyboard/focus behavior

Define:

- a labelled preview region and status semantics;
- accessible Preview/Refresh/Close actions;
- slide-over focus entry, focus trap, Escape/close, and focus restoration;
- keyboard reachability without adding a global shortcut;
- independent editor and preview scrolling;
- error discoverability without noisy per-keystroke live-region announcements;
- non-color-only loading/error/selection communication; and
- preview links/actions behavior so an admin does not accidentally navigate
  away from a dirty editor. Decide whether links are inert in preview or need a
  clearly bounded safe alternative while preserving visible card parity.

### 8. Bounded performance acceptance

Keep measurement planes honest. Server response, query, component HTML,
serialized state, and browser DOM/listener/heap/network/TTFB are distinct.

Define repeatable acceptance tied to current SP3C fixtures/helpers, including:

- no duplicate copy of the full draft in Livewire state;
- no new persisted preview state;
- no settings read, reference scan, writer call, lifecycle derivation, save
  event, backup, or cache invalidation on draft-only preview refresh;
- sample loading at mount/family/sample change only, with a fixed bounded query
  count and all presenter-required relations/aggregates eager-loaded;
- zero lazy-loading from presenter/Blade rendering;
- no new per-part query or settings read;
- preview normalization/presentation once per accepted refresh boundary, not
  per Blade part;
- current Builder editor wrapper/control guarantees preserved; preview markup
  must not mount a second editor schema or duplicate Builder controls;
- explicit component HTML/state/query caps or delta budgets derived from a
  named deterministic fixture. Reuse SP3C's DOM parser and encoding contract
  where appropriate, but do not invent numeric browser caps without a runner;
  and
- real-browser focus/responsive/teleported slide-over evidence classified as
  required manual acceptance or optional measured evidence, never relabelled
  from component tests.

If current evidence cannot support a numeric cap, define the exact baseline and
measurement procedure the later implementation must run before adopting a cap.
Do not weaken or overwrite the existing SP3C frozen measurements.

### 9. Preservation matrix

Include a compact matrix showing how Step 5B preserves:

- SP3A measurement fixture/SHA, profiling boundaries, lifecycle derivation,
  and existing response-plane claims;
- SP3B ownership/fresh owned-path behavior;
- SP3C library, one-template draft, Builder previews, locked state,
  protected-state absence/restoration, optimistic fingerprint/stale detection,
  reference/default/collision guards, focused writer, one-save lifecycle,
  sibling/foreign-root preservation, and dirty-navigation behavior;
- import locks, import/restore/normalize, backups/snapshots, cache invalidation,
  current roles/mode gates, and existing data; and
- existing public renderer/presenter/Blade behavior.

Do not claim simultaneous-request serialization, scan-to-save TOCTOU closure,
literal database JSON bytes, durable remount recovery, or browser evidence that
was not measured.

### 10. Acceptance checklist and likely implementation forecast

Provide a checkbox acceptance checklist covering all required behavior,
failure states, family coverage, accessibility, localization, security,
performance, and preservation boundaries.

Forecast likely touched files and tests, grouped by:

- existing editor/page/view surfaces;
- the minimum focused read-only normalization/preview seam, if evidence proves
  one is needed;
- existing presenters/public Blade components expected to be reused rather
  than rewritten;
- HE/EN translations that a later implementation would add; and
- focused Pest/Livewire/component/browser-manual acceptance coverage.

Classify each forecast as likely change, reuse only, or test-only. Do not create
or edit those files now. Include a bounded task/effort/dependency/audit forecast
and a stop condition if implementation evidence exceeds it.

### 11. Open decision and recommendation

Resolve every small design question that current evidence can answer. End with
exactly one operator decision:

**Accept the v1 specification or request revisions before any Laravel
Simplifier implementation audit.**

Do not offer implementation start, implementation prompt drafting, an audit
chain, ARCH1, or SP3D as the next action.

## Handoff and checks

The concise handoff must include:

- prompt path/version and clean starting commit;
- specification outcome and recommended required slice;
- files changed, all Markdown;
- installed-version and FilamentExamples access level;
- checks run and results;
- assumptions, optional items, and deferred items;
- two-task/four-hour budget result; and
- the single accept/revise decision.

Run only the docs checks:

```bash
git diff --check
git status --short
```

Also verify with a changed-path audit that every changed file ends in `.md`.
Do not run PHP tests, Pint, FilaCheck, npm, Composer, migrations, database
commands, browser writes, or application generators for this docs-only task.

Commit the exact authorized Markdown set once with subject:

`docs: specify step5b card preview ux`

Do not create a second hash-stamp commit and do not push. Return the
specification path, handoff path, changed files, checks, commit hash, clean
status, budget result, and the single accept/revise next action.
