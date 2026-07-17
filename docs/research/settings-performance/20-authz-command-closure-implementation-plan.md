# AUTHZ Command Closure Implementation Plan

Date: 2026-07-17

Status: v1 implementation plan ready; implementation is not authorized by this
planning task

Controlling research:
`19-authz-complexity-reset-and-feature-first-master-plan.md`.

## Outcome

Make the dormant legacy-role migration utility non-operational by removing only
its three Artisan entry points. Preserve the current five-role legacy authority
and every dormant support asset. This is the accepted AUTHZ done-for-now
closure, not another migration-hardening cycle.

## Current-source findings

- `bootstrap/app.php` calls `withCommands()` without an explicit list. Laravel
  13 therefore discovers concrete command classes under
  `app/Console/Commands`.
- `routes/console.php` does not register an AUTHZ command.
- Each `authz:roles:*` name belongs to one auto-discovered class:

| Runtime name | Sole command class |
|---|---|
| `authz:roles:analyze` | `AuthzAnalyzeLegacyRolesCommand` |
| `authz:roles:backfill` | `AuthzBackfillLegacyRolesCommand` |
| `authz:roles:rollback` | `AuthzRollbackLegacyRolesCommand` |

- Outside `App\Auth\LegacyRoleBackfill`, those three command classes are the
  only application callers of `LegacyRoleBackfillAnalyzer`,
  `LegacyRoleBackfillApplier`, `LegacyRoleBackfillRollback`, and
  `PrivateArtifactRepository`.
- Existing tests already prove Shield is not registered, `User` does not use
  `HasRoles`, and the five legacy roles retain their Gate/panel/resource
  behavior.
- `AuthzLegacyRoleBackfillTest` contains two command-execution tests that become
  obsolete when the entry points are removed, plus one registration assertion
  that must be inverted.

## Chosen implementation

Delete exactly:

- `app/Console/Commands/AuthzAnalyzeLegacyRolesCommand.php`;
- `app/Console/Commands/AuthzBackfillLegacyRolesCommand.php`; and
- `app/Console/Commands/AuthzRollbackLegacyRolesCommand.php`.

Do not change `bootstrap/app.php`. Removing or narrowing general command
discovery would be broader than the accepted closure. Do not add replacement
registration conditions, feature flags, environment checks, stubs, aliases,
or hidden command names.

## Test changes

1. Update `tests/Feature/AuthzLegacyRoleBackfillTest.php`:
   - remove only `executes analyze backfill and rollback only through accepted
     command fingerprints` and `refuses immutable v1 artifacts with command
     exit two and no mutation`;
   - retain all direct dormant-service tests;
   - rename `keeps package assignments dormant and exposes only the three
     controlled commands` to describe command closure, and assert each of the
     three names is absent from `Artisan::all()`;
   - retain its dormant Shield and absent-`HasRoles` assertions.
2. Add `tests/Unit/AuthzCommandClosureArchitectureTest.php` using Pest's
   architecture expectation. Require the complete
   `App\Auth\LegacyRoleBackfill` namespace to be used only inside that same
   namespace:

   ```php
   arch('keeps the dormant legacy-role migration boundary isolated')
       ->expect('App\Auth\LegacyRoleBackfill')
       ->toOnlyBeUsedIn('App\Auth\LegacyRoleBackfill');
   ```

   This is the executable guard against any new application caller.
3. Do not rewrite existing package-foundation or legacy-matrix tests. Run them
   as regressions:
   - `AuthzPackageFoundationTest` for dormant Shield and absent `HasRoles`;
   - `LegacyAuthorizationMatrixTest` for the five-role Gate, panel, and
     resource matrix;
   - `PanelAuthHardeningTest` and `PublicMaintenanceModeTest` for adjacent
     five-role admission behavior.

## Exact preservation boundary

Preserve without modification:

- everything under `app/Auth/LegacyRoleBackfill`;
- Shield/Permission package, dependency, schema, configuration, catalogs, and
  manifests;
- `User`, `UserRole`, legacy ranks, Gates/macros, policies, panel access,
  Horizon, maintenance admission, and Users Resource restrictions;
- package dormancy: no Shield plugin, `HasRoles`, assignment, direct grant,
  role-management UI, or authority cutover;
- all data and all non-AUTHZ commands.

No operational AUTHZ command, local/development/production database, MySQL
rehearsal, migration, grant, backfill, rollback, dependency change, AUTHZ1-D–I,
ARCH1, SP3D, MAINT-LW-UX1, or independent-audit chain belongs to this slice.

## Execution and verification

Preflight must confirm a clean checkout at the exact operator-named planning
commit containing the v1 prompt. Baseline and final tests use the repository
test environment only; never run any `authz:roles:*` command.

Run focused baseline tests before edits, then focused final tests after edits:

```bash
php artisan test --compact tests/Feature/AuthzLegacyRoleBackfillTest.php
php artisan test --compact tests/Feature/AuthzPackageFoundationTest.php tests/Feature/LegacyAuthorizationMatrixTest.php tests/Feature/PanelAuthHardeningTest.php tests/Feature/PublicMaintenanceModeTest.php
php artisan test --compact tests/Unit/AuthzCommandClosureArchitectureTest.php tests/Feature/AuthzLegacyRoleBackfillTest.php
php artisan test --compact tests/Feature/AuthzPackageFoundationTest.php tests/Feature/LegacyAuthorizationMatrixTest.php tests/Feature/PanelAuthHardeningTest.php tests/Feature/PublicMaintenanceModeTest.php
```

Before final gates, inspect the whole diff and use read-only source searches to
confirm the three command files/names have no application registration or
caller while the dormant namespace remains present. Then run the canonical
final gate once on the final tracked implementation state:

1. `vendor/bin/pint --test`
2. `vendor/bin/filacheck`
3. `npm run build`
4. full `php artisan test` last

After any tracked change, restart at Pint. Never parallelize or interrupt the
full suite. Do not use `filacheck --fix`.

## Documentation and closeout

Create `docs/phase-02/authz-command-closure-handoff.md` with requirement
classification, changed files, tests, every command/result, gate results,
numbered imperative Local Front Check steps, preservation/deferred boundaries,
and `## Commit hash` set to `pending`.

Update only current state, the mini-step ledger, queue, and prompt index as
needed. Close with exactly:

1. implementation/tests/docs commit:
   `fix: withhold authz migration commands`;
2. immediate Markdown-only hash stamp:
   `docs: backfill authz command closure hash`.

Do not push. Stop after a clean status and report both hashes.

## Acceptance checklist

- [ ] Only the three AUTHZ command classes are removed from application code.
- [ ] All three command names are unavailable through Artisan discovery.
- [ ] No application caller reaches the dormant migration-service boundary.
- [ ] Shield remains unregistered and `User` remains without `HasRoles`.
- [ ] The existing five-role legacy authorization matrix remains green.
- [ ] Dormant services, package/schema/config/catalogs, dependencies, and data
  are unchanged.
- [ ] No forbidden adjacent track or recursive audit work appears.
- [ ] The canonical final gate and two-commit closeout are complete.
