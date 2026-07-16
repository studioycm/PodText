# Prompt Index

Run prompts sequentially. The current active sequence is:

1. `07-phase-02-transcriptions-model-revision.md`
2. `08-phase-02-taxonomy-tags-pinning-settings-media-foundation.md`
3. `09-phase-02-admin-content-management.md`
4. `10-phase-02-import-export.md`
5. `11-phase-02-public-homepage-search.md`
6. `12-phase-02-media-embed-item-page-parser.md`
7. `13-phase-02-dashboard-metrics.md`
8. `14-phase-02-viewer-studio-future-plan.md`
9. `15-phase-02-filament-blueprint-security-audit.md`

For current completion/progress state, see `docs/phase-02/current-project-state.md`.

Historical prompts are under `prompts/archive/` and are not active instructions.

## AUTHZ1 pre-Prompt-13 contracts

- `pre-13-prompts/authz1-foundation-codex-prompt.md` v3 is complete; its
  canonical implementation and hash-stamp commits are recorded in the AUTHZ1
  foundation handoff.
- `pre-13-prompts/maintenance-livewire-enforcement-audit-codex-prompt.md` v1 is
  complete as a Markdown-only effects audit. It authorized no remediation.
- `pre-13-prompts/authz1c-analyzer-backfill-codex-prompt.md` v1 was executed as
  the AUTHZ1-C implementation contract. Its implementation and two-commit
  closeout are complete; the independent audit's findings are addressed by the
  separate remediation contract below.
- `pre-13-prompts/authz1c-audit-remediation-codex-prompt.md` v1 was executed as
  the R-01–R-05 remediation contract. Local implementation and two-commit
  closeout are complete; independent remediation review is pending and
  AUTHZ1-D–I remain blocked and unstarted.

## Pre-Prompt-08 research note

`prompts/admin-relation-manager-research/00-overview.md` was a docs-only refinement task for Prompt 09 admin Resource and Relation Manager UX. It did not change the active prompt order.

## Blueprint usage rule

Every implementation prompt must treat its referenced blueprint as the detailed implementation contract.

- The prompt defines scope, sequencing, out-of-scope boundaries, and final quality gate.
- The blueprint defines concrete fields, migrations, relationships, casts, validation, Filament Resources/Pages/Actions/Widgets/Importers/Exporters, form schemas, table columns, filters, tests, edge cases, and security rules.
- If the prompt body is shorter than the blueprint, follow the blueprint.
- If the blueprint conflicts with the active prompt, Phase 02 specs, `AGENTS.md`, or current code, stop and report the conflict before implementing.
- Do not omit blueprint requirements unless they are explicitly marked optional, already implemented, impossible with the current code, or superseded by a newer active spec.
- Every implementation final report must include a Blueprint completion checklist.

## Completion rule

After successful implementation, update `docs/phase-02/current-project-state.md` before the final commit. Patch `feature-map.md`, `answers-coverage-matrix.md`, specs, blueprints, guidelines, and this README only when stable requirements, scope, ownership, or durable lessons changed.
