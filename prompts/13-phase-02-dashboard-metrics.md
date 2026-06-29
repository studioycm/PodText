# Prompt 13: Phase 02 Dashboard Metrics

## Goal

Implement lightweight editorial dashboard metrics.

## Current state assumptions

- Prompts 07 through 12 are complete and committed.

## Docs to read

- `AGENTS.md`
- `docs/phase-02/dashboard-metrics-spec.md`
- `docs/phase-02/tooling-and-quality-gates.md`

## Blueprint and guidelines

- `docs/phase-02/blueprints/13-dashboard-metrics-blueprint.md`
- `.ai/guidelines/settings-dashboard.md`
- `.ai/guidelines/tooling-quality.md`

## Scope

Stats and table widgets for editorial counts and warning lists.

## Out of scope

No analytics, search logging, observability, retry dashboards, custom activity logs, or public UI changes.

## Package/tool assumptions

Use Boost docs when available and FilamentExamples dashboard/widget examples.

## Implementation plan

1. Write widget render/count tests.
2. Scaffold widgets.
3. Implement StatsOverview counts.
4. Implement recent items and warning table widgets.
5. Register widgets on admin dashboard.

## Acceptance criteria

Authenticated admins see accurate editorial metrics and warning lists with Resource links.

## Required tests

Widget rendering, count accuracy, warning lists, admin-only access, Resource links.

## Required quality gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Final report format

Report files changed, tests added, commands/results, assumptions, deferred issues, and FilaCheck output.

## Commit behavior

Commit only after the full quality gate passes.
