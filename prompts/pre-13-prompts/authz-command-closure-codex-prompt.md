# AUTHZ Command Closure Implementation Prompt

Prompt version: v1 — 2026-07-17. If the operator kickoff does not name this
exact file and version, stop before any command or edit and report the mismatch.

## Task contract

Complete only the accepted AUTHZ done-for-now closure: remove the three dormant
legacy-role migration commands from the Artisan runtime and prove the existing
legacy authority is unchanged. Do not implement a replacement architecture or
continue AUTHZ migration work.

Controlling contracts, in order after this prompt:

1. `docs/research/settings-performance/20-authz-command-closure-implementation-plan.md`;
2. `docs/research/settings-performance/19-authz-complexity-reset-and-feature-first-master-plan.md`;
3. `docs/phase-02/authz-command-closure-planning-handoff.md`; and
4. current source, installed Laravel 13 and Pest source, and existing tests.

Report 19 is the required research note. Plan 20 makes the implementation
choice. Do not start a second research or design cycle.

## Mandatory preflight

Before work, read in repository-mandated order and in full:

1. `AGENTS.md`;
2. `docs/phase-02/ai-development-lessons.md`;
3. `docs/phase-02/current-project-state.md`;
4. the head and AUTHZ row of the mini-step ledger;
5. the newest two handoffs, including the planning handoff;
6. this exact v1 prompt, plans 19 and 20, the queue, and prompt index;
7. `bootstrap/app.php`, `routes/console.php`, the three command classes, all of
   `app/Auth/LegacyRoleBackfill`, and all application references to them;
8. `AuthzLegacyRoleBackfillTest`, `AuthzPackageFoundationTest`,
   `LegacyAuthorizationMatrixTest`, `PanelAuthHardeningTest`, and
   `PublicMaintenanceModeTest`; and
9. repository `laravel-best-practices` and `pest-testing` skills.

Require a clean `main` at the exact operator-named planning commit with subject
`docs: plan authz command closure`. The exact hash must match the kickoff and
must contain this v1 prompt and plan 20. Confirm no other repository writer and
no unexpected PHP, Blade, migration, test, config, dependency, or app dirt.
Stop on mismatch. Work sequentially in the existing checkout; no branch,
worktree, subagent, or parallel task.

## Baseline

Run sequentially before edits:

```bash
php artisan test --compact tests/Feature/AuthzLegacyRoleBackfillTest.php
php artisan test --compact tests/Feature/AuthzPackageFoundationTest.php tests/Feature/LegacyAuthorizationMatrixTest.php tests/Feature/PanelAuthHardeningTest.php tests/Feature/PublicMaintenanceModeTest.php
```

The repository test canaries must show the test environment and SQLite
`:memory:`. Stop on failure. Never invoke `authz:roles:analyze`,
`authz:roles:backfill`, or `authz:roles:rollback` directly or against a
non-test database; the pre-edit baseline may exercise them only through
test-owned in-memory fixtures. Never access or mutate the local development or
production database, and do not run a MySQL rehearsal.

## Exact implementation

Delete exactly:

- `app/Console/Commands/AuthzAnalyzeLegacyRolesCommand.php`;
- `app/Console/Commands/AuthzBackfillLegacyRolesCommand.php`; and
- `app/Console/Commands/AuthzRollbackLegacyRolesCommand.php`.

This is the smallest mechanism because Laravel currently auto-discovers these
files through the existing general `withCommands()` path. Do not change
`bootstrap/app.php`, `routes/console.php`, or command discovery. Do not add a
replacement registration condition, flag, environment gate, stub, alias, or
hidden command.

Update `tests/Feature/AuthzLegacyRoleBackfillTest.php` only as follows:

- delete `executes analyze backfill and rollback only through accepted command
  fingerprints` and `refuses immutable v1 artifacts with command exit two and
  no mutation`;
- keep every direct dormant-service test;
- rename `keeps package assignments dormant and exposes only the three
  controlled commands` to describe command closure;
- assert each of the three command keys is absent from `Artisan::all()`; and
- retain its Shield-dormancy and absent-`HasRoles` assertions.

Add `tests/Unit/AuthzCommandClosureArchitectureTest.php`. Use Pest's
`toOnlyBeUsedIn()` architecture expectation on the complete
`App\Auth\LegacyRoleBackfill` namespace to prove it is used only inside that
same namespace. This must guard every dormant migration class, not only the
four classes currently reached by the commands. Use this exact expectation:

```php
arch('keeps the dormant legacy-role migration boundary isolated')
    ->expect('App\Auth\LegacyRoleBackfill')
    ->toOnlyBeUsedIn('App\Auth\LegacyRoleBackfill');
```

Do not duplicate or rewrite the existing package-dormancy or five-role legacy
matrix tests. They are required unchanged regressions.

## Preservation and hard stops

Do not modify dormant support services, package/schema/config/catalogs,
dependencies, data, `User`, `UserRole`, roles/ranks, Gates/macros, policies,
panel/Horizon/maintenance access, Users Resource behavior, or any non-AUTHZ
command. Shield remains unregistered, `User` remains without `HasRoles`, and
legacy `users.role` remains authoritative.

Do not add permissions, assignments, grants, direct grants, cutover, role UI,
extra panels, production migration machinery, or compatibility behavior. Do
not enter AUTHZ1-D–I, ARCH1, SP3D, MAINT-LW-UX1, MySQL work, operational AUTHZ
work, or an independent-audit chain. Stop for operator direction if the closure
cannot fit this exact boundary.

## Required evidence

After edits, run sequentially:

```bash
php artisan test --compact tests/Unit/AuthzCommandClosureArchitectureTest.php tests/Feature/AuthzLegacyRoleBackfillTest.php
php artisan test --compact tests/Feature/AuthzPackageFoundationTest.php tests/Feature/LegacyAuthorizationMatrixTest.php tests/Feature/PanelAuthHardeningTest.php tests/Feature/PublicMaintenanceModeTest.php
```

The final evidence must prove:

- all three commands are absent from `Artisan::all()`;
- no application caller reaches the dormant migration boundary;
- Shield remains unregistered and `User` still lacks `HasRoles`;
- the current five-role Gate/panel/resource/Horizon/maintenance behavior is
  unchanged; and
- dormant services continue to pass their direct tests.

Use a read-only requirements sweep to confirm the three deleted class names and
command signatures have no application registration/caller. An expected
no-match search is success, not a reason to add code. Inspect every changed
file and classify all prompt and plan requirements.

## Documentation

Create `docs/phase-02/authz-command-closure-handoff.md`. Include:

- requirement classification;
- exact files changed and tests added/updated;
- every command and result, including failures;
- focused and final gate outcomes;
- preservation, assumptions, deviations, and deferred work;
- numbered imperative Local Front Check steps; and
- `## Commit hash` with `pending` before the implementation commit.

Update only the minimum current state, ledger, queue, and prompt index. Do not
rewrite reports/handoffs 12–19, older plans, blueprints, or unrelated docs.

## Canonical final gate and closeout

On the final tracked state, run sequentially and exactly:

1. requirements sweep;
2. `vendor/bin/pint --test`;
3. `vendor/bin/filacheck`;
4. `npm run build`;
5. full `php artisan test` last.

After any tracked file change, re-enter from Pint. Run the full suite once green
on the final code state; never parallelize or interrupt it. Do not run
`vendor/bin/filacheck --fix`.

After green gates:

1. commit implementation/tests/docs/handoff with pending hash as
   `fix: withhold authz migration commands`;
2. immediately stamp that implementation hash into the handoff and ledger and
   commit only those Markdown files as
   `docs: backfill authz command closure hash`;
3. verify a clean status and report both hashes; and
4. do not push.

Hard stop. Do not continue to Card Template UX or any other feature in this
run. No separate independent audit is required unless the actual closure diff
exposes a concrete code risk and the operator authorizes it.
