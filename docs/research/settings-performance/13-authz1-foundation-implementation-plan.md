# AUTHZ1 Foundation Implementation Plan

Date: 2026-07-16
Contract: AUTHZ1 foundation prompt v3
Boundary: reversible package/schema/catalog expansion only; legacy authority
remains active
Status: implemented; canonical final gate and commit sequence remain

## 1. Preflight and dependency checkpoint

1. Record status, exact HEAD/history, strict Composer validation/audit, and the
   sequential SQLite `:memory:` Roles/Gates, Horizon, and maintenance baseline.
2. Run the exact approved Composer dry-run. Stop on any package/version outside
   Shield 4.2.0, Permission 7.3.0, and transitive Plugin Essentials 1.2.1, or
   on any update/removal.
3. Install the two exact direct constraints with `--no-scripts`,
   `--no-interaction`, and `--minimal-changes`. Review `composer.json` and
   `composer.lock` before running narrow Laravel package discovery.
4. Run Composer's supported package-discovery command only if the no-scripts
   install requires it. Do not run migrations or setup/generation commands.

Files: `composer.json`, `composer.lock`.

## 2. Installed source and safe publishing checkpoint

Inspect exact installed service providers, Composer constraints, publish tags,
config, migration stub, plugin/Role Resource registration, command classes and
signatures, destructive-command prohibition, guard/cache behavior, teams, and
schema/index/FK definitions.

Publish only:

- `config/filament-shield.php` via `filament-shield-config`;
- `config/permission.php` via `permission-config`;
- `database/migrations/<timestamp>_create_permission_tables.php` via
  `permission-migrations`.

Do not use `--force`. Review every published line. Keep teams false, default
guard `web`, and change the package cache key to `podtext.permission.cache`
while retaining the existing application cache prefix convention. Do not
publish Shield Role Resources, translations, policies, or seeders.

Files: `config/filament-shield.php`, `config/permission.php`, one published
Permission migration.

## 3. Owned immutable authorization definitions

Create `app/Auth/AbilityDefinition.php` as a final readonly data object with the
ten canonical fields in prompt order and a deterministic array serializer.

Create `app/Auth/AbilityCatalog.php` with:

- `VERSION = 'AUTHZ1-2026-07-16'` and the literal expected hash;
- explicit ordered group/key definitions for all 135 entries;
- `definitions()`, `keys()`, `canonicalPayload()`, `canonicalJson()`, and
  `hash()` APIs;
- deterministic label/description keys, sensitive/delegable rules, protected
  domains, and `web` guard exactly matching v3.

Create `app/Auth/RoleDefinition.php` and `app/Auth/RoleCatalog.php` with exactly
the five metadata rows in enum order.

Create `app/Auth/CompatibilityGrantManifest.php` with explicit ordered Admin
89-literal grants, Super mapped to the deployed catalog, and explicit empty
arrays for Moderator/Transcriber/User. It is declarative only and exposes no
sync/write API.

Create `app/Auth/AuthorizationFoundationValidator.php` with separately testable
ability, role/grant, and translation validation. Throw `LogicException` before
returning an accepted result on grammar, key/case collision, duplicate order,
guard, role/grant, or bilingual metadata failure. Do not call the validator
from current Gates/policies.

## 4. Bilingual metadata

Create `lang/en/authz.php` and `lang/he/authz.php` with exact group labels and
the 135 per-ability label/description paths. Small locale-owned builder closures
may eliminate mechanical repetition, but the returned arrays must expose every
exact key independently in nested arrays so Laravel resolves dotted paths such
as `authz.abilities.panel.admin.access.label`; literal dotted PHP array keys are
not allowed. Values return nonempty native-language strings and never use
locale fallback. Hebrew is primary and clear sensitive/security effects must be
described.

## 5. Shield non-authoritative Admin boundary

Preferred safe result: do not add `FilamentShieldPlugin` to either panel when
registration automatically exposes the Role Resource route or requires
`HasRoles`. `app/Providers/Filament/PublicPanelProvider.php` remains unchanged.
If installed source proves the Role Resource route can be entirely absent while
the plugin is registered, register it only in
`app/Providers/Filament/AdminPanelProvider.php`; navigation hiding alone is not
sufficient. Otherwise record Shield as installed/configured but unregistered
on every panel and test both panel registries/routes. Do not create an unused
constants-only adapter class. Explicitly inspect and disable/avoid package-owned
Super Gate interception, panel-user behavior, RolePolicy registration,
generation, and management defaults. This is an installed-source hard
checkpoint, not an architectural guess.

Update `app/Providers/AppServiceProvider.php` only to invoke Shield's supported
production destructive-command prohibition. Do not edit current Gate/policy,
panel-admission, or model behavior.

## 6. Independent fixtures and catalog tests

Create `tests/Fixtures/Authz/authorization-foundation.php` containing literal
groups/keys, full-entry expectations, version/hash, exact role metadata, and
explicit Admin allow/deny arrays. It must not import or reflect production
catalog/manifest classes.

Create `tests/Feature/AuthzFoundationCatalogTest.php` for exact 135/unique keys,
field ordering, canonical JSON/hash, immutable copies, role metadata, grant
snapshots, every named invalid fixture, exact HE/EN key-set and locale-specific
presence, nonempty/no-key-return/no-fallback behavior, and the duplicate PHP
translation-key scan.

## 7. Package/schema and Shield boundary tests

Create `tests/Feature/AuthzPackageFoundationTest.php`. Configure a
dedicated temporary SQLite `:memory:` connection, switch only inside `try/finally`,
require the single published migration, call its object `up()`, reverse
`down()`, and `up()` again, and assert package tables, columns, unique/index/FK
shape after both ups plus absence after down. Never call broad Artisan rollback
commands.

The same file proves exact Shield/Permission config, teams false, `web` guard/
cache key, no `HasRoles`/`roles()`/`permissions()` on `User`, no panel management
route/navigation/plugin leakage, no generated policies, guarded production
commands, and no catalog/assignment sync.

## 8. Five-role legacy no-regression tests

Add one shared five-role Pest dataset in `tests/Pest.php` or a dedicated loaded
dataset file, preserving enum order and expected ordinary/User-only access.

Create `tests/Feature/LegacyAuthorizationMatrixTest.php` for Admin panel,
ordinary Author Resource direct URL/Livewire, Admin Tools direct URL/Livewire,
User Resource index/edit direct URL and List/Edit Livewire/action/save, the
`super-admin` Gate, and both single/multi `multi-transcription` minimums. Test
the User table's `EditAction` record path separately from `EditUser::save`, and
assert denied mounts, record actions, and saves preserve the target role. Every
denied mutation asserts persisted state unchanged. Run the full surface matrix
again with deliberately inserted package role and permission definition rows
but no assignments; every result must remain identical and assignment tables
must remain empty.

Update `tests/Feature/PanelAuthHardeningTest.php` for the exact five-role
Horizon dataset. Update `tests/Feature/PublicMaintenanceModeTest.php` for all
five roles in initial HTTP and persistent Livewire update paths. Preserve all
existing self/final-Super and feature-mode regressions.

## 9. Operator-authorized maintenance/Livewire correction

The prompt-only authorization commit is `d084d9b`. Before editing, collect one
read-only security/runtime review and one read-only test/effects review and
resolve contradictions against Boost guidance, installed Livewire/Laravel
source, and the executed real-endpoint failure.

Update only `app/Http/Middleware/RenderMaintenanceMode.php` as follows:

1. build the same maintenance response only after the existing enabled and
   Super/Admin bypass checks;
2. when the denied request is an actual Livewire update, terminate using
   Laravel's `HttpResponseException` carrying that exact response;
3. return the response normally for every non-Livewire request;
4. do not alter the public panel middleware list, update URI, Livewire vendor
   source, renderer, role decision, components, routes, or maintenance forms.

Extend `tests/Feature/PublicMaintenanceModeTest.php` with a helper that captures
the actual `public.content-item-browser` child snapshot from rendered public
HTML and posts it to `app('livewire')->getUpdateUri()` with the real header.
For the exact five-role dataset prove enabled-mode Super/Admin 200 and denied
roles' exact 503 body/`Retry-After`, absence of a Livewire effects envelope,
and no persisted relevant mutation. Add a disabled-mode five-role update test
proving 200. Preserve initial HTTP, raw/styled output, Admin routes, forms/mail,
CSRF, cache, Hebrew, and RTL tests.

Record effect planes separately: directly tested single stale snapshot and
five-role server response; source-proven persistent-middleware/exception
mechanics; inferred bundled/lazy/polling equivalence where justified; and
browser UX/log-noise/multi-component uncertainty deferred to the committed v1
audit prompt. Do not claim browser behavior without observation.

## 10. Documentation, requirements, and final gates

Update the research note with exact solve/install/publish/source/test evidence
and inference separated. Create `docs/phase-02/authz1-foundation-handoff.md`
with complete classification, command log, files/tests, rollback, limitations,
gate results, and imperative numbered Local Front Check steps.

Update:

- `docs/phase-02/current-project-state.md`;
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`;
- `docs/research/settings-performance/10-pending-decision-question-queue.md`.

The restart point is: foundation complete, legacy authority active, AUTHZ1-C
not started pending a new accepted slice. Do not mark AUTHZ1 complete or start
ARCH1/SP3D.

Run requirements sweep, then exactly Pint, FilaCheck, npm build, and the full
test suite last. After any file change, restart from Pint. Never parallelize or
interrupt the full suite.

## 11. Reversibility and commits

Rollback before cutover is code/config/package migration removal plus the
published package migration `down()` on a controlled target; no legacy data or
authority projection is involved because package assignments remain empty.
This plan does not authorize a local/dev/prod migration execution.

On complete green evidence, create:

1. `feat: add authz package and catalog foundation` with handoff hash pending;
2. immediate docs-only `docs: backfill authz1 foundation hash` stamping the
   implementation hash into handoff and ledger.

Verify a clean tree and do not push.

The v3 prompt and dedicated v1 audit contract are already isolated in the
additional prompt-only commit `d084d9b`; do not duplicate them as uncommitted
changes in the implementation commit.
