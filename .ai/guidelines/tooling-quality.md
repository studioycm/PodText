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

## Related active docs

- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/research/filament-examples-phase-02.md`
