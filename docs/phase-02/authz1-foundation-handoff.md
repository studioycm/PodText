# AUTHZ1 Foundation Handoff

Date: 2026-07-16
Contract: prompts/pre-13-prompts/authz1-foundation-codex-prompt.md v3
Prompt-only authorization commit: d084d9b
Production/local-development database/push: none

## Scope

Completed only the reversible AUTHZ1 package/schema/catalog foundation accepted
from AUTHZ1-A. Legacy users.role, UserRole ranks, Gates/macros, panel admission,
Horizon, User Resource behavior, callers, and assignments remain authoritative.
The operator-authorized v3 exception closes the pre-existing persistent-
Livewire maintenance enforcement defect without changing its declared
audience. AUTHZ1-C through AUTHZ1-I, ARCH1, SP3D, assignment backfill, authority
cutover, and role/direct-grant management remain unstarted.

## Commit hash

Pending implementation commit. The immediate docs-only stamp commit must
replace this line and the ledger's pending hash.

## Requirement classification

- Implemented — exact dependencies: added only direct
  bezhansalleh/filament-shield 4.2.0 and spatie/laravel-permission 7.3.0;
  Plugin Essentials 1.2.1 is transitive. Composer resolved no unrelated update
  or removal and reports no advisory.
- Implemented — published foundation: published only Shield config, Permission
  config, and one Permission migration. Teams, Permission's Gate hook, events,
  wildcards, Shield Super Gate/panel user/policy generation, discovery,
  management, and RolePolicy registration are disabled. The cache key is
  podtext.permission.cache.
- Implemented — reversible schema proof: a package-only SQLite :memory: test
  calls the checked-in migration object up(), down(), and up() and verifies all
  five tables, columns, unique/primary indexes, and package foreign keys. No
  broad rollback command or local MySQL probe was used.
- Implemented — dormant Shield boundary: Shield is installed/configured but
  unregistered in both panels. Neither panel exposes its Role Resource,
  navigation, pages, or routes. Production package mutation commands are
  replaced by app-owned refusal commands, including installed gaps not covered
  by Shield's helper.
- Implemented — owned catalog: added exactly 135 ordered literal abilities in
  12 groups, exact canonical entry serialization, version AUTHZ1-2026-07-16,
  and SHA-256 fb46f5ef0228c2017e049b13a6f18eb72183a85b89249385828bf5295b9193c7.
  The catalog, role definitions, compatibility manifest, and validator are
  application-owned and have no database write/sync API.
- Implemented — bilingual metadata: every group/ability has Hebrew and English
  label/description values with exact key parity, locale-specific existence,
  nonempty/no-key-return/no-fallback checks, and duplicate PHP-key scanning.
- Implemented — exact roles/grants: froze five protected role definitions;
  Super has all 135 declaratively, Admin exactly 89 allowed/46 denied, and
  Moderator/Transcriber/User exactly empty. Independent fixtures do not derive
  expected keys/entries/grants from production classes.
- Implemented — fail-closed validation: exact uppercase, wildcard, brace,
  underscore, empty-segment, duplicate/case-collision, duplicate-order,
  wrong-guard, unknown-grant, unknown-role, duplicate-role, and missing-role
  fixtures fail before an accepted result.
- Implemented — legacy no-regression matrix: the shared enum-ordered five-role
  dataset covers panel, Horizon, maintenance initial/real Livewire, legacy
  Gates in single/multi modes, User Resource direct/List/Edit/save, ordinary
  Author Resource, and Admin Tools. The full surface matrix is repeated with
  additive package definitions while assignment pivots remain empty.
- Implemented — maintenance exception: denied real public Livewire updates now
  terminate with the same maintenance response object, exact body, 503, and
  Retry-After. Super/Admin, disabled mode, initial HTTP, Admin routes, raw and
  styled output, forms/mail, CSRF rendering, cache invalidation, HE/RTL, and
  non-Livewire paths are preserved.
- Already existed and preserved: self-demotion/final-Super-Admin safeguards,
  the multi-transcription mode boundary, protected transcription behavior,
  current Resource/Page admission, importer launch behavior, and Curator's
  reference-aware individual delete.
- Deferred to AUTHZ1-D: importer per-row actor-aware create/update and queued
  reauthorization; User Resource action/save migration to a UserPolicy plus
  transactional writer; Curator bulk delete's unconditional deleteAny()
  discrepancy, which must become individual-record or equivalent reference-
  aware authorization.
- Deferred to dedicated audit v1: observed browser error UX/focus/accessibility/
  client-state handling; mixed-route bundle ordering; unused lazy/polling/
  streaming edges; broader browser/log effects. The audit is Markdown-only and
  cannot remediate.
- Not applicable: npm dependency changes, Shield setup/install/generation,
  package assignments/backfill/sync/seeding, HasRoles, production mutation,
  local development-database work, push, AUTHZ1-C–I, ARCH1, or SP3D.
- Blocked: none.

## Latest Shield documentation and source disposition

The current official [Shield plugin documentation](https://filamentphp.com/plugins/bezhansalleh-shield)
and exact [Shield 4.2.0 source](https://github.com/bezhanSalleh/filament-shield/tree/4.2.0)
were checked before and after installation. Version 4.2.0 is the approved
Laravel 13-compatible release installed here. Its normal setup documents
HasRoles, setup/generation, and panel plugin registration. Installed source
proves plugin registration always registers the writable Role Resource; hiding
navigation does not remove its routes. Those normal steps conflict with this
foundation's no-HasRoles, no-management, no-cutover boundary, so they were
deliberately not run. The supported config publish tag and destructive-command
helper were used; uncovered mutating commands received an app-owned production
guard. This is a constrained latest-docs-compatible installation, not a full
Shield authority activation.

## Evidence separation

- Official/installed-version guidance: Boost returned Laravel 13, Filament 5,
  Livewire 4, Pest, Horizon, package publishing, authorization, and persistent-
  middleware guidance; database-schema targeted only isolated SQLite. Official
  Shield/Permission/Laravel primary docs fixed the package, publish, Gate,
  cache, and migration expectations.
- Installed/tagged source: exact Shield service provider/plugin/Role Resource/
  commands/config, Permission provider/commands/config/migration, and Livewire
  persistent middleware/request handling plus Laravel response exception/
  rendering were inspected.
- Executed tests: independent catalog/schema/config/absence matrices, real
  rendered snapshots posted to the installed update endpoint, direct HTTP,
  Filament page snapshots, zero-assignment checks, and form/mail side-effect
  checks are automated.
- Controller inference: throwing HttpResponseException only after the existing
  middleware has built a denied response is the smallest app-owned way to
  prevent Livewire from discarding it. Both independent reviews found no design
  contradiction.
- Limitations: no browser observation or production probe was made. Same-route
  bundle termination is source-proven; mixed-route ordering remains an audit
  limitation. No browser DOM/UX/performance claim is made.

## Maintenance causal trace and effects

1. The Public panel registers RenderMaintenanceMode as persistent.
2. A public component snapshot stores its original path/method.
3. The installed Livewire update endpoint requires JSON plus X-Livewire.
4. Livewire reconstructs the original route and applies its persistent
   middleware before hydration, updates, calls, render, or effects.
5. The middleware's ordinary 503 was returned by Livewire's internal pipeline
   but discarded by the persistent wrapper; only redirects aborted.
6. RenderMaintenanceMode now throws Laravel's HttpResponseException with that
   exact already-built response only for the denied Livewire request.
7. Laravel renders the embedded response and internally excludes the exception
   from reporting. Initial HTTP still returns the response normally.

Directly tested planes: enabled/disabled five-role stale snapshot, exact body/
header parity, absent component effects, no public-form row/mail side effect,
Admin real Livewire pages, initial public/Admin paths, and Horizon. Source-
proven planes: middleware ordering, web/session/auth stack, same-route bundle
abort, and non-reporting. Inferred/deferred planes: browser dialog/retry/focus/
unsaved state, mixed-route bundles, and unused lazy/polling/streaming behavior.

## Files changed

- Dependencies/schema/config: composer.json, composer.lock,
  config/filament-shield.php, config/permission.php, and the new Permission
  migration.
- Catalog/runtime boundary: six files under app/Auth; two command-guard files
  under app/Support/Authorization; and app/Providers/AppServiceProvider.php.
- Narrow maintenance correction:
  app/Http/Middleware/RenderMaintenanceMode.php.
- Localization: lang/en/authz.php and lang/he/authz.php.
- Tests/fixture: tests/Pest.php, the independent Authz fixture,
  AuthzFoundationCatalogTest, AuthzPackageFoundationTest,
  LegacyAuthorizationMatrixTest, PanelAuthHardeningTest, and
  PublicMaintenanceModeTest.
- Research/restart records: AUTHZ1 research/plan, pending decision queue,
  current project state, the step ledger, and this handoff.
- Prompt scope was committed separately in d084d9b: active v3, future audit v1,
  and prompts/README.md.

## Tests added or updated

- Catalog: exact independent 135 vector/full entries/hash; immutability; invalid
  fixtures; roles/grants; HE/EN parity and duplicate keys.
- Package: config/plugin/resource/trait absence; production command guards;
  definition-only package rows; empty assignment pivots; migration up/down/up.
- Legacy surfaces: 50-test shared five-role/package-definition matrix for panel,
  Gates, Author, Admin Tools, and User Resource direct/real-Livewire/save paths.
- Horizon: exact five-role direct Gate and HTTP matrix with/without package
  definitions.
- Maintenance: exact five-role initial and real-update paths with/without
  package definitions; disabled updates; byte-equal body/header; no effects or
  DB/mail side effects; all existing maintenance/form/cache/HE/RTL regressions.

## Command and result record

- command -v rg: found; repository discovery used rg/rg --files.
- Startup status/log/ancestor checks and complete instruction/prompt/docs/source
  reads: stale fd39adc expectation was resolved to committed prompt baseline
  6339e85; no unrelated initial change.
- composer validate --strict: green with expected warnings that the operator-
  required exact constraints are pinned.
- composer audit --format=plain: green, no advisories; unwritable user-cache
  warnings were environmental and Composer continued without cache.
- Initial sequential SQLite authorization/Horizon/maintenance baseline:
  26 tests / 243 assertions green.
- Exact Composer dry run: three installs, zero updates/removals.
- One attempted two-package composer show command used an invalid second
  positional argument, failed non-mutating, and was replaced by individual
  installed-version checks.
- Exact no-scripts/minimal-changes Composer require installed only the two
  approved direct packages and transitive Essentials.
- Narrow Laravel package discovery completed after lock/source review.
- The three vendor:publish tag commands published only Shield config,
  Permission config, and Permission migration. No setup/generation/migration
  command ran.
- Initial catalog test: 15 tests / 2,547 assertions green.
- Initial package foundation test: 4 / 91 green.
- Initial Horizon five-role test: 6 / 12 green.
- Pre-fix real maintenance update run: 11 tests; 8 green, three denied roles
  incorrectly returned 200. This is the retained causal failure.
- Prompt/version/diff checks passed; prompt-only staging contained three files;
  the requested prompt commit created d084d9b.
- First post-fix maintenance run: 29 tests; 25 green, four assertion failures
  because unsupported 2/3-hour fixture values normalized to 24 hours.
- Supported-six-hour rerun: 29 tests / 266 assertions green.
- First legacy matrix run: 50 tests; panel/Gates 20 green and 30 snapshot-name
  extractor errors. Installed Filament class names replaced guessed aliases.
- Final legacy matrix: 50 tests / 372 assertions green.
- Expanded final maintenance matrix: 44 / 444 green.
- Combined panel/package/catalog/ROLES1 regression: 41 / 2,782 green.
- Final Composer validation/audit: green with the same intentional pin/cache
  warnings and no advisories.
- First canonical `vendor/bin/pint --test`: failed on five mechanical files
  (import ordering/spacing, strict-type formatting, and one unused import).
  `vendor/bin/pint` applied only those formatter changes; the canonical gate
  then restarted from Pint.
- First full `php artisan test` after green Pint/FilaCheck/build: 685 tests,
  677 passed and eight browser tests failed/risky because sandboxed Chromium
  could not register its Mach port (`Permission denied (1100)`). The feature
  suite was green. This infrastructure-only result was retained; after this
  handoff update, the entire gate restarted from Pint and the full suite used
  the approved unsandboxed runner without interruption.
- First staged `git diff --cached --check`: failed on five Markdown hard-break
  trailing spaces in the new research/plan headers. They were removed, the
  staged diff check was rerun, and the entire gate restarted from Pint because
  tracked files changed after the prior full-suite pass.
- Read-only rg, sed, git diff/show/status/log, Composer version/source, and
  vendor-source inspections made no repository/data change.
- Final ordered gate on the final tracked implementation state: requirements
  sweep passed; vendor/bin/pint --test passed; vendor/bin/filacheck passed;
  npm run build passed; full php artisan test passed last without interruption
  or parallelization.

## Tool, skill, and delegated evidence

- Boost application-info, installed-version search-docs, and explicit isolated-
  SQLite database-schema were used. FilamentExamples was queried in decomposed/
  refined batches; it exposed search results with returned source snippets but
  no separate detail/read tool.
- Applied skills: filament-security-audit, laravel-best-practices,
  spatie-laravel-php, pest-testing, filament-forms-ux-audit,
  configuring-horizon, and, after v3 authorization, livewire-development.
  Performance and Tailwind triggers did not enter scope.
- Evidence-only workers covered package/schema, catalog/manifest, current
  surfaces, plan, installed source, security, tests, monitoring, and context.
  Material v3 reviews: AUDT-02 found the real maintenance defect; AUDT-03 was
  GREEN on the one-file boundary; TEST-02 was AMBER only for missing assertions
  that were added; MON-02 was GREEN; CTX-03 retained browser/mixed-route/lazy
  limitations. Workers made no repository changes.

## Rollback and authority boundary

Before a future cutover, rollback is limited to removing the owned catalog/
guard/config code and Composer requirements plus applying the published package
migration's down() on an explicitly approved target. No legacy role data,
assignments, policies, or authority were projected into package tables. The
maintenance correction can be reverted independently to its prior middleware
return behavior, though that would restore the proven stale-snapshot bypass.

## Local Front Check Report

1. Sign in as a Super Admin, open /admin, and expect the dashboard to load.
2. Sign in as an Admin, open /admin, and expect ordinary Admin access as before.
3. Sign in as Moderator, Transcriber, and User in turn, open /admin, and expect
   access to remain denied.
4. Open Admin navigation as Super Admin and Admin, search for role, permission,
   or Shield management, and expect no Shield Role Resource, assignment page,
   or direct-grant UI.
5. As Super Admin, open Users and edit an ordinary user; expect the existing
   role editor and self/final-Super safeguards to remain.
6. As Admin, open the Users index/edit URLs directly and expect 403 responses.
7. As Super Admin and Admin, open /horizon and expect access; repeat as the
   three lower roles and expect denial.
8. With maintenance disabled, load a public podcast page as each role, interact
   with its search/filter component, and expect normal Livewire updates.
9. Load a public podcast page as Moderator, then enable maintenance in another
   Admin session and trigger the already-open page's Livewire interaction;
   expect maintenance HTML with HTTP 503/Retry-After, not updated content.
10. Repeat the stale-page check as Super Admin and Admin and expect the existing
    public interaction to continue.
11. While maintenance is enabled, open the maintenance contact form and submit
    its normal plain-post flow; expect existing validation/OTP/success behavior.
12. Run future audit v1 only after review if browser error-dialog, focus, retry,
    mixed-bundle, lazy, or polling effects need formal observation.

## Assumptions, deferrals, and final status

- Exact pins are intentional operator constraints despite Composer's general
  semantic-version warning.
- Shield's absence from panel plugin registries is required by installed source
  and the no-management/no-HasRoles boundary.
- Browser UX was not observed and no browser claim is made.
- The dedicated maintenance audit prompt is prepared, not executed.
- AUTHZ1-C analyzer/backfill and all later authority slices require new
  acceptance.
- Implementation commit hash is pending only until the canonical commit is
  created; the immediate docs-only stamp leaves no hash debt.
- No production action, local development-database access, push, npm dependency
  change, or secret/local configuration occurred.
