# AUTHZ Command Closure Handoff

Date: 2026-07-17

## Outcome

Laravel Simplifier audit `LS-20260717-AUTHZ-01`, approved Option
`AUTHZ-CLOSE-O1-DELETE-3`, is implemented on operator-approved baseline
`c7641ef55b3604988d40c7ed35a41b091ce9965a`.

The three dormant legacy-role migration commands are no longer reachable
through Artisan discovery. The retained `App\Auth\LegacyRoleBackfill`
namespace has no application caller outside itself. Legacy `users.role`, ranks,
Gates/macros, panel/Horizon/maintenance admission, and Users Resource
restrictions remain authoritative.

## Requirement classification

### Implemented

- Deleted only `authz:roles:analyze`, `authz:roles:backfill`, and
  `authz:roles:rollback` command classes.
- Replaced command-execution coverage with three command-absence assertions.
- Added one Pest architecture guard covering the complete retained migration
  namespace.
- Updated only the minimum current state, ledger, queue, prompt index, and this
  handoff.

### Already existed and preserved

- Shield 4.2.0 remains unregistered; `User` remains without `HasRoles`; package
  assignment checks remain disabled.
- Every dormant migration service and its direct service tests remain present.
- The five-role legacy Gate, panel, Users Resource, Horizon, and maintenance
  behavior remains unchanged.
- General Artisan discovery and all unrelated command classes remain unchanged.

### Not applicable

- No migration, dependency, configuration, translation, schema, data, queue,
  storage, or user-facing UI change was required.
- No FilaCheck-specific application finding was expected because no Filament
  source changed; the canonical FilaCheck gate still ran.

### Deferred / excluded

- AUTHZ1-D–I, package cutover, grants, role UI, ARCH1, SP3D, SP4, LOG1,
  MAINT-LW-UX1, MySQL rehearsal, production work, and recursive audit work stay
  cancelled or independently deferred.
- Step 5B Card Template Admin Preview UX remains the preferred next visible
  preparation, but was not started.

## Files changed

Deleted:

- `app/Console/Commands/AuthzAnalyzeLegacyRolesCommand.php`
- `app/Console/Commands/AuthzBackfillLegacyRolesCommand.php`
- `app/Console/Commands/AuthzRollbackLegacyRolesCommand.php`

Tests:

- `tests/Feature/AuthzLegacyRoleBackfillTest.php`
- `tests/Unit/AuthzCommandClosureArchitectureTest.php`

Closeout documentation:

- `docs/phase-02/authz-command-closure-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/research/settings-performance/10-pending-decision-question-queue.md`
- `prompts/README.md`

## Tests and commands

Read-only preflight inspection (`git status`, `git log`, `git diff`, `rg`,
`sed`, `awk`, `wc`) — PASS. The checkout was clean at the approved baseline,
the v1 prompt/version and current discovery path matched the audit, and no
unexpected writer or scoped change was present. Laravel Boost application and
installed-version documentation inspection also completed successfully.

Baseline, before edits:

- `php artisan test --compact tests/Feature/AuthzLegacyRoleBackfillTest.php` —
  PASS: 56 tests, 555 assertions.
- `php artisan test --compact tests/Feature/AuthzPackageFoundationTest.php tests/Feature/LegacyAuthorizationMatrixTest.php tests/Feature/PanelAuthHardeningTest.php tests/Feature/PublicMaintenanceModeTest.php`
  — PASS: 109 tests, 949 assertions.

Focused evidence, after edits:

- `php artisan test --compact tests/Unit/AuthzCommandClosureArchitectureTest.php tests/Feature/AuthzLegacyRoleBackfillTest.php`
  — PASS: 55 tests, 528 assertions.
- `php artisan test --compact tests/Feature/AuthzPackageFoundationTest.php tests/Feature/LegacyAuthorizationMatrixTest.php tests/Feature/PanelAuthHardeningTest.php tests/Feature/PublicMaintenanceModeTest.php`
  — PASS: 109 tests, 949 assertions.

Final requirements sweep:

- Changed-file and full-diff inspection — PASS.
- Application registration/caller search for the three deleted class names and
  signatures — PASS with expected no matches outside negative test assertions
  and historical documentation.
- Dormant namespace isolation and preservation-boundary inspection — PASS.
- `git diff --check` — PASS.

Canonical final gate, sequential on the final tracked state:

- `vendor/bin/pint --test` — PASS.
- `vendor/bin/filacheck` — PASS with zero issues.
- `npm run build` — PASS.
- First `php artisan test` — FAIL after 732 passing tests and 8,983
  assertions: Chromium could not register its macOS rendezvous port inside the
  command sandbox, closing the browser and cascading across all eight browser
  tests.
- Re-entered from `vendor/bin/pint --test`, then reran the complete ordered gate
  with `php artisan test` outside that browser restriction — PASS.

No test, formatter, generator, package command, migration, database probe,
storage write, queue operation, operational AUTHZ command, or external mutation
ran outside the listed test and canonical gate commands. The failed full-suite
attempt above is the only failure.

## Preservation, assumptions, and deviations

- The user explicitly approved descendant baseline
  `c7641ef55b3604988d40c7ed35a41b091ce9965a` as the replacement for the prompt's
  older `97627b0` HEAD wording. The planning commit and required restored
  Simplifier integrations remain ancestors; scoped implementation sources were
  unchanged after planning.
- No other assumption changed implementation scope.
- Tooling deviation: the final full-suite rerun used an unsandboxed command
  because the first run's Chromium process was denied the required macOS port.
  The command and test configuration were unchanged.

## Local Front Check Report

1. Open the local admin panel as a Super Admin; expect the dashboard and Users
   Resource to remain available.
2. Open the local admin panel as an Admin; expect the dashboard to remain
   available and the Users Resource to remain forbidden.
3. Open the local admin panel as a Moderator, Transcriber, and User; expect each
   account to remain denied panel admission.
4. Run `php artisan list` from the project directory; expect
   `authz:roles:analyze`, `authz:roles:backfill`, and
   `authz:roles:rollback` to be absent while unrelated application commands
   remain listed.

## Commit hash

`0be807051a3e43d08b57bd214278ce95c42bda1b`
