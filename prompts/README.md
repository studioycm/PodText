# Prompt Index

Run prompts sequentially. The current active sequence is:

1. `07-phase-02-transcriptions-model-revision.md` - already run, committed, and locally migrated; keep for historical/reference unless explicitly asked to rerun.
2. `08-phase-02-taxonomy-tags-pinning-settings-media-foundation.md` - next implementation prompt after human review of the post-migration state sync.
3. `09-phase-02-admin-content-management.md`
4. `10-phase-02-import-export.md`
5. `11-phase-02-public-homepage-search.md`
6. `12-phase-02-media-embed-item-page-parser.md`
7. `13-phase-02-dashboard-metrics.md`
8. `14-phase-02-viewer-studio-future-plan.md`
9. `15-phase-02-filament-blueprint-security-audit.md`

Historical prompts are under `prompts/archive/` and are not active instructions.

## Pre-Prompt-08 research note

`prompts/admin-relation-manager-research/00-overview.md` is a docs-only refinement task for Prompt 09 admin Resource and Relation Manager UX. It does not change the active prompt order. Prompt 08 remains the next implementation prompt after review.

## Blueprint usage rule

Every implementation prompt must treat its referenced blueprint as the detailed implementation contract.

- The prompt defines scope, sequencing, out-of-scope boundaries, and final quality gate.
- The blueprint defines concrete fields, migrations, relationships, casts, validation, Filament Resources/Pages/Actions/Widgets/Importers/Exporters, form schemas, table columns, filters, tests, edge cases, and security rules.
- If the prompt body is shorter than the blueprint, follow the blueprint.
- If the blueprint conflicts with the active prompt, Phase 02 specs, `AGENTS.md`, or current code, stop and report the conflict before implementing.
- Do not omit blueprint requirements unless they are explicitly marked optional, already implemented, impossible with the current code, or superseded by a newer active spec.
- Every implementation final report must include a Blueprint completion checklist.
