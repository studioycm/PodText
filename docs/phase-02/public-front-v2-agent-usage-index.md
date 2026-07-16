# Public Front v2 Agent Usage Index

> **ARCH1 routing notice — 2026-07-16:** Before planning Template, Form,
> settings, preview, or SP3D work, read
> `docs/research/settings-performance/07-sp3d-pre-research.md`. It supersedes the
> old settings-only storage assumption for Card Templates and Public Forms.
> Also read `09-arch1-drafts-authorization-research.md`: AUTHZ1 now precedes
> ARCH1, and Template/Form autosave working drafts are isolated per user.

## Purpose

Use this index when turning the Public Front v2 research into future implementation prompts. This file is not an implementation prompt, and the execution plan is an implementation guide rather than a prompt.

Generate one implementation prompt per step. Each implementation prompt must complete, run its quality gate, update `docs/phase-02/current-project-state.md`, and commit before the next implementation prompt starts.

## Start Here

1. Read `docs/phase-02/public-front-v2-final-report.md`.
2. Read `docs/phase-02/public-front-v2-execution-plan.md` for the approved user decisions and execution order.
3. After Step 1 is implemented, read `docs/phase-02/public-front-v2-step1-json-settings-handoff.md` before generating Step 3+ prompts.
4. Use `docs/phase-02/public-front-v2-open-questions.md` only for historical context and unresolved/deferred follow-ups.
5. Read the matching research file under `docs/research/public-front-v2/`.
6. Read the matching blueprint under `docs/phase-02/blueprints/public-front-v2/`.
7. Re-run preflight from `AGENTS.md` and the active implementation prompt.

## Topic To Blueprint Map

- JSON settings architecture:
  - Research: `docs/research/public-front-v2/01-json-settings-architecture.md`
  - Blueprint: `docs/phase-02/blueprints/public-front-v2/01-json-settings-architecture-blueprint.md`
- Card template builder:
  - Research: `docs/research/public-front-v2/02-card-template-builder.md`
  - Blueprint: `docs/phase-02/blueprints/public-front-v2/02-card-template-builder-blueprint.md`
- Display sections/loopers:
  - Research: `docs/research/public-front-v2/03-public-display-sections-loopers.md`
  - Blueprint: `docs/phase-02/blueprints/public-front-v2/03-public-display-sections-loopers-blueprint.md`
- Public menu/header:
  - Research: `docs/research/public-front-v2/04-public-menu-header-manager.md`
  - Blueprint: `docs/phase-02/blueprints/public-front-v2/04-public-menu-header-manager-blueprint.md`
- About/team:
  - Research: `docs/research/public-front-v2/05-about-page-content-team-builder.md`
  - Blueprint: `docs/phase-02/blueprints/public-front-v2/05-about-page-content-team-builder-blueprint.md`
- Public forms/submissions:
  - Research: `docs/research/public-front-v2/06-public-forms-submissions.md`
  - Blueprint: `docs/phase-02/blueprints/public-front-v2/06-public-forms-submissions-blueprint.md`
- Transcription policy:
  - Research: `docs/research/public-front-v2/07-transcription-publication-policy.md`
  - Blueprint: `docs/phase-02/blueprints/public-front-v2/07-transcription-publication-policy-blueprint.md`
- Contributors/transcribers UX:
  - Research: `docs/research/public-front-v2/08-contributors-transcribers-ux.md`
  - Blueprint: `docs/phase-02/blueprints/public-front-v2/08-contributors-transcribers-ux-blueprint.md`
- Latest/search UX:
  - Research: `docs/research/public-front-v2/09-latest-search-ux.md`
  - Blueprint: `docs/phase-02/blueprints/public-front-v2/09-latest-search-ux-blueprint.md`
- Podcasts/groups UX:
  - Research: `docs/research/public-front-v2/10-podcasts-groups-ux.md`
  - Blueprint: `docs/phase-02/blueprints/public-front-v2/10-podcasts-groups-ux-blueprint.md`
- Seeders/demo data:
  - Research: `docs/research/public-front-v2/11-seeders-demo-data.md`
  - Blueprint: `docs/phase-02/blueprints/public-front-v2/11-seeders-demo-data-blueprint.md`
- Implementation sequence:
  - Blueprint: `docs/phase-02/blueprints/public-front-v2/12-implementation-sequence-blueprint.md`

## Corrected Execution Order

0. Required agent preflight.
1. JSON Settings Architecture.
2. Deferred / Reserved — Transcription Publication Policy.
3. Card Template Builder.
4. Public Display Sections and Loopers.
5. Latest and Search UX.
6. Public Forms and Submissions.
7. About Page Content and Team Builder.
8. Podcasts and Groups UX.
9. Public Menu and Header.
10. Contributors and Top Transcribers UX.
11. Seeders, Demo Data, Assets, and Cleanup.
12. Prompt 13 Dashboard Metrics readiness / next decision.

Public Menu/Header runs after Public Forms, About, and Podcasts so it can safely link to existing forms/pages/routes. If a missing route/form target is not implemented yet, menu rendering must skip or disable it server-side.

## Required Pre-Implementation Checks

- `git status --short --branch`
- `git log --oneline --decorate -15`
- Confirm Prompt 12 complete.
- Confirm Prompt 13 has not started unless the user explicitly approved it.
- Stop if unexpected app-code changes exist.
- Confirm the previous step was committed before starting the next step.
- Use Laravel Boost `application_info`, `database_schema`, and `search_docs`.
- Use FilamentExamples MCP before Filament code and record access level.
- For FilamentExamples MCP, prepare short search-topic batches before implementation, run multiple searches with a higher limit such as 8 to 10 when supported, inspect result names/snippets/paths, run a refined second pass, and record example names, file/class paths, copied patterns, rejected patterns, and PodText adaptation notes. If only `search_examples` exists, say so explicitly.

## Non-Negotiable Boundaries

- JSON-first inside the owning aggregate; bounded global policy remains typed
  settings.
- Independently managed Card Templates/Public Forms are the approved ARCH1
  model/Resource exceptions with immutable revision-owned JSON. Do not create
  other settings-only models by analogy.
- No raw classes/CSS/SQL/PHP/Blade paths/unsafe HTML in JSON.
- Preserve existing public visibility constraints.
- Preserve custom Livewire/Blade public listing from Prompt 11R.
- Preserve `Author` as contributor/transcriber model.
- Preserve Prompt 12 safe media and transcript viewer behavior.
- Preserve existing PodText logo use from `public/images/podtext-logo.jpg`.
- Transcription publication policy is deferred/reserved and should not run early unless explicitly approved as an isolated prompt.

## Step 1 External Handoff

After Step 1 completes, Codex must create a handoff report for the external reviewer agent, ChatGPT/Yoni, before future implementation prompts are generated.

Required handoff file:
`docs/phase-02/public-front-v2-step1-json-settings-handoff.md`

The handoff must explain:

- what JSON Settings Architecture was actually implemented;
- final namespaces/classes/value objects/registries/readers/validators;
- final public API/method names future prompts should call;
- settings keys/config groups added or changed;
- fallback/default behavior;
- validation and sanitization behavior;
- how invalid config is reported or ignored;
- whether existing `PublicContentSettings` and `PublicContentCardOptions` were changed;
- sample JSON config payloads;
- sample PHP usage for future steps;
- any deviations from the blueprint;
- any small implementation details that may affect card templates, loopers, public forms, menu/header, about/team, podcasts/groups, contributors, seeders, or Prompt 13;
- exact recommendations for how the next prompts should adapt to the final implementation.

## Planned prompts after Step 1

The execution plan should require one implementation prompt per step. After Step 1 is finished and reviewed, future prompts should be generated in this order, with exact wording adapted to the final JSON Settings Architecture implementation:

1. Public Front v2 Step 3: Card Template Builder Foundation.
2. Public Front v2 Step 4: Public Display Sections and Loopers.
3. Public Front v2 Step 5: Latest and Search UX.
4. Public Front v2 Step 6: Public Forms and Submissions.
5. Public Front v2 Step 7: About Page Content and Team Builder.
6. Public Front v2 Step 8: Podcasts and Groups UX.
7. Public Front v2 Step 9: Public Menu and Header.
8. Public Front v2 Step 10: Contributors and Top Transcribers UX.
9. Public Front v2 Step 11: Seeders, Demo Data, Assets, and Cleanup.
10. Public Front v2 Step 2 / Reserved: Transcription Publication Policy, only if explicitly promoted from deferred status and always as an isolated prompt.
11. Public Front v2 Step 12: Prompt 13 Dashboard Metrics readiness / next decision.

Do not pre-generate all implementation prompts before Step 1 is reviewed. The final public JSON settings API may affect all following prompts.

## Prompt 13 Note

Prompt 13 remains blocked until Public Front v2 is implemented or the user explicitly chooses dashboard metrics first.
