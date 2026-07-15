# Audit Output

## Findings First

For reviews, report findings first and order them by severity. Keep summaries short and secondary.

Use this shape:

```markdown
**Findings**
- High: `path/to/File.php:42` - Short title.
  Observed: concrete code pattern.
  Impact: why it is slow in Filament/Livewire/Laravel.
  Fix: specific change.
  Confidence: verified or inferred.
```

Use nested detail only when it prevents ambiguity. For small audits, prose with one or two findings is acceptable.

## Severity

- High: likely N+1, unbounded query, heavy polling, page-load query fan-out, or upload behavior that can break production usage.
- Medium: inefficient pattern that becomes material with normal growth, such as missing indexes for common filters or uncached dashboard stats.
- Low: cleanup or guardrail that improves future scalability but is not currently tied to a clear hot path.

## Confidence

- Verified: visible directly in code, schema, logs, query output, tests, or runtime evidence.
- Inferred: depends on production data size, traffic, user behavior, or configuration not present in the workspace.

## Suggested Verification

Include only relevant checks:

- Run the affected Pest test file or add a focused test if behavior changes are requested.
- Compare query counts before and after when fixing N+1 or per-row callback issues.
- Smoke test the affected Filament page in the browser if UI behavior changes.
- Run `vendor/bin/pint --dirty --format agent` after PHP edits, not for audit-only reports.

## Measurement Boundaries

Name the measurement plane for every numeric claim:

- Server/component: response bytes, queries, settings reads, schema-build time,
  Livewire snapshot/state bytes, parsed HTML elements, wrappers, controls.
- Browser: hydrated DOM, teleported modal DOM, listeners, heap, request count,
  transferred bytes, navigation, Back dialogs, console errors, TTFB.

Use the same fixture, package version, viewport, server/cache/profile state, and
runner when comparing samples. Do not turn one developer-laptop timing into a
universal normal-suite cap.

## Clean Audit

If no issues are found, say so clearly and mention residual risk, such as missing production data volume or no runtime query trace.
