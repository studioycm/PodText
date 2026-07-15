# Filament Audit Skills Installation Plan

> **Historical tooling-plan notice — 2026-07-16:** This remains the installation
> record. Its SP3 audit disposition is superseded by
> `docs/research/settings-performance/07-sp3d-pre-research.md` after ARCH1-A–S
> were approved in the active operator task.

Date: 2026-07-15

## Plan

1. Revise both supplied skills into concise, version-aware packages. Preserve
   their routed rules/references, add only the resources needed for reliable
   Filament 5 and Livewire 4 use, and generate matching `agents/openai.yaml`
   metadata.
2. Install canonical project packages under `.ai/skills/`, expose them through
   tracked relative symlinks under `.agents/skills/`, `.claude/skills/`, and
   `.junie/skills/`, and install identical global Codex copies under the
   configured global skills directory. Do not modify dependencies or
   application code.
3. Add an evergreen repository instruction that invokes the forms UX skill for
   Filament form work and the performance skill for measured Filament/Livewire
   performance work, while retaining Boost, FilamentExamples, and repository
   gate requirements.
4. Validate every installed skill with the skill-creator validator, compare the
   project/global trees byte-for-byte, and scan examples for known Filament 5
   incompatibilities.
5. Apply both skills to the current SP3 files and write a findings-first audit
   with verified/inferred labels and exact file references. Do not implement
   SP3D or mutate the local development database.
6. Run Markdown/YAML checks, `git diff --check`, and report the uncommitted
   working tree for operator review. No commit or push is authorized.

## Acceptance criteria

- Both skills are discoverable from the Agents, Claude, Junie, and global Codex
  skill roots while the project maintains one canonical package per skill.
- Each skill has valid `SKILL.md` frontmatter and matching UI metadata.
- Canonical project and global copies are identical, and all three project
  discovery paths resolve to the canonical packages.
- Examples use Filament 5 namespaces and current APIs; known obsolete or
  misleading examples are absent.
- Performance guidance includes Livewire 4 and honest browser-versus-component
  measurement boundaries.
- The SP3 audit identifies only evidence-backed findings and preserves the
  SP3D/operator decision boundary.
