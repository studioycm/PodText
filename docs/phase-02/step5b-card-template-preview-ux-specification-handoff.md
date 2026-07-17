# Step 5B Card Template Preview UX Specification Handoff

## Contract

- Prompt:
  `prompts/pre-13-prompts/step5b-card-template-preview-ux-specification-codex-prompt.md`
- Prompt version: v1 — 2026-07-17.
- Clean starting commit:
  `89784d3accc6b5677576b1ebf2f9fcf2cb2d5f16`.
- Task type: Markdown-only specification. No implementation, implementation
  prompt, or Laravel Simplifier audit was started.

## Outcome

Prepared the evidence-backed v1 specification at
`docs/research/settings-performance/21-step5b-card-template-preview-ux-specification.md`.
It defines a read-only preview of the existing single unsaved Card Template
draft through shared candidate cleanup, the existing validator/value object,
the current renderer/presenters, and the public Blade components.

The recommended required slice is:

- explicit Preview/Refresh rather than per-keystroke rendering;
- one deterministic public-safe sample for `content_item`, `content_group`, or
  `contributor`;
- an adjacent preview at `xl` and wider and a read-only slide-over below `xl`;
- a preview-only in-memory default render context with zero settings/lifecycle
  activity;
- inert preview links/actions, HE/EN and RTL/LTR behavior, focus/keyboard
  requirements, and honest component-versus-browser measurement boundaries;
- a bounded transient sample selector, localized freshness/sample details, and
  recorded browser DOM/focusable/network/listener/heap/timing observations;
- preservation of the SP3C writer, one draft, protected-state rules, Builder
  previews, dirty navigation, and all settings lifecycle paths.

The previously optional selector, freshness details, and browser evidence are
required by operator revision. Persistence, autosave/collaboration/revisions,
synthetic samples, general preview infrastructure, permissions,
AUTHZ/ARCH1/SP3D, and unrelated queue work remain deferred/out of scope because
they require separate storage, lifecycle, authorization, or acceptance
contracts and are not necessary for a read-only unsaved preview.

## Files changed

- `docs/research/settings-performance/21-step5b-card-template-preview-ux-specification.md`
- `docs/phase-02/step5b-card-template-preview-ux-specification-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

All changed files are Markdown.

## Research and tool access

- Read the controlling reset/queue/state, SP3C research/plan/handoff, original
  Step 3/5 records, exact editor/writer/validator/presenter/Blade surfaces, and
  the focused tests/canaries/fixtures required by the prompt.
- Applied the repository technical-debt, Filament forms UX, Filament
  performance, and read-only Laravel best-practices guidance as one integrated
  specification review.
- Laravel Boost returned installed versions and version-aware Filament 5 and
  Livewire 4 documentation. No database schema/query tool was needed.
- FilamentExamples provided search/snippet results only. Relevant patterns were
  custom-page header actions, a responsive two-column custom page, and
  read-only action/modal composition. No source/detail endpoint was available.

## Checks

- `git diff --check`: passed with no output after the final pointer edits.
- `git status --short`: reported exactly the four authorized Markdown paths
  before commit; the post-commit status is recorded in the final report.
- Markdown-only changed-path audit using `git status --porcelain=v1`: passed;
  every changed/untracked path ended in `.md`.
- Post-operator revision `git diff --check`: passed after making all previously
  optional items required and clarifying the effort breakdown.
- No PHP tests, Pint, FilaCheck, npm, Composer, Artisan/database commands,
  browser writes, or generators were run because the prompt permits docs checks
  only.

## Assumptions and deferrals

- `xl` (1280 CSS px) is the specified breakpoint, subject to required manual
  HE/EN browser acceptance in a later implementation.
- A shared stateless draft-normalization extraction and preview-only in-memory
  render-context factory are the smallest likely seams; a later Simplifier
  audit must validate their exact shape.
- Existing numeric SP3C ceilings remain frozen. A later candidate must establish
  a separate three-run Step 5B delta before adopting numeric preview caps.
- No claim is made for simultaneous-request serialization, literal JSON bytes,
  durable remount recovery, browser DOM/listener/heap/network/TTFB metrics, or
  other unmeasured behavior.

## Budget result

This completed Mini-task 2 as the controller's second and final docs-only task.
The revised specification remains within the two-task/four-hour stop rule with
little margin. The specified later feature is forecast at no more than two
logical implementation tasks: approximately 1.5–2.5 hours of coding plus
1–1.5 hours for focused tests, browser evidence, and ordered verification,
3–4 hours total. There is no dependency change and only one integrated review.
Scope drift beyond those bounds requires a new operator decision.

## Operator decision

**Accept the v1 specification or request revisions before any Laravel
Simplifier implementation audit.**
