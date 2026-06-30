# Prompt 14: Phase 02 Viewer and Studio Future Plan

## Goal

Plan future synced viewer and transcription studio work after parse-only viewer exists.

## Current state assumptions

- Prompt 12 already implemented parse-only timestamp/speaker viewer behavior.

## Preflight and carry-forward rules

- Run git status/log preflight before changing docs and stop on unexpected app-code dirt.
- Confirm Prompt 12 is complete before starting; this prompt remains planning-only.
- Preserve Prompt 10 native import/export behavior and Prompt 12 parse-only viewer behavior in the future plan assumptions.
- Do not implement player sync, studio UI, autosave, migrations, Resources, Livewire components, or Blade files.
- If this planning prompt is committed, keep changes Markdown-only unless the user explicitly changes the prompt.

## Docs to read

- `AGENTS.md`
- `docs/phase-02/transcript-viewer-and-studio-future-plan.md`
- `docs/phase-02/tooling-and-quality-gates.md`

## Blueprint and guidelines

- `docs/phase-02/blueprints/14-viewer-studio-future-plan-blueprint.md`
- `.ai/guidelines/viewer-studio.md`
- `.ai/guidelines/tooling-quality.md`

## Blueprint contract

The blueprint file named above is the planning contract for this prompt.

Before changing docs:

1. Read the entire blueprint.
2. Summarize the blueprint sections that apply to this prompt.
3. Compare the blueprint against the current repository state and Prompt 12 implementation.
4. If the blueprint conflicts with the active prompt, Phase 02 specs, `AGENTS.md`, or current code, stop and report the conflict before changing docs.
5. If Prompt 12's parser/viewer implementation differs from the future plan assumptions, document the difference.

Do not implement studio/sync features. Do not create migrations, Resources, Livewire components, or Blade files unless this prompt is explicitly changed later.

## Scope

Documentation/planning only for future synced viewer and transcription studio.

## Out of scope

No studio implementation, no autosave implementation, no player sync implementation, no new Resources unless explicitly approved.

## Package/tool assumptions

Use FilamentExamples MCP for custom Livewire/Alpine/sidebar patterns. Do not install packages.

## Implementation plan

1. Inspect current Prompt 12 viewer/parser implementation.
2. Document future viewer/studio requirements and risks.
3. Define later permissions, failure states, and testing strategy.

## Acceptance criteria

Future plan is explicit enough for a later implementation prompt and does not implement features.

## Required tests

Run tests only if code changes, which should not happen in this prompt.

## Required quality gate

```bash
git diff --check
git status --short
```

If any app code changes accidentally, also run the full implementation gate.

## Final report format

Report docs changed, commands/results, assumptions, deferred issues, and confirmation that no app features were built.

## Commit behavior

Commit only after human review or explicit user instruction.
