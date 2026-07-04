# Public Front v2 Agent Usage Index

## Purpose

Use this index when turning the Public Front v2 research into future implementation prompts. This file is not an implementation prompt.

## Start Here

1. Read `docs/phase-02/public-front-v2-final-report.md`.
2. Read `docs/phase-02/public-front-v2-execution-plan.md` for the approved user decisions and execution order.
3. Use `docs/phase-02/public-front-v2-open-questions.md` only for historical context and unresolved/deferred follow-ups.
4. Read the matching research file under `docs/research/public-front-v2/`.
5. Read the matching blueprint under `docs/phase-02/blueprints/public-front-v2/`.
6. Re-run preflight from `AGENTS.md` and the active implementation prompt.

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

## Required Pre-Implementation Checks

- `git status --short --branch`
- `git log --oneline --decorate -15`
- Confirm Prompt 12 complete.
- Confirm Prompt 13 has not started unless the user explicitly approved it.
- Stop if unexpected app-code changes exist.
- Use Laravel Boost `application_info`, `database_schema`, and `search_docs`.
- Use FilamentExamples MCP before Filament code and record access level.

## Non-Negotiable Boundaries

- JSON-first settings/configuration.
- No settings-only models by default.
- No raw classes/CSS/SQL/PHP/Blade paths/unsafe HTML in JSON.
- Preserve existing public visibility constraints.
- Preserve custom Livewire/Blade public listing from Prompt 11R.
- Preserve `Author` as contributor/transcriber model.
- Preserve Prompt 12 safe media and transcript viewer behavior.

## Prompt 13 Note

Prompt 13 dashboard metrics should wait until Yoni approves whether this public-front v2 plan should be implemented first or whether dashboard metrics should proceed immediately.
