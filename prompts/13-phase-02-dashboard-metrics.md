# Prompt 13: Phase 02 Dashboard Metrics

## Goal

Implement lightweight editorial dashboard metrics.

## Current state assumptions

- Prompts 07 through 12 are complete and committed.

## Preflight and carry-forward rules

- Run git status/log preflight before implementation and stop on unexpected app-code dirt.
- Confirm Prompt 12 is complete before starting; do not backfill public homepage/search or item page scope in this prompt.
- Preserve Prompt 10 native import/export behavior and Prompt 11/12 public visibility rules while adding dashboard-only admin metrics.
- Do not add analytics, search logging, observability dashboards, retry dashboards, custom activity logs, or public UI changes.
- After successful implementation and passing gates, update active state Markdown before the final commit.

## Docs to read

- `AGENTS.md`
- `docs/phase-02/dashboard-metrics-spec.md`
- `docs/phase-02/tooling-and-quality-gates.md`

## Blueprint and guidelines

- `docs/phase-02/blueprints/13-dashboard-metrics-blueprint.md`
- `.ai/guidelines/settings-dashboard.md`
- `.ai/guidelines/tooling-quality.md`

## Blueprint contract

The blueprint file named above is the detailed implementation contract for this prompt.

Before changing code:

1. Read the entire blueprint.
2. Summarize the blueprint sections that apply to this prompt.
3. Compare the blueprint against the current repository state.
4. If the blueprint conflicts with the active prompt, Phase 02 specs, `AGENTS.md`, or current code, stop and report the conflict before implementing.
5. If the prompt body is shorter than the blueprint, follow the blueprint details.
6. Do not omit blueprint fields, relationships, constraints, Filament components, tests, or quality checks unless the blueprint marks them optional or the current code makes them impossible.
7. In the final report, include a "Blueprint completion checklist" with:
   - implemented;
   - already existed;
   - deferred by blueprint;
   - not applicable;
   - blocked.

The dashboard metrics blueprint is the authority for widget class names, metrics, availability staging, table columns, warning modes, date formatting, tests, and Resource URL links.

- Dashboard metrics that are already available from the current schema should be shown.
- Metrics that depend on Prompt 08 or later schema should be implemented only when the schema exists and otherwise documented in the final report.

## Scope

Stats and table widgets for editorial counts and warning lists.

Metrics that are already available from the current schema should be shown as admin dashboard widgets as early as this prompt. If some metrics require schema from Prompt 08 or later, mark them as "available after Prompt X" in implementation notes and tests.

Initial dashboard widgets should include all currently available editorial counts. Extend widgets for Prompt 08+ schema where the schema exists.

Dashboard date displays should use Israel/Hebrew day-first formatting:

- dates: `dd/mm/yyyy`;
- date-times: `dd/mm/yyyy HH:mm`;
- UI timezone: `Asia/Jerusalem`.

Dashboard widgets should not poll unless needed. Dashboard links should use Filament Resource URLs.

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

Report files changed, tests added, commands/results, assumptions, deferred issues, FilaCheck output, and the Blueprint completion checklist.

## Commit behavior

Commit only after the full quality gate passes.
