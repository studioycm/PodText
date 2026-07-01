# Admin Resource and Relation Manager Research — Overview

You are working inside the current PhpStorm project repository for PodText.

This is a historical **docs/research/blueprint/prompt refinement task** that ran before Prompt 08.

The goal is to research rich Filament admin Resource and Relation Manager UX patterns, then update the Phase 02 planning docs so Prompt 09 can implement better admin management for `ContentItem` → `Transcriptions`.

## Why this runs before Prompt 08

Current state note: this is a historical research task. For current prompt progress, read `docs/phase-02/current-project-state.md`. Do not rerun this research task unless explicitly asked.

Prompt 08 is schema/foundation only. Prompt 09 is responsible for admin Resource UX, including transcriptions, categories, homepage sections, settings, item/category/tag/media fields, and related admin-management screens.

This task prepares Prompt 09 and its blueprint. It must not implement those features.

## Non-negotiable rules

- Work sequentially in the current checkout.
- Do not create worktrees.
- Do not launch parallel agents.
- Do not implement application features.
- Do not edit PHP, Blade, migrations, tests, Resources, Livewire components, config, or app code.
- Do not run Filament generators.
- Do not install packages.
- Do not run migrations.
- Do not run Prompt 08.
- Do not commit unless explicitly asked.
- Patch only Markdown files.
- Do not write MCP tokens, license data, headers, or local private paths into files.

## Read these files first

Read:

- `AGENTS.md`
- `prompts/README.md`
- `prompts/08-phase-02-taxonomy-tags-pinning-settings-media-foundation.md`
- `prompts/09-phase-02-admin-content-management.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/feature-map.md`
- `docs/phase-02/transcriptions-model-spec.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/phase-02/blueprints/09-admin-content-management-blueprint.md`
- `.ai/guidelines/transcriptions.md`
- `.ai/guidelines/tooling-quality.md`
- `.ai/guidelines/public-panel.md`
- `.ai/guidelines/settings-dashboard.md`
- `.ai/guidelines/taxonomy-tags.md`

Then read the sibling files in this folder:

1. `01-research-targets.md`
2. `02-research-output-file.md`
3. `03-podtext-admin-ux-decisions.md`
4. `04-files-to-patch.md`
5. `05-validation-final-report.md`

## High-level questions to answer

Research and decide:

1. How should `ContentItemResource` manage many child `Transcription` records?
2. Should `ContentItemResource` use a `TranscriptionsRelationManager`?
3. Should the item edit form and relation manager tabs be combined?
4. Should the form/content tab be customized?
5. Should the form tab appear before or after relation tabs?
6. Should `TranscriptionResource` remain as a standalone global Resource?
7. When should a dedicated `ManageRelatedRecords` page be preferred?
8. How should standalone Create/Edit pages redirect after save?
9. Should relation manager create/edit actions stay on the owner item page?
10. What other admin UX improvements from FilamentExamples should be added to Prompt 09?

## Required output

The final state should include:

- a new research file at `docs/research/filament-examples-admin-resource-relation-managers.md`;
- updates to the Prompt 09 blueprint;
- updates to Prompt 09;
- updates to relevant coverage/guidelines/feature-map files;
- no application feature changes.
