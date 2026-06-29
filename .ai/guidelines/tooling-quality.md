# Tooling Quality Guideline

## Purpose

Keep AI/tooling quality gates consistent across planning and implementation tasks.

## Preferred architecture

Every implementation prompt uses Boost where available, reads its blueprint, checks FilamentExamples for relevant code patterns, and runs the full quality gate.

## Do

- Retry Laravel Boost MCP tools before implementation.
- Read the relevant blueprint first.
- Use FilamentExamples MCP before Filament code.
- Run full final quality gate.
- Record FilaCheck/FilaCheck Pro output.
- Preserve cross-cutting form, locale, and dashboard requirements from active specs/guidelines.

## Do not

- Do not claim Boost was used if MCP calls fail.
- Do not run `filacheck --fix` without explicit approval.
- Do not write secrets, tokens, licenses, Composer auth, MCP headers, or machine paths to tracked files.

## Testing rules

- Each implementation prompt must add/update Pest tests.
- Final implementation gate:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Security rules

- Review diffs for secrets before final report.
- Keep `.env`, MCP config, Composer auth, and license files untouched.

## FilaCheck / FilaCheck Pro notes

- Treat remaining violations as blockers in implementation prompts.
- Local iteration may use `vendor/bin/filacheck --dirty`.
- Final verification uses full `vendor/bin/filacheck`.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields should auto-generate from title/name fields but allow manual override.
- Technical fields must have helper text, hints, or descriptions.
- Date/date-time UI should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Admin dashboard widgets should include available editorial metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/research/filament-examples-phase-02.md`
