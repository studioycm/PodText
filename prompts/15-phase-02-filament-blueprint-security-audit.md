# Prompt 15: Phase 02 Filament Blueprint Security Audit

## Goal

Audit completed Phase 02 implementation for Filament, public visibility, media, import/export, Markdown, and dashboard risks.

## Current state assumptions

- Prompts 07 through 14 are complete.
- This is audit-only unless specific fixes are approved.

## Docs to read

- `AGENTS.md`
- all Phase 02 specs
- `docs/phase-02/tooling-and-quality-gates.md`

## Blueprint and guidelines

- `docs/phase-02/blueprints/15-filament-security-audit-blueprint.md`
- all relevant active `.ai/guidelines/*.md`

## Scope

- Review Filament Resources, Pages, Widgets, Imports, Exports, public Livewire components, Blade components, tests, and quality output.

## Out of scope

No broad refactors, no new product features, no package installs.

## Package/tool assumptions

Use Boost docs when available, FilamentExamples MCP when checking patterns, and full FilaCheck/FilaCheck Pro.

## Implementation plan

1. Run full quality gate.
2. Review security/visibility rules against blueprint.
3. Report findings with file/line references.
4. Fix only approved or obvious low-risk issues if this prompt explicitly authorizes fixes.

## Acceptance criteria

Security audit findings are clear, prioritized, and grounded in code/tests.

## Required tests

Run existing full suite. Add tests only for approved fixes.

## Required quality gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Final report format

Findings first, then test/quality results, then residual risk.

## Commit behavior

Commit only if fixes are made, quality gate passes, and the user asks for a commit.
