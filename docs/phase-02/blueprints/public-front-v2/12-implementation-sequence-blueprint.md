# Public Front v2 Implementation Sequence Blueprint

> **Historical sequence notice — 2026-07-16:** This blueprint remains evidence
> for the shipped Public Front v2 sequence. Its Template/Form settings-only
> guardrail is superseded by ARCH1 in
> `docs/research/settings-performance/07-sp3d-pre-research.md`.

Using Filament Blueprint, produce an implementation plan for a Filament v5 application feature: Public Front v2 implementation sequence before Prompt 13.

The plan should:
- Describe the primary user flows end to end.
- Map each domain/configuration concept and flow to concrete Filament primitives such as Settings Pages, Resources, Pages, Relation Managers, Actions, Builder blocks, Repeaters, FileUpload, RichEditor, and Livewire components.
- Identify configuration/state transitions and the actions that trigger them.
- Identify public Livewire/Blade flows and admin Filament flows.
- Identify tests, security rules, and out-of-scope boundaries.

## Goal

Define the recommended sequence for implementation prompts after Prompt 12 and before resuming Prompt 13 dashboard metrics.

## Dependencies

- User approval of this research/blueprint pack.
- Resolution of open questions in `docs/phase-02/public-front-v2-open-questions.md`.
- Current Prompt 12 code and docs remain source of truth.

## Recommended Order

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

## Why This Order

- JSON settings and registries must exist before storing large public-front configuration.
- Card templates are needed by latest, groups, loopers, and contributor previews.
- Loopers provide the source/display system for Latest.
- Search/latest fixes are high user-facing value and exercise card/layout assumptions.
- Forms, About, and Podcasts must exist before Menu/Header so menu entries can safely link to existing public routes and enabled form targets.
- Group and contributor refinements reuse templates and source resolvers.
- Seeder cleanup should happen after final default JSON shapes are known.
- Transcription publication policy is deferred/reserved. If implementation conflict forces it earlier, run it as a dedicated isolated prompt with full regression tests.
- Prompt 13 dashboard metrics remains blocked until Public Front v2 is implemented or the user explicitly chooses dashboard metrics first.

## Prompt Conversion Rule

The execution plan must not be pasted into Codex as one giant implementation task. Convert it into one implementation prompt per step. Each step must complete, run its quality gate, update current state, and commit before the next step starts.

After every successful implementation step:

- update `docs/phase-02/current-project-state.md`;
- mark the completed step and commit hash;
- mark the next step;
- patch other docs only when stable requirements, ownership, or durable lessons changed;
- commit implementation + tests + required state docs together only after the full quality gate passes.

After Step 1 completes, Codex must create `docs/phase-02/public-front-v2-step1-json-settings-handoff.md` for ChatGPT/Yoni before future implementation prompts are generated.

## Filament Primitive Mapping

Each implementation prompt should include only the relevant primitives:

- Settings Pages for JSON config.
- HomepageSection Resource for loopers.
- Public Filament Pages for About/groups if needed.
- Actions for form modal/slide-over and admin selection workflows.
- Builder/Repeater for JSON arrays.
- Optional `PublicFormSubmissionResource` only for actual submissions.

## JSON Settings/Configuration Shape

All implementation prompts must reuse the architecture blueprint naming:

- site-level arrays in settings
- section-level JSON on `HomepageSection`
- normalized readers before rendering

## Models/Migrations

Default no new settings models. The only approved model exception is `PublicFormSubmission`, and it belongs to the later Public Forms and Submissions step only.

## Casts/Enums/Support Classes

Create small focused registries/readers per domain. Avoid broad `ContentService` style classes.

## Relationships

Keep internal domain names:

- `ContentGroup`
- `ContentItem`
- `Author`
- `Transcription`

## Filament Resources/Pages

Use Filament 5 APIs only. Existing public listings remain custom Livewire/Blade, not Filament Tables.

## Form Schemas

Every JSON editing form uses whitelisted values, helper text for technical keys, and no arbitrary code/class/view/style fields.

## Tables/Actions

Use tables/actions only for admin selection or real transactional records. Do not create admin tables for settings-only JSON unless there is a model-backed exception.

## Public Pages/Livewire/Blade

Preserve:

- Prompt 11R custom Livewire state and Blade card grids/rows.
- Prompt 11B `Author` contributor discovery.
- Prompt 12 public item page, safe media component, parse-only transcript viewer.

## Settings

Use code defaults so pages render safely without settings rows. Use production-safe seeders only when defaults must be persisted for admin editing.

Defer `homepage_sections` JSON columns until Step 4 / Public Display Sections and Loopers, unless a narrower implementation prompt explicitly needs them earlier.

## Seeders

Defer final seeder split until default JSON structures stabilize.

## Tests

Each implementation prompt adds focused Pest tests for:

- settings defaults and invalid config fallback
- admin settings save behavior
- public rendering and draft hiding
- Livewire URL state
- security/sanitization
- import/form submission edge cases where relevant

## Security

Never store/render raw CSS, classes, SQL, arbitrary PHP, arbitrary Blade paths, iframe HTML, or unsafe rich HTML. Public forms need rate limiting/honeypot before production.

## State/Configuration Transitions

Every prompt final report should classify meaningful blueprint requirements as implemented, already existed, deferred, not applicable, or blocked.

## Out Of Scope

- Starting Prompt 13 without user approval.
- Implementing Step 2 / Reserved transcription publication policy unless explicitly promoted as an isolated prompt.
- Installing packages.
- Replacing the public panel architecture.
- Creating Podcast/Episode models.

## Quality Gate

For future implementation prompts run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

Docs-only follow-up prompts may use `git diff --check` and `git status --short`.

## Final-Report Checklist

- State which sequence step was implemented.
- State user decisions applied.
- State Boost and FilamentExamples usage.
- State whether Step 1 handoff was created when Step 1 is implemented.
- State current-state document updates and next step.
- State tests and quality gate.
- State current git status.
