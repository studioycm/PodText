# Codex Prompt A v2 — Public Front v2 Plan Corrections Before Step 1

Work in the current PhpStorm / Codex App project repository only.

This is a documentation-only correction task.

Do not implement application features.
Do not edit PHP, Blade, migrations, tests, Resources, Livewire components, config, package files, or app code.
Do not install packages.
Do not run migrations.
Do not run Prompt 13.
Do not push to GitHub unless explicitly asked.
Do not run `vendor/bin/filacheck --fix`.

## Goal

Patch the Public Front v2 planning docs before implementation starts.

The user approved the Public Front v2 direction, but several corrections must be applied before the first implementation prompt:

1. Fix the step reference mismatch.
2. Move Public Menu/Header after About and Podcasts to avoid dead links.
3. Defer the multi-published-transcriptions policy work for now.
4. Make it explicit that the execution plan must be converted into one implementation prompt per step.
5. Add a state-sync step after every implementation prompt.
6. Preserve the newly added PodText brand logo in admin/public panels and mention it as an existing baseline.
7. Add a clear list of future prompts that should be generated after Step 1.
8. Add a requirement that Step 1 must produce a handoff report for the external reviewer agent, ChatGPT/Yoni, describing the final JSON Settings Architecture implementation and any follow-up changes needed before generating subsequent prompts.

## Current user decisions to apply

- Defer the “allow/disallow multiple published transcriptions per item” policy implementation.
- For now, keep transcription behavior simple:
  - there is a featured/final effective transcription;
  - the first transcription created should already be featured by current model behavior;
  - do not add complex “publish and replace” workflow yet unless a later dedicated prompt explicitly approves it.
- If the transcription publication policy must be implemented earlier because an implementation conflict appears, it is cross-cutting and must run as a dedicated isolated prompt with full regression tests.
- The rest of the plan corrections should follow the review recommendations.
- The PodText logo was added to admin and public panels in the latest commit/push. Preserve it and mention it as baseline, not new work.
- Future implementation prompts must be generated after Step 1 from the final JSON Settings Architecture implementation, not from assumptions.

## Read first

- `AGENTS.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-final-report.md`
- `docs/phase-02/public-front-v2-agent-usage-index.md`
- `docs/phase-02/public-front-v2-execution-plan.md`
- `docs/phase-02/blueprints/public-front-v2/12-implementation-sequence-blueprint.md`
- `.ai/guidelines/tooling-quality.md`

## Preflight

Run:

```bash
git status --short --branch
git log --oneline --decorate -15
```

Confirm:

- Prompt 12 is complete.
- Prompt 13 has not started unless explicitly approved.
- Working tree is clean before this docs task.
- Latest commit includes the brand logo change, if present locally.

If unexpected app-code changes exist, stop and report.

## Patch only Markdown files

Patch at minimum:

- `docs/phase-02/public-front-v2-execution-plan.md`
- `docs/phase-02/public-front-v2-agent-usage-index.md`
- `docs/phase-02/public-front-v2-final-report.md` if it contains stale implementation order or transcription-policy wording
- `docs/phase-02/blueprints/public-front-v2/12-implementation-sequence-blueprint.md` if it mirrors the stale ordering

Patch `docs/phase-02/current-project-state.md` only if the latest brand-logo commit or docs-correction commit should be reflected as rolling progress state.

## Required changes

### 1. Fix the Step 1 defer reference

Find wording like:

```text
homepage_sections JSON columns until Step 3
```

Replace with:

```text
homepage_sections JSON columns until Step 4 / Public Display Sections and Loopers, unless a narrower implementation prompt explicitly needs them earlier.
```

The Looper/Public Display Sections step must be Step 4 in the corrected plan.

### 2. Defer transcription publication policy

Replace the current Step 2 implementation block that says to implement `allow_multiple_published_transcriptions_per_item = false`, publish-and-replace, import validation, and public tab restrictions.

New Step 2 should be:

```md
## Step 2: Deferred / Reserved — Transcription Publication Policy

Do not implement this step in the normal Public Front v2 run.

Current production-safe behavior remains:
- use the existing featured/effective transcription flow;
- the first transcription is featured by current behavior;
- keep public output simple around the effective/final transcription;
- do not add a complex multiple-published-transcription policy until a later dedicated prompt.

If this policy must be implemented earlier because an implementation conflict appears, it must run as a dedicated isolated prompt with full regression tests across:
- TranscriptionResource;
- ContentItem transcriptions relation manager;
- importers;
- public item page/transcript viewer;
- effective transcription resolution.
```

Also remove or rewrite any approved-decision language that says the public transcription policy “defaults to one public transcription per content item” as an immediate implementation decision. It may be listed as a deferred policy option only.

### 3. Correct implementation order

Use this corrected order:

```md
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
```

Notes:
- Public Menu/Header must run after Public Forms, About, and Podcasts so it can safely link to existing forms/pages/routes.
- If a missing route/form target is not implemented yet, menu rendering must skip or disable it server-side.

### 4. One implementation prompt per step

Add this rule clearly:

```md
The execution plan must not be pasted into Codex as one giant implementation task. Convert it into one implementation prompt per step. Each step must complete, run its quality gate, update current state, and commit before the next step starts.
```

### 5. State sync after each implementation prompt

Add this rule clearly:

```md
After every successful implementation step:
- update `docs/phase-02/current-project-state.md`;
- mark the completed step and commit hash;
- mark the next step;
- patch other docs only when stable requirements, ownership, or durable lessons changed;
- commit implementation + tests + required state docs together only after the full quality gate passes.
```

### 6. Brand logo baseline

Where the execution plan mentions the PodText logo, make the wording clear:

```md
The PodText logo already exists at `public/images/podtext-logo.jpg` from the latest branding commit. Future steps must preserve its admin/public panel use and may reuse it in public header/menu defaults where appropriate.
```

Do not imply the next implementation step needs to add the logo again.

### 7. Planned prompt list after Step 1

Add this exact section to the execution plan or agent usage index, adjusted for the surrounding document style:

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


### 8. External-agent handoff requirement after Step 1

Add this rule to the execution plan and agent usage index:

```md
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
```

### 9. Agent usage index updates

Update the agent usage index so it says:

- the execution plan is an implementation guide, not a prompt;
- one prompt per step;
- after Step 1, read `docs/phase-02/public-front-v2-step1-json-settings-handoff.md` before generating Step 3+ prompts;
- Prompt 13 remains blocked until Public Front v2 is implemented or the user explicitly chooses dashboard metrics first;
- transcription publication policy is deferred/reserved and should not run early unless explicitly approved.

## Validation

Run:

```bash
git diff --check
git status --short
```

Do not run tests, FilaCheck, build, or migrations because this is Markdown-only.

## Commit behavior

If only Markdown files changed and validation passes, commit with:

```text
docs: correct public front v2 execution plan
```

Do not push unless explicitly asked.

## Final report

Include:

- latest HEAD before commit;
- files patched;
- exact execution-order changes;
- transcription policy deferral summary;
- confirmation that menu/header is now after forms/about/podcasts;
- confirmation that one-prompt-per-step and state-sync rules were added;
- confirmation that the planned future prompt list was added;
- confirmation that the Step 1 external-agent handoff requirement was added;
- confirmation that brand logo is treated as baseline;
- validation command results;
- commit hash if committed;
- current git status.

End with exactly:

```text
Public Front v2 execution plan corrections are ready. No implementation was started.
```
