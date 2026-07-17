# AUTHZ Command Closure Planning Handoff

Date: 2026-07-17

## Outcome

The operator accepted the feature-first reset. A single v1 implementation
contract now defines the bounded AUTHZ closure without authorizing its
execution:

`prompts/pre-13-prompts/authz-command-closure-codex-prompt.md` v1.

Plan 20 selects deletion of only the three auto-discovered command classes.
General Artisan discovery, dormant migration services, package foundations,
legacy authority, dependencies, and data remain untouched.

## Source evidence

- Laravel's current `withCommands()` path auto-discovers concrete classes in
  `app/Console/Commands`; `routes/console.php` has no AUTHZ registration.
- The three command classes are the only application callers outside
  `App\Auth\LegacyRoleBackfill` of its analyzer, applier, rollback, and private
  artifact repository boundary.
- Existing tests already own the dormant-package and five-role legacy
  authorization regressions.
- The implementation needs one narrow Pest architecture guard, one inverted
  command-availability assertion, and removal of two obsolete command-execution
  tests. It does not need new architecture.

## Files in this planning set

- `docs/research/settings-performance/19-authz-complexity-reset-and-feature-first-master-plan.md`
- `docs/research/settings-performance/20-authz-command-closure-implementation-plan.md`
- `prompts/pre-13-prompts/authz-command-closure-codex-prompt.md`
- `docs/phase-02/authz-command-closure-planning-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/research/settings-performance/10-pending-decision-question-queue.md`
- `prompts/README.md`

All are Markdown. No historical report/handoff body, implementation file,
test, config, dependency, translation, prompt outside the new v1 contract, or
database changed.

## Checks

- `git diff --check` — PASS.
- Markdown-only changed-path audit — PASS.
- `git status --short` — only the eight authorized Markdown paths above before
  staging.

No PHP tests or implementation gates ran because this is planning only. No
operational AUTHZ command or database command ran.

## Implementation-start requirements

The operator must explicitly start
`prompts/pre-13-prompts/authz-command-closure-codex-prompt.md` v1 and name the
exact clean planning commit hash. The checkout must contain the commit with
subject `docs: plan authz command closure`, have no other writer, and satisfy
the prompt preflight and baseline tests.

That kickoff authorizes only the three-command closure and its focused tests,
minimum docs, canonical final gate, and two-commit closeout. It does not
authorize operational commands, database/MySQL work, AUTHZ1-D–I, ARCH1, SP3D,
MAINT-LW-UX1, Card Template UX, or an independent-audit chain.

## Next decision

Operator either starts the exact v1 prompt from the reported clean planning
commit or leaves the command-surface closure pending. Until implementation, the
three unused commands remain reachable. No implementation is authorized by
this handoff.

## Commit

This handoff belongs to the single docs-only commit with subject
`docs: plan authz command closure`. Its exact hash is reported in the planning
task final; no planning hash-stamp commit is authorized.
