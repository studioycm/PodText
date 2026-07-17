# Step 5B Feature-First Controller

Prompt version: v1 — 2026-07-17

## Purpose

Control the transition from the completed AUTHZ closure to one small visible
feature without rebuilding a broad roadmap. Coordinate exactly two sequential,
docs-only user-visible tasks:

1. write a thorough execution prompt for preparing the Step 5B Card Template
   Preview UX specification; then
2. run that prompt in a separate task and return the completed specification to
   the operator for review.

This controller does not write application code or authorize Step 5B
implementation.

## Required kickoff

The kickoff must name this path and `v1`, identify the exact clean commit that
contains this prompt, and state that the AUTHZ closure commits `0be8070` and
`abca9ae` are present. On a version mismatch, dirty checkout, missing closure
commit, or another active same-checkout writer, stop and report.

Work directly in `/Users/studioycm/Herd/PodText` on the current branch. Do not
create a worktree, branch, parallel repository writer, subagent, or push.

## Controller preflight

1. Read `AGENTS.md` and its mandatory session-start documents in full.
2. Read:
   - `docs/research/settings-performance/19-authz-complexity-reset-and-feature-first-master-plan.md`;
   - `docs/research/settings-performance/10-pending-decision-question-queue.md`;
   - `docs/phase-02/authz-command-closure-handoff.md`;
   - `docs/phase-02/current-project-state.md`;
   - the head of the mini-step ledger; and
   - this prompt.
3. Run `git status --short --branch`, `git rev-parse HEAD`, and a short recent
   log. Confirm the checkout is clean and AUTHZ is done for now.
4. Use the technical-debt-manager framework to enforce the two-task/four-hour
   stage-fit budget. This is coordination, not a new architecture review.

## Sequential writer rule

Only one task may write this checkout at a time. The controller remains
read-only while either child task is active. Before opening the next task,
confirm that the previous task is idle or complete, its intended Markdown is
committed, and `git status --short` is clean. If any unexpected app-code change
or overlapping writer appears, stop and report instead of repairing or
absorbing it.

Monitor child tasks through short status/turn summaries. Do not copy their full
logs or research into the controller context.

## Mini-task 1 — write the specification prompt

Open a new user-visible local-project task titled **Write Step 5B preview
specification prompt**. Give it the following exact outcome and boundaries.

### Outcome

Create one thorough versioned v1 prompt at:

`prompts/pre-13-prompts/step5b-card-template-preview-ux-specification-codex-prompt.md`

That prompt must be executable by a separate docs-only task without inventing
architecture. It must prepare, not implement, a small Step 5B Card Template
Preview UX specification.

### Required source inspection

The prompt-writer must read the mandatory session-start documents, report 19,
the recovered queue, current state, ledger, feature map, prompt index, SP3C
research/plan/handoff, the original Step 5/Step 3 card-template records, and the
exact current editor/presenter/test surfaces, including:

- `app/Filament/Pages/CardTemplateEditorPage.php`;
- `app/Filament/Pages/CreateCardTemplate.php`;
- `app/Filament/Pages/EditCardTemplate.php`;
- `resources/views/filament/pages/card-template-editor.blade.php`;
- `app/Support/Settings/CardTemplates/`;
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRenderer.php`;
- the content-item, content-group, and contributor presenters;
- their public card Blade components; and
- `tests/Feature/PublicFrontCardTemplateBuilderTest.php` plus relevant SP3C
  canary fixtures/tests.

The prompt-writer may inspect additional directly relevant files, but must not
start a second broad research cycle.

### Required prompt contract

The new specification prompt must:

- be Markdown/research/planning only;
- activate the repository technical-debt-manager, Filament Forms UX audit, and
  Filament performance audit skills for the specification work;
- require installed-version Laravel/Filament/Livewire guidance and the normal
  multi-pass FilamentExamples protocol where available, while reporting access
  limitations honestly;
- define one output specification at
  `docs/research/settings-performance/21-step5b-card-template-preview-ux-specification.md`
  and one concise docs-only handoff;
- inspect and inventory current working behavior before proposing changes;
- specify the wide-screen adjacent preview and narrower-screen slide-over;
- define how normalized unsaved editor state reaches existing controlled card
  presenters without saving, adding persistence, or bypassing the current
  writer;
- cover content-item, content-group, and contributor template families only as
  current code and evidence require;
- define fixture/sample-state selection, empty/loading/error states,
  responsive behavior, Hebrew/English and RTL/LTR behavior, accessibility,
  keyboard/focus behavior, and bounded performance acceptance;
- include a compact wireframe, acceptance checklist, likely touched-file/test
  forecast, and required-versus-optional classification;
- preserve SP3A/SP3B/SP3C lifecycle, locks, backups, focused writer, protected
  state, stale detection, current public presenters, and existing data;
- keep `MAINT-LW-UX1`, P2/P3, WB, LENS, production checks, and Public Front
  queue items independent; and
- end with one decision: accept/revise the specification before any
  implementation audit.

The prompt must prohibit PHP, Blade, JS/CSS, tests, migrations, config,
dependencies, translations, database commands, browser writes, production
actions, implementation prompts, Laravel Simplifier Stage 1, and application
implementation. It must also prohibit ARCH1, SP3D, autosave, collaboration,
versioned aggregates, revisions, new permissions/roles/panels, generalized
preview architecture, and an independent-audit chain.

### Mini-task 1 finish

Update only `prompts/README.md` and the minimum rolling state/ledger pointers if
the new prompt's stable location or selected status requires it. Validate that
every changed file is Markdown with `git diff --check` and `git status --short`.
Commit the exact docs-only prompt set with subject:

`docs: plan step5b preview specification`

Do not push. Return the prompt path/version, changed files, checks, commit hash,
and clean status.

## Mini-task 2 — run the specification prompt

After Mini-task 1 is committed and the checkout is clean, open a new
user-visible local-project task titled **Prepare Step 5B Card Template Preview
UX specification**. Its kickoff must name the new prompt path, `v1`, and the
exact Mini-task 1 commit. It must state that this remains docs-only and that no
Laravel Simplifier implementation audit is authorized.

The task must execute the new prompt exactly, commit only its authorized
Markdown deliverables with subject:

`docs: specify step5b card preview ux`

It must not push. The controller monitors it without writing concurrently.

## Controller finish

When Mini-task 2 finishes, recheck the exact HEAD and clean status. Report:

- both task IDs and final statuses;
- the specification prompt path/version;
- the specification and handoff paths;
- both docs-only commit hashes;
- checks reported by each task;
- whether the two-task/four-hour budget held;
- any concrete specification decision that needs operator review; and
- the single next action: operator accept or revise the specification.

Do not start a Laravel Simplifier audit, draft an implementation prompt, or
implement Step 5B. Those require a later explicit operator selection after the
specification is reviewed.

## Stop conditions

Stop and report if:

- either task needs non-Markdown changes;
- the proposed UX requires new persistence, dependencies, roles, permissions,
  panels, versioning, collaboration, or a generalized preview platform;
- the work exceeds two logical tasks or four estimated engineering hours;
- a child proposes a second research/audit cycle;
- source and controlling docs materially conflict; or
- checkout ownership or cleanliness becomes uncertain.
